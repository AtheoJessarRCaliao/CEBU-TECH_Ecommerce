<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Cart.php';
require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();
$cart = Cart::getDetailedCart();

// Section 8: "Prevent checkout with an empty cart."
if ($cart['is_empty']) {
    setFlash('warning', 'Your shopping cart is empty. Please add at least one product before proceeding to checkout.');
    header('Location: cart.php');
    exit;
}

$formData = [
    'customer_name'    => '',
    'customer_email'   => '',
    'contact_number'   => '',
    'delivery_address' => '',
    'payment_method'   => 'Cash on Delivery',
    'order_notes'      => '',
];
$fieldErrors     = [];
$generalErrors   = [];
$detectedChanges = [];

foreach ($cart['items'] as $cItem) {
    if (!empty($cItem['price_changed'])) {
        $detectedChanges[] = "The price of \"{$cItem['name']}\" changed from " . formatPhp((int)$cItem['snapshot_price_cents']) . " to " . formatPhp((int)$cItem['unit_price_cents']) . ". Please review the updated summary below before placing your order.";
    }
}
if (!empty($detectedChanges)) {
    Cart::syncSnapshotsWithLiveProducts();
    $cart = Cart::getDetailedCart();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $generalErrors[] = 'Invalid security token. Please review your form and submit again.';
    } else {
        $formData = [
            'customer_name'    => (string)($_POST['customer_name'] ?? ''),
            'customer_email'   => (string)($_POST['customer_email'] ?? ''),
            'contact_number'   => (string)($_POST['contact_number'] ?? ''),
            'delivery_address' => (string)($_POST['delivery_address'] ?? ''),
            'payment_method'   => (string)($_POST['payment_method'] ?? ''),
            'order_notes'      => (string)($_POST['order_notes'] ?? ''),
        ];

        $rawCart = Cart::getRawItems();
        $postedExpectedPrices = $_POST['expected_price_cents'] ?? [];
        if (is_array($postedExpectedPrices)) {
            foreach ($postedExpectedPrices as $pid => $expPrice) {
                $pidInt = (int)$pid;
                if (isset($rawCart[$pidInt])) {
                    $rawCart[$pidInt]['snapshot_price_cents'] = (int)$expPrice;
                }
            }
        }

        $result = $repo->placeOrder($formData, $rawCart);

        if ($result['success']) {
            Cart::clear();
            $_SESSION['last_confirmed_order_number'] = $result['order_number'];
            header('Location: confirmation.php?order=' . urlencode($result['order_number']));
            exit;
        } else {
            $fieldErrors     = $result['field_errors'] ?? [];
            $detectedChanges = $result['changes'] ?? [];
            if (!empty($result['changes_detected'])) {
                Cart::syncSnapshotsWithLiveProducts();
            } elseif (!empty($result['errors']) && empty($fieldErrors)) {
                $generalErrors = $result['errors'];
            }
            $cart = Cart::getDetailedCart();
        }
    }
}

$paymentDescriptions = [
    'Cash on Delivery' => 'Pay in cash when your order arrives at your doorstep.',
    'E-Wallet'         => 'Simulated GCash / Maya e-wallet selection (no live gateway).',
    'Bank Transfer'    => 'Simulated BPI / BDO / UnionBank transfer selection.',
];

$pageTitle = 'Checkout';
$activeNav = 'cart';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<div class="section-header" style="margin-top: 22px;">
    <div>
        <h1 class="section-title">Guest Checkout</h1>
        <p class="section-subtitle">
            Complete your delivery details and select a sample payment method to place your order.
        </p>
    </div>
    <a href="cart.php" class="btn btn-secondary btn-sm">&larr; Back to Cart</a>
</div>

<?php if (!empty($detectedChanges)): ?>
    <div class="alert alert-warning" role="alert">
        <div class="alert-content">
            <strong>Price or Availability Change Detected &mdash; Please Review Before Trying Again:</strong>
            <ul style="margin: 8px 0 0 18px;">
                <?php foreach ($detectedChanges as $changeMsg): ?>
                    <li><?= e($changeMsg) ?></li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top: 10px;">
                <a href="cart.php" class="btn btn-secondary btn-sm">Modify Cart Items</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($generalErrors)): ?>
    <div class="alert alert-error" role="alert">
        <div class="alert-content">
            <strong>Order Could Not Be Placed:</strong>
            <ul style="margin: 6px 0 0 18px;">
                <?php foreach ($generalErrors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($fieldErrors)): ?>
    <div class="alert alert-error" role="alert">
        <div class="alert-content">
            <strong>Please correct the highlighted fields below before submitting your order.</strong>
        </div>
    </div>
<?php endif; ?>

