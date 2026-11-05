<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();

// Handle Customer Self-Service Cancellation (Permitted from Pending or Confirmed only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = (string)($_POST['action'] ?? '');
    $postOrderNum = trim((string)($_POST['order_number'] ?? ''));
    $csrf         = (string)($_POST['csrf_token'] ?? '');

    if (!verifyCsrf($csrf)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: track.php' . ($postOrderNum !== '' ? '?order=' . urlencode($postOrderNum) : ''));
        exit;
    }

    $targetOrder = $postOrderNum !== '' ? $repo->getOrderByNumber($postOrderNum) : null;
    if (!$targetOrder) {
        setFlash('danger', 'The requested order could not be found.');
        header('Location: track.php');
        exit;
    }

    if ($action === 'cancel_order') {
        // Section 10 Rule: Permit cancellation from Pending or Confirmed only. Completed and Cancelled are final.
        if (!in_array($targetOrder['status'], ['Pending', 'Confirmed'], true)) {
            setFlash('danger', "Cancellation is rejected: orders in \"{$targetOrder['status']}\" status cannot be cancelled. Cancellation is permitted from Pending or Confirmed only.");
        } else {
            $res = $repo->updateOrderStatus((int)$targetOrder['id'], 'Cancelled');
            if ($res['success']) {
                setFlash('success', "Order {$targetOrder['order_number']} has been cancelled. Product stock quantities have been restored to available inventory.");
            } else {
                setFlash('danger', $res['error'] ?? 'Failed to cancel order.');
            }
        }
    }

    header('Location: track.php?order=' . urlencode($postOrderNum));
    exit;
}

$queryNum = trim((string)($_GET['order'] ?? ''));
$order    = $queryNum !== '' ? $repo->getOrderByNumber($queryNum) : null;
$recentOrders = $repo->getOrders();

$pageTitle = 'Track Order Status';
$activeNav = 'track';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<div class="section-header" style="margin-top: 22px;">
    <div>
        <h1 class="section-title">Track Your Order</h1>
        <p class="section-subtitle">
            Enter your Order Number (e.g., <code>ORD-20261101-1001</code>) to check live status, or select a recent customer order below.
        </p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <?php foreach (array_slice($recentOrders, 0, 4) as $pillOrd): ?>
            <a href="track.php?order=<?= urlencode($pillOrd['order_number']) ?>" 
               class="btn <?= ($order && $order['order_number'] === $pillOrd['order_number']) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                <?= e($pillOrd['order_number']) ?> (<?= e($pillOrd['status']) ?>)
            </a>
        <?php endforeach; ?>
    </div>
</div>

<section class="filter-bar">
    <form action="track.php" method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
        <div class="form-group" style="flex: 1; min-width: 240px;">
            <label for="order-input" class="form-label">Order Number</label>
            <input
                type="text"
                id="order-input"
                name="order"
                class="form-control"
                placeholder="e.g. ORD-20261101-1001"
                value="<?= e($queryNum) ?>"
                required
            >
        </div>
        <button type="submit" class="btn btn-primary" style="min-height: 42px;">Look Up Order</button>
        <?php if ($queryNum !== ''): ?>
            <a href="track.php" class="btn btn-secondary" style="min-height: 42px;">Clear Filter</a>
        <?php endif; ?>
    </form>
</section>

<?php if ($queryNum !== '' && !$order): ?>
    <div class="empty-state" style="margin-bottom: 32px;">
        <div class="empty-state-icon">&#128269;</div>
        <h3>Order Not Found</h3>
        <p>No saved order matches <strong>"<?= e($queryNum) ?>"</strong>. Please check your order number or select from the recent orders below.</p>
    </div>
