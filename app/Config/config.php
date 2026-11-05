<?php
declare(strict_types=1);

/**
 * CebuTech Hub - Global Configuration & Helper Utilities
 * Web Development Internship Assessment | CubeTech Innovations
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Manila');

// Store Identity Constants
define('STORE_NAME', 'CebuTech Hub');
define('STORE_TAGLINE', 'Precision Gear for Developers & Creators');
define('STORE_COMPANY', 'CubeTech Innovations (Fictional Demo Store)');
define('STORE_CURRENCY', 'PHP');

// Demo Admin Credentials (Simulated authentication for assessment demo only)
define('DEMO_ADMIN_USERNAME', getenv('ADMIN_USER') ?: 'admin@cubetech.demo');
define('DEMO_ADMIN_PASSWORD', getenv('ADMIN_PASS') ?: 'AdminDemo123!');

// Directory & Storage Paths
define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'public');
define('DATA_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
define('SQLITE_DB_PATH', DATA_DIR . DIRECTORY_SEPARATOR . 'cebutech.sqlite');
define('JSON_DB_PATH', DATA_DIR . DIRECTORY_SEPARATOR . 'cebutech_data.json');

// Allowed Payment Methods (Selection only per assessment specification)
define('PAYMENT_METHODS', [
    'Cash on Delivery',
    'E-Wallet',
    'Bank Transfer',
]);

// Order Statuses & Strict State Machine Transitions (Section 10 of Assessment)
// Sequence: Pending -> Confirmed -> Preparing -> Shipped -> Completed
// Cancellation permitted ONLY from Pending or Confirmed.
// Completed and Cancelled are final statuses.
define('ORDER_STATUSES', [
    'Pending',
    'Confirmed',
    'Preparing',
    'Shipped',
    'Completed',
    'Cancelled',
]);

define('ORDER_STATUS_TRANSITIONS', [
    'Pending'   => ['Confirmed', 'Cancelled'],
    'Confirmed' => ['Preparing', 'Cancelled'],
    'Preparing' => ['Shipped'],
    'Shipped'   => ['Completed'],
    'Completed' => [], // Final status
    'Cancelled' => [], // Final status
]);

/**
 * HTML-escape output safely
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Convert integer centavos to formatted PHP string (two decimal places)
 * Example: 32550 -> "PHP 325.50"
 */
function formatPhp(int $cents, bool $includeCurrency = true): string
{
    $negative = $cents < 0;
    $absCents = abs($cents);
    $whole = intdiv($absCents, 100);
    $fraction = $absCents % 100;
    $formatted = number_format($whole) . '.' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT);
    $prefix = $includeCurrency ? STORE_CURRENCY . ' ' : '';
    return ($negative ? '-' : '') . $prefix . $formatted;
}

/**
 * Convert integer centavos to plain 2-decimal number string for form inputs
 * Example: 8550 -> "85.50"
 */
function centsToDecimal(int $cents): string
{
    $whole = intdiv(abs($cents), 100);
    $fraction = abs($cents) % 100;
    return ($cents < 0 ? '-' : '') . $whole . '.' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT);
}

/**
 * Safely parse a decimal price string (e.g., "120.00" or "85.50") into integer centavos
 * Avoids floating-point rounding bugs by parsing string parts directly.
 * Returns null if invalid or <= 0.
 */
function parsePriceToCents(string $input): ?int
{
    $cleaned = str_replace([',', ' ', 'PHP', 'php', '₱'], '', trim($input));
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $cleaned)) {
        return null;
    }
    $parts = explode('.', $cleaned);
    $whole = (int)$parts[0];
    $fractionStr = isset($parts[1]) ? str_pad($parts[1], 2, '0', STR_PAD_RIGHT) : '00';
    $fraction = (int)$fractionStr;
    $totalCents = ($whole * 100) + $fraction;
    return $totalCents > 0 ? $totalCents : null;
}

/**
 * Normalize customer email for deduplication and lookup
 */
function normalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Format a database timestamp into an accurate, human-readable local time string
 * Example: "2026-11-05 18:22:15" -> "Nov 5, 2026, 6:22 PM"
 */
function formatDateTime(?string $datetime, string $format = 'M j, Y, g:i A'): string
{
    if (empty($datetime)) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return (string)$datetime;
    }
    return date($format, $ts);
}

/**
 * Return current real-time timestamp in the configured timezone
 */
function currentTimestamp(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Generate or retrieve CSRF token
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token on POST requests
 */
function verifyCsrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set a flash message for the next request
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear all flash messages
 */
function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Generate URL slug from string
 */
function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim((string)$slug, '-') ?: 'item';
}

