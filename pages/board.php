<?php
/**
 * TaskFlow — Kanban Task Board
 */

$projectId = int_input('project', 0);
$assignee  = int_input('assignee', 0);
$priority  = (string)query('priority', '');
$search    = (string)query('q', '');

$scope = scope_tasks_sql('t');
$ps    = scope_projects_sql('p');

$projects = Db::all('SELECT id, name, code, color FROM projects p WHERE ' . $ps['sql'] . ' ORDER BY name ASC', $ps['params']);
$people   = Db::all("SELECT id, name, color, avatar FROM users WHERE status = 'active' ORDER BY name ASC");

$where  = ['t.is_archived = 0', 't.parent_id IS NULL', $scope['sql']];
$params = $scope['params'];

if ($projectId > 0) { $where[] = 't.project_id = ?'; $params[] = $projectId; }
if ($assignee > 0)  {
    $where[] = $assignee === -1 ? 't.assignee_id IS NULL' : 't.assignee_id = ?';
    if ($assignee !== -1) { $params[] = $assignee; }
}
if ($priority !== '' && array_key_exists($priority, priorities())) { $where[] = 't.priority = ?'; $params[] = $priority; }
if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.task_key LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$tasks = Db::all(
    "SELECT t.*, p.code AS project_code, p.name AS project_name, p.color AS project_color,
            u.name AS assignee_name, u.color AS assignee_color, u.avatar AS assignee_avatar, u.job_title AS assignee_title,
            (SELECT COUNT(*) FROM comments c WHERE c.task_id = t.id) AS comments_count,
            (SELECT COUNT(*) FROM attachments a WHERE a.task_id = t.id) AS files_count,
            (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = t.id) AS subtasks_total,
            (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = t.id AND s.status = 'done') AS subtasks_done
     FROM tasks t
     JOIN projects p ON p.id = t.project_id
     LEFT JOIN users u ON u.id = t.assignee_id
     WHERE {$whereSql}
     ORDER BY t.position ASC, t.id ASC",
    $params
);

$columns = [];
foreach (board_columns() as $key) {
    $columns[$key] = [];
}
$blockedCards = [];
foreach ($tasks as $task) {
    if ($task['status'] === 'blocked') {
        $blockedCards[] = $task;
        continue;
    }
    if (isset($columns[$task['status']])) {
        $columns[$task['status']][] = $task;
    } else {
        $columns['todo'][] = $task;
    }
}

$today = sql_date();
$allStatuses = task_statuses();
?>
<?= page_header(
    'Task Board',
    'Drag cards between columns to update their status. Changes are saved instantly.',
    '<div class="board-toolbar">'
        . '<form class="board-filters" method="get" action="' . e(url('index.php')) . '">'
        . '<input type="hidden" name="page" value="board">'
        . '<div class="filter-field">' . icon('projects', 15)
        . '<select name="project" onchange="this.form.submit()">'
        . '<option value="0">All projects</option>'
        . select_options($projects, $projectId, 'id', 'name')
        . '</select></div>'
        . '<div class="filter-field">' . icon('user', 15)
        . '<select name="assignee" onchange="this.form.submit()">'
        . '<option value="0">Everyone</option>'
        . '<option value="-1"' . ($assignee === -1 ? ' selected' : '') . '>Unassigned</option>'
        . select_options($people, $assignee, 'id', 'name')
        . '</select></div>'
        . '<div class="filter-field">' . icon('flag', 15)
        . '<select name="priority" onchange="this.form.submit()">'
        . '<option value="">Any priority</option>'
        . select_options(priorities(), $priority, '', 'label')
        . '</select></div>'
        . '<div class="filter-field search-field">' . icon('search', 15)
        . '<input type="search" name="q" value="' . e($search) . '" placeholder="Search this board…">'
        . '</div>'
        . '<button class="btn btn-ghost btn-sm" type="submit">' . icon('filter', 15) . ' Apply</button>'
        . (($projectId || $assignee || $priority || $search) ? '<a class="btn btn-ghost btn-sm" href="' . e(page_url('board')) . '">' . icon('x', 15) . ' Clear</a>' : '')
        . '</form></div>',
    breadcrumbs([['label' => 'Workspace', 'url' => page_url('dashboard')], ['label' => 'Task Board']])
) ?>

