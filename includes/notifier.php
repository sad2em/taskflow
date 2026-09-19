<?php
/**
 * TaskFlow — Notifications, mentions and the activity (audit) log
 */

/** Push an in-app notification to one user. */
function notify_user(int $userId, string $type, string $title, ?string $body = null, ?string $link = null,
                     ?string $entityType = null, ?int $entityId = null, ?int $actorId = null): int
{
    if ($userId <= 0 || $userId === (int)($actorId ?? user_id())) {
        // never notify the actor about their own action
        if ($userId === (int)($actorId ?? user_id())) {
            return 0;
        }
    }

    $id = Db::insert('notifications', [
        'user_id'     => $userId,
        'actor_id'    => $actorId !== null ? $actorId : (user_id() ?: null),
        'type'        => $type,
        'title'       => mb_substr($title, 0, 200),
        'body'        => $body !== null ? mb_substr($body, 0, 1000) : null,
        'link'        => $link,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'is_read'     => 0,
    ]);

    maybe_send_mail_notification($userId, $title, $body);
    return $id;
}

/** Push the same notification to many users (the actor is skipped). */
function notify_many(array $userIds, string $type, string $title, ?string $body = null, ?string $link = null,
                     ?string $entityType = null, ?int $entityId = null): int
{
    $actor = user_id();
    $count = 0;
    foreach (array_unique(array_map('intval', $userIds)) as $uid) {
        if ($uid <= 0 || $uid === $actor) {
            continue;
        }
        notify_user($uid, $type, $title, $body, $link, $entityType, $entityId, $actor);
        $count++;
    }
    return $count;
}

/**
 * Notify everybody who cares about a task: assignee, reporter, watchers,
 * project owner and project managers.
 */
function notify_task_stakeholders(array $task, string $type, string $title, ?string $body = null, array $extraUsers = []): int
{
    $taskId = (int)$task['id'];
    $ids = $extraUsers;

    foreach (['assignee_id', 'reporter_id'] as $field) {
        if (!empty($task[$field])) {
            $ids[] = (int)$task[$field];
        }
    }

    $watchers = Db::all('SELECT user_id FROM task_watchers WHERE task_id = ?', [$taskId]);
    foreach ($watchers as $w) {
        $ids[] = (int)$w['user_id'];
    }

    if (!empty($task['project_id'])) {
        $owner = Db::one('SELECT owner_id FROM projects WHERE id = ?', [(int)$task['project_id']]);
        if ($owner) {
            $ids[] = (int)$owner['owner_id'];
        }
        $managers = Db::all(
            "SELECT user_id FROM project_members WHERE project_id = ? AND project_role IN ('owner','manager')",
            [(int)$task['project_id']]
        );
        foreach ($managers as $m) {
            $ids[] = (int)$m['user_id'];
        }
    }

    return notify_many($ids, $type, $title, $body, 'index.php?page=task&id=' . $taskId, 'task', $taskId);
}

/** Notify about a comment, including @mentions found in the body. */
function notify_comment(array $task, int $commentId, string $body): int
{
    $mentioned = extract_mentions($body);
    $actor = user_id();
    $title = 'New comment on ' . ($task['task_key'] ?? 'task');
    $link  = 'index.php?page=task&id=' . (int)$task['id'];

    if ($mentioned) {
        $placeholders = implode(',', array_fill(0, count($mentioned), '?'));
        $users = Db::all("SELECT id, name FROM users WHERE id IN ($placeholders)", $mentioned);
        foreach ($users as $u) {
            notify_user((int)$u['id'], 'mention', $u['name'] . ', you were mentioned by ' . user_name(),
                excerpt($body, 160), $link, 'task', (int)$task['id'], $actor);
        }
        $sent = array_map(static fn($u) => (int)$u['id'], $users);
        notify_task_stakeholders($task, 'task_comment', $title, excerpt($body, 160), $sent);
    } else {
        notify_task_stakeholders($task, 'task_comment', $title, excerpt($body, 160));
    }

    return $commentId;
}

