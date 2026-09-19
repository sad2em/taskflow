<?php
/**
 * TaskFlow — Main application layout (sidebar + topbar + content)
 * Variables available: $GLOBALS['PAGE'], $GLOBALS['PAGE_TITLE'], $GLOBALS['APP_USER'], ...
 */
$__user    = $GLOBALS['APP_USER'] ?? null;
$__unread  = (int)($GLOBALS['UNREAD'] ?? 0);
$__company = $GLOBALS['COMPANY'] ?? APP_NAME;
$__page    = $GLOBALS['PAGE'] ?? 'dashboard';
$__theme   = (string)($_SESSION['theme'] ?? '');
if ($__theme !== 'dark' && $__theme !== 'light') {
    $__theme = (string)setting('theme', 'light') === 'dark' ? 'dark' : 'light';
}
$__crumbs  = $GLOBALS['BREADCRUMBS'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-theme="<?= e($__theme) ?>">
<head>
<meta charset="UTF-8">
<script>(function(){try{var t=localStorage.getItem('taskflow_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);document.documentElement.style.colorScheme=t;}else if(t!==null){localStorage.removeItem('taskflow_theme');}}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(APP_BASE_URL) ?>">
<?php if (locale() === 'ar'): ?>
<script>window.TASKFLOW_TRANSLATIONS = <?= json_encode(locale_translations(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php endif; ?>
<title><?= e($__page === 'dashboard' ? $__company : $GLOBALS['PAGE_TITLE'] . ' · ' . $__company) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%234f46e5'/><path d='M9 17l4 4 10-11' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="app-body<?= $__theme === 'dark' ? ' theme-dark' : '' ?>">

<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="<?= e(t('Toggle navigation')) ?>"><?= icon('menu', 20) ?></button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark"><?= icon('check', 18) ?></span>
        <span class="brand-text"><?= e($__company) ?></span>
        <span class="brand-version">v<?= e(APP_VERSION) ?></span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach (sidebar_menu() as $section): ?>
            <?php
            $visible = array_values(array_filter($section['items'], static fn($i) => can($i['perm'])));
            if (!$visible) { continue; }
            ?>
            <div class="nav-section">
                <span class="nav-section-title"><?= e($section['group']) ?></span>
                <?php foreach ($visible as $item): ?>
                    <a class="nav-link<?= nav_is_active($item['page']) ? ' active' : '' ?>"
                       href="<?= e(page_url($item['page'])) ?>">
                        <?= icon($item['icon'], 18) ?>
                        <span><?= e($item['label']) ?></span>
                        <?php if ($item['page'] === 'tasks'): ?>
                            <?php
                            $__scope = scope_tasks_sql('t');
                            $__open  = Db::count(
                                "SELECT COUNT(*) FROM tasks t WHERE t.is_archived = 0 AND t.parent_id IS NULL AND t.status <> 'done' AND " . $__scope['sql'],
                                $__scope['params']
                            );
                            ?>
                            <?php if ($__open > 0): ?><span class="nav-pill"><?= $__open ?></span><?php endif; ?>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <div class="sidebar-user">
            <?= avatar_html($__user, 36) ?>
            <div class="sidebar-user-meta">
                <strong><?= e($__user['name'] ?? '') ?></strong>
                <span><?= e($__user['role_name'] ?? '') ?></span>
            </div>
        </div>
        <div class="sidebar-actions">
            <button class="icon-btn" id="themeToggle" title="<?= e(t('Toggle dark mode')) ?>" aria-label="<?= e(t('Toggle dark mode')) ?>">
                <?= icon($__theme === 'dark' ? 'sun' : 'moon', 17) ?>
            </button>
            <a class="icon-btn" href="<?= e(page_url('settings', ['tab' => 'profile'])) ?>" title="<?= e(t('My profile')) ?>" aria-label="<?= e(t('My profile')) ?>"><?= icon('user', 17) ?></a>
            <a class="icon-btn danger" href="<?= e(page_url('logout')) ?>" title="<?= e(t('Sign out')) ?>" aria-label="<?= e(t('Sign out')) ?>"><?= icon('logout', 17) ?></a>
        </div>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-left">
            <h1 class="topbar-title"><?= e($GLOBALS['PAGE_TITLE']) ?></h1>
        </div>

        <div class="topbar-center">
            <div class="global-search">
                <?= icon('search', 16) ?>
                <input type="search" id="globalSearch" placeholder="<?= e(t('Search tasks, projects, people…')) ?>" autocomplete="off">
                <kbd>/</kbd>
                <div class="search-results" id="searchResults" hidden></div>
            </div>
        </div>

        <div class="topbar-right">
            <?php if (can('tasks.create')): ?>
                <button class="btn btn-primary btn-sm" data-modal-open="taskModal">
                    <?= icon('plus', 15) ?><span><?= e(t('New task')) ?></span>
                </button>
            <?php endif; ?>

            <div class="dropdown" id="notifDropdown">
                <button class="icon-btn bell-btn" data-dropdown-toggle aria-label="<?= e(t('Notifications')) ?>">
                    <?= icon('bell', 18) ?>
                    <?php if ($__unread > 0): ?><span class="bell-count"><?= $__unread > 9 ? '9+' : $__unread ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-notif" data-dropdown-menu>
                    <div class="dropdown-head">
                        <strong><?= e(t('Notifications')) ?></strong>
                        <?php if ($__unread > 0): ?><button class="link-btn" id="markAllRead"><?= e(t('Mark all read')) ?></button><?php endif; ?>
                    </div>
                    <div class="dropdown-body" id="notifList">
                        <div class="dropdown-loading"><?= icon('loader', 18, 'spin') ?> <?= e(t('Loading…')) ?></div>
                    </div>
                    <a class="dropdown-foot" href="<?= e(page_url('notifications')) ?>"><?= e(t('View all notifications')) ?></a>
                </div>
            </div>

            <div class="dropdown" id="userDropdown">
                <button class="user-btn" data-dropdown-toggle aria-label="<?= e(t('Account menu')) ?>">
                    <?= avatar_html($__user, 30) ?>
                    <?= icon('chevron-down', 14) ?>
                </button>
                <div class="dropdown-menu dropdown-user" data-dropdown-menu>
                    <div class="dropdown-user-head">
                        <?= avatar_html($__user, 40) ?>
                        <div>
                            <strong><?= e($__user['name'] ?? '') ?></strong>
                            <span><?= e($__user['email'] ?? '') ?></span>
                        </div>
                    </div>
                    <a href="<?= e(page_url('profile', ['id' => $__user['id'] ?? 0])) ?>"><?= icon('user', 16) ?> <?= e(t('My profile')) ?></a>
                    <a href="<?= e(page_url('tasks', ['mine' => 1])) ?>"><?= icon('tasks', 16) ?> <?= e(t('My tasks')) ?></a>
                    <?php if (can('settings.profile')): ?>
                        <a href="<?= e(page_url('settings', ['tab' => 'profile'])) ?>"><?= icon('settings', 16) ?> <?= e(t('Settings')) ?></a>
                    <?php endif; ?>
                    <div class="dropdown-sep"></div>
                    <a href="<?= e(page_url('logout')) ?>" class="danger"><?= icon('logout', 16) ?> <?= e(t('Sign out')) ?></a>
                </div>
            </div>
        </div>
    </header>

    <main class="main" id="main">
        <div class="flash-zone" id="flashZone"><?= render_flashes() ?></div>
        <?php require $__viewPath ?? $GLOBALS['VIEW_PATH']; ?>
    </main>

    <footer class="app-footer">
        <span>&copy; <?= date('Y') ?> <?= e($__company) ?> — powered by TaskFlow <?= e(APP_VERSION) ?></span>
        <span><?= e($__user['role_name'] ?? '') ?> · scope: <?= e(data_scope()) ?></span>
    </footer>
</div>

<?php /* ---------- Shared modals ---------- */ ?>
<div class="modal-root" id="modalRoot" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-head">
            <h2 id="modalTitle">Title</h2>
            <button class="icon-btn" data-modal-close aria-label="<?= e(t('Close')) ?>"><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<?php if (can('tasks.create')): require INCLUDES_PATH . '/views/modals.php'; endif; ?>

<div class="toast-zone" id="toastZone" aria-live="polite"></div>

<!-- UX Enhancements Script -->
<script src="<?= e(asset('js/select-menu.js')) ?>"></script>
<script src="<?= e(asset('js/ux-enhancements.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/i18n.js')) ?>"></script>
<?php if (in_array($__page, ['board'], true)): ?><script src="<?= e(asset('js/kanban.js')) ?>"></script><?php endif; ?>
<?php if (in_array($__page, ['dashboard', 'reports', 'project', 'profile', 'ux-audit'], true)): ?><script src="<?= e(asset('js/charts.js')) ?>"></script><?php endif; ?>
</body>
</html>