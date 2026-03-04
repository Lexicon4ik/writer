<?php
/**
 * Bots list template.
 * Variables: $bots
 */

use function NewsBot\Web\Helpers\__;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-robot"></i> <?= __('bots.title') ?></h2>
    <a href="?page=bots&action=edit" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> <?= __('bots.create') ?>
    </a>
</div>

<?php if (empty($bots)): ?>
<div class="alert alert-info">
    <p class="mb-0"><?= __('bots.msg_no_bots') ?> <a href="?page=bots&action=edit"><?= __('bots.create') ?></a>.</p>
</div>
<?php else: ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th><?= __('common.label.id') ?></th>
                <th><?= __('bots.col_name') ?></th>
                <th><?= __('common.label.type') ?></th>
                <th><?= __('bots.col_status') ?></th>
                <th><?= __('bots.col_channels') ?></th>
                <th><?= __('bots.col_created') ?></th>
                <th><?= __('common.btn.actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bots as $bot): ?>
            <tr>
                <td><?= (int)$bot['id'] ?></td>
                <td>
                    <a href="?page=bots&action=edit&id=<?= (int)$bot['id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($bot['name']) ?>
                    </a>
                </td>
                <td>
                    <?php if ($bot['type'] === 'publishing'): ?>
                        <span class="badge bg-primary">Publishing</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Service</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($bot['status'] === 'active'): ?>
                        <span class="badge bg-success"><?= __('common.status.active') ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><?= __('common.status.paused') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-info text-dark"><?= (int)$bot['channel_count'] ?></span>
                </td>
                <td>
                    <small class="text-muted"><?= date('Y-m-d H:i', strtotime($bot['created_at'])) ?></small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?page=bots&action=edit&id=<?= (int)$bot['id'] ?>"
                           class="btn btn-outline-primary" title="<?= __('common.btn.edit') ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" action="?page=bots&action=toggle" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$bot['id'] ?>">
                            <button type="submit"
                                    class="btn btn-outline-<?= $bot['status'] === 'active' ? 'warning' : 'success' ?>"
                                    title="<?= $bot['status'] === 'active' ? __('common.status.paused') : __('common.status.active') ?>">
                                <i class="bi bi-<?= $bot['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                            </button>
                        </form>
                        <?php if ((int)$bot['channel_count'] === 0): ?>
                        <form method="post" action="?page=bots&action=delete" class="d-inline"
                              onsubmit="return confirm('<?= __('bots.msg_delete_confirm') ?>')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)$bot['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger" title="<?= __('common.btn.delete') ?>">
                                <i class="bi bi-trash"></i>
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
