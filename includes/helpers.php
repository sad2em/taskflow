<?php
/**
 * TaskFlow — Global helper functions
 */

/* ============================ Output / URLs ============================ */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '', array $params = []): string
{
    $path = ltrim($path, '/');
    $base = APP_BASE_URL;
    $full = $base === '' ? '/' . $path : $base . '/' . $path;
    if ($params) {
        $full .= (strpos($full, '?') === false ? '?' : '&') . http_build_query($params);
    }
    return $full;
}

function page_url(string $page, array $params = []): string
{
    return url('index.php', array_merge(['page' => $page], $params));
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . APP_VERSION;
}

function upload_url(string $file): string
{
    return url('uploads/' . ltrim($file, '/'));
}

function redirect(string $to): void
{
    if (!headers_sent()) {
        header('Location: ' . $to);
    }
    echo '<script>window.location.href=' . json_encode($to) . ';</script>';
    exit;
}

function back(): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : url('index.php'));
}

function json_response($data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ============================ Request input ============================ */

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function input(string $key, $default = null)
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function post(string $key, $default = null)
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function query(string $key, $default = null)
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function int_input(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_numeric($value) ? (int)$value : $default;
}

function bool_input(string $key): bool
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true);
}

function array_input(string $key): array
{
    $value = $_POST[$key] ?? $_GET[$key] ?? [];
    return is_array($value) ? $value : [];
}

function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', (string)$_SERVER[$key])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

/* ============================ Flash messages ============================ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_success(string $m): void { flash('success', $m); }
function flash_error(string $m): void   { flash('error', $m); }
function flash_info(string $m): void    { flash('info', $m); }

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_flashes(): string
{
    $out = '';
    foreach (get_flashes() as $f) {
        $icon = ['success' => 'check-circle', 'error' => 'alert-triangle', 'info' => 'info'][$f['type']] ?? 'info';
        $out .= '<div class="alert alert-' . e($f['type']) . '">'
              . icon($icon, 18) . '<span>' . e($f['message']) . '</span>'
              . '<button type="button" class="alert-close" data-dismiss="alert" aria-label="Close">&times;</button>'
              . '</div>';
    }
    return $out;
}

/* ============================ Formatting ============================ */

function format_date($date, string $format = 'M j, Y'): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }
    try {
        $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
        return $ts ? date($format, $ts) : '—';
    } catch (Throwable $e) {
        return '—';
    }
}

function time_ago($datetime): string
{
    if (empty($datetime)) {
        return '—';
    }
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime((string)$datetime);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 0) {
        return 'in ' . human_duration(-$diff);
    }
    if ($diff < 60)      { return 'just now'; }
    if ($diff < 3600)    { return floor($diff / 60) . 'm ago'; }
    if ($diff < 86400)   { return floor($diff / 3600) . 'h ago'; }
    if ($diff < 604800)  { return floor($diff / 86400) . 'd ago'; }
    if ($diff < 2592000) { return floor($diff / 604800) . 'w ago'; }
    return date('M j, Y', $ts);
}

function human_duration(int $seconds): string
{
    if ($seconds < 60)    { return $seconds . 's'; }
    if ($seconds < 3600)  { return floor($seconds / 60) . 'm'; }
    if ($seconds < 86400) { return floor($seconds / 3600) . 'h'; }
    return floor($seconds / 86400) . 'd';
}

function format_bytes(int $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : $precision) . ' ' . $units[$i];
}

function format_hours($h): string
{
    $h = (float)$h;
    if ($h <= 0) { return '—'; }
    return rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.') . 'h';
}

function days_until($date): ?int
{
    if (empty($date)) { return null; }
    $ts = strtotime((string)$date . ' 23:59:59');
    if (!$ts) { return null; }
    return (int)floor(($ts - time()) / 86400);
}

/** Portable date literal for SQL (works on MySQL, MariaDB and SQLite). */
function sql_date(?string $when = null): string
{
    $ts = $when ? strtotime($when) : time();
    return "'" . date('Y-m-d', $ts ?: time()) . "'";
}

function sql_datetime(?string $when = null): string
{
    $ts = $when ? strtotime($when) : time();
    return "'" . date('Y-m-d H:i:s', $ts ?: time()) . "'";
}

function excerpt(?string $text, int $length = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)) ?? '');
    if (mb_strlen($text) <= $length) { return $text; }
    return mb_substr($text, 0, $length - 1) . '…';
}

