<?php
/**
 * TaskFlow — Task detail page
 */

$taskId = int_input('id', 0);
$scope  = scope_tasks_sql('t');

$task = Db::one(
    "SELECT t.*, p.id AS p_id, p.name AS project_name, p.code AS project_code, p.color AS project_color, p.status AS project_status,
            a.id AS a_id, a.name AS assignee_name, a.color AS assignee_color, a.avatar AS assignee_avatar, a.job_title AS assignee_title,
            r.id AS r_id, r.name AS reporter_name, r.color AS reporter_color, r.avatar AS reporter_avatar
     FROM tasks t
     JOIN projects p ON p.id = t.project_id
     LEFT JOIN users a ON a.id = t.assignee_id
     LEFT JOIN users r ON r.id = t.reporter_id
     WHERE t.id = ? AND " . $scope['sql'],
    array_merge([$taskId], $scope['params'])
);

if (!$task) {
    http_response_code(404);
    echo empty_state('search', 'Task not found',
        'This task does not exist, was deleted, or you do not have access to it.')
        . '<div class="center-actions"><a class="btn btn-primary" href="' . e(page_url('board')) . '">Back to the board</a></div>';
    return;
}

$projectId = (int)$task['project_id'];
$canEdit   = can('tasks.edit');

$subtasks = Db::all(
    "SELECT s.*, u.name AS assignee_name, u.color AS assignee_color, u.avatar AS assignee_avatar
     FROM tasks s LEFT JOIN users u ON u.id = s.assignee_id
     WHERE s.parent_id = ? AND s.is_archived = 0 ORDER BY s.position ASC, s.id ASC",
    [$taskId]
);

$comments = Db::all(
    'SELECT c.*, u.name AS user_name, u.color AS user_color, u.avatar AS user_avatar, u.job_title
     FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.task_id = ? ORDER BY c.created_at ASC',
    [$taskId]
);

$attachments = Db::all(
    'SELECT f.*, u.name AS user_name FROM attachments f JOIN users u ON u.id = f.user_id
     WHERE f.task_id = ? ORDER BY f.created_at DESC',
    [$taskId]
);

$timeLogs = Db::all(
    'SELECT tl.*, u.name AS user_name, u.color AS user_color, u.avatar AS user_avatar
     FROM time_logs tl JOIN users u ON u.id = tl.user_id
     WHERE tl.task_id = ? ORDER BY tl.log_date DESC, tl.id DESC LIMIT 30',
    [$taskId]
);

$logs = Db::all(
    "SELECT * FROM activity_logs WHERE entity_type = 'task' AND entity_id = ? ORDER BY created_at DESC LIMIT 15",
    [$taskId]
);

$watchers = Db::all(
    'SELECT u.id, u.name, u.color, u.avatar, u.job_title FROM task_watchers tw
     JOIN users u ON u.id = tw.user_id WHERE tw.task_id = ? ORDER BY u.name ASC',
    [$taskId]
);
$isWatching = in_array(user_id(), array_map(static fn($w) => (int)$w['id'], $watchers), true);

$projectMembers = Db::all(
    "SELECT u.id, u.name, u.color, u.avatar, u.job_title, pm.project_role
     FROM project_members pm JOIN users u ON u.id = pm.user_id
     WHERE pm.project_id = ? AND u.status = 'active' ORDER BY u.name ASC",
    [$projectId]
);
$allUsers = Db::all("SELECT id, name, color, avatar, job_title FROM users WHERE status = 'active' ORDER BY name ASC");
$assignOptions = can('tasks.assign') ? $allUsers : $projectMembers;

$days = days_until($task['due_date']);
$isLate = $days !== null && $days < 0 && $task['status'] !== 'done';
$isSoon = $days !== null && $days >= 0 && $days <= 3 && $task['status'] !== 'done';
$subDone = count(array_filter($subtasks, static fn($s) => $s['status'] === 'done'));