<div class="board" id="kanbanBoard"
     data-project="<?= $projectId ?>"
     data-can-edit="<?= can('tasks.edit') ? '1' : '0' ?>"
     data-can-create="<?= can('tasks.create') ? '1' : '0' ?>">

    <?php foreach (board_columns() as $statusKey): ?>
        <?php $meta = $allStatuses[$statusKey]; $cards = $columns[$statusKey]; ?>
        <section class="board-column" data-status="<?= e($statusKey) ?>">
            <header class="board-column-head" style="--col-color:<?= e($meta['color']) ?>">
                <span class="col-dot"></span>
                <h3><?= e($meta['label']) ?></h3>
                <span class="col-count"><?= count($cards) ?></span>
                <?php if (can('tasks.create')): ?>
                    <button class="icon-btn col-add" data-quick-add="<?= e($statusKey) ?>"
                            title="Add a task to <?= e($meta['label']) ?>" aria-label="Add task"><?= icon('plus', 16) ?></button>
                <?php endif; ?>
            </header>
            <div class="board-column-body" data-dropzone="<?= e($statusKey) ?>">
                <?php if (!$cards): ?><div class="board-empty">Drop tasks here</div><?php endif; ?>
                <?php foreach ($cards as $t) { echo task_card($t); } ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="board-column board-column-blocked" data-status="blocked">
        <header class="board-column-head" style="--col-color:<?= e($allStatuses['blocked']['color']) ?>">
            <span class="col-dot"></span>
            <h3>Blocked</h3>
            <span class="col-count"><?= count($blockedCards) ?></span>
            <span class="col-hint"><?= icon('alert-triangle', 13) ?> needs attention</span>
        </header>
        <div class="board-column-body" data-dropzone="blocked">
            <?php if (!$blockedCards): ?><div class="board-empty">Nothing blocked 🎉</div><?php endif; ?>
            <?php foreach ($blockedCards as $t) { echo task_card($t); } ?>
        </div>
    </section>
</div>

<?php
/** Render one kanban card (used by every column). */
function task_card(array $t): string
{
    $allStatuses = task_statuses();
    $prio = priorities()[$t['priority']] ?? ['label' => ucfirst((string)$t['priority']), 'color' => '#94a3b8'];
    $days = days_until($t['due_date']);
    $late = $days !== null && $days < 0;
    $soon = $days !== null && $days >= 0 && $days <= 2;
    $canEdit = can('tasks.edit');

    $html  = '<article class="task-card' . ($t['status'] === 'blocked' ? ' is-blocked' : '') . '"'
           . ' draggable="' . ($canEdit ? 'true' : 'false') . '"'
           . ' data-id="' . (int)$t['id'] . '" data-status="' . e($t['status']) . '" data-project="' . (int)$t['project_id'] . '">';
    $html .= '<span class="card-priority" style="background:' . e($prio['color']) . '" title="' . e($prio['label']) . ' priority"></span>';
    $html .= '<div class="card-top">'
           . '<span class="card-key" style="--key-color:' . e($t['project_color']) . '">' . e($t['task_key']) . '</span>';
    if ((int)$t['subtasks_total'] > 0) {
        $html .= '<span class="card-subtasks" title="Sub-tasks">' . icon('tasks', 12) . ' '
               . (int)$t['subtasks_done'] . '/' . (int)$t['subtasks_total'] . '</span>';
    }
    $html .= '<span class="card-status-dot" style="background:' . e(($allStatuses[$t['status']]['color'] ?? '#94a3b8')) . '" title="'
           . e($allStatuses[$t['status']]['label'] ?? '') . '"></span>';
    $html .= '</div>';

    $html .= '<a class="card-title" href="' . e(page_url('task', ['id' => (int)$t['id']])) . '">' . e($t['title']) . '</a>';

    if ((int)$t['progress'] > 0 && (int)$t['progress'] < 100) {
        $html .= progress_bar((int)$t['progress'], 'var(--primary)', 'card-progress');
    }

    $html .= '<div class="card-foot"><div class="card-meta">';
    if ((int)$t['comments_count'] > 0) {
        $html .= '<span class="card-chip" title="' . (int)$t['comments_count'] . ' comments">' . icon('message', 13) . ' ' . (int)$t['comments_count'] . '</span>';
    }
    if ((int)$t['files_count'] > 0) {
        $html .= '<span class="card-chip" title="' . (int)$t['files_count'] . ' files">' . icon('paperclip', 13) . ' ' . (int)$t['files_count'] . '</span>';
    }
    if ($t['due_date']) {
        $label = $late ? abs((int)$days) . 'd late' : ($days === 0 ? 'Today' : format_date($t['due_date'], 'M j'));
        $html .= '<span class="card-chip due' . ($late ? ' late' : ($soon ? ' soon' : '')) . '">' . icon('calendar', 13) . ' ' . e($label) . '</span>';
    }
    $html .= '</div><div class="card-right">';
    if (!empty($t['estimated_hours'])) {
        $html .= '<span class="card-chip" title="Estimated">' . icon('clock', 12) . ' ' . e(format_hours((float)$t['estimated_hours'])) . '</span>';
    }
    $html .= avatar_html(!empty($t['assignee_id']) ? $t : null, 24);
    $html .= '</div></div></article>';

    return $html;
}
