<?php
/**
 * Settings page template.
 * Variables: $settingsGroups, $allSettings, $serviceBots, $todayUsage
 */

use NewsBot\Web\Controllers\SettingsController;
use function NewsBot\Web\Helpers\__;


// Helper to get setting value
$getSetting = function(string $key, string $default = '') use ($allSettings): string {
    return $allSettings[$key] ?? $default;
};

// Helper to mask sensitive values
$getMaskedSetting = function(string $key) use ($allSettings): string {
    $value = $allSettings[$key] ?? '';
    if (empty($value)) return '';
    return SettingsController::maskSensitiveValue($value);
};
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-gear"></i> <?= __('settings.title') ?></h2>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="post" action="?page=settings&action=save">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <?php foreach ($settingsGroups as $groupKey => $group): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi <?= htmlspecialchars($group['icon']) ?>"></i>
                    <?= htmlspecialchars($group['label']) ?>
                </div>
                <div class="card-body">
                    <?php foreach ($group['settings'] as $key => $config): ?>
                    <div class="mb-3 row">
                        <label for="<?= htmlspecialchars($key) ?>" class="col-sm-4 col-form-label">
                            <?= htmlspecialchars($config['label']) ?>
                        </label>
                        <div class="col-sm-8">
                            <?php if ($config['type'] === 'select'): ?>
                                <select class="form-select" id="<?= htmlspecialchars($key) ?>"
                                        name="settings[<?= htmlspecialchars($key) ?>]">
                                    <?php foreach ($config['options'] as $optVal => $optLabel): ?>
                                    <option value="<?= htmlspecialchars($optVal) ?>"
                                            <?= $getSetting($key, $config['default'] ?? '') === $optVal ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($optLabel) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($config['type'] === 'bot_select'): ?>
                                <select class="form-select" id="<?= htmlspecialchars($key) ?>"
                                        name="settings[<?= htmlspecialchars($key) ?>]">
                                    <option value=""><?= __('settings.not_selected') ?></option>
                                    <?php foreach ($serviceBots as $bot): ?>
                                    <option value="<?= (int)$bot['id'] ?>"
                                            <?= $getSetting($key) === (string)$bot['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bot['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($config['type'] === 'password'): ?>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="<?= htmlspecialchars($key) ?>"
                                           name="settings[<?= htmlspecialchars($key) ?>]"
                                           placeholder="<?= htmlspecialchars($config['placeholder'] ?? '') ?>"
                                           autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary toggle-password"
                                            data-target="<?= htmlspecialchars($key) ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <?php $masked = $getMaskedSetting($key); if (!empty($masked)): ?>
                                <div class="form-text"><?= __('settings.current_value') ?>: <code><?= htmlspecialchars($masked) ?></code></div>
                                <?php endif; ?>

                            <?php elseif ($config['type'] === 'checkbox'): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="<?= htmlspecialchars($key) ?>"
                                           name="settings[<?= htmlspecialchars($key) ?>]" value="1"
                                           <?= $getSetting($key, '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= htmlspecialchars($key) ?>"><?= __('settings.enabled') ?></label>
                                </div>

                            <?php elseif ($config['type'] === 'number'): ?>
                                <input type="number" class="form-control" id="<?= htmlspecialchars($key) ?>"
                                       name="settings[<?= htmlspecialchars($key) ?>]"
                                       value="<?= htmlspecialchars($getSetting($key, $config['default'] ?? '')) ?>"
                                       step="<?= htmlspecialchars($config['step'] ?? '1') ?>"
                                       min="<?= htmlspecialchars($config['min'] ?? '') ?>"
                                       max="<?= htmlspecialchars($config['max'] ?? '') ?>">

                            <?php else: ?>
                                <input type="text" class="form-control" id="<?= htmlspecialchars($key) ?>"
                                       name="settings[<?= htmlspecialchars($key) ?>]"
                                       value="<?= htmlspecialchars($getSetting($key, $config['default'] ?? '')) ?>"
                                       placeholder="<?= htmlspecialchars($config['placeholder'] ?? ($config['default'] ?? '')) ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= __('settings.btn_save') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- AI Usage Today -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> <?= __('settings.ai_usage_today') ?>
            </div>
            <div class="card-body">
                <?php
                $budget = (float)($allSettings['ai_daily_budget'] ?? 10);
                $used = (float)($todayUsage['cost'] ?? 0);
                $pct = $budget > 0 ? min(100, round(($used / $budget) * 100)) : 0;
                $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= __('settings.used') ?>:</span>
                    <strong>$<?= number_format($used, 4) ?> / $<?= number_format($budget, 2) ?></strong>
                </div>
                <div class="progress mb-3" style="height: 10px;">
                    <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-6"><?= __('settings.calls') ?>:</dt>
                    <dd class="col-6"><?= number_format((int)($todayUsage['calls'] ?? 0)) ?></dd>
                    <dt class="col-6"><?= __('settings.input_tokens') ?>:</dt>
                    <dd class="col-6"><?= number_format((int)($todayUsage['input_tokens'] ?? 0)) ?></dd>
                    <dt class="col-6"><?= __('settings.output_tokens') ?>:</dt>
                    <dd class="col-6"><?= number_format((int)($todayUsage['output_tokens'] ?? 0)) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Test AI -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-cpu"></i> <?= __('settings.test_ai') ?>
            </div>
            <div class="card-body">
                <p class="small text-muted"><?= __('settings.test_ai_hint') ?></p>
                <button type="button" id="test-ai-btn" class="btn btn-outline-primary w-100">
                    <i class="bi bi-play-fill"></i> <?= __('settings.btn_test_ai') ?>
                </button>
                <div id="test-ai-result" class="mt-3" style="display: none;"></div>
            </div>
        </div>

        <!-- Test Telegram -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-telegram"></i> <?= __('settings.test_telegram') ?>
            </div>
            <div class="card-body">
                <p class="small text-muted"><?= __('settings.test_telegram_hint') ?></p>
                <button type="button" id="test-telegram-btn" class="btn btn-outline-info w-100">
                    <i class="bi bi-send"></i> <?= __('settings.btn_test_telegram') ?>
                </button>
                <div id="test-telegram-result" class="mt-3" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
const i18n = {
    testAiBtn: <?= json_encode('<i class="bi bi-play-fill"></i> ' . __('settings.btn_test_ai')) ?>,
    testTgBtn: <?= json_encode('<i class="bi bi-send"></i> ' . __('settings.btn_test_telegram')) ?>,
    testSuccess: <?= json_encode(__('settings.test_success')) ?>,
    testProvider: <?= json_encode(__('settings.test_provider')) ?>,
    testModel: <?= json_encode(__('settings.test_model')) ?>,
    testResponse: <?= json_encode(__('settings.test_response')) ?>,
    testTokens: <?= json_encode(__('settings.test_tokens')) ?>,
    testDuration: <?= json_encode(__('settings.test_duration')) ?>,
    msgSent: <?= json_encode(__('settings.msg_sent')) ?>,
    testBot: <?= json_encode(__('settings.test_bot')) ?>,
    testChat: <?= json_encode(__('settings.test_chat')) ?>,
    testMessageId: <?= json_encode(__('settings.test_message_id')) ?>,
};

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = document.getElementById(this.dataset.target);
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});

