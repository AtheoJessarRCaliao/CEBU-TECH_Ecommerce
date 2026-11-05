<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Core/Cart.php';
require_once __DIR__ . '/../../Core/Auth.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . STORE_NAME : STORE_NAME . ' — ' . STORE_TAGLINE;
$activeNav = $activeNav ?? '';
$cartCount = Cart::getTotalUnitCount();
$flashes   = getFlashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="assets/images/Cube-Logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/Cube-Logo.png">
    <link rel="apple-touch-icon" href="assets/images/Cube-Logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="has-bottom-nav">
    <div class="demo-banner">
        <div class="demo-banner-inner">
            <span>
                <strong>FICTIONAL DEMO STORE:</strong> <?= e(STORE_NAME) ?> is an applicant assessment project for CubeTech Innovations.
            </span>
            <a href="admin/index.php" class="demo-banner-link">Open Admin Console &rarr;</a>
        </div>
    </div>

    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="brand-logo">
                <img src="assets/images/Cube-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="brand-logo-img">
                <span class="brand-text-wrap">
                    <span><?= e(STORE_NAME) ?></span>
                    <span class="brand-sub">CubeTech Gear</span>
                </span>
            </a>

            <nav id="customer-main-nav" class="nav-links" aria-label="Main Navigation">
                <a href="index.php" class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
                <a href="products.php" class="nav-link <?= $activeNav === 'products' && empty($_GET['category_id']) ? 'active' : '' ?>">All Products</a>
                <a href="products.php?category_id=1" class="nav-link <?= ($_GET['category_id'] ?? '') === '1' ? 'active' : '' ?>">Keyboards &amp; Mice</a>
                <a href="products.php?category_id=2" class="nav-link <?= ($_GET['category_id'] ?? '') === '2' ? 'active' : '' ?>">Audio Gear</a>
                <a href="products.php?category_id=3" class="nav-link <?= ($_GET['category_id'] ?? '') === '3' ? 'active' : '' ?>">Accessories</a>
                <a href="track.php" class="nav-link <?= $activeNav === 'track' ? 'active' : '' ?>">Track Order</a>
            </nav>

            <div class="header-right-actions">
                <a href="cart.php" class="cart-btn" aria-label="Shopping Cart">
                    <span>Cart</span>
                    <span class="cart-badge"><?= (int)$cartCount ?></span>
                </a>

                <button
                    type="button"
                    class="mobile-menu-toggle"
                    data-mobile-menu-toggle="true"
                    aria-controls="customer-main-nav"
                    aria-expanded="false"
                    aria-label="Toggle Navigation Menu"
                >
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>
        </div>
    </header>

    <div class="mobile-nav-backdrop" data-mobile-backdrop="true"></div>

    <main class="container">
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
