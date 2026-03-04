<?php
/**
 * Dashboard template.
 * Variables: $stats, $todayArticles, $recentPublished, $manualReviewCount, $aiUsage, $pipelineRuns, $recentParserRuns
 */

use function NewsBot\Web\Helpers\__;

// Helper function for status badges
function statusBadge(string $status): string {
    $class = match($status) {
        'active', 'published', 'validated' => 'bg-success',
        'paused', 'processing', 'pending', 'publishing' => 'bg-warning text-dark',
        'failed', 'cancelled', 'scrape_failed', 'process_failed' => 'bg-danger',
        'expired', 'duplicate', 'skipped' => 'bg-secondary',
        'manual_review' => 'bg-info',
        default => 'bg-secondary'
    };
    // Use translation if available
    $label = __('common.status.' . $status);
    if ($label === 'common.status.' . $status) {
        $label = $status; // fallback to raw status
    }
    return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
}

// Calculate AI usage color
$aiUsageClass = 'bg-success';
if (($aiUsage['percent'] ?? 0) > 80) {
    $aiUsageClass = 'bg-danger';
} elseif (($aiUsage['percent'] ?? 0) > 50) {
    $aiUsageClass = 'bg-warning';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><?= __('dashboard.title') ?></h2>
    <small class="text-muted">
        <i class="bi bi-clock"></i> <?= date('Y-m-d H:i:s') ?> UTC
    </small>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h3 class="mb-0"><?= (int)($stats['bots'] ?? 0) ?></h3>
                <p class="mb-0 small"><?= __('dashboard.active_bots') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h3 class="mb-0"><?= (int)($stats['channels'] ?? 0) ?></h3>
                <p class="mb-0 small"><?= __('dashboard.active_channels') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h3 class="mb-0"><?= (int)($stats['sources'] ?? 0) ?></h3>
                <p class="mb-0 small"><?= __('dashboard.active_sources') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body">
                <h3 class="mb-0"><?= (int)($stats['feeds'] ?? 0) ?></h3>
                <p class="mb-0 small"><?= __('dashboard.active_feeds') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-dark text-white h-100">
            <div class="card-body">
                <h3 class="mb-0"><?= (int)($stats['parsers'] ?? 0) ?></h3>
                <p class="mb-0 small"><?= __('dashboard.active_parsers') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card <?= $manualReviewCount > 0 ? 'bg-warning' : 'bg-light' ?> h-100">
            <div class="card-body">
                <h3 class="mb-0"><?= $manualReviewCount ?></h3>
                <p class="mb-0 small"><?= __('dashboard.manual_review') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Today's Articles by Status -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-calendar-day"></i> <?= __('dashboard.today_articles') ?></h6>
            </div>
            <div class="card-body">
                <?php if (empty($todayArticles) || ($todayArticles['total'] ?? 0) === 0): ?>
                    <p class="text-muted mb-0"><?= __('dashboard.no_articles_today') ?></p>
                <?php else: ?>
                    <div class="d-flex justify-content-between mb-2">
                        <strong><?= __('common.label.total') ?>:</strong>
                        <span class="badge bg-primary"><?= $todayArticles['total'] ?? 0 ?></span>
                    </div>
                    <?php foreach ($todayArticles as $status => $count): ?>
                        <?php if ($status !== 'total'): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span style="width: 150px;"><?= __('common.status.' . $status) ?>:</span>
                            <?= statusBadge($status) ?> <?= $count ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- AI Usage -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-cpu"></i> <?= __('dashboard.ai_usage') ?></h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= __('dashboard.budget_used') ?>:</span>
                        <strong>$<?= number_format($aiUsage['cost'] ?? 0, 4) ?> / $<?= number_format($aiUsage['budget'] ?? 10, 2) ?></strong>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar <?= $aiUsageClass ?>" role="progressbar"
                             style="width: <?= min(100, $aiUsage['percent'] ?? 0) ?>%"
                             aria-valuenow="<?= $aiUsage['percent'] ?? 0 ?>" aria-valuemin="0" aria-valuemax="100">
                            <?= $aiUsage['percent'] ?? 0 ?>%
                        </div>
                    </div>
                </div>
                <div class="row text-center small">
                    <div class="col-4">
                        <div class="fw-bold"><?= number_format($aiUsage['calls'] ?? 0) ?></div>
                        <div class="text-muted"><?= __('dashboard.calls') ?></div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold"><?= number_format($aiUsage['input_tokens'] ?? 0) ?></div>
                        <div class="text-muted"><?= __('dashboard.input_tokens') ?></div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold"><?= number_format($aiUsage['output_tokens'] ?? 0) ?></div>
                        <div class="text-muted"><?= __('dashboard.output_tokens') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pipeline Status -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-activity"></i> <?= __('dashboard.pipeline_status') ?></h6>
            </div>
            <div class="card-body">
                <?php
                $steps = ['fetch', 'scrape', 'process', 'publish'];
                foreach ($steps as $step):
                    $info = $pipelineRuns[$step] ?? null;
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= __('dashboard.' . $step) ?>:</span>
                    <span style="width:110px">
                        <?php if ($info): ?>
                            <small class="text-muted"><?= date('H:i', strtotime($info['last_run'])) ?></small>
                            <span class="badge bg-success"><?= $info['success'] ?></span>
                            <?php if ($info['errors'] > 0): ?>
                            <span class="badge bg-danger"><?= $info['errors'] ?> <?= __('dashboard.errors') ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Published -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-send-check"></i> <?= __('dashboard.recent_published') ?></h6>
                <a href="?page=articles&status=published" class="btn btn-sm btn-outline-primary"><?= __('common.btn.view_all') ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentPublished)): ?>
                <p class="p-3 mb-0 text-muted"><?= __('dashboard.no_published_yet') ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th><?= __('dashboard.col_title') ?></th>
                                <th><?= __('dashboard.col_channel') ?></th>
                                <th><?= __('dashboard.col_source') ?></th>
                                <th><?= __('dashboard.col_published') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPublished as $article): ?>
                            <tr>
                                <td class="truncate" style="max-width: 250px;">
                                    <a href="?page=articles&action=view&id=<?= (int)$article['article_id'] ?>"
                                       title="<?= htmlspecialchars($article['processed_title'] ?? $article['rss_title'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_substr($article['processed_title'] ?? $article['rss_title'] ?? '(no title)', 0, 60)) ?>
                                        <?= strlen($article['processed_title'] ?? $article['rss_title'] ?? '') > 60 ? '...' : '' ?>
                                    </a>
                                </td>
                                <td><small><?= htmlspecialchars($article['channel_name'] ?? '') ?></small></td>
                                <td><small><?= htmlspecialchars($article['source_name'] ?? '') ?></small></td>
                                <td><small><?= $article['published_at'] ? date('H:i', strtotime($article['published_at'])) : '-' ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Parser Runs -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-code-slash"></i> <?= __('dashboard.recent_parser_runs') ?></h6>
                <a href="?page=parsers" class="btn btn-sm btn-outline-primary"><?= __('common.btn.manage') ?></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentParserRuns)): ?>
                <p class="p-3 mb-0 text-muted"><?= __('dashboard.no_parser_runs') ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?= __('dashboard.col_source') ?></th>
                                <th><?= __('dashboard.col_found') ?></th>
                                <th><?= __('dashboard.col_new') ?></th>
                                <th><?= __('dashboard.col_status') ?></th>
                                <th><?= __('dashboard.col_time') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentParserRuns as $run): ?>
                            <tr>
                                <td><small><?= htmlspecialchars($run['source_name'] ?? '') ?></small></td>
                                <td><?= (int)($run['articles_found'] ?? 0) ?></td>
                                <td><?= (int)($run['articles_new'] ?? 0) ?></td>
                                <td>
                                    <?php if (!empty($run['error_message'])): ?>
                                        <span class="badge bg-danger" title="<?= htmlspecialchars($run['error_message']) ?>">
                                            <?= __('dashboard.status_error') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= __('dashboard.status_ok') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= date('H:i', strtotime($run['started_at'] ?? 'now')) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-lightning"></i> <?= __('dashboard.quick_actions') ?></h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($manualReviewCount > 0): ?>
                    <a href="?page=articles&status=manual_review" class="btn btn-warning">
                        <i class="bi bi-eye"></i> <?= __('dashboard.review_articles', ['count' => $manualReviewCount]) ?>
                    </a>
                    <?php endif; ?>
                    <a href="?page=parsers" class="btn btn-outline-primary">
                        <i class="bi bi-code-slash"></i> <?= __('dashboard.manage_parsers') ?>
                    </a>
                    <a href="?page=settings" class="btn btn-outline-secondary">
                        <i class="bi bi-gear"></i> <?= __('common.nav.settings') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
