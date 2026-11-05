<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';

Auth::requireAdmin();
$repo = Repository::getInstance();

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$search       = trim((string)($_GET['search'] ?? ''));

if ($statusFilter !== 'all' && !in_array($statusFilter, ORDER_STATUSES, true)) {
    $statusFilter = 'all';
}

$orders = $repo->getOrders($statusFilter, $search);

$pageTitle = 'Order Management';
$adminNav  = 'orders';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Order Management</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Listed newest first. Click <strong>View Details</strong> on any order to inspect snapshot line items and update fulfillment status.
        </p>
    </div>
    <a href="orders.php" class="btn btn-secondary btn-sm">&circlearrowright; Manual Refresh</a>
</div>

<section class="filter-bar">
    <form action="orders.php" method="get" class="filter-form" style="grid-template-columns: 1.5fr 1fr auto;">
        <div class="form-group">
            <label for="ord-search" class="form-label">Search Order # or Customer</label>
            <input
                type="search"
                id="ord-search"
                name="search"
                class="form-control"
                placeholder="e.g. ORD-20261101-1001 or Maria..."
                value="<?= e($search) ?>"
            >
        </div>

        <div class="form-group">
            <label for="ord-status" class="form-label">Filter by Order Status</label>
            <select id="ord-status" name="status" class="form-control">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <?php foreach (ORDER_STATUSES as $st): ?>
                    <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                        <?= e($st) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="display: flex; flex-direction: row; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="height: 39px;">Filter</button>
            <?php if ($statusFilter !== 'all' || $search !== ''): ?>
                <a href="orders.php" class="btn btn-secondary" style="height: 39px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<div class="card" style="padding: 0; overflow: hidden;">
    <?php if (empty($orders)): ?>
        <div class="empty-state" style="border: none; margin: 0;">
            <h3>No Orders Found</h3>
            <p>
                <?php if ($statusFilter !== 'all' || $search !== ''): ?>
                    No saved orders match your current filter or search criteria.
                <?php else: ?>
                    No customer orders have been placed yet. Place an order on the customer storefront to see it here!
                <?php endif; ?>
            </p>
            <?php if ($statusFilter !== 'all' || $search !== ''): ?>
                <a href="orders.php" class="btn btn-secondary btn-sm">Clear Filter</a>
            <?php else: ?>
                <a href="../products.php" class="btn btn-primary btn-sm">Go to Customer Storefront</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Customer Name</th>
                        <th>Date / Time</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord): ?>
                        <tr>
                            <td data-label="Order Number" style="font-weight: 800;">
                                <a href="order.php?id=<?= (int)$ord['id'] ?>"><?= e($ord['order_number']) ?></a>
                            </td>
                            <td data-label="Customer Name">
                                <div style="font-weight: 600;"><?= e($ord['customer_name']) ?></div>
                                <div style="font-size: 0.78rem; color: var(--text-secondary);"><?= e($ord['customer_email']) ?></div>
                            </td>
                            <td data-label="Date / Time" style="font-size: 0.85rem; color: var(--text-secondary);">
                                <?= e(formatDateTime($ord['created_at'])) ?>
                            </td>
                            <td data-label="Total" style="font-weight: 700;">
                                <?= e(formatPhp((int)$ord['total_cents'])) ?>
                            </td>
                            <td data-label="Payment Method">
                                <?= e($ord['payment_method']) ?>
                            </td>
                            <td data-label="Status">
                                <span class="status-pill status-<?= e($ord['status']) ?>">
                                    <?= e($ord['status']) ?>
                                </span>
                            </td>
                            <td data-label="Action">
                                <a href="order.php?id=<?= (int)$ord['id'] ?>" class="btn btn-primary btn-sm">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>

