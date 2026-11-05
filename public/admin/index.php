<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';
require_once __DIR__ . '/../../app/Core/Cart.php';

Auth::requireAdmin();
$repo = Repository::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_demo_data') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $repo->resetDatabase();
        Cart::clear();
        setFlash('success', 'Demo database has been reset and re-seeded to its initial assessment state.');
    }
    header('Location: index.php');
    exit;
}

$metrics      = $repo->getDashboardMetrics();
$recentOrders = array_slice($repo->getOrders(), 0, 6);

$pageTitle = 'Overview';
$adminNav  = 'overview';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Admin Dashboard Overview</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Real-time metrics synchronized with the customer storefront.
        </p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="products.php?action=new" class="btn btn-primary btn-sm">&plus; Add New Product</a>
        <form action="index.php" method="post" data-confirm="Reset all products, categories, and orders back to the initial seeded state?" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="reset_demo_data">
            <button type="submit" class="btn btn-secondary btn-sm">Reset / Re-seed Demo Data</button>
        </form>
    </div>
</div>

<!-- 5 Required Summary Cards (Section 11) - Total Sales Priority Highlight -->
<section class="metrics-grid has-priority-kpi" aria-label="Dashboard Summary Metrics">
    <!-- Priority Highlight: Total Sales (Positioned First & Featured) -->
    <div class="metric-card metric-card-priority">
        <div class="priority-kpi-badge">&#9733; Priority KPI</div>
        <div class="metric-label" style="color: #93c5fd;">Total Sales (Completed Orders Only)</div>
        <div class="metric-value priority-sales-val">
            <?= e(formatPhp((int)$metrics['total_sales_cents'])) ?>
        </div>
        <div class="metric-sub priority-sales-sub">
            Authoritative revenue calculated from <?= (int)$metrics['completed_orders'] ?> Completed order(s). Excludes Pending, Confirmed, Preparing, Shipped, and Cancelled.
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Total Products</div>
        <div class="metric-value"><?= (int)$metrics['total_products'] ?></div>
        <div class="metric-sub">
            All records: <?= (int)$metrics['active_products'] ?> active (<?= (int)$metrics['out_of_stock_products'] ?> zero-stock), <?= (int)$metrics['inactive_products'] ?> inactive
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Total Orders</div>
        <div class="metric-value"><?= (int)$metrics['total_orders'] ?></div>
        <div class="metric-sub">
            All saved orders, including <?= (int)$metrics['cancelled_orders'] ?> cancelled
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Pending / Completed Orders</div>
        <div class="metric-value">
            <span style="color: #b45309;"><?= (int)$metrics['pending_orders'] ?></span>
            <span style="color: #94a3b8; font-weight: 400;">/</span>
            <span style="color: var(--success-text);"><?= (int)$metrics['completed_orders'] ?></span>
        </div>
        <div class="metric-sub">
            <?= (int)$metrics['pending_orders'] ?> Pending &bull; <?= (int)$metrics['completed_orders'] ?> Completed
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Total Customers</div>
        <div class="metric-value"><?= (int)$metrics['total_customers'] ?></div>
        <div class="metric-sub">
            Unique customers from normalized checkout emails
        </div>
    </div>
</section>

<!-- Quick Action Links & Recent Orders -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
        <h2 style="font-size: 1.1rem; font-weight: 700;">Recent Orders (Newest First)</h2>
        <a href="orders.php" class="btn btn-secondary btn-sm">View All Orders &rarr;</a>
    </div>

    <?php if (empty($recentOrders)): ?>
        <div class="empty-state" style="margin: 10px 0;">
            <h3>No Orders Placed Yet</h3>
            <p>Orders submitted on the customer website will appear here automatically.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Date / Time</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $ord): ?>
                        <tr>
                            <td data-label="Order #" style="font-weight: 700;">
                                <a href="order.php?id=<?= (int)$ord['id'] ?>"><?= e($ord['order_number']) ?></a>
                            </td>
                            <td data-label="Customer">
                                <div style="font-weight: 600;"><?= e($ord['customer_name']) ?></div>
                                <div style="font-size: 0.78rem; color: var(--text-secondary);"><?= e($ord['customer_email']) ?></div>
                            </td>
                            <td data-label="Date / Time" style="font-size: 0.84rem; color: var(--text-secondary);">
                                <?= e($ord['created_at']) ?>
                            </td>
                            <td data-label="Total" style="font-weight: 700;">
                                <?= e(formatPhp((int)$ord['total_cents'])) ?>
                            </td>
                            <td data-label="Payment"><?= e($ord['payment_method']) ?></td>
                            <td data-label="Status">
                                <span class="status-pill status-<?= e($ord['status']) ?>"><?= e($ord['status']) ?></span>
                            </td>
                            <td data-label="Action">
                                <a href="order.php?id=<?= (int)$ord['id'] ?>" class="btn btn-secondary btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>

