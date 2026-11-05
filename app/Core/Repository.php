<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/config.php';

/**
 * CebuTech Hub - Data Repository & Authoritative Business Logic
 * Uses SQLite (PDO) backed by database/schema.sql & database/seed.sql.
 * Also supports JSON-file storage mirror/fallback for portability.
 */
class Repository
{
    private ?PDO $pdo = null;
    private bool $useJsonFallback = false;
    private string $storageEngineName = 'SQLite 3 (PDO)';

    private static ?Repository $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0775, true);
        }

        if (extension_loaded('pdo_sqlite')) {
            $isNewDb = !file_exists(SQLITE_DB_PATH) || filesize(SQLITE_DB_PATH) === 0;
            $this->pdo = new PDO('sqlite:' . SQLITE_DB_PATH, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->pdo->exec('PRAGMA busy_timeout = 5000;');

            if ($isNewDb || !$this->hasTables()) {
                $this->initializeSqliteDatabase();
            }
        } else {
            // Automatic fallback if someone runs PHP without pdo_sqlite enabled
            $this->useJsonFallback = true;
            $this->storageEngineName = 'Server-Side JSON Storage (File-Locked)';
            if (!file_exists(JSON_DB_PATH)) {
                $this->initializeJsonDatabase();
            }
        }
    }

    public function getStorageEngineName(): string
    {
        return $this->storageEngineName;
    }

    private function hasTables(): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
            if (!$stmt->fetch()) {
                return false;
            }
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            return $count > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function initializeSqliteDatabase(): void
    {
        $schemaSql = file_get_contents(BASE_PATH . '/database/schema.sql');
        $seedSql   = file_get_contents(BASE_PATH . '/database/seed.sql');

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec($schemaSql);
            $this->pdo->exec($seedSql);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reset and re-seed the entire database back to its initial assessment state
     */
    public function resetDatabase(): void
    {
        if (!$this->useJsonFallback && $this->pdo) {
            $this->pdo->exec('PRAGMA foreign_keys = OFF;');
            $this->pdo->exec('DROP TABLE IF EXISTS activity_notifications;');
            $this->pdo->exec('DROP TABLE IF EXISTS order_items;');
            $this->pdo->exec('DROP TABLE IF EXISTS orders;');
            $this->pdo->exec('DROP TABLE IF EXISTS products;');
            $this->pdo->exec('DROP TABLE IF EXISTS categories;');
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->initializeSqliteDatabase();
        } else {
            $this->initializeJsonDatabase();
        }
    }

    // ========================================================================
    // NOTIFICATIONS & MONITORING METHODS
    // ========================================================================

    public function addNotification(string $type, string $title, string $message, string $link = ''): void
    {
        if ($this->useJsonFallback) {
            $db = $this->readJsonDb();
            $maxId = 0;
            foreach ($db['activity_notifications'] ?? [] as $n) {
                $maxId = max($maxId, (int)$n['id']);
            }
            $db['activity_notifications'][] = [
                'id'         => $maxId + 1,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'link'       => $link,
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $this->writeJsonDb($db);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_notifications (type, title, message, link, is_read, created_at)
                VALUES (:type, :title, :message, :link, 0, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':type'    => $type,
                ':title'   => $title,
                ':message' => $message,
                ':link'    => $link,
            ]);
        } catch (Throwable $e) {
            // Non-critical logging failure
        }
    }

    public function getNotifications(int $limit = 20): array
    {
        if ($this->useJsonFallback) {
            $db = $this->readJsonDb();
            $notifs = $db['activity_notifications'] ?? [];
            usort($notifs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']) ?: ($b['id'] <=> $a['id']));
            return array_slice($notifs, 0, $limit);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM activity_notifications
                ORDER BY created_at DESC, id DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['id']      = (int)$r['id'];
                $r['is_read'] = (int)$r['is_read'];
            }
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getUnreadNotificationCount(): int
    {
        if ($this->useJsonFallback) {
            $db = $this->readJsonDb();
            $count = 0;
            foreach ($db['activity_notifications'] ?? [] as $n) {
                if ((int)$n['is_read'] === 0) {
                    $count++;
                }
            }
            return $count;
        }

        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM activity_notifications WHERE is_read = 0")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function markAllNotificationsAsRead(): void
    {
        if ($this->useJsonFallback) {
            $db = $this->readJsonDb();
            foreach ($db['activity_notifications'] ?? [] as &$n) {
                $n['is_read'] = 1;
            }
            $this->writeJsonDb($db);
            return;
        }

        try {
            $this->pdo->exec("UPDATE activity_notifications SET is_read = 1 WHERE is_read = 0");
        } catch (Throwable $e) {
            // Ignore
        }
    }

    // ========================================================================
    // CATEGORY METHODS (Section 13)
    // ========================================================================

    public function getCategories(): array
    {
        if ($this->useJsonFallback) {
            return $this->jsonGetCategories();
        }

        $sql = "
            SELECT 
                c.*,
                COUNT(p.id) AS product_count,
                SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) AS active_product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id
            ORDER BY c.id ASC
        ";
        $rows = $this->pdo->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['product_count'] = (int)($row['product_count'] ?? 0);
            $row['active_product_count'] = (int)($row['active_product_count'] ?? 0);
        }
        return $rows;
    }

    public function getCategoryById(int $id): ?array
    {
        foreach ($this->getCategories() as $cat) {
            if ((int)$cat['id'] === $id) {
                return $cat;
            }
        }
        return null;
    }

    public function createCategory(string $name, string $description = ''): array
    {
        $name = trim($name);
        $description = trim($description);

        if ($name === '') {
            return ['success' => false, 'error' => 'Category name is required and cannot be blank.'];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'error' => 'Category name must not exceed 100 characters.'];
        }

        // Case-insensitive duplicate check (Section 13: "prevent duplicate names ignoring case")
        foreach ($this->getCategories() as $existing) {
            if (strtolower(trim($existing['name'])) === strtolower($name)) {
                return ['success' => false, 'error' => "A category named \"{$existing['name']}\" already exists (case-insensitive check)."];
            }
        }

        $slug = slugify($name);

        if ($this->useJsonFallback) {
            return $this->jsonCreateCategory($name, $slug, $description);
        }

        $stmt = $this->pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :desc)");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $description,
        ]);

        return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
    }

    public function updateCategory(int $id, string $name, string $description = ''): array
    {
        $name = trim($name);
        $description = trim($description);

        $current = $this->getCategoryById($id);
        if (!$current) {
            return ['success' => false, 'error' => 'Category not found.'];
        }
        if ($name === '') {
            return ['success' => false, 'error' => 'Category name is required and cannot be blank.'];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'error' => 'Category name must not exceed 100 characters.'];
        }

        foreach ($this->getCategories() as $existing) {
            if ((int)$existing['id'] !== $id && strtolower(trim($existing['name'])) === strtolower($name)) {
                return ['success' => false, 'error' => "Another category named \"{$existing['name']}\" already exists (case-insensitive check)."];
            }
        }

        $slug = slugify($name);

        if ($this->useJsonFallback) {
            return $this->jsonUpdateCategory($id, $name, $slug, $description);
        }

        $stmt = $this->pdo->prepare("UPDATE categories SET name = :name, slug = :slug, description = :desc WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $description,
            ':id'   => $id,
        ]);

        return ['success' => true];
    }

    public function deleteCategory(int $id): array
    {
        $category = $this->getCategoryById($id);
        if (!$category) {
            return ['success' => false, 'error' => 'Category not found.'];
        }

        // Section 13: "Block deletion while any product is assigned to the category; explain why."
        if ((int)$category['product_count'] > 0) {
            $count = (int)$category['product_count'];
            return [
                'success' => false,
                'error'   => "Cannot delete category \"{$category['name']}\" because {$count} product(s) are currently assigned to it. Please reassign or remove those products before deleting this category.",
            ];
        }

        if ($this->useJsonFallback) {
            return $this->jsonDeleteCategory($id);
        }

        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return ['success' => true];
    }

    // ========================================================================
    // PRODUCT METHODS (Sections 6, 7, 12)
    // ========================================================================

    /**
     * Enrich product row with derived status and integer types
     */
    private function enrichProductRow(array $row): array
    {
        $row['id']                    = (int)$row['id'];
        $row['category_id']           = (int)$row['category_id'];
        $row['price_cents']           = (int)$row['price_cents'];
        $row['stock']                 = (int)$row['stock'];
        $row['is_active']             = (int)$row['is_active'];
        $row['is_featured']           = (int)($row['is_featured'] ?? 0);
        $row['order_reference_count'] = (int)($row['order_reference_count'] ?? 0);

        // Derived availability status (Section 6 & Section 12):
        // "Stock availability is derived: an active product with zero stock displays Out of Stock."
        if ($row['is_active'] === 0) {
            $row['display_status'] = 'Inactive';
            $row['status_badge']   = 'inactive';
            $row['can_order']      = false;
        } elseif ($row['stock'] <= 0) {
            $row['display_status'] = 'Out of Stock';
            $row['status_badge']   = 'out-of-stock';
            $row['can_order']      = false;
        } else {
            $row['display_status'] = 'In Stock';
            $row['status_badge']   = 'in-stock';
            $row['can_order']      = true;
        }

        return $row;
    }

    /**
     * Retrieve products with combined search, category filter, status filter, and price sort
     */
    public function getProducts(array $filters = []): array
    {
        if ($this->useJsonFallback) {
            return $this->jsonGetProducts($filters);
        }

        $where = ['1 = 1'];
        $params = [];

        // Customer listing hides inactive products (Section 6)
        if (!empty($filters['customer_only'])) {
            $where[] = 'p.is_active = 1';
        }

        if (!empty($filters['featured_only'])) {
            $where[] = 'p.is_featured = 1';
        }

        // Case-insensitive product-name search (Section 6 & 12)
        if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
            $searchTerm = '%' . strtolower(trim((string)$filters['search'])) . '%';
            $where[] = 'LOWER(p.name) LIKE :search';
            $params[':search'] = $searchTerm;
        }

        // Category filter
        if (!empty($filters['category_id']) && (int)$filters['category_id'] > 0) {
            $where[] = 'p.category_id = :category_id';
            $params[':category_id'] = (int)$filters['category_id'];
        }

        // Admin displayed status filter (Section 12: "Filter by category and displayed status")
        if (!empty($filters['status_filter'])) {
            switch ($filters['status_filter']) {
                case 'in_stock':
                    $where[] = 'p.is_active = 1 AND p.stock > 0';
                    break;
                case 'out_of_stock':
                    $where[] = 'p.is_active = 1 AND p.stock = 0';
                    break;
                case 'inactive':
                    $where[] = 'p.is_active = 0';
                    break;
            }
        }

        // Price sorting (Section 6: low to high and high to low, working together with search & filter)
        $sort = $filters['sort'] ?? 'default';
        switch ($sort) {
            case 'price_asc':
                $orderBy = 'p.price_cents ASC, p.id ASC';
                break;
            case 'price_desc':
                $orderBy = 'p.price_cents DESC, p.id ASC';
                break;
            case 'name_asc':
                $orderBy = 'LOWER(p.name) ASC';
                break;
            default:
                $orderBy = 'p.id ASC';
                break;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT 
                p.*,
                c.name AS category_name,
                c.slug AS category_slug,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) AS order_reference_count
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map([$this, 'enrichProductRow'], $rows);
    }

    public function getProductById(int $id): ?array
    {
        if ($this->useJsonFallback) {
            return $this->jsonGetProductById($id);
        }

        $sql = "
            SELECT 
                p.*,
                c.name AS category_name,
                c.slug AS category_slug,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) AS order_reference_count
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.id = :id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->enrichProductRow($row) : null;
    }

    /**
     * Section 7: "Show up to three related active products from the same category, excluding the current product."
     */
    public function getRelatedProducts(int $productId, int $categoryId, int $limit = 3): array
    {
        $allActiveInCategory = $this->getProducts([
            'customer_only' => true,
            'category_id'   => $categoryId,
        ]);

        $related = [];
        foreach ($allActiveInCategory as $prod) {
            if ($prod['id'] !== $productId) {
                $related[] = $prod;
                if (count($related) >= $limit) {
                    break;
                }
            }
        }
        return $related;
    }

    /**
     * Validate and create/update a product (Section 12)
     */
    public function saveProduct(array $input, ?int $id = null): array
    {
        $errors = [];

        $name        = trim((string)($input['name'] ?? ''));
        $categoryId  = (int)($input['category_id'] ?? 0);
        $description = trim((string)($input['description'] ?? ''));
        $priceInput  = trim((string)($input['price'] ?? ''));
        $stockInput  = trim((string)($input['stock'] ?? ''));
        $imageUrl    = trim((string)($input['image_url'] ?? ''));
        $isActive    = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;
        $isFeatured  = isset($input['is_featured']) && (string)$input['is_featured'] === '1' ? 1 : 0;

        if ($name === '') {
            $errors[] = 'Product name is required.';
        } elseif (mb_strlen($name) > 180) {
            $errors[] = 'Product name must not exceed 180 characters.';
        }

        if ($categoryId <= 0 || !$this->getCategoryById($categoryId)) {
            $errors[] = 'Please select a valid product category.';
        }

        if ($description === '') {
            $errors[] = 'Product description is required.';
        }

        // Price validation: must be greater than zero
        $priceCents = parsePriceToCents($priceInput);
        if ($priceCents === null || $priceCents <= 0) {
            $errors[] = 'Price is required and must be a valid amount greater than PHP 0.00 (up to 2 decimal places).';
        }

        // Stock validation: must be a whole-number stock quantity of zero or more
        if ($stockInput === '' || !preg_match('/^\d+$/', $stockInput)) {
            $errors[] = 'Stock quantity must be a whole number of 0 or more (no decimals or negative numbers).';
        }
        $stock = (int)$stockInput;

        if ($imageUrl === '') {
            $errors[] = 'Product image URL or bundled image path is required.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $slug = slugify($name);

        if ($this->useJsonFallback) {
            return $this->jsonSaveProduct([
                'name'        => $name,
                'slug'        => $slug,
                'category_id' => $categoryId,
                'description' => $description,
                'price_cents' => $priceCents,
                'stock'       => $stock,
                'is_active'   => $isActive,
                'is_featured' => $isFeatured,
                'image_url'   => $imageUrl,
            ], $id);
        }

        if ($id === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO products (category_id, name, slug, description, price_cents, stock, is_active, is_featured, image_url, created_at, updated_at)
                VALUES (:category_id, :name, :slug, :description, :price_cents, :stock, :is_active, :is_featured, :image_url, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name'        => $name,
                ':slug'        => $slug,
                ':description' => $description,
                ':price_cents' => $priceCents,
                ':stock'       => $stock,
                ':is_active'   => $isActive,
                ':is_featured' => $isFeatured,
                ':image_url'   => $imageUrl,
            ]);
            $newId = (int)$this->pdo->lastInsertId();
            $this->addNotification('product', 'Product Created', "New product \"{$name}\" was added with stock {$stock}.", "products.php?search=" . urlencode($name));
            return ['success' => true, 'id' => $newId];
        } else {
            $existing = $this->getProductById($id);
            if (!$existing) {
                return ['success' => false, 'errors' => ['Product not found.']];
            }
            $stmt = $this->pdo->prepare("
                UPDATE products
                SET category_id = :category_id,
                    name = :name,
                    slug = :slug,
                    description = :description,
                    price_cents = :price_cents,
                    stock = :stock,
                    is_active = :is_active,
                    is_featured = :is_featured,
                    image_url = :image_url,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name'        => $name,
                ':slug'        => $slug,
                ':description' => $description,
                ':price_cents' => $priceCents,
                ':stock'       => $stock,
                ':is_active'   => $isActive,
                ':is_featured' => $isFeatured,
                ':image_url'   => $imageUrl,
                ':id'          => $id,
            ]);

            $changeNotes = [];
            if ($existing['name'] !== $name) $changeNotes[] = "renamed from \"{$existing['name']}\"";
            if ($existing['price_cents'] !== $priceCents) $changeNotes[] = "price changed to " . formatPhp($priceCents);
            if ($existing['stock'] !== $stock) $changeNotes[] = "stock updated to {$stock}";
            if ($existing['is_active'] !== $isActive) $changeNotes[] = $isActive ? "activated" : "deactivated";
            $summary = empty($changeNotes) ? "updated details" : implode(', ', $changeNotes);

            $this->addNotification('product', 'Product Updated', "Product \"{$name}\": {$summary}.", "products.php?search=" . urlencode($name));

            return ['success' => true, 'id' => $id];
        }
    }

    /**
     * Section 12: "Block hard deletion of a product referenced by an order and offer to mark it inactive instead. Preserve order history."
     */
    public function deleteProduct(int $id): array
    {
        $product = $this->getProductById($id);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        if ($product['order_reference_count'] > 0) {
            $refCount = $product['order_reference_count'];
            return [
                'success'        => false,
                'can_deactivate' => true,
                'product_id'     => $id,
                'product_name'   => $product['name'],
                'error'          => "Cannot permanently delete \"{$product['name']}\" because it is referenced in {$refCount} order line(s). To preserve historical order data, please mark this product as Inactive instead.",
            ];
        }

        if ($this->useJsonFallback) {
            return $this->jsonDeleteProduct($id);
        }

        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return ['success' => true];
    }

    public function deactivateProduct(int $id): array
    {
        $product = $this->getProductById($id);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        if ($this->useJsonFallback) {
            return $this->jsonDeactivateProduct($id);
        }

        $stmt = $this->pdo->prepare("UPDATE products SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->addNotification('product', 'Product Deactivated', "Product \"{$product['name']}\" was marked as Inactive (hidden from customers).", "products.php?status_filter=inactive");

        return ['success' => true, 'product_name' => $product['name']];
    }

    public function activateProduct(int $id): array
    {
        $product = $this->getProductById($id);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        if ($this->useJsonFallback) {
            return $this->jsonActivateProduct($id);
        }

        $stmt = $this->pdo->prepare("UPDATE products SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->addNotification('product', 'Product Activated', "Product \"{$product['name']}\" was marked Active and is now visible on the customer storefront.", "products.php?status_filter=in_stock");

        return ['success' => true, 'product_name' => $product['name']];
    }

    // ========================================================================
    // CHECKOUT & ORDER PLACEMENT (Sections 9, 10)
    // ========================================================================

    /**
     * Validate customer checkout fields (Section 9)
     */
    public function validateCheckoutFields(array $input): array
    {
        $errors = [];

        $customerName    = trim((string)($input['customer_name'] ?? ''));
        $customerEmail   = trim((string)($input['customer_email'] ?? ''));
        $contactNumber   = trim((string)($input['contact_number'] ?? ''));
        $deliveryAddress = trim((string)($input['delivery_address'] ?? ''));
        $paymentMethod   = trim((string)($input['payment_method'] ?? ''));
        $orderNotes      = trim((string)($input['order_notes'] ?? ''));

        if ($customerName === '') {
            $errors['customer_name'] = 'Customer name is required and cannot be blank or whitespace only.';
        } elseif (mb_strlen($customerName) > 150) {
            $errors['customer_name'] = 'Customer name must not exceed 150 characters.';
        }

        if ($customerEmail === '') {
            $errors['customer_email'] = 'Email address is required.';
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'Please enter a valid email address (e.g., name@example.com).';
        }

        // Contact number: allow common phone formatting (+63, spaces, hyphens, parentheses) without accepting empty or non-digits
        $digitsOnly = preg_replace('/\D+/', '', $contactNumber);
        if ($contactNumber === '') {
            $errors['contact_number'] = 'Contact number is required.';
        } elseif (!preg_match('/^[+\d][\d\s\-().]{6,25}$/', $contactNumber) || strlen((string)$digitsOnly) < 7 || strlen((string)$digitsOnly) > 15) {
            $errors['contact_number'] = 'Please enter a valid phone number (7 to 15 digits; spaces, +, hyphens, and parentheses are allowed).';
        }

        if ($deliveryAddress === '') {
            $errors['delivery_address'] = 'Delivery address is required and cannot be blank.';
        }

        if ($paymentMethod === '' || !in_array($paymentMethod, PAYMENT_METHODS, true)) {
            $errors['payment_method'] = 'Please select a valid payment method (Cash on Delivery, E-Wallet, or Bank Transfer).';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'clean'  => [
                'customer_name'    => $customerName,
                'customer_email'   => $customerEmail,
                'normalized_email' => normalizeEmail($customerEmail),
                'contact_number'   => $contactNumber,
                'delivery_address' => $deliveryAddress,
                'payment_method'   => $paymentMethod,
                'order_notes'      => $orderNotes,
            ],
        ];
    }

    /**
     * Place an order atomically:
     * 1. Validates customer fields
     * 2. Rechecks product availability, price, and stock against shared data source
     * 3. Saves order & order_items snapshots
     * 4. Reduces stock
     */
    public function placeOrder(array $customerInput, array $cartItems): array
    {
        if (empty($cartItems)) {
            return [
                'success' => false,
                'errors'  => ['Your shopping cart is empty. Please add items before checking out.'],
            ];
        }

        $validation = $this->validateCheckoutFields($customerInput);
        $fieldErrors = $validation['errors'];

        if ($this->useJsonFallback) {
            return $this->jsonPlaceOrder($validation, $cartItems);
        }

        $this->pdo->beginTransaction();
        try {
            $changesDetected = [];
            $verifiedLines   = [];
            $subtotalCents   = 0;

            foreach ($cartItems as $productId => $cartEntry) {
                $pid = (int)$productId;
                $qty = (int)($cartEntry['quantity'] ?? 0);
                $expectedPriceCents = isset($cartEntry['snapshot_price_cents']) ? (int)$cartEntry['snapshot_price_cents'] : null;
                $expectedName       = (string)($cartEntry['snapshot_name'] ?? "Product #{$pid}");

                if ($qty <= 0) {
                    $changesDetected[] = "Invalid quantity ({$qty}) for \"{$expectedName}\". Quantity must be a positive whole number.";
                    continue;
                }

                $liveProduct = $this->getProductById($pid);

                // Check 1: Does product still exist and is it active?
                if (!$liveProduct || $liveProduct['is_active'] === 0) {
                    $pName = $liveProduct['name'] ?? $expectedName;
                    $changesDetected[] = "\"{$pName}\" is no longer available and cannot be ordered. Please remove it from your cart.";
                    continue;
                }

                // Check 2: Did the unit price change since it was added/viewed?
                if ($expectedPriceCents !== null && $liveProduct['price_cents'] !== $expectedPriceCents) {
                    $oldFormatted = formatPhp($expectedPriceCents);
                    $newFormatted = formatPhp($liveProduct['price_cents']);
                    $changesDetected[] = "The price of \"{$liveProduct['name']}\" changed from {$oldFormatted} to {$newFormatted}. Please review the updated total before submitting again.";
                }

                // Check 3: Is stock sufficient?
                if ($liveProduct['stock'] <= 0) {
                    $changesDetected[] = "\"{$liveProduct['name']}\" is now Out of Stock and cannot be ordered.";
                    continue;
                }

                if ($qty > $liveProduct['stock']) {
                    $changesDetected[] = "Insufficient stock for \"{$liveProduct['name']}\": you requested {$qty}, but only {$liveProduct['stock']} unit(s) remain in stock.";
                    continue;
                }

                $lineTotalCents = $liveProduct['price_cents'] * $qty;
                $subtotalCents += $lineTotalCents;

                $verifiedLines[] = [
                    'product_id'               => $liveProduct['id'],
                    'product_name_snapshot'    => $liveProduct['name'],
                    'unit_price_cents_snapshot'=> $liveProduct['price_cents'],
                    'quantity'                 => $qty,
                    'line_total_cents'         => $lineTotalCents,
                    'current_stock'            => $liveProduct['stock'],
                ];
            }

            if (!empty($changesDetected) || !empty($fieldErrors)) {
                $this->pdo->rollBack();
                return [
                    'success'          => false,
                    'changes_detected' => !empty($changesDetected),
                    'changes'          => $changesDetected,
                    'field_errors'     => $fieldErrors,
                    'errors'           => array_merge(array_values($fieldErrors), $changesDetected),
                ];
            }

            // Subtotal equals total per Section 8 ("subtotal equals total: no shipping fee, tax, voucher, or service charge")
            $totalCents = $subtotalCents;
            $clean = $validation['clean'];

            // Generate unique order number
            $orderNumber = $this->generateUniqueOrderNumber();

            $stmtOrder = $this->pdo->prepare("
                INSERT INTO orders (
                    order_number, customer_name, customer_email, normalized_email,
                    contact_number, delivery_address, payment_method, order_notes,
                    subtotal_cents, total_cents, status, stock_restored, created_at, updated_at
                ) VALUES (
                    :order_number, :customer_name, :customer_email, :normalized_email,
                    :contact_number, :delivery_address, :payment_method, :order_notes,
                    :subtotal_cents, :total_cents, 'Pending', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");
            $stmtOrder->execute([
                ':order_number'     => $orderNumber,
                ':customer_name'    => $clean['customer_name'],
                ':customer_email'   => $clean['customer_email'],
                ':normalized_email' => $clean['normalized_email'],
                ':contact_number'   => $clean['contact_number'],
                ':delivery_address' => $clean['delivery_address'],
                ':payment_method'   => $clean['payment_method'],
                ':order_notes'      => $clean['order_notes'],
                ':subtotal_cents'   => $subtotalCents,
                ':total_cents'      => $totalCents,
            ]);

            $orderId = (int)$this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name_snapshot,
                    unit_price_cents_snapshot, quantity, line_total_cents
                ) VALUES (
                    :order_id, :product_id, :product_name_snapshot,
                    :unit_price_cents_snapshot, :quantity, :line_total_cents
                )
            ");

            $stmtStock = $this->pdo->prepare("
                UPDATE products
                SET stock = stock - :qty, updated_at = CURRENT_TIMESTAMP
                WHERE id = :product_id AND stock >= :qty
            ");

            foreach ($verifiedLines as $line) {
                $stmtItem->execute([
                    ':order_id'                  => $orderId,
                    ':product_id'                => $line['product_id'],
                    ':product_name_snapshot'     => $line['product_name_snapshot'],
                    ':unit_price_cents_snapshot' => $line['unit_price_cents_snapshot'],
                    ':quantity'                  => $line['quantity'],
                    ':line_total_cents'          => $line['line_total_cents'],
                ]);

                $stmtStock->execute([
                    ':qty'        => $line['quantity'],
                    ':product_id' => $line['product_id'],
                ]);

                if ($stmtStock->rowCount() !== 1) {
                    throw new RuntimeException("Stock reduction failed for {$line['product_name_snapshot']}.");
                }
            }

            $this->pdo->commit();

            // Order monitoring notification
            $formattedTotal = formatPhp($totalCents);
            $this->addNotification('order', 'New Order Placed', "Order {$orderNumber} by {$clean['customer_name']} ({$formattedTotal}, {$clean['payment_method']}).", "order.php?id={$orderId}");

            // Check if any product dropped to 0 stock
            foreach ($verifiedLines as $line) {
                $rem = $line['current_stock'] - $line['quantity'];
                if ($rem === 0) {
                    $this->addNotification('product', 'Stock Depleted (Out of Stock)', "Product \"{$line['product_name_snapshot']}\" is now OUT OF STOCK (0 units remaining).", "products.php?status_filter=out_of_stock");
                } elseif ($rem <= 3) {
                    $this->addNotification('product', 'Low Stock Warning', "Product \"{$line['product_name_snapshot']}\" is running low ({$rem} units left).", "products.php?search=" . urlencode($line['product_name_snapshot']));
                }
            }

            return [
                'success'      => true,
                'order_id'     => $orderId,
                'order_number' => $orderNumber,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'errors'  => ['An unexpected error occurred while saving your order: ' . $e->getMessage()],
            ];
        }
    }

    private function generateUniqueOrderNumber(): string
    {
        $datePart = date('Ymd');
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $seq = 1001 + $count;
        $candidate = "ORD-{$datePart}-{$seq}";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = :num");
        while (true) {
            $stmt->execute([':num' => $candidate]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $candidate;
            }
            $seq++;
            $candidate = "ORD-{$datePart}-{$seq}";
        }
    }

    // ========================================================================
    // ORDER MANAGEMENT & STATUS TRANSITIONS (Sections 10, 14)
    // ========================================================================

    public function getOrders(?string $statusFilter = null, ?string $search = null): array
    {
        if ($this->useJsonFallback) {
            return $this->jsonGetOrders($statusFilter, $search);
        }

        $where = ['1 = 1'];
        $params = [];

        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            $where[] = 'o.status = :status';
            $params[':status'] = $statusFilter;
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . strtolower(trim($search)) . '%';
            $where[] = '(LOWER(o.order_number) LIKE :term OR LOWER(o.customer_name) LIKE :term OR LOWER(o.customer_email) LIKE :term)';
            $params[':term'] = $term;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "
            SELECT 
                o.*,
                (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id) AS total_units,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS line_count
            FROM orders o
            WHERE {$whereSql}
            ORDER BY o.created_at DESC, o.id DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id']             = (int)$row['id'];
            $row['subtotal_cents'] = (int)$row['subtotal_cents'];
            $row['total_cents']    = (int)$row['total_cents'];
            $row['stock_restored'] = (int)$row['stock_restored'];
            $row['total_units']    = (int)($row['total_units'] ?? 0);
            $row['line_count']     = (int)($row['line_count'] ?? 0);
        }
        return $rows;
    }

    public function getOrderById(int $id): ?array
    {
        if ($this->useJsonFallback) {
            return $this->jsonGetOrderById($id);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        return $this->hydrateOrderWithItems($order);
    }

    public function getOrderByNumber(string $orderNumber): ?array
    {
        if ($this->useJsonFallback) {
            return $this->jsonGetOrderByNumber($orderNumber);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE UPPER(order_number) = UPPER(:num) LIMIT 1");
        $stmt->execute([':num' => trim($orderNumber)]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        return $this->hydrateOrderWithItems($order);
    }

    private function hydrateOrderWithItems(array $order): array
    {
        $order['id']             = (int)$order['id'];
        $order['subtotal_cents'] = (int)$order['subtotal_cents'];
        $order['total_cents']    = (int)$order['total_cents'];
        $order['stock_restored'] = (int)$order['stock_restored'];

        $stmtItems = $this->pdo->prepare("
            SELECT 
                oi.*,
                p.slug AS current_product_slug,
                p.image_url AS product_image_url
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
            ORDER BY oi.id ASC
        ");
        $stmtItems->execute([':order_id' => $order['id']]);
        $items = $stmtItems->fetchAll();

        foreach ($items as &$item) {
            $item['id']                        = (int)$item['id'];
            $item['order_id']                  = (int)$item['order_id'];
            $item['product_id']                = (int)$item['product_id'];
            $item['unit_price_cents_snapshot'] = (int)$item['unit_price_cents_snapshot'];
            $item['quantity']                  = (int)$item['quantity'];
            $item['line_total_cents']          = (int)$item['line_total_cents'];
        }

        $order['items'] = $items;
        $order['allowed_transitions'] = ORDER_STATUS_TRANSITIONS[$order['status']] ?? [];

        return $order;
    }

    /**
     * Section 10:
     * - Use the sequence Pending -> Confirmed -> Preparing -> Shipped -> Completed.
     * - Permit cancellation from Pending or Confirmed only.
     * - Completed and Cancelled are final statuses.
     * - When cancelling an eligible order, restore its stock exactly once.
     * - Reject repeated cancellation or backward status changes.
     */
    public function updateOrderStatus(int $orderId, string $newStatus): array
    {
        $newStatus = trim($newStatus);
        if (!in_array($newStatus, ORDER_STATUSES, true)) {
            return ['success' => false, 'error' => "Unrecognized order status \"{$newStatus}\"."];
        }

        if ($this->useJsonFallback) {
            return $this->jsonUpdateOrderStatus($orderId, $newStatus);
        }

        $this->pdo->beginTransaction();
        try {
            $order = $this->getOrderById($orderId);
            if (!$order) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'Order not found.'];
            }

            $currentStatus = $order['status'];

            if ($currentStatus === $newStatus) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'error'   => "Order {$order['order_number']} is already in \"{$currentStatus}\" status.",
                ];
            }

            $allowedNext = ORDER_STATUS_TRANSITIONS[$currentStatus] ?? [];
            if (!in_array($newStatus, $allowedNext, true)) {
                $this->pdo->rollBack();
                $allowedList = empty($allowedNext)
                    ? 'none (this is a final status)'
                    : implode(' or ', $allowedNext);
                return [
                    'success' => false,
                    'error'   => "Invalid status transition from \"{$currentStatus}\" to \"{$newStatus}\". Allowed next status from \"{$currentStatus}\": {$allowedList}.",
                ];
            }

            $stockRestoredNow = false;

            // Restore stock exactly once when cancelling from Pending or Confirmed
            if ($newStatus === 'Cancelled') {
                if ((int)$order['stock_restored'] === 1) {
                    $this->pdo->rollBack();
                    return [
                        'success' => false,
                        'error'   => "Stock for order {$order['order_number']} has already been restored.",
                    ];
                }

                $stmtRestore = $this->pdo->prepare("
                    UPDATE products
                    SET stock = stock + :qty, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :product_id
                ");

                foreach ($order['items'] as $item) {
                    $stmtRestore->execute([
                        ':qty'        => $item['quantity'],
                        ':product_id' => $item['product_id'],
                    ]);
                }

                $stmtUpdate = $this->pdo->prepare("
                    UPDATE orders
                    SET status = 'Cancelled', stock_restored = 1, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND stock_restored = 0
                ");
                $stmtUpdate->execute([':id' => $orderId]);
                $stockRestoredNow = true;
            } else {
                $stmtUpdate = $this->pdo->prepare("
                    UPDATE orders
                    SET status = :status, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':status' => $newStatus,
                    ':id'     => $orderId,
                ]);
            }

            $this->pdo->commit();

            $msg = "Order {$order['order_number']} status updated from \"{$currentStatus}\" to \"{$newStatus}\".";
            if ($stockRestoredNow) {
                $msg .= " Product stock quantities have been restored.";
            }

            $notifTitle = ($newStatus === 'Cancelled') ? 'Order Cancelled' : "Order {$newStatus}";
            $this->addNotification('order', $notifTitle, "Order {$order['order_number']} updated from {$currentStatus} to {$newStatus}." . ($stockRestoredNow ? " Stock quantities restored." : ""), "order.php?id={$orderId}");

            return [
                'success'        => true,
                'message'        => $msg,
                'stock_restored' => $stockRestoredNow,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'error'   => 'Failed to update order status: ' . $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // DASHBOARD SUMMARY & CUSTOMER DERIVATION (Sections 11, 13)
    // ========================================================================

    /**
     * Section 11: Overview Summary Cards
     * - Total Products: All product records, including inactive products.
     * - Total Orders: All saved orders, including cancelled orders.
     * - Pending / Completed Orders: Counts of orders with the respective status.
     * - Total Customers: Unique customers derived from normalized checkout emails.
     * - Total Sales: Total value of Completed orders only; exclude all other statuses.
     */
    public function getDashboardMetrics(): array
    {
        $products = $this->getProducts();
        $orders   = $this->getOrders();

        $totalProducts     = count($products);
        $activeProducts    = 0;
        $outOfStockCount   = 0;
        $inactiveCount     = 0;

        foreach ($products as $p) {
            if ($p['is_active'] === 0) {
                $inactiveCount++;
            } elseif ($p['stock'] === 0) {
                $outOfStockCount++;
                $activeProducts++;
            } else {
                $activeProducts++;
            }
        }

        $totalOrders      = count($orders);
        $pendingOrders    = 0;
        $confirmedOrders  = 0;
        $preparingOrders  = 0;
        $shippedOrders    = 0;
        $completedOrders  = 0;
        $cancelledOrders  = 0;
        $totalSalesCents  = 0;
        $uniqueEmails     = [];

        foreach ($orders as $o) {
            $normEmail = normalizeEmail($o['customer_email']);
            if ($normEmail !== '') {
                $uniqueEmails[$normEmail] = true;
            }

            switch ($o['status']) {
                case 'Pending':
                    $pendingOrders++;
                    break;
                case 'Confirmed':
                    $confirmedOrders++;
                    break;
                case 'Preparing':
                    $preparingOrders++;
                    break;
                case 'Shipped':
                    $shippedOrders++;
                    break;
                case 'Completed':
                    $completedOrders++;
                    $totalSalesCents += (int)$o['total_cents'];
                    break;
                case 'Cancelled':
                    $cancelledOrders++;
                    break;
            }
        }

        return [
            'total_products'        => $totalProducts,
            'active_products'       => $activeProducts,
            'out_of_stock_products' => $outOfStockCount,
            'inactive_products'     => $inactiveCount,
            'total_orders'          => $totalOrders,
            'pending_orders'        => $pendingOrders,
            'confirmed_orders'      => $confirmedOrders,
            'preparing_orders'      => $preparingOrders,
            'shipped_orders'        => $shippedOrders,
            'completed_orders'      => $completedOrders,
            'cancelled_orders'      => $cancelledOrders,
            'total_customers'       => count($uniqueEmails),
            'total_sales_cents'     => $totalSalesCents,
        ];
    }

    /**
     * Section 13: Customers
     * "A read-only list derived from orders is sufficient. Show name, normalized email,
     * contact number, order count, and total value of Completed orders.
     * Use the latest submitted name/contact for repeat emails."
     */
    public function getDerivedCustomers(): array
    {
        // getOrders() returns orders sorted newest first (created_at DESC, id DESC)
        $orders = $this->getOrders();
        $customersByEmail = [];

        foreach ($orders as $order) {
            $normEmail = normalizeEmail($order['customer_email']);
            if ($normEmail === '') {
                continue;
            }

            if (!isset($customersByEmail[$normEmail])) {
                // First time seeing this normalized email while iterating newest-first = latest submitted details!
                $customersByEmail[$normEmail] = [
                    'customer_name'         => $order['customer_name'],
                    'normalized_email'      => $normEmail,
                    'contact_number'        => $order['contact_number'],
                    'delivery_address'      => $order['delivery_address'],
                    'order_count'           => 0,
                    'completed_order_count' => 0,
                    'completed_sales_cents' => 0,
                    'latest_order_date'     => $order['created_at'],
                    'latest_order_number'   => $order['order_number'],
                ];
            }

            $customersByEmail[$normEmail]['order_count']++;
            if ($order['status'] === 'Completed') {
                $customersByEmail[$normEmail]['completed_order_count']++;
                $customersByEmail[$normEmail]['completed_sales_cents'] += (int)$order['total_cents'];
            }
        }

        return array_values($customersByEmail);
    }

    // ========================================================================
    // JSON FALLBACK IMPLEMENTATION (Only used if pdo_sqlite is absent)
    // ========================================================================

    private function initializeJsonDatabase(): void
    {
        $initialData = [
            'categories' => [
                ['id' => 1, 'name' => 'Keyboards & Mice', 'slug' => 'keyboards-mice', 'description' => 'Precision mechanical keyboards, lightweight wireless mice, and tactile desk peripherals.', 'created_at' => '2026-11-01 08:00:00'],
                ['id' => 2, 'name' => 'Audio & Studio Gear', 'slug' => 'audio-studio-gear', 'description' => 'High-fidelity studio headphones, condenser microphones, and desktop acoustic gear.', 'created_at' => '2026-11-01 08:00:00'],
                ['id' => 3, 'name' => 'Workspace & Accessories', 'slug' => 'workspace-accessories', 'description' => 'Essential desk mats, braided cables, USB-C hubs, and keyboard maintenance tools.', 'created_at' => '2026-11-01 08:00:00'],
            ],
            'products' => [
                ['id' => 1, 'category_id' => 1, 'name' => 'CubeTech K75 Hot-Swap Mechanical Keyboard', 'slug' => 'cubetech-k75-mechanical-keyboard', 'description' => 'Compact 75% layout mechanical keyboard with gasket mount, hot-swappable tactile switches, PBT double-shot keycaps, and per-key white LED backlight.', 'price_cents' => 349900, 'stock' => 12, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-k75-keyboard.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 2, 'category_id' => 1, 'name' => 'CubeTech K65 Low-Profile Wireless Keyboard', 'slug' => 'cubetech-k65-low-profile-keyboard', 'description' => 'Ultra-slim 65% tri-mode wireless mechanical keyboard with aluminum top plate, Bluetooth 5.1 / 2.4GHz dongle, and 40-hour battery life.', 'price_cents' => 285000, 'stock' => 8, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-k65-keyboard.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 3, 'category_id' => 1, 'name' => 'CubeTech M1 Pro Ergonomic Vertical Mouse', 'slug' => 'cubetech-m1-ergonomic-mouse', 'description' => 'Natural handshake 57-degree vertical wireless mouse designed to reduce wrist strain during marathon coding sessions. Adjustable up to 4000 DPI.', 'price_cents' => 145000, 'stock' => 15, 'is_active' => 1, 'is_featured' => 0, 'image_url' => 'assets/images/prod-m1-mouse.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 4, 'category_id' => 1, 'name' => 'CubeTech AeroLight 55g Gaming Mouse', 'slug' => 'cubetech-aerolight-gaming-mouse', 'description' => 'Featherweight 55-gram e-sports wireless mouse featuring a flagship 26,000 DPI optical sensor and crisp optical microswitches. Currently out of stock.', 'price_cents' => 249900, 'stock' => 0, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-aerolight-mouse.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 5, 'category_id' => 2, 'name' => 'CubeTech Nova ANC Studio Headphones', 'slug' => 'cubetech-nova-anc-headphones', 'description' => 'Over-ear wireless studio monitor headphones with hybrid active noise cancellation, 40mm beryllium drivers, and plush memory-foam earcups.', 'price_cents' => 429900, 'stock' => 10, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-nova-headphones.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 6, 'category_id' => 2, 'name' => 'CubeTech Pulse USB-C Condenser Microphone', 'slug' => 'cubetech-pulse-usb-microphone', 'description' => 'Cardioid studio USB-C microphone with built-in pop filter, zero-latency headphone monitoring, tap-to-mute sensor, and heavy-duty desk stand.', 'price_cents' => 219900, 'stock' => 9, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-pulse-mic.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 7, 'category_id' => 2, 'name' => 'CubeTech Clarity Dual Desktop Soundbar', 'slug' => 'cubetech-clarity-desktop-soundbar', 'description' => 'Compact under-monitor stereo soundbar with dual passive radiators, Bluetooth 5.3 + USB audio input, and rotary volume control knob.', 'price_cents' => 169000, 'stock' => 14, 'is_active' => 1, 'is_featured' => 0, 'image_url' => 'assets/images/prod-clarity-soundbar.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 8, 'category_id' => 2, 'name' => 'CubeTech SonicBuds Pro True Wireless Earbuds', 'slug' => 'cubetech-sonicbuds-tws', 'description' => 'Low-latency wireless earbuds with environmental noise cancellation for clear team calls, IPX5 water resistance, and USB-C fast-charging case.', 'price_cents' => 129900, 'stock' => 18, 'is_active' => 1, 'is_featured' => 0, 'image_url' => 'assets/images/prod-sonicbuds.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 9, 'category_id' => 3, 'name' => 'CubeTech Braided USB-C Cable (1.5m)', 'slug' => 'cubetech-braided-usbc-cable', 'description' => 'Heavy-duty nylon-braided USB-C to USB-C 60W fast-charging and data sync cable with reinforced aluminum housings. (Assessment test item: PHP 120.00).', 'price_cents' => 12000, 'stock' => 25, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-usbc-cable.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 10, 'category_id' => 3, 'name' => 'Keycap Puller & Switch Brush Kit', 'slug' => 'keycap-puller-switch-brush-kit', 'description' => 'Dual-ended stainless steel wire keycap/switch puller paired with an anti-static nylon cleaning brush for mechanical keyboard maintenance. (Assessment test item: PHP 85.50).', 'price_cents' => 8550, 'stock' => 30, 'is_active' => 1, 'is_featured' => 1, 'image_url' => 'assets/images/prod-keycap-kit.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 11, 'category_id' => 3, 'name' => 'CubeTech 7-in-1 Aluminum USB-C Hub', 'slug' => 'cubetech-7in1-usbc-hub', 'description' => 'Sleek anodized aluminum USB-C docking hub with 4K@60Hz HDMI, 100W Power Delivery pass-through, SD/microSD card readers, and 3x USB 3.0 ports.', 'price_cents' => 159900, 'stock' => 11, 'is_active' => 1, 'is_featured' => 0, 'image_url' => 'assets/images/prod-usbc-hub.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
                ['id' => 12, 'category_id' => 3, 'name' => 'CubeTech Legacy Wired Trackball Pro (Discontinued)', 'slug' => 'cubetech-legacy-trackball-pro', 'description' => 'Archived first-generation wired optical trackball controller. Marked inactive to demonstrate hidden listing and direct-link order prevention.', 'price_cents' => 89000, 'stock' => 8, 'is_active' => 0, 'is_featured' => 0, 'image_url' => 'assets/images/prod-legacy-trackball.svg', 'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00'],
            ],
            'orders' => [
                ['id' => 1, 'order_number' => 'ORD-20261101-1001', 'customer_name' => 'Maria Clara Santos', 'customer_email' => 'maria.santos@example.com', 'normalized_email' => 'maria.santos@example.com', 'contact_number' => '+63 917 555 0142', 'delivery_address' => 'Unit 402, IT Park Tower 2, Lahug, Cebu City 6000', 'payment_method' => 'E-Wallet', 'order_notes' => 'Please leave at the lobby reception desk.', 'subtotal_cents' => 373900, 'total_cents' => 373900, 'status' => 'Completed', 'stock_restored' => 0, 'created_at' => '2026-11-01 10:15:00', 'updated_at' => '2026-11-02 16:30:00'],
                ['id' => 2, 'order_number' => 'ORD-20261103-1002', 'customer_name' => 'Juan Dela Cruz', 'customer_email' => 'juan.delacruz@example.com', 'normalized_email' => 'juan.delacruz@example.com', 'contact_number' => '0918-555-0199', 'delivery_address' => '88 Mango Avenue, Kamputhaw, Cebu City 6000', 'payment_method' => 'Cash on Delivery', 'order_notes' => 'Call upon arrival at gate.', 'subtotal_cents' => 219900, 'total_cents' => 219900, 'status' => 'Confirmed', 'stock_restored' => 0, 'created_at' => '2026-11-03 14:20:00', 'updated_at' => '2026-11-03 15:10:00'],
                ['id' => 3, 'order_number' => 'ORD-20261104-1003', 'customer_name' => 'Maria Clara Santos-Reyes', 'customer_email' => 'MARIA.SANTOS@example.com', 'normalized_email' => 'maria.santos@example.com', 'contact_number' => '+63 917 555 0188', 'delivery_address' => 'Unit 402, IT Park Tower 2, Lahug, Cebu City 6000', 'payment_method' => 'Bank Transfer', 'order_notes' => 'Second order with updated contact info.', 'subtotal_cents' => 32550, 'total_cents' => 32550, 'status' => 'Pending', 'stock_restored' => 0, 'created_at' => '2026-11-04 09:05:00', 'updated_at' => '2026-11-04 09:05:00'],
            ],
            'order_items' => [
                ['id' => 1, 'order_id' => 1, 'product_id' => 1, 'product_name_snapshot' => 'CubeTech K75 Hot-Swap Mechanical Keyboard', 'unit_price_cents_snapshot' => 349900, 'quantity' => 1, 'line_total_cents' => 349900],
                ['id' => 2, 'order_id' => 1, 'product_id' => 9, 'product_name_snapshot' => 'CubeTech Braided USB-C Cable (1.5m)', 'unit_price_cents_snapshot' => 12000, 'quantity' => 2, 'line_total_cents' => 24000],
                ['id' => 3, 'order_id' => 2, 'product_id' => 6, 'product_name_snapshot' => 'CubeTech Pulse USB-C Condenser Microphone', 'unit_price_cents_snapshot' => 219900, 'quantity' => 1, 'line_total_cents' => 219900],
                ['id' => 4, 'order_id' => 3, 'product_id' => 9, 'product_name_snapshot' => 'CubeTech Braided USB-C Cable (1.5m)', 'unit_price_cents_snapshot' => 12000, 'quantity' => 2, 'line_total_cents' => 24000],
                ['id' => 5, 'order_id' => 3, 'product_id' => 10, 'product_name_snapshot' => 'Keycap Puller & Switch Brush Kit', 'unit_price_cents_snapshot' => 8550, 'quantity' => 1, 'line_total_cents' => 8550],
            ],
        ];
        file_put_contents(JSON_DB_PATH, json_encode($initialData, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function readJsonDb(): array
    {
        if (!file_exists(JSON_DB_PATH)) {
            $this->initializeJsonDatabase();
        }
        $raw = file_get_contents(JSON_DB_PATH);
        return json_decode((string)$raw, true) ?: [];
    }

    private function writeJsonDb(array $data): void
    {
        file_put_contents(JSON_DB_PATH, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function jsonGetCategories(): array
    {
        $db = $this->readJsonDb();
        $cats = $db['categories'] ?? [];
        $prods = $db['products'] ?? [];
        foreach ($cats as &$c) {
            $c['id'] = (int)$c['id'];
            $c['product_count'] = 0;
            $c['active_product_count'] = 0;
            foreach ($prods as $p) {
                if ((int)$p['category_id'] === $c['id']) {
                    $c['product_count']++;
                    if ((int)$p['is_active'] === 1) {
                        $c['active_product_count']++;
                    }
                }
            }
        }
        return $cats;
    }

    private function jsonCreateCategory(string $name, string $slug, string $desc): array
    {
        $db = $this->readJsonDb();
        $maxId = 0;
        foreach ($db['categories'] as $c) {
            $maxId = max($maxId, (int)$c['id']);
        }
        $newId = $maxId + 1;
        $db['categories'][] = [
            'id'          => $newId,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $this->writeJsonDb($db);
        return ['success' => true, 'id' => $newId];
    }

    private function jsonUpdateCategory(int $id, string $name, string $slug, string $desc): array
    {
        $db = $this->readJsonDb();
        foreach ($db['categories'] as &$c) {
            if ((int)$c['id'] === $id) {
                $c['name']        = $name;
                $c['slug']        = $slug;
                $c['description'] = $desc;
                break;
            }
        }
        $this->writeJsonDb($db);
        return ['success' => true];
    }

    private function jsonDeleteCategory(int $id): array
    {
        $db = $this->readJsonDb();
        $db['categories'] = array_values(array_filter($db['categories'], fn($c) => (int)$c['id'] !== $id));
        $this->writeJsonDb($db);
        return ['success' => true];
    }

    private function jsonGetProducts(array $filters = []): array
    {
        $db = $this->readJsonDb();
        $catMap = [];
        foreach ($db['categories'] as $c) {
            $catMap[(int)$c['id']] = $c;
        }
        $refCounts = [];
        foreach ($db['order_items'] as $oi) {
            $pid = (int)$oi['product_id'];
            $refCounts[$pid] = ($refCounts[$pid] ?? 0) + 1;
        }

        $result = [];
        foreach ($db['products'] as $p) {
            $cid = (int)$p['category_id'];
            $p['category_name'] = $catMap[$cid]['name'] ?? 'Uncategorized';
            $p['category_slug'] = $catMap[$cid]['slug'] ?? 'uncategorized';
            $p['order_reference_count'] = $refCounts[(int)$p['id']] ?? 0;
            $p = $this->enrichProductRow($p);

            if (!empty($filters['customer_only']) && $p['is_active'] !== 1) {
                continue;
            }
            if (!empty($filters['featured_only']) && $p['is_featured'] !== 1) {
                continue;
            }
            if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
                $needle = strtolower(trim((string)$filters['search']));
                if (strpos(strtolower($p['name']), $needle) === false) {
                    continue;
                }
            }
            if (!empty($filters['category_id']) && $p['category_id'] !== (int)$filters['category_id']) {
                continue;
            }
            if (!empty($filters['status_filter'])) {
                if ($filters['status_filter'] === 'in_stock' && !($p['is_active'] === 1 && $p['stock'] > 0)) {
                    continue;
                }
                if ($filters['status_filter'] === 'out_of_stock' && !($p['is_active'] === 1 && $p['stock'] === 0)) {
                    continue;
                }
                if ($filters['status_filter'] === 'inactive' && $p['is_active'] !== 0) {
                    continue;
                }
            }
            $result[] = $p;
        }

        $sort = $filters['sort'] ?? 'default';
        usort($result, function ($a, $b) use ($sort) {
            if ($sort === 'price_asc') {
                return $a['price_cents'] <=> $b['price_cents'] ?: $a['id'] <=> $b['id'];
            }
            if ($sort === 'price_desc') {
                return $b['price_cents'] <=> $a['price_cents'] ?: $a['id'] <=> $b['id'];
            }
            if ($sort === 'name_asc') {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['id'] <=> $b['id'];
        });

        return $result;
    }

    private function jsonGetProductById(int $id): ?array
    {
        foreach ($this->jsonGetProducts() as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    private function jsonSaveProduct(array $data, ?int $id = null): array
    {
        $db = $this->readJsonDb();
        if ($id === null) {
            $maxId = 0;
            foreach ($db['products'] as $p) {
                $maxId = max($maxId, (int)$p['id']);
            }
            $newId = $maxId + 1;
            $data['id'] = $newId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $db['products'][] = $data;
            $this->writeJsonDb($db);
            return ['success' => true, 'id' => $newId];
        } else {
            foreach ($db['products'] as &$p) {
                if ((int)$p['id'] === $id) {
                    $p = array_merge($p, $data, ['id' => $id, 'updated_at' => date('Y-m-d H:i:s')]);
                    break;
                }
            }
            $this->writeJsonDb($db);
            return ['success' => true, 'id' => $id];
        }
    }

    private function jsonDeleteProduct(int $id): array
    {
        $db = $this->readJsonDb();
        $db['products'] = array_values(array_filter($db['products'], fn($p) => (int)$p['id'] !== $id));
        $this->writeJsonDb($db);
        return ['success' => true];
    }

    private function jsonDeactivateProduct(int $id): array
    {
        $db = $this->readJsonDb();
        $name = '';
        foreach ($db['products'] as &$p) {
            if ((int)$p['id'] === $id) {
                $p['is_active'] = 0;
                $p['updated_at'] = date('Y-m-d H:i:s');
                $name = $p['name'];
                break;
            }
        }
        $this->writeJsonDb($db);
        return ['success' => true, 'product_name' => $name];
    }

    private function jsonActivateProduct(int $id): array
    {
        $db = $this->readJsonDb();
        $name = '';
        foreach ($db['products'] as &$p) {
            if ((int)$p['id'] === $id) {
                $p['is_active'] = 1;
                $p['updated_at'] = date('Y-m-d H:i:s');
                $name = $p['name'];
                break;
            }
        }
        $this->writeJsonDb($db);
        return ['success' => true, 'product_name' => $name];
    }

    private function jsonPlaceOrder(array $validation, array $cartItems): array
    {
        $db = $this->readJsonDb();
        $changesDetected = [];
        $verifiedLines   = [];
        $subtotalCents   = 0;
        $fieldErrors     = $validation['errors'];

        foreach ($cartItems as $productId => $cartEntry) {
            $pid = (int)$productId;
            $qty = (int)($cartEntry['quantity'] ?? 0);
            $expectedPriceCents = isset($cartEntry['snapshot_price_cents']) ? (int)$cartEntry['snapshot_price_cents'] : null;
            $expectedName       = (string)($cartEntry['snapshot_name'] ?? "Product #{$pid}");

            if ($qty <= 0) {
                $changesDetected[] = "Invalid quantity ({$qty}) for \"{$expectedName}\".";
                continue;
            }
            $liveProduct = $this->jsonGetProductById($pid);
            if (!$liveProduct || $liveProduct['is_active'] === 0) {
                $pName = $liveProduct['name'] ?? $expectedName;
                $changesDetected[] = "\"{$pName}\" is no longer available and cannot be ordered.";
                continue;
            }
            if ($expectedPriceCents !== null && $liveProduct['price_cents'] !== $expectedPriceCents) {
                $changesDetected[] = "The price of \"{$liveProduct['name']}\" changed from " . formatPhp($expectedPriceCents) . " to " . formatPhp($liveProduct['price_cents']) . ".";
            }
            if ($liveProduct['stock'] <= 0) {
                $changesDetected[] = "\"{$liveProduct['name']}\" is now Out of Stock.";
                continue;
            }
            if ($qty > $liveProduct['stock']) {
                $changesDetected[] = "Insufficient stock for \"{$liveProduct['name']}\": requested {$qty}, only {$liveProduct['stock']} available.";
                continue;
            }

            $lineTotal = $liveProduct['price_cents'] * $qty;
            $subtotalCents += $lineTotal;
            $verifiedLines[] = [
                'product_id'                => $liveProduct['id'],
                'product_name_snapshot'     => $liveProduct['name'],
                'unit_price_cents_snapshot' => $liveProduct['price_cents'],
                'quantity'                  => $qty,
                'line_total_cents'          => $lineTotal,
            ];
        }

        if (!empty($changesDetected) || !empty($fieldErrors)) {
            return [
                'success'          => false,
                'changes_detected' => !empty($changesDetected),
                'changes'          => $changesDetected,
                'field_errors'     => $fieldErrors,
                'errors'           => array_merge(array_values($fieldErrors), $changesDetected),
            ];
        }

        $maxOrderId = 0;
        foreach ($db['orders'] as $o) {
            $maxOrderId = max($maxOrderId, (int)$o['id']);
        }
        $orderId = $maxOrderId + 1;
        $orderNumber = 'ORD-' . date('Ymd') . '-' . (1000 + $orderId);
        $clean = $validation['clean'];
        $now = date('Y-m-d H:i:s');

        $db['orders'][] = [
            'id'               => $orderId,
            'order_number'     => $orderNumber,
            'customer_name'    => $clean['customer_name'],
            'customer_email'   => $clean['customer_email'],
            'normalized_email' => $clean['normalized_email'],
            'contact_number'   => $clean['contact_number'],
            'delivery_address' => $clean['delivery_address'],
            'payment_method'   => $clean['payment_method'],
            'order_notes'      => $clean['order_notes'],
            'subtotal_cents'   => $subtotalCents,
            'total_cents'      => $subtotalCents,
            'status'           => 'Pending',
            'stock_restored'   => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $maxItemId = 0;
        foreach ($db['order_items'] as $oi) {
            $maxItemId = max($maxItemId, (int)$oi['id']);
        }

        foreach ($verifiedLines as $line) {
            $maxItemId++;
            $db['order_items'][] = array_merge(['id' => $maxItemId, 'order_id' => $orderId], $line);
            foreach ($db['products'] as &$p) {
                if ((int)$p['id'] === $line['product_id']) {
                    $p['stock'] = (int)$p['stock'] - $line['quantity'];
                    $p['updated_at'] = $now;
                    break;
                }
            }
        }

        $this->writeJsonDb($db);
        return ['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber];
    }

    private function jsonGetOrders(?string $statusFilter = null, ?string $search = null): array
    {
        $db = $this->readJsonDb();
        $orders = $db['orders'] ?? [];
        $items  = $db['order_items'] ?? [];

        $result = [];
        foreach ($orders as $o) {
            if ($statusFilter && $statusFilter !== 'all' && $o['status'] !== $statusFilter) {
                continue;
            }
            if ($search && trim($search) !== '') {
                $t = strtolower(trim($search));
                if (
                    strpos(strtolower($o['order_number']), $t) === false &&
                    strpos(strtolower($o['customer_name']), $t) === false &&
                    strpos(strtolower($o['customer_email']), $t) === false
                ) {
                    continue;
                }
            }
            $units = 0;
            $lines = 0;
            foreach ($items as $oi) {
                if ((int)$oi['order_id'] === (int)$o['id']) {
                    $units += (int)$oi['quantity'];
                    $lines++;
                }
            }
            $o['id']             = (int)$o['id'];
            $o['subtotal_cents'] = (int)$o['subtotal_cents'];
            $o['total_cents']    = (int)$o['total_cents'];
            $o['stock_restored'] = (int)$o['stock_restored'];
            $o['total_units']    = $units;
            $o['line_count']     = $lines;
            $result[] = $o;
        }

        usort($result, fn($a, $b) => strcmp($b['created_at'], $a['created_at']) ?: ($b['id'] <=> $a['id']));
        return $result;
    }

    private function jsonGetOrderById(int $id): ?array
    {
        $db = $this->readJsonDb();
        foreach ($db['orders'] as $o) {
            if ((int)$o['id'] === $id) {
                return $this->jsonHydrateOrder($o, $db);
            }
        }
        return null;
    }

    private function jsonGetOrderByNumber(string $num): ?array
    {
        $db = $this->readJsonDb();
        foreach ($db['orders'] as $o) {
            if (strcasecmp($o['order_number'], trim($num)) === 0) {
                return $this->jsonHydrateOrder($o, $db);
            }
        }
        return null;
    }

    private function jsonHydrateOrder(array $o, array $db): array
    {
        $o['id']             = (int)$o['id'];
        $o['subtotal_cents'] = (int)$o['subtotal_cents'];
        $o['total_cents']    = (int)$o['total_cents'];
        $o['stock_restored'] = (int)$o['stock_restored'];

        $prodMap = [];
        foreach ($db['products'] as $p) {
            $prodMap[(int)$p['id']] = $p;
        }
        $items = [];
        foreach ($db['order_items'] as $oi) {
            if ((int)$oi['order_id'] === $o['id']) {
                $pid = (int)$oi['product_id'];
                $oi['id']                        = (int)$oi['id'];
                $oi['order_id']                  = (int)$oi['order_id'];
                $oi['product_id']                = $pid;
                $oi['unit_price_cents_snapshot'] = (int)$oi['unit_price_cents_snapshot'];
                $oi['quantity']                  = (int)$oi['quantity'];
                $oi['line_total_cents']          = (int)$oi['line_total_cents'];
                $oi['current_product_slug']      = $prodMap[$pid]['slug'] ?? '';
                $oi['product_image_url']         = $prodMap[$pid]['image_url'] ?? '';
                $items[] = $oi;
            }
        }
        $o['items'] = $items;
        $o['allowed_transitions'] = ORDER_STATUS_TRANSITIONS[$o['status']] ?? [];
        return $o;
    }

    private function jsonUpdateOrderStatus(int $orderId, string $newStatus): array
    {
        $db = $this->readJsonDb();
        $order = $this->jsonGetOrderById($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }
        $currentStatus = $order['status'];
        if ($currentStatus === $newStatus) {
            return ['success' => false, 'error' => "Order is already in \"{$currentStatus}\" status."];
        }
        $allowedNext = ORDER_STATUS_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($newStatus, $allowedNext, true)) {
            $allowedList = empty($allowedNext) ? 'none (final status)' : implode(' or ', $allowedNext);
            return ['success' => false, 'error' => "Invalid transition from \"{$currentStatus}\" to \"{$newStatus}\". Allowed: {$allowedList}."];
        }

        $stockRestoredNow = false;
        if ($newStatus === 'Cancelled') {
            if ((int)$order['stock_restored'] === 1) {
                return ['success' => false, 'error' => 'Stock already restored for this order.'];
            }
            foreach ($order['items'] as $item) {
                foreach ($db['products'] as &$p) {
                    if ((int)$p['id'] === $item['product_id']) {
                        $p['stock'] = (int)$p['stock'] + $item['quantity'];
                        break;
                    }
                }
            }
            $stockRestoredNow = true;
        }

        foreach ($db['orders'] as &$o) {
            if ((int)$o['id'] === $orderId) {
                $o['status'] = $newStatus;
                if ($stockRestoredNow) {
                    $o['stock_restored'] = 1;
                }
                $o['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }

        $this->writeJsonDb($db);
        $msg = "Order {$order['order_number']} status updated from \"{$currentStatus}\" to \"{$newStatus}\".";
        if ($stockRestoredNow) {
            $msg .= " Product stock quantities have been restored.";
        }
        return ['success' => true, 'message' => $msg, 'stock_restored' => $stockRestoredNow];
    }
}

