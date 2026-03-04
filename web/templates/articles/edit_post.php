<?php
/**
 * Edit post template.
 * Variables: $version, $article, $channel
 */

use function NewsBot\Web\Helpers\__;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil-square"></i> <?= __('articles.edit_post') ?></h2>
    <a href="?page=articles&action=view&id=<?= (int)$article->id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('articles.btn_back') ?>
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <?= __('articles.version_for_channel') ?>: <strong><?= htmlspecialchars($channel->name) ?></strong>
                    <?php if ($version->status === 'published'): ?>
                        <span class="badge bg-success ms-2"><?= __('common.status.published') ?></span>
                        <span class="badge bg-primary ms-1">
                            <i class="bi bi-telegram"></i> #<?= (int)$version->telegram_message_id ?>
                        </span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="?page=articles&action=edit_post&version_id=<?= (int)$version->id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="version_id" value="<?= (int)$version->id ?>">

                    <div class="mb-3">
                        <label for="title" class="form-label"><?= __('articles.field_title') ?></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?= htmlspecialchars($version->title ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="body" class="form-label"><?= __('articles.field_body') ?></label>
                        <textarea class="form-control prompt" id="body" name="body" rows="12" required><?= htmlspecialchars($version->body ?? '') ?></textarea>
                        <div class="form-text">
                            <?= __('articles.tg_html_hint') ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            <?php if ($version->status === 'published'): ?>
                                <?= __('articles.btn_save_update') ?>
                            <?php else: ?>
                                <?= __('articles.btn_save') ?>
                            <?php endif; ?>
                        </button>
                        <a href="?page=articles&action=view&id=<?= (int)$article->id ?>" class="btn btn-outline-secondary">
                            <?= __('articles.btn_cancel_edit') ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> <?= __('articles.info') ?></h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small mb-0">
                    <li><strong><?= __('articles.article_id') ?>:</strong> <?= (int)$article->id ?></li>
                    <li><strong><?= __('articles.version_id') ?>:</strong> <?= (int)$version->id ?></li>
                    <li><strong><?= __('articles.channel') ?>:</strong> <?= htmlspecialchars($channel->name) ?></li>
                    <li><strong><?= __('common.label.status') ?>:</strong> <?= __('common.status.' . $version->status) ?></li>
                    <?php if ($version->validation_score): ?>
                    <li><strong><?= __('articles.validation_score') ?>:</strong> <?= number_format((float)$version->validation_score, 1) ?></li>
                    <?php endif; ?>
                    <li><strong><?= __('articles.created') ?>:</strong> <?= $version->created_at ?></li>
                    <?php if ($version->published_at): ?>
                    <li><strong><?= __('articles.col_published') ?>:</strong> <?= $version->published_at ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-text"></i> <?= __('articles.original_content') ?></h5>
            </div>
            <div class="card-body">
                <p class="small mb-1"><strong><?= __('articles.original_title') ?>:</strong></p>
                <p class="text-muted small"><?= htmlspecialchars($article->scraped_title ?? $article->rss_title ?? '') ?></p>

                <p class="small mb-1"><strong><?= __('articles.original_text') ?>:</strong></p>
                <div class="text-muted small" style="max-height: 200px; overflow-y: auto;">
                    <?= nl2br(htmlspecialchars(mb_substr($article->scraped_text ?? $article->rss_description ?? '', 0, 500))) ?>
                </div>
            </div>
        </div>
    </div>
</div>
