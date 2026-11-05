<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';

Auth::requireAdmin();
$repo = Repository::getInstance();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['order_id'] ?? 0);
$order   = $orderId > 0 ? $repo->getOrderById($orderId) : null;

if (!$order) {
    setFlash('error', 'Order not found.');
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token.');
        header('Location: order.php?id=' . $orderId);
        exit;
    }

    $newStatus = trim((string)($_POST['new_status'] ?? ''));
    $res = $repo->updateOrderStatus($orderId, $newStatus);

    if ($res['success']) {
        setFlash('success', $res['message']);
    } else {
        setFlash('error', $res['error']);
    }

    header('Location: order.php?id=' . $orderId);
    exit;
}

$allowedTransitions = $order['allowed_transitions'];

$pageTitle = 'Order Details — ' . $order['order_number'];
$adminNav  = 'orders';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Order Details: <?= e($order['order_number']) ?></h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Placed on <?= e($order['created_at']) ?> &bull; Last updated <?= e($order['updated_at']) ?>
        </p>
    </div>
    <a href="orders.php" class="btn btn-secondary btn-sm">&larr; Back to Orders List</a>
</div>

<!-- Status Sequence & Transition Controls (Sections 10 & 14) -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
        <div>
            <h2 style="font-size: 1.08rem; font-weight: 700;">Fulfillment Status &amp; Transitions</h2>
            <p style="font-size: 0.84rem; color: var(--text-secondary);">
                Required sequence: <strong>Pending &rarr; Confirmed &rarr; Preparing &rarr; Shipped &rarr; Completed</strong>. Cancellation is permitted from <strong>Pending</strong> or <strong>Confirmed</strong> only and restores stock once.
            </p>
        </div>
        <span class="status-pill status-<?= e($order['status']) ?>" style="font-size: 0.95rem; padding: 6px 16px;">
            Saved Status: <?= e($order['status']) ?>
        </span>
    </div>

    <!-- Visual Step Pipeline -->
    <div class="pipeline-stepper">
        <?php
        $pipeline = ['Pending', 'Confirmed', 'Preparing', 'Shipped', 'Completed'];
        $currentIndex = array_search($order['status'], $pipeline, true);
        foreach ($pipeline as $idx => $step):
            $stepClass = '';
            if ($order['status'] === $step) {
                $stepClass = 'is-active';
            } elseif ($currentIndex !== false && $idx < $currentIndex) {
                $stepClass = 'is-done';
            }
        ?>
            <span class="pipeline-step <?= $stepClass ?>">
                <?= ($currentIndex !== false && $idx < $currentIndex) ? '&#10003; ' : '' ?><?= e($step) ?>
            </span>
        <?php endforeach; ?>
        <?php if ($order['status'] === 'Cancelled'): ?>
            <span class="status-pill status-Cancelled">
                Cancelled <?= (int)$order['stock_restored'] === 1 ? '(Stock Restored Once)' : '' ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($allowedTransitions)): ?>
        <div class="alert alert-info" style="margin: 0;">
            <strong>Final Status Reached:</strong> Order <code><?= e($order['order_number']) ?></code> is <strong><?= e($order['status']) ?></strong>.
            <?php if ($order['status'] === 'Cancelled' && (int)$order['stock_restored'] === 1): ?>
                Product stock quantities for this order have already been restored once.
            <?php endif; ?>
            Backward transitions and repeated cancellations are blocked.
        </div>
    <?php else: ?>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-weight: 700; font-size: 0.9rem;">Allowed Next Actions:</span>
                <?php foreach ($allowedTransitions as $nextStatus): ?>
                    <?php if ($nextStatus === 'Cancelled'): ?>
                        <form
                            action="order.php?id=<?= (int)$order['id'] ?>"
                            method="post"
                            data-confirm="Cancel order <?= e($order['order_number']) ?> and restore its item stock quantities?"
                            style="margin: 0;"
                        >
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                            <input type="hidden" name="new_status" value="Cancelled">
                            <button type="submit" class="btn btn-danger btn-sm">
                                Cancel Order &amp; Restore Stock
                            </button>
                        </form>
                    <?php else: ?>
                        <form action="order.php?id=<?= (int)$order['id'] ?>" method="post" data-disable-on-submit="true" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= e($nextStatus) ?>">
                            <button type="submit" class="btn btn-primary btn-sm" data-saving-text="Updating...">
                                Advance to <?= e($nextStatus) ?> &rarr;
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="checkout-layout" style="grid-template-columns: 1fr 1.6fr;">
    <!-- Customer, Contact, Delivery & Payment Details (Section 14) -->
    <div class="card">
        <h2 class="card-title">Customer &amp; Delivery Details</h2>

        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.92rem;">
            <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Customer Name</div>
                <div style="font-weight: 700;"><?= e($order['customer_name']) ?></div>
            </div>

            <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Email Address (Normalized)</div>
                <div><?= e($order['customer_email']) ?> <span style="color: var(--text-muted); font-size: 0.8rem;">(<code><?= e($order['normalized_email']) ?></code>)</span></div>
            </div>

            <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Contact Number</div>
                <div><?= e($order['contact_number']) ?></div>
            </div>

            <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Delivery Address</div>
                <div style="background: #f8fafc; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-top: 4px;">
                    <?= nl2br(e($order['delivery_address'])) ?>
                </div>
            </div>

            <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Payment Method</div>
                <div style="font-weight: 700;"><?= e($order['payment_method']) ?></div>
            </div>

            <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Order Notes</div>
                <div style="background: #f8fafc; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-top: 4px; color: var(--text-secondary);">
                    <?= trim((string)$order['order_notes']) !== '' ? nl2br(e($order['order_notes'])) : '<em>No order notes provided.</em>' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Line Items Snapshot & Totals (Section 10 & 14) -->
    <div class="card">
        <h2 class="card-title">Ordered Products (Immutable Snapshot)</h2>
        <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 12px;">
            Product names and unit prices below reflect the saved snapshot at the moment this order was placed and are unaffected by later catalog edits.
        </p>

        <div class="table-responsive" style="margin-bottom: 18px;">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>Product (Snapshot Name)</th>
                        <th>Unit Price (Snapshot)</th>
                        <th>Quantity</th>
                        <th>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td data-label="Product">
                                <div style="font-weight: 700; color: var(--text-primary);">
                                    <?= e($item['product_name_snapshot']) ?>
                                </div>
                                <div style="font-size: 0.76rem; color: var(--text-muted);">
                                    Product ID #<?= (int)$item['product_id'] ?>
                                </div>
                            </td>
                            <td data-label="Unit Price">
                                <?= e(formatPhp((int)$item['unit_price_cents_snapshot'])) ?>
                            </td>
                            <td data-label="Quantity" style="font-weight: 700;">
                                <?= (int)$item['quantity'] ?>
                            </td>
                            <td data-label="Line Total" style="font-weight: 800;">
                                <?= e(formatPhp((int)$item['line_total_cents'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="max-width: 340px; margin-left: auto;">
            <div class="summary-row">
                <span>Subtotal</span>
                <strong><?= e(formatPhp((int)$order['subtotal_cents'])) ?></strong>
            </div>
            <div class="summary-row total">
                <span>Order Total</span>
                <span><?= e(formatPhp((int)$order['total_cents'])) ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>

