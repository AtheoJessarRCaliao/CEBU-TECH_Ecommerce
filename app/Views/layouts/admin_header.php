<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Repository.php';

Auth::requireAdmin();

$pageTitle    = isset($pageTitle) ? $pageTitle . ' | Admin — ' . STORE_NAME : 'Admin Dashboard | ' . STORE_NAME;
$adminNav     = $adminNav ?? 'overview';
$flashes      = getFlashes();
$notifUnread  = Repository::getInstance()->getUnreadNotificationCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="../assets/images/Cebu-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/Cebu-Logo.png">
    <link rel="apple-touch-icon" href="../assets/images/Cebu-Logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <!-- Admin Desktop & Mobile Sidebar -->
        <aside id="admin-sidebar" class="admin-sidebar" aria-label="Admin Navigation Sidebar">
            <div class="admin-sidebar-header">
                <a href="index.php" class="admin-brand">
                    <img src="../assets/images/Cebu-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="admin-brand-logo">
                    <div class="admin-brand-text">
                        <span class="admin-brand-title"><?= e(STORE_NAME) ?></span>
                        <span class="admin-brand-badge">Admin Panel</span>
                    </div>
                </a>
                <button type="button" class="admin-sidebar-close" data-admin-sidebar-toggle="true" aria-label="Close Sidebar">&times;</button>
            </div>

            <div class="admin-sidebar-user">
                <div class="admin-user-avatar">
                    <?= strtoupper(substr(e(Auth::getAdminUser()), 0, 1)) ?>
                </div>
                <div class="admin-user-info">
                    <span class="admin-user-role">Demo Administrator</span>
                    <span class="admin-user-email" title="<?= e(Auth::getAdminUser()) ?>"><?= e(Auth::getAdminUser()) ?></span>
                </div>
            </div>

            <nav class="admin-sidebar-nav">
                <div class="admin-nav-section-label">Main Menu</div>
                <a href="index.php" class="admin-nav-item <?= $adminNav === 'overview' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                    <span>Overview</span>
                </a>
                <a href="products.php" class="admin-nav-item <?= $adminNav === 'products' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <span>Products</span>
                </a>
                <a href="categories.php" class="admin-nav-item <?= $adminNav === 'categories' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <span>Categories</span>
                </a>
                <a href="orders.php" class="admin-nav-item <?= $adminNav === 'orders' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Orders</span>
                </a>
                <a href="customers.php" class="admin-nav-item <?= $adminNav === 'customers' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Customers</span>
                </a>
                <a href="notifications.php" class="admin-nav-item <?= $adminNav === 'notifications' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span>Alerts Feed</span>
                    <?php if ($notifUnread > 0): ?>
                        <span class="notif-badge-pill"><?= $notifUnread ?></span>
                    <?php endif; ?>
                </a>

                <div class="admin-nav-section-label" style="margin-top: 18px;">Quick Links</div>
                <a href="../index.php" class="admin-nav-item" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    <span>Storefront &nearr;</span>
                </a>
                <a href="logout.php" class="admin-nav-item text-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span>Log Out</span>
                </a>
            </nav>
        </aside>

        <div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop="true"></div>

        <!-- Admin Main Content Area -->
        <div class="admin-main-wrap">
            <!-- Admin Top Bar (Mobile App & Web Responsive) -->
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button type="button" class="admin-hamburger" data-admin-sidebar-toggle="true" aria-label="Toggle Navigation Sidebar">
                        <span class="hamburger-icon">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </button>
                    <div class="admin-topbar-title">
                        <img src="../assets/images/Cebu-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="admin-topbar-mobile-logo">
                        <span class="admin-topbar-prefix">Admin Console</span>
                        <span class="admin-topbar-sep">&rsaquo;</span>
                        <strong class="admin-topbar-current"><?= ucfirst($adminNav) ?></strong>
                    </div>
                </div>

                <div class="admin-topbar-right">
                    <a href="notifications.php" class="admin-topbar-btn <?= $notifUnread > 0 ? 'has-badge' : '' ?>" title="View Activity Alerts" aria-label="Activity Alerts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($notifUnread > 0): ?>
                            <span class="topbar-badge"><?= $notifUnread ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="../index.php" class="admin-topbar-btn admin-topbar-btn-labeled" target="_blank" rel="noopener" title="Customer Storefront" aria-label="Customer Storefront">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="17" height="17"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        <span class="topbar-btn-text">Storefront &nearr;</span>
                    </a>
                    <a href="logout.php" class="admin-topbar-btn admin-topbar-btn-labeled admin-topbar-logout" title="Log Out" aria-label="Log Out">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="17" height="17"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span class="topbar-btn-text">Log Out</span>
                    </a>
                </div>
            </header>

            <div class="demo-banner admin-mode" style="margin: 0; border-radius: 0;">
                <div class="demo-banner-inner">
                    <span>
                        <strong>SIMULATED ADMIN DEMO:</strong> Signed in as <code><?= e(Auth::getAdminUser()) ?></code>. Simulated session login is for assessment evaluation only.
                    </span>
                    <a href="../index.php" class="demo-banner-link">&larr; Return to Store</a>
                </div>
            </div>

            <main class="admin-content-container">
                <?php foreach ($flashes as $flash): ?>
                    <?php
                    $fType = e($flash['type']);
                    $fIcon = '&#9432;';
                    $fTitle = 'Notice';
                    if ($fType === 'success') {
                        $fIcon = '&#10003;';
                        $fTitle = 'Success';
                    } elseif ($fType === 'warning') {
                        $fIcon = '&#9888;';
                        $fTitle = 'Attention';
                    } elseif ($fType === 'error' || $fType === 'danger') {
                        $fIcon = '&#9888;';
                        $fTitle = 'Action Required';
                    }
                    ?>
                    <div class="alert alert-<?= $fType ?>" role="alert">
                        <div class="alert-icon-pill"><?= $fIcon ?></div>
                        <div class="alert-content">
                            <strong class="alert-title-text"><?= $fTitle ?>:</strong>
                            <span><?= e($flash['message']) ?></span>
                        </div>
                        <button type="button" class="alert-close" data-alert-close="true" aria-label="Dismiss alert">&times;</button>
                        <div class="alert-timer-bar"></div>
                    </div>
                <?php endforeach; ?>
