<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;

/**
 * Authentication controller with brute-force protection.
 */
class AuthController extends BaseController
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Show login form.
     */
    public function index(?int $id = null): void
    {
        // If already logged in, redirect to dashboard
        if (!empty($_SESSION['admin_id'])) {
            $this->redirect('?page=dashboard');
            return;
        }

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        $this->render('login', [
            'error' => $error,
            'pageTitle' => 'Login',
        ]);
    }

    /**
     * Process login form.
     */
    public function login(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $this->getClientIp();

        // Check for lockout
        if ($this->isLockedOut($ip)) {
            $_SESSION['login_error'] = 'Too many failed attempts. Please try again in ' . self::LOCKOUT_MINUTES . ' minutes.';
            $this->redirect('?page=login');
            return;
        }

        // Validate input
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Username and password are required.';
            $this->logAttempt($ip, $username, false);
            $this->redirect('?page=login');
            return;
        }

        // Find user
        $user = Database::fetchOne(
            "SELECT id, username, password_hash FROM admin_users WHERE username = ?",
            [$username]
        );

        // Verify password
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logAttempt($ip, $username, false);
            $_SESSION['login_error'] = 'Invalid username or password.';

            Logger::warning('Failed login attempt', [
                'username' => $username,
                'ip' => $ip,
            ]);

            $this->redirect('?page=login');
            return;
        }

        // Successful login
        $this->logAttempt($ip, $username, true);

        // Update last login
        Database::update('admin_users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        // Set session
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['last_activity'] = time();

        // Regenerate session ID for security
        session_regenerate_id(true);

        Logger::info('Admin login successful', [
            'user_id' => $user['id'],
            'username' => $username,
            'ip' => $ip,
        ]);

        $this->redirect('?page=dashboard');
    }

    /**
     * Logout.
     */
    public function logout(?int $id = null): void
    {
        $username = $_SESSION['admin_username'] ?? 'unknown';

        Logger::info('Admin logout', ['username' => $username]);

        // Clear session
        $_SESSION = [];

        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session
        session_destroy();

        // Redirect to login
        header('Location: ?page=login');
        exit;
    }

    /**
     * Check if IP is locked out due to too many failed attempts.
     */
    private function isLockedOut(string $ip): bool
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::LOCKOUT_MINUTES . ' minutes'));

        $result = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM admin_login_attempts
             WHERE ip_address = ? AND success = 0 AND attempted_at > ?",
            [$ip, $cutoff]
        );

        return ((int)($result['cnt'] ?? 0)) >= self::MAX_ATTEMPTS;
    }

    /**
     * Log login attempt.
     */
    private function logAttempt(string $ip, string $username, bool $success): void
    {
        Database::insert('admin_login_attempts', [
            'ip_address' => $ip,
            'username' => $username,
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