$GLOBALS['PAGE_TITLE'] = $task['task_key'] . ' — ' . $task['title'];
?>
<?= page_header(
    $task['task_key'],
    '<span class="crumb-project" style="--key-color:' . e($task['project_color']) . '">' . icon('projects', 14) . ' ' . e($task['project_name']) . '</span>',
    '<div class="head-btns">'
        . '<button class="btn btn-ghost btn-sm' . ($isWatching ? ' active' : '') . '" data-watch-task="' . (int)$task['id'] . '">'
        . icon($isWatching ? 'check-circle' : 'bell', 15) . ($isWatching ? ' Following' : ' Follow') . '</button>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(page_url('board', ['project' => $projectId])) . '">' . icon('board', 15) . ' Board</a>'
        . ($canEdit ? '<button class="btn btn-primary btn-sm" data-edit-task=\'' . json_encode([
              'id' => (int)$task['id'], 'title' => $task['title'], 'description' => (string)$task['description'],
              'project_id' => $projectId, 'assignee_id' => (int)$task['assignee_id'], 'priority' => $task['priority'],
              'status' => $task['status'], 'due_date' => (string)$task['due_date'], 'start_date' => (string)$task['start_date'],
              'estimated_hours' => (string)$task['estimated_hours'], 'progress' => (int)$task['progress'],
              'task_key' => $task['task_key'], 'parent_id' => (int)$task['parent_id'],
          ], JSON_HEX_APOS | JSON_HEX_QUOT) . '\'>' . icon('edit', 15) . ' Edit</button>' : '')
        . '</div>',
    breadcrumbs([
        ['label' => 'Workspace', 'url' => page_url('dashboard')],
        ['label' => 'Task Board', 'url' => page_url('board')],
        ['label' => $task['task_key']],
    ])
) ?>

