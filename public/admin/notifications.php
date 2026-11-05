<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';

Auth::requireAdmin();
$repo = Repository::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (verifyCsrf($_POST['csrf_token'] ?? null)) {
        $repo->markAllNotificationsAsRead();
        setFlash('success', 'All notifications marked as read.');
    }
    header('Location: notifications.php');
    exit;
}

$notifications = $repo->getNotifications(50);
$unreadCount   = $repo->getUnreadNotificationCount();

$pageTitle = 'Activity Notifications & Monitoring';
$adminNav  = 'notifications';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Activity Notifications &amp; Monitoring</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Real-time feed tracking product inventory alerts (low/zero stock, updates) and customer order lifecycle changes.
        </p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <?php if ($unreadCount > 0): ?>
            <form action="notifications.php" method="post" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-secondary btn-sm">&#10003; Mark All as Read</button>
            </form>
        <?php endif; ?>
        <a href="notifications.php" class="btn btn-secondary btn-sm">&circlearrowright; Refresh</a>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 16px 20px; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <span style="font-weight: 700; font-size: 0.95rem;">
            Activity Log Feed (<?= count($notifications) ?> total, <?= $unreadCount ?> unread)
        </span>
        <span class="stock-badge <?= $unreadCount > 0 ? 'out-of-stock' : 'in-stock' ?>">
            <?= $unreadCount > 0 ? $unreadCount . ' Unread Alerts' : 'All Caught Up' ?>
        </span>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state" style="border: none; margin: 0; box-shadow: none;">
            <h3>No Activity Notifications Yet</h3>
            <p>New orders, order status changes, and product updates or low-stock alerts will be logged here automatically.</p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $n): ?>
                <?php
                $isOrder = ($n['type'] === 'order');
                $isUnread = ((int)$n['is_read'] === 0);
                ?>
                <div class="notif-item <?= $isUnread ? 'is-unread' : '' ?>">
                    <div class="notif-icon-wrap <?= $isOrder ? 'icon-order' : 'icon-product' ?>">
                        <?= $isOrder ? '&#128230;' : '&#9888;' ?>
                    </div>
                    <div class="notif-content">
                        <div class="notif-header">
                            <span class="notif-title"><?= e($n['title']) ?></span>
                            <span class="notif-time mono-num"><?= e(formatDateTime($n['created_at'])) ?></span>
                        </div>
                        <p class="notif-message"><?= e($n['message']) ?></p>
                        <?php if (!empty($n['link'])): ?>
                            <a href="<?= e($n['link']) ?>" class="notif-link">View Details &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($isUnread): ?>
                        <span class="notif-dot" title="Unread notification"></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>

