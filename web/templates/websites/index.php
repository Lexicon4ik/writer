<?php
/**
 * Website endpoints list template.
 * Variables: $endpoints, $flash
 */

use function NewsBot\Web\Helpers\__;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-globe2"></i> <?= __('websites.title') ?></h2>
    <a href="?page=websites&action=edit" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> <?= __('websites.create') ?>
    </a>
</div>

<?php if (empty($endpoints)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-globe2 fs-1 d-block mb-3"></i>
            <p class="mb-3"><?= __('websites.msg_no_endpoints') ?></p>
            <a href="?page=websites&action=edit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> <?= __('websites.msg_add_first') ?>
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= __('websites.col_site') ?></th>
                        <th><?= __('websites.col_channel') ?></th>
                        <th><?= __('websites.col_auth') ?></th>
                        <th><?= __('websites.col_schedule') ?></th>
                        <th class="text-center"><?= __('websites.col_published') ?></th>
                        <th class="text-center"><?= __('websites.col_errors') ?></th>
                        <th class="text-center"><?= __('websites.col_status') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($endpoints as $ep): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($ep['name']) ?></div>
                            <?php if (!empty($ep['site_url'])): ?>
                                <small class="text-muted">
                                    <a href="<?= htmlspecialchars($ep['site_url']) ?>" target="_blank"
                                       class="text-muted text-decoration-none">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                        <?= htmlspecialchars(parse_url($ep['site_url'], PHP_URL_HOST) ?? $ep['site_url']) ?>
                                    </a>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($ep['channel_name'])): ?>
                                <i class="bi bi-megaphone text-info me-1"></i>
                                <?= htmlspecialchars($ep['channel_name']) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $authLabels = [
                                'none'          => ['secondary', __('websites.auth_none')],
                                'bearer'        => ['primary',   __('websites.auth_bearer')],
                                'api_key'       => ['info',      __('websites.auth_api_key')],
                                'basic'         => ['warning',   __('websites.auth_basic')],
                                'custom_header' => ['dark',      __('websites.auth_custom_header')],
                            ];
                            $authType = $ep['auth_type'] ?? 'none';
                            [$color, $label] = $authLabels[$authType] ?? ['secondary', $authType];
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= $label ?></span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= htmlspecialchars(substr($ep['active_hours_start'] ?? '08:00:00', 0, 5)) ?>
                                – <?= htmlspecialchars(substr($ep['active_hours_end'] ?? '22:00:00', 0, 5)) ?>
                                · <?= __('websites.schedule_max_day', ['n' => (int)($ep['max_per_day'] ?? 50)]) ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success"><?= (int)($ep['published_count'] ?? 0) ?></span>
                        </td>
                        <td class="text-center">
                            <?php $failedCount = (int)($ep['failed_count'] ?? 0); ?>
                            <span class="badge bg-<?= $failedCount > 0 ? 'danger' : 'secondary' ?>">
                                <?= $failedCount ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($ep['status'] === 'active'): ?>
                                <span class="badge bg-success"><?= __('websites.status_active') ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= __('websites.status_paused') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <!-- Test connection -->
                                <button type="button"
                                        class="btn btn-sm btn-outline-info"
                                        onclick="testEndpoint(<?= (int)$ep['id'] ?>, this)"
                                        title="<?= __('websites.btn_test') ?>">
                                    <i class="bi bi-wifi"></i>
                                </button>

                                <!-- Edit -->
                                <a href="?page=websites&action=edit&id=<?= (int)$ep['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary" title="<?= __('common.btn.edit') ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <!-- Toggle status -->
                                <form method="post" action="?page=websites&action=toggle" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$ep['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-<?= $ep['status'] === 'active' ? 'warning' : 'success' ?>"
                                            title="<?= $ep['status'] === 'active' ? __('websites.msg_toggle_pause') : __('websites.msg_toggle_resume') ?>">
                                        <i class="bi bi-<?= $ep['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="post" action="?page=websites&action=delete" class="d-inline"
                                      onsubmit="return confirm('<?= htmlspecialchars(__('websites.msg_delete_confirm', ['name' => $ep['name']]), ENT_QUOTES) ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$ep['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= __('common.btn.delete') ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Test result toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="testToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="testToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
function testEndpoint(id, btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const form = new FormData();
    form.append('id', id);
    form.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

    fetch('?page=websites&action=test', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            const toast   = document.getElementById('testToast');
            const body    = document.getElementById('testToastBody');
            const bsToast = bootstrap.Toast.getOrCreateInstance(toast);

            toast.classList.remove('bg-success', 'bg-danger', 'text-white');
            toast.classList.add(data.success ? 'bg-success' : 'bg-danger', 'text-white');
            body.textContent = data.message;
            bsToast.show();
        })
        .catch(e => alert('<?= __('websites.test_error') ?>' + e.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = original;
        });
}
</script>
