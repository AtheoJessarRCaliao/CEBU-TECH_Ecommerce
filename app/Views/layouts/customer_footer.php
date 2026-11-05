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
                <div class="footer-col">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <img src="assets/images/Cebu-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="brand-logo-img" style="width: 32px; height: 32px;">
                        <h4 style="margin-bottom: 0; font-size: 1.05rem;"><?= e(STORE_NAME) ?></h4>
                    </div>
                    <p style="margin: 0; line-height: 1.5; font-size: 0.85rem; color: #94a3b8; max-width: 340px;">
                        <?= e(STORE_TAGLINE) ?> &bull; Cebu City, Philippines.
                    </p>
                </div>

                <div class="footer-col">
                    <h4>Explore</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Shop Products</a></li>
                        <li><a href="cart.php">Shopping Cart</a></li>
                        <li><a href="track.php">Track Order</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Customer Care</h4>
                    <ul class="footer-links">
                        <li><strong>Email:</strong> support@cebutechhub.demo</li>
                        <li><strong>Location:</strong> Cebu IT Park, Cebu City</li>
                        <li><a href="admin/index.php" style="color: #38bdf8;">Admin Console &rarr;</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> <?= e(STORE_NAME) ?>. All rights reserved.</span>
                
            </div>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
