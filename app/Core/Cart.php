<?php
declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

/**
 * CubeTech Hub - Persistent Shopping Cart Manager
 * Implements Section 8 (Shopping Cart) & Section 9 (Checkout Price/Availability Recheck)
 */
class Cart
{
    private const SESSION_KEY = 'cubetech_cart';
    private const COOKIE_NAME = 'cubetech_cart_backup';
    private const COOKIE_SECRET = 'cubetech-assessment-cart-secret-key-2026';

    /**
     * Ensure cart is initialized in session (restoring from signed cookie if session is fresh)
     */
    private static function init(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            // Restore from legacy session key if available
            if (isset($_SESSION['cebutech_cart']) && is_array($_SESSION['cebutech_cart'])) {
                $_SESSION[self::SESSION_KEY] = $_SESSION['cebutech_cart'];
            } else {
                $_SESSION[self::SESSION_KEY] = self::loadFromCookie();
            }
        }
    }

    private static function saveToCookie(array $cartData): void
    {
        if (headers_sent()) {
            return;
        }
        $json = json_encode($cartData);
        $sig  = hash_hmac('sha256', (string)$json, self::COOKIE_SECRET);
        $payload = base64_encode($sig . '|' . $json);
        setcookie(self::COOKIE_NAME, $payload, [
            'expires'  => time() + (86400 * 7),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function loadFromCookie(): array
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? $_COOKIE['cebutech_cart_backup'] ?? '';
        if (empty($raw)) {
            return [];
        }
        $decoded = base64_decode((string)$raw, true);
        if (!$decoded || strpos($decoded, '|') === false) {
            return [];
        }
        [$sig, $json] = explode('|', $decoded, 2);
        $expectedSig = hash_hmac('sha256', $json, self::COOKIE_SECRET);
        $legacySig   = hash_hmac('sha256', $json, 'cebutech-assessment-cart-secret-key-2026');
        if (!hash_equals($expectedSig, $sig) && !hash_equals($legacySig, $sig)) {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Return raw cart map: [product_id => ['product_id' => int, 'quantity' => int, 'snapshot_price_cents' => int, 'snapshot_name' => string]]
     */
    public static function getRawItems(): array
    {
        self::init();
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Add a product to the cart or increment its quantity if already present.
     * Validates positive whole number quantity, active status, and available stock.
     */
    public static function addItem(int $productId, $quantityInput = 1): array
    {
        self::init();

        // Validate positive whole number
        $qtyStr = trim((string)$quantityInput);
        if (!preg_match('/^\d+$/', $qtyStr) || (int)$qtyStr <= 0) {
            return [
                'success' => false,
                'error'   => 'Quantity must be a positive whole number (1 or more).',
            ];
        }
        $addQty = (int)$qtyStr;

        $repo = Repository::getInstance();
        $product = $repo->getProductById($productId);

        if (!$product || $product['is_active'] === 0) {
            return [
                'success' => false,
                'error'   => 'This product is unavailable or inactive and cannot be added to your cart.',
            ];
        }

        if ($product['stock'] <= 0) {
            return [
                'success' => false,
                'error'   => "\"{$product['name']}\" is currently Out of Stock and cannot be added to your cart.",
            ];
        }

        $existingQty = isset($_SESSION[self::SESSION_KEY][$productId])
            ? (int)$_SESSION[self::SESSION_KEY][$productId]['quantity']
            : 0;

        $newTotalQty = $existingQty + $addQty;

        if ($newTotalQty > $product['stock']) {
            $remainingAllowed = max(0, $product['stock'] - $existingQty);
            if ($existingQty > 0) {
                return [
                    'success' => false,
                    'error'   => "Cannot add {$addQty} more unit(s) of \"{$product['name']}\". You already have {$existingQty} in your cart and only {$product['stock']} unit(s) are available in stock.",
                ];
            }
            return [
                'success' => false,
                'error'   => "Requested quantity ({$addQty}) exceeds available stock ({$product['stock']}) for \"{$product['name']}\".",
            ];
        }

        // Merge duplicate product line by increasing quantity
        $_SESSION[self::SESSION_KEY][$productId] = [
            'product_id'           => $productId,
            'quantity'             => $newTotalQty,
            'snapshot_price_cents' => $product['price_cents'],
            'snapshot_name'        => $product['name'],
        ];

        self::saveToCookie($_SESSION[self::SESSION_KEY]);

        return [
            'success'  => true,
            'message'  => $existingQty > 0
                ? "Updated \"{$product['name']}\" quantity to {$newTotalQty} in your cart."
                : "Added {$addQty} × \"{$product['name']}\" to your cart.",
            'quantity' => $newTotalQty,
        ];
    }

    /**
     * Update quantity of a specific cart line
     */
    public static function updateQuantity(int $productId, $quantityInput): array
    {
        self::init();

        if (!isset($_SESSION[self::SESSION_KEY][$productId])) {
            return ['success' => false, 'error' => 'Item not found in your cart.'];
        }

        $qtyStr = trim((string)$quantityInput);
        if (!preg_match('/^\d+$/', $qtyStr) || (int)$qtyStr <= 0) {
            return [
                'success' => false,
                'error'   => 'Quantity must be a positive whole number (1 or more). To remove an item, use the Remove button.',
            ];
        }
        $newQty = (int)$qtyStr;

        $repo = Repository::getInstance();
        $product = $repo->getProductById($productId);

        if (!$product || $product['is_active'] === 0) {
            return [
                'success' => false,
                'error'   => 'This product is no longer active. Please remove it from your cart.',
            ];
        }

        if ($product['stock'] <= 0) {
            return [
                'success' => false,
                'error'   => "\"{$product['name']}\" is currently Out of Stock.",
            ];
        }

        if ($newQty > $product['stock']) {
            return [
                'success' => false,
                'error'   => "Cannot set quantity to {$newQty} for \"{$product['name']}\". Only {$product['stock']} unit(s) available in stock.",
            ];
        }

        $_SESSION[self::SESSION_KEY][$productId]['quantity']             = $newQty;
        $_SESSION[self::SESSION_KEY][$productId]['snapshot_price_cents'] = $product['price_cents'];
        $_SESSION[self::SESSION_KEY][$productId]['snapshot_name']        = $product['name'];

        self::saveToCookie($_SESSION[self::SESSION_KEY]);

        return [
            'success' => true,
            'message' => "Updated quantity for \"{$product['name']}\" to {$newQty}.",
        ];
    }

    /**
     * Remove a product line from the cart
     */
    public static function removeItem(int $productId): void
    {
        self::init();
        unset($_SESSION[self::SESSION_KEY][$productId]);
        self::saveToCookie($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Clear the entire shopping cart
     */
    public static function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
        self::saveToCookie([]);
    }

    /**
     * Sync cart snapshot prices/names with current live products (called after user reviews detected changes)
     */
    public static function syncSnapshotsWithLiveProducts(): void
    {
        self::init();
        $repo = Repository::getInstance();

        foreach ($_SESSION[self::SESSION_KEY] as $productId => &$entry) {
            $live = $repo->getProductById((int)$productId);
            if ($live && $live['is_active'] === 1 && $live['stock'] > 0) {
                $entry['snapshot_price_cents'] = $live['price_cents'];
                $entry['snapshot_name']        = $live['name'];
                if ($entry['quantity'] > $live['stock']) {
                    $entry['quantity'] = $live['stock'];
                }
            }
        }
        self::saveToCookie($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Count total units in the cart (for header badge)
     */
    public static function getTotalUnitCount(): int
    {
        self::init();
        $count = 0;
        foreach ($_SESSION[self::SESSION_KEY] as $entry) {
            $count += (int)($entry['quantity'] ?? 0);
        }
        return $count;
    }

    /**
     * Build detailed cart summary joined with live product data from Repository.
     * Calculates line totals, subtotal, and total in integer centavos (Section 8).
     */
    public static function getDetailedCart(): array
    {
        self::init();
        $repo = Repository::getInstance();

        $items         = [];
        $subtotalCents = 0;
        $totalUnits    = 0;
        $hasIssues     = false;
        $issueMessages = [];

        foreach ($_SESSION[self::SESSION_KEY] as $productId => $entry) {
            $pid  = (int)$productId;
            $qty  = (int)($entry['quantity'] ?? 0);
            $live = $repo->getProductById($pid);

            $snapshotPriceCents = (int)($entry['snapshot_price_cents'] ?? ($live['price_cents'] ?? 0));
            $snapshotName       = (string)($entry['snapshot_name'] ?? ($live['name'] ?? "Product #{$pid}"));

            if (!$live || $live['is_active'] === 0) {
                $hasIssues = true;
                $issueMessages[] = "\"{$snapshotName}\" is no longer available and must be removed before checkout.";
                $items[] = [
                    'product_id'           => $pid,
                    'name'                 => $live['name'] ?? $snapshotName,
                    'slug'                 => $live['slug'] ?? '',
                    'category_name'        => $live['category_name'] ?? 'Unavailable',
                    'image_url'            => $live['image_url'] ?? 'assets/images/prod-legacy-trackball.jpg',
                    'unit_price_cents'     => $live ? $live['price_cents'] : $snapshotPriceCents,
                    'snapshot_price_cents' => $snapshotPriceCents,
                    'quantity'             => $qty,
                    'line_total_cents'     => 0,
                    'stock'                => 0,
                    'is_active'            => 0,
                    'has_issue'            => true,
                    'issue_reason'         => 'Product is inactive/unavailable',
                ];
                continue;
            }

            $itemIssue = false;
            $issueReason = '';

            if ($live['stock'] <= 0) {
                $hasIssues = true;
                $itemIssue = true;
                $issueReason = 'Out of Stock';
                $issueMessages[] = "\"{$live['name']}\" is now Out of Stock.";
            } elseif ($qty > $live['stock']) {
                $hasIssues = true;
                $itemIssue = true;
                $issueReason = "Only {$live['stock']} left in stock";
                $issueMessages[] = "Requested quantity ({$qty}) for \"{$live['name']}\" exceeds available stock ({$live['stock']}).";
            }

            $priceChanged = ($snapshotPriceCents !== $live['price_cents']);
            if ($priceChanged) {
                $issueMessages[] = "Note: Price for \"{$live['name']}\" was updated from " . formatPhp($snapshotPriceCents) . " to " . formatPhp($live['price_cents']) . ".";
            }

            $lineTotalCents = $live['price_cents'] * $qty;
            $subtotalCents += $lineTotalCents;
            $totalUnits    += $qty;

            $items[] = [
                'product_id'           => $live['id'],
                'name'                 => $live['name'],
                'slug'                 => $live['slug'],
                'category_name'        => $live['category_name'],
                'image_url'            => $live['image_url'],
                'unit_price_cents'     => $live['price_cents'],
                'snapshot_price_cents' => $snapshotPriceCents,
                'price_changed'        => $priceChanged,
                'quantity'             => $qty,
                'line_total_cents'     => $lineTotalCents,
                'stock'                => $live['stock'],
                'is_active'            => $live['is_active'],
                'has_issue'            => $itemIssue,
                'issue_reason'         => $issueReason,
            ];
        }

        // Section 8: "For this assessment, subtotal equals total: no shipping fee, tax, voucher, or service charge is required."
        $totalCents = $subtotalCents;

        return [
            'items'          => $items,
            'item_count'     => count($items),
            'total_units'    => $totalUnits,
            'subtotal_cents' => $subtotalCents,
            'total_cents'    => $totalCents,
            'is_empty'       => empty($items),
            'has_issues'     => $hasIssues,
            'issue_messages' => $issueMessages,
        ];
    }
}

