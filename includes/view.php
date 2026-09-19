<?php
/**
 * TaskFlow — View layer: layout, sidebar, topbar, modals and partials
 */

/** Render a page inside the main application layout. */
function render(string $view, array $data = [], string $title = '', string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);

    $GLOBALS['PAGE']         = query('page', 'dashboard');
    $GLOBALS['PAGE_ID']      = (int)query('id', 0);
    $GLOBALS['PAGE_TITLE']   = $title !== '' ? $title : ucwords(str_replace(['-', '_'], ' ', $view));
    $GLOBALS['APP_USER']     = current_user();
    $GLOBALS['UNREAD']       = unread_notifications_count();
    $GLOBALS['COMPANY']      = (string)setting('company_name', APP_NAME);
    $GLOBALS['COMPANY_LOGO'] = (string)setting('company_logo', '');
    $GLOBALS['VIEW_PATH']    = PAGES_PATH . '/' . $view . '.php';

    if (!is_file($GLOBALS['VIEW_PATH'])) {
        http_response_code(404);
        $GLOBALS['VIEW_PATH'] = PAGES_PATH . '/404.php';
        $GLOBALS['PAGE_TITLE'] = 'Page not found';
    }

    require INCLUDES_PATH . '/views/' . $layout . '.php';
}

/** Render a bare page (login / register / setup) with no sidebar. */
function render_blank(string $view, array $data = [], string $title = ''): void
{
    render($view, $data, $title, 'blank');
}

/* ========================= Partials ========================= */

function sidebar_menu(): array
{
    return [
        [
            'group' => t('Workspace'),
            'items' => [
                ['page' => 'dashboard', 'label' => t('Dashboard'),  'icon' => 'dashboard', 'perm' => 'dashboard.view'],
                ['page' => 'board',     'label' => t('Task Board'), 'icon' => 'board',     'perm' => 'tasks.view'],
                ['page' => 'tasks',     'label' => t('All Tasks'),  'icon' => 'tasks',     'perm' => 'tasks.view'],
                ['page' => 'projects',  'label' => t('Projects'),   'icon' => 'projects',  'perm' => 'projects.view'],
                ['page' => 'team',      'label' => t('Team'),       'icon' => 'team',      'perm' => 'users.view'],
                ['page' => 'departments','label'=> 'Departments','icon' => 'building',  'perm' => 'departments.view'],
            ],
        ],
        [
            'group' => t('Insights'),
            'items' => [
                ['page' => 'reports',   'label' => t('Reports'),    'icon' => 'reports',   'perm' => 'reports.view'],
                ['page' => 'activity',  'label' => t('Activity Log'),'icon' => 'activity', 'perm' => 'activity.view'],
                ['page' => 'ux-audit',  'label' => t('UX Audit'),   'icon' => 'analytics', 'perm' => 'settings.system'],
            ],
        ],
        [
            'group' => t('Administration'),
            'items' => [
                ['page' => 'roles',     'label' => t('Roles & Permissions'), 'icon' => 'shield', 'perm' => 'roles.view'],
                ['page' => 'settings',  'label' => t('Settings'),   'icon' => 'settings',  'perm' => ['settings.company', 'settings.profile']],
            ],
        ],
    ];
}

/** Highlight the active menu entry (also for detail pages). */
function nav_is_active(string $page): bool
{
    $current = $GLOBALS['PAGE'] ?? '';
    if ($current === $page) {
        return true;
    }
    $map = [
        'board'    => ['task', 'board'],
        'tasks'    => ['task', 'tasks'],
        'projects' => ['project', 'projects'],
        'team'     => ['team', 'profile'],
        'settings' => ['settings', 'profile'],
    ];
    foreach ($map as $nav => $pages) {
        if ($nav === $page && in_array($current, $pages, true)) {
            return true;
        }
    }
    return false;
}

function breadcrumbs(array $items): string
{
    $out = '<nav class="breadcrumbs">';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($i === $last || empty($item['url'])) {
            $out .= '<span class="crumb current">' . e(t($item['label'])) . '</span>';
        } else {
            $out .= '<a class="crumb" href="' . e($item['url']) . '">' . e(t($item['label'])) . '</a>';
            $out .= icon('chevron-right', 14, 'crumb-sep');
        }
    }
    return $out . '</nav>';
}

function page_header(string $title, string $subtitle = '', string $actionsHtml = '', string $crumbs = ''): string
{
    return '<div class="page-head">'
         . '<div class="page-head-text">' . ($crumbs ?: '') . '<h1>' . e(t($title)) . '</h1>'
         . ($subtitle !== '' ? '<p class="page-subtitle">' . t($subtitle) . '</p>' : '')
         . '</div>'
         . ($actionsHtml !== '' ? '<div class="page-head-actions">' . $actionsHtml . '</div>' : '')
         . '</div>';
}

/* ========================= Form helpers ========================= */

function form_row(string $label, string $fieldHtml, string $hint = '', string $error = '', bool $required = false): string
{
    return '<div class="form-row' . ($error !== '' ? ' has-error' : '') . '">'
         . '<label class="form-label">' . e(t($label)) . ($required ? ' <span class="req">*</span>' : '') . '</label>'
         . $fieldHtml
         . ($hint !== '' ? '<small class="form-hint">' . t($hint) . '</small>' : '')
         . ($error !== '' ? '<small class="form-error">' . e($error) . '</small>' : '')
         . '</div>';
}

function select_options(array $options, $selected, string $valueKey = '', string $labelKey = ''): string
{
    $out = '';
    foreach ($options as $key => $value) {
        if (is_array($value)) {
            $k = $valueKey !== '' ? $value[$valueKey] : $key;
            $l = $labelKey !== '' ? $value[$labelKey] : ($value['label'] ?? $value['name'] ?? $k);
        } else {
            $k = $key;
            $l = $value;
        }
        $out .= '<option value="' . e((string)$k) . '"' . ((string)$k === (string)$selected ? ' selected' : '') . '>' . e(t((string)$l)) . '</option>';
    }
    return $out;
}

function checked($value, $expected): string
{
    return (string)$value === (string)$expected ? ' checked' : '';
}

/* ========================= Data helpers ========================= */

/** Map of user id => user row (for avatars / names in lists). */
function users_map(array $ids = []): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Db::all('SELECT id, name, email, color, avatar, job_title, department_id FROM users') as $u) {
            $cache[(int)$u['id']] = $u;
        }
    }
    if (!$ids) {
        return $cache;
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($cache[(int)$id])) {
            $out[(int)$id] = $cache[(int)$id];
        }
    }
    return $out;
}

function projects_map(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Db::all('SELECT id, name, code, color, status FROM projects') as $p) {
            $cache[(int)$p['id']] = $p;
        }
    }
    return $cache;
}

function departments_map(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Db::all('SELECT id, name, code, color FROM departments') as $d) {
            $cache[(int)$d['id']] = $d;
        }
    }
    return $cache;
}

function user_by_id(int $id): ?array
{
    return users_map()[$id] ?? null;
}

/** Progress bar HTML */
function progress_bar(int $percent, string $color = 'var(--primary)', string $extraClass = ''): string
{
    $percent = max(0, min(100, $percent));
    return '<div class="progress ' . $extraClass . '" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100">'
         . '<span style="width:' . $percent . '%;background:' . e($color) . '"></span></div>';
}

function empty_state(string $icon, string $title, string $text, string $actionHtml = ''): string
{
    return '<div class="empty-state">' . icon($icon, 42) . '<h3>' . e($title) . '</h3><p>' . e($text) . '</p>'
         . ($actionHtml ?: '') . '</div>';
}
