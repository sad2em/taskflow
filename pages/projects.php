<?php
/**
 * TaskFlow — Projects list
 */

$status  = (string)query('status', '');
$search  = (string)query('q', '');
$view    = query('view') === 'list' ? 'list' : 'grid';
$perPage = max(4, min(60, (int)setting('items_per_page', 12)));
$page    = max(1, int_input('p', 1));

$ps = scope_projects_sql('p');
$where  = [$ps['sql']];
$params = $ps['params'];

if ($status !== '' && array_key_exists($status, project_statuses())) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$total = Db::count("SELECT COUNT(*) FROM projects p WHERE {$whereSql}", $params);
$pg = paginate($total, $perPage, $page);

$projects = Db::all(
    "SELECT p.*, d.name AS department_name,
            u.id AS owner_uid, u.name AS owner_name, u.color AS owner_color, u.avatar AS owner_avatar, u.job_title AS owner_title,
            (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS members_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_archived = 0 AND t.parent_id IS NULL) AS tasks_total,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_archived = 0 AND t.parent_id IS NULL AND t.status = 'done') AS tasks_done,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_archived = 0 AND t.parent_id IS NULL AND t.status <> 'done'
                AND t.due_date IS NOT NULL AND t.due_date < " . sql_date() . ") AS tasks_overdue,
            (SELECT COALESCE(SUM(t.actual_hours),0) FROM tasks t WHERE t.project_id = p.id) AS logged_hours,
            (SELECT COALESCE(SUM(t.estimated_hours),0) FROM tasks t WHERE t.project_id = p.id) AS estimated_hours
     FROM projects p
     LEFT JOIN users u ON u.id = p.owner_id
     LEFT JOIN departments d ON d.id = p.department_id
     WHERE {$whereSql}
     ORDER BY CASE p.status WHEN 'active' THEN 0 WHEN 'planning' THEN 1 WHEN 'on_hold' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
              (p.due_date IS NULL), p.due_date ASC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$counts = Db::one(
    "SELECT
        COALESCE(SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END),0) AS active,
        COALESCE(SUM(CASE WHEN p.status = 'planning' THEN 1 ELSE 0 END),0) AS planning,
        COALESCE(SUM(CASE WHEN p.status = 'on_hold' THEN 1 ELSE 0 END),0) AS on_hold,
        COALESCE(SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END),0) AS completed,
        COUNT(*) AS total
     FROM projects p WHERE " . $ps['sql'],
    $ps['params']
) ?? [];

$queryBase = array_filter(['status' => $status, 'q' => $search, 'view' => $view === 'list' ? 'list' : ''], static fn($v) => $v !== '');
?>
<?= page_header(
    'Projects',
    (int)($counts['total'] ?? 0) . ' projects · ' . (int)($counts['active'] ?? 0) . ' active · ' . (int)($counts['completed'] ?? 0) . ' completed',
    '<div class="head-btns">'
        . '<div class="view-switch">'
        . '<a class="view-btn' . ($view === 'grid' ? ' active' : '') . '" href="' . e(url('index.php', array_merge(['page' => 'projects'], $queryBase, ['view' => '']))) . '" title="Grid view">' . icon('grid', 15) . '</a>'
        . '<a class="view-btn' . ($view === 'list' ? ' active' : '') . '" href="' . e(url('index.php', array_merge(['page' => 'projects'], $queryBase, ['view' => 'list']))) . '" title="List view">' . icon('list', 15) . '</a>'
        . '</div>'
        . (can('projects.create') ? '<button class="btn btn-primary btn-sm" data-modal-open="projectModal">' . icon('plus', 15) . ' New project</button>' : '')
        . '</div>',
    breadcrumbs([['label' => 'Workspace', 'url' => page_url('dashboard')], ['label' => 'Projects']])
) ?>

