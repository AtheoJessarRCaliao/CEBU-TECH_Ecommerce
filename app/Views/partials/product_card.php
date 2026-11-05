<?php
declare(strict_types=1);
/**
 * Reusable Customer Product Card Component
 * Expects $product array from Repository::getProducts()
 */
$isOutOfStock = ((int)$product['stock'] <= 0);
$returnUrl    = $_SERVER['REQUEST_URI'] ?? 'products.php';
?>
<article class="product-card">
    <a href="product.php?id=<?= (int)$product['id'] ?>" class="product-thumb-wrap">
        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/images/prod-k75-keyboard.jpg';">
    </a>

    <div class="product-body">
        <div class="product-meta-top">
            <span class="product-category-label"><?= e($product['category_name']) ?></span>
            <?php if ($isOutOfStock): ?>
                <span class="stock-badge out-of-stock">Out of Stock</span>
            <?php else: ?>
                <span class="stock-badge in-stock">In Stock (<?= (int)$product['stock'] ?>)</span>
            <?php endif; ?>
        </div>

        <h3 class="product-title">
            <a href="product.php?id=<?= (int)$product['id'] ?>"><?= e($product['name']) ?></a>
        </h3>

        <div class="product-price-row">
            <span class="product-price"><?= e(formatPhp((int)$product['price_cents'])) ?></span>
        </div>

        <div class="product-actions">
            <a href="product.php?id=<?= (int)$product['id'] ?>" class="btn btn-secondary btn-sm">
                View Details
            </a>

            <?php if ($isOutOfStock): ?>
                <button type="button" class="btn btn-secondary btn-sm disabled" disabled aria-disabled="true" title="Currently Out of Stock">
                    Out of Stock
                </button>
            <?php else: ?>
                <form action="cart.php" method="post" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="redirect_to" value="<?= e($returnUrl) ?>">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                        &plus; Add to Cart
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
