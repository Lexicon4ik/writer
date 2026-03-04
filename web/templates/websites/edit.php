<?php
/**
 * Website endpoint edit/create form.
 * Variables: $endpoint, $decryptedCredential, $channels, $flash
 */

use function NewsBot\Web\Helpers\__;

$isEdit = $endpoint !== null;

$authTypes = [
    'none'          => __('websites.auth_none'),
    'bearer'        => __('websites.auth_bearer'),
    'api_key'       => __('websites.auth_api_key'),
    'basic'         => __('websites.auth_basic'),
    'custom_header' => __('websites.auth_custom_header'),
];

$httpMethods  = ['POST', 'PUT', 'PATCH'];
$contentTypes = [
    'application/json'                  => __('websites.content_type_json'),
    'application/x-www-form-urlencoded' => 'application/x-www-form-urlencoded',
];

// Pretty-print JSON fields for editing
function prettyJson(?string $raw): string {
    if (empty($raw)) return '';
    $decoded = json_decode($raw, true);
    if ($decoded === null) return $raw;
    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$fieldMappingValue  = $isEdit ? prettyJson($endpoint->field_mapping)  : prettyJson('{"title":"title","body":{"to":"content","transform":"strip_html"},"description":"excerpt","hashtags":{"to":"tags","transform":"array"},"url":"source_url"}');
$payloadExtrasValue = $isEdit ? prettyJson($endpoint->payload_extras) : prettyJson('{"status":"publish"}');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <i class="bi bi-globe2"></i>
        <?= $isEdit ? __('websites.edit') : __('websites.new') ?>
    </h2>
    <a href="?page=websites" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('websites.btn_back') ?>
    </a>
</div>

<form method="post" action="?page=websites&action=save" id="endpointForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="id" value="<?= $isEdit ? (int)$endpoint->id : 0 ?>">

    <div class="row g-4">

        <!-- LEFT COLUMN -->
        <div class="col-lg-8">

            <!-- Basic info -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-info-circle"></i> <?= __('websites.section_basic') ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('websites.field_name') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->name ?? '') : '') ?>"
                                   placeholder="<?= __('websites.field_name_placeholder') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('websites.field_site_url') ?> <small class="text-muted"><?= __('websites.field_site_url_note') ?></small></label>
                            <input type="url" class="form-control" name="site_url"
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->site_url ?? '') : '') ?>"
                                   placeholder="https://example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('websites.field_api_url') ?> <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" name="api_url" required
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->api_url ?? '') : '') ?>"
                                   placeholder="https://example.com/wp-json/wp/v2/posts">
                            <div class="form-text"><?= __('websites.field_api_url_help') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('websites.field_channel') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" name="source_channel_id" required>
                                <option value=""><?= __('websites.field_channel_select') ?></option>
                                <?php foreach ($channels as $ch): ?>
                                    <option value="<?= (int)$ch->id ?>"
                                        <?= ($isEdit && (int)$endpoint->source_channel_id === (int)$ch->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ch->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= __('websites.field_channel_help') ?></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('websites.field_http_method') ?></label>
                            <select class="form-select" name="http_method">
                                <?php foreach ($httpMethods as $m): ?>
                                    <option value="<?= $m ?>"
                                        <?= ($isEdit ? $endpoint->http_method : 'POST') === $m ? 'selected' : '' ?>>
                                        <?= $m ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('websites.field_status') ?></label>
                            <select class="form-select" name="status">
                                <option value="active" <?= (!$isEdit || $endpoint->status === 'active') ? 'selected' : '' ?>><?= __('websites.status_active') ?></option>
                                <option value="paused" <?= ($isEdit && $endpoint->status === 'paused') ? 'selected' : '' ?>><?= __('websites.status_paused') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('websites.field_content_type') ?></label>
                            <select class="form-select" name="content_type">
                                <?php foreach ($contentTypes as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= ($isEdit ? $endpoint->content_type : 'application/json') === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Authentication -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-shield-lock"></i> <?= __('websites.section_auth') ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_auth_type') ?></label>
                            <select class="form-select" name="auth_type" id="authType"
                                    onchange="toggleAuthFields()">
                                <?php foreach ($authTypes as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= ($isEdit ? $endpoint->auth_type : 'bearer') === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="headerNameWrap" style="display:none">
                            <label class="form-label"><?= __('websites.field_auth_header_name') ?></label>
                            <input type="text" class="form-control" name="auth_header_name"
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->auth_header_name ?? '') : '') ?>"
                                   placeholder="X-API-Key">
                            <div class="form-text"><?= __('websites.field_auth_header_name_help') ?></div>
                        </div>
                        <div class="col" id="credentialWrap">
                            <label class="form-label">
                                <?= __('websites.field_auth_credential') ?>
                                <?php if ($isEdit && !empty($decryptedCredential)): ?>
                                    <small class="text-muted"><?= __('websites.field_auth_credential_keep') ?></small>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="auth_credential"
                                       id="authCredential"
                                       value="<?= htmlspecialchars($decryptedCredential ?? '') ?>"
                                       placeholder="<?= __('websites.field_auth_credential_placeholder') ?>">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="toggleCredVisibility()">
                                    <i class="bi bi-eye" id="credIcon"></i>
                                </button>
                            </div>
                            <div class="form-text" id="credHelp">
                                <?= __('websites.field_auth_credential_help') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Field mapping -->
            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-arrow-left-right"></i> <?= __('websites.section_mapping') ?></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="collapse" data-bs-target="#mappingHelp">
                        <i class="bi bi-question-circle"></i> <?= __('websites.mapping_help_btn') ?>
                    </button>
                </div>
                <div class="card-body">
                    <div class="collapse mb-3" id="mappingHelp">
                        <div class="alert alert-info small mb-0">
                            <?= __('websites.mapping_help_fields') ?><br>
                            <code>title</code>, <code>short_title</code>, <code>description</code>,
                            <code>body</code>,
                            <code>body_plain</code>,
                            <code>hashtags</code>,
                            <code>url</code>, <code>image_url</code>,
                            <code>date</code>, <code>date_iso</code>,
                            <code>source_name</code>, <code>importance_score</code>
                            <br><br>
                            <?= __('websites.mapping_help_syntax') ?><br>
                            <?= __('websites.mapping_help_direct') ?><br>
                            <?= __('websites.mapping_help_transform') ?><br>
                            <?= __('websites.mapping_help_transforms_label') ?>
                            <code>strip_html</code>, <code>array</code>, <code>csv</code>,
                            <code>iso8601</code>, <code>plain_date</code><br>
                            <?= __('websites.mapping_help_nesting') ?> <code>"meta.source_url"</code> →
                            <code>{"meta": {"source_url": "..."}}</code>
                        </div>
                    </div>

                    <label class="form-label"><?= __('websites.field_mapping_json') ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control font-monospace" name="field_mapping"
                              rows="8" required
                              style="font-size:0.85em"><?= htmlspecialchars($fieldMappingValue) ?></textarea>
                    <div class="form-text">
                        <?= __('websites.mapping_example') ?>
                        <code>{"title": "title", "body": {"to": "content", "transform": "strip_html"}, "description": "excerpt"}</code>
                    </div>
                </div>
            </div>

            <!-- Payload extras -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-plus-square"></i> <?= __('websites.section_extras') ?>
                    <small class="text-muted fw-normal ms-2">(<?= __('common.optional') ?>)</small>
                </div>
                <div class="card-body">
                    <textarea class="form-control font-monospace" name="payload_extras"
                              rows="4"
                              style="font-size:0.85em"><?= htmlspecialchars($payloadExtrasValue) ?></textarea>
                    <div class="form-text">
                        <?= __('websites.extras_note') ?>
                    </div>
                </div>
            </div>

            <!-- Response & error handling -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-arrow-return-left"></i> <?= __('websites.section_response') ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_success_codes') ?></label>
                            <input type="text" class="form-control" name="success_http_codes"
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->success_http_codes ?? '200,201') : '200,201') ?>"
                                   placeholder="200,201">
                            <div class="form-text"><?= __('websites.field_success_codes_help') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_external_id_path') ?></label>
                            <input type="text" class="form-control" name="external_id_path"
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->external_id_path ?? '') : '') ?>"
                                   placeholder="id  или  data.post_id">
                            <div class="form-text"><?= __('websites.field_external_id_help') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_external_url_path') ?></label>
                            <input type="text" class="form-control" name="external_url_path"
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->external_url_path ?? '') : '') ?>"
                                   placeholder="link  или  data.url">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_retry_codes') ?></label>
                            <input type="text" class="form-control" name="retry_http_codes"
                                   value="<?= htmlspecialchars($isEdit ? ($endpoint->retry_http_codes ?? '429,500,502,503,504') : '429,500,502,503,504') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_max_retries') ?></label>
                            <input type="number" class="form-control" name="max_retries"
                                   min="0" max="10"
                                   value="<?= (int)($isEdit ? ($endpoint->max_retries ?? 3) : 3) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('websites.field_retry_delay') ?></label>
                            <input type="number" class="form-control" name="retry_delay_sec"
                                   min="30"
                                   value="<?= (int)($isEdit ? ($endpoint->retry_delay_sec ?? 300) : 300) ?>">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-lg-4">

            <!-- Schedule -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-clock"></i> <?= __('websites.section_schedule') ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('websites.field_interval') ?></label>
                        <input type="number" class="form-control" name="publish_interval_min"
                               min="1"
                               value="<?= (int)($isEdit ? ($endpoint->publish_interval_min ?? 30) : 30) ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label"><?= __('websites.field_hours_start') ?></label>
                            <input type="time" class="form-control" name="active_hours_start"
                                   value="<?= htmlspecialchars(substr($isEdit ? ($endpoint->active_hours_start ?? '08:00:00') : '08:00:00', 0, 5)) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?= __('websites.field_hours_end') ?></label>
                            <input type="time" class="form-control" name="active_hours_end"
                                   value="<?= htmlspecialchars(substr($isEdit ? ($endpoint->active_hours_end ?? '22:00:00') : '22:00:00', 0, 5)) ?>">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label"><?= __('websites.field_max_per_run') ?></label>
                            <input type="number" class="form-control" name="max_per_run"
                                   min="1"
                                   value="<?= (int)($isEdit ? ($endpoint->max_per_run ?? 5) : 5) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?= __('websites.field_max_per_day') ?></label>
                            <input type="number" class="form-control" name="max_per_day"
                                   min="1"
                                   value="<?= (int)($isEdit ? ($endpoint->max_per_day ?? 50) : 50) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info (edit mode) -->
            <?php if ($isEdit): ?>
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-activity"></i> <?= __('websites.section_stats') ?>
                </div>
                <div class="card-body">
                    <?php
                    $stats = \NewsBot\Core\Database::fetchOne("
                        SELECT
                            COUNT(*) as total,
                            SUM(status = 'published') as published,
                            SUM(status = 'failed') as failed,
                            SUM(status = 'cancelled') as cancelled,
                            MAX(published_at) as last_published
                        FROM website_article_versions
                        WHERE endpoint_id = ?
                    ", [(int)$endpoint->id]);
                    ?>
                    <dl class="row small mb-0">
                        <dt class="col-7"><?= __('websites.stats_total') ?></dt>
                        <dd class="col-5"><?= (int)($stats['total'] ?? 0) ?></dd>

                        <dt class="col-7"><?= __('websites.stats_published') ?></dt>
                        <dd class="col-5 text-success"><?= (int)($stats['published'] ?? 0) ?></dd>

                        <dt class="col-7"><?= __('websites.stats_failed') ?></dt>
                        <dd class="col-5 text-danger"><?= (int)($stats['failed'] ?? 0) ?></dd>

                        <dt class="col-7"><?= __('websites.stats_cancelled') ?></dt>
                        <dd class="col-5 text-muted"><?= (int)($stats['cancelled'] ?? 0) ?></dd>

                        <dt class="col-7"><?= __('websites.stats_last') ?></dt>
                        <dd class="col-5">
                            <?= $stats['last_published']
                                ? date('d.m.Y H:i', strtotime($stats['last_published']))
                                : '—' ?>
                        </dd>

                        <dt class="col-7"><?= __('websites.stats_created') ?></dt>
                        <dd class="col-5"><?= date('d.m.Y H:i', strtotime($endpoint->created_at ?? 'now')) ?></dd>
                    </dl>

                    <?php if ($endpoint->isActive()): ?>
                    <div class="mt-3">
                        <form method="post" action="?page=websites&action=test" class="d-inline" id="testForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$endpoint->id ?>">
                            <button type="button" class="btn btn-sm btn-outline-info w-100"
                                    onclick="testEndpointPage(<?= (int)$endpoint->id ?>)">
                                <i class="bi bi-wifi"></i> <?= __('websites.btn_test') ?>
                            </button>
                        </form>
                        <div id="testResult" class="mt-2 small"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Save -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= __('websites.btn_save') ?>
                        </button>
                        <a href="?page=websites" class="btn btn-outline-secondary"><?= __('websites.btn_cancel') ?></a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
function toggleAuthFields() {
    const authType       = document.getElementById('authType').value;
    const headerNameWrap = document.getElementById('headerNameWrap');
    const credentialWrap = document.getElementById('credentialWrap');

    const needsHeader = ['api_key', 'custom_header'].includes(authType);
    const needsCred   = authType !== 'none';

    headerNameWrap.style.display = needsHeader ? '' : 'none';
    credentialWrap.style.display = needsCred   ? '' : 'none';
}

function toggleCredVisibility() {
    const input = document.getElementById('authCredential');
    const icon  = document.getElementById('credIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function testEndpointPage(id) {
    const btn    = event.target.closest('button');
    const result = document.getElementById('testResult');
    const orig   = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> <?= __('websites.btn_checking') ?>';
    result.textContent = '';

    const form = new FormData();
    form.append('id', id);
    form.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

    fetch('?page=websites&action=test', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            result.innerHTML = '<span class="text-' + (data.success ? 'success' : 'danger') + '">'
                + '<i class="bi bi-' + (data.success ? 'check-circle' : 'x-circle') + '"></i> '
                + data.message + '</span>';
        })
        .catch(e => { result.textContent = '<?= __('websites.test_error') ?>' + e.message; })
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

// Init on load
document.addEventListener('DOMContentLoaded', toggleAuthFields);
</script>
