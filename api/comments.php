<?php
/**
 * TaskFlow — Comments API
 * Actions: list, create, update, delete
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    case 'list':
        require_permission('tasks.view');
        $taskId = int_input('task_id');
        if ($taskId <= 0) { api_fail('Missing task.', 404); }
        $rows = Db::all(
            'SELECT c.*, u.name AS user_name, u.color AS user_color, u.avatar AS user_avatar, u.job_title
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.task_id = ? ORDER BY c.created_at ASC',
            [$taskId]
        );
        $out = [];
        foreach ($rows as $row) {
            $row['body_html']  = render_mentions((string)$row['body']);
            $row['time_ago']   = time_ago($row['created_at']);
            $row['avatar']     = avatar_html($row, 32);
            $out[] = $row;
        }
        api_ok(['comments' => $out]);
        break;

    case 'create':
        require_permission('tasks.comment');
        $taskId = int_input('task_id');
        $body   = trim((string)input('body', ''));
        $task   = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }
        if (mb_strlen($body) < 1) { api_fail('Write something first.'); }
        if (mb_strlen($body) > 5000) { api_fail('Comments are limited to 5000 characters.'); }

        $commentId = Db::insert('comments', [
            'task_id'   => $taskId,
            'user_id'   => user_id(),
            'body'      => $body,
            'parent_id' => int_input('parent_id') > 0 ? int_input('parent_id') : null,
        ]);

        // Commenting makes you a watcher automatically
        Db::insertIgnore('task_watchers', ['task_id', 'user_id'], '?, ?', [$taskId, user_id()]);

        notify_comment($task, $commentId, $body);
        activity('comment.create', 'task', $taskId, 'Commented on ' . $task['task_key']);

        $me = current_user();
        api_ok([
            'id' => $commentId,
            'comment' => [
                'id'         => $commentId,
                'body'       => $body,
                'body_html'  => render_mentions($body),
                'user_name'  => $me['name'] ?? '',
                'user_id'    => user_id(),
                'created_at' => date('Y-m-d H:i:s'),
                'time_ago'   => 'just now',
                'avatar'     => avatar_html($me, 32),
                'can_edit'   => true,
            ],
            'message' => 'Comment posted.',
        ]);
        break;

    case 'update':
        $commentId = int_input('id');
        $comment = Db::one('SELECT * FROM comments WHERE id = ?', [$commentId]);
        if (!$comment) { api_fail('Comment not found.', 404); }
        if ((int)$comment['user_id'] !== user_id() && !can('tasks.delete')) {
            api_fail('You can only edit your own comments.', 403);
        }
        $body = trim((string)input('body', ''));
        if ($body === '') { api_fail('The comment cannot be empty.'); }
        Db::update('comments', ['body' => $body], 'id = :id', ['id' => $commentId]);
        api_ok(['id' => $commentId, 'body_html' => render_mentions($body), 'message' => 'Comment updated.']);
        break;

    case 'delete':
        $commentId = int_input('id');
        $comment = Db::one('SELECT * FROM comments WHERE id = ?', [$commentId]);
        if (!$comment) { api_fail('Comment not found.', 404); }
        if ((int)$comment['user_id'] !== user_id() && !can('tasks.delete')) {
            api_fail('You can only delete your own comments.', 403);
        }
        Db::run('DELETE FROM comments WHERE id = ?', [$commentId]);
        activity('comment.delete', 'task', (int)$comment['task_id'], 'Deleted a comment');
        api_ok(['id' => $commentId, 'message' => 'Comment deleted.']);
        break;

    default:
        api_fail('Unknown action.', 404);
}
