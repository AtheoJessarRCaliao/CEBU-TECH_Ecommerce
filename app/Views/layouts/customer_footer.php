<?php
declare(strict_types=1);
$footerCategories = Repository::getInstance()->getCategories();
$mobileCartCount  = Cart::getTotalUnitCount();
$currentNav       = $activeNav ?? '';
?>
    </main>

    <!-- Interactive Mobile Bottom App Bar (Visible on Mobile <= 600px) -->
    <nav class="mobile-bottom-nav" aria-label="Mobile Quick Navigation">
        <div class="mobile-bottom-nav-inner">
            <a href="index.php" class="mobile-tab-item <?= $currentNav === 'home' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1V9.5z"/></svg>
                <span>Home</span>
            </a>

            <a href="products.php" class="mobile-tab-item <?= $currentNav === 'products' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                <span>Shop</span>
            </a>

            <a href="cart.php" class="mobile-tab-item <?= $currentNav === 'cart' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <?php if ($mobileCartCount > 0): ?>
                    <span class="mobile-tab-badge"><?= (int)$mobileCartCount ?></span>
                <?php endif; ?>
                <span>Cart</span>
            </a>

            <a href="track.php" class="mobile-tab-item <?= $currentNav === 'track' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span>Track</span>
            </a>

            <a href="admin/index.php" class="mobile-tab-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                <span>Admin</span>
            </a>
        </div>
    </nav>

    <!-- Native HTML5 Dialog Confirmation Modal -->
    <dialog id="native-confirm-dialog" class="native-modal">
        <form method="dialog" class="native-modal-dialog">
            <div class="native-modal-header">
                <div class="native-modal-icon">&#9888;</div>
                <h3 id="native-confirm-title" class="modal-title">Please Confirm Action</h3>
            </div>
            <p id="native-confirm-message" class="modal-body">Are you sure you want to proceed?</p>
            <div class="modal-actions">
                <button type="button" id="native-confirm-cancel" class="btn btn-secondary btn-sm" value="cancel">Cancel</button>
                <button type="button" id="native-confirm-accept" class="btn btn-danger btn-sm" value="confirm">Confirm</button>
            </div>
        </form>
    </dialog>

    <footer class="site-footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col footer-col-brand">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <img src="assets/images/Cube-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="brand-logo-img" style="width: 34px; height: 34px;">
                        <div>
                            <h4 style="margin: 0; font-size: 1.15rem; color: #f8fafc; font-weight: 800;"><?= e(STORE_NAME) ?></h4>
                            <span style="font-size: 0.72rem; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">CubeTech Innovations</span>
                        </div>
                    </div>
                    <p style="margin: 0 0 14px; line-height: 1.55; font-size: 0.85rem; color: #94a3b8; max-width: 320px;">
                        <?= e(STORE_TAGLINE) ?>. Precision-engineered mechanical keyboards, ergonomic mice, studio audio, and creator accessories.
                    </p>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <span class="social-pill-link">&#10003; 100% PHP PDO</span>
                        <span class="social-pill-link">&#10003; Real-Time Tracking</span>
                    </div>
                </div>

                <div class="footer-col">
                    <h4>Categories</h4>
                    <ul class="footer-links">
                        <?php foreach ($footerCategories as $fCat): ?>
                            <li><a href="products.php?category_id=<?= (int)$fCat['id'] ?>"><?= e($fCat['name']) ?></a></li>
                        <?php endforeach; ?>
                        <li><a href="products.php" style="color: #38bdf8;">All Products &rarr;</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home Storefront</a></li>
                        <li><a href="products.php">Shop Products</a></li>
                        <li><a href="cart.php">Shopping Cart</a></li>
                        <li><a href="track.php">Track Order Status</a></li>
                        <li><a href="admin/index.php">Admin Console</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Customer Care</h4>
                    <ul class="footer-links">
                        <li><strong>Email:</strong> support@cubetechhub.demo</li>
                        <li><strong>Location:</strong> Cebu IT Park, Cebu City, PH</li>
                        <li><strong>Hours:</strong> Mon &ndash; Sat, 8:00 AM &ndash; 7:00 PM</li>
                        <li style="margin-top: 4px; font-size: 0.8rem; color: #64748b;">
                            Payments: COD, E-Wallet, Bank Transfer
                        </li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-bottom-left">
                    &copy; <?= date('Y') ?> <?= e(STORE_NAME) ?> &bull; CubeTech Innovations. All rights reserved.
                </div>
                <div class="footer-bottom-right">
                    <span>Precision Developer Gear</span> &bull; 
                    <a href="track.php" style="color: #38bdf8; text-decoration: none;">Live Order Tracking &nearr;</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