<?php elseif ($order): ?>
    <?php
    $pipeline = ['Pending', 'Confirmed', 'Preparing', 'Shipped', 'Completed'];
    $currentIndex = array_search($order['status'], $pipeline, true);
    $canCancel  = in_array($order['status'], ['Pending', 'Confirmed'], true);
    ?>
    <div class="card" style="margin-bottom: 36px; border-left: 4px solid var(--primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
            <div>
                <span class="stock-badge in-stock" style="margin-bottom: 6px; display: inline-block;">Live Fulfillment Status</span>
                <h2 class="mono-num" style="font-size: 1.35rem; font-weight: 800; margin: 4px 0;">Order <?= e($order['order_number']) ?></h2>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Placed on <?= e(formatDateTime($order['created_at'])) ?> &bull; Updated <?= e(formatDateTime($order['updated_at'])) ?>
                </div>
            </div>
            <span class="status-pill status-<?= e($order['status']) ?>" style="font-size: 0.95rem; padding: 7px 16px;">
                Current Status: <?= e($order['status']) ?>
            </span>
        </div>

        <!-- Status Pipeline Indicator -->
        <div class="pipeline-stepper" style="margin: 20px 0;">
            <?php foreach ($pipeline as $idx => $step):
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
                <span class="status-pill status-Cancelled" style="font-size: 0.88rem; padding: 8px 16px;">
                    &#9888; Cancelled (Stock Restored)
                </span>
            <?php endif; ?>
        </div>

        <!-- Customer Order Action Controls (Eligible Cancellation from Pending or Confirmed) -->
        <?php if ($canCancel): ?>
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <strong style="font-size: 0.9rem;">Order Cancellation</strong>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                        This order is currently <strong><?= e($order['status']) ?></strong> and eligible for cancellation. Cancelling will release the items back to available inventory.
                    </div>
                </div>

                <form method="post" action="track.php" data-confirm="Are you sure you want to cancel Order <?= e($order['order_number']) ?>? Product stock will be immediately restored to inventory.">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="order_number" value="<?= e($order['order_number']) ?>">
                    <input type="hidden" name="action" value="cancel_order">
                    <button type="submit" class="btn btn-danger btn-sm">
                        &#10006; Cancel Order
                    </button>
                </form>
            </div>
        <?php elseif ($order['status'] === 'Cancelled'): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #991b1b;">
                <strong>Order Cancelled:</strong> This order has been cancelled and product stock quantities were restored to available inventory.
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 20px; font-size: 0.9rem;">
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px;">
                <h3 style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); margin-bottom: 8px;">Customer Information</h3>
                <strong>Name:</strong> <?= e($order['customer_name']) ?><br>
                <strong>Email:</strong> <?= e($order['customer_email']) ?><br>
                <strong>Contact:</strong> <?= e($order['contact_number']) ?>
            </div>
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px;">
                <h3 style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); margin-bottom: 8px;">Delivery &amp; Payment</h3>
                <strong>Payment Method:</strong> <?= e($order['payment_method']) ?><br>
                <strong>Delivery Address:</strong><br><?= nl2br(e($order['delivery_address'])) ?>
                <?php if (trim((string)$order['order_notes']) !== ''): ?>
                    <br><strong>Notes:</strong> <?= e($order['order_notes']) ?>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 12px;">Ordered Items (Snapshot)</h3>
        <div class="table-responsive">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>Item Name (Saved Snapshot)</th>
                        <th>Unit Price (Purchased)</th>
                        <th>Quantity</th>
                        <th>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td data-label="Item" style="font-weight: 700;"><?= e($item['product_name_snapshot']) ?></td>
                            <td data-label="Unit Price" class="mono-num"><?= e(formatPhp((int)$item['unit_price_cents_snapshot'])) ?></td>
                            <td data-label="Quantity" style="font-weight: 700;"><?= (int)$item['quantity'] ?></td>
                            <td data-label="Line Total" class="mono-num" style="font-weight: 800;"><?= e(formatPhp((int)$item['line_total_cents'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="background: var(--bg-subtle); border-radius: var(--radius-sm); border-left: 3px solid var(--primary); padding: 10px 14px; margin-top: 14px; font-size: 0.82rem; color: var(--text-secondary);">
            <strong>Snapshot Guarantee:</strong> Item names and unit prices are permanently snapshotted in this order record. Later product catalog changes or price adjustments will not alter past order receipts.
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border-color);">
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                Subtotal equals Total: No shipping fee, tax, voucher, or service charge added.
            </div>
            <div class="mono-num" style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary);">
                Total: <?= e(formatPhp((int)$order['total_cents'])) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Customer Orders Section -->
<section style="margin-top: 28px; margin-bottom: 40px;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color);">
            <div>
                <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0 0 4px;">Recent Customer Orders</h2>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">
                    Click <strong>Track Order</strong> on any customer order below to view its complete fulfillment timeline and itemized receipt.
                </p>
            </div>
            <span class="stock-badge in-stock" style="font-size: 0.8rem;">
                <?= count($recentOrders) ?> <?= count($recentOrders) === 1 ? 'Order Recorded' : 'Orders Recorded' ?>
            </span>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div class="empty-state" style="padding: 30px;">
                <p style="color: var(--text-secondary); margin: 0;">No customer orders found in the database yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table mobile-stack-table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Placed At</th>
                            <th>Items / Units</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $rOrd): 
                            $isSelected = $order && $order['order_number'] === $rOrd['order_number'];
                        ?>
                            <tr style="<?= $isSelected ? 'background: #f0f9ff; outline: 2px solid var(--primary);' : '' ?>">
                                <td data-label="Order Number" class="mono-num" style="font-weight: 800;">
                                    <a href="track.php?order=<?= urlencode($rOrd['order_number']) ?>" style="color: var(--primary); text-decoration: none;">
                                        <?= e($rOrd['order_number']) ?>
                                    </a>
                                    <?php if ($isSelected): ?>
                                        <div style="font-size: 0.72rem; color: #0284c7; font-weight: 700; margin-top: 2px;">&bull; Currently Viewing</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Customer">
                                    <div style="font-weight: 600;"><?= e($rOrd['customer_name']) ?></div>
                                    <div style="font-size: 0.76rem; color: var(--text-secondary);"><?= e($rOrd['delivery_address']) ?></div>
                                </td>
                                <td data-label="Placed At" style="font-size: 0.84rem; color: var(--text-secondary);">
                                    <?= e(formatDateTime($rOrd['created_at'])) ?>
                                </td>
                                <td data-label="Items / Units" style="font-size: 0.86rem;">
                                    <strong><?= (int)$rOrd['total_units'] ?></strong> <?= (int)$rOrd['total_units'] === 1 ? 'unit' : 'units' ?>
                                    <span style="color: var(--text-muted); font-size: 0.76rem;">(<?= (int)$rOrd['line_count'] ?> <?= (int)$rOrd['line_count'] === 1 ? 'item' : 'items' ?>)</span>
                                </td>
                                <td data-label="Total" class="mono-num" style="font-weight: 800; color: var(--text-primary);">
                                    <?= e(formatPhp((int)$rOrd['total_cents'])) ?>
                                </td>
                                <td data-label="Status">
                                    <span class="status-pill status-<?= e($rOrd['status']) ?>">
                                        <?= e($rOrd['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Action">
                                    <a href="track.php?order=<?= urlencode($rOrd['order_number']) ?>" class="btn <?= $isSelected ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                                        <?= $isSelected ? 'Viewing &check;' : 'Track Order &rarr;' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
