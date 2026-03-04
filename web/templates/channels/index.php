<?php
/**
 * Channels list template.
 * Variables: $channels
 */

use function NewsBot\Web\Helpers\__;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-megaphone"></i> <?= __('channels.title') ?></h2>
    <a href="?page=channels&action=edit" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> <?= __('channels.create') ?>
    </a>
</div>

<?php if (empty($channels)): ?>
<div class="alert alert-info">
    <p class="mb-0"><?= __('channels.msg_no_channels') ?> <a href="?page=channels&action=edit"><?= __('channels.create') ?></a>.</p>
</div>
<?php else: ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th><?= __('common.label.id') ?></th>
                <th><?= __('channels.col_name') ?></th>
                <th><?= __('channels.col_bot') ?></th>
                <th><?= __('channels.col_chat_id') ?></th>
                <th><?= __('channels.col_sources') ?></th>
                <th><?= __('common.time.today') ?> / Limit</th>
                <th><?= __('channels.col_status') ?></th>
                <th><?= __('common.btn.actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($channels as $ch): ?>
            <tr>
                <td><?= (int)$ch['id'] ?></td>
                <td>
                    <a href="?page=channels&action=edit&id=<?= (int)$ch['id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($ch['name']) ?>
                    </a>
                    <?php if ($ch['manual_review_enabled']): ?>
                        <span class="badge bg-info text-dark ms-1" title="<?= __('dashboard.manual_review') ?>">
                            <i class="bi bi-eye"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($ch['bot_name']): ?>
                        <span class="text-muted"><?= htmlspecialchars($ch['bot_name']) ?></span>
                    <?php else: ?>
                        <span class="text-danger"><?= __('channels.msg_select_bot') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <code class="small"><?= htmlspecialchars($ch['chat_id'] ?? '') ?></code>
                </td>
                <td>
                    <span class="badge bg-secondary"><?= (int)$ch['source_count'] ?></span>
                </td>
                <td>
                    <?php
                    $published = (int)$ch['published_today'];
                    $max = (int)$ch['max_per_day'];
                    $pct = $max > 0 ? round(($published / $max) * 100) : 0;
                    $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                    ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small"><?= $published ?> / <?= $max ?></span>
                        <div class="progress flex-grow-1" style="height: 6px; min-width: 50px;">
                            <div class="progress-bar <?= $barClass ?>" style="width: <?= min($pct, 100) ?>%"></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($ch['status'] === 'active'): ?>
                        <span class="badge bg-success"><?= __('common.status.active') ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><?= __('common.status.paused') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?page=channels&action=edit&id=<?= (int)$ch['id'] ?>"
                           class="btn btn-outline-primary" title="<?= __('common.btn.edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" action="?page=channels&action=toggle" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$ch['id'] ?>">
                            <button type="submit"
                                    class="btn btn-outline-<?= $ch['status'] === 'active' ? 'warning' : 'success' ?>"
                                    title="<?= $ch['status'] === 'active' ? __('common.status.paused') : __('common.status.active') ?>">
                                <i class="bi bi-<?= $ch['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                            </button>
                        </form>
                        <form method="post" action="?page=channels&action=delete" class="d-inline"
                              onsubmit="return confirm('<?= __('channels.msg_delete_confirm') ?>')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$ch['id'] ?>">
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
