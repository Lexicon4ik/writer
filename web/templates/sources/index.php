<?php
/**
 * Sources list template.
 * Variables: $sources
 */

use function NewsBot\Web\Helpers\__;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-globe"></i> <?= __('sources.title') ?></h2>
    <a href="?page=sources&action=edit" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> <?= __('sources.create') ?>
    </a>
</div>

<?php if (empty($sources)): ?>
<div class="alert alert-info">
    <p class="mb-0"><?= __('sources.msg_no_sources') ?> <a href="?page=sources&action=edit"><?= __('sources.msg_no_sources_link') ?></a>.</p>
</div>
<?php else: ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th><?= __('common.label.id') ?></th>
                <th><?= __('sources.col_name') ?></th>
                <th><?= __('sources.col_type') ?></th>
                <th><?= __('sources.col_strategy') ?></th>
                <th><?= __('sources.col_feeds') ?></th>
                <th><?= __('sources.col_channels') ?></th>
                <th><?= __('sources.col_rank') ?></th>
                <th><?= __('sources.col_status') ?></th>
                <th><?= __('common.btn.actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sources as $src): ?>
            <tr>
                <td><?= (int)$src['id'] ?></td>
                <td>
                    <a href="?page=sources&action=edit&id=<?= (int)$src['id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($src['name']) ?>
                    </a>
                    <br>
                    <small class="text-muted">
                        <a href="<?= htmlspecialchars($src['site_url']) ?>" target="_blank" class="text-muted">
                            <?= htmlspecialchars(parse_url($src['site_url'], PHP_URL_HOST) ?? $src['site_url']) ?>
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </small>
                </td>
                <td>
                    <span class="badge bg-secondary"><?= htmlspecialchars($src['type']) ?></span>
                </td>
                <td>
                    <?php
                    $strategyBadge = match ($src['scrape_strategy']) {
                        'web' => 'bg-primary',
                        'rss_only' => 'bg-info text-dark',
                        'custom_parser' => 'bg-warning text-dark',
                        default => 'bg-secondary',
                    };
                    ?>
                    <span class="badge <?= $strategyBadge ?>"><?= htmlspecialchars($src['scrape_strategy']) ?></span>
                </td>
                <td>
                    <?php
                    $feedCount = (int)$src['feed_count'];
                    $activeCount = (int)$src['active_feed_count'];
                    $disabledCount = (int)$src['disabled_feed_count'];
                    ?>
                    <span class="badge bg-success" title="<?= __('common.status.active') ?>"><?= $activeCount ?></span>
                    <?php if ($disabledCount > 0): ?>
                        <span class="badge bg-danger" title="<?= __('sources.disabled_feeds') ?>"><?= $disabledCount ?></span>
                    <?php endif; ?>
                    <?php if ($feedCount > $activeCount + $disabledCount): ?>
                        <span class="badge bg-secondary" title="<?= __('common.status.paused') ?>"><?= $feedCount - $activeCount - $disabledCount ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-info text-dark"><?= (int)$src['channel_count'] ?></span>
                </td>
                <td>
                    <?php
                    $rank = (int)$src['authority_rank'];
                    $rankClass = $rank <= 30 ? 'text-success' : ($rank <= 60 ? 'text-warning' : 'text-muted');
                    ?>
                    <span class="<?= $rankClass ?>"><?= $rank ?></span>
                </td>
                <td>
                    <?php if ($src['status'] === 'active'): ?>
                        <span class="badge bg-success"><?= __('common.status.active') ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><?= __('common.status.paused') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?page=sources&action=edit&id=<?= (int)$src['id'] ?>"
                           class="btn btn-outline-primary" title="<?= __('common.btn.edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($src['scrape_strategy'] === 'custom_parser'): ?>
                        <a href="?page=parsers&action=edit&source_id=<?= (int)$src['id'] ?>"
                           class="btn btn-outline-secondary" title="<?= __('sources.btn_parser') ?>">
                            <i class="bi bi-code-slash"></i>
                        </a>
                        <?php endif; ?>
                        <form method="post" action="?page=sources&action=toggle" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$src['id'] ?>">
                            <button type="submit"
                                    class="btn btn-outline-<?= $src['status'] === 'active' ? 'warning' : 'success' ?>"
                                    title="<?= $src['status'] === 'active' ? __('sources.btn_pause') : __('sources.btn_activate') ?>">
                                <i class="bi bi-<?= $src['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                            </button>
                        </form>
                        <form method="post" action="?page=sources&action=delete" class="d-inline"
                              onsubmit="return confirm('<?= __('sources.msg_delete_confirm', ['name' => htmlspecialchars($src['name'])]) ?>')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$src['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger" title="<?= __('common.btn.delete') ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
