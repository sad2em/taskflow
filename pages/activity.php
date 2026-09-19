<?php
/**
 * TaskFlow — Activity / audit log
 */

$action  = (string)query('action', '');
$actor   = int_input('user', 0);
$entity  = (string)query('entity', '');
$from    = (string)query('from', '');
$to      = (string)query('to', '');
$perPage = 25;
$page    = max(1, int_input('p', 1));

$where  = ['1=1'];
$params = [];

if ($action !== '')  { $where[] = 'action LIKE ?'; $params[] = $action . '%'; }
if ($actor > 0)      { $where[] = 'user_id = ?'; $params[] = $actor; }
if ($entity !== '')  { $where[] = 'entity_type = ?'; $params[] = $entity; }
if ($from !== '' && strtotime($from)) { $where[] = 'created_at >= ?'; $params[] = date('Y-m-d 00:00:00', strtotime($from)); }
if ($to !== '' && strtotime($to))     { $where[] = 'created_at <= ?'; $params[] = date('Y-m-d 23:59:59', strtotime($to)); }
$whereSql = implode(' AND ', $where);

$total = Db::count("SELECT COUNT(*) FROM activity_logs WHERE {$whereSql}", $params);
$pg = paginate($total, $perPage, $page);

$logs = Db::all(
    "SELECT l.*, u.color AS user_color, u.avatar AS user_avatar
     FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
     WHERE {$whereSql}
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$actionGroups = [];
foreach (Db::all('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC') as $row) {
    $module = explode('.', (string)$row['action'])[0];
    $actionGroups[$module][] = $row['action'];
}
$entityTypes = array_column(Db::all("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type ASC"), 'entity_type');
$actors = Db::all("SELECT id, name, color, avatar FROM users ORDER BY name ASC");

if (query('export') === 'csv' && can('reports.export')) {
    $rows = [['When', 'User', 'Action', 'Entity', 'Entity ID', 'Description', 'IP']];
    foreach (Db::all("SELECT * FROM activity_logs WHERE {$whereSql} ORDER BY created_at DESC LIMIT 5000", $params) as $l) {
        $rows[] = [$l['created_at'], $l['user_name'] ?: 'System', $l['action'], $l['entity_type'] ?: '', $l['entity_id'] ?: '', $l['description'] ?: '', $l['ip_address'] ?: ''];
    }
    csv_download('taskflow-activity-' . date('Y-m-d') . '.csv', $rows);
}

$iconFor = static function (string $action): string {
    if (str_contains($action, 'login'))    { return 'login'; }
    if (str_contains($action, 'logout'))   { return 'logout'; }
    if (str_contains($action, 'delete'))   { return 'trash'; }
    if (str_contains($action, 'create'))   { return 'plus'; }
    if (str_contains($action, 'assign'))   { return 'user'; }
    if (str_contains($action, 'comment'))  { return 'message'; }
    if (str_contains($action, 'complete')) { return 'check-circle'; }
    if (str_contains($action, 'move'))     { return 'refresh'; }
    if (str_contains($action, 'settings')) { return 'settings'; }
    if (str_contains($action, 'attach'))   { return 'paperclip'; }
    if (str_contains($action, 'time'))     { return 'clock'; }
    return 'edit';
};

$queryBase = array_filter(['action' => $action, 'user' => $actor ?: '', 'entity' => $entity, 'from' => $from, 'to' => $to], static fn($v) => $v !== '');
?>
<?= page_header(
    'Activity Log',
    $total . ' recorded events — a full audit trail of what happened, who did it and when',
    can('reports.export')
        ? '<a class="btn btn-ghost btn-sm" href="' . e(url('index.php', array_merge(['page' => 'activity'], $queryBase, ['export' => 'csv']))) . '">' . icon('download', 15) . ' Export CSV</a>'
        : '',
    breadcrumbs([['label' => 'Insights', 'url' => page_url('dashboard')], ['label' => 'Activity Log']])
) ?>

<form class="filters-bar" method="get" action="<?= e(url('index.php')) ?>">
    <input type="hidden" name="page" value="activity">
    <div class="filter-field"><?= icon('activity', 15) ?>
        <select name="action">
            <option value="">All actions</option>
            <?php foreach ($actionGroups as $module => $actions): ?>
                <optgroup label="<?= e(ucfirst($module)) ?>">
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(str_replace('.', ' → ', $a)) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field"><?= icon('user', 15) ?>
        <select name="user"><option value="">Everyone</option><?= select_options($actors, $actor, 'id', 'name') ?></select>
    </div>
    <div class="filter-field"><?= icon('database', 15) ?>
        <select name="entity">
            <option value="">Any object</option>
            <?php foreach ($entityTypes as $et): ?>
                <option value="<?= e($et) ?>" <?= $entity === $et ? 'selected' : '' ?>><?= e(ucfirst($et)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field"><?= icon('calendar', 15) ?><input class="input-flat" type="date" name="from" value="<?= e($from) ?>" title="From"></div>
    <div class="filter-field"><?= icon('calendar', 15) ?><input class="input-flat" type="date" name="to" value="<?= e($to) ?>" title="To"></div>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('filter', 15) ?> Apply</button>
    <?php if ($queryBase): ?><a class="btn btn-ghost btn-sm" href="<?= e(page_url('activity')) ?>"><?= icon('x', 15) ?> Reset</a><?php endif; ?>
</form>

<div class="card"><div class="card-body no-pad">
    <?php if (!$logs): ?>
        <?= empty_state('activity', 'No activity found', 'Nothing matches these filters yet.') ?>
    <?php else: ?>
        <ul class="log-list">
            <?php foreach ($logs as $l): ?>
                <li class="log-item" data-action="<?= e($l['action']) ?>">
                    <span class="log-icon"><?= icon($iconFor((string)$l['action']), 15) ?></span>
                    <div class="log-main">
                        <p>
                            <strong><?= e($l['user_name'] ?: 'System') ?></strong>
                            <?= e((string)$l['description'] ?: str_replace(['.', '_'], ' ', (string)$l['action'])) ?>
                            <?php if ($l['entity_type'] && $l['entity_id']): ?>
                                <span class="chip chip-muted sm-chip"><?= e($l['entity_type']) ?> #<?= (int)$l['entity_id'] ?></span>
                            <?php endif; ?>
                        </p>
                        <div class="log-meta">
                            <time title="<?= e(format_date($l['created_at'], 'M j, Y H:i:s')) ?>"><?= e(time_ago($l['created_at'])) ?></time>
                            <?php if ($l['ip_address']): ?><span><?= icon('shield', 11) ?> <?= e($l['ip_address']) ?></span><?php endif; ?>
                            <span class="chip chip-muted sm-chip"><?= e($l['action']) ?></span>
                        </div>
                    </div>
                    <?php if ($l['user_id']): ?>
                        <?= avatar_html($l, 28) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php if ($pg['pages'] > 1): ?><div class="card-foot"><?= pagination_html($pg, 'index.php', array_merge(['page' => 'activity'], $queryBase)) ?></div><?php endif; ?>
</div>
