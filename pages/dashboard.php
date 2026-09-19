<?php
/**
 * TaskFlow — Dashboard
 */

$uid   = user_id();
$scope = data_scope();
$ts    = scope_tasks_sql('t');
$ps    = scope_projects_sql('p');

// هل المستخدم يشوف بيانات أبعد من مهامه الشخصية؟ (مدير/سوبر أدمن/قائد فريق)
// هذا هو الأساس اللي نبني عليه الفروقات الحقيقية بين لوحة المشرف والعضو،
// بدل الاعتماد بس على أن الاستعلامات متصفّاة صمن.
$isManagerView = ($scope !== 'own');
$roleName      = (string)(current_user()['role_name'] ?? '');

/* ---------------- KPIs ---------------- */
$today    = sql_date();
$soonDate = sql_date('+' . max(1, (int)setting('due_soon_days', 3)) . ' days');

$kpi = Db::one(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END), 0) AS done,
        COALESCE(SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress,
        COALESCE(SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END), 0) AS blocked,
        COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < {$today} AND t.status <> 'done' THEN 1 ELSE 0 END), 0) AS overdue,
        COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date BETWEEN {$today} AND {$soonDate} AND t.status <> 'done' THEN 1 ELSE 0 END), 0) AS due_soon,
        COALESCE(SUM(t.estimated_hours), 0) AS estimated,
        COALESCE(SUM(t.actual_hours), 0) AS logged
     FROM tasks t WHERE t.is_archived = 0 AND t.parent_id IS NULL AND " . $ts['sql'],
    $ts['params']
) ?? [];

$mine = Db::one(
    "SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status <> 'done' THEN 1 ELSE 0 END), 0) AS open,
        COALESCE(SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END), 0) AS done,
        COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < {$today} AND status <> 'done' THEN 1 ELSE 0 END), 0) AS overdue
     FROM tasks WHERE assignee_id = ? AND is_archived = 0 AND parent_id IS NULL",
    [$uid]
) ?? [];

$projectCount = Db::count('SELECT COUNT(*) FROM projects p WHERE ' . $ps['sql'], $ps['params']);
$teamCount    = Db::count("SELECT COUNT(*) FROM users WHERE status = 'active'");

$completionRate = ((int)($kpi['total'] ?? 0) > 0)
    ? (int)round(((int)($kpi['done'] ?? 0) / (int)$kpi['total']) * 100)
    : 0;

/* ---------------- Charts ---------------- */
$statusRows = Db::all(
    'SELECT t.status, COUNT(*) AS c FROM tasks t
     WHERE t.is_archived = 0 AND t.parent_id IS NULL AND ' . $ts['sql'] . '
     GROUP BY t.status',
    $ts['params']
);
$statusCounts = array_fill_keys(array_keys(task_statuses()), 0);
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int)$row['c'];
}

$donePerDay = [];
for ($i = 13; $i >= 0; $i--) {
    $donePerDay[date('Y-m-d', strtotime("-{$i} day"))] = 0;
}
$since14 = sql_datetime('-14 days');
$doneRows = Db::all(
    'SELECT DATE(t.completed_at) AS d, COUNT(*) AS c FROM tasks t
     WHERE t.completed_at IS NOT NULL AND t.completed_at >= ' . $since14 . ' AND ' . $ts['sql'] . '
     GROUP BY DATE(t.completed_at)',
    $ts['params']
);
foreach ($doneRows as $row) {
    if (isset($donePerDay[$row['d']])) {
        $donePerDay[$row['d']] = (int)$row['c'];
    }
}

$createdPerDay = [];
for ($i = 13; $i >= 0; $i--) {
    $createdPerDay[date('Y-m-d', strtotime("-{$i} day"))] = 0;
}
$createdRows = Db::all(
    'SELECT DATE(t.created_at) AS d, COUNT(*) AS c FROM tasks t
     WHERE t.created_at >= ' . $since14 . ' AND ' . $ts['sql'] . '
     GROUP BY DATE(t.created_at)',
    $ts['params']
);
foreach ($createdRows as $row) {
    if (isset($createdPerDay[$row['d']])) {
        $createdPerDay[$row['d']] = (int)$row['c'];
    }
}

