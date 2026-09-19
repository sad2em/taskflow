<?php
/**
 * TaskFlow — Team directory
 */

$search    = (string)query('q', '');
$deptId    = int_input('department', 0);
$roleId    = int_input('role', 0);
$status    = (string)query('status', 'active');
$sort      = (string)query('sort', 'name');
$today     = sql_date();

$where  = ['1=1'];
$params = [];
if (!in_array($status, ['active', 'inactive', 'suspended', ''], true)) { $status = 'active'; }
if ($status !== '') { $where[] = 'u.status = ?'; $params[] = $status; }
if ($deptId > 0)    { $where[] = 'u.department_id = ?'; $params[] = $deptId; }
if ($roleId > 0)    { $where[] = 'u.role_id = ?'; $params[] = $roleId; }
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.job_title LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$sortMap = [
    'name'      => 'u.name ASC',
    'tasks'     => 'open_tasks DESC, u.name ASC',
    'done'      => 'done_tasks DESC, u.name ASC',
    'hours'     => 'logged_hours DESC, u.name ASC',
    'newest'    => 'u.created_at DESC',
    'department'=> 'd.name ASC, u.name ASC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['name'];

$users = Db::all(
    "SELECT u.*, r.name AS role_name, r.slug AS role_slug, d.name AS department_name, d.color AS department_color,
            m.name AS manager_name,
            COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.status <> 'done' AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS open_tasks,
            COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.status = 'done' AND t.is_archived = 0 AND t.parent_id IS NULL),0) AS done_tasks,
            COALESCE((SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.is_archived = 0 AND t.parent_id IS NULL
                      AND t.due_date IS NOT NULL AND t.due_date < {$today} AND t.status <> 'done'),0) AS overdue_tasks,
            COALESCE((SELECT SUM(t.actual_hours) FROM tasks t WHERE t.assignee_id = u.id),0) AS logged_hours,
            COALESCE((SELECT COUNT(*) FROM project_members pm WHERE pm.user_id = u.id),0) AS projects_count
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     LEFT JOIN departments d ON d.id = u.department_id
     LEFT JOIN users m ON m.id = u.manager_id
     WHERE {$whereSql}
     ORDER BY {$orderSql}",
    $params
);

$departments = Db::all('SELECT * FROM departments WHERE is_active = 1 ORDER BY name ASC');
$roles       = all_roles();

$totals = Db::one(
    "SELECT COUNT(*) AS members,
            COALESCE(SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END),0) AS active_members
     FROM users u"
) ?? [];

$queryBase = array_filter(['q' => $search, 'department' => $deptId ?: '', 'role' => $roleId ?: '', 'status' => $status !== 'active' ? $status : '', 'sort' => $sort !== 'name' ? $sort : ''], static fn($v) => $v !== '');

$canEditUsers = can('users.edit');
?>
<?= page_header(
    'Team',
    (int)($totals['active_members'] ?? 0) . ' active members across ' . count($departments) . ' departments',
    '<div class="head-btns">'
        . (can('reports.export') ? '<a class="btn btn-ghost btn-sm" href="' . e(url('index.php', array_merge(['page' => 'reports', 'tab' => 'team', 'export' => 'csv']))) . '">' . icon('download', 15) . ' Export</a>' : '')
        . (can('users.create') ? '<button class="btn btn-primary btn-sm" data-modal-open="userModal">' . icon('plus', 15) . ' Add member</button>' : '')
        . '</div>',
    breadcrumbs([['label' => 'Workspace', 'url' => page_url('dashboard')], ['label' => 'Team']])
) ?>

