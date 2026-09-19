<?php
/**
 * TaskFlow — Project detail (overview, tasks, team, settings)
 */

$projectId = int_input('id', 0);
$tab       = (string)query('tab', 'overview');
if (!in_array($tab, ['overview', 'tasks', 'team', 'settings'], true)) { $tab = 'overview'; }

$project = Db::one(
    'SELECT p.*, d.name AS department_name, d.color AS department_color,
            u.id AS owner_uid, u.name AS owner_name, u.color AS owner_color, u.avatar AS owner_avatar, u.job_title AS owner_title,
            u.email AS owner_email
     FROM projects p
     LEFT JOIN departments d ON d.id = p.department_id
     LEFT JOIN users u ON u.id = p.owner_id
     WHERE p.id = ?',
    [$projectId]
);

if (!$project || !can_view_project($project)) {
    http_response_code(404);
    echo empty_state('search', 'Project not found', 'This project does not exist or you do not have access to it.')
       . '<div class="center-actions"><a class="btn btn-primary" href="' . e(page_url('projects')) . '">Back to projects</a></div>';
    return;
}

$canManage = can_manage_project($project);
$today     = sql_date();

$stats = Db::one(
    "SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END),0) AS done,
        COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END),0) AS in_progress,
        COALESCE(SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END),0) AS blocked,
        COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < {$today} AND status <> 'done' THEN 1 ELSE 0 END),0) AS overdue,
        COALESCE(SUM(COALESCE(estimated_hours,0)),0) AS estimated,
        COALESCE(SUM(actual_hours),0) AS logged
     FROM tasks WHERE project_id = ? AND is_archived = 0 AND parent_id IS NULL",
    [$projectId]
) ?? [];

$members = Db::all(
    "SELECT u.*, pm.project_role, pm.created_at AS joined_at,
            COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.project_id = ? AND t.assignee_id = u.id AND t.status <> 'done' AND t.parent_id IS NULL),0) AS open_tasks,
            COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.project_id = ? AND t.assignee_id = u.id AND t.status = 'done' AND t.parent_id IS NULL),0) AS done_tasks,
            COALESCE((SELECT SUM(t.actual_hours) FROM tasks t WHERE t.project_id = ? AND t.assignee_id = u.id),0) AS logged_hours
     FROM project_members pm JOIN users u ON u.id = pm.user_id
     WHERE pm.project_id = ?
     ORDER BY CASE pm.project_role WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 WHEN 'member' THEN 2 ELSE 3 END, u.name ASC",
    [$projectId, $projectId, $projectId, $projectId]
);

$memberTasks = [];
foreach ($members as $m) {
    $rows = Db::all(
        "SELECT status, COUNT(*) AS c FROM tasks WHERE project_id = ? AND assignee_id = ? AND parent_id IS NULL AND is_archived = 0 GROUP BY status",
        [$projectId, (int)$m['id']]
    );
    $byStatus = array_fill_keys(array_keys(task_statuses()), 0);
    foreach ($rows as $r) { $byStatus[$r['status']] = (int)$r['c']; }
    $memberTasks[(int)$m['id']] = $byStatus;
}

$tasks = Db::all(
    "SELECT t.*, a.name AS assignee_name, a.color AS assignee_color, a.avatar AS assignee_avatar,
            (SELECT COUNT(*) FROM comments c WHERE c.task_id = t.id) AS comments_count
     FROM tasks t LEFT JOIN users a ON a.id = t.assignee_id
     WHERE t.project_id = ? AND t.is_archived = 0 AND t.parent_id IS NULL
     ORDER BY (CASE WHEN t.status = 'done' THEN 1 ELSE 0 END),
              (CASE t.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END),
              (t.due_date IS NULL), t.due_date ASC
     LIMIT 200",
    [$projectId]
);

$statusGroups = [];
foreach (task_statuses() as $key => $meta) { $statusGroups[$key] = []; }
foreach ($tasks as $t) { $statusGroups[$t['status']][] = $t; }

$recentLogs = Db::all(
    "SELECT * FROM activity_logs WHERE (entity_type = 'project' AND entity_id = ?) OR (entity_type = 'task' AND entity_id IN
        (SELECT id FROM tasks WHERE project_id = ?)) ORDER BY created_at DESC LIMIT 12",
    [$projectId, $projectId]
);

$availableUsers = Db::all(
    "SELECT id, name, color, avatar, job_title FROM users u
     WHERE u.status = 'active' AND NOT EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = ? AND pm.user_id = u.id)
     ORDER BY u.name ASC",
    [$projectId]
);

