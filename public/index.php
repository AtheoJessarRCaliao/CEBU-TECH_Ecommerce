<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();
$categories = $repo->getCategories();
$featuredProducts = $repo->getProducts([
    'customer_only' => true,
    'featured_only' => true,
]);

if (count($featuredProducts) < 4) {
    $featuredProducts = array_slice($repo->getProducts(['customer_only' => true]), 0, 8);
}

$spotlightProduct = $repo->getProductById(1);

$pageTitle = 'Home';
$activeNav = 'home';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<!-- Promotional Hero Banner (Section 5) -->
<section class="hero-banner">
    <div>
        <span class="hero-tag">&#9889; CubeTech Innovations Collection</span>
        <h1 class="hero-title">Upgrade Your Desk With Precision Tech Gear</h1>
        <p class="hero-desc">
            Explore hot-swappable mechanical keyboards, studio-grade USB-C audio equipment, and workspace essentials engineered for developers and creators.
        </p>
        <div class="hero-actions">
            <a href="products.php" class="btn btn-primary btn-lg">Browse Full Catalog &rarr;</a>
            <a href="products.php?category_id=1" class="btn btn-outline-light btn-lg">Explore Keyboards &amp; Mice</a>
        </div>
    </div>

    <?php if ($spotlightProduct && (int)$spotlightProduct['is_active'] === 1): ?>
        <div class="hero-spotlight-card">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <span style="font-size: 0.74rem; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.05em;">
                    Flagship Spotlight
                </span>
                <span class="stock-badge in-stock">In Stock (<?= (int)$spotlightProduct['stock'] ?>)</span>
            </div>

            <a href="product.php?id=<?= (int)$spotlightProduct['id'] ?>" class="hero-spotlight-thumb">
                <img src="<?= e($spotlightProduct['image_url']) ?>" alt="<?= e($spotlightProduct['name']) ?>">
            </a>

            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: #fff; margin-bottom: 4px;">
                    <?= e($spotlightProduct['name']) ?>
                </h2>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                    Gasket-mounted 75% mechanical keyboard with tactile switches and double-shot PBT keycaps.
                </p>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.12);">
                <span class="mono-num" style="font-size: 1.3rem; font-weight: 800; color: #fff;">
                    <?= e(formatPhp((int)$spotlightProduct['price_cents'])) ?>
                </span>
                <a href="product.php?id=<?= (int)$spotlightProduct['id'] ?>" class="btn btn-sm btn-primary">
                    View Flagship &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Category Links Section (Section 5) -->
<section aria-labelledby="categories-heading">
    <div class="section-header">
        <div>
            <h2 id="categories-heading" class="section-title">Shop by Category</h2>
            <p class="section-subtitle">Select a hardware category to filter our catalog.</p>
        </div>
        <a href="products.php" class="btn btn-secondary btn-sm">View All Categories &rarr;</a>
    </div>

    <div class="category-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="products.php?category_id=<?= (int)$cat['id'] ?>" class="category-card">
                <div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p><?= e($cat['description'] ?: 'Browse products in ' . $cat['name']) ?></p>
                </div>
                <div class="category-meta">
                    <span><?= (int)$cat['active_product_count'] ?> Active Product<?= (int)$cat['active_product_count'] === 1 ? '' : 's' ?></span>
                    <span>Explore &rarr;</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Featured Products Section (Section 5) -->
<section aria-labelledby="featured-heading">
    <div class="section-header">
        <div>
            <h2 id="featured-heading" class="section-title">Featured Products</h2>
            <p class="section-subtitle">
                Hand-picked gear including active, zero-stock, and assessment benchmark items.
            </p>
        </div>
        <a href="products.php" class="btn btn-secondary btn-sm">See All Products &rarr;</a>
    </div>

    <?php if (empty($featuredProducts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#128717;</div>
            <h3>No Featured Products Available</h3>
            <p>Check back soon or browse our full product catalog.</p>
            <a href="products.php" class="btn btn-primary">Browse Catalog</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($featuredProducts as $product): ?>
                <?php require __DIR__ . '/../app/Views/partials/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
