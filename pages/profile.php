<?php
/**
 * TaskFlow — Member profile (public view of a teammate)
 */

$targetId = int_input('id', 0) ?: user_id();
$today    = sql_date();

$member = Db::one(
    'SELECT u.*, r.name AS role_name, r.slug AS role_slug, d.name AS department_name, d.color AS department_color,
            m.name AS manager_name, m.id AS manager_id
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     LEFT JOIN departments d ON d.id = u.department_id
     LEFT JOIN users m ON m.id = u.manager_id
     WHERE u.id = ?',
    [$targetId]
);

if (!$member || !can('users.view')) {
    http_response_code(404);
    echo empty_state('user', 'Member not found', 'This profile does not exist or is not visible to you.')
       . '<div class="center-actions"><a class="btn btn-primary" href="' . e(page_url('team')) . '">Back to team</a></div>';
    return;
}

$isMe = (int)$member['id'] === user_id();

$stats = Db::one(
    "SELECT
        COALESCE(SUM(CASE WHEN status <> 'done' THEN 1 ELSE 0 END),0) AS open_tasks,
        COALESCE(SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END),0) AS done_tasks,
        COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < {$today} AND status <> 'done' THEN 1 ELSE 0 END),0) AS overdue_tasks,
        COALESCE(SUM(COALESCE(estimated_hours,0)),0) AS estimated,
        COALESCE(SUM(actual_hours),0) AS logged,
        COUNT(*) AS total
     FROM tasks WHERE assignee_id = ? AND is_archived = 0 AND parent_id IS NULL",
    [$targetId]
) ?? [];

$completion = (int)($stats['total'] ?? 0) > 0 ? (int)round(((int)$stats['done_tasks'] / (int)$stats['total']) * 100) : 0;

$projects = Db::all(
    'SELECT p.id, p.name, p.code, p.color, p.status, p.progress, pm.project_role,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.assignee_id = ? AND t.status <> "done" AND t.parent_id IS NULL) AS my_open
     FROM project_members pm JOIN projects p ON p.id = pm.project_id
     WHERE pm.user_id = ? ORDER BY p.status = "completed", p.name ASC',
    [$targetId, $targetId]
);

$openTasks = Db::all(
    "SELECT t.*, p.code AS project_code, p.color AS project_color
     FROM tasks t JOIN projects p ON p.id = t.project_id
     WHERE t.assignee_id = ? AND t.is_archived = 0 AND t.parent_id IS NULL AND t.status <> 'done'
     ORDER BY (CASE WHEN t.due_date IS NOT NULL AND t.due_date < {$today} THEN 0 ELSE 1 END),
              (CASE t.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END),
              (t.due_date IS NULL), t.due_date ASC
     LIMIT 8",
    [$targetId]
);

$recentDone = Db::all(
    "SELECT t.*, p.code AS project_code, p.color AS project_color
     FROM tasks t JOIN projects p ON p.id = t.project_id
     WHERE t.assignee_id = ? AND t.status = 'done' AND t.parent_id IS NULL
     ORDER BY t.completed_at DESC LIMIT 6",
    [$targetId]
);

// 8-week completion trend for this member
$weeks = [];
for ($i = 7; $i >= 0; $i--) {
    $weeks[date('Y-m-d', strtotime("-{$i} week"))] = 0;
}
$weekRows = Db::all(
    'SELECT DATE(completed_at) AS d FROM tasks WHERE assignee_id = ? AND completed_at IS NOT NULL',
    [$targetId]
);
foreach ($weekRows as $row) {
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime((string)$row['d'])));
    if (isset($weeks[$weekStart])) { $weeks[$weekStart]++; }
}

$colleagues = Db::all(
    'SELECT id, name, color, avatar, job_title FROM users
     WHERE department_id = ? AND id <> ? AND status = "active" ORDER BY name ASC LIMIT 8',
    [(int)$member['department_id'] ?: 0, $targetId]
);

