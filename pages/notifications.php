<?php
/**
 * TaskFlow — Notifications centre
 */

$uid  = user_id();
$only = query('filter') === 'unread' ? 'unread' : 'all';

$where  = $only === 'unread' ? 'n.user_id = ? AND n.is_read = 0' : 'n.user_id = ?';
$params = [$uid];

$notifications = Db::all(
    "SELECT n.*, a.name AS actor_name, a.color AS actor_color, a.avatar AS actor_avatar
     FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
     WHERE {$where}
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT 200",
    $params
);

$unread = unread_notifications_count();

$grouped = [];
foreach ($notifications as $n) {
    $day = date('Y-m-d', strtotime((string)$n['created_at']));
    $grouped[$day][] = $n;
}

$typeIcons = [
    'task_assigned'  => 'user',
    'task_status'    => 'refresh',
    'task_comment'   => 'message',
    'mention'        => 'zap',
    'attachment'     => 'paperclip',
    'task_due'       => 'clock',
    'project_member' => 'projects',
    'project_status' => 'projects',
    'system'         => 'info',
];
?>
<?= page_header(
    'Notifications',
    $unread > 0 ? $unread . ' unread notification' . ($unread === 1 ? '' : 's') : 'You are all caught up',
    '<div class="head-btns">'
        . '<div class="view-switch">'
        . '<a class="view-btn' . ($only === 'all' ? ' active' : '') . '" href="' . e(page_url('notifications')) . '">All</a>'
        . '<a class="view-btn' . ($only === 'unread' ? ' active' : '') . '" href="' . e(page_url('notifications', ['filter' => 'unread'])) . '">Unread ' . ($unread ? '<span class="count-pill">' . $unread . '</span>' : '') . '</a>'
        . '</div>'
        . ($unread > 0 ? '<button class="btn btn-ghost btn-sm" id="markAllReadPage">' . icon('check', 15) . ' Mark all read</button>' : '')
        . '<button class="btn btn-ghost btn-sm" id="clearReadPage">' . icon('trash', 15) . ' Clear read</button>'
        . '</div>',
    breadcrumbs([['label' => 'Workspace', 'url' => page_url('dashboard')], ['label' => 'Notifications']])
) ?>

<?php if (!$notifications): ?>
    <div class="card"><div class="card-body">
        <?= empty_state('bell', $only === 'unread' ? 'No unread notifications' : 'No notifications yet',
            'You will be notified when a task is assigned to you, when somebody comments or mentions you, and when project status changes.') ?>
    </div></div>
<?php else: ?>
    <div class="notif-groups">
        <?php foreach ($grouped as $day => $items): ?>
            <section class="card">
                <div class="card-head">
                    <h3><?= $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : format_date($day, 'l, F j, Y')) ?></h3>
                    <span class="count-pill"><?= count($items) ?></span>
                </div>
                <div class="card-body no-pad">
                    <ul class="notif-list">
                        <?php foreach ($items as $n): ?>
                            <li class="notif-item<?= (int)$n['is_read'] === 0 ? ' unread' : '' ?>" data-id="<?= (int)$n['id'] ?>">
                                <span class="notif-icon" data-type="<?= e($n['type']) ?>">
                                    <?= icon($typeIcons[$n['type']] ?? 'bell', 16) ?>
                                </span>
                                <?php if ($n['actor_id']): ?><?= avatar_html($n, 30) ?><?php else: ?><span class="notif-avatar"><?= icon('zap', 15) ?></span><?php endif; ?>
                                <div class="notif-body">
                                    <p><strong><?= e($n['title']) ?></strong></p>
                                    <?php if ($n['body']): ?><span><?= e($n['body']) ?></span><?php endif; ?>
                                    <time><?= e(time_ago($n['created_at'])) ?></time>
                                </div>
                                <div class="notif-actions">
                                    <?php if ($n['link']): ?>
                                        <a class="btn btn-ghost btn-sm" data-notif-open="<?= (int)$n['id'] ?>" href="<?= e(url($n['link'])) ?>">
                                            Open <?= icon('chevron-right', 13) ?>
                                        </a>
                                    <?php elseif ((int)$n['is_read'] === 0): ?>
                                        <button class="btn btn-ghost btn-sm" data-notif-read="<?= (int)$n['id'] ?>">Mark read</button>
                                    <?php endif; ?>
                                    <button class="icon-btn danger sm" data-notif-delete="<?= (int)$n['id'] ?>" title="Remove"><?= icon('trash', 14) ?></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
