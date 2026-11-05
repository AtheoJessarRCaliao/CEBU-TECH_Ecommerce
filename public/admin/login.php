<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$usernameInput = DEMO_ADMIN_USERNAME;
$hasSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasSubmitted = true;
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $usernameInput = trim((string)($_POST['username'] ?? ''));
        $passwordInput = (string)($_POST['password'] ?? '');

        if (Auth::attemptLogin($usernameInput, $passwordInput)) {
            setFlash('success', 'Welcome to the ' . STORE_NAME . ' Admin Dashboard (Simulated Demo Session).');
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid demo credentials. Please use the demo credentials shown below.';
        }
    }
}

$flashes = getFlashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Admin Portal Sign In | <?= e(STORE_NAME) ?></title>
    <link rel="icon" type="image/png" href="../assets/images/Cube-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/Cube-Logo.png">
    <link rel="apple-touch-icon" href="../assets/images/Cube-Logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page-body" data-has-submitted="<?= $hasSubmitted ? 'true' : 'false' ?>">

    <!-- ===================================================================== -->
    <!-- 1. Splash Activity Screen (Branded Mobile App & Web Startup Animation) -->
    <!-- ===================================================================== -->
    <div id="splash-activity" class="splash-activity" role="dialog" aria-modal="true" aria-label="Starting CubeTech Hub">
        <button type="button" id="splash-skip-btn" class="splash-skip-btn" aria-label="Skip splash screen">
            Skip &#8250;
        </button>

        <div class="splash-center">
            <div class="splash-logo-wrap">
                <div class="splash-pulse-ring"></div>
                <img src="../assets/images/Cube-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="splash-logo-img">
            </div>

            <h1 class="splash-brand-title"><?= e(STORE_NAME) ?></h1>
            <div class="splash-badge">
                <span class="splash-dot"></span> Admin Management Portal
            </div>

            <div class="splash-loader-wrap">
                <div class="splash-progress-track">
                    <div id="splash-progress-bar" class="splash-progress-bar"></div>
                </div>
                <p id="splash-status-text" class="splash-status">Initializing portal session...</p>
            </div>
        </div>

        <div class="splash-app-tagline">
            Powered by CubeTech Innovations &bull; Enterprise Core
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 2. Mobile App Bar Header (Native Mobile Experience on <= 600px)       -->
    <!-- ===================================================================== -->
    <header class="login-mobile-appbar">
        <a href="../index.php" class="login-mobile-back-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Storefront</span>
        </a>
        <span class="login-mobile-title"><?= e(STORE_NAME) ?> Admin</span>
        <span class="login-mobile-status-dot" title="Demo system active"></span>
    </header>

    <!-- Simulated Auth Notice Banner (Section 1 Demo Requirement) -->
    <div class="login-sim-notice">
        <strong>SIMULATED LOGIN NOTICE:</strong> This portal uses demo-only credentials for the CubeTech Innovations internship assessment.
    </div>

    <!-- ===================================================================== -->
    <!-- 3. Main Login Viewport (Web Centered Card & Responsive Mobile Sheet)  -->
    <!-- ===================================================================== -->
    <main class="login-main-viewport">
        <div class="login-auth-card <?= $error !== '' ? 'has-error' : '' ?>">
            <div class="login-brand-header">
                <div class="login-brand-logo-wrap">
                    <img src="../assets/images/Cube-Logo.png" alt="<?= e(STORE_NAME) ?> Logo" class="login-brand-logo-img">
                </div>
                <h2 class="login-title">Admin Management</h2>
                <p class="login-subtitle">
                    Enter your authorized demo credentials to manage catalog, orders, and sales analytics.
                </p>
            </div>

            <!-- Flash & Error Alerts -->
            <?php foreach ($flashes as $flash): ?>
                <?php
                $fType = e($flash['type']);
                $fIcon = $fType === 'success' ? '&#10003;' : '&#9888;';
                ?>
                <div class="alert alert-<?= $fType ?>" role="alert" style="margin-bottom: 18px;">
                    <div class="alert-icon-pill"><?= $fIcon ?></div>
                    <div class="alert-content">
                        <span><?= e($flash['message']) ?></span>
                    </div>
                    <div class="alert-timer-bar"></div>
                </div>
            <?php endforeach; ?>

            <?php if ($error !== ''): ?>
                <div class="feedback-card feedback-card-danger" role="alert" style="margin-bottom: 22px;">
                    <div class="feedback-card-icon">&#9888;</div>
                    <div class="feedback-card-content">
                        <h4 class="feedback-card-title">Authentication Failed</h4>
                        <p class="feedback-card-desc"><?= e($error) ?></p>
                    </div>
                    <div class="feedback-card-action">
                        <button type="button" class="btn btn-danger btn-sm" id="load-creds-from-error" style="white-space: nowrap;">
                            &#9889; Load Demo Creds
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 1-Click Demo Credentials Auto-Fill Helper -->
            <div class="login-credentials-helper" id="demo-credentials-box">
                <div class="login-cred-row">
                    <span style="font-weight: 700; color: #93c5fd;">Demo Credentials (Simulated Auth):</span>
                    <button type="button" id="autofill-demo-btn" class="login-autofill-btn" title="Click to populate credentials instantly">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        <span>1-Click Auto-Fill</span>
                    </button>
                </div>
                <div class="login-cred-row">
                    <span>Email / User:</span>
                    <code class="login-cred-val" id="demo-user-val"><?= e(DEMO_ADMIN_USERNAME) ?></code>
                </div>
                <div class="login-cred-row">
                    <span>Password:</span>
                    <code class="login-cred-val" id="demo-pass-val"><?= e(DEMO_ADMIN_PASSWORD) ?></code>
                </div>
            </div>

            <!-- Login Form -->
            <form action="login.php" method="post" data-disable-on-submit="true" id="admin-login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <!-- Email / Username Input -->
                <div class="login-form-group">
                    <label for="username" class="login-form-label">Admin Email or Username</label>
                    <div class="login-input-box">
                        <span class="login-input-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="login-input-control"
                            placeholder="admin@cubetech.demo"
                            value="<?= e($usernameInput) ?>"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <!-- Password Input with Eye Toggle -->
                <div class="login-form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 7px;">
                        <label for="password" class="login-form-label" style="margin: 0;">Password</label>
                        <span style="font-size: 0.74rem; color: #64748b;">Simulated Auth</span>
                    </div>
                    <div class="login-input-box">
                        <span class="login-input-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="login-input-control"
                            placeholder="Enter password..."
                            value="<?= e(DEMO_ADMIN_PASSWORD) ?>"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            id="toggle-password-btn"
                            class="login-pw-toggle"
                            aria-label="Toggle password visibility"
                            title="Show/hide password"
                        >
                            <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="login-submit-btn" id="login-submit-btn" data-saving-text="Authenticating...">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span>Sign In to Dashboard</span>
                </button>
            </form>

            <!-- Card Footer: Return to Storefront -->
            <div class="login-card-footer">
                <a href="../index.php" class="login-store-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Return to Customer Storefront</span>
                </a>
            </div>
        </div>
    </main>

    <!-- Interactive Client Script for Splash Activity & UI Enhancements -->
    <script>
    (function () {
        'use strict';

        var splashEl     = document.getElementById('splash-activity');
        var skipBtn      = document.getElementById('splash-skip-btn');
        var progressBar  = document.getElementById('splash-progress-bar');
        var statusText   = document.getElementById('splash-status-text');
        var autofillBtn  = document.getElementById('autofill-demo-btn');
        var userInput    = document.getElementById('username');
        var passInput    = document.getElementById('password');
        var pwToggleBtn  = document.getElementById('toggle-password-btn');
        var eyeIcon      = document.getElementById('eye-icon');
        var hasSubmitted = document.body.getAttribute('data-has-submitted') === 'true';

        // 1. Splash Activity Execution
        function dismissSplash() {
            if (!splashEl || splashEl.classList.contains('is-hidden')) return;
            splashEl.classList.add('is-hidden');
            setTimeout(function () {
                splashEl.remove();
            }, 600);
        }

        if (skipBtn) {
            skipBtn.addEventListener('click', dismissSplash);
        }

        // If the user already submitted and got an error, skip splash immediately
        if (hasSubmitted) {
            dismissSplash();
        } else {
            // Animate progress bar across key checkpoints
            var steps = [
                { percent: 25, delay: 180, text: 'Verifying environment...' },
                { percent: 65, delay: 480, text: 'Loading store modules...' },
                { percent: 95, delay: 880, text: 'Establishing secure session...' },
                { percent: 100, delay: 1180, text: 'Ready!' }
            ];

            steps.forEach(function (step) {
                setTimeout(function () {
                    if (progressBar) progressBar.style.width = step.percent + '%';
                    if (statusText) statusText.textContent = step.text;
                }, step.delay);
            });

            // Auto-dismiss after completion
            setTimeout(function () {
                dismissSplash();
            }, 1450);
        }

        // 2. 1-Click Auto-Fill Demo Credentials Helper
        var errorAutofillBtn = document.getElementById('load-creds-from-error');

        function triggerAutofill(triggerBtn) {
            if (!userInput || !passInput) return;
            userInput.value = '<?= e(DEMO_ADMIN_USERNAME) ?>';
            passInput.value = '<?= e(DEMO_ADMIN_PASSWORD) ?>';

            // Add highlight glow to inputs
            [userInput, passInput].forEach(function (inp) {
                inp.classList.remove('input-autofill-highlight');
                void inp.offsetWidth; // trigger reflow
                inp.classList.add('input-autofill-highlight');
            });

            if (triggerBtn) {
                var originalHtml = triggerBtn.innerHTML;
                triggerBtn.innerHTML = '&#10003; Loaded!';
                triggerBtn.style.background = '#059669';
                triggerBtn.style.borderColor = '#34d399';

                setTimeout(function () {
                    triggerBtn.innerHTML = originalHtml;
                    triggerBtn.style.background = '';
                    triggerBtn.style.borderColor = '';
                }, 1200);
            }

            passInput.focus();
        }

        if (autofillBtn) {
            autofillBtn.addEventListener('click', function (e) {
                e.preventDefault();
                triggerAutofill(autofillBtn);
            });
        }

        if (errorAutofillBtn) {
            errorAutofillBtn.addEventListener('click', function (e) {
                e.preventDefault();
                triggerAutofill(errorAutofillBtn);
            });
        }

        // Form Submission Loading State with Animated Spinner
        var loginForm = document.getElementById('admin-login-form');
        var submitBtn = document.getElementById('login-submit-btn');
        if (loginForm && submitBtn) {
            loginForm.addEventListener('submit', function () {
                if (!submitBtn.disabled) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="btn-spinner-icon"></span> Authenticating Session...';
                    submitBtn.style.opacity = '0.9';
                }
            });
        }

        // 3. Password Visibility Toggle
        if (pwToggleBtn && passInput && eyeIcon) {
            pwToggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var isPassword = passInput.type === 'password';
                passInput.type = isPassword ? 'text' : 'password';

                if (isPassword) {
                    // Show eye-off icon
                    eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                    pwToggleBtn.setAttribute('title', 'Hide password');
                } else {
                    // Show standard eye icon
                    eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                    pwToggleBtn.setAttribute('title', 'Show password');
                }
            });
        }
    })();
    </script>
</body>
</html>
