<?php
/**
 * TaskFlow — Reports & Analytics
 * Tabs: overview · team · projects · time
 */

$tab   = (string)query('tab', 'overview');
if (!in_array($tab, ['overview', 'team', 'projects', 'time'], true)) { $tab = 'overview'; }

$range = (int)query('days', 30);
if (!in_array($range, [7, 14, 30, 90], true)) { $range = 30; }

$ts    = scope_tasks_sql('t');
$ps    = scope_projects_sql('p');
$today = sql_date();
$since = sql_datetime("-{$range} days");

/* ---------------- Overview numbers ---------------- */
$totals = Db::one(
    "SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END),0) AS done,
        COALESCE(SUM(CASE WHEN t.status <> 'done' THEN 1 ELSE 0 END),0) AS open,
        COALESCE(SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END),0) AS blocked,
        COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < {$today} AND t.status <> 'done' THEN 1 ELSE 0 END),0) AS overdue,
        COALESCE(SUM(CASE WHEN t.created_at >= {$since} THEN 1 ELSE 0 END),0) AS created_in_range,
        COALESCE(SUM(CASE WHEN t.completed_at >= {$since} THEN 1 ELSE 0 END),0) AS completed_in_range,
        COALESCE(SUM(COALESCE(t.estimated_hours,0)),0) AS estimated,
        COALESCE(SUM(t.actual_hours),0) AS logged
     FROM tasks t WHERE t.is_archived = 0 AND t.parent_id IS NULL AND " . $ts['sql'],
    $ts['params']
) ?? [];

$completionRate = (int)($totals['total'] ?? 0) > 0 ? (int)round(((int)$totals['done'] / (int)$totals['total']) * 100) : 0;
$onTimeRate = 0;
$completedWithDate = Db::one(
    "SELECT COUNT(*) AS c, COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND DATE(t.completed_at) <= t.due_date THEN 1 ELSE 0 END),0) AS on_time,
            COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL THEN 1 ELSE 0 END),0) AS with_due
     FROM tasks t WHERE t.status = 'done' AND t.completed_at IS NOT NULL AND " . $ts['sql'],
    $ts['params']
) ?? [];
if ((int)($completedWithDate['with_due'] ?? 0) > 0) {
    $onTimeRate = (int)round(((int)$completedWithDate['on_time'] / (int)$completedWithDate['with_due']) * 100);
}

/* ---------------- Status / priority distribution ---------------- */
$statusCounts = array_fill_keys(array_keys(task_statuses()), 0);
foreach (Db::all('SELECT t.status, COUNT(*) c FROM tasks t WHERE t.is_archived = 0 AND t.parent_id IS NULL AND ' . $ts['sql'] . ' GROUP BY t.status', $ts['params']) as $row) {
    $statusCounts[$row['status']] = (int)$row['c'];
}
$priorityCounts = array_fill_keys(array_keys(priorities()), 0);
foreach (Db::all('SELECT t.priority, COUNT(*) c FROM tasks t WHERE t.is_archived = 0 AND t.parent_id IS NULL AND ' . $ts['sql'] . ' GROUP BY t.priority', $ts['params']) as $row) {
    $priorityCounts[$row['priority']] = (int)$row['c'];
}

/* ---------------- Throughput per day ---------------- */
$days = [];
for ($i = $range - 1; $i >= 0; $i--) { $days[date('Y-m-d', strtotime("-{$i} day"))] = ['created' => 0, 'done' => 0]; }
foreach (Db::all('SELECT DATE(t.created_at) d, COUNT(*) c FROM tasks t WHERE t.created_at >= ' . $since . ' AND ' . $ts['sql'] . ' GROUP BY DATE(t.created_at)', $ts['params']) as $row) {
    if (isset($days[$row['d']])) { $days[$row['d']]['created'] = (int)$row['c']; }
}
foreach (Db::all('SELECT DATE(t.completed_at) d, COUNT(*) c FROM tasks t WHERE t.completed_at >= ' . $since . ' AND ' . $ts['sql'] . ' GROUP BY DATE(t.completed_at)', $ts['params']) as $row) {
    if (isset($days[$row['d']])) { $days[$row['d']]['done'] = (int)$row['c']; }
}

