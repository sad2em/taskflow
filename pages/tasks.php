<?php
/**
 * TaskFlow — All Tasks (list view with filters, sorting, pagination and CSV export)
 */

$uid    = user_id();
$scope  = scope_tasks_sql('t');
$ps     = scope_projects_sql('p');
$today  = sql_date();

$status    = (string)query('status', '');
$priority  = (string)query('priority', '');
$projectId = int_input('project', 0);
$assignee  = int_input('assignee', 0);
$search    = (string)query('q', '');
$overdue   = bool_input('overdue');
$mine      = bool_input('mine');
$sort      = (string)query('sort', 'updated');
$dir       = strtolower((string)query('dir', '')) === 'asc' ? 'ASC' : 'DESC';

$perPage   = max(5, min(100, (int)setting('items_per_page', 12)));
$currentPage = max(1, int_input('p', 1));

$projects = Db::all('SELECT id, name, code, color FROM projects p WHERE ' . $ps['sql'] . ' ORDER BY name ASC', $ps['params']);
$people   = Db::all("SELECT id, name, color, avatar FROM users WHERE status = 'active' ORDER BY name ASC");

$where  = ['t.is_archived = 0', 't.parent_id IS NULL', $scope['sql']];
$params = $scope['params'];

if ($status !== '' && array_key_exists($status, task_statuses())) { $where[] = 't.status = ?'; $params[] = $status; }
if ($priority !== '' && array_key_exists($priority, priorities())) { $where[] = 't.priority = ?'; $params[] = $priority; }
if ($projectId > 0) { $where[] = 't.project_id = ?'; $params[] = $projectId; }
if ($mine)          { $where[] = 't.assignee_id = ?'; $params[] = $uid; }
if ($assignee === -1) { $where[] = 't.assignee_id IS NULL'; }
elseif ($assignee > 0) { $where[] = 't.assignee_id = ?'; $params[] = $assignee; }
if ($overdue) { $where[] = "t.due_date IS NOT NULL AND t.due_date < {$today} AND t.status <> 'done'"; }
if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.task_key LIKE ? OR t.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$sortMap = [
    'due'      => 't.due_date',
    'priority' => "CASE t.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END",
    'status'   => "CASE t.status WHEN 'blocked' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'in_review' THEN 2 WHEN 'todo' THEN 3 WHEN 'backlog' THEN 4 ELSE 5 END",
    'created'  => 't.created_at',
    'updated'  => 't.updated_at',
    'title'    => 't.title',
    'progress' => 't.progress',
    'assignee' => 'a.name',
];
$sortSql = ($sortMap[$sort] ?? $sortMap['updated']) . ' ' . $dir . ', t.id DESC';

$total = Db::count(
    "SELECT COUNT(*) FROM tasks t LEFT JOIN users a ON a.id = t.assignee_id WHERE {$whereSql}",
    $params
);
$pg = paginate($total, $perPage, $currentPage);