<form action="checkout.php" method="post" data-disable-on-submit="true" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <?php foreach ($cart['items'] as $cItem): ?>
        <input type="hidden" name="expected_price_cents[<?= (int)$cItem['product_id'] ?>]" value="<?= (int)$cItem['unit_price_cents'] ?>">
    <?php endforeach; ?>

    <div class="checkout-layout">
        <!-- Customer Details Form (Section 9) -->
        <div class="card">
            <h2 class="card-title">1. Customer &amp; Delivery Information</h2>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="customer_name" class="form-label">Customer Full Name <span style="color: var(--danger);">*</span></label>
                <input
                    type="text"
                    id="customer_name"
                    name="customer_name"
                    class="form-control"
                    placeholder="e.g. Maria Clara Santos"
                    value="<?= e($formData['customer_name']) ?>"
                    required
                >
                <?php if (isset($fieldErrors['customer_name'])): ?>
                    <span class="form-error"><?= e($fieldErrors['customer_name']) ?></span>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label for="customer_email" class="form-label">Email Address <span style="color: var(--danger);">*</span></label>
                    <input
                        type="email"
                        id="customer_email"
                        name="customer_email"
                        class="form-control"
                        placeholder="e.g. maria.santos@example.com"
                        value="<?= e($formData['customer_email']) ?>"
                        required
                    >
                    <?php if (isset($fieldErrors['customer_email'])): ?>
                        <span class="form-error"><?= e($fieldErrors['customer_email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="contact_number" class="form-label">Contact Number <span style="color: var(--danger);">*</span></label>
                    <input
                        type="tel"
                        id="contact_number"
                        name="contact_number"
                        class="form-control"
                        placeholder="e.g. +63 917 555 0142 or 0917-555-0142"
                        value="<?= e($formData['contact_number']) ?>"
                        required
                    >
                    <?php if (isset($fieldErrors['contact_number'])): ?>
                        <span class="form-error"><?= e($fieldErrors['contact_number']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="delivery_address" class="form-label">Delivery Address <span style="color: var(--danger);">*</span></label>
                <textarea
                    id="delivery_address"
                    name="delivery_address"
                    class="form-control"
                    rows="3"
                    placeholder="Street address, building/unit, barangay, city, postal code"
                    required
                ><?= e($formData['delivery_address']) ?></textarea>
                <?php if (isset($fieldErrors['delivery_address'])): ?>
                    <span class="form-error"><?= e($fieldErrors['delivery_address']) ?></span>
                <?php endif; ?>
            </div>

            <h2 class="card-title" style="margin-top: 8px;">2. Payment Method &amp; Order Notes</h2>

            <div class="form-group" style="margin-bottom: 18px;">
                <label class="form-label">Select Sample Payment Method <span style="color: var(--danger);">*</span></label>
                <div class="payment-methods-grid">
                    <?php foreach (PAYMENT_METHODS as $method): ?>
                        <?php $isChecked = ($formData['payment_method'] === $method); ?>
                        <label class="payment-option-card <?= $isChecked ? 'is-selected' : '' ?>">
                            <input
                                type="radio"
                                name="payment_method"
                                value="<?= e($method) ?>"
                                <?= $isChecked ? 'checked' : '' ?>
                                required
                            >
                            <div>
                                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-primary);"><?= e($method) ?></div>
                                <div style="font-size: 0.76rem; color: var(--text-secondary); margin-top: 2px;">
                                    <?= e($paymentDescriptions[$method] ?? '') ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($fieldErrors['payment_method'])): ?>
                    <span class="form-error"><?= e($fieldErrors['payment_method']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="order_notes" class="form-label">Order Notes <span style="color: var(--text-muted); font-weight: 400;">(Optional)</span></label>
                <textarea
                    id="order_notes"
                    name="order_notes"
                    class="form-control"
                    rows="2"
                    placeholder="Delivery instructions, landmarks, or preferred delivery time..."
                ><?= e($formData['order_notes']) ?></textarea>
            </div>
        </div>

        <!-- Order Review Summary Before Submission (Section 9) -->
        <aside class="card card-sticky">
            <h2 class="card-title">
                <span>Order Review</span>
                <span class="stock-badge in-stock"><?= (int)$cart['total_units'] ?> Unit<?= (int)$cart['total_units'] === 1 ? '' : 's' ?></span>
            </h2>

            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
                <?php foreach ($cart['items'] as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); font-size: 0.88rem;">
                        <div>
                            <div style="font-weight: 700; color: var(--text-primary);"><?= e($item['name']) ?></div>
                            <div style="color: var(--text-secondary);">
                                Qty: <strong><?= (int)$item['quantity'] ?></strong> &times; <span class="mono-num"><?= e(formatPhp((int)$item['unit_price_cents'])) ?></span>
                            </div>
                            <?php if ($item['has_issue']): ?>
                                <div style="color: var(--danger); font-weight: 700;"><?= e($item['issue_reason']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mono-num" style="font-weight: 700; white-space: nowrap;">
                            <?= e(formatPhp((int)$item['line_total_cents'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary-row">
                <span>Subtotal (<?= (int)$cart['total_units'] ?> unit<?= (int)$cart['total_units'] === 1 ? '' : 's' ?>)</span>
                <strong class="mono-num"><?= e(formatPhp((int)$cart['subtotal_cents'])) ?></strong>
            </div>
            <div class="summary-row total">
                <span>Final Total</span>
                <span class="mono-num"><?= e(formatPhp((int)$cart['total_cents'])) ?></span>
            </div>

            <p style="font-size: 0.78rem; color: var(--text-muted); margin: 12px 0 18px;">
                Product availability, unit prices, and stock quantities are re-verified on the server when you submit your order.
            </p>

            <?php if ($cart['has_issues']): ?>
                <a href="cart.php" class="btn btn-danger btn-lg btn-block">
                    Return to Cart to Fix Stock Issues
                </a>
            <?php else: ?>
                <button
                    type="submit"
                    class="btn btn-primary btn-lg btn-block"
                    data-saving-text="Saving Order... Please Wait"
                >
                    Place Order (<?= e(formatPhp((int)$cart['total_cents'])) ?>)
                </button>
            <?php endif; ?>
        </aside>
    </div>
</form>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
