<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();
$orderNum = trim((string)($_GET['order'] ?? ($_SESSION['last_confirmed_order_number'] ?? '')));
$order    = $orderNum !== '' ? $repo->getOrderByNumber($orderNum) : null;

if (!$order) {
    setFlash('warning', 'Order confirmation not found. You can look up any order number on the Track Order page.');
    header('Location: track.php');
    exit;
}

// Handle Order Cancellation directly from confirmation page if eligible (Pending or Confirmed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!verifyCsrf($csrf)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: confirmation.php?order=' . urlencode($order['order_number']));
        exit;
    }

    if (in_array($order['status'], ['Pending', 'Confirmed'], true)) {
        $res = $repo->updateOrderStatus((int)$order['id'], 'Cancelled');
        if ($res['success']) {
            setFlash('success', "Order {$order['order_number']} has been successfully cancelled. Reserved inventory was restored to stock.");
        } else {
            setFlash('danger', $res['error'] ?? 'Failed to cancel order.');
        }
    } else {
        setFlash('danger', "Cancellation is not permitted for orders in \"{$order['status']}\" status.");
    }

    header('Location: confirmation.php?order=' . urlencode($order['order_number']));
    exit;
}

$canCancel = in_array($order['status'], ['Pending', 'Confirmed'], true);
$pageTitle = 'Order Confirmation — ' . $order['order_number'];
$activeNav = '';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<div class="card" style="max-width: 880px; margin: 28px auto; padding: 32px;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
        <div>
            <?php if ($order['status'] === 'Cancelled'): ?>
                <span class="status-pill status-Cancelled" style="margin-bottom: 10px; display: inline-block;">Order Cancelled (Stock Restored)</span>
                <h1 style="font-size: clamp(1.5rem, 2.5vw, 1.85rem); font-weight: 800;">Order Has Been Cancelled</h1>
                <p style="color: var(--text-secondary); font-size: 0.94rem; margin-top: 4px;">
                    This order has been cancelled and all item quantities have been restored to available stock.
                </p>
            <?php else: ?>
                <span class="stock-badge in-stock" style="margin-bottom: 10px; display: inline-block;">Order Successfully Saved</span>
                <h1 style="font-size: clamp(1.5rem, 2.5vw, 1.85rem); font-weight: 800;">Thank You for Your Order!</h1>
                <p style="color: var(--text-secondary); font-size: 0.94rem; margin-top: 4px;">
                    Your order has been recorded and product stock quantities have been reserved.
                </p>
            <?php endif; ?>
        </div>

        <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 16px;">
            <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
                <span>Unique Order Number</span>
                <button type="button" class="copy-badge-btn" data-copy="<?= e($order['order_number']) ?>" title="Copy Order Number">
                    &#128203; Copy
                </button>
            </div>
            <div class="mono-num" style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin: 2px 0 6px;">
                <?= e($order['order_number']) ?>
            </div>
            <span class="status-pill status-<?= e($order['status']) ?>">
                Status: <?= e($order['status']) ?>
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin: 22px 0; padding-bottom: 22px; border-bottom: 1px solid var(--border-color); font-size: 0.92rem;">
        <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px;">
            <h3 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); margin-bottom: 10px;">Customer Details</h3>
            <div style="margin-bottom: 4px;"><strong>Name:</strong> <?= e($order['customer_name']) ?></div>
            <div style="margin-bottom: 4px;"><strong>Email:</strong> <?= e($order['customer_email']) ?></div>
            <div style="margin-bottom: 4px;"><strong>Contact:</strong> <?= e($order['contact_number']) ?></div>
            <div><strong>Placed At:</strong> <?= e(formatDateTime($order['created_at'])) ?></div>
        </div>

        <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px;">
            <h3 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); margin-bottom: 10px;">Delivery &amp; Payment</h3>
            <div style="margin-bottom: 6px;"><strong>Payment Method:</strong> <?= e($order['payment_method']) ?></div>
            <div style="margin-bottom: 6px;"><strong>Delivery Address:</strong><br><?= nl2br(e($order['delivery_address'])) ?></div>
            <?php if (trim((string)$order['order_notes']) !== ''): ?>
                <div><strong>Order Notes:</strong> <?= nl2br(e($order['order_notes'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <h2 style="font-size: 1.08rem; font-weight: 800; margin-bottom: 14px;">Ordered Item Summary (Saved Snapshot)</h2>

    <div class="table-responsive" style="margin-bottom: 20px;">
        <table class="data-table mobile-stack-table">
            <thead>
                <tr>
                    <th>Item Name (Snapshot)</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): 
                    $liveP = $repo->getProductById((int)$item['product_id']);
                    $remStock = $liveP ? (int)$liveP['stock'] : null;
                ?>
                    <tr>
                        <td data-label="Item Name" style="font-weight: 700;">
                            <div><?= e($item['product_name_snapshot']) ?></div>
                            <?php if ($remStock !== null): ?>
                                <div style="font-size: 0.78rem; color: #0284c7; font-weight: 600; margin-top: 3px;">
                                    &bull; Real-time remaining stock: <?= $remStock ?> unit<?= $remStock === 1 ? '' : 's' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Unit Price" class="mono-num">
                            <?= e(formatPhp((int)$item['unit_price_cents_snapshot'])) ?>
                        </td>
                        <td data-label="Quantity" style="font-weight: 700;">
                            <?= (int)$item['quantity'] ?>
                        </td>
                        <td data-label="Line Total" class="mono-num" style="font-weight: 800;">
                            <?= e(formatPhp((int)$item['line_total_cents'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="background: var(--bg-subtle); border-radius: var(--radius-sm); border-left: 3px solid var(--primary); padding: 10px 14px; margin-bottom: 20px; font-size: 0.82rem; color: var(--text-secondary);">
        <strong>Snapshot Guarantee:</strong> Item names and unit prices are permanently snapshotted in this order record. Later product catalog changes or price adjustments will not alter past order receipts.
    </div>

    <div style="max-width: 360px; margin-left: auto;">
        <div class="summary-row">
            <span>Subtotal</span>
            <strong class="mono-num"><?= e(formatPhp((int)$order['subtotal_cents'])) ?></strong>
        </div>
        <div class="summary-row total">
            <span>Total</span>
            <span class="mono-num"><?= e(formatPhp((int)$order['total_cents'])) ?></span>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 26px; padding-top: 20px; border-top: 1px solid var(--border-color);">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="products.php" class="btn btn-primary">&larr; Continue Shopping</a>
            <?php if ($canCancel): ?>
                <form method="post" action="confirmation.php?order=<?= urlencode($order['order_number']) ?>" data-confirm="Are you sure you want to cancel this order? This will release reserved stock back to available inventory.">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="cancel_order">
                    <button type="submit" class="btn btn-danger btn-sm">
                        &#10006; Cancel Order
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="track.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-secondary">Track Order Status</a>
            <a href="admin/order.php?id=<?= (int)$order['id'] ?>" class="btn btn-secondary">Manage Order in Admin &rarr;</a>
        </div>
    </div>
            <a href="track.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-secondary">Track Order Status</a>
            <a href="admin/order.php?id=<?= (int)$order['id'] ?>" class="btn btn-secondary">Manage Order in Admin &rarr;</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