$allProjects = Db::all('SELECT id, name, code FROM projects ORDER BY name ASC');
$allUsers    = Db::all("SELECT id, name FROM users WHERE status = 'active' ORDER BY name ASC");
$departments = Db::all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');

$GLOBALS['PAGE_TITLE'] = $project['name'];
$daysLeft = days_until($project['due_date']);
?>
<?= page_header(
    $project['name'],
    '<span class="project-code-lg" style="--p-color:' . e($project['color']) . '">' . e($project['code']) . '</span> '
    . ($project['department_name'] ? icon('building', 13) . ' ' . e($project['department_name']) . ' · ' : '')
    . 'Owner: <strong>' . e($project['owner_name'] ?: '—') . '</strong>',
    '<div class="head-btns">'
        . '<a class="btn btn-ghost btn-sm" href="' . e(page_url('board', ['project' => $projectId])) . '">' . icon('board', 15) . ' Board</a>'
        . (can('tasks.create') ? '<button class="btn btn-primary btn-sm" data-modal-open="taskModal" data-project="' . $projectId . '">' . icon('plus', 15) . ' New task</button>' : '')
        . '</div>',
    breadcrumbs([
        ['label' => 'Projects', 'url' => page_url('projects')],
        ['label' => $project['name']],
    ])
) ?>

<div class="project-hero" style="--p-color:<?= e($project['color']) ?>">
    <div class="project-hero-main">
        <div class="project-hero-badges">
            <?= project_status_badge($project['status']) ?>
            <?= priority_badge($project['priority']) ?>
            <?php if ($project['visibility'] === 'private'): ?><span class="chip chip-muted"><?= icon('lock', 12) ?> Private</span><?php endif; ?>
            <?php if ($project['due_date']): ?>
                <span class="due-chip<?= $daysLeft !== null && $daysLeft < 0 && $project['status'] !== 'completed' ? ' late' : '' ?>">
                    <?= icon('calendar', 13) ?>
                    <?= $daysLeft !== null && $daysLeft < 0
                        ? 'Overdue by ' . abs((int)$daysLeft) . ' days'
                        : ($daysLeft === 0 ? 'Due today' : 'Due ' . format_date($project['due_date'], 'M j, Y')) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($project['description']): ?>
            <p class="project-hero-desc"><?= e($project['description']) ?></p>
        <?php endif; ?>
        <div class="project-hero-progress">
            <div class="progress-head"><span>Overall progress</span><strong><?= (int)$project['progress'] ?>%</strong></div>
            <?= progress_bar((int)$project['progress'], e($project['color'])) ?>
            <div class="progress-foot">
                <span><?= (int)($stats['done'] ?? 0) ?> of <?= (int)($stats['total'] ?? 0) ?> tasks completed</span>
                <span><?= count($members) ?> members</span>
                <span><?= e(format_hours((float)($stats['logged'] ?? 0))) ?> logged / <?= e(format_hours((float)($stats['estimated'] ?? 0))) ?> estimated</span>
            </div>
        </div>
    </div>
    <div class="project-hero-side">
        <div class="hero-stat"><span><?= (int)($stats['in_progress'] ?? 0) ?></span><small>In progress</small></div>
        <div class="hero-stat<?= (int)($stats['blocked'] ?? 0) > 0 ? ' warn' : '' ?>"><span><?= (int)($stats['blocked'] ?? 0) ?></span><small>Blocked</small></div>
        <div class="hero-stat<?= (int)($stats['overdue'] ?? 0) > 0 ? ' danger' : '' ?>"><span><?= (int)($stats['overdue'] ?? 0) ?></span><small>Overdue</small></div>
        <?= avatar_stack($members, 30, 6) ?>
    </div>
</div>

