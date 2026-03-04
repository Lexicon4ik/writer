<?php
/**
 * Base layout template with Bootstrap 5.
 * Variables: $contentHtml, $page, $pageTitle (optional)
 */

use NewsBot\Web\Helpers\Lang;
use function NewsBot\Web\Helpers\__;

$pageTitle = $pageTitle ?? ucfirst($page ?? 'Dashboard');
$isLoggedIn = !empty($_SESSION['admin_id']);
$adminUsername = $_SESSION['admin_username'] ?? '';
$currentLocale = Lang::getLocale();
$availableLocales = Lang::getAvailableLocales();
?>
<!DOCTYPE html>
<html lang="<?= $currentLocale ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - NewsBot Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
</head>
<body>
    <?php if ($isLoggedIn): ?>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar bg-dark text-white p-3">
            <div class="mb-4">
                <h5 class="text-white mb-0">
                    <i class="bi bi-newspaper"></i> NewsBot
                </h5>
                <small class="text-muted" style="color: inherit !important;"><?= __('common.nav.admin_panel') ?></small>
            </div>

            <ul class="nav nav-pills flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'dashboard' ? 'active' : 'text-white' ?>" href="?page=dashboard">
                        <i class="bi bi-speedometer2"></i> <?= __('common.nav.dashboard') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'bots' ? 'active' : 'text-white' ?>" href="?page=bots">
                        <i class="bi bi-robot"></i> <?= __('common.nav.bots') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'websites' ? 'active' : 'text-white' ?>" href="?page=websites">
                        <i class="bi bi-globe2"></i> <?= __('common.nav.sites') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'channels' ? 'active' : 'text-white' ?>" href="?page=channels">
                        <i class="bi bi-megaphone"></i> <?= __('common.nav.channels') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'sources' ? 'active' : 'text-white' ?>" href="?page=sources">
                        <i class="bi bi-globe"></i> <?= __('common.nav.sources') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'parsers' ? 'active' : 'text-white' ?>" href="?page=parsers">
                        <i class="bi bi-code-slash"></i> <?= __('common.nav.parsers') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'articles' ? 'active' : 'text-white' ?>" href="?page=articles">
                        <i class="bi bi-newspaper"></i> <?= __('common.nav.articles') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'stats' ? 'active' : 'text-white' ?>" href="?page=stats">
                        <i class="bi bi-bar-chart"></i> <?= __('common.nav.stats') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') === 'settings' ? 'active' : 'text-white' ?>" href="?page=settings">
                        <i class="bi bi-gear"></i> <?= __('common.nav.settings') ?>
                    </a>
                </li>

                <li class="nav-item mt-4 pt-3 border-top border-secondary">
                    <a class="nav-link text-white-50" href="?page=login&action=logout">
                        <i class="bi bi-box-arrow-right"></i> <?= __('common.nav.logout') ?>
                    </a>
                </li>
            </ul>

            <div class="mt-auto pt-4">
                <small class="text-muted d-block">
                    <i class="bi bi-person"></i> <?= htmlspecialchars($adminUsername) ?>
                </small>
                <small class="text-muted d-block">
                    <i class="bi bi-clock"></i> <?= date('Y-m-d H:i') ?> UTC
                </small>
                <!-- Language switcher -->
                <div class="mt-2">
                    <?php foreach ($availableLocales as $code => $name): ?>
                        <?php if ($code !== $currentLocale): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['lang' => $code])) ?>"
                               class="text-muted small text-decoration-none" style="color: inherit !important;">
                                <?= htmlspecialchars($name) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-white small"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                        <?php if ($code !== array_key_last($availableLocales)): ?> | <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow-1 p-4 main-content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __('common.btn.close') ?>"></button>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <?= $contentHtml ?>
        </main>
    </div>
    <?php else: ?>
    <!-- Login page - no sidebar -->
    <main class="container">
        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?> alert-dismissible fade show mt-3" role="alert">
                <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __('common.btn.close') ?>"></button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?= $contentHtml ?>
    </main>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/app.js"></script>
</body>
</html>