<form class="filters-bar" method="get" action="<?= e(url('index.php')) ?>">
    <input type="hidden" name="page" value="team">
    <div class="filter-field grow search-field"><?= icon('search', 15) ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name, email or job title…">
    </div>
    <div class="filter-field"><?= icon('building', 15) ?>
        <select name="department"><option value="">All departments</option><?= select_options($departments, $deptId, 'id', 'name') ?></select>
    </div>
    <div class="filter-field"><?= icon('shield', 15) ?>
        <select name="role"><option value="">All roles</option><?= select_options($roles, $roleId, 'id', 'name') ?></select>
    </div>
    <div class="filter-field"><?= icon('check-circle', 15) ?>
        <select name="status">
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
        </select>
    </div>
    <div class="filter-field"><?= icon('list', 15) ?>
        <select name="sort">
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Sort: Name</option>
            <option value="tasks" <?= $sort === 'tasks' ? 'selected' : '' ?>>Sort: Open tasks</option>
            <option value="done" <?= $sort === 'done' ? 'selected' : '' ?>>Sort: Completed</option>
            <option value="hours" <?= $sort === 'hours' ? 'selected' : '' ?>>Sort: Hours logged</option>
            <option value="department" <?= $sort === 'department' ? 'selected' : '' ?>>Sort: Department</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
        </select>
    </div>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('filter', 15) ?> Apply</button>
    <?php if ($queryBase): ?><a class="btn btn-ghost btn-sm" href="<?= e(page_url('team')) ?>"><?= icon('x', 15) ?> Reset</a><?php endif; ?>
</form>

<div class="card"><div class="card-body no-pad">
    <?php if (!$users): ?>
        <?= empty_state('team', 'No team members found', 'Adjust the filters, or add a new member to your company.',
            can('users.create') ? '<button class="btn btn-primary" data-modal-open="userModal">' . icon('plus', 16) . ' Add member</button>' : '') ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table table-team">
                <thead><tr>
                    <th>Member</th><th>Department</th><th>Role</th><th>Projects</th>
                    <th>Open</th><th>Done</th><th>Hours</th><th>Last seen</th><th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="<?= $u['status'] !== 'active' ? 'row-muted' : '' ?>">
                            <td>
                                <a class="person lg" href="<?= e(page_url('profile', ['id' => (int)$u['id']])) ?>">
                                    <?= avatar_html($u, 38) ?>
                                    <span>
                                        <strong><?= e($u['name']) ?></strong>
                                        <small><?= e($u['email']) ?></small>
                                        <?php if ($u['job_title']): ?><em><?= e($u['job_title']) ?></em><?php endif; ?>
                                    </span>
                                </a>
                                <?php if ($u['status'] !== 'active'): ?>
                                    <span class="chip chip-<?= $u['status'] === 'suspended' ? 'danger' : 'muted' ?>"><?= e(ucfirst((string)$u['status'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['department_name']): ?>
                                    <span class="dept-chip" style="--dept-color:<?= e($u['department_color'] ?: '#6366f1') ?>">
                                        <?= icon('building', 12) ?> <?= e($u['department_name']) ?>
                                    </span>
                                <?php else: ?><span class="muted">—</span><?php endif; ?>
                                <?php if ($u['manager_name']): ?><small class="muted">reports to <?= e($u['manager_name']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="chip chip-role"><?= icon('shield', 12) ?> <?= e($u['role_name'] ?: '—') ?></span></td>
                            <td><?= (int)$u['projects_count'] ?></td>
                            <td>
                                <strong><?= (int)$u['open_tasks'] ?></strong>
                                <?php if ((int)$u['overdue_tasks'] > 0): ?>
                                    <span class="chip chip-danger sm-chip"><?= (int)$u['overdue_tasks'] ?> late</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$u['done_tasks'] ?></td>
                            <td><?= e(format_hours((float)$u['logged_hours'])) ?></td>
                            <td><span class="muted"><?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : 'Never') ?></span></td>
                            <td class="cell-actions">
                                <a class="icon-btn" href="<?= e(page_url('profile', ['id' => (int)$u['id']])) ?>" title="Profile"><?= icon('user', 15) ?></a>
                                <?php if ($canEditUsers): ?>
                                    <button class="icon-btn" data-edit-user='<?= json_encode([
                                        'id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'],
                                        'role_id' => (int)$u['role_id'], 'department_id' => (int)$u['department_id'],
                                        'job_title' => (string)$u['job_title'], 'phone' => (string)$u['phone'], 'status' => $u['status'],
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>' title="Edit member"><?= icon('edit', 15) ?></button>
                                    <button class="icon-btn" data-reset-password="<?= (int)$u['id'] ?>" data-name="<?= e($u['name']) ?>" title="Reset password"><?= icon('key', 15) ?></button>
                                <?php endif; ?>
                                <?php if (can('users.delete') && (int)$u['id'] !== user_id()): ?>
                                    <button class="icon-btn danger" data-delete-user="<?= (int)$u['id'] ?>" data-name="<?= e($u['name']) ?>" title="Delete member"><?= icon('trash', 15) ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div></div>