/* ---------------- Team performance ---------------- */
$team = Db::all(
    "SELECT u.id, u.name, u.color, u.avatar, u.job_title, d.name AS department_name, r.name AS role_name,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS total,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.status = 'done' AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS done,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.status <> 'done' AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS open,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.is_archived = 0 AND t.parent_id IS NULL
                  AND t.due_date IS NOT NULL AND t.due_date < {$today} AND t.status <> 'done'),0) AS overdue,
        COALESCE((SELECT SUM(t.actual_hours) FROM tasks t WHERE t.assignee_id = u.id),0) AS logged,
        COALESCE((SELECT SUM(t.estimated_hours) FROM tasks t WHERE t.assignee_id = u.id),0) AS estimated,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.completed_at >= {$since}),0) AS done_in_range,
        COALESCE((SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id AND c.created_at >= {$since}),0) AS comments_in_range
     FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE u.status = 'active'
     ORDER BY done DESC, total DESC, u.name ASC"
);

/* ---------------- Project performance ---------------- */
$projects = Db::all(
    "SELECT p.*, d.name AS department_name, u.name AS owner_name,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS total,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS done,
        COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status <> 'done' AND t.is_archived = 0 AND t.parent_id IS NULL
                  AND t.due_date IS NOT NULL AND t.due_date < {$today}),0) AS overdue,
        COALESCE((SELECT SUM(t.actual_hours) FROM tasks t WHERE t.project_id = p.id),0) AS logged,
        COALESCE((SELECT SUM(COALESCE(t.estimated_hours,0)) FROM tasks t WHERE t.project_id = p.id),0) AS estimated,
        COALESCE((SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id),0) AS members_count
     FROM projects p
     LEFT JOIN departments d ON d.id = p.department_id
     LEFT JOIN users u ON u.id = p.owner_id
     WHERE " . $ps['sql'] . '
     ORDER BY p.progress DESC, p.name ASC',
    $ps['params']
);

/* ---------------- Time tracking summary ---------------- */
$timeByUser = Db::all(
    "SELECT u.id, u.name, u.color, u.avatar,
        COALESCE(SUM(tl.hours),0) AS hours, COUNT(tl.id) AS entries
     FROM users u LEFT JOIN time_logs tl ON tl.user_id = u.id AND tl.log_date >= " . sql_date("-{$range} days") . "
     WHERE u.status = 'active' GROUP BY u.id, u.name, u.color, u.avatar
     HAVING hours > 0 ORDER BY hours DESC LIMIT 10"
);

$timeByProject = Db::all(
    "SELECT p.id, p.name, p.code, p.color,
        COALESCE(SUM(t.actual_hours),0) AS logged, COALESCE(SUM(COALESCE(t.estimated_hours,0)),0) AS estimated
     FROM projects p LEFT JOIN tasks t ON t.project_id = p.id
     WHERE " . $ps['sql'] . '
     GROUP BY p.id, p.name, p.code, p.color
     HAVING logged > 0 OR estimated > 0 ORDER BY logged DESC',
    $ps['params']
);

/* ---------------- CSV export ---------------- */
if (query('export') === 'csv' && can('reports.export')) {
    $rows = [];
    if ($tab === 'team') {
        $rows[] = ['Member', 'Department', 'Role', 'Total', 'Done', 'Open', 'Overdue', 'Completion %', 'Logged h', 'Estimated h', 'Completed (range)', 'Comments (range)'];
        foreach ($team as $m) {
            $rate = (int)$m['total'] > 0 ? (int)round(((int)$m['done'] / (int)$m['total']) * 100) : 0;
            $rows[] = [$m['name'], $m['department_name'] ?: '', $m['role_name'] ?: '', $m['total'], $m['done'], $m['open'],
                       $m['overdue'], $rate, $m['logged'], $m['estimated'], $m['done_in_range'], $m['comments_in_range']];
        }
    } elseif ($tab === 'projects') {
        $rows[] = ['Project', 'Code', 'Status', 'Owner', 'Department', 'Members', 'Total', 'Done', 'Overdue', 'Progress %', 'Logged h', 'Estimated h', 'Due'];
        foreach ($projects as $p) {
            $rows[] = [$p['name'], $p['code'], project_statuses()[$p['status']]['label'] ?? $p['status'], $p['owner_name'] ?: '',
                       $p['department_name'] ?: '', $p['members_count'], $p['total'], $p['done'], $p['overdue'], $p['progress'],
                       $p['logged'], $p['estimated'], $p['due_date'] ?: ''];
        }
    } elseif ($tab === 'time') {
        $rows[] = ['Member', 'Hours', 'Entries'];
        foreach ($timeByUser as $t) { $rows[] = [$t['name'], $t['hours'], $t['entries']]; }
    } else {
        $rows[] = ['Date', 'Tasks created', 'Tasks completed'];
        foreach ($days as $d => $v) { $rows[] = [$d, $v['created'], $v['done']]; }
    }
    activity('report.export', null, null, 'Exported the ' . $tab . ' report to CSV');
    csv_download('taskflow-report-' . $tab . '-' . date('Y-m-d') . '.csv', $rows);
}

