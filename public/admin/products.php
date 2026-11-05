<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';

Auth::requireAdmin();
$repo = Repository::getInstance();
$categories = $repo->getCategories();

$bundledImages = [
    'assets/images/prod-k75-keyboard.jpg'     => 'Bundled: K75 Mechanical Keyboard',
    'assets/images/prod-k65-keyboard.jpg'     => 'Bundled: K65 Wireless Keyboard',
    'assets/images/prod-m1-mouse.jpg'         => 'Bundled: M1 Ergonomic Mouse',
    'assets/images/prod-aerolight-mouse.jpg'  => 'Bundled: AeroLight Gaming Mouse',
    'assets/images/prod-nova-headphones.jpg'  => 'Bundled: Nova ANC Headphones',
    'assets/images/prod-pulse-mic.jpg'        => 'Bundled: Pulse USB-C Microphone',
    'assets/images/prod-clarity-soundbar.jpg' => 'Bundled: Clarity Desktop Soundbar',
    'assets/images/prod-sonicbuds.jpg'        => 'Bundled: SonicBuds Pro Earbuds',
    'assets/images/prod-usbc-cable.jpg'       => 'Bundled: Braided USB-C Cable',
    'assets/images/prod-keycap-kit.jpg'       => 'Bundled: Keycap Puller & Brush Kit',
    'assets/images/prod-usbc-hub.jpg'         => 'Bundled: 7-in-1 Aluminum USB-C Hub',
    'assets/images/prod-legacy-trackball.jpg' => 'Bundled: Legacy Trackball',
];

$formErrors           = [];
$blockedDeactivateProduct = null;
$showForm             = false;
$editingProduct       = null;

$formData = [
    'id'          => null,
    'name'        => '',
    'category_id' => $categories[0]['id'] ?? 1,
    'description' => '',
    'price'       => '',
    'stock'       => '10',
    'image_url'   => 'assets/images/prod-k75-keyboard.jpg',
    'is_active'   => '1',
    'is_featured' => '0',
];

// Handle GET edit / new mode
if (isset($_GET['action']) && $_GET['action'] === 'new') {
    $showForm = true;
} elseif (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $editingProduct = $repo->getProductById($editId);
    if ($editingProduct) {
        $showForm = true;
        $formData = [
            'id'          => $editingProduct['id'],
            'name'        => $editingProduct['name'],
            'category_id' => $editingProduct['category_id'],
            'description' => $editingProduct['description'],
            'price'       => centsToDecimal((int)$editingProduct['price_cents']),
            'stock'       => (string)$editingProduct['stock'],
            'image_url'   => $editingProduct['image_url'],
            'is_active'   => (string)$editingProduct['is_active'],
            'is_featured' => (string)$editingProduct['is_featured'],
        ];
    } else {
        setFlash('error', 'Product not found.');
        header('Location: products.php');
        exit;
    }
}

// Handle POST actions (save, delete, deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: products.php');
        exit;
    }

    $postAction = (string)($_POST['action'] ?? '');

    if ($postAction === 'save_product') {
        $editId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;

        $imageUrl = trim((string)($_POST['image_url'] ?? ''));

        // Optional image file upload support (Section 12: "File upload is optional")
        if (!empty($_FILES['image_upload']['name']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image_upload']['tmp_name'];
            $origName = basename((string)$_FILES['image_upload']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
                $destFile = 'prod-upload-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $destPath = PUBLIC_PATH . '/assets/images/' . $destFile;
                if (move_uploaded_file($tmpName, $destPath)) {
                    $imageUrl = 'assets/images/' . $destFile;
                }
            } else {
                $formErrors[] = 'Uploaded image must be JPG, PNG, WEBP, GIF, or SVG.';
            }
        }

        $formData = [
            'id'          => $editId,
            'name'        => (string)($_POST['name'] ?? ''),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'description' => (string)($_POST['description'] ?? ''),
            'price'       => (string)($_POST['price'] ?? ''),
            'stock'       => (string)($_POST['stock'] ?? ''),
            'image_url'   => $imageUrl,
            'is_active'   => isset($_POST['is_active']) && (string)$_POST['is_active'] === '1' ? '1' : '0',
            'is_featured' => isset($_POST['is_featured']) && (string)$_POST['is_featured'] === '1' ? '1' : '0',
        ];

        if (empty($formErrors)) {
            $res = $repo->saveProduct($formData, $editId);
            if ($res['success']) {
                setFlash('success', $editId ? 'Product updated successfully.' : 'New product added successfully.');
                header('Location: products.php');
                exit;
            } else {
                $formErrors = $res['errors'] ?? ['Failed to save product.'];
                $showForm = true;
            }
        } else {
            $showForm = true;
        }
    } elseif ($postAction === 'delete_product') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        $res = $repo->deleteProduct($prodId);
        if ($res['success']) {
            setFlash('success', 'Product permanently deleted.');
            header('Location: products.php');
            exit;
        } else {
            // Section 12: "Block hard deletion of a product referenced by an order and offer to mark it inactive instead."
            if (!empty($res['can_deactivate'])) {
                $blockedDeactivateProduct = [
                    'id'    => $res['product_id'],
                    'name'  => $res['product_name'],
                    'error' => $res['error'],
                ];
            } else {
                setFlash('error', $res['error'] ?? 'Could not delete product.');
                header('Location: products.php');
                exit;
            }
        }
    } elseif ($postAction === 'deactivate_product') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        $res = $repo->deactivateProduct($prodId);
        if ($res['success']) {
            setFlash('success', "Product \"{$res['product_name']}\" has been marked as Inactive. Order history is preserved and it is now hidden from customer listings.");
        } else {
            setFlash('error', $res['error'] ?? 'Could not update product status.');
        }
        header('Location: products.php');
        exit;
    } elseif ($postAction === 'activate_product') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        $res = $repo->activateProduct($prodId);
        if ($res['success']) {
            setFlash('success', "Product \"{$res['product_name']}\" has been activated and is now visible on the customer storefront.");
        } else {
            setFlash('error', $res['error'] ?? 'Could not activate product.');
        }
        header('Location: products.php');
        exit;
    }
}