/** Find @mentions in a text body and resolve them to user ids. */
function extract_mentions(string $text): array
{
    if (!preg_match_all('/@([A-Za-z0-9_.\-]{2,40})/', $text, $m)) {
        return [];
    }
    $tokens = array_unique(array_map('strtolower', $m[1]));
    if (!$tokens) {
        return [];
    }

    $users = Db::all("SELECT id, name, email FROM users WHERE status = 'active'");
    $ids = [];
    foreach ($users as $u) {
        $handle = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)$u['name']) ?? '');
        $first  = strtolower(preg_replace('/[^A-Za-z0-9]/', '', explode(' ', (string)$u['name'])[0]) ?? '');
        $mail   = strtolower(explode('@', (string)$u['email'])[0]);
        foreach ($tokens as $token) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '';
            if ($clean !== '' && in_array($clean, [$handle, $first, $mail], true)) {
                $ids[] = (int)$u['id'];
                break;
            }
        }
    }
    return array_values(array_unique($ids));
}

/** Turn @mentions into highlighted spans (for display). */
function render_mentions(string $text): string
{
    return preg_replace_callback('/@([A-Za-z0-9_.\-]{2,40})/', static function ($m) {
        return '<span class="mention">@' . e($m[1]) . '</span>';
    }, e($text)) ?? e($text);
}

/** Count unread notifications for the current user. */
function unread_notifications_count(): int
{
    $uid = user_id();
    if (!$uid) {
        return 0;
    }
    try {
        return Db::count('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Write an entry to the audit log. */
function log_activity(?int $userId, ?string $userName, string $action, ?string $entityType = null,
                      ?int $entityId = null, ?string $description = null, ?array $meta = null): void
{
    try {
        Db::insert('activity_logs', [
            'user_id'     => $userId,
            'user_name'   => $userName,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'meta'        => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'  => client_ip(),
        ]);
    } catch (Throwable $e) {
        // The audit log must never break a user action.
    }
}

/** Shortcut: log the action of the signed-in user. */
function activity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null, ?array $meta = null): void
{
    log_activity(user_id() ?: null, user_name(), $action, $entityType, $entityId, $description, $meta);
}

/** Optional e-mail mirror of an in-app notification. */
function maybe_send_mail_notification(int $userId, string $subject, ?string $body): void
{
    if (!(bool)setting('mail_enabled', 0)) {
        return;
    }
    $user = Db::one('SELECT email, name, email_notifications FROM users WHERE id = ?', [$userId]);
    if (!$user || empty($user['email']) || !(int)$user['email_notifications']) {
        return;
    }
    mail_send((string)$user['email'], (string)$user['name'], $subject, (string)$body);
}

/** Very small mail() wrapper with proper UTF-8 headers. */
function mail_send(string $to, string $toName, string $subject, string $body): bool
{
    if (!(bool)setting('mail_enabled', 0) || !function_exists('mail')) {
        return false;
    }
    $from     = (string)setting('mail_from', 'no-reply@localhost');
    $fromName = (string)setting('mail_from_name', APP_NAME);
    $appUrl   = rtrim((($_SERVER['HTTP_HOST'] ?? 'localhost') ?
        ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : 'http://localhost'), '/')
        . APP_BASE_URL;

    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'From: ' . $fromName . ' <' . $from . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";

    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;padding:28px">'
          . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e6e9f2">'
          . '<div style="background:#4f46e5;color:#fff;padding:18px 24px;font-size:18px;font-weight:600">' . e($fromName) . '</div>'
          . '<div style="padding:24px;color:#1f2937;font-size:14px;line-height:1.7">'
          . '<p style="margin-top:0">Hi ' . e($toName) . ',</p>'
          . '<p style="font-size:16px;font-weight:600;color:#111827">' . e($subject) . '</p>'
          . '<p>' . nl2br(e($body)) . '</p>'
          . '<p style="margin-bottom:0"><a href="' . e($appUrl) . '/index.php" '
          . 'style="display:inline-block;background:#4f46e5;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Open ' . e($fromName) . '</a></p>'
          . '</div></div></div>';

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
}
