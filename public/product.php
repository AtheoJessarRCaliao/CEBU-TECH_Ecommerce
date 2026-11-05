<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product   = $productId > 0 ? $repo->getProductById($productId) : null;

// Section 6: "Inactive products: Hide them from customer listings and prevent ordering through a direct product link."
if (!$product || (int)$product['is_active'] === 0) {
    http_response_code(404);
    $pageTitle = 'Product Unavailable';
    $activeNav = 'products';
    require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
    ?>
    <div class="empty-state" style="margin-top: 32px;">
        <span class="stock-badge inactive" style="margin-bottom: 14px;">Unavailable / Inactive Product</span>
        <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 10px;">
            <?= $product ? e($product['name']) . ' is Currently Unavailable' : 'Product Not Found' ?>
        </h1>
        <p>
            This product is either discontinued, marked inactive by the store administrator, or does not exist. Ordering through a direct link is disabled.
        </p>
        <a href="products.php" class="btn btn-primary">&larr; Browse Available Products</a>
    </div>
    <?php
    require_once __DIR__ . '/../app/Views/layouts/customer_footer.php';
    exit;
}

$isOutOfStock    = ((int)$product['stock'] <= 0);
$relatedProducts = $repo->getRelatedProducts((int)$product['id'], (int)$product['category_id'], 3);

$pageTitle = $product['name'];
$activeNav = 'products';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <a href="index.php">Home</a>
    <span>&rsaquo;</span>
    <a href="products.php">Products</a>
    <span>&rsaquo;</span>
    <a href="products.php?category_id=<?= (int)$product['category_id'] ?>"><?= e($product['category_name']) ?></a>
    <span>&rsaquo;</span>
    <span class="breadcrumb-current"><?= e($product['name']) ?></span>
</nav>

<section class="product-detail-card">
    <div class="detail-image-box">
        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>" onerror="this.onerror=null; this.src='assets/images/prod-k75-keyboard.jpg';">
    </div>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
            <a href="products.php?category_id=<?= (int)$product['category_id'] ?>" class="product-category-label">
                <?= e($product['category_name']) ?>
            </a>
            <?php if ($isOutOfStock): ?>
                <span class="stock-badge out-of-stock">Out of Stock (0 available)</span>
            <?php else: ?>
                <span class="stock-badge in-stock">In Stock &mdash; <?= (int)$product['stock'] ?> unit<?= (int)$product['stock'] === 1 ? '' : 's' ?> available</span>
            <?php endif; ?>
        </div>

        <h1 style="font-size: clamp(1.45rem, 2.5vw, 1.9rem); font-weight: 800; line-height: 1.25; letter-spacing: -0.02em;">
            <?= e($product['name']) ?>
        </h1>

        <div class="detail-price">
            <?= e(formatPhp((int)$product['price_cents'])) ?>
        </div>

        <p class="detail-desc">
            <?= nl2br(e($product['description'])) ?>
        </p>

        <div class="detail-specs-box">
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Stock Status</div>
                <div style="font-weight: 700; color: var(--text-primary);"><?= (int)$product['stock'] ?> unit<?= (int)$product['stock'] === 1 ? '' : 's' ?> available</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Payment Options</div>
                <div style="font-weight: 600; color: var(--text-primary);"><?= e(implode(', ', PAYMENT_METHODS)) ?></div>
            </div>
        </div>

        <?php if ($isOutOfStock): ?>
            <div class="alert alert-warning" style="margin-bottom: 16px;">
                <div class="alert-content">
                    This item is currently <strong>Out of Stock</strong> and cannot be added to your cart right now.
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-lg btn-block disabled" disabled aria-disabled="true">
                Out of Stock &mdash; Add to Cart Disabled
            </button>
        <?php else: ?>
            <form action="cart.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <input type="hidden" name="redirect_to" value="cart.php">

                <div class="qty-selector-row">
                    <label for="detail-qty" style="font-weight: 700; font-size: 0.92rem;">Quantity:</label>
                    <div class="qty-input-group">
                        <button type="button" class="qty-btn" data-qty-step="-1" aria-label="Decrease quantity">&minus;</button>
                        <input
                            type="number"
                            id="detail-qty"
                            name="quantity"
                            class="qty-input"
                            value="1"
                            min="1"
                            max="<?= (int)$product['stock'] ?>"
                            step="1"
                            required
                        >
                        <button type="button" class="qty-btn" data-qty-step="1" aria-label="Increase quantity">&plus;</button>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="flex: 1; min-width: 200px;">
                        &plus; Add to Cart
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<!-- Related Products Section (Section 7) -->
<?php if (!empty($relatedProducts)): ?>
    <section aria-labelledby="related-heading" style="margin-top: 40px;">
        <div class="section-header">
            <div>
                <h2 id="related-heading" class="section-title">Related Products in <?= e($product['category_name']) ?></h2>
                <p class="section-subtitle">Up to three active products from the same category.</p>
            </div>
            <a href="products.php?category_id=<?= (int)$product['category_id'] ?>" class="btn btn-secondary btn-sm">
                View All in Category &rarr;
            </a>
        </div>

        <div class="product-grid">
            <?php foreach ($relatedProducts as $product): ?>
                <?php require __DIR__ . '/../app/Views/partials/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