$GLOBALS['PAGE_TITLE'] = $member['name'];
?>
<?= page_header(
    $member['name'],
    e((string)($member['job_title'] ?: 'Team member')) . ($member['department_name'] ? ' · ' . e($member['department_name']) : ''),
    '<div class="head-btns">'
        . '<a class="btn btn-ghost btn-sm" href="' . e(page_url('tasks', ['assignee' => (int)$member['id']])) . '">' . icon('tasks', 15) . ' Their tasks</a>'
        . ($isMe ? '<a class="btn btn-primary btn-sm" href="' . e(page_url('settings', ['tab' => 'profile'])) . '">' . icon('edit', 15) . ' Edit my profile</a>' : '')
        . (!$isMe && can('users.edit') ? '<a class="btn btn-ghost btn-sm" href="' . e(page_url('team')) . '">' . icon('team', 15) . ' Manage team</a>' : '')
        . '</div>',
    breadcrumbs([
        ['label' => 'Team', 'url' => page_url('team')],
        ['label' => $member['name']],
    ])
) ?>

<div class="profile-layout">
    <aside class="profile-side">
        <div class="card profile-card">
            <div class="profile-cover" style="background:<?= e($member['color'] ?: '#6366f1') ?>"></div>
            <div class="profile-head">
                <?= avatar_html($member, 84, 'profile-avatar') ?>
                <h2><?= e($member['name']) ?></h2>
                <p><?= e((string)($member['job_title'] ?: 'Team member')) ?></p>
                <div class="profile-badges">
                    <span class="chip chip-role"><?= icon('shield', 12) ?> <?= e($member['role_name'] ?: '—') ?></span>
                    <span class="chip chip-<?= $member['status'] === 'active' ? 'success' : 'muted' ?>">
                        <i class="dot"></i> <?= e(ucfirst((string)$member['status'])) ?>
                    </span>
                </div>
            </div>
            <div class="card-body side-fields">
                <div class="side-field"><span class="side-label"><?= icon('mail', 14) ?> Email</span>
                    <a class="side-value link" href="mailto:<?= e($member['email']) ?>"><?= e($member['email']) ?></a></div>
                <?php if ($member['phone']): ?>
                    <div class="side-field"><span class="side-label"><?= icon('phone', 14) ?> Phone</span>
                        <span class="side-value"><?= e($member['phone']) ?></span></div>
                <?php endif; ?>
                <?php if ($member['department_name']): ?>
                    <div class="side-field"><span class="side-label"><?= icon('building', 14) ?> Department</span>
                        <a class="side-value link" href="<?= e(page_url('departments')) ?>">
                            <i class="dot" style="background:<?= e($member['department_color'] ?: '#6366f1') ?>"></i> <?= e($member['department_name']) ?></a></div>
                <?php endif; ?>
                <?php if ($member['manager_name']): ?>
                    <div class="side-field"><span class="side-label"><?= icon('user', 14) ?> Reports to</span>
                        <a class="side-value link" href="<?= e(page_url('profile', ['id' => (int)$member['manager_id']])) ?>"><?= e($member['manager_name']) ?></a></div>
                <?php endif; ?>
                <div class="side-field"><span class="side-label"><?= icon('calendar', 14) ?> Joined</span>
                    <span class="side-value"><?= e(format_date($member['created_at'], 'M j, Y')) ?></span></div>
                <div class="side-field"><span class="side-label"><?= icon('clock', 14) ?> Last seen</span>
                    <span class="side-value"><?= e($member['last_login_at'] ? time_ago($member['last_login_at']) : 'Never') ?></span></div>
            </div>
        </div>

        <?php if ($colleagues): ?>
            <div class="card">
                <div class="card-head"><h3><?= icon('users', 16) ?> Colleagues</h3></div>
                <div class="card-body">
                    <div class="people-row wrap">
                        <?php foreach ($colleagues as $c): ?>
                            <a class="colleague" href="<?= e(page_url('profile', ['id' => (int)$c['id']])) ?>" title="<?= e($c['name']) ?>">
                                <?= avatar_html($c, 30) ?><span><?= e(explode(' ', (string)$c['name'])[0]) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <div class="profile-main">
        <div class="kpi-grid compact">
            <div class="kpi-card" style="--kpi-color:#6366f1">
                <span class="kpi-icon"><?= icon('tasks', 20) ?></span>
                <div class="kpi-body"><span class="kpi-label">Open tasks</span><strong class="kpi-value"><?= (int)($stats['open_tasks'] ?? 0) ?></strong>
                    <span class="kpi-foot"><?= (int)($stats['overdue_tasks'] ?? 0) ?> overdue</span></div>
            </div>
            <div class="kpi-card" style="--kpi-color:#10b981">
                <span class="kpi-icon"><?= icon('check-circle', 20) ?></span>
                <div class="kpi-body"><span class="kpi-label">Completed</span><strong class="kpi-value"><?= (int)($stats['done_tasks'] ?? 0) ?></strong>
                    <span class="kpi-foot"><?= $completion ?>% completion rate</span></div>
            </div>
            <div class="kpi-card" style="--kpi-color:#8b5cf6">
                <span class="kpi-icon"><?= icon('clock', 20) ?></span>
                <div class="kpi-body"><span class="kpi-label">Hours logged</span><strong class="kpi-value"><?= e(format_hours((float)($stats['logged'] ?? 0))) ?></strong>
                    <span class="kpi-foot"><?= e(format_hours((float)($stats['estimated'] ?? 0))) ?> estimated</span></div>
            </div>
            <div class="kpi-card" style="--kpi-color:#0ea5e9">
                <span class="kpi-icon"><?= icon('projects', 20) ?></span>
                <div class="kpi-body"><span class="kpi-label">Projects</span><strong class="kpi-value"><?= count($projects) ?></strong>
                    <span class="kpi-foot">active memberships</span></div>
            </div>
        </div>

        <section class="card">
            <div class="card-head"><div><h3>Completed tasks per week</h3><p class="card-subtitle">Last 8 weeks</p></div></div>
            <div class="card-body">
                <div class="chart-container bars" data-chart="bars"
                     data-labels='<?= json_encode(array_map(static fn($d) => date('M j', strtotime($d)), array_keys($weeks))) ?>'
                     data-series='<?= json_encode([['name' => 'Completed', 'color' => '#10b981', 'data' => array_values($weeks)]]) ?>'></div>
            </div>
        </section>

        <div class="dash-grid two">
            <section class="card">
                <div class="card-head">
                    <div><h3>Current tasks</h3><p class="card-subtitle">Open work assigned to <?= e(explode(' ', (string)$member['name'])[0]) ?></p></div>
                    <a class="link-btn" href="<?= e(page_url('tasks', ['assignee' => (int)$member['id']])) ?>">All</a>
                </div>
                <div class="card-body no-pad">
                    <?php if (!$openTasks): ?>
                        <p class="inline-empty">No open tasks — all clear.</p>
                    <?php else: ?>
                        <ul class="mini-task-list">
                            <?php foreach ($openTasks as $t): ?>
                                <?php $d = days_until($t['due_date']); ?>
                                <li class="mini-task">
                                    <span class="mini-task-key" style="--key-color:<?= e($t['project_color']) ?>"><?= e($t['task_key']) ?></span>
                                    <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                        <span class="mini-task-title"><?= e($t['title']) ?></span>
                                    </a>
                                    <div class="mini-task-meta">
                                        <?= priority_badge($t['priority']) ?>
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
                <div class="card-head"><div><h3>Projects</h3><p class="card-subtitle">Memberships and role in each</p></div></div>
                <div class="card-body no-pad">
                    <?php if (!$projects): ?>
                        <p class="inline-empty">Not a member of any project yet.</p>
                    <?php else: ?>
                        <ul class="project-list-mini">
                            <?php foreach ($projects as $p): ?>
                                <li>
                                    <a href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>">
                                        <span class="project-badge sm" style="--p-color:<?= e($p['color']) ?>"><?= e($p['code']) ?></span>
                                        <span class="pl-name"><?= e($p['name']) ?></span>
                                        <span class="chip chip-muted"><?= e(ucfirst((string)$p['project_role'])) ?></span>
                                        <span class="pl-open"><?= (int)$p['my_open'] ?> open</span>
                                        <?= progress_bar((int)$p['progress'], e($p['color']), 'thin') ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <?php if ($recentDone): ?>
            <section class="card">
                <div class="card-head"><h3><?= icon('check-circle', 16) ?> Recently completed</h3></div>
                <div class="card-body no-pad">
                    <ul class="mini-task-list">
                        <?php foreach ($recentDone as $t): ?>
                            <li class="mini-task">
                                <span class="mini-check checked"><?= icon('check', 11) ?></span>
                                <a class="mini-task-main" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>">
                                    <span class="mini-task-key" style="--key-color:<?= e($t['project_color']) ?>"><?= e($t['task_key']) ?></span>
                                    <span class="mini-task-title is-done"><?= e($t['title']) ?></span>
                                </a>
                                <span class="muted"><?= e(time_ago($t['completed_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
