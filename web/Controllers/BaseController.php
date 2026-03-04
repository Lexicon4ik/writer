<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;

/**
 * Base controller with common functionality for all admin controllers.
 */
abstract class BaseController
{
    /**
     * Render a template with data.
     *
     * @param string $template Template path (relative to templates/)
     * @param array $data Variables to extract into template
     */
    protected function render(string $template, array $data = []): void
    {
        // Extract data to make variables available in template
        extract($data);

        // Get current page from request for sidebar active state
        $page = $_GET['page'] ?? 'dashboard';

        // Content path
        $content = ROOT_DIR . '/web/templates/' . $template . '.php';

        if (!file_exists($content)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        // Render content first
        ob_start();
        require $content;
        $contentHtml = ob_get_clean();

        // Include layout
        require ROOT_DIR . '/web/templates/layout.php';
    }

    /**
     * Redirect to URL.
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Validate CSRF token from POST data.
     */
    protected function validateCsrf(): void
    {
        $postToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($postToken) || $postToken !== $sessionToken) {
            http_response_code(403);
            die('CSRF token mismatch');
        }
    }

    /**
     * Set a flash message for display on next request.
     *
     * @param string $type Message type (success, danger, warning, info)
     * @param string $message Message text
     */
    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Get flash message and clear it.
     */
    protected function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Paginate a query result.
     *
     * @param string $countSql SQL query to count total items
     * @param string $dataSql SQL query to fetch data with LIMIT placeholder
     * @param array $params Parameters for both queries
     * @param int $perPage Items per page
     * @return array ['items' => array, 'pagination' => array]
     */
    protected function paginate(
        string $countSql,
        string $dataSql,
        array $params = [],
        int $perPage = 50
    ): array {
        $currentPage = max(1, (int)($_GET['p'] ?? 1));

        // Get total count
        $countResult = Database::fetchOne($countSql, $params);
        $totalItems = (int)($countResult['cnt'] ?? $countResult[array_key_first($countResult)] ?? 0);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Ensure current page is valid
        $currentPage = min($currentPage, $totalPages);

        // Calculate offset
        $offset = ($currentPage - 1) * $perPage;

        // Fetch data
        $dataSql .= " LIMIT {$perPage} OFFSET {$offset}";
        $items = Database::fetchAll($dataSql, $params);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'per_page' => $perPage,
                'has_prev' => $currentPage > 1,
                'has_next' => $currentPage < $totalPages,
            ],
        ];
    }

    /**
     * Require POST method for action.
     */
    protected function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }
    }

    /**
     * Return JSON response.
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get client IP address.
     */
    protected function getClientIp(): string
    {
        // Check for forwarded IP (behind proxy/load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Escape string for HTML output.
     */
    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