$rangeLinks = static function (int $value) use ($tab): string {
    return url('index.php', ['page' => 'reports', 'tab' => $tab, 'days' => $value]);
};
?>
<?= page_header(
    'Reports & Analytics',
    'Performance insights for the last <strong>' . $range . ' days</strong> · scope: <strong>' . e(data_scope()) . '</strong>',
    '<div class="head-btns">'
        . '<div class="view-switch">'
        . foreach_range($rangeLinks, $range)
        . '</div>'
        . (can('reports.export') ? '<a class="btn btn-ghost btn-sm" href="' . e(url('index.php', ['page' => 'reports', 'tab' => $tab, 'days' => $range, 'export' => 'csv'])) . '">' . icon('download', 15) . ' Export CSV</a>' : '')
        . '</div>',
    breadcrumbs([['label' => 'Insights', 'url' => page_url('dashboard')], ['label' => 'Reports']])
) ?>

<nav class="tabs">
    <?php foreach (['overview' => ['Overview', 'reports'], 'team' => ['Team performance', 'users'], 'projects' => ['Projects', 'projects'], 'time' => ['Time tracking', 'clock']] as $key => $meta): ?>
        <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="<?= e(url('index.php', ['page' => 'reports', 'tab' => $key, 'days' => $range])) ?>">
            <?= icon($meta[1], 15) ?> <?= e($meta[0]) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
    <div class="kpi-grid">
        <?php
        $cards = [
            ['Total tasks', (int)($totals['total'] ?? 0), 'tasks', '#6366f1', (int)($totals['open'] ?? 0) . ' open'],
            ['Completed', (int)($totals['done'] ?? 0), 'check-circle', '#10b981', $completionRate . '% completion rate'],
            ['Created (' . $range . 'd)', (int)($totals['created_in_range'] ?? 0), 'plus', '#0ea5e9', 'new work added'],
            ['Finished (' . $range . 'd)', (int)($totals['completed_in_range'] ?? 0), 'zap', '#f59e0b', 'tasks closed'],
            ['Overdue', (int)($totals['overdue'] ?? 0), 'alert-triangle', '#ef4444', (int)($totals['blocked'] ?? 0) . ' blocked'],
            ['On-time delivery', $onTimeRate . '%', 'target', '#8b5cf6', 'of completed tasks with a due date'],
        ];
        foreach ($cards as $c): ?>
            <div class="kpi-card" style="--kpi-color:<?= e($c[3]) ?>">
                <span class="kpi-icon"><?= icon($c[2], 20) ?></span>
                <div class="kpi-body"><span class="kpi-label"><?= e($c[0]) ?></span><strong class="kpi-value"><?= e((string)$c[1]) ?></strong>
                    <span class="kpi-foot"><?= e($c[4]) ?></span></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="dash-grid">
        <section class="card span-2">
            <div class="card-head"><div><h3>Throughput</h3><p class="card-subtitle">Tasks created vs. completed over the last <?= $range ?> days</p></div></div>
            <div class="card-body">
                <div class="chart-container tall" data-chart="line"
                     data-labels='<?= json_encode(array_map(static fn($d) => date('M j', strtotime($d)), array_keys($days))) ?>'
                     data-series='<?= json_encode([
                         ['name' => 'Created',   'color' => '#6366f1', 'data' => array_column($days, 'created')],
                         ['name' => 'Completed', 'color' => '#10b981', 'data' => array_column($days, 'done')],
                     ]) ?>'></div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h3>Status mix</h3></div>
            <div class="card-body status-summary">
                <div class="donut-wrap">
                    <div class="chart-container donut" data-chart="donut"
                         data-series='<?= json_encode(array_values(array_map(static function ($k) use ($statusCounts) {
                             return ['name' => task_statuses()[$k]['label'], 'value' => $statusCounts[$k], 'color' => task_statuses()[$k]['color']];
                         }, array_keys($statusCounts)))) ?>'></div>
                    <div class="donut-center"><strong><?= $completionRate ?>%</strong><span>done</span></div>
                </div>
                <ul class="status-legend">
                    <?php foreach (task_statuses() as $k => $s): ?>
                        <li><i style="background:<?= e($s['color']) ?>"></i><span><?= e($s['label']) ?></span><strong><?= (int)$statusCounts[$k] ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h3>By priority</h3></div>
            <div class="card-body">
                <div class="chart-container bars" data-chart="bars"
                     data-labels='<?= json_encode(array_map(static fn($k) => priorities()[$k]['label'], array_keys($priorityCounts))) ?>'
                     data-series='<?= json_encode([['name' => 'Tasks', 'color' => '#f59e0b',
                         'data' => array_values($priorityCounts),
                         'colors' => array_map(static fn($k) => priorities()[$k]['color'], array_keys($priorityCounts))]]) ?>'></div>
            </div>
        </section>

        <section class="card span-2">
            <div class="card-head"><div><h3>Top performers</h3><p class="card-subtitle">Most tasks completed in the last <?= $range ?> days</p></div></div>
            <div class="card-body">
                <div class="chart-container bars horizontal" data-chart="bars"
                     data-labels='<?= json_encode(array_map(static fn($m) => $m['name'], array_slice($team, 0, 8))) ?>'
                     data-series='<?= json_encode([['name' => 'Completed', 'color' => '#10b981',
                         'data' => array_map(static fn($m) => (int)$m['done_in_range'], array_slice($team, 0, 8))]]) ?>'></div>
            </div>
        </section>
    </div>