/* ---------------- Projects ---------------- */
$projects = Db::all(
    'SELECT p.*, u.name AS owner_name, u.color AS owner_color, u.avatar AS owner_avatar,
            (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS members_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_archived = 0 AND t.parent_id IS NULL) AS tasks_total,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_archived = 0 AND t.parent_id IS NULL AND t.status = "done") AS tasks_done
     FROM projects p
     LEFT JOIN users u ON u.id = p.owner_id
     WHERE ' . $ps['sql'] . "
     ORDER BY CASE p.status WHEN 'active' THEN 0 WHEN 'planning' THEN 1 WHEN 'on_hold' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END, (p.due_date IS NULL), p.due_date ASC
     LIMIT 6",
    $ps['params']
);

/* ---------------- My tasks ---------------- */
$dueSoon = max(1, (int)setting('due_soon_days', 3));
$myTasks = Db::all(
    "SELECT t.*, p.code AS project_code, p.color AS project_color, p.name AS project_name
     FROM tasks t JOIN projects p ON p.id = t.project_id
     WHERE t.assignee_id = ? AND t.is_archived = 0 AND t.parent_id IS NULL AND t.status <> 'done'
     ORDER BY (CASE WHEN t.due_date IS NOT NULL AND t.due_date < {$today} THEN 0 ELSE 1 END),
              (CASE t.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END),
              (t.due_date IS NULL), t.due_date ASC
     LIMIT 6",
    [$uid]
);

$overdueTasks = Db::all(
    "SELECT t.id, t.task_key, t.title, t.due_date, t.priority, t.status,
            u.name AS assignee_name, u.color AS assignee_color, u.avatar AS assignee_avatar
     FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id
     WHERE t.is_archived = 0 AND t.parent_id IS NULL AND t.due_date < {$today} AND t.status <> 'done' AND " . $ts['sql'] . "
     ORDER BY t.due_date ASC LIMIT 5",
    $ts['params']
);

/* ---------------- Team workload (managers/leads only) ---------------- */
$workload = [];
if ($isManagerView) {
    // مدير/سوبر أدمن (scope=all) يشوف الفريق كامل. قائد فريق (scope=project)
    // يشوف بس الأعضاء المشتركين معه بنفس المشاريع، مو الشركة كلها.
    if ($scope === 'all') {
        $workload = Db::all(
            "SELECT u.id, u.name, u.color, u.avatar, u.job_title, d.name AS department_name,
                    COUNT(t.id) AS open_tasks,
                    COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < {$today} THEN 1 ELSE 0 END), 0) AS overdue_tasks,
                    COALESCE(SUM(t.actual_hours), 0) AS logged_hours
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN tasks t ON t.assignee_id = u.id AND t.status <> 'done' AND t.is_archived = 0 AND t.parent_id IS NULL
             WHERE u.status = 'active'
             GROUP BY u.id, u.name, u.color, u.avatar, u.job_title, d.name
             ORDER BY open_tasks DESC, u.name ASC
             LIMIT 6"
        );
    } else {
        $workload = Db::all(
            "SELECT u.id, u.name, u.color, u.avatar, u.job_title, d.name AS department_name,
                    COUNT(t.id) AS open_tasks,
                    COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < {$today} THEN 1 ELSE 0 END), 0) AS overdue_tasks,
                    COALESCE(SUM(t.actual_hours), 0) AS logged_hours
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN tasks t ON t.assignee_id = u.id AND t.status <> 'done' AND t.is_archived = 0 AND t.parent_id IS NULL
             WHERE u.status = 'active' AND u.id IN (
                 SELECT DISTINCT pm2.user_id FROM project_members pm2
                 WHERE pm2.project_id IN (SELECT pm.project_id FROM project_members pm WHERE pm.user_id = ?)
             )
             GROUP BY u.id, u.name, u.color, u.avatar, u.job_title, d.name
             ORDER BY open_tasks DESC, u.name ASC
             LIMIT 6",
            [$uid]
        );
    }
}

