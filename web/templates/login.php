<?php
/**
 * Login page template.
 * Variables: $error (optional)
 */

use function NewsBot\Web\Helpers\__;
?>
<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-dark text-white text-center">
                <h4 class="mb-0">
                    <i class="bi bi-newspaper"></i> <?= __('login.heading') ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="?page=login&action=login">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label"><?= __('login.field_username') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                   required autocomplete="username" autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label"><?= __('login.field_password') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> <?= __('login.btn_login') ?>
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-muted text-center small">
                NBW &copy; <?= date('Y') ?>
            </div>
        </div>
    </div>
</div>
