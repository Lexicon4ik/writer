<?php
/**
 * Bot edit/create form template.
 * Variables: $bot, $decryptedToken
 */

use function NewsBot\Web\Helpers\__;

$isEdit = $bot !== null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-robot"></i> <?= $isEdit ? __('bots.edit') : __('bots.create') ?></h2>
    <a href="?page=bots" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('common.btn.back') ?>
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="post" action="?page=bots&action=save">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="id" value="<?= $isEdit ? (int)$bot->id : 0 ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label"><?= __('bots.field_name') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?= htmlspecialchars($isEdit ? ($bot->name ?? '') : '') ?>"
                               placeholder="My Publishing Bot">
                        <div class="form-text"><?= __('bots.field_name_help') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="token" class="form-label">
                            <?= __('bots.field_token') ?> <?= !$isEdit ? '<span class="text-danger">*</span>' : '' ?>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="token" name="token"
                                   <?= !$isEdit ? 'required' : '' ?>
                                   value="<?= htmlspecialchars($decryptedToken ?? '') ?>"
                                   placeholder="123456789:ABCdefGHIjklmNOPqrstUVWxyz">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleTokenVisibility()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <div class="form-text"><?= __('bots.field_token_help') ?></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label"><?= __('common.label.type') ?></label>
                                <select class="form-select" id="type" name="type">
                                    <option value="publishing" <?= ($isEdit && $bot->type === 'publishing') ? 'selected' : '' ?>>
                                        Publishing
                                    </option>
                                    <option value="service" <?= ($isEdit && $bot->type === 'service') ? 'selected' : '' ?>>
                                        Service
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label"><?= __('bots.field_status') ?></label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?= (!$isEdit || $bot->status === 'active') ? 'selected' : '' ?>>
                                        <?= __('common.status.active') ?>
                                    </option>
                                    <option value="paused" <?= ($isEdit && $bot->status === 'paused') ? 'selected' : '' ?>>
                                        <?= __('common.status.paused') ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= __('common.btn.save') ?>
                        </button>
                        <a href="?page=bots" class="btn btn-outline-secondary"><?= __('common.btn.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> <?= __('common.label.description') ?>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-0">
                    <?= __('bots.msg_token_encrypted') ?>
                </p>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-activity"></i> Info
            </div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5"><?= __('common.label.id') ?>:</dt>
                    <dd class="col-7"><?= (int)$bot->id ?></dd>

                    <dt class="col-5"><?= __('common.label.created_at') ?>:</dt>
                    <dd class="col-7"><?= date('Y-m-d H:i', strtotime($bot->created_at ?? 'now')) ?></dd>

                    <dt class="col-5"><?= __('common.label.updated_at') ?>:</dt>
                    <dd class="col-7"><?= date('Y-m-d H:i', strtotime($bot->updated_at ?? 'now')) ?></dd>
                </dl>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleTokenVisibility() {
    const tokenInput = document.getElementById('token');
    const toggleIcon = document.getElementById('toggleIcon');

    if (tokenInput.type === 'password') {
        tokenInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        tokenInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
}
</script>
