<?php
declare(strict_types=1);
$currentAdminNav = $adminNav ?? 'overview';
?>
            </main>

            <footer class="admin-footer">
                <div class="admin-footer-inner">
                    <span>&copy; <?= date('Y') ?> <?= e(STORE_NAME) ?> &bull; Admin Management Portal</span>
                    <span style="font-size: 0.78rem; color: var(--text-secondary);">CubeTech Innovations Internship Assessment</span>
                </div>
            </footer>
        </div><!-- /.admin-main-wrap -->
    </div><!-- /.admin-layout -->

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

    <script src="../assets/js/app.js"></script>
</body>
</html>
