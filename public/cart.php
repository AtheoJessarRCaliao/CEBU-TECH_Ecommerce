<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Cart.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: cart.php');
        exit;
    }

    $action    = (string)($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);

    switch ($action) {
        case 'add':
            $qty = $_POST['quantity'] ?? 1;
            $res = Cart::addItem($productId, $qty);
            if ($res['success']) {
                setFlash('success', $res['message']);
            } else {
                setFlash('error', $res['error']);
            }
            $redirect = (string)($_POST['redirect_to'] ?? 'cart.php');
            if (strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
                $redirect = 'cart.php';
            }
            header('Location: ' . $redirect);
            exit;

        case 'update':
            $qty = $_POST['quantity'] ?? 1;
            $res = Cart::updateQuantity($productId, $qty);
            if ($res['success']) {
                setFlash('success', $res['message']);
            } else {
                setFlash('error', $res['error']);
            }
            header('Location: cart.php');
            exit;

        case 'remove':
            Cart::removeItem($productId);
            setFlash('info', 'Item removed from your cart.');
            header('Location: cart.php');
            exit;

        case 'clear':
            Cart::clear();
            setFlash('info', 'Your shopping cart has been cleared.');
            header('Location: cart.php');
            exit;

        case 'load_example':
            Cart::clear();
            $r1 = Cart::addItem(9, 2);
            $r2 = Cart::addItem(10, 1);
            if ($r1['success'] && $r2['success']) {
                setFlash('success', 'Loaded Assessment Example: 2 × PHP 120.00 + 1 × PHP 85.50 = PHP 325.50.');
            } else {
                setFlash('warning', 'Could not load example items (check if stock or prices were modified in Admin).');
            }
            header('Location: cart.php');
            exit;
    }
}

$cart = Cart::getDetailedCart();

$pageTitle = 'Shopping Cart';
$activeNav = 'cart';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<div class="section-header" style="margin-top: 22px;">
    <div>
        <h1 class="section-title">Your Shopping Cart</h1>
        <p class="section-subtitle">
            Cart contents persist automatically across browser refreshes. Use the stepper buttons to adjust quantities.
        </p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <form action="cart.php" method="post" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="load_example">
            <button type="submit" class="btn btn-secondary btn-sm" title="Seeds 2 × PHP 120.00 + 1 × PHP 85.50">
                &#9889; Load PHP 325.50 Test Example
            </button>
        </form>

        <?php if (!$cart['is_empty']): ?>
            <form action="cart.php" method="post" data-confirm="Clear all items from your shopping cart?" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-secondary btn-sm">Clear Cart</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($cart['issue_messages'])): ?>
    <div class="alert alert-warning">
        <div class="alert-content">
            <strong>Cart Availability / Price Notice:</strong>
            <ul style="margin: 6px 0 0 18px;">
                <?php foreach ($cart['issue_messages'] as $msg): ?>
                    <li><?= e($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($cart['is_empty']): ?>
    <div class="empty-state">
        <div class="empty-state-icon">&#128722;</div>
        <h3>Your Shopping Cart is Empty</h3>
        <p>You haven't added any items yet. Browse our catalog to add products to your cart.</p>
        <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
            <a href="products.php" class="btn btn-primary">Browse Products</a>
            <button type="button" class="btn btn-secondary disabled" disabled aria-disabled="true">
                Checkout Disabled (Empty Cart)
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="cart-layout">
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="data-table mobile-stack-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Line Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart['items'] as $item): ?>
                            <tr data-unit-cents="<?= (int)$item['unit_price_cents'] ?>">
                                <td data-label="Product">
                                    <div style="display: flex; align-items: center; gap: 12px; text-align: left;">
                                        <img
                                            src="<?= e($item['image_url']) ?>"
                                            alt="<?= e($item['name']) ?>"
                                            style="width: 60px; height: 44px; object-fit: cover; border-radius: 8px; flex-shrink: 0; background: #0f172a;"
                                        >
                                        <div>
                                            <a href="product.php?id=<?= (int)$item['product_id'] ?>" style="font-weight: 700; color: var(--text-primary);">
                                                <?= e($item['name']) ?>
                                            </a>
                                            <div style="font-size: 0.78rem; color: var(--text-secondary);">
                                                <?= e($item['category_name']) ?> &bull;
                                                <?php if ($item['has_issue']): ?>
                                                    <span style="color: var(--danger); font-weight: 700;"><?= e($item['issue_reason']) ?></span>
                                                <?php else: ?>
                                                    <span><?= (int)$item['stock'] ?> available</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td data-label="Unit Price" class="mono-num" style="font-weight: 600;">
                                    <?= e(formatPhp((int)$item['unit_price_cents'])) ?>
                                </td>

                                <td data-label="Quantity">
                                    <?php if ((int)$item['is_active'] === 1 && (int)$item['stock'] > 0): ?>
                                        <form action="cart.php" method="post" style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">

                                            <div class="qty-input-group">
                                                <button type="button" class="qty-btn" data-qty-step="-1" aria-label="Decrease">&minus;</button>
                                                <input
                                                    type="number"
                                                    name="quantity"
                                                    class="qty-input"
                                                    value="<?= (int)$item['quantity'] ?>"
                                                    min="1"
                                                    max="<?= (int)$item['stock'] ?>"
                                                    step="1"
                                                    aria-label="Quantity for <?= e($item['name']) ?>"
                                                    required
                                                >
                                                <button type="button" class="qty-btn" data-qty-step="1" aria-label="Increase">&plus;</button>
                                            </div>

                                            <button type="submit" class="btn btn-secondary btn-sm" data-update-qty-btn="true">Update</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="stock-badge out-of-stock">Unavailable</span>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Line Total" data-line-total-display="true" class="mono-num" style="font-weight: 800; color: var(--text-primary);">
                                    <?= e(formatPhp((int)$item['line_total_cents'])) ?>
                                </td>

                                <td data-label="Action">
                                    <form action="cart.php" method="post" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cart Totals Summary (Section 8: Subtotal equals Total) -->
        <aside class="card card-sticky">
            <h2 class="card-title">Order Summary</h2>

            <div class="summary-row">
                <span>Unique Product Lines</span>
                <strong><?= (int)$cart['item_count'] ?></strong>
            </div>
            <div class="summary-row">
                <span>Total Units</span>
                <strong><?= (int)$cart['total_units'] ?></strong>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <strong class="mono-num"><?= e(formatPhp((int)$cart['subtotal_cents'])) ?></strong>
            </div>
            <div class="summary-row">
                <span>Shipping / Fees</span>
                <span style="color: var(--success-text); font-weight: 700;">PHP 0.00 (None)</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span class="mono-num"><?= e(formatPhp((int)$cart['total_cents'])) ?></span>
            </div>

            <p style="font-size: 0.78rem; color: var(--text-muted); margin: 12px 0 18px;">
                Per assessment rules, subtotal equals total with exact two-decimal centavo math.
            </p>

            <?php if ($cart['has_issues']): ?>
                <div class="alert alert-error" style="margin-bottom: 12px;">
                    <div class="alert-content">
                        Please resolve the stock or availability issues highlighted in your cart before proceeding to checkout.
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-lg btn-block disabled" disabled aria-disabled="true">
                    Resolve Cart Issues to Checkout
                </button>
            <?php else: ?>
                <a href="checkout.php" class="btn btn-primary btn-lg btn-block">
                    Proceed to Checkout &rarr;
                </a>
            <?php endif; ?>

            <a href="products.php" class="btn btn-secondary btn-block" style="margin-top: 10px;">
                &larr; Continue Shopping
            </a>
        </aside>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