function initials(string $name): string
{
    $parts = preg_split('/[\s\-_.]+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts));
    if (!$parts) { return '?'; }
    $out = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $out .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $out;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Db::all('SELECT `key`, `value` FROM settings') as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function save_setting(string $key, $value): void
{
    if (Db::isSqlite()) {
        Db::run('INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`', [$key, (string)$value]);
        return;
    }
    Db::run('INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, (string)$value]);
}

/* ============================ Enums / labels ============================ */

function task_statuses(): array
{
    return [
        'backlog'     => ['label' => t('Backlog'),     'color' => '#94a3b8', 'icon' => 'inbox'],
        'todo'        => ['label' => t('To Do'),       'color' => '#3b82f6', 'icon' => 'circle'],
        'in_progress' => ['label' => t('In Progress'), 'color' => '#f59e0b', 'icon' => 'loader'],
        'in_review'   => ['label' => t('In Review'),   'color' => '#8b5cf6', 'icon' => 'eye'],
        'done'        => ['label' => t('Done'),        'color' => '#10b981', 'icon' => 'check-circle'],
        'blocked'     => ['label' => t('Blocked'),     'color' => '#ef4444', 'icon' => 'slash'],
    ];
}

function board_columns(): array
{
    return ['backlog', 'todo', 'in_progress', 'in_review', 'done'];
}

function priorities(): array
{
    return [
        'low'      => ['label' => t('Low'),      'color' => '#64748b', 'weight' => 1],
        'medium'   => ['label' => t('Medium'),   'color' => '#3b82f6', 'weight' => 2],
        'high'     => ['label' => t('High'),     'color' => '#f59e0b', 'weight' => 3],
        'critical' => ['label' => t('Critical'), 'color' => '#ef4444', 'weight' => 4],
    ];
}

function project_statuses(): array
{
    return [
        'planning'  => ['label' => t('Planning'),  'color' => '#94a3b8'],
        'active'    => ['label' => t('Active'),    'color' => '#10b981'],
        'on_hold'   => ['label' => t('On Hold'),   'color' => '#f59e0b'],
        'completed' => ['label' => t('Completed'), 'color' => '#3b82f6'],
        'cancelled' => ['label' => t('Cancelled'), 'color' => '#ef4444'],
    ];
}

function status_badge(string $status): string
{
    $s = task_statuses()[$status] ?? ['label' => ucfirst($status), 'color' => '#94a3b8'];
    return '<span class="badge" style="--badge-color:' . e($s['color']) . '">'
         . '<i class="dot"></i>' . e($s['label']) . '</span>';
}

function priority_badge(string $priority): string
{
    $p = priorities()[$priority] ?? ['label' => ucfirst($priority), 'color' => '#94a3b8'];
    return '<span class="badge badge-soft" style="--badge-color:' . e($p['color']) . '">'
         . icon('flag', 12) . e($p['label']) . '</span>';
}

function project_status_badge(string $status): string
{
    $s = project_statuses()[$status] ?? ['label' => ucfirst($status), 'color' => '#94a3b8'];
    return '<span class="badge" style="--badge-color:' . e($s['color']) . '"><i class="dot"></i>' . e($s['label']) . '</span>';
}

/* ============================ Avatars ============================ */

function avatar_html(?array $user, int $size = 32, string $extraClass = ''): string
{
    if (!$user) {
        return '<span class="avatar avatar-empty ' . $extraClass . '" style="width:' . $size . 'px;height:' . $size . 'px;'
             . 'font-size:' . max(9, (int)($size / 2.8)) . 'px">?</span>';
    }
    $color = $user['color'] ?? '#6366f1';
    $name  = $user['name'] ?? '?';
    $title = e($name . (isset($user['job_title']) && $user['job_title'] ? ' — ' . $user['job_title'] : ''));
    $style = 'width:' . $size . 'px;height:' . $size . 'px;font-size:' . max(9, (int)($size / 2.8)) . 'px;background:' . e($color) . ';';

    if (!empty($user['avatar']) && is_file(UPLOAD_PATH . '/' . $user['avatar'])) {
        return '<span class="avatar ' . $extraClass . '" title="' . $title . '" style="' . $style . '">'
             . '<img src="' . e(upload_url($user['avatar'])) . '" alt="' . e($name) . '"></span>';
    }
    return '<span class="avatar ' . $extraClass . '" title="' . $title . '" style="' . $style . '">'
         . e(initials($name)) . '</span>';
}

function avatar_stack(array $users, int $size = 26, int $max = 4): string
{
    $out   = '<div class="avatar-stack">';
    $shown = array_slice($users, 0, $max);
    foreach ($shown as $u) {
        $out .= avatar_html($u, $size);
    }
    $extra = count($users) - count($shown);
    if ($extra > 0) {
        $out .= '<span class="avatar avatar-more" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . max(9, (int)($size / 3)) . 'px">+' . $extra . '</span>';
    }
    return $out . '</div>';
}

/* ============================ Icons (inline SVG) ============================ */

function icon_paths(): array
{
    return [
        'dashboard'   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'board'       => '<rect x="3" y="3" width="5" height="18" rx="1.5"/><rect x="10" y="3" width="5" height="12" rx="1.5"/><rect x="17" y="3" width="4" height="8" rx="1.5"/>',
        'projects'    => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h3.2a2 2 0 0 1 1.6.8l1 1.4a2 2 0 0 0 1.6.8h4.6A2.5 2.5 0 0 1 21 10.5v6A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/>',
        'tasks'       => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
        'team'        => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6M17.5 14.4A6.2 6.2 0 0 1 21.2 20"/>',
        'building'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 21v-3h4v3"/>',
        'reports'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'activity'    => '<path d="M22 12h-4l-3 8-6-16-3 8H2"/>',
        'bell'        => '<path d="M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M13.7 20a2 2 0 0 1-3.4 0"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2v.2a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H1a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 2.6 7a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 7 2.6h.1A1.7 1.7 0 0 0 8.3 1V1a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 15 2.6a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'search'      => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'edit'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'       => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'check'       => '<path d="M20 6L9 17l-5-5"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'x'           => '<path d="M18 6L6 18M6 6l12 12"/>',
        'x-circle'    => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
        'alert-triangle' => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'user'        => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        'users'       => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5a3.5 3.5 0 0 1 0 7M17.5 14a6.5 6.5 0 0 1 4 6"/>',
        'shield'      => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9.5 12l2 2 3.5-4"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'login'       => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>',
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5L12 13l8.5-6.5"/>',
        'phone'       => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/>',
        'paperclip'   => '<path d="M21 11.5l-8.4 8.4a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/>',
        'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
        'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
        'filter'      => '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
        'chevron-down'=> '<path d="M6 9l6 6 6-6"/>',
        'chevron-right'=> '<path d="M9 6l6 6-6 6"/>',
        'chevron-left'=> '<path d="M15 6l-6 6 6 6"/>',
        'arrow-left'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'more'        => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
        'flag'        => '<path d="M4 21V4s1.5-1 4-1 4 2 7 2 4-1 4-1v11s-1.5 1-4 1-4-2-7-2-4 1-4 1"/>',
        'loader'      => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/>',
        'inbox'       => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
        'circle'      => '<circle cx="12" cy="12" r="9"/>',
        'eye'         => '<path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'slash'       => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
        'sun'         => '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.5 1.5M17.6 17.6l1.5 1.5M19.1 4.9l-1.5 1.5M6.4 17.6l-1.5 1.5"/>',
        'moon'        => '<path d="M21 13.5A9 9 0 1 1 10.5 3a7 7 0 0 0 10.5 10.5z"/>',
        'menu'        => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'list'        => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'key'         => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L20 3M17 6l2.5 2.5M14.5 8.5L17 11"/>',
        'link'        => '<path d="M10 13a5 5 0 0 0 7.5.5l2-2A5 5 0 0 0 12.5 4.5l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2A5 5 0 0 0 11.5 19.5l1.1-1.1"/>',
        'star'        => '<path d="M12 3l2.8 5.8 6.2.9-4.5 4.4 1 6.4-5.5-3-5.5 3 1-6.4L3 9.7l6.2-.9z"/>',
        'target'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4"/>',
        'zap'         => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'file'        => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        'image'       => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5-9 9"/>',
        'message'     => '<path d="M21 12a8 8 0 0 1-8 8H8l-5 3 1.6-4.4A8 8 0 1 1 21 12z"/>',
        'refresh'     => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/>',
        'external'    => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14L21 3"/>',
        'save'        => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'lock'        => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'send'        => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'trend-up'    => '<path d="M22 7l-8.5 8.5-4-4L2 19"/><path d="M16 7h6v6"/>',
        'database'    => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'briefcase'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M2 13h20"/>',
    ];
}

function icon(string $name, int $size = 18, string $class = ''): string
{
    $paths = icon_paths()[$name] ?? icon_paths()['circle'];
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<svg' . $cls . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true">' . $paths . '</svg>';
}

/* ============================ Pagination ============================ */

function paginate(int $total, int $perPage, int $currentPage): array
{
    $pages = max(1, (int)ceil($total / max(1, $perPage)));
    $currentPage = min(max(1, $currentPage), $pages);
    return [
        'total'   => $total,
        'per_page'=> $perPage,
        'page'    => $currentPage,
        'pages'   => $pages,
        'offset'  => ($currentPage - 1) * $perPage,
        'from'    => $total === 0 ? 0 : (($currentPage - 1) * $perPage) + 1,
        'to'      => min($total, $currentPage * $perPage),
    ];
}

function pagination_html(array $p, string $baseUrl, array $query = []): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $link = function (int $page) use ($baseUrl, $query) {
        return e(url($baseUrl, array_merge($query, ['p' => $page])));
    };
    $out = '<nav class="pagination"><span class="pagination-info">Showing ' . $p['from'] . '–' . $p['to'] . ' of ' . $p['total'] . '</span><div class="pagination-pages">';
    $out .= $p['page'] > 1 ? '<a href="' . $link($p['page'] - 1) . '" class="page-link">' . icon('chevron-left', 15) . '</a>' : '<span class="page-link disabled">' . icon('chevron-left', 15) . '</span>';

    $window = 1;
    $from = max(1, $p['page'] - $window);
    $to = min($p['pages'], $p['page'] + $window);
    if ($from > 1) { $out .= '<a href="' . $link(1) . '" class="page-link">1</a>' . ($from > 2 ? '<span class="page-dots">…</span>' : ''); }
    for ($i = $from; $i <= $to; $i++) {
        $out .= $i === $p['page'] ? '<span class="page-link active">' . $i . '</span>' : '<a href="' . $link($i) . '" class="page-link">' . $i . '</a>';
    }
    if ($to < $p['pages']) { $out .= ($to < $p['pages'] - 1 ? '<span class="page-dots">…</span>' : '') . '<a href="' . $link($p['pages']) . '" class="page-link">' . $p['pages'] . '</a>'; }

    $out .= $p['page'] < $p['pages'] ? '<a href="' . $link($p['page'] + 1) . '" class="page-link">' . icon('chevron-right', 15) . '</a>' : '<span class="page-link disabled">' . icon('chevron-right', 15) . '</span>';
    return $out . '</div></nav>';
}

/* ============================ Uploads ============================ */

function allowed_extensions(): array
{
    $list = (string)setting('allowed_extensions', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt,md,svg');
    return array_filter(array_map('trim', explode(',', strtolower($list))));
}

function max_upload_bytes(): int
{
    return (int)setting('max_upload_mb', 8) * 1024 * 1024;
}

/**
 * Handle one uploaded file. Returns ['ok'=>bool,'name'=>string,'error'=>string]
 */
function handle_upload(array $file, string $subDir = 'attachments'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file is larger than the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The file is larger than the allowed size.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Could not write the file to disk.',
        ];
        return ['ok' => false, 'name' => '', 'error' => $messages[$file['error']] ?? 'Upload failed.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'name' => '', 'error' => 'Invalid upload.'];
    }

    $max = max_upload_bytes();
    if ($file['size'] > $max) {
        return ['ok' => false, 'name' => '', 'error' => 'File exceeds the ' . round($max / 1048576) . ' MB limit.'];
    }

    $original = basename($file['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, allowed_extensions(), true)) {
        return ['ok' => false, 'name' => '', 'error' => 'File type .' . e($ext) . ' is not allowed.'];
    }

    $dir = UPLOAD_PATH . '/' . trim($subDir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $stored = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return ['ok' => false, 'name' => '', 'error' => 'Could not store the uploaded file.'];
    }
    @chmod($dir . '/' . $stored, 0644);

    return [
        'ok'          => true,
        'name'        => trim($subDir, '/') . '/' . $stored,
        'original'    => $original,
        'size'        => (int)$file['size'],
        'mime'        => $file['type'] ?: mime_content_type($dir . '/' . $stored) ?: 'application/octet-stream',
        'error'       => '',
    ];
}

/* ============================ CSV export ============================ */

function csv_download(string $filename, array $rows): void
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}
