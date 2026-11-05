<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();
$categories = $repo->getCategories();

$search     = trim((string)($_GET['search'] ?? ''));
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : 0;
$sort       = trim((string)($_GET['sort'] ?? 'default'));

if (!in_array($sort, ['default', 'price_asc', 'price_desc', 'name_asc'], true)) {
    $sort = 'default';
}

$selectedCategory = $categoryId > 0 ? $repo->getCategoryById($categoryId) : null;
$hasActiveFilters = ($search !== '' || $categoryId > 0 || $sort !== 'default');

$products = $repo->getProducts([
    'customer_only' => true,
    'search'        => $search,
    'category_id'   => $categoryId,
    'sort'          => $sort,
]);

$pageTitle = $selectedCategory ? $selectedCategory['name'] . ' — Products' : 'All Products';
$activeNav = 'products';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<div class="section-header" style="margin-top: 22px;">
    <div>
        <h1 class="section-title">
            <?= $selectedCategory ? e($selectedCategory['name']) : 'Product Catalog' ?>
        </h1>
        <p class="section-subtitle">
            Showing <strong><?= count($products) ?></strong> active product<?= count($products) === 1 ? '' : 's' ?>
            <?php if ($search !== ''): ?>
                matching <strong>"<?= e($search) ?>"</strong>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($hasActiveFilters): ?>
        <a href="products.php" class="btn btn-secondary btn-sm">Reset All Filters</a>
    <?php endif; ?>
</div>

<!-- Combined Search, Category Filter & Price Sort Bar (Section 6) -->
<section class="filter-bar is-collapsible is-expanded" aria-label="Product Search, Filter, and Sort">
    <div class="mobile-filter-header">
        <span style="font-weight: 700; font-size: 0.9rem;">Search, Filter &amp; Sort</span>
        <button type="button" class="btn btn-secondary btn-sm" data-filter-toggle="true" aria-expanded="true">
            <span data-filter-toggle-label="true">Hide Filters &#9650;</span>
        </button>
    </div>

    <form action="products.php" method="get" class="filter-form">
        <div class="form-group">
            <label for="search-input" class="form-label">Search by Product Name</label>
            <input
                type="search"
                id="search-input"
                name="search"
                class="form-control"
                placeholder="e.g. Keyboard, USB-C, Mouse..."
                value="<?= e($search) ?>"
            >
        </div>

        <div class="form-group">
            <label for="category-select" class="form-label">Category Filter</label>
            <select id="category-select" name="category_id" class="form-control">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?> (<?= (int)$cat['active_product_count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="sort-select" class="form-label">Sort by Price</label>
            <select id="sort-select" name="sort" class="form-control">
                <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>Featured / Default</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
            </select>
        </div>

        <div class="form-group" style="display: flex; flex-direction: row; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="flex: 1; min-height: 42px;">Apply</button>
            <?php if ($hasActiveFilters): ?>
                <a href="products.php" class="btn btn-secondary" style="min-height: 42px;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($hasActiveFilters): ?>
        <div class="active-filter-chips">
            <span style="color: var(--text-secondary); font-weight: 600;">Active Filters:</span>
            <?php if ($search !== ''): ?>
                <span class="filter-chip">Search: "<?= e($search) ?>"</span>
            <?php endif; ?>
            <?php if ($selectedCategory): ?>
                <span class="filter-chip">Category: <?= e($selectedCategory['name']) ?></span>
            <?php endif; ?>
            <?php if ($sort !== 'default'): ?>
                <span class="filter-chip">
                    Sort: <?= $sort === 'price_asc' ? 'Price: Low to High' : ($sort === 'price_desc' ? 'Price: High to Low' : 'Name: A to Z') ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Responsive Product Grid or Empty State (Section 6) -->
<?php if (empty($products)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">&#128269;</div>
        <h3>No Matching Products Found</h3>
        <p>
            We couldn't find any active products matching your current search or filter criteria
            <?php if ($search !== ''): ?>
                (<strong>"<?= e($search) ?>"</strong>)
            <?php endif; ?>.
        </p>
        <a href="products.php" class="btn btn-primary">Clear Search &amp; Filters</a>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <?php require __DIR__ . '/../app/Views/partials/product_card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
