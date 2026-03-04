<?php
/**
 * Source edit/create form template.
 * Variables: $source, $feeds, $scrapeRules
 */

use function NewsBot\Web\Helpers\__;

$isEdit = $source !== null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-globe"></i> <?= $isEdit ? __('sources.edit') : __('sources.create') ?></h2>
    <a href="?page=sources" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= __('common.btn.back') ?>
    </a>
</div>

<form method="post" action="?page=sources&action=save">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="id" value="<?= $isEdit ? (int)$source->id : 0 ?>">

    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-gear"></i> <?= __('sources.general_settings') ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label"><?= __('sources.field_name') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?= htmlspecialchars($isEdit ? ($source->name ?? '') : '') ?>"
                                       placeholder="Bangkok Post">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_url" class="form-label"><?= __('sources.field_site_url') ?> <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="site_url" name="site_url" required
                                       value="<?= htmlspecialchars($isEdit ? ($source->site_url ?? '') : '') ?>"
                                       placeholder="https://www.bangkokpost.com">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="type" class="form-label"><?= __('sources.field_type') ?></label>
                                <select class="form-select" id="type" name="type">
                                    <option value="news" <?= (!$isEdit || ($source->type ?? 'news') === 'news') ? 'selected' : '' ?>><?= __('sources.field_type_news') ?></option>
                                    <option value="blog" <?= ($isEdit && $source->type === 'blog') ? 'selected' : '' ?>><?= __('sources.field_type_blog') ?></option>
                                    <option value="aggregator" <?= ($isEdit && $source->type === 'aggregator') ? 'selected' : '' ?>><?= __('sources.field_type_aggregator') ?></option>
                                    <option value="official" <?= ($isEdit && $source->type === 'official') ? 'selected' : '' ?>><?= __('sources.field_type_official') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="scrape_strategy" class="form-label"><?= __('sources.field_scrape_strategy') ?></label>
                                <select class="form-select" id="scrape_strategy" name="scrape_strategy">
                                    <option value="web" <?= (!$isEdit || ($source->scrape_strategy ?? 'web') === 'web') ? 'selected' : '' ?>>
                                        <?= __('sources.field_strategy_web') ?>
                                    </option>
                                    <option value="rss_only" <?= ($isEdit && $source->scrape_strategy === 'rss_only') ? 'selected' : '' ?>>
                                        <?= __('sources.field_strategy_rss_only') ?>
                                    </option>
                                    <option value="custom_parser" <?= ($isEdit && $source->scrape_strategy === 'custom_parser') ? 'selected' : '' ?>>
                                        <?= __('sources.field_strategy_custom') ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="authority_rank" class="form-label"><?= __('sources.field_authority_rank') ?></label>
                                <input type="number" class="form-control" id="authority_rank" name="authority_rank"
                                       min="1" max="100"
                                       value="<?= $isEdit ? (int)($source->authority_rank ?? 50) : 50 ?>">
                                <div class="form-text"><?= __('sources.field_authority_help') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="request_delay_ms" class="form-label"><?= __('sources.field_request_delay') ?></label>
                                <input type="number" class="form-control" id="request_delay_ms" name="request_delay_ms"
                                       min="500" max="30000"
                                       value="<?= $isEdit ? (int)($source->request_delay_ms ?? 2000) : 2000 ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="proxy_url" class="form-label"><?= __('sources.field_proxy_url') ?></label>
                                <input type="url" class="form-control" id="proxy_url" name="proxy_url"
                                       value="<?= htmlspecialchars($isEdit ? ($source->proxy_url ?? '') : '') ?>"
                                       placeholder="http://proxy:8080">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label"><?= __('sources.field_status') ?></label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?= (!$isEdit || $source->status === 'active') ? 'selected' : '' ?>><?= __('common.status.active') ?></option>
                                    <option value="paused" <?= ($isEdit && $source->status === 'paused') ? 'selected' : '' ?>><?= __('common.status.paused') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feeds -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-rss"></i> <?= __('sources.feeds') ?></span>
                    <button type="button" class="btn btn-sm btn-success" onclick="addFeedRow()">
                        <i class="bi bi-plus"></i> <?= __('sources.feeds_add') ?>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-1 mb-1 text-muted small d-none d-md-flex">
                        <div class="col-md-4"><?= __('sources.feed_url') ?></div>
                        <div class="col-md-3"><?= __('sources.feed_name') ?></div>
                        <div class="col-md-2"><?= __('sources.feed_interval') ?></div>
                        <div class="col-md-2"><?= __('sources.feed_status') ?></div>
                    </div>
                    <div id="feeds-container">
                        <?php if (empty($feeds)): ?>
                        <div class="feed-row row mb-2 g-1" data-index="0">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="feeds[0][url]"
                                       placeholder="https://example.com/rss.xml">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" name="feeds[0][name]"
                                       placeholder="<?= __('sources.feed_name') ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control form-control-sm" name="feeds[0][fetch_interval_min]"
                                       min="1" max="10080" placeholder="<?= __('sources.feed_interval_placeholder') ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" name="feeds[0][status]">
                                    <option value="active"><?= __('common.status.active') ?></option>
                                    <option value="paused"><?= __('common.status.paused') ?></option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFeedRow(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <input type="hidden" name="feeds[0][id]" value="0">
                        </div>
                        <?php else: ?>
                        <?php foreach ($feeds as $idx => $feed): ?>
                        <div class="feed-row row mb-2 g-1" data-index="<?= $idx ?>">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="feeds[<?= $idx ?>][url]"
                                       value="<?= htmlspecialchars($feed['url'] ?? '') ?>"
                                       placeholder="https://example.com/rss.xml">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" name="feeds[<?= $idx ?>][name]"
                                       value="<?= htmlspecialchars($feed['name'] ?? '') ?>"
                                       placeholder="<?= __('sources.feed_name') ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control form-control-sm" name="feeds[<?= $idx ?>][fetch_interval_min]"
                                       min="1" max="10080"
                                       value="<?= !empty($feed['fetch_interval_min']) ? (int)$feed['fetch_interval_min'] : '' ?>"
                                       placeholder="<?= __('sources.feed_interval_placeholder') ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" name="feeds[<?= $idx ?>][status]">
                                    <option value="active" <?= ($feed['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= __('common.status.active') ?></option>
                                    <option value="paused" <?= ($feed['status'] ?? '') === 'paused' ? 'selected' : '' ?>><?= __('common.status.paused') ?></option>
                                    <option value="auto_disabled" <?= ($feed['status'] ?? '') === 'auto_disabled' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFeedRow(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <input type="hidden" name="feeds[<?= $idx ?>][id]" value="<?= (int)($feed['id'] ?? 0) ?>">
                            <?php if (!empty($feed['last_error'])): ?>
                            <div class="col-12">
                                <small class="text-danger"><?= __('sources.feed_error') ?>: <?= htmlspecialchars($feed['last_error']) ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Scrape Rules -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-code-square"></i> <?= __('sources.scrape_rules') ?></span>
                    <button type="button" class="btn btn-sm btn-success" onclick="addRuleRow()">
                        <i class="bi bi-plus"></i> <?= __('sources.scrape_rules_add') ?>
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?= __('sources.scrape_rules_help') ?></p>
                    <div id="rules-container">
                        <?php if (empty($scrapeRules)): ?>
                        <div class="rule-row row mb-3" data-index="0">
                            <div class="col-md-5">
                                <input type="text" class="form-control form-control-sm" name="rules[0][content_selector]"
                                       placeholder="article .content, .article-body">
                                <small class="text-muted"><?= __('sources.scrape_content_selector') ?></small>
                            </div>
                            <div class="col-md-5">
                                <textarea class="form-control form-control-sm" name="rules[0][remove_selectors]" rows="2"
                                          placeholder=".ads&#10;.social-share&#10;.related-posts"></textarea>
                                <small class="text-muted"><?= __('sources.scrape_remove_selectors') ?> (<?= __('sources.scrape_remove_help') ?>)</small>
                            </div>
                            <div class="col-md-1">
                                <input type="number" class="form-control form-control-sm" name="rules[0][priority]"
                                       value="0" min="0" max="100">
                                <small class="text-muted"><?= __('sources.scrape_priority') ?></small>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRuleRow(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <input type="hidden" name="rules[0][id]" value="0">
                        </div>
                        <?php else: ?>
                        <?php foreach ($scrapeRules as $idx => $rule): ?>
                        <?php
                        $removeSelectors = '';
                        if (!empty($rule['remove_selectors'])) {
                            $decoded = json_decode($rule['remove_selectors'], true);
                            $removeSelectors = is_array($decoded) ? implode("\n", $decoded) : $rule['remove_selectors'];
                        }
                        ?>
                        <div class="rule-row row mb-3" data-index="<?= $idx ?>">
                            <div class="col-md-5">
                                <input type="text" class="form-control form-control-sm" name="rules[<?= $idx ?>][content_selector]"
                                       value="<?= htmlspecialchars($rule['content_selector'] ?? '') ?>"
                                       placeholder="article .content, .article-body">
                                <small class="text-muted"><?= __('sources.scrape_content_selector') ?></small>
                            </div>
                            <div class="col-md-5">
                                <textarea class="form-control form-control-sm" name="rules[<?= $idx ?>][remove_selectors]" rows="2"
                                          placeholder=".ads&#10;.social-share"><?= htmlspecialchars($removeSelectors) ?></textarea>
                                <small class="text-muted"><?= __('sources.scrape_remove_selectors') ?></small>
                            </div>
                            <div class="col-md-1">
                                <input type="number" class="form-control form-control-sm" name="rules[<?= $idx ?>][priority]"
                                       value="<?= (int)($rule['priority'] ?? 0) ?>" min="0" max="100">
                                <small class="text-muted"><?= __('sources.scrape_priority') ?></small>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRuleRow(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <input type="hidden" name="rules[<?= $idx ?>][id]" value="<?= (int)($rule['id'] ?? 0) ?>">
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <?php if ($isEdit): ?>
            <!-- Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> <?= __('sources.info') ?>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5"><?= __('common.label.id') ?>:</dt>
                        <dd class="col-7"><?= (int)$source->id ?></dd>

                        <dt class="col-5"><?= __('common.label.created_at') ?>:</dt>
                        <dd class="col-7"><?= date('Y-m-d H:i', strtotime($source->created_at ?? 'now')) ?></dd>

                        <dt class="col-5"><?= __('common.label.updated_at') ?>:</dt>
                        <dd class="col-7"><?= date('Y-m-d H:i', strtotime($source->updated_at ?? 'now')) ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Disabled Feeds -->
            <?php
            $disabledFeeds = array_filter($feeds, fn($f) => ($f['status'] ?? '') === 'auto_disabled');
            if (!empty($disabledFeeds)):
            ?>
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> <?= __('sources.disabled_feeds') ?>
                </div>
                <div class="card-body">
                    <p class="small text-muted"><?= __('sources.disabled_feeds_help') ?></p>
                    <form method="post" action="?page=sources&action=reactivate_all_feeds">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="source_id" value="<?= (int)$source->id ?>">
                        <button type="submit" class="btn btn-sm btn-warning w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> <?= __('sources.reactivate_all') ?> (<?= count($disabledFeeds) ?>)
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Custom Parser Link -->
            <?php if ($isEdit && ($source->scrape_strategy ?? '') === 'custom_parser'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-code-slash"></i> <?= __('sources.custom_parser') ?>
                </div>
                <div class="card-body">
                    <a href="?page=parsers&action=edit&source_id=<?= (int)$source->id ?>" class="btn btn-outline-primary w-100">
                        <i class="bi bi-pencil"></i> <?= __('sources.configure_parser') ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Submit -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= __('common.btn.save') ?>
                        </button>
                        <a href="?page=sources" class="btn btn-outline-secondary"><?= __('common.btn.cancel') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let feedIndex = <?= count($feeds) ?: 1 ?>;
let ruleIndex = <?= count($scrapeRules) ?: 1 ?>;

function addFeedRow() {
    const container = document.getElementById('feeds-container');
    const html = `
        <div class="feed-row row mb-2 g-1" data-index="${feedIndex}">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="feeds[${feedIndex}][url]"
                       placeholder="https://example.com/rss.xml">
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" name="feeds[${feedIndex}][name]"
                       placeholder="<?= __('sources.feed_name') ?>">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control form-control-sm" name="feeds[${feedIndex}][fetch_interval_min]"
                       min="1" max="10080" placeholder="<?= __('sources.feed_interval_placeholder') ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="feeds[${feedIndex}][status]">
                    <option value="active"><?= __('common.status.active') ?></option>
                    <option value="paused"><?= __('common.status.paused') ?></option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFeedRow(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <input type="hidden" name="feeds[${feedIndex}][id]" value="0">
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    feedIndex++;
}

function removeFeedRow(btn) {
    btn.closest('.feed-row').remove();
}

function addRuleRow() {
    const container = document.getElementById('rules-container');
    const html = `
        <div class="rule-row row mb-3" data-index="${ruleIndex}">
            <div class="col-md-5">
                <input type="text" class="form-control form-control-sm" name="rules[${ruleIndex}][content_selector]"
                       placeholder="article .content">
                <small class="text-muted"><?= __('sources.scrape_content_selector') ?></small>
            </div>
            <div class="col-md-5">
                <textarea class="form-control form-control-sm" name="rules[${ruleIndex}][remove_selectors]" rows="2"
                          placeholder=".ads"></textarea>
                <small class="text-muted"><?= __('sources.scrape_remove_selectors') ?></small>
            </div>
            <div class="col-md-1">
                <input type="number" class="form-control form-control-sm" name="rules[${ruleIndex}][priority]"
                       value="0" min="0" max="100">
                <small class="text-muted"><?= __('sources.scrape_priority') ?></small>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRuleRow(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <input type="hidden" name="rules[${ruleIndex}][id]" value="0">
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    ruleIndex++;
}

function removeRuleRow(btn) {
    btn.closest('.rule-row').remove();
}
</script>
