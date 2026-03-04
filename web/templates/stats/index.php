<?php
/**
 * Statistics dashboard template.
 * Variables: $period, $dateFrom, $dateTo, $articlesByStatus, $articlesByChannel,
 *            $aiUsageByDay, $aiUsageByChannel, $aiTotals, $pipelineStats, $topSources, $topClusters
 */

use function NewsBot\Web\Helpers\__;

// Build export URL with current filters
$exportParams = http_build_query([
    'page' => 'stats',
    'action' => 'export',
    'period' => $period,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-bar-chart"></i> <?= __('stats.title') ?></h2>
    <div class="dropdown">
        <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i> <?= __('stats.export_csv') ?>
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="?<?= $exportParams ?>&report=articles"><?= __('stats.export_articles') ?></a></li>
            <li><a class="dropdown-item" href="?<?= $exportParams ?>&report=ai_usage"><?= __('stats.export_ai_usage') ?></a></li>
            <li><a class="dropdown-item" href="?<?= $exportParams ?>&report=publications"><?= __('stats.export_publications') ?></a></li>
        </ul>
    </div>
</div>

<!-- Period filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="stats">

            <div class="col-auto">
                <label class="form-label"><?= __('stats.period') ?></label>
                <select name="period" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>><?= __('stats.period_today') ?></option>
                    <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>><?= __('stats.period_7days') ?></option>
                    <option value="30days" <?= $period === '30days' ? 'selected' : '' ?>><?= __('stats.period_30days') ?></option>
                    <option value="alltime" <?= $period === 'alltime' ? 'selected' : '' ?>><?= __('stats.period_all') ?></option>
                    <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>><?= __('stats.period_custom') ?></option>
                </select>
            </div>

            <div class="col-auto custom-dates" style="<?= $period !== 'custom' ? 'display:none' : '' ?>">
                <label class="form-label"><?= __('stats.date_from') ?></label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($dateFrom) ?>">
            </div>

            <div class="col-auto custom-dates" style="<?= $period !== 'custom' ? 'display:none' : '' ?>">
                <label class="form-label"><?= __('stats.date_to') ?></label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($dateTo) ?>">
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> <?= __('stats.btn_apply') ?>
                </button>
            </div>

            <div class="col-auto">
                <span class="text-muted small">
                    <?= __('stats.period_range') ?>: <?= htmlspecialchars($dateFrom) ?> — <?= htmlspecialchars($dateTo) ?>
                </span>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- AI Totals -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cpu"></i> <?= __('stats.ai_period') ?></h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h3 class="mb-0"><?= number_format((int)$aiTotals['total_calls']) ?></h3>
                        <small class="text-muted"><?= __('stats.calls') ?></small>
                    </div>
                    <div class="col-6 mb-3">
                        <h3 class="mb-0 text-success">$<?= number_format((float)$aiTotals['total_cost'], 2) ?></h3>
                        <small class="text-muted"><?= __('stats.cost') ?></small>
                    </div>
                    <div class="col-6">
                        <h5 class="mb-0"><?= number_format((int)$aiTotals['total_input_tokens']) ?></h5>
                        <small class="text-muted"><?= __('stats.input_tokens') ?></small>
                    </div>
                    <div class="col-6">
                        <h5 class="mb-0"><?= number_format((int)$aiTotals['total_output_tokens']) ?></h5>
                        <small class="text-muted"><?= __('stats.output_tokens') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Articles by Status -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-newspaper"></i> <?= __('stats.articles_by_status') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($articlesByStatus)): ?>
                <div class="p-3 text-muted"><?= __('stats.no_data') ?></div>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __('stats.col_status') ?></th>
                            <th class="text-end"><?= __('stats.col_count') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total = array_sum(array_column($articlesByStatus, 'count'));
                        foreach ($articlesByStatus as $row): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?= __('common.status.' . $row['status']) ?></span>
                            </td>
                            <td class="text-end">
                                <?= number_format((int)$row['count']) ?>
                                <small class="text-muted">(<?= $total > 0 ? round((int)$row['count'] / $total * 100, 1) : 0 ?>%)</small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary">
                            <th><?= __('stats.total') ?></th>
                            <th class="text-end"><?= number_format($total) ?></th>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pipeline Stats -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-speedometer2"></i> <?= __('stats.pipeline_avg') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pipelineStats['byStep'])): ?>
                <div class="p-3 text-muted"><?= __('stats.no_data') ?></div>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __('stats.col_step') ?></th>
                            <th class="text-end"><?= __('stats.col_avg_time') ?></th>
                            <th class="text-end"><?= __('stats.col_runs') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pipelineStats['byStep'] as $step): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($step['step']) ?></code></td>
                            <td class="text-end"><?= number_format((float)$step['avg_duration'], 1) ?>s</td>
                            <td class="text-end"><?= number_format((int)$step['runs']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Articles by Channel -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-megaphone"></i> <?= __('stats.articles_by_channel') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($articlesByChannel)): ?>
                <div class="p-3 text-muted"><?= __('stats.no_data') ?></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?= __('stats.col_channel') ?></th>
                                <th class="text-end"><?= __('stats.col_total') ?></th>
                                <th class="text-end"><?= __('stats.col_published') ?></th>
                                <th class="text-end"><?= __('stats.col_percent') ?></th>
                                <th class="text-end"><?= __('stats.col_avg_score') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articlesByChannel as $row): ?>
                            <tr>
                                <td>
                                    <a href="?page=channels&action=edit&id=<?= (int)$row['id'] ?>">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </a>
                                </td>
                                <td class="text-end"><?= number_format((int)$row['total_versions']) ?></td>
                                <td class="text-end text-success"><?= number_format((int)$row['published']) ?></td>
                                <td class="text-end"><?= $row['publish_rate'] ?? 0 ?>%</td>
                                <td class="text-end"><?= $row['avg_validation_score'] ?? '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- AI Usage by Channel -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cpu"></i> <?= __('stats.ai_by_channel') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($aiUsageByChannel)): ?>
                <div class="p-3 text-muted"><?= __('stats.no_data') ?></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?= __('stats.col_channel') ?></th>
                                <th class="text-end"><?= __('stats.col_calls') ?></th>
                                <th class="text-end"><?= __('stats.col_tokens') ?></th>
                                <th class="text-end"><?= __('stats.col_cost') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aiUsageByChannel as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['channel_name']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['calls']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['input_tokens'] + (int)$row['output_tokens']) ?></td>
                                <td class="text-end text-success">$<?= number_format((float)$row['cost'], 3) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- AI Usage by Day -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar3"></i> <?= __('stats.ai_by_day') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($aiUsageByDay)): ?>
                <div class="p-3 text-muted"><?= __('stats.no_data') ?></div>
                <?php else: ?>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th><?= __('stats.col_date') ?></th>
                                <th class="text-end"><?= __('stats.col_calls') ?></th>
                                <th class="text-end"><?= __('stats.col_input') ?></th>
                                <th class="text-end"><?= __('stats.col_output') ?></th>
                                <th class="text-end"><?= __('stats.col_cost') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aiUsageByDay as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['calls']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['input_tokens']) ?></td>
                                <td class="text-end"><?= number_format((int)$row['output_tokens']) ?></td>
                                <td class="text-end text-success">$<?= number_format((float)$row['cost'], 3) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Sources -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-globe"></i> <?= __('stats.top_sources') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topSources)): ?>
                <div class="p-3 text-muted"><?= __('stats.no_data') ?></div>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __('stats.col_source') ?></th>
                            <th class="text-end"><?= __('stats.col_articles') ?></th>
                            <th class="text-end"><?= __('stats.col_published') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topSources as $row): ?>
                        <tr>
                            <td>
                                <a href="?page=sources&action=edit&id=<?= (int)$row['id'] ?>">
                                    <?= htmlspecialchars($row['name']) ?>
                                </a>
                            </td>
                            <td class="text-end"><?= number_format((int)$row['article_count']) ?></td>
                            <td class="text-end text-success"><?= number_format((int)$row['published_count']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Clusters -->
<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> <?= __('stats.top_clusters') ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topClusters)): ?>
                <div class="p-3 text-muted"><?= __('stats.no_clusters') ?></div>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= __('stats.col_id') ?></th>
                            <th><?= __('stats.col_primary_title') ?></th>
                            <th class="text-end"><?= __('stats.col_duplicates') ?></th>
                            <th><?= __('stats.col_created') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topClusters as $row): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td class="truncate" style="max-width: 500px;">
                                <?= htmlspecialchars(mb_substr($row['primary_title'] ?? 'N/A', 0, 80)) ?>
                                <?= mb_strlen($row['primary_title'] ?? '') > 80 ? '...' : '' ?>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-warning text-dark"><?= (int)$row['member_count'] ?></span>
                            </td>
                            <td class="small text-muted"><?= $row['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomDates(value) {
    const customDates = document.querySelectorAll('.custom-dates');
    customDates.forEach(el => {
        el.style.display = value === 'custom' ? '' : 'none';
    });
}
</script>