// Test AI
document.getElementById('test-ai-btn').addEventListener('click', function() {
    const btn = this;
    const result = document.getElementById('test-ai-result');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
    result.style.display = 'none';

    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

    fetch('?page=settings&action=test_ai', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        result.style.display = 'block';
        if (data.success) {
            result.innerHTML = `
                <div class="alert alert-success small">
                    <strong>${i18n.testSuccess}</strong><br>
                    ${i18n.testProvider}: ${escapeHtml(data.provider)}<br>
                    ${i18n.testModel}: ${escapeHtml(data.model)}<br>
                    ${i18n.testResponse}: ${escapeHtml(data.response)}<br>
                    ${i18n.testTokens}: ${data.input_tokens} in / ${data.output_tokens} out<br>
                    ${i18n.testDuration}: ${data.duration_ms}ms
                </div>
            `;
        } else {
            result.innerHTML = `<div class="alert alert-danger small">${escapeHtml(data.error)}</div>`;
        }
    })
    .catch(err => {
        result.style.display = 'block';
        result.innerHTML = `<div class="alert alert-danger small">${escapeHtml(err.message)}</div>`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = i18n.testAiBtn;
    });
});

// Test Telegram
document.getElementById('test-telegram-btn').addEventListener('click', function() {
    const btn = this;
    const result = document.getElementById('test-telegram-result');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
    result.style.display = 'none';

    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

    fetch('?page=settings&action=test_telegram', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        result.style.display = 'block';
        if (data.success) {
            result.innerHTML = `
                <div class="alert alert-success small">
                    <strong>${i18n.msgSent}</strong><br>
                    ${i18n.testBot}: @${escapeHtml(data.bot_username)}<br>
                    ${i18n.testChat}: ${escapeHtml(data.chat_id)}<br>
                    ${i18n.testMessageId}: ${data.message_id}
                </div>
            `;
        } else {
            result.innerHTML = `<div class="alert alert-danger small">${escapeHtml(data.error)}</div>`;
        }
    })
    .catch(err => {
        result.style.display = 'block';
        result.innerHTML = `<div class="alert alert-danger small">${escapeHtml(err.message)}</div>`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = i18n.testTgBtn;
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
</script>
