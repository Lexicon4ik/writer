<?php
/**
 * Article view template.
 * Variables: $article, $source, $versions, $statusHistory, $cluster, $clusterArticles
 */

use function NewsBot\Web\Helpers\__;

// Status badges
$statusBadges = [
    'fetched' => 'bg-secondary',
    'scraped' => 'bg-info text-dark',
    'processed' => 'bg-primary',
    'validated' => 'bg-success',
    'published' => 'bg-success',
    'edited' => 'bg-success',
    'manual_review' => 'bg-warning text-dark',
    'duplicate' => 'bg-secondary',
    'failed' => 'bg-danger',
    'scrape_failed' => 'bg-danger',
    'process_failed' => 'bg-danger',
    'cancelled' => 'bg-dark',
    'deleted' => 'bg-dark',
    'expired' => 'bg-secondary',
    'pending' => 'bg-info text-dark',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <i class="bi bi-newspaper"></i> <?= __('articles.article_num', ['id' => (int)$article->id]) ?>
        <span class="badge <?= $statusBadges[$article->status] ?? 'bg-secondary' ?> ms-2">
            <?= __('common.status.' . $article->status) ?>
        </span>
    </h2>
    <a href="?page=articles" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('articles.btn_back') ?>
    </a>
</div>

<div class="row">
    <!-- Left column: Article info -->
    <div class="col-lg-8">
        <!-- Original content -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-text"></i> <?= __('articles.original') ?></h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th style="width: 150px;"><?= __('articles.rss_title') ?></th>
                        <td><?= htmlspecialchars($article->rss_title ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th><?= __('articles.scraped_title') ?></th>
                        <td><?= htmlspecialchars($article->scraped_title ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th><?= __('articles.rss_description') ?></th>
                        <td>
                            <div class="text-muted small" style="max-height: 100px; overflow-y: auto;">
                                <?= nl2br(htmlspecialchars(mb_substr($article->rss_description ?? '', 0, 500))) ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?= __('articles.scraped_text') ?></th>
                        <td>
                            <div class="text-muted small" style="max-height: 200px; overflow-y: auto;">
                                <?= nl2br(htmlspecialchars(mb_substr($article->scraped_text ?? '', 0, 2000))) ?>
                                <?= mb_strlen($article->scraped_text ?? '') > 2000 ? '...' : '' ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?= __('articles.url') ?></th>
                        <td>
                            <a href="<?= htmlspecialchars($article->url ?? '') ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars(mb_substr($article->url ?? '', 0, 80)) ?>...
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th><?= __('articles.source') ?></th>
                        <td>
                            <?php if ($source): ?>
                                <a href="?page=sources&action=edit&id=<?= (int)$source->id ?>">
                                    <?= htmlspecialchars($source->name) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?= __('articles.importance') ?></th>
                        <td>
                            <?php
                            $importanceScores = array_filter(array_column($versions, 'importance_score'));
                            if (!empty($importanceScores)) {
                                $avgImportance = round(array_sum($importanceScores) / count($importanceScores), 1);
                                echo $avgImportance;
                                if (count($importanceScores) > 1) {
                                    echo ' <small class="text-muted">(' . __('articles.avg_from_versions', ['count' => count($importanceScores)]) . ')</small>';
                                }
                            } else {
                                echo '<span class="text-muted">N/A</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?= __('articles.created') ?></th>
                        <td><?= $article->created_at ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Versions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-layers"></i> <?= __('articles.versions') ?> (<?= count($versions) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($versions)): ?>
                <div class="p-3 text-muted"><?= __('articles.no_versions') ?></div>
                <?php else: ?>
                <div class="accordion" id="versionsAccordion">
                    <?php foreach ($versions as $i => $v): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#version<?= (int)$v['id'] ?>">
                                <span class="badge <?= $statusBadges[$v['status']] ?? 'bg-secondary' ?> me-2">
                                    <?= __('common.status.' . $v['status']) ?>
                                </span>
                                <strong><?= htmlspecialchars($v['channel_name'] ?? 'Unknown') ?></strong>
                                <?php if (!empty($v['telegram_message_id'])): ?>
                                    <span class="badge bg-primary ms-2" title="Telegram Message ID">
                                        <i class="bi bi-telegram"></i> <?= (int)$v['telegram_message_id'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($v['validation_score']): ?>
                                    <span class="badge bg-info text-dark ms-2">
                                        Score: <?= number_format((float)$v['validation_score'], 1) ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </h2>
                        <div id="version<?= (int)$v['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><?= __('articles.version_data') ?></h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <th><?= __('articles.field_title') ?></th>
                                                <td><?= htmlspecialchars($v['title'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('articles.field_body') ?></th>
                                                <td>
                                                    <div class="small" style="max-height: 1000px; overflow-y: auto;">
                                                        <?= nl2br(htmlspecialchars(mb_substr($v['body'] ?? '', 0, 500))) ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php if (!empty($v['hashtags'])): ?>
                                            <tr>
                                                <th><?= __('articles.hashtags') ?></th>
                                                <td><code><?=str_replace(',', ', ', htmlspecialchars($v['hashtags'])) ?></code></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th><?= __('articles.created') ?></th>
                                                <td><?= $v['created_at'] ?></td>
                                            </tr>
                                            <?php if ($v['published_at']): ?>
                                            <tr>
                                                <th><?= __('articles.col_published') ?></th>
                                                <td><?= $v['published_at'] ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><?= __('articles.post_preview') ?></h6>
                                        <div class="border rounded p-3 bg-light" style="max-width: 400px; font-size: 14px;">
                                            <?= nl2br($v['post_preview']) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Version actions -->
                                <div class="mt-3 d-flex gap-2">
                                    <?php if (in_array($v['status'], ['validated', 'manual_review', 'pending'])): ?>
                                    <form method="post" action="?page=articles&action=publish_manual" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="id" value="<?= (int)$article->id ?>">
                                        <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success"
                                                onclick="return confirm(<?= htmlspecialchars(json_encode(__('articles.confirm_publish')), ENT_QUOTES) ?>)">
                                            <i class="bi bi-send"></i> <?= __('articles.btn_publish') ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if (in_array($v['status'], ['published', 'edited'])): ?>
                                    <a href="?page=articles&action=edit_post&version_id=<?= (int)$v['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> <?= __('articles.btn_edit') ?>
                                    </a>
                                    <form method="post" action="?page=articles&action=delete_post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm(<?= htmlspecialchars(json_encode(__('articles.confirm_delete_tg')), ENT_QUOTES) ?>)">
                                            <i class="bi bi-trash"></i> <?= __('articles.btn_delete_tg') ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status History -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> <?= __('articles.status_history') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($statusHistory)): ?>
                <div class="p-3 text-muted"><?= __('articles.no_history') ?></div>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __('articles.col_time') ?></th>
                            <th><?= __('articles.col_old_status') ?></th>
                            <th></th>
                            <th><?= __('articles.col_new_status') ?></th>
                            <th><?= __('articles.col_details') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statusHistory as $log): ?>
                        <tr>
                            <td class="small"><?= date('d.m H:i:s', strtotime($log->created_at)) ?></td>
                            <td>
                                <span class="badge <?= $statusBadges[$log->old_status] ?? 'bg-secondary' ?>">
                                    <?= $log->old_status ? __('common.status.' . $log->old_status) : 'N/A' ?>
                                </span>
                            </td>
                            <td><i class="bi bi-arrow-right"></i></td>
                            <td>
                                <span class="badge <?= $statusBadges[$log->new_status] ?? 'bg-secondary' ?>">
                                    <?= __('common.status.' . $log->new_status) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $details = $log->getDetails();
                                if (!empty($details)): ?>
                                    <code class="small"><?=str_replace(',&quot', ', &quot', htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE))) ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right column: Actions & Cluster -->
    <div class="col-lg-4">
        <!-- Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear"></i> <?= __('articles.actions') ?></h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (!in_array($article->status, ['published', 'cancelled'])): ?>
                    <form method="post" action="?page=articles&action=reprocess">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= (int)$article->id ?>">
                        <button type="submit" class="btn btn-outline-primary w-100"
                                onclick="return confirm(<?= htmlspecialchars(json_encode(__('articles.confirm_reprocess')), ENT_QUOTES) ?>)">
                            <i class="bi bi-arrow-repeat"></i> <?= __('articles.bulk_reprocess') ?>
                        </button>
                    </form>
                    <form method="post" action="?page=articles&action=cancel">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= (int)$article->id ?>">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm(<?= htmlspecialchars(json_encode(__('articles.confirm_cancel')), ENT_QUOTES) ?>)">
                            <i class="bi bi-x-circle"></i> <?= __('articles.btn_cancel') ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="text-muted small">
                        <?= __('articles.status_no_actions', ['status' => __('common.status.' . $article->status)]) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cluster info -->
        <?php if ($cluster): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> <?= __('articles.duplicate_cluster') ?></h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    <?= __('articles.cluster_hash') ?>: <code><?= htmlspecialchars(mb_substr($cluster['cluster_hash'] ?? '', 0, 16)) ?>...</code>
                </p>
                <p class="small text-muted mb-2">
                    <?= __('articles.cluster_created') ?>: <?= $cluster['created_at'] ?>
                </p>

                <h6><?= __('articles.articles_in_cluster') ?> (<?= count($clusterArticles) ?>):</h6>
                <ul class="list-unstyled small">
                    <?php foreach ($clusterArticles as $ca): ?>
                    <li class="mb-1">
                        <?php if ($ca['is_primary']): ?>
                            <i class="bi bi-star-fill text-warning" title="<?= __('articles.primary') ?>"></i>
                        <?php endif; ?>
                        <?php if ((int)$ca['id'] === (int)$article->id): ?>
                            <strong>#<?= (int)$ca['id'] ?></strong> (<?= __('articles.current') ?>)
                        <?php else: ?>
                            <a href="?page=articles&action=view&id=<?= (int)$ca['id'] ?>">
                                #<?= (int)$ca['id'] ?>
                            </a>
                        <?php endif; ?>
                        <span class="badge <?= $statusBadges[$ca['status']] ?? 'bg-secondary' ?> ms-1">
                            <?= __('common.status.' . $ca['status']) ?>
                        </span>
                        <br>
                        <span class="text-muted"><?= htmlspecialchars(mb_substr($ca['title'] ?? '', 0, 50)) ?>...</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick info -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> <?= __('articles.info') ?></h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small mb-0">
                    <li><strong>ID:</strong> <?= (int)$article->id ?></li>
                    <li><strong><?= __('articles.source_id') ?>:</strong> <?= (int)$article->source_id ?></li>
                    <li><strong><?= __('articles.feed_id') ?>:</strong> <?= $article->feed_id ?? 'N/A' ?></li>
                    <li><strong><?= __('articles.cluster_id') ?>:</strong> <?= $article->cluster_id ?? 'N/A' ?></li>
                    <li><strong><?= __('articles.url_hash') ?>:</strong> <code><?= htmlspecialchars(mb_substr($article->url_hash ?? '', 0, 16)) ?>...</code></li>
                    <li><strong><?= __('articles.updated') ?>:</strong> <?= $article->updated_at ?? 'N/A' ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