/* ---------------- Needs attention: unassigned tasks (managers only) --- */
$unassignedTasks = [];
if ($isManagerView && can('tasks.assign')) {
    $unassignedTasks = Db::all(
        "SELECT t.id, t.task_key, t.title, t.priority, t.created_at, p.code AS project_code, p.color AS project_color
         FROM tasks t JOIN projects p ON p.id = t.project_id
         WHERE t.assignee_id IS NULL AND t.is_archived = 0 AND t.parent_id IS NULL AND t.status <> 'done' AND " . $ts['sql'] . '
         ORDER BY (CASE t.priority WHEN \'critical\' THEN 0 WHEN \'high\' THEN 1 WHEN \'medium\' THEN 2 ELSE 3 END), t.created_at ASC
         LIMIT 5',
        $ts['params']
    );
}

/* ---------------- My week (members/viewers only) ---------------------- */
$myWeek = ['hours' => 0.0, 'completed' => 0, 'unread' => 0];
if (!$isManagerView) {
    $since7 = sql_date('-7 days');
    $myWeek['hours'] = (float)(Db::one(
        "SELECT COALESCE(SUM(hours), 0) AS h FROM time_logs WHERE user_id = ? AND log_date >= {$since7}",
        [$uid]
    )['h'] ?? 0);
    $myWeek['completed'] = (int)(Db::one(
        "SELECT COUNT(*) AS c FROM tasks WHERE assignee_id = ? AND completed_at >= " . sql_datetime('-7 days'),
        [$uid]
    )['c'] ?? 0);
    $myWeek['unread'] = Db::count('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]);
    $myProjects = Db::all(
        'SELECT p.id, p.code, p.name, p.color, p.progress, p.due_date
         FROM projects p JOIN project_members pm ON pm.project_id = p.id
         WHERE pm.user_id = ? AND p.status <> \'completed\'
         ORDER BY (p.due_date IS NULL), p.due_date ASC LIMIT 4',
        [$uid]
    );
}

/* ---------------- Recent activity ---------------- */
// كانت هذي تعرض سجل الشركة كامل لأي شخص، حتى الأعضاء اللي ما عندهم صلاحية
// activity.view — وهذا تسريب بيانات. الآن: من يملك الصلاحية يشوف سجل الشركة،
// وغيره يشوف نشاطه الشخصي بس.
if (can('activity.view')) {
    $recentActivity = Db::all(
        'SELECT * FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT 8'
    );
} else {
    $recentActivity = Db::all(
        'SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8',
        [$uid]
    );
}
?>
<div class="dashboard">

    <div class="welcome-banner">
        <div class="welcome-text">
            <h2><?= greeting() ?>, <?= e(explode(' ', user_name())[0]) ?> 👋
                <?php if ($roleName !== ''): ?><span class="chip chip-role"><?= e($roleName) ?></span><?php endif; ?>
            </h2>
            <p>
                <?php if ((int)$mine['open'] > 0): ?>
                    You have <strong><?= (int)$mine['open'] ?></strong> open task<?= (int)$mine['open'] === 1 ? '' : 's' ?>
                    <?php if ((int)$mine['overdue'] > 0): ?>
                        and <strong class="text-danger"><?= (int)$mine['overdue'] ?> overdue</strong>
                    <?php endif; ?>.
                <?php else: ?>
                    Your task list is clear — nice work!
                <?php endif; ?>
                Today is <strong><?= date('l, F j, Y') ?></strong>.
            </p>
        </div>
        <div class="welcome-actions">
            <?php if (can('tasks.create')): ?>
                <button class="btn btn-light" data-modal-open="taskModal"><?= icon('plus', 16) ?> New task</button>
            <?php endif; ?>
            <a class="btn btn-light-ghost" href="<?= e(page_url('board')) ?>"><?= icon('board', 16) ?> Open board</a>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="kpi-grid">
        <?php
        $kpis = [
            ['label' => 'My open tasks', 'value' => (int)$mine['open'], 'icon' => 'tasks', 'color' => '#6366f1',
             'foot' => (int)$mine['done'] . ' completed all-time', 'url' => page_url('tasks', ['mine' => 1])],
            ['label' => 'In progress', 'value' => (int)($kpi['in_progress'] ?? 0), 'icon' => 'loader', 'color' => '#f59e0b',
             'foot' => (int)($kpi['blocked'] ?? 0) . ' blocked', 'url' => page_url('tasks', ['status' => 'in_progress'])],
            ['label' => 'Overdue', 'value' => (int)($kpi['overdue'] ?? 0), 'icon' => 'alert-triangle', 'color' => '#ef4444',
             'foot' => (int)($kpi['due_soon'] ?? 0) . ' due in ' . $dueSoon . ' days', 'url' => page_url('tasks', ['overdue' => 1])],
            ['label' => 'Completion rate', 'value' => $completionRate . '%', 'icon' => 'trend-up', 'color' => '#10b981',
             'foot' => (int)($kpi['done'] ?? 0) . ' of ' . (int)($kpi['total'] ?? 0) . ' tasks done', 'url' => page_url('reports')],
            ['label' => 'Active projects', 'value' => $projectCount, 'icon' => 'projects', 'color' => '#0ea5e9',
             'foot' => $teamCount . ' team members', 'url' => page_url('projects')],
            ['label' => 'Hours logged', 'value' => format_hours((float)($kpi['logged'] ?? 0)), 'icon' => 'clock', 'color' => '#8b5cf6',
             'foot' => format_hours((float)($kpi['estimated'] ?? 0)) . ' estimated', 'url' => page_url('reports', ['tab' => 'time'])],
        ];
        foreach ($kpis as $k): ?>
            <a class="kpi-card" href="<?= e($k['url']) ?>" style="--kpi-color:<?= e($k['color']) ?>">
                <span class="kpi-icon"><?= icon($k['icon'], 20) ?></span>
                <div class="kpi-body">
                    <span class="kpi-label"><?= e($k['label']) ?></span>
                    <strong class="kpi-value"><?= e((string)$k['value']) ?></strong>
                    <span class="kpi-foot"><?= e($k['foot']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="dash-grid">
        <!-- Throughput chart -->
        <section class="card span-2">
            <div class="card-head">
                <div>
                    <h3>Throughput — last 14 days</h3>
                    <p class="card-subtitle">Tasks created vs. tasks completed</p>
                </div>
                <div class="legend">
                    <span><i style="background:#6366f1"></i> Created</span>
                    <span><i style="background:#10b981"></i> Completed</span>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container"
                     data-chart="line"
                     data-labels='<?= json_encode(array_map(static fn($d) => date('M j', strtotime($d)), array_keys($createdPerDay))) ?>'
                     data-series='<?= json_encode([
                         ['name' => 'Created',   'color' => '#6366f1', 'data' => array_values($createdPerDay)],
                         ['name' => 'Completed', 'color' => '#10b981', 'data' => array_values($donePerDay)],
                     ]) ?>'></div>
            </div>
        </section>

        <!-- Status distribution -->
        <section class="card">
            <div class="card-head"><h3>By status</h3></div>
            <div class="card-body status-summary">
                <div class="donut-wrap">
                    <div class="chart-container donut" data-chart="donut"
                         data-series='<?= json_encode(array_values(array_map(static function ($key) use ($statusCounts) {
                             $s = task_statuses()[$key];
                             return ['name' => $s['label'], 'value' => $statusCounts[$key], 'color' => $s['color']];
                         }, array_keys($statusCounts)))) ?>'></div>
                    <div class="donut-center">
                        <strong><?= (int)($kpi['total'] ?? 0) ?></strong>
                        <span>tasks</span>
                    </div>
                </div>
                <ul class="status-legend">
                    <?php foreach (task_statuses() as $key => $s): ?>
                        <li>
                            <i style="background:<?= e($s['color']) ?>"></i>
                            <a href="<?= e(page_url('tasks', ['status' => $key])) ?>"><?= e($s['label']) ?></a>
                            <strong><?= (int)$statusCounts[$key] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- My tasks -->
        <section class="card span-2">
            <div class="card-head">
                <div><h3>My tasks</h3><p class="card-subtitle">Sorted by due date and priority</p></div>
                <a class="link-btn" href="<?= e(page_url('tasks', ['mine' => 1])) ?>">View all <?= icon('chevron-right', 14) ?></a>
            </div>
            <div class="card-body no-pad">
                <?php if (!$myTasks): ?>
                    <?= empty_state('check-circle', 'Inbox zero', 'You have no open tasks assigned to you right now.') ?>
                <?php else: ?>
                    <ul class="mini-task-list">
                        <?php foreach ($myTasks as $t): ?>
                            <?php
                            $days = days_until($t['due_date']);
                            $late = $days !== null && $days < 0;
                            $soon = $days !== null && $days >= 0 && $days <= $dueSoon;
                            ?>
                            <li class="mini-task">
                                <button class="mini-check" data-toggle-done="<?= (int)$t['id'] ?>" title="Mark as done" aria-label="Mark as done"></button>
                                <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                    <span class="mini-task-key" style="--key-color:<?= e($t['project_color']) ?>"><?= e($t['task_key']) ?></span>
                                    <span class="mini-task-title"><?= e($t['title']) ?></span>
                                </a>
                                <div class="mini-task-meta">
                                    <?= priority_badge($t['priority']) ?>
                                    <?= status_badge($t['status']) ?>
                                    <?php if ($t['due_date']): ?>
                                        <span class="due-chip<?= $late ? ' late' : ($soon ? ' soon' : '') ?>">
                                            <?= icon('calendar', 13) ?>
                                            <?= $late ? abs((int)$days) . 'd overdue' : ($days === 0 ? 'Today' : format_date($t['due_date'], 'M j')) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isManagerView): ?>
        <!-- Team workload (managers / team leads only) -->
        <section class="card">
            <div class="card-head">
                <div><h3>Team workload</h3><p class="card-subtitle"><?= $scope === 'all' ? 'Open tasks per member' : 'Open tasks — your project teammates' ?></p></div>
                <a class="link-btn" href="<?= e(page_url('team')) ?>">Team</a>
            </div>
            <div class="card-body">
                <?php if (!$workload): ?>
                    <?= empty_state('users', 'No teammates yet', 'Add people to your projects to see their workload here.') ?>
                <?php else: ?>
                <div class="chart-container bars" data-chart="bars"
                     data-labels='<?= json_encode(array_map(static fn($w) => explode(' ', (string)$w['name'])[0], $workload)) ?>'
                     data-series='<?= json_encode([['name' => 'Open tasks', 'color' => '#6366f1',
                         'data' => array_map(static fn($w) => (int)$w['open_tasks'], $workload)]]) ?>'></div>
                <ul class="workload-list">
                    <?php foreach ($workload as $w): ?>
                        <li>
                            <?= avatar_html($w, 28) ?>
                            <div class="workload-meta">
                                <a href="<?= e(page_url('profile', ['id' => (int)$w['id']])) ?>"><?= e($w['name']) ?></a>
                                <span><?= e($w['department_name'] ?: $w['job_title'] ?: '—') ?></span>
                            </div>
                            <div class="workload-nums">
                                <?php if ((int)$w['overdue_tasks'] > 0): ?>
                                    <span class="chip chip-danger"><?= (int)$w['overdue_tasks'] ?> late</span>
                                <?php endif; ?>
                                <strong><?= (int)$w['open_tasks'] ?></strong>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
        <!-- My week (members / viewers) -->
        <section class="card">
            <div class="card-head">
                <div><h3>My week</h3><p class="card-subtitle">Last 7 days</p></div>
                <a class="link-btn" href="<?= e(page_url('notifications')) ?>">Notifications</a>
            </div>
            <div class="card-body">
                <ul class="my-week-stats">
                    <li>
                        <span class="my-week-icon" style="--kpi-color:#8b5cf6"><?= icon('clock', 18) ?></span>
                        <div><strong><?= e(format_hours($myWeek['hours'])) ?></strong><span>hours logged</span></div>
                    </li>
                    <li>
                        <span class="my-week-icon" style="--kpi-color:#10b981"><?= icon('check-circle', 18) ?></span>
                        <div><strong><?= (int)$myWeek['completed'] ?></strong><span>tasks completed</span></div>
                    </li>
                    <li>
                        <span class="my-week-icon" style="--kpi-color:#0ea5e9"><?= icon('bell', 18) ?></span>
                        <div><strong><?= (int)$myWeek['unread'] ?></strong><span>unread notification<?= (int)$myWeek['unread'] === 1 ? '' : 's' ?></span></div>
                    </li>
                </ul>
                <?php if (!empty($myProjects)): ?>
                    <p class="card-subtitle" style="margin-top:14px">My projects</p>
                    <ul class="mini-task-list">
                        <?php foreach ($myProjects as $p): ?>
                            <li class="mini-task">
                                <a class="mini-task-main" href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>">
                                    <span class="mini-task-key" style="--key-color:<?= e($p['color']) ?>"><?= e($p['code']) ?></span>
                                    <span class="mini-task-title"><?= e($p['name']) ?></span>
                                </a>
                                <div class="mini-task-meta"><?= progress_bar((int)$p['progress']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Projects -->
        <section class="card span-2">
            <div class="card-head">
                <div><h3>Projects</h3><p class="card-subtitle">Progress and deadlines</p></div>
                <a class="link-btn" href="<?= e(page_url('projects')) ?>">All projects <?= icon('chevron-right', 14) ?></a>
            </div>
            <div class="card-body no-pad">
                <?php if (!$projects): ?>
                    <?= empty_state('projects', 'No projects yet', 'Create your first project to start distributing work.',
                        can('projects.create') ? '<button class="btn btn-primary" data-modal-open="projectModal">' . icon('plus', 16) . ' New project</button>' : '') ?>
                <?php else: ?>
                    <div class="project-strip">
                        <?php foreach ($projects as $p): ?>
                            <a class="project-mini" href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>" style="--p-color:<?= e($p['color']) ?>">
                                <div class="project-mini-top">
                                    <span class="project-code"><?= e($p['code']) ?></span>
                                    <?= project_status_badge($p['status']) ?>
                                </div>
                                <strong><?= e($p['name']) ?></strong>
                                <?= progress_bar((int)$p['progress']) ?>
                                <div class="project-mini-foot">
                                    <span><?= (int)$p['tasks_done'] ?>/<?= (int)$p['tasks_total'] ?> tasks</span>
                                    <span><?= (int)$p['progress'] ?>%</span>
                                    <span class="<?= $p['due_date'] && days_until($p['due_date']) !== null && days_until($p['due_date']) < 0 ? 'text-danger' : '' ?>">
                                        <?= $p['due_date'] ? icon('calendar', 12) . ' ' . format_date($p['due_date'], 'M j') : '' ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Overdue -->
        <?php if ($overdueTasks): ?>
        <section class="card">
            <div class="card-head">
                <div><h3 class="text-danger"><?= icon('alert-triangle', 17) ?> Overdue</h3></div>
                <a class="link-btn" href="<?= e(page_url('tasks', ['overdue' => 1])) ?>">See all</a>
            </div>
            <div class="card-body no-pad">
                <ul class="mini-task-list">
                    <?php foreach ($overdueTasks as $t): ?>
                        <li class="mini-task">
                            <?= avatar_html($t['assignee_id'] ? $t : null, 26) ?>
                            <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                <span class="mini-task-title"><?= e($t['title']) ?></span>
                                <span class="mini-task-sub"><?= e($t['task_key']) ?> · <?= e($t['assignee_name'] ?: 'Unassigned') ?></span>
                            </a>
                            <span class="due-chip late"><?= icon('calendar', 13) ?> <?= e(format_date($t['due_date'], 'M j')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <!-- Needs attention: unassigned tasks (managers / leads only) -->
        <?php if ($isManagerView && $unassignedTasks): ?>
        <section class="card">
            <div class="card-head">
                <div><h3><?= icon('flag', 17) ?> Needs an owner</h3><p class="card-subtitle">Unassigned open tasks</p></div>
                <a class="link-btn" href="<?= e(page_url('tasks', ['assignee' => -1])) ?>">See all</a>
            </div>
            <div class="card-body no-pad">
                <ul class="mini-task-list">
                    <?php foreach ($unassignedTasks as $t): ?>
                        <li class="mini-task">
                            <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                <span class="mini-task-key" style="--key-color:<?= e($t['project_color']) ?>"><?= e($t['task_key']) ?></span>
                                <span class="mini-task-title"><?= e($t['title']) ?></span>
                            </a>
                            <div class="mini-task-meta"><?= priority_badge($t['priority']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <!-- Activity -->
        <section class="card<?= $overdueTasks ? '' : ' span-2' ?>">
            <div class="card-head">
                <div><h3>Recent activity</h3></div>
                <?php if (can('activity.view')): ?><a class="link-btn" href="<?= e(page_url('activity')) ?>">Full log</a><?php endif; ?>
            </div>
            <div class="card-body no-pad">
                <ul class="activity-feed">
                    <?php foreach ($recentActivity as $log): ?>
                        <li>
                            <span class="activity-dot"></span>
                            <div class="activity-body">
                                <p><strong><?= e($log['user_name'] ?: 'System') ?></strong> <?= e(strtolower((string)$log['description'] ?: str_replace(['.', '_'], ' ', (string)$log['action']))) ?></p>
                                <time><?= e(time_ago($log['created_at'])) ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recentActivity): ?>
                        <li class="muted">No activity recorded yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php
function greeting(): string
{
    $h = (int)date('G');
    if ($h < 12) { return 'Good morning'; }
    if ($h < 17) { return 'Good afternoon'; }
    return 'Good evening';
}