<?php elseif ($tab === 'team'): ?>
    <div class="card"><div class="card-body no-pad"><div class="table-scroll">
        <table class="table">
            <thead><tr>
                <th>Member</th><th>Department</th><th>Total</th><th>Done</th><th>Open</th><th>Overdue</th>
                <th>Completion</th><th>Logged</th><th>Estimate accuracy</th><th>Comments (<?= $range ?>d)</th>
            </tr></thead>
            <tbody>
                <?php foreach ($team as $m): ?>
                    <?php
                    $rate = (int)$m['total'] > 0 ? (int)round(((int)$m['done'] / (int)$m['total']) * 100) : 0;
                    $est  = (float)$m['estimated'];
                    $log  = (float)$m['logged'];
                    $accuracy = $est > 0 ? (int)round(($log / $est) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <a class="person lg" href="<?= e(page_url('profile', ['id' => (int)$m['id']])) ?>">
                                <?= avatar_html($m, 34) ?>
                                <span><strong><?= e($m['name']) ?></strong><small><?= e($m['job_title'] ?: $m['role_name'] ?: '') ?></small></span>
                            </a>
                        </td>
                        <td><?= e($m['department_name'] ?: '—') ?></td>
                        <td><?= (int)$m['total'] ?></td>
                        <td><strong class="text-success"><?= (int)$m['done'] ?></strong></td>
                        <td><?= (int)$m['open'] ?></td>
                        <td><?= (int)$m['overdue'] > 0 ? '<span class="chip chip-danger sm-chip">' . (int)$m['overdue'] . '</span>' : '<span class="muted">0</span>' ?></td>
                        <td class="cell-progress"><div class="progress-wrap"><?= progress_bar($rate, $rate >= 70 ? '#10b981' : ($rate >= 40 ? '#f59e0b' : '#ef4444')) ?><span><?= $rate ?>%</span></div></td>
                        <td><?= e(format_hours($log)) ?></td>
                        <td>
                            <?php if ($est > 0): ?>
                                <span class="chip chip-<?= $accuracy > 130 ? 'danger' : ($accuracy >= 70 ? 'success' : 'warning') ?> sm-chip"><?= $accuracy ?>%</span>
                                <small class="muted"><?= e(format_hours($log)) ?>/<?= e(format_hours($est)) ?></small>
                            <?php else: ?><span class="muted">—</span><?php endif; ?>
                        </td>
                        <td><?= (int)$m['comments_in_range'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$team): ?><tr><td colspan="10" class="muted center">No team data yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

<?php elseif ($tab === 'projects'): ?>
    <div class="card"><div class="card-body no-pad"><div class="table-scroll">
        <table class="table">
            <thead><tr>
                <th>Project</th><th>Status</th><th>Owner</th><th>Team</th><th>Tasks</th>
                <th>Done</th><th>Overdue</th><th>Progress</th><th>Hours (logged / est.)</th><th>Deadline</th>
            </tr></thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                    <?php $d = days_until($p['due_date']); $late = $d !== null && $d < 0 && $p['status'] !== 'completed'; ?>
                    <tr>
                        <td class="cell-title">
                            <a class="task-title" href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>">
                                <i class="dot" style="background:<?= e($p['color']) ?>"></i> <?= e($p['name']) ?>
                            </a>
                            <span class="task-project"><?= e($p['code']) ?><?= $p['department_name'] ? ' · ' . e($p['department_name']) : '' ?></span>
                        </td>
                        <td><?= project_status_badge($p['status']) ?></td>
                        <td><?= e($p['owner_name'] ?: '—') ?></td>
                        <td><?= (int)$p['members_count'] ?></td>
                        <td><?= (int)$p['total'] ?></td>
                        <td><strong class="text-success"><?= (int)$p['done'] ?></strong></td>
                        <td><?= (int)$p['overdue'] > 0 ? '<span class="chip chip-danger sm-chip">' . (int)$p['overdue'] . '</span>' : '<span class="muted">0</span>' ?></td>
                        <td class="cell-progress"><div class="progress-wrap"><?= progress_bar((int)$p['progress'], e($p['color'])) ?><span><?= (int)$p['progress'] ?>%</span></div></td>
                        <td>
                            <?= e(format_hours((float)$p['logged'])) ?>
                            <small class="muted">/ <?= e(format_hours((float)$p['estimated'])) ?></small>
                        </td>
                        <td><?= $p['due_date'] ? '<span class="due-chip' . ($late ? ' late' : '') . '">' . icon('calendar', 13) . ' ' . e(format_date($p['due_date'], 'M j, Y')) . '</span>' : '<span class="muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?><tr><td colspan="10" class="muted center">No projects to report on.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

<?php else: /* time */ ?>
    <div class="dash-grid two">
        <section class="card">
            <div class="card-head"><div><h3>Hours by member</h3><p class="card-subtitle">Logged in the last <?= $range ?> days</p></div></div>
            <div class="card-body">
                <?php if ($timeByUser): ?>
                    <div class="chart-container bars horizontal" data-chart="bars"
                         data-labels='<?= json_encode(array_map(static fn($t) => $t['name'], $timeByUser)) ?>'
                         data-series='<?= json_encode([['name' => 'Hours', 'color' => '#8b5cf6',
                             'data' => array_map(static fn($t) => round((float)$t['hours'], 2), $timeByUser)]]) ?>'></div>
                <?php else: ?>
                    <p class="inline-empty">No time has been logged in this period. Team members can log hours from any task page.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h3>Estimate vs. actual</h3><p class="card-subtitle">Per project, all time</p></div></div>
            <div class="card-body">
                <?php if ($timeByProject): ?>
                    <div class="chart-container bars" data-chart="bars"
                         data-labels='<?= json_encode(array_map(static fn($p) => $p['code'], $timeByProject)) ?>'
                         data-series='<?= json_encode([
                             ['name' => 'Estimated', 'color' => '#94a3b8', 'data' => array_map(static fn($p) => round((float)$p['estimated'], 1), $timeByProject)],
                             ['name' => 'Logged',    'color' => '#6366f1', 'data' => array_map(static fn($p) => round((float)$p['logged'], 1), $timeByProject)],
                         ]) ?>'></div>
                <?php else: ?>
                    <p class="inline-empty">No hour estimates yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card span-2">
            <div class="card-head"><h3>Time log detail</h3></div>
            <div class="card-body no-pad"><div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Member</th><th>Project</th><th>Hours</th><th>Entries</th><th>Per task</th></tr></thead>
                    <tbody>
                        <?php foreach ($timeByUser as $t): ?>
                            <tr>
                                <td><span class="person"><?= avatar_html($t, 28) ?> <?= e($t['name']) ?></span></td>
                                <td><?= e(implode(', ', array_map(static fn($p) => $p['code'], array_slice($timeByProject, 0, 3)))) ?: '—' ?></td>
                                <td><strong><?= e(format_hours((float)$t['hours'])) ?></strong></td>
                                <td><?= (int)$t['entries'] ?></td>
                                <td><?= $t['entries'] > 0 ? e(format_hours((float)$t['hours'] / (int)$t['entries'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$timeByUser): ?><tr><td colspan="5" class="muted center">Nothing logged yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </section>
    </div>
<?php endif; ?>

<?php
/** Range switcher buttons */
function foreach_range(callable $link, int $current): string
{
    $out = '';
    foreach ([7, 14, 30, 90] as $value) {
        $out .= '<a class="view-btn' . ($value === $current ? ' active' : '') . '" href="' . e($link($value)) . '">' . $value . 'd</a>';
    }
    return $out;
}