// Filters for product table
$search       = trim((string)($_GET['search'] ?? ''));
$categoryId   = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$statusFilter = trim((string)($_GET['status_filter'] ?? 'all'));

if (!in_array($statusFilter, ['all', 'in_stock', 'out_of_stock', 'inactive'], true)) {
    $statusFilter = 'all';
}

$products = $repo->getProducts([
    'search'        => $search,
    'category_id'   => $categoryId,
    'status_filter' => $statusFilter,
]);

$pageTitle = 'Product Management';
$adminNav  = 'products';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Product Management</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Add, edit, filter, or deactivate products. Changes reflect immediately on the customer storefront.
        </p>
    </div>

    <?php if (!$showForm): ?>
        <a href="products.php?action=new" class="btn btn-primary">&plus; Add New Product</a>
    <?php else: ?>
        <a href="products.php" class="btn btn-secondary">&larr; Cancel / View Product List</a>
    <?php endif; ?>
</div>

<!-- Offer to Mark Inactive when Hard Deletion is Blocked (Section 12) -->
<?php if ($blockedDeactivateProduct): ?>
    <div class="feedback-card feedback-card-warning" role="alert">
        <div class="feedback-card-icon">&#9888;</div>
        <div class="feedback-card-content">
            <h4 class="feedback-card-title">Hard Deletion Blocked (Order History Protection)</h4>
            <p class="feedback-card-desc"><?= e($blockedDeactivateProduct['error']) ?></p>
        </div>
        <div class="feedback-card-action">
            <form action="products.php" method="post" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="deactivate_product">
                <input type="hidden" name="product_id" value="<?= (int)$blockedDeactivateProduct['id'] ?>">
                <button type="submit" class="btn btn-warning btn-sm">
                    Mark "<?= e($blockedDeactivateProduct['name']) ?>" as Inactive Instead
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Add / Edit Product Form (Section 12) -->
<?php if ($showForm): ?>
    <div class="card" style="margin-bottom: 28px;">
        <h2 class="card-title">
            <?= $formData['id'] ? 'Edit Product #' . (int)$formData['id'] . ' — ' . e($formData['name']) : 'Add New Product' ?>
        </h2>

        <?php if (!empty($formErrors)): ?>
            <div class="alert alert-error">
                <strong>Please fix the following validation errors:</strong>
                <ul style="margin: 6px 0 0 18px;">
                    <?php foreach ($formErrors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="products.php" method="post" enctype="multipart/form-data" data-disable-on-submit="true" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="save_product">
            <?php if ($formData['id']): ?>
                <input type="hidden" name="product_id" value="<?= (int)$formData['id'] ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label for="prod-name" class="form-label">Product Name <span style="color: var(--danger);">*</span></label>
                    <input
                        type="text"
                        id="prod-name"
                        name="name"
                        class="form-control"
                        placeholder="e.g. CubeTech K80 Wireless Keyboard"
                        value="<?= e($formData['name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="prod-category" class="form-label">Category <span style="color: var(--danger);">*</span></label>
                    <select id="prod-category" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$formData['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label for="prod-price" class="form-label">Price (PHP, greater than 0.00) <span style="color: var(--danger);">*</span></label>
                    <input
                        type="text"
                        id="prod-price"
                        name="price"
                        class="form-control"
                        placeholder="e.g. 1499.00"
                        value="<?= e($formData['price']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="prod-stock" class="form-label">Stock Quantity (Whole number &ge; 0) <span style="color: var(--danger);">*</span></label>
                    <input
                        type="number"
                        id="prod-stock"
                        name="stock"
                        class="form-control"
                        min="0"
                        step="1"
                        placeholder="0"
                        value="<?= e($formData['stock']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="prod-status" class="form-label">Visibility Setting <span style="color: var(--danger);">*</span></label>
                    <select id="prod-status" name="is_active" class="form-control">
                        <option value="1" <?= (string)$formData['is_active'] === '1' ? 'selected' : '' ?>>Active (Visible on Storefront)</option>
                        <option value="0" <?= (string)$formData['is_active'] === '0' ? 'selected' : '' ?>>Inactive (Hidden from Customers)</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label for="prod-image" class="form-label">Image URL or Bundled Image Reference <span style="color: var(--danger);">*</span></label>
                    <input
                        type="text"
                        id="prod-image"
                        name="image_url"
                        class="form-control"
                        list="bundled-images-list"
                        placeholder="assets/images/prod-k75-keyboard.jpg or https://..."
                        value="<?= e($formData['image_url']) ?>"
                        required
                    >
                    <datalist id="bundled-images-list">
                        <?php foreach ($bundledImages as $path => $label): ?>
                            <option value="<?= e($path) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="prod-upload" class="form-label">Or Upload Product Image <span style="color: var(--text-muted); font-weight: 400;">(Optional)</span></label>
                    <input type="file" id="prod-upload" name="image_upload" class="form-control" accept="image/*">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="prod-desc" class="form-label">Product Description <span style="color: var(--danger);">*</span></label>
                <textarea
                    id="prod-desc"
                    name="description"
                    class="form-control"
                    rows="3"
                    placeholder="Detailed product specifications and features..."
                    required
                ><?= e($formData['description']) ?></textarea>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600;">
                    <input type="checkbox" name="is_featured" value="1" <?= (string)$formData['is_featured'] === '1' ? 'checked' : '' ?>>
                    Feature this product on the Customer Home Page
                </label>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" data-saving-text="Saving Product...">
                    <?= $formData['id'] ? 'Save Product Changes' : 'Create Product' ?>
                </button>
                <a href="products.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Admin Product Search & Filter Bar (Section 12) -->
<section class="filter-bar is-collapsible is-expanded">
    <div class="mobile-filter-header">
        <span style="font-weight: 700; font-size: 0.9rem;">Search &amp; Filter Products</span>
        <button type="button" class="btn btn-secondary btn-sm" data-filter-toggle="true" aria-expanded="true">
            <span data-filter-toggle-label="true">Hide Filters &#9650;</span>
        </button>
    </div>

    <form action="products.php" method="get" class="filter-form">
        <div class="form-group">
            <label for="admin-search" class="form-label">Search Products</label>
            <input
                type="search"
                id="admin-search"
                name="search"
                class="form-control"
                placeholder="Search by product name..."
                value="<?= e($search) ?>"
            >
        </div>

        <div class="form-group">
            <label for="admin-cat" class="form-label">Filter by Category</label>
            <select id="admin-cat" name="category_id" class="form-control">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="admin-status" class="form-label">Filter by Displayed Status</label>
            <select id="admin-status" name="status_filter" class="form-control">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="in_stock" <?= $statusFilter === 'in_stock' ? 'selected' : '' ?>>Active / In Stock</option>
                <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (Active, 0 Stock)</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
            </select>
        </div>

        <div class="form-group" style="display: flex; flex-direction: row; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="height: 39px;">Filter</button>
            <?php if ($search !== '' || $categoryId > 0 || $statusFilter !== 'all'): ?>
                <a href="products.php" class="btn btn-secondary" style="height: 39px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- Products Table -->
<?php
$summaryCounts = ['in-stock' => 0, 'out-of-stock' => 0, 'inactive' => 0];
foreach ($products as $p) {
    $summaryCounts[$p['status_badge']] = ($summaryCounts[$p['status_badge']] ?? 0) + 1;
}
?>
<div class="card product-table-card">
    <div class="product-table-toolbar">
        <div>
            <h2 class="product-table-title">Product Inventory</h2>
            <p class="product-table-sub">
                Showing <strong><?= count($products) ?></strong> product<?= count($products) === 1 ? '' : 's' ?>, sorted by ID
            </p>
        </div>
        <div class="product-table-legend">
            <span class="stock-badge in-stock"><?= (int)$summaryCounts['in-stock'] ?> In Stock</span>
            <span class="stock-badge out-of-stock"><?= (int)$summaryCounts['out-of-stock'] ?> Out of Stock</span>
            <span class="stock-badge inactive"><?= (int)$summaryCounts['inactive'] ?> Inactive</span>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state" style="border: none; margin: 0; box-shadow: none;">
            <h3>No Products Match Your Filter</h3>
            <p>Try clearing your search or status filter, or add a new product.</p>
            <a href="products.php" class="btn btn-secondary btn-sm">Reset Filters</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table mobile-stack-table product-admin-table">
                <colgroup>
                    <col class="col-id">
                    <col class="col-product">
                    <col class="col-category">
                    <col class="col-price">
                    <col class="col-stock">
                    <col class="col-status">
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">ID</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-right">Price</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                        <?php
                        $imgSrc   = strpos($prod['image_url'], 'http') === 0 ? $prod['image_url'] : '../' . ltrim($prod['image_url'], '/');
                        $stock    = (int)$prod['stock'];
                        $stockCls = $stock === 0 ? 'stock-zero' : ($stock <= 5 ? 'stock-low' : 'stock-ok');
                        $refCount = (int)$prod['order_reference_count'];
                        ?>
                        <tr class="<?= (int)$prod['is_active'] === 0 ? 'row-inactive' : '' ?>">
                            <td data-label="ID" class="text-center">
                                <span class="id-chip">#<?= (int)$prod['id'] ?></span>
                            </td>

                            <td data-label="Product" class="cell-product">
                                <div class="product-cell">
                                    <img src="<?= e($imgSrc) ?>" alt="<?= e($prod['name']) ?>" class="product-cell-thumb" onerror="this.onerror=null; this.src='../assets/images/prod-k75-keyboard.jpg';">
                                    <div class="product-cell-info">
                                        <a href="products.php?edit_id=<?= (int)$prod['id'] ?>" class="product-cell-name"><?= e($prod['name']) ?></a>
                                        <div class="product-cell-meta">
                                            <?php if ((int)$prod['is_featured'] === 1): ?>
                                                <span class="mini-tag tag-featured">&#9733; Featured</span>
                                            <?php endif; ?>
                                            <span class="mini-tag <?= $refCount > 0 ? 'tag-ref' : 'tag-noref' ?>">
                                                <?= $refCount > 0 ? $refCount . ' order line' . ($refCount === 1 ? '' : 's') : 'No orders yet' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td data-label="Category">
                                <span class="category-pill"><?= e($prod['category_name']) ?></span>
                            </td>

                            <td data-label="Price" class="text-right mono-num cell-price">
                                <?= e(formatPhp((int)$prod['price_cents'])) ?>
                            </td>

                            <td data-label="Stock" class="text-center">
                                <span class="stock-count <?= $stockCls ?>"><?= $stock ?></span>
                            </td>

                            <td data-label="Status" class="text-center">
                                <span class="stock-badge <?= e($prod['status_badge']) ?>">
                                    <?= e($prod['display_status']) ?>
                                </span>
                            </td>

                            <td data-label="Actions" class="text-right">
                                <div class="row-actions">
                                    <a href="products.php?edit_id=<?= (int)$prod['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>

                                    <?php if ($refCount > 0): ?>
                                        <?php if ((int)$prod['is_active'] === 1): ?>
                                            <form
                                                action="products.php"
                                                method="post"
                                                data-confirm="Deactivate '<?= e(addslashes($prod['name'])) ?>'? The product will be hidden from the storefront while keeping past customer order records safe."
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="deactivate_product">
                                                <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" title="Referenced by orders; safely mark inactive">
                                                    Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form
                                                action="products.php"
                                                method="post"
                                                data-confirm="Re-activate '<?= e(addslashes($prod['name'])) ?>' so it becomes visible on the customer storefront again?"
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="activate_product">
                                                <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" title="Make this product visible to customers">
                                                    Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form
                                            action="products.php"
                                            method="post"
                                            data-confirm="Are you sure you want to permanently delete '<?= e(addslashes($prod['name'])) ?>'? This product has no orders."
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>
