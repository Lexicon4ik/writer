<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\{Crypto, Database};
use NewsBot\Models\{Channel, WebsiteEndpoint};

/**
 * Controller for managing website REST publishing endpoints.
 */
class WebsiteController extends BaseController
{
    /**
     * List all website endpoints.
     */
    public function index(?int $id = null): void
    {
        $endpoints = Database::fetchAll("
            SELECT we.*,
                   c.name AS channel_name,
                   (SELECT COUNT(*) FROM website_article_versions wav
                    WHERE wav.endpoint_id = we.id AND wav.status = 'published') AS published_count,
                   (SELECT COUNT(*) FROM website_article_versions wav
                    WHERE wav.endpoint_id = we.id AND wav.status = 'failed') AS failed_count
            FROM website_endpoints we
            LEFT JOIN channels c ON c.id = we.source_channel_id
            ORDER BY we.status ASC, we.name ASC
        ");

        $this->render('websites/index', [
            'pageTitle' => 'Сайты',
            'endpoints' => $endpoints,
            'flash'     => $this->getFlash(),
        ]);
    }

    /**
     * Edit / create endpoint form.
     */
    public function edit(?int $id = null): void
    {
        $endpoint          = null;
        $decryptedCredential = '';

        if ($id) {
            $endpoint = WebsiteEndpoint::find($id);
            if (!$endpoint) {
                $this->setFlash('danger', 'Сайт не найден');
                $this->redirect('?page=websites');
                return;
            }
            try {
                $decryptedCredential = $endpoint->getCredential();
            } catch (\Throwable) {
                $decryptedCredential = '';
            }
        }

        $channels = Channel::all("status = 'active'", [], 'name ASC');

        $this->render('websites/edit', [
            'pageTitle'            => $endpoint ? 'Редактировать сайт' : 'Новый сайт',
            'endpoint'             => $endpoint,
            'decryptedCredential'  => $decryptedCredential,
            'channels'             => $channels,
            'flash'                => $this->getFlash(),
        ]);
    }

    /**
     * Save endpoint (create or update).
     */
    public function save(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id               = (int)($_POST['id'] ?? 0);
        $name             = trim($_POST['name'] ?? '');
        $siteUrl          = trim($_POST['site_url'] ?? '');
        $apiUrl           = trim($_POST['api_url'] ?? '');
        $sourceChannelId  = (int)($_POST['source_channel_id'] ?? 0);
        $authType         = $_POST['auth_type'] ?? 'bearer';
        $credential       = trim($_POST['auth_credential'] ?? '');
        $authHeaderName   = trim($_POST['auth_header_name'] ?? '');
        $httpMethod       = $_POST['http_method'] ?? 'POST';
        $contentType      = $_POST['content_type'] ?? 'application/json';
        $fieldMappingRaw  = trim($_POST['field_mapping'] ?? '');
        $payloadExtrasRaw = trim($_POST['payload_extras'] ?? '');
        $successCodes     = trim($_POST['success_http_codes'] ?? '200,201');
        $externalIdPath   = trim($_POST['external_id_path'] ?? '');
        $externalUrlPath  = trim($_POST['external_url_path'] ?? '');
        $retryHttpCodes   = trim($_POST['retry_http_codes'] ?? '429,500,502,503,504');
        $maxRetries       = (int)($_POST['max_retries'] ?? 3);
        $retryDelaySec    = (int)($_POST['retry_delay_sec'] ?? 300);
        $publishInterval  = (int)($_POST['publish_interval_min'] ?? 30);
        $activeStart      = trim($_POST['active_hours_start'] ?? '08:00');
        $activeEnd        = trim($_POST['active_hours_end'] ?? '22:00');
        $maxPerRun        = (int)($_POST['max_per_run'] ?? 5);
        $maxPerDay        = (int)($_POST['max_per_day'] ?? 50);
        $status           = $_POST['status'] ?? 'active';

        // Validation
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Название обязательно';
        }
        if (empty($apiUrl) || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'API URL обязателен и должен быть корректным URL';
        }
        if ($sourceChannelId <= 0) {
            $errors[] = 'Необходимо выбрать канал-источник контента';
        }
        if (!in_array($authType, ['none','bearer','api_key','basic','custom_header'], true)) {
            $errors[] = 'Неверный тип аутентификации';
        }
        if (!in_array($httpMethod, ['POST','PUT','PATCH'], true)) {
            $errors[] = 'Неверный HTTP-метод';
        }
        if (!in_array($contentType, ['application/json','application/x-www-form-urlencoded'], true)) {
            $errors[] = 'Неверный Content-Type';
        }
        if (!in_array($status, ['active','paused'], true)) {
            $errors[] = 'Неверный статус';
        }

        // Validate field_mapping JSON
        $fieldMapping = null;
        if (!empty($fieldMappingRaw)) {
            $fieldMapping = json_decode($fieldMappingRaw, true);
            if ($fieldMapping === null) {
                $errors[] = 'Маппинг полей: некорректный JSON';
            }
        } else {
            $errors[] = 'Маппинг полей обязателен';
        }

        // Validate payload_extras JSON (optional)
        $payloadExtras = null;
        if (!empty($payloadExtrasRaw)) {
            $payloadExtras = json_decode($payloadExtrasRaw, true);
            if ($payloadExtras === null) {
                $errors[] = 'Статические поля: некорректный JSON';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect($id ? "?page=websites&action=edit&id={$id}" : '?page=websites&action=edit');
            return;
        }

        $data = [
            'name'                => $name,
            'site_url'            => $siteUrl,
            'api_url'             => $apiUrl,
            'source_channel_id'   => $sourceChannelId,
            'auth_type'           => $authType,
            'auth_header_name'    => $authHeaderName ?: null,
            'http_method'         => $httpMethod,
            'content_type'        => $contentType,
            'field_mapping'       => json_encode($fieldMapping, JSON_UNESCAPED_UNICODE),
            'payload_extras'      => $payloadExtras !== null
                                     ? json_encode($payloadExtras, JSON_UNESCAPED_UNICODE)
                                     : null,
            'success_http_codes'  => $successCodes,
            'external_id_path'    => $externalIdPath ?: null,
            'external_url_path'   => $externalUrlPath ?: null,
            'retry_http_codes'    => $retryHttpCodes,
            'max_retries'         => max(0, min(10, $maxRetries)),
            'retry_delay_sec'     => max(30, $retryDelaySec),
            'publish_interval_min' => max(1, $publishInterval),
            'active_hours_start'  => $activeStart . ':00',
            'active_hours_end'    => $activeEnd . ':00',
            'max_per_run'         => max(1, $maxPerRun),
            'max_per_day'         => max(1, $maxPerDay),
            'status'              => $status,
        ];

        // Handle credential — empty value preserves existing
        if (!empty($credential)) {
            $data['auth_credential'] = Crypto::encrypt($credential);
        } elseif ($id === 0) {
            $data['auth_credential'] = null;
        }
        // If editing and credential is empty — don't update (preserve existing)

        try {
            if ($id > 0) {
                WebsiteEndpoint::update($id, $data);
                $this->setFlash('success', 'Сайт обновлён');
            } else {
                $created = WebsiteEndpoint::create($data);
                $id = (int)$created->id;
                $this->setFlash('success', 'Сайт добавлен');
            }
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Ошибка сохранения: ' . $e->getMessage());
            $this->redirect($id ? "?page=websites&action=edit&id={$id}" : '?page=websites&action=edit');
            return;
        }

        $this->redirect('?page=websites');
    }

    /**
     * Toggle endpoint status (active/paused).
     */
    public function toggle(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $endpoint = WebsiteEndpoint::find($id);

        if (!$endpoint) {
            $this->setFlash('danger', 'Сайт не найден');
            $this->redirect('?page=websites');
            return;
        }

        $newStatus = $endpoint->status === 'active' ? 'paused' : 'active';
        WebsiteEndpoint::update($id, ['status' => $newStatus]);

        $this->setFlash('success', $newStatus === 'active' ? 'Сайт активирован' : 'Сайт приостановлен');
        $this->redirect('?page=websites');
    }

    /**
     * Delete endpoint.
     */
    public function delete(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $endpoint = WebsiteEndpoint::find($id);

        if (!$endpoint) {
            $this->setFlash('danger', 'Сайт не найден');
            $this->redirect('?page=websites');
            return;
        }

        WebsiteEndpoint::delete($id);
        $this->setFlash('success', 'Сайт удалён');
        $this->redirect('?page=websites');
    }

    /**
     * Test endpoint connectivity (sends a test request without saving an article).
     */
    public function test(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id       = (int)($_POST['id'] ?? $id ?? 0);
        $endpoint = WebsiteEndpoint::find($id);

        if (!$endpoint) {
            $this->json(['success' => false, 'message' => 'Эндпоинт не найден'], 404);
            return;
        }

        // Build minimal test payload using extras only
        $payload = array_merge(
            $endpoint->getPayloadExtras(),
            ['_newsbot_test' => true]
        );

        $headers   = $this->buildTestHeaders($endpoint);
        $headers[] = 'Content-Type: ' . ($endpoint->content_type ?? 'application/json');

        $body = $endpoint->content_type === 'application/x-www-form-urlencoded'
            ? http_build_query($payload)
            : json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint->api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($endpoint->http_method ?? 'POST'),
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->json(['success' => false, 'message' => 'Ошибка сети: ' . $curlErr]);
            return;
        }

        $successCodes = $endpoint->getSuccessHttpCodes();
        $ok           = in_array($httpCode, $successCodes, true);

        $this->json([
            'success'   => $ok,
            'http_code' => $httpCode,
            'message'   => $ok
                ? "Успех (HTTP {$httpCode})"
                : "HTTP {$httpCode}: " . mb_substr((string)$response, 0, 300),
        ]);
    }

    private function buildTestHeaders(WebsiteEndpoint $endpoint): array
    {
        $authType = $endpoint->auth_type ?? 'none';
        if ($authType === 'none') {
            return [];
        }
        $credential = $endpoint->getCredential();
        return match ($authType) {
            'bearer'        => ['Authorization: Bearer ' . $credential],
            'basic'         => ['Authorization: Basic ' . base64_encode($credential)],
            'api_key'       => [($endpoint->auth_header_name ?? 'X-API-Key') . ': ' . $credential],
            'custom_header' => [($endpoint->auth_header_name ?? 'X-Token') . ': ' . $credential],
            default         => [],
        };
    }
}
