<?php
/**
 * Articles list template.
 * Variables: $articles, $pagination, $channels, $sources, $statuses, $filters
 */

use function NewsBot\Web\Helpers\__;

// Status badges
$statusBadges = [
    'fetched' => 'bg-secondary',
    'scraped' => 'bg-info text-dark',
    'processed' => 'bg-primary',
    'validated' => 'bg-success',
    'published' => 'bg-success',
    'manual_review' => 'bg-warning text-dark',
    'duplicate' => 'bg-secondary',
    'failed' => 'bg-danger',
    'scrape_failed' => 'bg-danger',
    'process_failed' => 'bg-danger',
    'cancelled' => 'bg-dark',
    'expired' => 'bg-secondary',
];

// Build query string for pagination
$queryParams = array_filter($filters);
$queryString = http_build_query($queryParams);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-newspaper"></i> <?= __('articles.title') ?></h2>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="page" value="articles">

            <div class="col-md-2">
                <label class="form-label"><?= __('articles.filter_status') ?></label>
                <select name="status" class="form-select form-select-sm">
                    <option value=""><?= __('articles.filter_all_statuses') ?></option>
                    <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"
                            <?= $filters['status'] === $s ? 'selected' : '' ?>>
                        <?= __('common.status.' . $s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label"><?= __('articles.filter_channel') ?></label>
                <select name="channel_id" class="form-select form-select-sm">
                    <option value=""><?= __('articles.filter_all_channels') ?></option>
                    <?php foreach ($channels as $ch): ?>
                    <option value="<?= (int)$ch['id'] ?>"
                            <?= $filters['channel_id'] == $ch['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ch['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label"><?= __('articles.filter_source') ?></label>
                <select name="source_id" class="form-select form-select-sm">
                    <option value=""><?= __('articles.filter_all_sources') ?></option>
                    <?php foreach ($sources as $src): ?>
                    <option value="<?= (int)$src['id'] ?>"
                            <?= $filters['source_id'] == $src['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($src['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label"><?= __('articles.filter_date_from') ?></label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['date_from']) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label"><?= __('articles.filter_date_to') ?></label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['date_to']) ?>">
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> <?= __('articles.btn_filter') ?>
                </button>
                <a href="?page=articles" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($articles)): ?>
<div class="alert alert-info">
    <p class="mb-0"><?= __('articles.msg_no_articles') ?></p>
</div>
<?php else: ?>

<!-- Bulk Actions -->
<form method="post" action="?page=articles&action=bulk" id="bulkForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="selectAll">
                    <label class="form-check-label" for="selectAll"><?= __('articles.select_all') ?></label>
                </div>
                <select name="bulk_action" class="form-select form-select-sm" style="width: auto;">
                    <option value=""><?= __('articles.bulk_action') ?></option>
                    <option value="reprocess"><?= __('articles.bulk_reprocess') ?></option>
                    <option value="cancel"><?= __('articles.bulk_cancel') ?></option>
                    <option value="publish"><?= __('articles.bulk_publish') ?></option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary"
                        onclick="return confirm(<?= htmlspecialchars(json_encode(__('articles.bulk_confirm')), ENT_QUOTES) ?>)">
                    <?= __('articles.btn_apply') ?>
                </button>
                <span class="text-muted small"><?= __('articles.total') ?>: <?= number_format($pagination['total_items']) ?></span>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" class="form-check-input" id="selectAllTop">
                    </th>
                    <th><?= __('articles.col_id') ?></th>
                    <th><?= __('articles.col_title') ?></th>
                    <th><?= __('articles.col_source') ?></th>
                    <th><?= __('articles.col_status') ?></th>
                    <th title="Importance Score"><?= __('articles.col_importance') ?></th>
                    <th><?= __('articles.col_versions') ?></th>
                    <th><?= __('articles.col_date') ?></th>
                    <th><?= __('articles.col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $a): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input article-checkbox"
                               name="article_ids[]" value="<?= (int)$a['id'] ?>">
                    </td>
                    <td><?= (int)$a['id'] ?></td>
                    <td>
                        <a href="?page=articles&action=view&id=<?= (int)$a['id'] ?>" class="text-decoration-none">
                            <span class="truncate d-inline-block" style="max-width: 350px;" title="<?= htmlspecialchars($a['title'] ?? '') ?>">
                                <?= htmlspecialchars(mb_substr($a['title'] ?? __('articles.no_title'), 0, 60)) ?>
                                <?= mb_strlen($a['title'] ?? '') > 60 ? '...' : '' ?>
                            </span>
                        </a>
                        <?php if ($a['cluster_id']): ?>
                            <span class="badge bg-secondary ms-1" title="<?= __('articles.in_cluster') ?>">
                                <i class="bi bi-layers"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="small text-muted"><?= htmlspecialchars($a['source_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $statusBadges[$a['status']] ?? 'bg-secondary' ?> status-badge">
                            <?= __('common.status.' . $a['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($a['importance_score']): ?>
                            <span class="badge <?= $a['importance_score'] >= 7 ? 'bg-success' : ($a['importance_score'] >= 5 ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= (int)$a['importance_score'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?= (int)$a['version_count'] ?></span>
                    </td>
                    <td>
                        <span class="small text-muted"><?= date('d.m H:i', strtotime($a['created_at'])) ?></span>
                    </td>
                    <td>
                        <a href="?page=articles&action=view&id=<?= (int)$a['id'] ?>"
                           class="btn btn-sm btn-outline-primary" title="<?= __('articles.btn_view') ?>">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <?php if ($pagination['has_prev']): ?>
        <li class="page-item">
            <a class="page-link" href="?page=articles&p=<?= $pagination['current_page'] - 1 ?>&<?= $queryString ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php endif; ?>

        <?php
        $startPage = max(1, $pagination['current_page'] - 2);
        $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
        for ($p = $startPage; $p <= $endPage; $p++):
        ?>
        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=articles&p=<?= $p ?>&<?= $queryString ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($pagination['has_next']): ?>
        <li class="page-item">
            <a class="page-link" href="?page=articles&p=<?= $pagination['current_page'] + 1 ?>&<?= $queryString ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkboxes
    const selectAllTop = document.getElementById('selectAllTop');
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.article-checkbox');

    function toggleAll(checked) {
        checkboxes.forEach(cb => cb.checked = checked);
        if (selectAllTop) selectAllTop.checked = checked;
        if (selectAll) selectAll.checked = checked;
    }

    if (selectAllTop) {
        selectAllTop.addEventListener('change', function() {
            toggleAll(this.checked);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            toggleAll(this.checked);
        });
    }
});
</script>
