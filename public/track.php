<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Repository.php';

$repo = Repository::getInstance();
$queryNum = trim((string)($_GET['order'] ?? ''));
$order    = $queryNum !== '' ? $repo->getOrderByNumber($queryNum) : null;

$pageTitle = 'Track Order Status';
$activeNav = 'track';
require_once __DIR__ . '/../app/Views/layouts/customer_header.php';
?>

<div class="section-header" style="margin-top: 22px;">
    <div>
        <h1 class="section-title">Track Your Order</h1>
        <p class="section-subtitle">
            Enter your Order Number (e.g., <code>ORD-20261101-1001</code>) to check its live fulfillment status.
        </p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="track.php?order=ORD-20261101-1001" class="btn btn-secondary btn-sm">Sample #1001</a>
        <a href="track.php?order=ORD-20261103-1002" class="btn btn-secondary btn-sm">Sample #1002</a>
        <a href="track.php?order=ORD-20261104-1003" class="btn btn-secondary btn-sm">Sample #1003</a>
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
    </form>
</section>

<?php if ($queryNum !== '' && !$order): ?>
    <div class="empty-state">
        <div class="empty-state-icon">&#128269;</div>
        <h3>Order Not Found</h3>
        <p>No saved order matches <strong>"<?= e($queryNum) ?>"</strong>. Please double-check your order number.</p>
    </div>
<?php elseif ($order): ?>
    <div class="card" style="margin-bottom: 36px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
            <div>
                <h2 class="mono-num" style="font-size: 1.3rem; font-weight: 800;">Order <?= e($order['order_number']) ?></h2>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Placed on <?= e($order['created_at']) ?> &bull; Updated <?= e($order['updated_at']) ?>
                </div>
            </div>
            <span class="status-pill status-<?= e($order['status']) ?>" style="font-size: 0.9rem; padding: 6px 14px;">
                Current Status: <?= e($order['status']) ?>
            </span>
        </div>

        <!-- Status Pipeline Indicator -->
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
                <span class="status-pill status-Cancelled">Cancelled (Stock Restored)</span>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 20px; font-size: 0.9rem;">
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px;">
                <strong>Customer:</strong> <?= e($order['customer_name']) ?><br>
                <strong>Email:</strong> <?= e($order['customer_email']) ?><br>
                <strong>Contact:</strong> <?= e($order['contact_number']) ?>
            </div>
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px;">
                <strong>Payment Method:</strong> <?= e($order['payment_method']) ?><br>
                <strong>Delivery Address:</strong> <?= e($order['delivery_address']) ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>Item (Saved Snapshot)</th>
                        <th>Unit Price</th>
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

        <div style="display: flex; justify-content: flex-end; margin-top: 16px; font-size: 1.2rem; font-weight: 800;" class="mono-num">
            Total: <?= e(formatPhp((int)$order['total_cents'])) ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../app/Views/layouts/customer_footer.php'; ?>