$tasks = Db::all(
    "SELECT t.*, p.code AS project_code, p.name AS project_name, p.color AS project_color,
            a.name AS assignee_name, a.color AS assignee_color, a.avatar AS assignee_avatar, a.job_title AS assignee_title,
            r.name AS reporter_name,
            (SELECT COUNT(*) FROM comments c WHERE c.task_id = t.id) AS comments_count,
            (SELECT COUNT(*) FROM attachments f WHERE f.task_id = t.id) AS files_count,
            (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = t.id) AS subtasks_total,
            (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = t.id AND s.status = 'done') AS subtasks_done
     FROM tasks t
     JOIN projects p ON p.id = t.project_id
     LEFT JOIN users a ON a.id = t.assignee_id
     LEFT JOIN users r ON r.id = t.reporter_id
     WHERE {$whereSql}
     ORDER BY {$sortSql}
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$queryBase = array_filter([
    'status' => $status, 'priority' => $priority, 'project' => $projectId ?: '',
    'assignee' => $assignee ?: '', 'q' => $search, 'overdue' => $overdue ? 1 : '',
    'mine' => $mine ? 1 : '', 'sort' => $sort, 'dir' => $dir === 'ASC' ? 'asc' : '',
], static fn($v) => $v !== '' && $v !== null);

/* ---------------- CSV export ---------------- */
if (query('export') === 'csv' && can('reports.export')) {
    $export = Db::all(
        "SELECT t.task_key, t.title, t.status, t.priority, p.name AS project,
                a.name AS assignee, r.name AS reporter, t.start_date, t.due_date, t.completed_at,
                t.estimated_hours, t.actual_hours, t.progress, t.created_at
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         LEFT JOIN users a ON a.id = t.assignee_id
         LEFT JOIN users r ON r.id = t.reporter_id
         WHERE {$whereSql} ORDER BY t.id DESC",
        $params
    );
    $rows = [['Key', 'Title', 'Status', 'Priority', 'Project', 'Assignee', 'Reporter', 'Start', 'Due', 'Completed', 'Est. hours', 'Logged hours', 'Progress %', 'Created']];
    foreach ($export as $row) {
        $rows[] = [
            $row['task_key'], $row['title'], task_statuses()[$row['status']]['label'] ?? $row['status'],
            priorities()[$row['priority']]['label'] ?? $row['priority'], $row['project'],
            $row['assignee'] ?: 'Unassigned', $row['reporter'], $row['start_date'] ?: '', $row['due_date'] ?: '',
            $row['completed_at'] ?: '', $row['estimated_hours'] ?: '', $row['actual_hours'], $row['progress'], $row['created_at'],
        ];
    }
    activity('report.export', null, null, 'Exported ' . count($export) . ' tasks to CSV');
    csv_download('taskflow-tasks-' . date('Y-m-d') . '.csv', $rows);
}

$dueSoonDays = max(1, (int)setting('due_soon_days', 3));
?>
<?= page_header(
    $mine ? 'My Tasks' : 'All Tasks',
    $total . ' task' . ($total === 1 ? '' : 's') . ' found' . ($overdue ? ' · overdue only' : '') . ($search !== '' ? ' · matching “' . e($search) . '”' : ''),
    '<div class="head-btns">'
        . (can('reports.export') ? '<a class="btn btn-ghost btn-sm" href="' . e(url('index.php', array_merge(['page' => 'tasks'], $queryBase, ['export' => 'csv']))) . '">' . icon('download', 15) . ' Export CSV</a>' : '')
        . '<a class="btn btn-ghost btn-sm" href="' . e(page_url('board', $queryBase)) . '">' . icon('board', 15) . ' Board view</a>'
        . (can('tasks.create') ? '<button class="btn btn-primary btn-sm" data-modal-open="taskModal">' . icon('plus', 15) . ' New task</button>' : '')
        . '</div>',
    breadcrumbs([['label' => 'Workspace', 'url' => page_url('dashboard')], ['label' => $mine ? 'My Tasks' : 'All Tasks']])
) ?>

<form class="filters-bar" method="get" action="<?= e(url('index.php')) ?>">
    <input type="hidden" name="page" value="tasks">
    <?php if ($mine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
    <?php if ($sort !== 'updated'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <?php if ($dir === 'ASC'): ?><input type="hidden" name="dir" value="asc"><?php endif; ?>

    <div class="filter-field grow search-field">
        <?= icon('search', 15) ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by title, key or description…">
    </div>
    <div class="filter-field"><?= icon('projects', 15) ?>
        <select name="project"><option value="">All projects</option><?= select_options($projects, $projectId, 'id', 'name') ?></select>
    </div>
    <div class="filter-field"><?= icon('user', 15) ?>
        <select name="assignee">
            <option value="">Everyone</option>
            <option value="-1" <?= $assignee === -1 ? 'selected' : '' ?>>Unassigned</option>
            <?= select_options($people, $assignee, 'id', 'name') ?>
        </select>
    </div>
    <div class="filter-field"><?= icon('circle', 15) ?>
        <select name="status"><option value="">Any status</option><?= select_options(task_statuses(), $status, '', 'label') ?></select>
    </div>
    <div class="filter-field"><?= icon('flag', 15) ?>
        <select name="priority"><option value="">Any priority</option><?= select_options(priorities(), $priority, '', 'label') ?></select>
    </div>
    <label class="checkbox chip-toggle">
        <input type="checkbox" name="overdue" value="1" <?= $overdue ? 'checked' : '' ?> onchange="this.form.submit()">
        <span><?= icon('alert-triangle', 14) ?> Overdue only</span>
    </label>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('filter', 15) ?> Apply</button>
    <?php if ($queryBase): ?><a class="btn btn-ghost btn-sm" href="<?= e(page_url('tasks')) ?>"><?= icon('x', 15) ?> Reset</a><?php endif; ?>
</form>

<div class="card">
    <div class="card-body no-pad">
        <?php if (!$tasks): ?>
            <?= empty_state('tasks', 'No tasks match these filters',
                'Try clearing a filter or create a new task to get things moving.',
                can('tasks.create') ? '<button class="btn btn-primary" data-modal-open="taskModal">' . icon('plus', 16) . ' New task</button>' : '') ?>
        <?php else: ?>
            <div class="table-scroll">
                <table class="table table-tasks">
                    <thead>
                        <tr>
                            <th class="w-check"></th>
                            <?php
                            $cols = [
                                'key'      => ['label' => 'Key',       'sort' => ''],
                                'title'    => ['label' => 'Task',      'sort' => 'title'],
                                'status'   => ['label' => 'Status',    'sort' => 'status'],
                                'priority' => ['label' => 'Priority',  'sort' => 'priority'],
                                'assignee' => ['label' => 'Assignee',  'sort' => 'assignee'],
                                'due'      => ['label' => 'Due',       'sort' => 'due'],
                                'progress' => ['label' => 'Progress',  'sort' => 'progress'],
                                'meta'     => ['label' => '',          'sort' => ''],
                                'actions'  => ['label' => '',          'sort' => ''],
                            ];
                            foreach ($cols as $key => $col):
                                $active = $col['sort'] !== '' && $col['sort'] === $sort;
                                $nextDir = $active && $dir === 'DESC' ? 'asc' : '';
                            ?>
                                <th class="<?= $active ? 'sorted' : '' ?>">
                                    <?php if ($col['sort']): ?>
                                        <a href="<?= e(url('index.php', array_merge(['page' => 'tasks'], $queryBase, ['sort' => $col['sort'], 'dir' => $nextDir, 'p' => '']))) ?>">
                                            <?= e($col['label']) ?>
                                            <?= icon($active ? ($dir === 'DESC' ? 'chevron-down' : 'chevron-down') : 'chevron-down', 12, 'sort-icon' . ($active ? '' : ' muted')) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e($col['label']) ?>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $t): ?>
                            <?php
                            $days = days_until($t['due_date']);
                            $late = $days !== null && $days < 0 && $t['status'] !== 'done';
                            $soon = $days !== null && $days >= 0 && $days <= $dueSoonDays && $t['status'] !== 'done';
                            ?>
                            <tr data-id="<?= (int)$t['id'] ?>" class="<?= $t['status'] === 'done' ? 'row-done' : '' ?><?= $late ? ' row-late' : '' ?>">
                                <td class="w-check">
                                    <button class="mini-check<?= $t['status'] === 'done' ? ' checked' : '' ?>"
                                            data-toggle-done="<?= (int)$t['id'] ?>"
                                            title="<?= $t['status'] === 'done' ? 'Reopen task' : 'Mark as done' ?>"
                                            aria-label="Toggle done"><?= $t['status'] === 'done' ? icon('check', 11) : '' ?></button>
                                </td>
                                <td><span class="task-key" style="--key-color:<?= e($t['project_color']) ?>"><?= e($t['task_key']) ?></span></td>
                                <td class="cell-title">
                                    <a class="task-title" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>"><?= e($t['title']) ?></a>
                                    <span class="task-project"><?= e($t['project_name']) ?></span>
                                    <?php if ((int)$t['subtasks_total'] > 0): ?>
                                        <span class="chip chip-muted"><?= icon('tasks', 11) ?> <?= (int)$t['subtasks_done'] ?>/<?= (int)$t['subtasks_total'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="inline-select status-select" data-status-change="<?= (int)$t['id'] ?>"
                                            <?= can('tasks.edit') ? '' : 'disabled' ?>
                                            style="--sel-color:<?= e(task_statuses()[$t['status']]['color']) ?>">
                                        <?= select_options(task_statuses(), $t['status'], '', 'label') ?>
                                    </select>
                                </td>
                                <td><?= priority_badge($t['priority']) ?></td>
                                <td>
                                    <?php if ($t['assignee_id']): ?>
                                        <a class="person" href="<?= e(page_url('profile', ['id' => (int)$t['assignee_id']])) ?>">
                                            <?= avatar_html($t, 24) ?><span><?= e($t['assignee_name']) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="person muted"><?= avatar_html(null, 24) ?> Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['due_date']): ?>
                                        <span class="due-chip<?= $late ? ' late' : ($soon ? ' soon' : '') ?>">
                                            <?= icon('calendar', 13) ?> <?= e(format_date($t['due_date'], 'M j, Y')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-progress">
                                    <div class="progress-wrap">
                                        <?= progress_bar((int)$t['progress']) ?>
                                        <span><?= (int)$t['progress'] ?>%</span>
                                    </div>
                                </td>
                                <td class="cell-meta">
                                    <?php if ((int)$t['comments_count'] > 0): ?><span class="card-chip"><?= icon('message', 12) ?> <?= (int)$t['comments_count'] ?></span><?php endif; ?>
                                    <?php if ((int)$t['files_count'] > 0): ?><span class="card-chip"><?= icon('paperclip', 12) ?> <?= (int)$t['files_count'] ?></span><?php endif; ?>
                                </td>
                                <td class="cell-actions">
                                    <a class="icon-btn" href="<?= e(page_url('task', ['id' => (int)$t['id']])) ?>" title="Open task"><?= icon('external', 15) ?></a>
                                    <?php if (can('tasks.edit')): ?>
                                        <button class="icon-btn" data-edit-task='<?= json_encode([
                                            'id' => (int)$t['id'], 'title' => $t['title'], 'description' => (string)$t['description'],
                                            'project_id' => (int)$t['project_id'], 'assignee_id' => (int)$t['assignee_id'],
                                            'priority' => $t['priority'], 'status' => $t['status'], 'due_date' => (string)$t['due_date'],
                                            'start_date' => (string)$t['start_date'], 'estimated_hours' => (string)$t['estimated_hours'],
                                            'progress' => (int)$t['progress'], 'task_key' => $t['task_key'],
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>' title="Edit task"><?= icon('edit', 15) ?></button>
                                    <?php endif; ?>
                                    <?php if (can('tasks.delete')): ?>
                                        <button class="icon-btn danger" data-delete-task="<?= (int)$t['id'] ?>"
                                                data-title="<?= e($t['task_key']) ?>" title="Delete task"><?= icon('trash', 15) ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
        <div class="card-foot">
            <?= pagination_html($pg, 'index.php', array_merge(['page' => 'tasks'], $queryBase)) ?>
        </div>
    <?php endif; ?>
</div>
