<?php
/**
 * TaskFlow — Tasks API
 * Actions: list, create, update, move, delete, toggle_done, watch, log_time, reorder
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    /* ================= CREATE ================= */
    case 'create':
        require_permission('tasks.create');

        $title     = (string)input('title', '');
        $projectId = int_input('project_id');
        if (mb_strlen($title) < 3)  { api_fail('Please enter a task title (at least 3 characters).'); }
        if ($projectId <= 0)        { api_fail('Please choose a project for this task.'); }

        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if (!$project) { api_fail('That project does not exist.'); }

        $assigneeId = int_input('assignee_id');
        $parentId   = int_input('parent_id');
        $status     = (string)input('status', 'todo');
        if (!array_key_exists($status, task_statuses())) { $status = 'todo'; }
        $priority = (string)input('priority', 'medium');
        if (!array_key_exists($priority, priorities())) { $priority = 'medium'; }

        // Auto task key: PROJECT-CODE-N
        $projectCode = strtoupper((string)$project['code']);
        $maxN = (int)Db::value(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(task_key, '-', -1) AS UNSIGNED)), 0) FROM tasks WHERE project_id = ?",
            [$projectId]
        );
        $taskKey = $projectCode . '-' . ($maxN + 1);

        $position = (int)Db::value(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE project_id = ? AND status = ? AND parent_id '
            . ($parentId > 0 ? '= ?' : 'IS NULL'),
            $parentId > 0 ? [$projectId, $status, $parentId] : [$projectId, $status]
        );

        $taskId = Db::insert('tasks', [
            'project_id'      => $projectId,
            'parent_id'       => $parentId > 0 ? $parentId : null,
            'task_key'        => $taskKey,
            'title'           => mb_substr($title, 0, 200),
            'description'     => (string)input('description', '') ?: null,
            'status'          => $status,
            'priority'        => $priority,
            'assignee_id'     => $assigneeId > 0 ? $assigneeId : null,
            'reporter_id'     => user_id(),
            'start_date'      => input('start_date') ?: null,
            'due_date'        => input('due_date') ?: null,
            'estimated_hours' => is_numeric(input('estimated_hours')) ? (float)input('estimated_hours') : null,
            'progress'        => $status === 'done' ? 100 : 0,
            'position'        => $position,
        ]);

        // Reporter always follows the task
        Db::insertIgnore('task_watchers', ['task_id', 'user_id'], '?, ?', [$taskId, user_id()]);

        $task = task_row($taskId);
        activity('task.create', 'task', $taskId, 'Created task ' . $taskKey . ' — ' . $title);

        if ($assigneeId > 0) {
            notify_user($assigneeId, 'task_assigned',
                'You were assigned ' . $taskKey,
                user_name() . ' assigned "' . $title . '" to you.',
                'index.php?page=task&id=' . $taskId, 'task', $taskId);
        }
        if ($parentId > 0) {
            $parent = Db::one('SELECT * FROM tasks WHERE id = ?', [$parentId]);
            if ($parent) { recalc_task_progress($parentId); }
        }
        recalc_project_progress($projectId);

        api_ok(['id' => $taskId, 'task' => $task, 'message' => 'Task ' . $taskKey . ' created.']);
        break;

    /* ================= UPDATE ================= */
    case 'update':
        require_permission('tasks.edit');
        $taskId = int_input('id');
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }

        $data = [];
        foreach (['title', 'description', 'priority', 'start_date', 'due_date'] as $field) {
            if (isset($_POST[$field])) {
                $value = trim((string)$_POST[$field]);
                if (in_array($field, ['start_date', 'due_date'], true) && $value === '') { $value = null; }
                if ($field === 'title' && mb_strlen($value) < 3) { api_fail('The task title is too short.'); }
                $data[$field] = $value;
            }
        }
        if (isset($_POST['priority']) && !array_key_exists($_POST['priority'], priorities())) {
            unset($data['priority']);
        }
        if (isset($_POST['status']) && array_key_exists($_POST['status'], task_statuses())) {
            $data['status'] = $_POST['status'];
        }
        if (isset($_POST['estimated_hours'])) {
            $data['estimated_hours'] = is_numeric($_POST['estimated_hours']) ? (float)$_POST['estimated_hours'] : null;
        }
        if (isset($_POST['progress'])) {
            $data['progress'] = max(0, min(100, (int)$_POST['progress']));
        }
        if (isset($_POST['project_id']) && (int)$_POST['project_id'] > 0) {
            $target = Db::one('SELECT * FROM projects WHERE id = ?', [(int)$_POST['project_id']]);
            if ($target) {
                $data['project_id'] = (int)$target['id'];
                $data['task_key'] = strtoupper($target['code']) . '-' . explode('-', (string)$task['task_key'])[1];
            }
        }
        if (isset($_POST['assignee_id'])) {
            $data['assignee_id'] = (int)$_POST['assignee_id'] > 0 ? (int)$_POST['assignee_id'] : null;
        }

        if (!$data) { api_fail('Nothing to update.'); }

        Db::update('tasks', $data, 'id = :id', ['id' => $taskId]);

        /* --- side effects --- */
        $changes = [];
        foreach ($data as $field => $value) {
            $old = $task[$field] ?? null;
            if ((string)$old !== (string)$value) {
                $changes[$field] = ['from' => $old, 'to' => $value];
            }
        }

        if (isset($changes['assignee_id']) && $changes['assignee_id']['to']) {
            $newAssignee = (int)$changes['assignee_id']['to'];
            Db::insertIgnore('task_watchers', ['task_id', 'user_id'], '?, ?', [$taskId, $newAssignee]);
            notify_user($newAssignee, 'task_assigned',
                'You were assigned ' . $task['task_key'],
                user_name() . ' assigned "' . ($data['title'] ?? $task['title']) . '" to you.',
                'index.php?page=task&id=' . $taskId, 'task', $taskId);
            $changes['assignee_id']['label'] = 'Assignee changed to ' . (user_by_id($newAssignee)['name'] ?? 'someone');
        }

        if (isset($changes['status'])) {
            $to = $changes['status']['to'];
            Db::update('tasks', [
                'completed_at' => $to === 'done' ? date('Y-m-d H:i:s') : null,
                'progress'     => $to === 'done' ? 100 : (int)$task['progress'],
            ], 'id = :id', ['id' => $taskId]);
            notify_task_stakeholders($task, 'task_status',
                $task['task_key'] . ' moved to ' . (task_statuses()[$to]['label'] ?? $to),
                user_name() . ' changed the status from ' . (task_statuses()[$changes['status']['from']]['label'] ?? '—')
                    . ' to ' . (task_statuses()[$to]['label'] ?? $to) . '.');
        }

        if ($task['parent_id']) {
            recalc_task_progress((int)$task['parent_id']);
        }
        recalc_project_progress((int)($data['project_id'] ?? $task['project_id']));

        if ($changes) {
            activity('task.update', 'task', $taskId,
                'Updated ' . $task['task_key'] . ' (' . implode(', ', array_keys($changes)) . ')', ['changes' => $changes]);
        }

        api_ok(['task' => task_row($taskId), 'message' => 'Task updated.']);
        break;

    /* ================= QUICK MOVE (kanban drag & drop) ================= */
    case 'move':
        require_permission('tasks.edit');
        $taskId = int_input('id');
        $status = (string)input('status', '');
        $task   = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }
        if (!array_key_exists($status, task_statuses())) { api_fail('Invalid status.'); }

        $projectId = int_input('project_id') ?: (int)$task['project_id'];
        $position  = (int)input('position', 9999);

        Db::run('UPDATE tasks SET status = ?, project_id = ?, position = ?, completed_at = ?, progress = ? WHERE id = ?', [
            $status,
            $projectId,
            $position,
            $status === 'done' ? date('Y-m-d H:i:s') : null,
            $status === 'done' ? 100 : (int)$task['progress'],
            $taskId,
        ]);

        reorder_status_column($projectId, $status, int_input('order') ? json_decode((string)input('order'), true) : null);

        if ($status !== $task['status']) {
            notify_task_stakeholders($task, 'task_status',
                $task['task_key'] . ' moved to ' . task_statuses()[$status]['label'],
                user_name() . ' moved the task from ' . task_statuses()[$task['status']]['label']
                    . ' to ' . task_statuses()[$status]['label'] . '.');
            activity('task.move', 'task', $taskId, 'Moved ' . $task['task_key'] . ' to ' . task_statuses()[$status]['label']);
        }
        if ($projectId !== (int)$task['project_id']) {
            recalc_project_progress((int)$task['project_id']);
            activity('task.move_project', 'task', $taskId, 'Moved ' . $task['task_key'] . ' to another project');
        }
        if ($task['parent_id']) { recalc_task_progress((int)$task['parent_id']); }
        recalc_project_progress($projectId);

        api_ok(['task' => task_row($taskId), 'message' => 'Moved to ' . task_statuses()[$status]['label'] . '.']);
        break;

    /* ================= DELETE ================= */
    case 'delete':
        require_permission('tasks.delete');
        $taskId = int_input('id');
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }

        foreach (Db::all('SELECT stored_name FROM attachments WHERE task_id = ?', [$taskId]) as $att) {
            $path = UPLOAD_PATH . '/' . $att['stored_name'];
            if (is_file($path)) { @unlink($path); }
        }
        Db::run('DELETE FROM tasks WHERE id = ?', [$taskId]);
        recalc_project_progress((int)$task['project_id']);
        activity('task.delete', 'task', $taskId, 'Deleted task ' . $task['task_key'] . ' — ' . $task['title']);
        api_ok(['message' => 'Task deleted.', 'id' => $taskId]);
        break;

    /* ================= TOGGLE DONE ================= */
    case 'toggle_done':
        require_permission('tasks.edit');
        $taskId = int_input('id');
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }
        $isDone = $task['status'] === 'done';
        $newStatus = $isDone ? 'todo' : 'done';

        Db::update('tasks', [
            'status'       => $newStatus,
            'progress'     => $isDone ? min(90, max(0, (int)$task['progress'])) : 100,
            'completed_at' => $isDone ? null : date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $taskId]);

        if ($task['parent_id']) { recalc_task_progress((int)$task['parent_id']); }
        recalc_project_progress((int)$task['project_id']);
        notify_task_stakeholders($task, 'task_status',
            $task['task_key'] . ($isDone ? ' reopened' : ' completed'),
            user_name() . ($isDone ? ' reopened the task.' : ' marked the task as done.'));
        activity($isDone ? 'task.reopen' : 'task.complete', 'task', $taskId,
            ($isDone ? 'Reopened ' : 'Completed ') . $task['task_key']);

        api_ok(['task' => task_row($taskId), 'message' => $isDone ? 'Task reopened.' : 'Task completed. 🎉']);
        break;

    /* ================= WATCH / UNWATCH ================= */
    case 'watch':
        $taskId = int_input('id');
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }
        $watching = Db::count('SELECT COUNT(*) FROM task_watchers WHERE task_id = ? AND user_id = ?', [$taskId, user_id()]) > 0;
        if ($watching) {
            Db::run('DELETE FROM task_watchers WHERE task_id = ? AND user_id = ?', [$taskId, user_id()]);
        } else {
            Db::insertIgnore('task_watchers', ['task_id', 'user_id'], '?, ?', [$taskId, user_id()]);
        }
        api_ok(['watching' => !$watching, 'message' => $watching ? 'You stopped following this task.' : 'You are now following this task.']);
        break;

    /* ================= TIME LOGGING ================= */
    case 'log_time':
        require_permission('tasks.time');
        $taskId = int_input('id');
        $hours  = (float)input('hours', 0);
        if ($taskId <= 0) { api_fail('Task not found.', 404); }
        if ($hours <= 0 || $hours > 24) { api_fail('Enter between 0.25 and 24 hours.'); }

        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }

        Db::insert('time_logs', [
            'task_id'  => $taskId,
            'user_id'  => user_id(),
            'hours'    => $hours,
            'note'     => mb_substr((string)input('note', ''), 0, 255) ?: null,
            'log_date' => input('log_date') ?: date('Y-m-d'),
        ]);
        Db::run('UPDATE tasks SET actual_hours = actual_hours + ? WHERE id = ?', [$hours, $taskId]);
        activity('time.log', 'task', $taskId, 'Logged ' . format_hours($hours) . ' on ' . $task['task_key']);

        api_ok([
            'message' => format_hours($hours) . ' logged.',
            'actual_hours' => (float)Db::value('SELECT actual_hours FROM tasks WHERE id = ?', [$taskId]),
        ]);
        break;

    /* ================= SEARCH (global) ================= */
    case 'search':
        require_permission('tasks.view');
        $term = (string)input('q', '');
        if (mb_strlen($term) < 2) { api_ok(['results' => []]); }
        $scope = scope_tasks_sql('t');
        $like = '%' . $term . '%';
        $rows = Db::all(
            "SELECT t.id, t.task_key, t.title, t.status, t.priority, t.progress, t.due_date,
                    p.name AS project_name, p.code AS project_code, p.color AS project_color,
                    u.name AS assignee_name, u.color AS assignee_color, u.avatar AS assignee_avatar
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN users u ON u.id = t.assignee_id
             WHERE (t.title LIKE ? OR t.task_key LIKE ? OR t.description LIKE ?) AND t.is_archived = 0 AND " . $scope['sql'] . "
             ORDER BY t.updated_at DESC LIMIT 12",
            array_merge([$like, $like, $like], $scope['params'])
        );
        api_ok(['results' => $rows]);
        break;

    /* ================= PARENT-TASK PICKER ================= */
    case 'list_for_project':
        require_permission('tasks.view');
        $projectId = int_input('project_id');
        if ($projectId <= 0) { api_ok(['tasks' => []]); }
        $rows = Db::all(
            "SELECT id, task_key, title FROM tasks
             WHERE project_id = ? AND parent_id IS NULL AND is_archived = 0 AND status <> 'done'
             ORDER BY task_key ASC LIMIT 200",
            [$projectId]
        );
        api_ok(['tasks' => $rows]);
        break;

    default:
        api_fail('Unknown action.', 404);
}

