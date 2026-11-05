<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';

Auth::requireAdmin();
$repo = Repository::getInstance();
$customers = $repo->getDerivedCustomers();

$pageTitle = 'Customer Summary';
$adminNav  = 'customers';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Customer Summary (Derived from Orders)</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Read-only customer list grouped by normalized email (<code>LOWER(TRIM(email))</code>). Repeat emails display the latest submitted name and contact number.
        </p>
    </div>
    <span class="stock-badge in-stock" style="font-size: 0.85rem; padding: 6px 12px;">
        Total Unique Customers: <?= count($customers) ?>
    </span>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <?php if (empty($customers)): ?>
        <div class="empty-state" style="border: none; margin: 0;">
            <h3>No Customers Yet</h3>
            <p>Customer records are automatically derived when guest orders are placed at checkout.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>Latest Customer Name</th>
                        <th>Normalized Email</th>
                        <th>Latest Contact Number</th>
                        <th>Order Count</th>
                        <th>Total Value of Completed Orders</th>
                        <th>Latest Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td data-label="Customer Name" style="font-weight: 700;">
                                <?= e($cust['customer_name']) ?>
                            </td>
                            <td data-label="Normalized Email">
                                <code><?= e($cust['normalized_email']) ?></code>
                            </td>
                            <td data-label="Contact Number">
                                <?= e($cust['contact_number']) ?>
                            </td>
                            <td data-label="Order Count" style="font-weight: 700;">
                                <a href="orders.php?search=<?= urlencode($cust['normalized_email']) ?>">
                                    <?= (int)$cust['order_count'] ?> order<?= (int)$cust['order_count'] === 1 ? '' : 's' ?>
                                </a>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">
                                    (<?= (int)$cust['completed_order_count'] ?> completed)
                                </span>
                            </td>
                            <td data-label="Completed Orders Value" style="font-weight: 800; color: var(--success-text);">
                                <?= e(formatPhp((int)$cust['completed_sales_cents'])) ?>
                            </td>
                            <td data-label="Latest Order" style="font-size: 0.84rem; color: var(--text-secondary);">
                                <?= e($cust['latest_order_number']) ?><br>
                                <span style="font-size: 0.76rem;"><?= e($cust['latest_order_date']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>