<nav class="tabs">
    <?php
    $tabs = [
        'overview' => ['label' => 'Overview', 'icon' => 'dashboard'],
        'tasks'    => ['label' => 'Tasks <span class="tab-count">' . (int)($stats['total'] ?? 0) . '</span>', 'icon' => 'tasks'],
        'team'     => ['label' => 'Team <span class="tab-count">' . count($members) . '</span>', 'icon' => 'users'],
    ];
    if ($canManage) { $tabs['settings'] = ['label' => 'Settings', 'icon' => 'settings']; }
    foreach ($tabs as $key => $meta): ?>
        <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="<?= e(page_url('project', ['id' => $projectId, 'tab' => $key])) ?>">
            <?= icon($meta['icon'], 15) ?> <?= $meta['label'] ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
    <div class="dash-grid">
        <section class="card span-2">
            <div class="card-head">
                <div><h3>Status breakdown</h3><p class="card-subtitle">How the <?= (int)($stats['total'] ?? 0) ?> tasks are distributed</p></div>
            </div>
            <div class="card-body status-summary">
                <div class="donut-wrap">
                    <div class="chart-container donut" data-chart="donut"
                         data-series='<?= json_encode(array_values(array_map(static function ($key) use ($statusGroups) {
                             $s = task_statuses()[$key];
                             return ['name' => $s['label'], 'value' => count($statusGroups[$key]), 'color' => $s['color']];
                         }, array_keys($statusGroups)))) ?>'></div>
                    <div class="donut-center"><strong><?= (int)($stats['total'] ?? 0) ?></strong><span>tasks</span></div>
                </div>
                <ul class="status-legend">
                    <?php foreach (task_statuses() as $key => $s): ?>
                        <li>
                            <i style="background:<?= e($s['color']) ?>"></i>
                            <a href="<?= e(page_url('project', ['id' => $projectId, 'tab' => 'tasks'])) ?>"><?= e($s['label']) ?></a>
                            <strong><?= count($statusGroups[$key]) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h3>Workload by member</h3></div>
            <div class="card-body">
                <div class="chart-container bars" data-chart="bars"
                     data-labels='<?= json_encode(array_map(static fn($m) => explode(' ', (string)$m['name'])[0], $members)) ?>'
                     data-series='<?= json_encode([
                         ['name' => 'Open',  'color' => '#6366f1', 'data' => array_map(static fn($m) => (int)$m['open_tasks'], $members)],
                         ['name' => 'Done',  'color' => '#10b981', 'data' => array_map(static fn($m) => (int)$m['done_tasks'], $members)],
                     ]) ?>'></div>
            </div>
        </section>

        <section class="card span-2">
            <div class="card-head">
                <div><h3>Priority tasks</h3><p class="card-subtitle">Critical and high-priority work that is still open</p></div>
                <a class="link-btn" href="<?= e(page_url('project', ['id' => $projectId, 'tab' => 'tasks'])) ?>">All tasks</a>
            </div>
            <div class="card-body no-pad">
                <?php
                $hot = array_values(array_filter($tasks, static fn($t) => in_array($t['priority'], ['critical', 'high'], true) && $t['status'] !== 'done'));
                usort($hot, static fn($a, $b) => (days_until($a['due_date']) ?? 9999) <=> (days_until($b['due_date']) ?? 9999));
                ?>
                <?php if (!$hot): ?>
                    <p class="inline-empty">No critical or high-priority tasks open. 🎉</p>
                <?php else: ?>
                    <ul class="mini-task-list">
                        <?php foreach (array_slice($hot, 0, 6) as $t): ?>
                            <?php $d = days_until($t['due_date']); ?>
                            <li class="mini-task">
                                <?= avatar_html($t['assignee_id'] ? $t : null, 26) ?>
                                <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                    <span class="mini-task-title"><?= e($t['title']) ?></span>
                                    <span class="mini-task-sub"><?= e($t['task_key']) ?> · <?= e($t['assignee_name'] ?: 'Unassigned') ?></span>
                                </a>
                                <div class="mini-task-meta">
                                    <?= priority_badge($t['priority']) ?>
                                    <?= status_badge($t['status']) ?>
                                    <?php if ($t['due_date']): ?>
                                        <span class="due-chip<?= $d !== null && $d < 0 ? ' late' : '' ?>"><?= icon('calendar', 13) ?> <?= e(format_date($t['due_date'], 'M j')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h3>Recent activity</h3></div>
            <div class="card-body no-pad">
                <ul class="activity-feed">
                    <?php foreach ($recentLogs as $log): ?>
                        <li>
                            <span class="activity-dot"></span>
                            <div class="activity-body">
                                <p><?= e((string)$log['description'] ?: $log['action']) ?></p>
                                <time><?= e($log['user_name'] ?: 'System') ?> · <?= e(time_ago($log['created_at'])) ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recentLogs): ?><li class="muted inline-empty">Nothing recorded yet.</li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>

