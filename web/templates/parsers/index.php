<?php
/**
 * Parser list template.
 * Variables: $parsers, $sourcesWithoutParsers
 */

use function NewsBot\Web\Helpers\__;

$pageTitle = __('parsers.title');

// Get all sources for adding new parsers (if no sources with custom_parser strategy exist)
$allSources = \NewsBot\Core\Database::fetchAll("
    SELECT s.id, s.name, s.site_url
    FROM sources s
    WHERE s.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM source_parsers sp WHERE sp.source_id = s.id
    )
    ORDER BY s.name ASC
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= __('parsers.title') ?></h2>
    <?php if (!empty($sourcesWithoutParsers)): ?>
    <div class="dropdown">
        <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-plus-lg"></i> <?= __('parsers.add_parser') ?>
        </button>
        <ul class="dropdown-menu">
            <?php foreach ($sourcesWithoutParsers as $src): ?>
            <li>
                <a class="dropdown-item" href="?page=parsers&action=edit&source_id=<?= (int)$src['id'] ?>">
                    <?= htmlspecialchars($src['name']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php elseif (!empty($allSources)): ?>
    <div class="dropdown">
        <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-plus-lg"></i> <?= __('parsers.add_parser') ?>
        </button>
        <ul class="dropdown-menu">
            <?php foreach ($allSources as $src): ?>
            <li>
                <a class="dropdown-item" href="?page=parsers&action=edit&source_id=<?= (int)$src['id'] ?>">
                    <?= htmlspecialchars($src['name']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash_success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash_error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (empty($parsers)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-gear fs-1 text-muted mb-3 d-block"></i>
        <h5><?= __('parsers.no_parsers') ?></h5>
        <?php if (!empty($sourcesWithoutParsers) || !empty($allSources)): ?>
            <p class="text-muted mb-3"><?= __('parsers.no_parsers_hint') ?></p>
            <div class="dropdown d-inline-block">
                <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-plus-lg"></i> <?= __('parsers.add_parser') ?>
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ((!empty($sourcesWithoutParsers) ? $sourcesWithoutParsers : $allSources) as $src): ?>
                    <li>
                        <a class="dropdown-item" href="?page=parsers&action=edit&source_id=<?= (int)$src['id'] ?>">
                            <?= htmlspecialchars($src['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <p class="text-muted mb-3"><?= __('parsers.add_source_first') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th><?= __('parsers.col_source') ?></th>
                <th><?= __('parsers.col_list_url') ?></th>
                <th><?= __('parsers.col_status') ?></th>
                <th><?= __('parsers.col_last_run') ?></th>
                <th><?= __('parsers.col_articles') ?></th>
                <th><?= __('parsers.col_errors') ?></th>
                <th><?= __('common.btn.actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($parsers as $p): ?>
            <tr>
                <td>
                    <a href="<?= htmlspecialchars($p['site_url']) ?>" target="_blank" class="text-decoration-none">
                        <?= htmlspecialchars($p['source_name']) ?>
                        <small class="text-muted"><i class="bi bi-box-arrow-up-right"></i></small>
                    </a>
                </td>
                <td>
                    <small class="text-muted" title="<?= htmlspecialchars($p['list_url']) ?>">
                        <?= htmlspecialchars(mb_strlen($p['list_url']) > 50 ? mb_substr($p['list_url'], 0, 50) . '...' : $p['list_url']) ?>
                    </small>
                </td>
                <td>
                    <?php if ($p['is_active']): ?>
                        <span class="badge bg-success"><?= __('parsers.status_active') ?></span>
                    <?php else: ?>
                        <span class="badge bg-danger"><?= __('parsers.status_disabled') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['last_parsed_at']): ?>
                        <small title="<?= htmlspecialchars($p['last_parsed_at']) ?>">
                            <?= date('M j, H:i', strtotime($p['last_parsed_at'])) ?>
                        </small>
                    <?php else: ?>
                        <span class="text-muted"><?= __('parsers.never') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-secondary"><?= (int)$p['last_articles_count'] ?></span>
                    <?php if ($p['consecutive_zero_articles'] > 0): ?>
                        <small class="text-warning" title="<?= __('parsers.zero_articles') ?>">
                            (0x<?= (int)$p['consecutive_zero_articles'] ?>)
                        </small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['consecutive_errors'] > 0): ?>
                        <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($p['last_error'] ?? '') ?>">
                            <?= (int)$p['consecutive_errors'] ?>/<?= (int)$p['max_errors'] ?>
                        </span>
                    <?php else: ?>
                        <span class="text-success">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?page=parsers&action=edit&source_id=<?= (int)$p['source_id'] ?>"
                           class="btn btn-outline-primary" title="<?= __('common.btn.edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-outline-secondary test-parser"
                                data-source-id="<?= (int)$p['source_id'] ?>" title="<?= __('parsers.btn_test') ?>">
                            <i class="bi bi-play-fill"></i>
                        </button>
                        <form method="post" action="?page=parsers&action=toggle" class="d-inline">
                            <input type="hidden" name="source_id" value="<?= (int)$p['source_id'] ?>">
                            <button type="submit" class="btn btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>"
                                    title="<?= $p['is_active'] ? __('common.btn.disable') : __('common.btn.enable') ?>">
                                <i class="bi bi-<?= $p['is_active'] ? 'pause' : 'play' ?>"></i>
                            </button>
                        </form>
                        <?php if ($p['consecutive_errors'] > 0): ?>
                        <form method="post" action="?page=parsers&action=resetErrors" class="d-inline">
                            <input type="hidden" name="source_id" value="<?= (int)$p['source_id'] ?>">
                            <button type="submit" class="btn btn-outline-info" title="<?= __('common.btn.reset') ?>">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Test Results Modal -->
<div class="modal fade" id="testResultsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('parsers.test_results') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testLoading" class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2"><?= __('parsers.testing') ?></p>
                </div>
                <div id="testResults" style="display: none;">
                    <div id="testError" class="alert alert-danger" style="display: none;"></div>
                    <div id="testSuccess" style="display: none;">
                        <div class="alert alert-success">
                            <?= __('parsers.col_found') ?>: <strong id="testCount">0</strong> <?= __('parsers.articles') ?> (<span id="testDuration">0</span>ms)
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th><?= __('parsers.col_title') ?></th>
                                        <th><?= __('parsers.col_date') ?></th>
                                        <th><?= __('parsers.col_url') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="testArticles"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const noTitleText = <?= json_encode(__('parsers.no_title')) ?>;
    const requestError = <?= json_encode(__('parsers.request_error')) ?>;

    // Test parser buttons
    document.querySelectorAll('.test-parser').forEach(btn => {
        btn.addEventListener('click', function() {
            const sourceId = this.dataset.sourceId;
            const modal = new bootstrap.Modal(document.getElementById('testResultsModal'));

            document.getElementById('testLoading').style.display = 'block';
            document.getElementById('testResults').style.display = 'none';
            modal.show();

            fetch('?page=parsers&action=test', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'source_id=' + sourceId
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('testLoading').style.display = 'none';
                document.getElementById('testResults').style.display = 'block';

                if (data.error) {
                    document.getElementById('testError').textContent = data.error;
                    document.getElementById('testError').style.display = 'block';
                    document.getElementById('testSuccess').style.display = 'none';
                } else {
                    document.getElementById('testError').style.display = 'none';
                    document.getElementById('testSuccess').style.display = 'block';
                    document.getElementById('testCount').textContent = data.count;
                    document.getElementById('testDuration').textContent = data.duration_ms;

                    const tbody = document.getElementById('testArticles');
                    tbody.innerHTML = '';
                    (data.articles || []).forEach(a => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${escapeHtml(a.title || noTitleText)}</td>
                            <td><small>${a.date || '-'}</small></td>
                            <td><a href="${escapeHtml(a.url)}" target="_blank" class="small">${escapeHtml(a.url.substring(0, 50))}...</a></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            })
            .catch(err => {
                document.getElementById('testLoading').style.display = 'none';
                document.getElementById('testResults').style.display = 'block';
                document.getElementById('testError').textContent = requestError + ': ' + err.message;
                document.getElementById('testError').style.display = 'block';
                document.getElementById('testSuccess').style.display = 'none';
            });
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
</script>