/* ==================== Local helpers ==================== */

function task_row(int $id): ?array
{
    return Db::one(
        'SELECT t.*, p.name AS project_name, p.code AS project_code, p.color AS project_color,
                a.name AS assignee_name, a.color AS assignee_color, a.avatar AS assignee_avatar,
                r.name AS reporter_name, r.color AS reporter_color
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         LEFT JOIN users a ON a.id = t.assignee_id
         LEFT JOIN users r ON r.id = t.reporter_id
         WHERE t.id = ?',
        [$id]
    );
}

function recalc_project_progress(int $projectId): void
{
    $avg = Db::value('SELECT COALESCE(AVG(progress), 0) FROM tasks WHERE project_id = ? AND parent_id IS NULL', [$projectId]);
    $cnt = Db::value('SELECT COUNT(*) FROM tasks WHERE project_id = ? AND parent_id IS NULL', [$projectId]);
    Db::run('UPDATE projects SET progress = ?, task_counter = ? WHERE id = ?', [(int)round((float)$avg), (int)$cnt, $projectId]);
}

function recalc_task_progress(int $taskId): void
{
    $hasChildren = Db::count('SELECT COUNT(*) FROM tasks WHERE parent_id = ?', [$taskId]);
    if ($hasChildren > 0) {
        $avg = Db::value('SELECT COALESCE(AVG(progress), 0) FROM tasks WHERE parent_id = ?', [$taskId]);
        Db::run('UPDATE tasks SET progress = ? WHERE id = ?', [(int)round((float)$avg), $taskId]);
    }
}

/** Persist the order of a kanban column after a drag & drop. */
function reorder_status_column(int $projectId, string $status, ?array $orderedIds): void
{
    if (!is_array($orderedIds) || !$orderedIds) {
        return;
    }
    $pos = 1;
    foreach ($orderedIds as $id) {
        Db::run('UPDATE tasks SET position = ? WHERE id = ? AND project_id = ?', [$pos++, (int)$id, $projectId]);
    }
}
