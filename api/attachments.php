<?php
/**
 * TaskFlow — Attachments API
 * Actions: upload, list, delete
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    case 'upload':
        require_permission('tasks.attach');
        $taskId = int_input('task_id');
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) { api_fail('Task not found.', 404); }
        if (empty($_FILES['file'])) { api_fail('No file received.'); }

        $result = handle_upload($_FILES['file'], 'attachments');
        if (!$result['ok']) { api_fail($result['error']); }

        $id = Db::insert('attachments', [
            'task_id'       => $taskId,
            'user_id'       => user_id(),
            'original_name' => mb_substr($result['original'], 0, 255),
            'stored_name'   => $result['name'],
            'mime_type'     => $result['mime'],
            'size'          => $result['size'],
        ]);

        Db::insertIgnore('task_watchers', ['task_id', 'user_id'], '?, ?', [$taskId, user_id()]);
        activity('attachment.upload', 'task', $taskId, 'Uploaded ' . $result['original'] . ' to ' . $task['task_key']);
        notify_task_stakeholders($task, 'attachment',
            'New file on ' . $task['task_key'],
            user_name() . ' attached "' . $result['original'] . '".', [$taskId]);

        api_ok([
            'id' => $id,
            'attachment' => [
                'id'            => $id,
                'original_name' => $result['original'],
                'stored_name'   => $result['name'],
                'size'          => format_bytes((int)$result['size']),
                'mime_type'     => $result['mime'],
                'url'           => upload_url($result['name']),
                'user_name'     => user_name(),
                'time_ago'      => 'just now',
                'is_image'      => (bool)preg_match('/^image\//', (string)$result['mime']),
                'can_delete'    => true,
            ],
            'message' => 'File uploaded.',
        ]);
        break;

    case 'list':
        require_permission('tasks.view');
        $taskId = int_input('task_id');
        $rows = Db::all(
            'SELECT a.*, u.name AS user_name FROM attachments a JOIN users u ON u.id = a.user_id
             WHERE a.task_id = ? ORDER BY a.created_at DESC',
            [$taskId]
        );
        foreach ($rows as &$row) {
            $row['url']      = upload_url($row['stored_name']);
            $row['size_h']   = format_bytes((int)$row['size']);
            $row['time_ago'] = time_ago($row['created_at']);
            $row['is_image'] = (bool)preg_match('/^image\//', (string)$row['mime_type']);
            $row['can_delete'] = (int)$row['user_id'] === user_id() || can('tasks.delete');
        }
        unset($row);
        api_ok(['attachments' => $rows]);
        break;

    case 'delete':
        require_permission('tasks.attach');
        $id = int_input('id');
        $att = Db::one('SELECT * FROM attachments WHERE id = ?', [$id]);
        if (!$att) { api_fail('Attachment not found.', 404); }
        if ((int)$att['user_id'] !== user_id() && !can('tasks.delete')) {
            api_fail('You can only delete files you uploaded.', 403);
        }
        $path = UPLOAD_PATH . '/' . $att['stored_name'];
        if (is_file($path)) { @unlink($path); }
        Db::run('DELETE FROM attachments WHERE id = ?', [$id]);
        activity('attachment.delete', 'task', (int)$att['task_id'], 'Deleted attachment ' . $att['original_name']);
        api_ok(['message' => 'Attachment deleted.']);
        break;

    default:
        api_fail('Unknown action.', 404);
}
