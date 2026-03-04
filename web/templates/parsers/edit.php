<?php
/**
 * Parser edit/create template.
 * Variables: $source, $parser, $errors, $recentRuns
 */

use function NewsBot\Web\Helpers\__;

$pageTitle = $parser ? __('parsers.edit') : __('parsers.create');
$isEdit = (bool)$parser;

// Helper to get field value from parser or POST
$val = function($field, $default = '') use ($parser) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return $_POST[$field] ?? $default;
    }
    return $parser ? ($parser->$field ?? $default) : $default;
};

// Get exclude patterns as newline-separated string
$excludePatterns = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $excludePatterns = $_POST['exclude_patterns'] ?? '';
} elseif ($parser) {
    $patterns = $parser->getExcludePatterns();
    $excludePatterns = implode("\n", $patterns);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><?= $pageTitle ?></h2>
        <p class="text-muted mb-0">
            <?= __('parsers.col_source') ?>: <strong><?= htmlspecialchars($source->name) ?></strong>
            (<a href="<?= htmlspecialchars($source->site_url) ?>" target="_blank"><?= htmlspecialchars($source->site_url) ?></a>)
        </p>
    </div>
    <a href="?page=parsers" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('parsers.btn_back') ?>
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong><?= __('common.errors') ?>:</strong>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" id="parserForm">
    <?php if ($parser): ?>
    <input type="hidden" name="parser_id" value="<?= (int)$parser->id ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('parsers.url_selectors') ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_list_url') ?> <span class="text-danger">*</span></label>
                        <input type="url" name="list_url" class="form-control" required
                               value="<?= htmlspecialchars($val('list_url')) ?>"
                               placeholder="https://example.com/news/">
                        <div class="form-text"><?= __('parsers.field_list_url_help') ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_article_selector') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="article_selector" class="form-control" required
                               value="<?= htmlspecialchars($val('article_selector')) ?>"
                               placeholder=".news-item, article.post, //div[@class='article']">
                        <div class="form-text"><?= __('parsers.field_article_selector_help') ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_link_selector') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="link_selector" class="form-control" required
                               value="<?= htmlspecialchars($val('link_selector')) ?>"
                               placeholder="a.title, h2 a, .//a[@class='headline']">
                        <div class="form-text"><?= __('parsers.field_link_selector_help') ?></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_title_selector') ?></label>
                            <input type="text" name="title_selector" class="form-control"
                                   value="<?= htmlspecialchars($val('title_selector')) ?>"
                                   placeholder=".title, h2, .//span[@class='headline']">
                            <div class="form-text"><?= __('parsers.field_title_selector_help') ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_date_selector') ?></label>
                            <input type="text" name="date_selector" class="form-control"
                                   value="<?= htmlspecialchars($val('date_selector')) ?>"
                                   placeholder=".date, time, .//span[@class='timestamp']">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_image_selector') ?></label>
                            <input type="text" name="image_selector" class="form-control"
                                   value="<?= htmlspecialchars($val('image_selector')) ?>"
                                   placeholder="img.thumbnail, .//img[@class='preview']">
                            <div class="form-text"><?= __('parsers.field_image_selector_help') ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_description_selector') ?></label>
                            <input type="text" name="description_selector" class="form-control"
                                   value="<?= htmlspecialchars($val('description_selector')) ?>"
                                   placeholder=".excerpt, .summary, p.lead">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('parsers.pagination') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_pagination_type') ?></label>
                            <select name="pagination_type" class="form-select" id="paginationType">
                                <option value="none" <?= $val('pagination_type', 'none') === 'none' ? 'selected' : '' ?>>
                                    <?= __('parsers.pagination_none') ?>
                                </option>
                                <option value="page_param" <?= $val('pagination_type') === 'page_param' ? 'selected' : '' ?>>
                                    <?= __('parsers.pagination_page') ?>
                                </option>
                                <option value="offset" <?= $val('pagination_type') === 'offset' ? 'selected' : '' ?>>
                                    <?= __('parsers.pagination_offset') ?>
                                </option>
                                <option value="next_link" <?= $val('pagination_type') === 'next_link' ? 'selected' : '' ?>>
                                    <?= __('parsers.pagination_next') ?>
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_max_pages') ?></label>
                            <input type="number" name="max_pages" class="form-control" min="1" max="20"
                                   value="<?= (int)$val('max_pages', 3) ?>">
                            <div class="form-text"><?= __('parsers.field_max_pages_help') ?></div>
                        </div>
                    </div>

                    <div class="row" id="paginationParams">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('parsers.field_pagination_param') ?></label>
                            <input type="text" name="pagination_param" class="form-control"
                                   value="<?= htmlspecialchars($val('pagination_param')) ?>"
                                   placeholder="page, p, offset">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('parsers.field_pagination_start') ?></label>
                            <input type="number" name="pagination_start" class="form-control" min="0"
                                   value="<?= (int)$val('pagination_start', 1) ?>">
                        </div>
                        <div class="col-md-4 mb-3" id="offsetIncrementGroup">
                            <label class="form-label"><?= __('parsers.field_offset_increment') ?></label>
                            <input type="number" name="offset_increment" class="form-control" min="1"
                                   value="<?= (int)$val('offset_increment', 20) ?>">
                        </div>
                    </div>

                    <div class="mb-3" id="nextLinkSelector" style="display: none;">
                        <label class="form-label"><?= __('parsers.field_next_selector') ?></label>
                        <input type="text" name="pagination_selector" class="form-control"
                               value="<?= htmlspecialchars($val('pagination_selector')) ?>"
                               placeholder="a.next-page, .pagination a:last-child">
                        <div class="form-text"><?= __('parsers.field_next_selector_help') ?></div>
                    </div>
                </div>
            </div>

            <!-- Filtering -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('parsers.filtering') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_min_title_length') ?></label>
                            <input type="number" name="min_title_length" class="form-control" min="0"
                                   value="<?= (int)$val('min_title_length', 10) ?>">
                            <div class="form-text"><?= __('parsers.field_min_title_help') ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_date_format') ?></label>
                            <input type="text" name="date_format" class="form-control"
                                   value="<?= htmlspecialchars($val('date_format')) ?>"
                                   placeholder="d/m/Y H:i">
                            <div class="form-text"><?= __('parsers.field_date_format_help') ?></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_exclude_patterns') ?></label>
                        <textarea name="exclude_patterns" class="form-control font-monospace" rows="4"
                                  placeholder="/\/category\//&#10;/\/tag\//"><?= htmlspecialchars($excludePatterns) ?></textarea>
                        <div class="form-text"><?= __('parsers.field_exclude_help') ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_link_base_url') ?></label>
                        <input type="url" name="link_base_url" class="form-control"
                               value="<?= htmlspecialchars($val('link_base_url')) ?>"
                               placeholder="https://example.com">
                        <div class="form-text"><?= __('parsers.field_link_base_help') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Status & Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('parsers.status') ?></h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                               <?= $val('is_active', 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive"><?= __('parsers.parser_active') ?></label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_request_delay') ?></label>
                        <input type="number" name="request_delay_ms" class="form-control" min="500" max="30000"
                               value="<?= (int)$val('request_delay_ms', 2000) ?>">
                        <div class="form-text"><?= __('parsers.field_request_delay_help') ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_fetch_interval') ?></label>
                        <input type="number" name="fetch_interval_min" class="form-control" min="1" max="10080"
                               value="<?= !empty($val('fetch_interval_min')) ? (int)$val('fetch_interval_min') : '' ?>"
                               placeholder="<?= __('parsers.field_fetch_interval_placeholder') ?>">
                        <div class="form-text"><?= __('parsers.field_fetch_interval_help') ?></div>
                    </div>

                    <hr>

                    <h6><?= __('parsers.error_handling') ?></h6>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_max_errors') ?></label>
                            <input type="number" name="max_errors" class="form-control" min="1" max="20"
                                   value="<?= (int)$val('max_errors', 5) ?>">
                            <div class="form-text"><?= __('parsers.field_max_errors_help') ?></div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= __('parsers.field_max_zero_runs') ?></label>
                            <input type="number" name="max_zero_runs" class="form-control" min="1" max="20"
                                   value="<?= (int)$val('max_zero_runs', 3) ?>">
                            <div class="form-text"><?= __('parsers.field_max_zero_help') ?></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('parsers.field_min_articles') ?></label>
                        <input type="number" name="min_articles_threshold" class="form-control" min="0"
                               value="<?= (int)$val('min_articles_threshold', 0) ?>">
                        <div class="form-text"><?= __('parsers.field_min_articles_help') ?></div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg"></i> <?= __('parsers.btn_save') ?>
                    </button>
                    <button type="button" class="btn btn-outline-info w-100 mb-2" id="analyzeBtn">
                        <i class="bi bi-magic"></i> <?= __('parsers.btn_autofill') ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary w-100" id="testParserBtn">
                        <i class="bi bi-play-fill"></i> <?= __('parsers.btn_test') ?>
                    </button>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <!-- Parser Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('parsers.statistics') ?></h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6"><?= __('parsers.last_run') ?>:</dt>
                        <dd class="col-6">
                            <?= $parser->last_parsed_at ? date('M j, H:i', strtotime($parser->last_parsed_at)) : __('parsers.never') ?>
                        </dd>
                        <dt class="col-6"><?= __('parsers.last_count') ?>:</dt>
                        <dd class="col-6"><?= (int)$parser->last_articles_count ?> <?= __('parsers.articles') ?></dd>
                        <dt class="col-6"><?= __('parsers.errors') ?>:</dt>
                        <dd class="col-6">
                            <?php if ($parser->consecutive_errors > 0): ?>
                                <span class="text-danger"><?= (int)$parser->consecutive_errors ?></span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </dd>
                        <dt class="col-6"><?= __('parsers.zero_runs') ?>:</dt>
                        <dd class="col-6"><?= (int)$parser->consecutive_zero_articles ?></dd>
                    </dl>
                    <?php if ($parser->last_error): ?>
                    <div class="mt-3">
                        <strong class="text-danger"><?= __('parsers.last_error') ?>:</strong>
                        <pre class="bg-light p-2 rounded small mb-0"><?= htmlspecialchars($parser->last_error) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Runs -->
            <?php if (!empty($recentRuns)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('parsers.recent_runs') ?></h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?= __('parsers.col_time') ?></th>
                                <th><?= __('parsers.col_found') ?></th>
                                <th><?= __('parsers.col_new') ?></th>
                                <th><?= __('parsers.col_status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRuns as $run): ?>
                            <tr>
                                <td><small><?= date('M j H:i', strtotime($run['started_at'])) ?></small></td>
                                <td><?= (int)$run['articles_found'] ?></td>
                                <td><?= (int)$run['articles_new'] ?></td>
                                <td>
                                    <?php if ($run['error_message']): ?>
                                        <span class="badge bg-danger" title="<?= htmlspecialchars($run['error_message']) ?>"><?= __('common.status.error') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Delete -->
            <div class="card border-danger mt-4">
                <div class="card-body">
                    <form method="post" action="?page=parsers&action=delete"
                          onsubmit="return confirm(<?= htmlspecialchars(json_encode(__('parsers.msg_delete_confirm')), ENT_QUOTES) ?>);">
                        <input type="hidden" name="source_id" value="<?= (int)$source->id ?>">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-trash"></i> <?= __('parsers.btn_delete') ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

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
                    <p class="mt-2"><?= __('parsers.testing_current') ?></p>
                </div>
                <div id="testResults" style="display: none;">
                    <div id="testError" class="alert alert-danger" style="display: none;"></div>
                    <div id="testSuccess" style="display: none;">
                        <div class="alert alert-success">
                            <?= __('parsers.col_found') ?> <strong id="testCount">0</strong> <?= __('parsers.articles') ?> &mdash; <span id="testDuration">0</span>ms
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th><?= __('parsers.col_title') ?></th>
                                        <th><?= __('parsers.col_date') ?></th>
                                        <th><?= __('parsers.col_image') ?></th>
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

<!-- Analyze Results Modal -->
<div class="modal fade" id="analyzeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('parsers.auto_analyze') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="analyzeLoading" class="text-center py-4">
                    <div class="spinner-border text-info"></div>
                    <p class="mt-2"><?= __('parsers.analyzing') ?></p>
                </div>
                <div id="analyzeResults" style="display: none;">
                    <div id="analyzeError" class="alert alert-danger" style="display: none;"></div>
                    <div id="analyzeSuccess" style="display: none;">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            <span id="analyzeFoundMsg"></span>
                        </div>
                        <h6><?= __('parsers.detected_selectors') ?></h6>
                        <table class="table table-sm">
                            <tbody id="analyzeSelectorsList"></tbody>
                        </table>
                        <div id="analyzeSampleTitles" style="display: none;">
                            <h6><?= __('parsers.sample_titles') ?></h6>
                            <ul id="analyzeTitlesList" class="small"></ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.btn.close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paginationType = document.getElementById('paginationType');
    const paginationParams = document.getElementById('paginationParams');
    const nextLinkSelector = document.getElementById('nextLinkSelector');
    const offsetIncrementGroup = document.getElementById('offsetIncrementGroup');

    const noTitleText = <?= json_encode(__('parsers.no_title')) ?>;
    const hasImageYes = <?= json_encode(__('parsers.has_image_yes')) ?>;
    const hasImageNo = <?= json_encode(__('parsers.has_image_no')) ?>;
    const analyzeFailedMsg = <?= json_encode(__('parsers.analyze_failed')) ?>;
    const requestErrorMsg = <?= json_encode(__('parsers.request_error')) ?>;
    const foundSelectorsMsg = <?= json_encode(__('parsers.found_selectors')) ?>;

    function updatePaginationUI() {
        const type = paginationType.value;
        if (type === 'none') {
            paginationParams.style.display = 'none';
            nextLinkSelector.style.display = 'none';
        } else if (type === 'next_link') {
            paginationParams.style.display = 'none';
            nextLinkSelector.style.display = 'block';
        } else {
            paginationParams.style.display = 'flex';
            nextLinkSelector.style.display = 'none';
            offsetIncrementGroup.style.display = type === 'offset' ? 'block' : 'none';
        }
    }

    paginationType.addEventListener('change', updatePaginationUI);
    updatePaginationUI();

    // Analyze button
    document.getElementById('analyzeBtn').addEventListener('click', function() {
        const sourceId = '<?= (int)$source->id ?>';
        const listUrlInput = document.querySelector('input[name="list_url"]');
        const url = listUrlInput.value.trim() || '<?= htmlspecialchars($source->site_url) ?>';

        const modal = new bootstrap.Modal(document.getElementById('analyzeModal'));
        document.getElementById('analyzeLoading').style.display = 'block';
        document.getElementById('analyzeResults').style.display = 'none';
        modal.show();

        const body = new URLSearchParams();
        body.append('source_id', sourceId);
        if (url) body.append('url', url);

        fetch('?page=parsers&action=analyze', {
            method: 'POST',
            body: body
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('analyzeLoading').style.display = 'none';
            document.getElementById('analyzeResults').style.display = 'block';

            if (!data.success) {
                document.getElementById('analyzeError').textContent = data.message || analyzeFailedMsg;
                document.getElementById('analyzeError').style.display = 'block';
                document.getElementById('analyzeSuccess').style.display = 'none';
                return;
            }

            document.getElementById('analyzeError').style.display = 'none';
            document.getElementById('analyzeSuccess').style.display = 'block';
            const count = data.articles_found || 0;
            document.getElementById('analyzeFoundMsg').textContent =
                foundSelectorsMsg.replace(':count', count);

            // Fill form fields
            const fieldMap = {
                'list_url': data.list_url,
                'article_selector': data.article_selector,
                'link_selector': data.link_selector,
                'title_selector': data.title_selector,
                'date_selector': data.date_selector,
                'image_selector': data.image_selector,
                'description_selector': data.description_selector,
                'link_base_url': data.link_base_url,
                'date_format': data.date_format,
            };

            const selectorsList = document.getElementById('analyzeSelectorsList');
            selectorsList.innerHTML = '';

            const labels = {
                'list_url': <?= json_encode(__('parsers.field_list_url')) ?>,
                'article_selector': <?= json_encode(__('parsers.field_article_selector')) ?>,
                'link_selector': <?= json_encode(__('parsers.field_link_selector')) ?>,
                'title_selector': <?= json_encode(__('parsers.field_title_selector')) ?>,
                'date_selector': <?= json_encode(__('parsers.field_date_selector')) ?>,
                'image_selector': <?= json_encode(__('parsers.field_image_selector')) ?>,
                'description_selector': <?= json_encode(__('parsers.field_description_selector')) ?>,
            };

            for (const [name, value] of Object.entries(fieldMap)) {
                if (value) {
                    const input = document.querySelector(`[name="${name}"]`);
                    if (input && !input.value.trim()) {
                        input.value = value;
                    }
                    if (labels[name]) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td class="fw-bold">${labels[name]}</td><td><code>${escapeHtml(value)}</code></td>`;
                        selectorsList.appendChild(tr);
                    }
                }
            }

            // Handle pagination
            if (data.pagination_type && data.pagination_type !== 'none') {
                const pagSelect = document.querySelector('[name="pagination_type"]');
                if (pagSelect) {
                    pagSelect.value = data.pagination_type;
                    pagSelect.dispatchEvent(new Event('change'));
                }
                if (data.pagination_param) {
                    const paramInput = document.querySelector('[name="pagination_param"]');
                    if (paramInput && !paramInput.value.trim()) paramInput.value = data.pagination_param;
                }
                if (data.pagination_selector) {
                    const selInput = document.querySelector('[name="pagination_selector"]');
                    if (selInput && !selInput.value.trim()) selInput.value = data.pagination_selector;
                }
                if (data.offset_increment) {
                    const oiInput = document.querySelector('[name="offset_increment"]');
                    if (oiInput) oiInput.value = data.offset_increment;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `<td class="fw-bold"><?= __('parsers.pagination') ?></td><td><code>${escapeHtml(data.pagination_type)}</code> ${data.pagination_param ? '(' + escapeHtml(data.pagination_param) + ')' : ''}</td>`;
                selectorsList.appendChild(tr);
            }

            // Show sample titles
            if (data.sample_titles && data.sample_titles.length > 0) {
                document.getElementById('analyzeSampleTitles').style.display = 'block';
                const titlesList = document.getElementById('analyzeTitlesList');
                titlesList.innerHTML = '';
                data.sample_titles.forEach(t => {
                    const li = document.createElement('li');
                    li.textContent = t;
                    titlesList.appendChild(li);
                });
            }
        })
        .catch(err => {
            document.getElementById('analyzeLoading').style.display = 'none';
            document.getElementById('analyzeResults').style.display = 'block';
            document.getElementById('analyzeError').textContent = requestErrorMsg + ': ' + err.message;
            document.getElementById('analyzeError').style.display = 'block';
            document.getElementById('analyzeSuccess').style.display = 'none';
        });
    });

    // Test parser button
    document.getElementById('testParserBtn').addEventListener('click', function() {
        const form = document.getElementById('parserForm');
        const formData = new FormData(form);
        formData.append('source_id', '<?= (int)$source->id ?>');
        formData.append('test_config', '1');

        const modal = new bootstrap.Modal(document.getElementById('testResultsModal'));
        document.getElementById('testLoading').style.display = 'block';
        document.getElementById('testResults').style.display = 'none';
        modal.show();

        fetch('?page=parsers&action=test', {
            method: 'POST',
            body: new URLSearchParams(formData)
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
                        <td>
                            <a href="${escapeHtml(a.url)}" target="_blank">${escapeHtml(a.title || noTitleText)}</a>
                            <br><small class="text-muted">${escapeHtml(a.description?.substring(0, 100) || '')}</small>
                        </td>
                        <td><small>${a.date || '-'}</small></td>
                        <td>${a.image ? '<span class="badge bg-success">' + hasImageYes + '</span>' : '<span class="badge bg-secondary">' + hasImageNo + '</span>'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        })
        .catch(err => {
            document.getElementById('testLoading').style.display = 'none';
            document.getElementById('testResults').style.display = 'block';
            document.getElementById('testError').textContent = requestErrorMsg + ': ' + err.message;
            document.getElementById('testError').style.display = 'block';
            document.getElementById('testSuccess').style.display = 'none';
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
</script>
