<?php
/**
 * TaskFlow — Notifications API
 * Actions: list, read, read_all, delete, unread_count
 */

require_once __DIR__ . '/bootstrap.php';

$uid = user_id();
$action = api_action();

switch ($action) {

    case 'list':
        $limit = min(50, max(1, int_input('limit', 10)));
        $rows = Db::all(
            'SELECT n.*, a.name AS actor_name, a.color AS actor_color, a.avatar AS actor_avatar
             FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
             WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT ' . $limit,
            [$uid]
        );
        $out = [];
        foreach ($rows as $r) {
            $r['time_ago'] = time_ago($r['created_at']);
            $r['avatar']   = avatar_html($r['actor_id'] ? $r : null, 30);
            $out[] = $r;
        }
        api_ok(['notifications' => $out, 'unread' => unread_notifications_count()]);
        break;

    case 'unread_count':
        api_ok(['unread' => unread_notifications_count()]);
        break;

    case 'read':
        $id = int_input('id');
        Db::run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?', [$id, $uid]);
        api_ok(['unread' => unread_notifications_count()]);
        break;

    case 'read_all':
        Db::run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0', [$uid]);
        activity('notifications.read_all', null, null, 'Marked all notifications as read');
        api_ok(['unread' => 0, 'message' => 'All notifications marked as read.']);
        break;

    case 'delete':
        $id = int_input('id');
        Db::run('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$id, $uid]);
        api_ok(['unread' => unread_notifications_count(), 'message' => 'Notification removed.']);
        break;

    case 'clear':
        Db::run('DELETE FROM notifications WHERE user_id = ? AND is_read = 1', [$uid]);
        api_ok(['message' => 'Read notifications cleared.', 'unread' => unread_notifications_count()]);
        break;

    default:
        api_fail('Unknown action.', 404);
}