<form class="filters-bar" method="get" action="<?= e(url('index.php')) ?>">
    <input type="hidden" name="page" value="projects">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="filter-field grow search-field">
        <?= icon('search', 15) ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search projects…">
    </div>
    <div class="filter-field"><?= icon('projects', 15) ?>
        <select name="status"><option value="">All statuses</option><?= select_options(project_statuses(), $status, '', 'label') ?></select>
    </div>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('filter', 15) ?> Apply</button>
    <?php if ($queryBase): ?><a class="btn btn-ghost btn-sm" href="<?= e(page_url('projects')) ?>"><?= icon('x', 15) ?> Reset</a><?php endif; ?>
    <div class="status-tabs">
        <a class="status-tab<?= $status === '' ? ' active' : '' ?>" href="<?= e(url('index.php', array_merge(['page' => 'projects'], ['q' => $search, 'view' => $view === 'list' ? 'list' : '']))) ?>">All <span><?= (int)($counts['total'] ?? 0) ?></span></a>
        <?php foreach (project_statuses() as $key => $meta): ?>
            <a class="status-tab<?= $status === $key ? ' active' : '' ?>"
               href="<?= e(url('index.php', array_merge(['page' => 'projects'], ['status' => $key, 'q' => $search, 'view' => $view === 'list' ? 'list' : '']))) ?>"
               style="--tab-color:<?= e($meta['color']) ?>">
                <?= e($meta['label']) ?> <span><?= (int)($counts[$key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</form>

<?php if (!$projects): ?>
    <div class="card"><div class="card-body">
        <?= empty_state('projects', 'No projects found',
            $search || $status ? 'Try a different search or filter.' : 'Create your first project to start distributing work across the team.',
            can('projects.create') ? '<button class="btn btn-primary" data-modal-open="projectModal">' . icon('plus', 16) . ' New project</button>' : '') ?>
    </div></div>
<?php elseif ($view === 'grid'): ?>
    <div class="project-grid">
        <?php foreach ($projects as $p): ?>
            <?php
            $days = days_until($p['due_date']);
            $members = Db::all(
                'SELECT u.id, u.name, u.color, u.avatar, u.job_title FROM project_members pm
                 JOIN users u ON u.id = pm.user_id WHERE pm.project_id = ? ORDER BY pm.project_role ASC, u.name ASC LIMIT 6',
                [(int)$p['id']]
            );
            ?>
            <article class="project-card" style="--p-color:<?= e($p['color']) ?>">
                <div class="project-card-top">
                    <span class="project-badge"><?= e($p['code']) ?></span>
                    <div class="project-card-actions">
                        <?= project_status_badge($p['status']) ?>
                        <?php if (can_manage_project($p)): ?>
                            <a class="icon-btn sm" href="<?= e(page_url('project', ['id' => (int)$p['id'], 'tab' => 'settings'])) ?>" title="Project settings"><?= icon('settings', 14) ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <a class="project-card-title" href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>"><?= e($p['name']) ?></a>
                <p class="project-card-desc"><?= e(excerpt((string)$p['description'], 96)) ?></p>

                <div class="project-card-progress">
                    <div class="progress-head">
                        <span>Progress</span><strong><?= (int)$p['progress'] ?>%</strong>
                    </div>
                    <?= progress_bar((int)$p['progress'], e($p['color'])) ?>
                    <div class="progress-foot">
                        <span><?= (int)$p['tasks_done'] ?>/<?= (int)$p['tasks_total'] ?> tasks done</span>
                        <?php if ((int)$p['tasks_overdue'] > 0): ?>
                            <span class="text-danger"><?= (int)$p['tasks_overdue'] ?> overdue</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="project-card-meta">
                    <?php if ($p['department_name']): ?><span class="chip chip-muted"><?= icon('building', 12) ?> <?= e($p['department_name']) ?></span><?php endif; ?>
                    <?= priority_badge($p['priority']) ?>
                    <?php if ($p['visibility'] === 'private'): ?><span class="chip chip-muted"><?= icon('lock', 12) ?> Private</span><?php endif; ?>
                </div>

                <div class="project-card-foot">
                    <?= avatar_stack($members, 26, 5) ?>
                    <div class="project-card-dates">
                        <?php if ($p['due_date']): ?>
                            <span class="due-chip<?= $days !== null && $days < 0 && $p['status'] !== 'completed' ? ' late' : '' ?>">
                                <?= icon('calendar', 12) ?>
                                <?= $days !== null && $days < 0 ? 'Overdue ' . abs($days) . 'd' : format_date($p['due_date'], 'M j, Y') ?>
                            </span>
                        <?php else: ?>
                            <span class="muted">No deadline</span>
                        <?php endif; ?>
                        <span class="chip chip-muted"><?= icon('clock', 12) ?> <?= e(format_hours((float)$p['logged_hours'])) ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card"><div class="card-body no-pad"><div class="table-scroll">
        <table class="table">
            <thead><tr>
                <th>Project</th><th>Status</th><th>Owner</th><th>Team</th><th>Tasks</th>
                <th>Progress</th><th>Hours</th><th>Deadline</th><th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                    <?php $days = days_until($p['due_date']); ?>
                    <tr>
                        <td class="cell-title">
                            <a class="task-title" href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>">
                                <i class="dot" style="background:<?= e($p['color']) ?>"></i> <?= e($p['name']) ?>
                            </a>
                            <span class="task-project"><?= e($p['code']) ?><?= $p['department_name'] ? ' · ' . e($p['department_name']) : '' ?></span>
                        </td>
                        <td><?= project_status_badge($p['status']) ?></td>
                        <td><span class="person"><?= avatar_html($p['owner_uid'] ? $p : null, 24) ?> <?= e($p['owner_name'] ?: '—') ?></span></td>
                        <td><?= (int)$p['members_count'] ?> members</td>
                        <td><?= (int)$p['tasks_done'] ?>/<?= (int)$p['tasks_total'] ?></td>
                        <td class="cell-progress"><div class="progress-wrap"><?= progress_bar((int)$p['progress'], e($p['color'])) ?><span><?= (int)$p['progress'] ?>%</span></div></td>
                        <td><?= e(format_hours((float)$p['logged_hours'])) ?></td>
                        <td><?= $p['due_date'] ? '<span class="due-chip' . ($days !== null && $days < 0 ? ' late' : '') . '">' . icon('calendar', 13) . ' ' . e(format_date($p['due_date'], 'M j, Y')) . '</span>' : '<span class="muted">—</span>' ?></td>
                        <td class="cell-actions"><a class="icon-btn" href="<?= e(page_url('project', ['id' => (int)$p['id']])) ?>" title="Open"><?= icon('external', 15) ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php if ($pg['pages'] > 1): ?><div class="card-foot"><?= pagination_html($pg, 'index.php', array_merge(['page' => 'projects'], $queryBase)) ?></div><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($view === 'grid' && $pg['pages'] > 1): ?>
    <?= pagination_html($pg, 'index.php', array_merge(['page' => 'projects'], $queryBase)) ?>
<?php endif; ?>