<div class="task-layout" data-task-id="<?= (int)$task['id'] ?>">

    <!-- ================= MAIN COLUMN ================= -->
    <div class="task-main">
        <h2 class="task-title-lg <?= $task['status'] === 'done' ? 'is-done' : '' ?>"><?= e($task['title']) ?></h2>

        <div class="task-badges">
            <?= status_badge($task['status']) ?>
            <?= priority_badge($task['priority']) ?>
            <?php if ($task['parent_id']): ?>
                <?php $parent = Db::one('SELECT id, task_key, title FROM tasks WHERE id = ?', [(int)$task['parent_id']]); ?>
                <?php if ($parent): ?>
                    <a class="chip chip-muted" href="<?= e(page_url('task', ['id' => (int)$parent['id']])) ?>">
                        <?= icon('link', 12) ?> Sub-task of <?= e($parent['task_key']) ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($isLate): ?><span class="chip chip-danger"><?= icon('alert-triangle', 12) ?> <?= abs((int)$days) ?> days overdue</span><?php endif; ?>
            <?php if ($isSoon): ?><span class="chip chip-warning"><?= icon('clock', 12) ?> Due in <?= (int)$days ?> day<?= $days === 1 ? '' : 's' ?></span><?php endif; ?>
        </div>

        <!-- Description -->
        <section class="card">
            <div class="card-head"><h3><?= icon('file', 16) ?> Description</h3></div>
            <div class="card-body">
                <?php if (!empty($task['description'])): ?>
                    <div class="rich-text"><?= render_mentions(nl2br(e((string)$task['description']))) ?></div>
                <?php else: ?>
                    <p class="muted">No description yet. <?= $canEdit ? 'Click <strong>Edit</strong> to add context, links or acceptance criteria.' : '' ?></p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Sub-tasks -->
        <section class="card">
            <div class="card-head">
                <div>
                    <h3><?= icon('tasks', 16) ?> Sub-tasks</h3>
                    <?php if ($subtasks): ?><p class="card-subtitle"><?= $subDone ?> of <?= count($subtasks) ?> completed</p><?php endif; ?>
                </div>
                <?php if (can('tasks.create')): ?>
                    <button class="btn btn-ghost btn-sm" data-add-subtask="<?= (int)$task['id'] ?>" data-project="<?= $projectId ?>">
                        <?= icon('plus', 15) ?> Add sub-task
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body no-pad">
                <?php if (!$subtasks): ?>
                    <p class="inline-empty">No sub-tasks. Break this work into smaller steps to track progress precisely.</p>
                <?php else: ?>
                    <?php if (count($subtasks) > 0): ?>
                        <div class="subtask-progress"><?= progress_bar((int)round(($subDone / count($subtasks)) * 100)) ?></div>
                    <?php endif; ?>
                    <ul class="subtask-list">
                        <?php foreach ($subtasks as $s): ?>
                            <li class="<?= $s['status'] === 'done' ? 'done' : '' ?>">
                                <button class="mini-check<?= $s['status'] === 'done' ? ' checked' : '' ?>"
                                        data-toggle-done="<?= (int)$s['id'] ?>"
                                        title="Toggle complete"><?= $s['status'] === 'done' ? icon('check', 11) : '' ?></button>
                                <a class="subtask-title" href="<?= e(page_url('task', ['id' => (int)$s['id']])) ?>"><?= e($s['title']) ?></a>
                                <span class="subtask-key"><?= e($s['task_key']) ?></span>
                                <?= avatar_html($s['assignee_id'] ? $s : null, 22) ?>
                                <?php if ($s['due_date']): ?>
                                    <span class="due-chip<?= days_until($s['due_date']) < 0 && $s['status'] !== 'done' ? ' late' : '' ?>">
                                        <?= icon('calendar', 12) ?> <?= e(format_date($s['due_date'], 'M j')) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($canEdit || can('tasks.delete')): ?>
                                    <button class="icon-btn danger sm" data-delete-task="<?= (int)$s['id'] ?>" data-title="<?= e($s['task_key']) ?>" title="Delete sub-task"><?= icon('trash', 14) ?></button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <!-- Comments -->
        <section class="card" id="commentsSection">
            <div class="card-head">
                <h3><?= icon('message', 16) ?> Comments <span class="count-pill" id="commentCount"><?= count($comments) ?></span></h3>
            </div>
            <div class="card-body">
                <?php if (can('tasks.comment')): ?>
                    <form class="comment-form" id="commentForm" data-task-id="<?= (int)$task['id'] ?>">
                        <?= csrf_field() ?>
                        <?= avatar_html(current_user(), 34) ?>
                        <div class="comment-input-wrap">
                            <textarea class="input" name="body" rows="2" required maxlength="5000"
                                      placeholder="Write a comment… use @name to mention a teammate"></textarea>
                            <div class="comment-actions">
                                <span class="hint"><?= icon('info', 12) ?> Ctrl + Enter to post</span>
                                <button type="submit" class="btn btn-primary btn-sm"><?= icon('send', 14) ?> Comment</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <ul class="comment-list" id="commentList">
                    <?php foreach ($comments as $c): ?>
                        <?php $canManage = (int)$c['user_id'] === user_id() || can('tasks.delete'); ?>
                        <li class="comment" data-id="<?= (int)$c['id'] ?>">
                            <?= avatar_html($c, 34) ?>
                            <div class="comment-body">
                                <div class="comment-head">
                                    <strong><?= e($c['user_name']) ?></strong>
                                    <?php if ($c['job_title']): ?><span class="comment-role"><?= e($c['job_title']) ?></span><?php endif; ?>
                                    <time title="<?= e(format_date($c['created_at'], 'M j, Y H:i')) ?>"><?= e(time_ago($c['created_at'])) ?></time>
                                    <?php if ($canManage): ?>
                                        <span class="comment-tools">
                                            <?php if ((int)$c['user_id'] === user_id()): ?>
                                                <button class="link-btn sm" data-edit-comment="<?= (int)$c['id'] ?>">Edit</button>
                                            <?php endif; ?>
                                            <button class="link-btn sm danger" data-delete-comment="<?= (int)$c['id'] ?>">Delete</button>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-text"><?= render_mentions(nl2br(e((string)$c['body']))) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$comments): ?>
                        <li class="inline-empty" id="noComments">No comments yet — start the discussion.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>
    </div>

    <!-- ================= SIDEBAR ================= -->
    <aside class="task-side">
        <div class="card">
            <div class="card-head"><h3>Details</h3></div>
            <div class="card-body side-fields">
                <div class="side-field">
                    <span class="side-label"><?= icon('circle', 14) ?> Status</span>
                    <select class="input input-sm" data-field="status" data-task-update="<?= (int)$task['id'] ?>" <?= $canEdit ? '' : 'disabled' ?>>
                        <?= select_options(task_statuses(), $task['status'], '', 'label') ?>
                    </select>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('flag', 14) ?> Priority</span>
                    <select class="input input-sm" data-field="priority" data-task-update="<?= (int)$task['id'] ?>" <?= $canEdit ? '' : 'disabled' ?>>
                        <?= select_options(priorities(), $task['priority'], '', 'label') ?>
                    </select>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('user', 14) ?> Assignee</span>
                    <select class="input input-sm" data-field="assignee_id" data-task-update="<?= (int)$task['id'] ?>" <?= can('tasks.assign') ? '' : 'disabled' ?>>
                        <option value="">Unassigned</option>
                        <?= select_options($assignOptions, (int)$task['assignee_id'], 'id', 'name') ?>
                    </select>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('projects', 14) ?> Project</span>
                    <a class="side-value link" href="<?= e(page_url('project', ['id' => $projectId])) ?>">
                        <i class="dot" style="background:<?= e($task['project_color']) ?>"></i><?= e($task['project_name']) ?>
                    </a>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('calendar', 14) ?> Start date</span>
                    <input class="input input-sm" type="date" value="<?= e($task['start_date'] ?: '') ?>"
                           data-field="start_date" data-task-update="<?= (int)$task['id'] ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('calendar', 14) ?> Due date</span>
                    <input class="input input-sm" type="date" value="<?= e($task['due_date'] ?: '') ?>"
                           data-field="due_date" data-task-update="<?= (int)$task['id'] ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('clock', 14) ?> Estimate</span>
                    <input class="input input-sm" type="number" min="0" step="0.5" value="<?= e($task['estimated_hours'] ?: '') ?>"
                           placeholder="—" data-field="estimated_hours" data-task-update="<?= (int)$task['id'] ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('trend-up', 14) ?> Progress</span>
                    <div class="side-progress">
                        <input type="range" min="0" max="100" step="5" value="<?= (int)$task['progress'] ?>"
                               data-field="progress" data-task-update="<?= (int)$task['id'] ?>" id="progressRange" <?= $canEdit ? '' : 'disabled' ?>>
                        <output id="progressValue"><?= (int)$task['progress'] ?>%</output>
                    </div>
                    <?= progress_bar((int)$task['progress']) ?>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('user', 14) ?> Reporter</span>
                    <span class="side-value person"><?= avatar_html($task['r_id'] ? $task : null, 22) ?> <?= e($task['reporter_name'] ?: '—') ?></span>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('clock', 14) ?> Logged</span>
                    <span class="side-value">
                        <?= e(format_hours((float)$task['actual_hours'])) ?>
                        <?php if ($task['estimated_hours']): ?>
                            <small class="muted">of <?= e(format_hours((float)$task['estimated_hours'])) ?></small>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="side-field">
                    <span class="side-label"><?= icon('calendar', 14) ?> Created</span>
                    <span class="side-value"><?= e(format_date($task['created_at'], 'M j, Y H:i')) ?></span>
                </div>

                <?php if ($task['completed_at']): ?>
                    <div class="side-field">
                        <span class="side-label"><?= icon('check-circle', 14) ?> Completed</span>
                        <span class="side-value"><?= e(time_ago($task['completed_at'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log time -->
        <?php if (can('tasks.time')): ?>
            <div class="card">
                <div class="card-head"><h3><?= icon('clock', 16) ?> Log work</h3></div>
                <div class="card-body">
                    <form class="stack-form" id="timeForm" data-task-id="<?= (int)$task['id'] ?>">
                        <?= csrf_field() ?>
                        <div class="row-2">
                            <input class="input input-sm" type="number" name="hours" step="0.25" min="0.25" max="24" placeholder="Hours" required>
                            <input class="input input-sm" type="date" name="log_date" value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <input class="input input-sm" type="text" name="note" maxlength="255" placeholder="What did you work on?">
                        <button class="btn btn-primary btn-sm btn-block" type="submit"><?= icon('plus', 14) ?> Add time</button>
                    </form>

                    <?php if ($timeLogs): ?>
                        <ul class="time-list">
                            <?php foreach ($timeLogs as $log): ?>
                                <li>
                                    <?= avatar_html($log, 22) ?>
                                    <div>
                                        <strong><?= e(format_hours((float)$log['hours'])) ?></strong>
                                        <span><?= e($log['user_name']) ?> · <?= e(format_date($log['log_date'], 'M j')) ?></span>
                                        <?php if ($log['note']): ?><em><?= e($log['note']) ?></em><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attachments -->
        <div class="card">
            <div class="card-head">
                <h3><?= icon('paperclip', 16) ?> Files <span class="count-pill" id="fileCount"><?= count($attachments) ?></span></h3>
            </div>
            <div class="card-body">
                <?php if (can('tasks.attach')): ?>
                    <form class="dropzone" id="uploadForm" data-task-id="<?= (int)$task['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="file" name="file" id="fileInput" hidden>
                        <label for="fileInput" class="dropzone-inner">
                            <?= icon('upload', 20) ?>
                            <span>Drop a file or <strong>browse</strong></span>
                            <small>Max <?= e((string)setting('max_upload_mb', 8)) ?> MB · <?= e((string)setting('allowed_extensions', '')) ?></small>
                        </label>
                    </form>
                <?php endif; ?>

                <ul class="file-list" id="fileList">
                    <?php foreach ($attachments as $f): ?>
                        <?php $isImage = (bool)preg_match('/^image\//', (string)$f['mime_type']); ?>
                        <li data-id="<?= (int)$f['id'] ?>">
                            <span class="file-icon"><?= icon($isImage ? 'image' : 'file', 16) ?></span>
                            <div class="file-meta">
                                <a href="<?= e(upload_url($f['stored_name'])) ?>" target="_blank" rel="noopener"><?= e($f['original_name']) ?></a>
                                <small><?= e(format_bytes((int)$f['size'])) ?> · <?= e($f['user_name']) ?> · <?= e(time_ago($f['created_at'])) ?></small>
                            </div>
                            <?php if ((int)$f['user_id'] === user_id() || can('tasks.delete')): ?>
                                <button class="icon-btn danger sm" data-delete-file="<?= (int)$f['id'] ?>" title="Delete file"><?= icon('trash', 14) ?></button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$attachments): ?><li class="inline-empty" id="noFiles">No files attached.</li><?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- People -->
        <div class="card">
            <div class="card-head"><h3><?= icon('users', 16) ?> People</h3></div>
            <div class="card-body">
                <div class="people-block">
                    <span class="side-label">Following (<?= count($watchers) ?>)</span>
                    <div class="people-row">
                        <?php foreach ($watchers as $w): ?>
                            <a href="<?= e(page_url('profile', ['id' => (int)$w['id']])) ?>" title="<?= e($w['name']) ?>"><?= avatar_html($w, 26) ?></a>
                        <?php endforeach; ?>
                        <?php if (!$watchers): ?><span class="muted">Nobody yet</span><?php endif; ?>
                    </div>
                </div>
                <div class="people-block">
                    <span class="side-label">Project team (<?= count($projectMembers) ?>)</span>
                    <div class="people-row">
                        <?php foreach ($projectMembers as $m): ?>
                            <a href="<?= e(page_url('profile', ['id' => (int)$m['id']])) ?>" title="<?= e($m['name'] . ' — ' . $m['project_role']) ?>"><?= avatar_html($m, 26) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task activity -->
        <div class="card">
            <div class="card-head"><h3><?= icon('activity', 16) ?> History</h3></div>
            <div class="card-body no-pad">
                <ul class="activity-feed compact">
                    <?php foreach ($logs as $log): ?>
                        <li>
                            <span class="activity-dot"></span>
                            <div class="activity-body">
                                <p><?= e((string)$log['description'] ?: $log['action']) ?></p>
                                <time><?= e($log['user_name'] ?: 'System') ?> · <?= e(time_ago($log['created_at'])) ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?><li class="muted inline-empty">No history recorded.</li><?php endif; ?>
                </ul>
            </div>
        </div>

        <?php if (can('tasks.delete')): ?>
            <div class="card danger-zone">
                <div class="card-body">
                    <button class="btn btn-danger btn-block btn-sm" data-delete-task="<?= (int)$task['id'] ?>" data-title="<?= e($task['task_key']) ?>">
                        <?= icon('trash', 15) ?> Delete this task
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>
