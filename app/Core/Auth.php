<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/config.php';

/**
 * CubeTech Hub - Admin Demo Authentication & Session Guard
 * Section 11: Prevents direct access to admin routes without a demo session.
 */
class Auth
{
    private const ADMIN_SESSION_KEY = 'cubetech_admin_logged_in';
    private const ADMIN_USER_KEY    = 'cubetech_admin_user';

    public static function isLoggedIn(): bool
    {
        return (!empty($_SESSION[self::ADMIN_SESSION_KEY]) && $_SESSION[self::ADMIN_SESSION_KEY] === true)
            || (!empty($_SESSION['cebutech_admin_logged_in']) && $_SESSION['cebutech_admin_logged_in'] === true);
    }

    public static function getAdminUser(): string
    {
        return (string)($_SESSION[self::ADMIN_USER_KEY] ?? $_SESSION['cebutech_admin_user'] ?? DEMO_ADMIN_USERNAME);
    }

    public static function attemptLogin(string $username, string $password): bool
    {
        $username = trim($username);
        // Case-insensitive username check for demo convenience, exact password check
        if (
            strcasecmp($username, DEMO_ADMIN_USERNAME) === 0 &&
            hash_equals(DEMO_ADMIN_PASSWORD, $password)
        ) {
            session_regenerate_id(true);
            $_SESSION[self::ADMIN_SESSION_KEY] = true;
            $_SESSION[self::ADMIN_USER_KEY]    = DEMO_ADMIN_USERNAME;
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::ADMIN_SESSION_KEY], $_SESSION[self::ADMIN_USER_KEY]);
    }

    /**
     * Guard an admin page: redirects unauthenticated visitors to admin/login.php
     */
    public static function requireAdmin(): void
    {
        if (!self::isLoggedIn()) {
            setFlash('warning', 'Please sign in with the demo admin credentials to access the Admin Dashboard.');
            header('Location: login.php');
            exit;
        }
    }
}

