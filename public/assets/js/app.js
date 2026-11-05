/**
 * CebuTech Hub - Enhanced Interactive Mobile & Desktop Script
 * Handles:
 *  1. Mobile Navigation Drawer & Backdrop
 *  2. Collapsible Mobile Search/Filter Bar
 *  3. Quantity Steppers (+ / -) with Live Price Calculation Preview
 *  4. Interactive Payment Method Selection Cards
 *  5. Custom Confirmation Modal for Deletions / Cancellations / Resets
 *  6. Dismissible Flash Alerts
 *  7. Submit Button Disabling While Saving
 */
document.addEventListener('DOMContentLoaded', function () {
    // ------------------------------------------------------------------------
    // 1. Interactive Mobile Navigation Drawer
    // ------------------------------------------------------------------------
    var menuToggleBtns = document.querySelectorAll('[data-mobile-menu-toggle]');
    var backdrop = document.querySelector('[data-mobile-backdrop]');

    function closeAllMenus() {
        menuToggleBtns.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
            var targetId = btn.getAttribute('aria-controls');
            var nav = targetId ? document.getElementById(targetId) : null;
            if (nav) nav.classList.remove('is-open');
        });
        if (backdrop) backdrop.classList.remove('is-open');
        document.body.classList.remove('menu-open');
    }

    menuToggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            var targetId = btn.getAttribute('aria-controls');
            var nav = targetId ? document.getElementById(targetId) : null;
            if (!nav) return;

            if (expanded) {
                closeAllMenus();
            } else {
                btn.setAttribute('aria-expanded', 'true');
                nav.classList.add('is-open');
                if (backdrop) backdrop.classList.add('is-open');
                document.body.classList.add('menu-open');
            }
        });
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeAllMenus);
    }

    // ------------------------------------------------------------------------
    // 1b. Admin Sidebar Toggle & Backdrop
    // ------------------------------------------------------------------------
    var adminSidebar         = document.getElementById('admin-sidebar');
    var adminSidebarBackdrop = document.querySelector('[data-admin-sidebar-backdrop]');
    var adminToggleBtns      = document.querySelectorAll('[data-admin-sidebar-toggle]');

    function closeAdminSidebar() {
        if (adminSidebar) adminSidebar.classList.remove('is-open');
        if (adminSidebarBackdrop) adminSidebarBackdrop.classList.remove('is-open');
        document.body.classList.remove('menu-open');
    }

    function toggleAdminSidebar() {
        if (!adminSidebar) return;
        var isOpen = adminSidebar.classList.toggle('is-open');
        if (adminSidebarBackdrop) {
            adminSidebarBackdrop.classList.toggle('is-open', isOpen);
        }
        document.body.classList.toggle('menu-open', isOpen);
    }

    adminToggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleAdminSidebar();
        });
    });

    if (adminSidebarBackdrop) {
        adminSidebarBackdrop.addEventListener('click', closeAdminSidebar);
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            closeAllMenus();
        }
    });

    // ------------------------------------------------------------------------
    // 2. Collapsible Mobile Filter Bar Toggle
    // ------------------------------------------------------------------------
    document.querySelectorAll('[data-filter-toggle]').forEach(function (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var filterBar = toggleBtn.closest('.filter-bar');
            if (!filterBar) return;
            var isExpanded = filterBar.classList.toggle('is-expanded');
            toggleBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            var labelSpan = toggleBtn.querySelector('[data-filter-toggle-label]');
            if (labelSpan) {
                labelSpan.textContent = isExpanded ? 'Hide Filters ▲' : 'Show Filters ▼';
            }
        });
    });

    // ------------------------------------------------------------------------
    // 3. Quantity Steppers (+ / -) & Live Cart Line-Total Preview
    // ------------------------------------------------------------------------
    function formatPhpFromCents(cents) {
        var whole = Math.floor(Math.abs(cents) / 100);
        var frac = Math.abs(cents) % 100;
        var wholeStr = whole.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var fracStr = frac < 10 ? '0' + frac : '' + frac;
        return 'PHP ' + wholeStr + '.' + fracStr;
    }

    function updateLiveCartPreview(input) {
        var row = input.closest('tr[data-unit-cents]');
        if (!row) return;
        var unitCents = parseInt(row.getAttribute('data-unit-cents'), 10) || 0;
        var qty = parseInt(input.value, 10) || 1;
        if (qty < 1) qty = 1;

        var lineCents = unitCents * qty;
        var lineTotalCell = row.querySelector('[data-line-total-display]');
        if (lineTotalCell) {
            lineTotalCell.textContent = formatPhpFromCents(lineCents);
        }

        // highlight update button so user sees they can save or auto-submit
        var updateBtn = row.querySelector('[data-update-qty-btn]');
        if (updateBtn) {
            updateBtn.classList.remove('btn-secondary');
            updateBtn.classList.add('btn-primary');
        }
    }

    document.querySelectorAll('[data-qty-step]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.qty-input-group');
            if (!group) return;
            var input = group.querySelector('input[type="number"]');
            if (!input) return;

            var step = parseInt(btn.getAttribute('data-qty-step'), 10) || 0;
            var current = parseInt(input.value, 10) || 1;
            var min = parseInt(input.getAttribute('min'), 10) || 1;
            var max = parseInt(input.getAttribute('max'), 10) || 9999;

            var next = current + step;
            if (next < min) next = min;
            if (next > max) next = max;
            input.value = next;

            // Micro-interaction pulse on number change
            input.classList.remove('has-pulsed');
            void input.offsetWidth;
            input.classList.add('has-pulsed');

            updateLiveCartPreview(input);
        });
    });

    document.querySelectorAll('tr[data-unit-cents] input[type="number"]').forEach(function (input) {
        input.addEventListener('input', function () {
            updateLiveCartPreview(input);
        });
    });

    // ------------------------------------------------------------------------
    // 4. Interactive Payment Method Selection Cards
    // ------------------------------------------------------------------------
    var paymentRadios = document.querySelectorAll('.payment-option-card input[type="radio"]');
    function syncPaymentCards() {
        paymentRadios.forEach(function (radio) {
            var card = radio.closest('.payment-option-card');
            if (!card) return;
            if (radio.checked) {
                card.classList.add('is-selected');
            } else {
                card.classList.remove('is-selected');
            }
        });
    }
    paymentRadios.forEach(function (radio) {
        radio.addEventListener('change', syncPaymentCards);
    });
    syncPaymentCards();

    // ------------------------------------------------------------------------
    // 5. Dismissible & Auto-Dismissing Flash Alerts with Timer
    // ------------------------------------------------------------------------
    function dismissAlertBox(alertBox) {
        if (!alertBox || alertBox.dataset.isDismissing) return;
        alertBox.dataset.isDismissing = 'true';
        alertBox.style.opacity = '0';
        alertBox.style.transform = 'translateY(-6px)';
        alertBox.style.transition = 'all 0.22s ease-out';
        setTimeout(function () {
            alertBox.remove();
        }, 230);
    }

    document.querySelectorAll('[data-alert-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            dismissAlertBox(btn.closest('.alert'));
        });
    });

    // Automatically dismiss flash alerts after 5 seconds with hover pause
    document.querySelectorAll('.alert').forEach(function (alertBox) {
        var timerBar = alertBox.querySelector('.alert-timer-bar');
        var remainingMs = 5000;
        var startMs = Date.now();
        var timerId = null;

        function startTimer() {
            startMs = Date.now();
            timerId = setTimeout(function () {
                dismissAlertBox(alertBox);
            }, remainingMs);
        }

        function pauseTimer() {
            if (timerId) {
                clearTimeout(timerId);
                timerId = null;
                var elapsed = Date.now() - startMs;
                remainingMs = Math.max(500, remainingMs - elapsed);
            }
        }

        alertBox.addEventListener('mouseenter', pauseTimer);
        alertBox.addEventListener('mouseleave', startTimer);
        startTimer();
    });

    // ------------------------------------------------------------------------
    // 6. Disable Submit Button While Saving with Animated Spinner (Section 9)
    // ------------------------------------------------------------------------
    document.querySelectorAll('form[data-disable-on-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                var savingText = submitBtn.getAttribute('data-saving-text') || 'Saving... Please wait';
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.dataset.originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="btn-spinner-icon" aria-hidden="true"></span> ' + savingText;
                } else {
                    submitBtn.value = savingText;
                }
            }
        });
    });

    // ------------------------------------------------------------------------
    // 7. Enhanced Confirmation Modal using Native HTML5 <dialog> (Section 12 & 13)
    // ------------------------------------------------------------------------
    var nativeDialog  = document.getElementById('native-confirm-dialog');
    var dialogMessage = document.getElementById('native-confirm-message');
    var dialogConfirm = document.getElementById('native-confirm-accept');
    var dialogCancel  = document.getElementById('native-confirm-cancel');
    var pendingForm   = null;

    function closeNativeDialog() {
        if (nativeDialog) {
            if (typeof nativeDialog.close === 'function' && nativeDialog.open) {
                nativeDialog.close();
            } else {
                nativeDialog.removeAttribute('open');
            }
        }
        pendingForm = null;
    }

    if (dialogCancel) {
        dialogCancel.addEventListener('click', function (e) {
            e.preventDefault();
            closeNativeDialog();
        });
    }

    if (nativeDialog) {
        // Native backdrop click handling
        nativeDialog.addEventListener('click', function (e) {
            var rect = nativeDialog.getBoundingClientRect();
            var isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height
                && rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
            if (!isInDialog) {
                closeNativeDialog();
            }
        });

        nativeDialog.addEventListener('cancel', function (e) {
            pendingForm = null;
        });
    }

    if (dialogConfirm) {
        dialogConfirm.addEventListener('click', function (e) {
            e.preventDefault();
            if (pendingForm) {
                var formToSubmit = pendingForm;
                pendingForm.removeAttribute('data-confirm');
                closeNativeDialog();
                formToSubmit.submit();
            }
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var message = form.getAttribute('data-confirm');
            if (!message) return;

            if (nativeDialog && dialogMessage) {
                e.preventDefault();
                pendingForm = form;
                dialogMessage.textContent = message;

                if (typeof nativeDialog.showModal === 'function') {
                    nativeDialog.showModal();
                } else {
                    nativeDialog.setAttribute('open', '');
                }

                if (dialogConfirm) {
                    dialogConfirm.focus();
                }
            } else {
                if (!window.confirm(message)) {
                    e.preventDefault();
                }
            }
        });
    });

    // ------------------------------------------------------------------------
    // 8. Universal Floating Toast Notification Manager (System-Wide Feedback)
    // ------------------------------------------------------------------------
    var toastContainer = document.getElementById('cebu-toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'cebu-toast-container';
        document.body.appendChild(toastContainer);
    }

    window.CebuToast = {
        show: function (type, title, message, durationMs) {
            durationMs = durationMs || 4000;
            type = type || 'info';

            var iconHtml = '&#9432;';
            if (type === 'success') iconHtml = '&#10003;';
            if (type === 'error' || type === 'danger') iconHtml = '&#9888;';
            if (type === 'warning') iconHtml = '&#9888;';

            var toast = document.createElement('div');
            toast.className = 'cebu-toast cebu-toast-' + type;
            toast.setAttribute('role', 'alert');

            toast.innerHTML = 
                '<div class="cebu-toast-icon">' + iconHtml + '</div>' +
                '<div class="cebu-toast-content">' +
                    '<div class="cebu-toast-title">' + (title || 'Notification') + '</div>' +
                    '<p class="cebu-toast-message">' + (message || '') + '</p>' +
                '</div>' +
                '<button type="button" class="cebu-toast-close" aria-label="Close">&times;</button>' +
                '<div class="cebu-toast-progress"></div>';

            toastContainer.appendChild(toast);

            var closeBtn = toast.querySelector('.cebu-toast-close');
            var dismissTimer = null;

            function dismissToast() {
                if (dismissTimer) clearTimeout(dismissTimer);
                toast.classList.add('toast-hiding');
                setTimeout(function () {
                    toast.remove();
                }, 260);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', dismissToast);
            }

            dismissTimer = setTimeout(dismissToast, durationMs);

            toast.addEventListener('mouseenter', function () {
                if (dismissTimer) clearTimeout(dismissTimer);
            });
            toast.addEventListener('mouseleave', function () {
                dismissTimer = setTimeout(dismissToast, 2000);
            });
        }
    };

    // ------------------------------------------------------------------------
    // 9. Instant Copy-to-Clipboard Micro-Feedback ([data-copy])
    // ------------------------------------------------------------------------
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var textToCopy = btn.getAttribute('data-copy');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(function () {
                btn.classList.add('is-copied');
                var originalHtml = btn.innerHTML;
                btn.innerHTML = '&#10003; Copied!';

                if (window.CebuToast) {
                    window.CebuToast.show('success', 'Copied to Clipboard', textToCopy, 2500);
                }

                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    btn.innerHTML = originalHtml;
                }, 1800);
            });
        });
    });

    // ------------------------------------------------------------------------
    // 10. Live Real-Time Clock Display (#admin-live-clock)
    // ------------------------------------------------------------------------
    var liveClockDisplay = document.getElementById('live-clock-display');
    if (liveClockDisplay) {
        function updateLiveClock() {
            var now = new Date();
            var options = {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            liveClockDisplay.textContent = now.toLocaleString('en-US', options);
        }
        setInterval(updateLiveClock, 1000);
    }
});
