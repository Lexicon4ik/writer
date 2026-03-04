<?php
/**
 * Channel edit/create form template.
 * Variables: $channel, $bots, $sources, $linkedSourceIds, $timezones
 */

use function NewsBot\Web\Helpers\__;

$isEdit = $channel !== null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-megaphone"></i> <?= $isEdit ? __('channels.edit') : __('channels.create') ?></h2>
    <a href="?page=channels" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('common.btn.back') ?>
    </a>
</div>

<form method="post" action="?page=channels&action=save">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="id" value="<?= $isEdit ? (int)$channel->id : 0 ?>">

    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-gear"></i> <?= __('settings.group_general') ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label"><?= __('channels.field_name') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?= htmlspecialchars($isEdit ? ($channel->name ?? '') : '') ?>"
                                       placeholder="Thailand News">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bot_id" class="form-label"><?= __('channels.field_bot') ?> <span class="text-danger">*</span></label>
                                <select class="form-select" id="bot_id" name="bot_id" required>
                                    <option value=""><?= __('channels.msg_select_bot') ?></option>
                                    <?php foreach ($bots as $bot): ?>
                                    <option value="<?= (int)$bot['id'] ?>"
                                            <?= ($isEdit && (int)$channel->bot_id === (int)$bot['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bot['name']) ?>
                                        (<?= $bot['type'] ?>)
                                        <?= $bot['status'] !== 'active' ? ' [' . __('common.status.paused') . ']' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="chat_id" class="form-label"><?= __('channels.field_chat_id') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="chat_id" name="chat_id" required
                                       value="<?= htmlspecialchars($isEdit ? ($channel->chat_id ?? '') : '') ?>"
                                       placeholder="@channel_name or -100123456789">
                                <div class="form-text"><?= __('channels.field_chat_id_help') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="timezone" class="form-label"><?= __('settings.timezone') ?></label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <?php foreach ($timezones as $tz => $label): ?>
                                    <option value="<?= htmlspecialchars($tz) ?>"
                                            <?= ($isEdit && ($channel->timezone ?? 'UTC') === $tz) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="topic" class="form-label">Topic</label>
                                <input type="text" class="form-control" id="topic" name="topic"
                                       value="<?= htmlspecialchars($isEdit ? ($channel->topic ?? '') : '') ?>"
                                       placeholder="Thailand, Bangkok, Tourism">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="language" class="form-label"><?= __('channels.field_language') ?></label>
                                <select class="form-select" id="language" name="language">
                                    <option value="ru" <?= ($isEdit && ($channel->language ?? 'ru') === 'ru') ? 'selected' : '' ?>>Русский</option>
                                    <option value="en" <?= ($isEdit && $channel->language === 'en') ? 'selected' : '' ?>>English</option>
                                    <option value="th" <?= ($isEdit && $channel->language === 'th') ? 'selected' : '' ?>>ภาษาไทย</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label"><?= __('channels.field_status') ?></label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= (!$isEdit || $channel->status === 'active') ? 'selected' : '' ?>><?= __('common.status.active') ?></option>
                            <option value="paused" <?= ($isEdit && $channel->status === 'paused') ? 'selected' : '' ?>><?= __('common.status.paused') ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- AI Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-robot"></i> <?= __('settings.group_ai') ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="ai_prompt" class="form-label"><?= __('channels.field_prompt') ?></label>
                        <textarea class="form-control font-monospace" id="ai_prompt" name="ai_prompt" rows="8"
                                  placeholder="<?= __('channels.field_prompt_help') ?>"><?= htmlspecialchars($isEdit ? ($channel->ai_prompt ?? '') : '') ?></textarea>
                        <div class="form-text"><?= __('channels.field_prompt_help') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="validation_prompt" class="form-label">Validation Prompt</label>
                        <textarea class="form-control font-monospace" id="validation_prompt" name="validation_prompt" rows="4"
                                  placeholder="AI validation instructions..."><?= htmlspecialchars($isEdit ? ($channel->validation_prompt ?? '') : '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ai_model" class="form-label"><?= __('settings.ai_model') ?></label>
                                <input type="text" class="form-control" id="ai_model" name="ai_model"
                                       value="<?= htmlspecialchars($isEdit ? ($channel->ai_model ?? '') : '') ?>"
                                       placeholder="Leave empty for global">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ai_temperature" class="form-label"><?= __('settings.ai_temperature') ?></label>
                                <input type="number" class="form-control" id="ai_temperature" name="ai_temperature"
                                       step="0.1" min="0" max="2"
                                       value="<?= $isEdit && $channel->ai_temperature !== null ? htmlspecialchars((string)$channel->ai_temperature) : '' ?>"
                                       placeholder="Global (0.4)">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="post_template" class="form-label"><?= __('channels.field_post_format') ?></label>
                        <textarea class="form-control font-monospace" id="post_template" name="post_template" rows="4"
                                  placeholder="{title}&#10;&#10;{body}&#10;&#10;{source}"><?= htmlspecialchars($isEdit ? ($channel->post_template ?? '') : '') ?></textarea>
                        <div class="form-text">{title}, {body}, {source}, {url}, {date}</div>
                    </div>
                </div>
            </div>

            <!-- Publishing Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-send"></i> <?= __('settings.group_publishing') ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="publish_interval_min" class="form-label"><?= __('channels.field_post_interval') ?></label>
                                <input type="number" class="form-control" id="publish_interval_min" name="publish_interval_min"
                                       min="1" max="1440"
                                       value="<?= $isEdit ? (int)($channel->publish_interval_min ?? 5) : 5 ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="max_per_run" class="form-label">Max per run</label>
                                <input type="number" class="form-control" id="max_per_run" name="max_per_run"
                                       min="1" max="50"
                                       value="<?= $isEdit ? (int)($channel->max_per_run ?? 3) : 3 ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="max_per_day" class="form-label">Max per day</label>
                                <input type="number" class="form-control" id="max_per_day" name="max_per_day"
                                       min="1" max="500"
                                       value="<?= $isEdit ? (int)($channel->max_per_day ?? 20) : 20 ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="min_importance_score" class="form-label">Min Importance</label>
                                <input type="number" class="form-control" id="min_importance_score" name="min_importance_score"
                                       min="1" max="10"
                                       value="<?= $isEdit ? (int)($channel->min_importance_score ?? 1) : 1 ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="active_hours_start" class="form-label">Active hours: start</label>
                                <input type="time" class="form-control" id="active_hours_start" name="active_hours_start"
                                       value="<?= htmlspecialchars($isEdit ? substr($channel->active_hours_start ?? '08:00:00', 0, 5) : '08:00') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="active_hours_end" class="form-label">Active hours: end</label>
                                <input type="time" class="form-control" id="active_hours_end" name="active_hours_end"
                                       value="<?= htmlspecialchars($isEdit ? substr($channel->active_hours_end ?? '22:00:00', 0, 5) : '22:00') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="use_images" name="use_images"
                               <?= (!$isEdit || $channel->use_images) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="use_images">
                            Use images
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="manual_review_enabled" name="manual_review_enabled"
                               <?= ($isEdit && $channel->manual_review_enabled) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="manual_review_enabled">
                            <strong><?= __('dashboard.manual_review') ?></strong>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Validation Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-check-circle"></i> Validation
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="min_validation_score" class="form-label">Min score</label>
                                <input type="number" class="form-control" id="min_validation_score" name="min_validation_score"
                                       min="1" max="10"
                                       value="<?= $isEdit ? (int)($channel->min_validation_score ?? 6) : 6 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="validation_mode" class="form-label">Mode</label>
                                <select class="form-select" id="validation_mode" name="validation_mode">
                                    <option value="never" <?= ($isEdit && $channel->validation_mode === 'never') ? 'selected' : '' ?>>Never</option>
                                    <option value="sample" <?= (!$isEdit || ($channel->validation_mode ?? 'sample') === 'sample') ? 'selected' : '' ?>>Sample (%)</option>
                                    <option value="importance_threshold" <?= ($isEdit && $channel->validation_mode === 'importance_threshold') ? 'selected' : '' ?>>By importance</option>
                                    <option value="always" <?= ($isEdit && $channel->validation_mode === 'always') ? 'selected' : '' ?>>Always</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="validation_sample_pct" class="form-label">Sample %</label>
                                <input type="number" class="form-control" id="validation_sample_pct" name="validation_sample_pct"
                                       min="1" max="100"
                                       value="<?= $isEdit ? (int)($channel->validation_sample_pct ?? 100) : 100 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="validation_importance_min" class="form-label">Min importance</label>
                                <input type="number" class="form-control" id="validation_importance_min" name="validation_importance_min"
                                       min="1" max="10"
                                       value="<?= $isEdit ? (int)($channel->validation_importance_min ?? 1) : 1 ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Sources -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-globe"></i> <?= __('common.nav.sources') ?>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($sources)): ?>
                        <p class="text-muted mb-0"><?= __('sources.msg_no_sources') ?></p>
                    <?php else: ?>
                        <?php foreach ($sources as $src): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="sources[]"
                                   value="<?= (int)$src['id'] ?>"
                                   id="source_<?= (int)$src['id'] ?>"
                                   <?= in_array((int)$src['id'], $linkedSourceIds) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="source_<?= (int)$src['id'] ?>">
                                <?= htmlspecialchars($src['name']) ?>
                                <?php if ($src['status'] !== 'active'): ?>
                                    <span class="badge bg-warning text-dark"><?= __('common.status.paused') ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <!-- Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> Info
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5"><?= __('common.label.id') ?>:</dt>
                        <dd class="col-7"><?= (int)$channel->id ?></dd>

                        <dt class="col-5"><?= __('common.label.created_at') ?>:</dt>
                        <dd class="col-7"><?= date('Y-m-d H:i', strtotime($channel->created_at ?? 'now')) ?></dd>

                        <dt class="col-5"><?= __('common.label.updated_at') ?>:</dt>
                        <dd class="col-7"><?= date('Y-m-d H:i', strtotime($channel->updated_at ?? 'now')) ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Reprocess -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-arrow-repeat"></i> <?= __('articles.reprocess') ?>
                </div>
                <div class="card-body">
                    <form method="post" action="?page=channels&action=reprocess_recent" class="d-flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= (int)$channel->id ?>">
                        <select name="days" class="form-select form-select-sm" style="width: auto;">
                            <option value="1">1 day</option>
                            <option value="3" selected>3 days</option>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-arrow-repeat"></i> <?= __('articles.reprocess') ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Submit -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= __('common.btn.save') ?>
                        </button>
                        <a href="?page=channels" class="btn btn-outline-secondary"><?= __('common.btn.cancel') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.font-monospace {
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 13px;
}
</style>