<?php elseif ($tab === 'tasks'): ?>
    <div class="project-tasks">
        <?php foreach (task_statuses() as $key => $meta): ?>
            <?php $list = $statusGroups[$key]; ?>
            <section class="card task-group">
                <div class="card-head" style="--col-color:<?= e($meta['color']) ?>">
                    <h3><span class="col-dot"></span> <?= e($meta['label']) ?> <span class="count-pill"><?= count($list) ?></span></h3>
                    <?php if (can('tasks.create')): ?>
                        <button class="btn btn-ghost btn-sm" data-modal-open="taskModal" data-project="<?= $projectId ?>" data-status="<?= e($key) ?>"><?= icon('plus', 14) ?></button>
                    <?php endif; ?>
                </div>
                <div class="card-body no-pad">
                    <?php if (!$list): ?>
                        <p class="inline-empty">Nothing here.</p>
                    <?php else: ?>
                        <ul class="mini-task-list">
                            <?php foreach ($list as $t): ?>
                                <?php $d = days_until($t['due_date']); ?>
                                <li class="mini-task">
                                    <button class="mini-check<?= $t['status'] === 'done' ? ' checked' : '' ?>" data-toggle-done="<?= (int)$t['id'] ?>"
                                            title="Toggle done"><?= $t['status'] === 'done' ? icon('check', 11) : '' ?></button>
                                    <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                        <span class="mini-task-key" style="--key-color:<?= e($project['color']) ?>"><?= e($t['task_key']) ?></span>
                                        <span class="mini-task-title"><?= e($t['title']) ?></span>
                                    </a>
                                    <div class="mini-task-meta">
                                        <?= priority_badge($t['priority']) ?>
                                        <?php if ((int)$t['comments_count'] > 0): ?><span class="card-chip"><?= icon('message', 12) ?> <?= (int)$t['comments_count'] ?></span><?php endif; ?>
                                        <?php if ($t['due_date']): ?>
                                            <span class="due-chip<?= $d !== null && $d < 0 && $t['status'] !== 'done' ? ' late' : '' ?>"><?= icon('calendar', 13) ?> <?= e(format_date($t['due_date'], 'M j')) ?></span>
                                        <?php endif; ?>
                                        <?= avatar_html($t['assignee_id'] ? $t : null, 24) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'team'): ?>
    <div class="card">
        <div class="card-head">
            <div><h3>Project team</h3><p class="card-subtitle"><?= count($members) ?> members · click a row to open their profile</p></div>
            <?php if ($canManage): ?>
                <div class="add-member-row">
                    <select class="input input-sm" id="newMemberSelect">
                        <option value="">Add a member…</option>
                        <?php foreach ($availableUsers as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?><?= $u['job_title'] ? ' — ' . e($u['job_title']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm" id="addMemberBtn" data-project="<?= $projectId ?>" <?= $availableUsers ? '' : 'disabled' ?>>
                        <?= icon('plus', 15) ?> Add
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body no-pad"><div class="table-scroll">
            <table class="table">
                <thead><tr><th>Member</th><th>Role in project</th><th>Open</th><th>Done</th><th>Hours</th><th>Distribution</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <?php $dist = $memberTasks[(int)$m['id']]; $totalM = array_sum($dist) ?: 1; ?>
                        <tr>
                            <td>
                                <a class="person" href="<?= e(page_url('profile', ['id' => (int)$m['id']])) ?>">
                                    <?= avatar_html($m, 30) ?>
                                    <span><strong><?= e($m['name']) ?></strong><small><?= e($m['job_title'] ?: '—') ?></small></span>
                                </a>
                            </td>
                            <td>
                                <?php if ($canManage && (int)$project['owner_id'] !== (int)$m['id']): ?>
                                    <select class="inline-select" data-member-role data-project="<?= $projectId ?>" data-user="<?= (int)$m['id'] ?>">
                                        <?= select_options(['owner' => 'Owner', 'manager' => 'Manager', 'member' => 'Member', 'viewer' => 'Viewer'], $m['project_role']) ?>
                                    </select>
                                <?php else: ?>
                                    <span class="chip"><?= icon('shield', 12) ?> <?= e(ucfirst((string)$m['project_role'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int)$m['open_tasks'] ?></strong></td>
                            <td><?= (int)$m['done_tasks'] ?></td>
                            <td><?= e(format_hours((float)$m['logged_hours'])) ?></td>
                            <td class="cell-dist">
                                <div class="dist-bar">
                                    <?php foreach ($dist as $sKey => $c): if ($c <= 0) { continue; } ?>
                                        <span style="width:<?= ($c / $totalM) * 100 ?>%;background:<?= e(task_statuses()[$sKey]['color']) ?>"
                                              title="<?= e(task_statuses()[$sKey]['label']) ?>: <?= $c ?>"></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <?php if ($canManage): ?>
                                <td class="cell-actions">
                                    <?php if ((int)$project['owner_id'] !== (int)$m['id']): ?>
                                        <button class="icon-btn danger sm" data-remove-member="<?= (int)$m['id'] ?>" data-project="<?= $projectId ?>"
                                                data-name="<?= e($m['name']) ?>" title="Remove from project"><?= icon('trash', 14) ?></button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>

<?php else: /* settings */ ?>
    <?php if (!$canManage): ?>
        <div class="card"><div class="card-body"><?= empty_state('lock', 'Not allowed', 'Only the project owner or a manager can change these settings.') ?></div></div>
    <?php else: ?>
    <div class="settings-grid">
        <form class="card" method="post" action="<?= e(url('api/projects.php')) ?>" data-ajax-form>
            <div class="card-head"><h3><?= icon('settings', 16) ?> Project details</h3></div>
            <div class="card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $projectId ?>">

                <div class="form-grid-2">
                    <?= form_row('Project name', '<input class="input" name="name" value="' . e($project['name']) . '" required maxlength="150">', '', '', true) ?>
                    <?= form_row('Department', '<select class="input" name="department_id"><option value="">None</option>' . select_options($departments, (int)$project['department_id'], 'id', 'name') . '</select>') ?>
                </div>
                <?= form_row('Description', '<textarea class="input" name="description" rows="4">' . e((string)$project['description']) . '</textarea>') ?>
                <div class="form-grid-3">
                    <?= form_row('Status', '<select class="input" name="status">' . select_options(project_statuses(), $project['status'], '', 'label') . '</select>') ?>
                    <?= form_row('Priority', '<select class="input" name="priority">' . select_options(priorities(), $project['priority'], '', 'label') . '</select>') ?>
                    <?= form_row('Visibility', '<select class="input" name="visibility"><option value="public"' . ($project['visibility'] === 'public' ? ' selected' : '') . '>Public</option><option value="private"' . ($project['visibility'] === 'private' ? ' selected' : '') . '>Private</option></select>') ?>
                </div>
                <div class="form-grid-3">
                    <?= form_row('Start date', '<input class="input" type="date" name="start_date" value="' . e((string)$project['start_date']) . '">') ?>
                    <?= form_row('Due date', '<input class="input" type="date" name="due_date" value="' . e((string)$project['due_date']) . '">') ?>
                    <?= form_row('Colour', '<input class="input input-color" type="color" name="color" value="' . e($project['color']) . '">') ?>
                </div>
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('save', 15) ?> Save changes</button></div>
        </form>

        <div class="settings-side">
            <div class="card">
                <div class="card-head"><h3><?= icon('info', 16) ?> Quick facts</h3></div>
                <div class="card-body side-fields">
                    <div class="side-field"><span class="side-label">Short code</span><span class="side-value"><code><?= e($project['code']) ?></code></span></div>
                    <div class="side-field"><span class="side-label">Owner</span><span class="side-value person"><?= avatar_html($project['owner_uid'] ? $project : null, 22) ?> <?= e($project['owner_name'] ?: '—') ?></span></div>
                    <div class="side-field"><span class="side-label">Created</span><span class="side-value"><?= e(format_date($project['created_at'], 'M j, Y')) ?></span></div>
                    <div class="side-field"><span class="side-label">Tasks</span><span class="side-value"><?= (int)($stats['total'] ?? 0) ?> total · <?= (int)($stats['done'] ?? 0) ?> done</span></div>
                    <div class="side-field"><span class="side-label">Members</span><span class="side-value"><?= count($members) ?></span></div>
                </div>
            </div>

            <?php if (can('projects.delete')): ?>
                <div class="card danger-zone">
                    <div class="card-head"><h3><?= icon('alert-triangle', 16) ?> Danger zone</h3></div>
                    <div class="card-body">
                        <p class="muted">Deleting the project removes its <?= (int)($stats['total'] ?? 0) ?> task(s), comments and files permanently.</p>
                        <form method="post" action="<?= e(url('api/projects.php')) ?>" data-confirm-delete
                              data-confirm="Delete the project “<?= e($project['name']) ?>” and all of its tasks?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $projectId ?>">
                            <input type="hidden" name="confirm_tasks" value="1">
                            <button class="btn btn-danger btn-block" type="submit"><?= icon('trash', 15) ?> Delete project</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>