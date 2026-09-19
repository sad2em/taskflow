<?php
/**
 * TaskFlow — Settings (my profile · company · system · data)
 */

$tabs = ['profile' => ['My profile', 'user']];
if (can('settings.company')) { $tabs['company'] = ['Company', 'building']; }
if (can('settings.system'))  { $tabs['system']  = ['System', 'settings']; }
if (can('settings.system'))  { $tabs['data']    = ['Data & backup', 'database']; }

$tab = (string)query('tab', 'profile');
if (!isset($tabs[$tab])) { $tab = 'profile'; }

// Each tab has its own permission — a member may only open "My profile".
$tabPermissions = [
    'profile' => 'settings.profile',
    'company' => 'settings.company',
    'system'  => 'settings.system',
    'data'    => 'settings.system',
];
require_permission($tabPermissions[$tab], 'You are not allowed to open these settings.');

$me          = current_user();
$departments = Db::all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');
$managers    = Db::all("SELECT id, name, job_title FROM users WHERE status = 'active' AND id <> ? ORDER BY name ASC", [user_id()]);

$stats = Db::one(
    'SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM projects) AS projects,
            (SELECT COUNT(*) FROM tasks) AS tasks,
            (SELECT COUNT(*) FROM comments) AS comments,
            (SELECT COUNT(*) FROM attachments) AS files,
            (SELECT COUNT(*) FROM activity_logs) AS logs,
            (SELECT COUNT(*) FROM notifications) AS notifications'
) ?? [];

$diskUsage = 0;
$diskFiles = 0;
if (is_dir(UPLOAD_PATH)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_PATH, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isFile()) { $diskUsage += $file->getSize(); $diskFiles++; }
    }
}
?>
<?= page_header(
    'Settings',
    'Manage your account, your company profile and the behaviour of the system',
    '',
    breadcrumbs([['label' => 'Administration', 'url' => page_url('dashboard')], ['label' => 'Settings']])
) ?>

<nav class="tabs">
    <?php foreach ($tabs as $key => $meta): ?>
        <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="<?= e(page_url('settings', ['tab' => $key])) ?>">
            <?= icon($meta[1], 15) ?> <?= e($meta[0]) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'profile'): ?>
    <div class="settings-grid">
        <form class="card" method="post" action="<?= e(url('api/settings.php')) ?>" data-ajax-form>
            <div class="card-head"><h3><?= icon('user', 16) ?> Personal information</h3></div>
            <div class="card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">

                <div class="avatar-editor">
                    <?= avatar_html($me, 76, 'profile-avatar') ?>
                    <div>
                        <p class="muted">Your avatar is shown on tasks, comments and notifications.</p>
                        <button type="button" class="btn btn-ghost btn-sm" data-modal-open="avatarModal"><?= icon('upload', 15) ?> Change picture</button>
                    </div>
                </div>

                <div class="form-grid-2">
                    <?= form_row('Language', '<select class="input" name="locale"><option value="en"' . (locale() === 'en' ? ' selected' : '') . '>English</option><option value="ar"' . (locale() === 'ar' ? ' selected' : '') . '>العربية</option></select>') ?>
                    <?= form_row('Full name', '<input class="input" name="name" value="' . e($me['name']) . '" required maxlength="100">', '', '', true) ?>
                    <?= form_row('Email address', '<input class="input" type="email" name="email" value="' . e($me['email']) . '" required>', 'Used to sign in and to receive e-mail notifications.', '', true) ?>
                </div>
                <div class="form-grid-3">
                    <?= form_row('Job title', '<input class="input" name="job_title" value="' . e((string)$me['job_title']) . '" maxlength="100">') ?>
                    <?= form_row('Phone', '<input class="input" name="phone" value="' . e((string)$me['phone']) . '" maxlength="40">') ?>
                    <?= form_row('Avatar colour', '<input class="input input-color" type="color" name="color" value="' . e($me['color'] ?: '#6366f1') . '">', 'Used for your initials avatar.') ?>
                </div>

                <div class="form-row">
                    <input type="hidden" name="email_notifications_present" value="1">
                    <label class="checkbox">
                        <input type="checkbox" name="email_notifications" value="1" <?= (int)$me['email_notifications'] === 1 ? 'checked' : '' ?>>
                        <span>Send me an e-mail when I am assigned a task or mentioned in a comment</span>
                    </label>
                </div>
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('save', 15) ?> Save profile</button></div>
        </form>

        <div class="settings-side">
            <form class="card" method="post" action="<?= e(url('api/settings.php')) ?>" data-ajax-form>
                <div class="card-head"><h3><?= icon('key', 16) ?> Change password</h3></div>
                <div class="card-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <?= form_row('Current password', '<input class="input" type="password" name="current_password" required autocomplete="current-password">', '', '', true) ?>
                    <?= form_row('New password', '<input class="input" type="password" name="new_password" required minlength="' . (int)PASSWORD_MIN_LENGTH . '" autocomplete="new-password">', 'At least ' . (int)PASSWORD_MIN_LENGTH . ' characters.', '', true) ?>
                    <?= form_row('Confirm new password', '<input class="input" type="password" name="confirm_password" required autocomplete="new-password">', '', '', true) ?>
                    <div class="alert alert-info"><?= icon('info', 16) ?><span>Changing your password signs you out of every other device.</span></div>
                </div>
                <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('lock', 15) ?> Update password</button></div>
            </form>

            <div class="card">
                <div class="card-head"><h3><?= icon('shield', 16) ?> Account</h3></div>
                <div class="card-body side-fields">
                    <div class="side-field"><span class="side-label">Role</span><span class="side-value"><?= e((string)$me['role_name']) ?></span></div>
                    <div class="side-field"><span class="side-label">Permissions</span><span class="side-value"><?= count(permissions()) ?> granted</span></div>
                    <div class="side-field"><span class="side-label">Data scope</span><span class="side-value"><?= e(data_scope()) ?></span></div>
                    <div class="side-field"><span class="side-label">Department</span><span class="side-value"><?= e((string)($me['department_name'] ?: '—')) ?></span></div>
                    <div class="side-field"><span class="side-label">Member since</span><span class="side-value"><?= e(format_date($me['created_at'], 'M j, Y')) ?></span></div>
                    <div class="side-field"><span class="side-label">Last sign-in</span><span class="side-value"><?= e($me['last_login_at'] ? time_ago($me['last_login_at']) : '—') ?></span></div>
                    <div class="side-field"><span class="side-label">Session IP</span><span class="side-value"><code><?= e(client_ip()) ?></code></span></div>
                </div>
                <div class="card-foot">
                    <a class="btn btn-ghost btn-sm" href="<?= e(page_url('activity', ['user' => user_id()])) ?>"><?= icon('activity', 15) ?> My activity</a>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'company'): ?>
    <form class="card" method="post" action="<?= e(url('api/settings.php')) ?>" enctype="multipart/form-data" data-ajax-form data-reload="1">
        <div class="card-head"><h3><?= icon('building', 16) ?> Company profile</h3>
            <p class="card-subtitle">Shown in the sidebar, on the login screen and in e-mails.</p></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="company">

            <div class="form-grid-2">
                <?= form_row('Company name', '<input class="input" name="company_name" value="' . e((string)setting('company_name')) . '" required maxlength="120">', '', '', true) ?>
                <?= form_row('Contact e-mail', '<input class="input" type="email" name="company_email" value="' . e((string)setting('company_email')) . '">') ?>
            </div>
            <div class="form-grid-2">
                <?= form_row('Phone', '<input class="input" name="company_phone" value="' . e((string)setting('company_phone')) . '" maxlength="60">') ?>
                <?= form_row('Address', '<input class="input" name="company_address" value="' . e((string)setting('company_address')) . '" maxlength="255">') ?>
            </div>

            <div class="form-row">
                <label class="form-label">Logo</label>
                <div class="logo-picker">
                    <?php $logo = (string)setting('company_logo', ''); ?>
                    <?php if ($logo && is_file(UPLOAD_PATH . '/' . $logo)): ?>
                        <img src="<?= e(upload_url($logo)) ?>" alt="Company logo" class="logo-preview">
                    <?php else: ?>
                        <span class="logo-preview placeholder"><?= icon('image', 22) ?></span>
                    <?php endif; ?>
                    <div>
                        <input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                        <small class="form-hint">PNG, JPG, SVG or WebP. Recommended 200×60 px, transparent background.</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('save', 15) ?> Save company settings</button></div>
    </form>

<?php elseif ($tab === 'system'): ?>
    <form class="card" method="post" action="<?= e(url('api/settings.php')) ?>" data-ajax-form data-reload="1">
        <div class="card-head"><h3><?= icon('settings', 16) ?> System preferences</h3></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="system">

            <div class="form-grid-3">
                <?= form_row('Default theme', '<select class="input" name="theme">'
                    . '<option value="light"' . (setting('theme') === 'light' ? ' selected' : '') . '>Light</option>'
                    . '<option value="dark"' . (setting('theme') === 'dark' ? ' selected' : '') . '>Dark</option></select>',
                    'Applied to new visitors. Each user can override it with the moon/sun button.') ?>
                <?= form_row('Items per page', '<input class="input" type="number" name="items_per_page" min="5" max="100" value="' . e((string)setting('items_per_page', 12)) . '">', 'Used on the tasks and projects lists.') ?>
                <?= form_row('“Due soon” window (days)', '<input class="input" type="number" name="due_soon_days" min="1" max="30" value="' . e((string)setting('due_soon_days', 3)) . '">', 'Tasks inside this window are highlighted as urgent.') ?>
            </div>

            <div class="form-grid-2">
                <?= form_row('Max upload size (MB)', '<input class="input" type="number" name="max_upload_mb" min="1" max="64" value="' . e((string)setting('max_upload_mb', 8)) . '">',
                    'Your server also limits uploads via <code>upload_max_filesize</code> in php.ini.') ?>
                <?= form_row('Allowed file extensions', '<input class="input" name="allowed_extensions" value="' . e((string)setting('allowed_extensions')) . '">', 'Comma separated, without dots.') ?>
            </div>

            <hr class="sep">
            <h4 class="section-title"><?= icon('mail', 15) ?> E-mail notifications</h4>

            <div class="form-row">
                <input type="hidden" name="mail_enabled_present" value="1">
                <label class="checkbox">
                    <input type="checkbox" name="mail_enabled" value="1" <?= (string)setting('mail_enabled') === '1' ? 'checked' : '' ?>>
                    <span>Enable e-mail notifications (uses PHP <code>mail()</code>)</span>
                </label>
            </div>
            <div class="form-grid-2">
                <?= form_row('From address', '<input class="input" type="email" name="mail_from" value="' . e((string)setting('mail_from')) . '">') ?>
                <?= form_row('From name', '<input class="input" name="mail_from_name" value="' . e((string)setting('mail_from_name')) . '" maxlength="60">') ?>
            </div>
            <?php if (!function_exists('mail')): ?>
                <div class="alert alert-warning"><?= icon('alert-triangle', 16) ?><span>The PHP <code>mail()</code> function is not available on this server. Configure SMTP in php.ini or install an SMTP extension.</span></div>
            <?php endif; ?>

            <hr class="sep">
            <h4 class="section-title"><?= icon('user', 15) ?> Registration</h4>
            <div class="form-row">
                <input type="hidden" name="allow_registration_present" value="1">
                <label class="checkbox">
                    <input type="checkbox" name="allow_registration" value="1" <?= (string)setting('allow_registration') === '1' ? 'checked' : '' ?>>
                    <span>Allow people to create their own account from the login page</span>
                </label>
                <small class="form-hint">New self-registered accounts receive the <strong>Member</strong> role. Keep this off for internal company use.</small>
            </div>
        </div>
        <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('save', 15) ?> Save system settings</button></div>
    </form>

<?php else: /* data */ ?>
    <div class="settings-grid">
        <div class="card">
            <div class="card-head"><h3><?= icon('database', 16) ?> Database contents</h3></div>
            <div class="card-body">
                <ul class="stat-list">
                    <?php
                    $items = [
                        ['users', 'Team members', 'team'],
                        ['projects', 'Projects', 'projects'],
                        ['tasks', 'Tasks (incl. sub-tasks)', 'tasks'],
                        ['comments', 'Comments', 'message'],
                        ['files', 'Attachments', 'paperclip'],
                        ['logs', 'Activity log entries', 'activity'],
                        ['notifications', 'Notifications', 'bell'],
                    ];
                    foreach ($items as [$key, $label, $iconName]): ?>
                        <li><?= icon($iconName, 16) ?><span><?= e($label) ?></span><strong><?= (int)($stats[$key] ?? 0) ?></strong></li>
                    <?php endforeach; ?>
                    <li><?= icon('upload', 16) ?><span>Uploaded files on disk</span><strong><?= $diskFiles ?> · <?= e(format_bytes($diskUsage)) ?></strong></li>
                    <li><?= icon('database', 16) ?><span>Database driver</span><strong><?= e(Db::driver()) ?></strong></li>
                </ul>
            </div>
        </div>

        <div class="settings-side">
            <div class="card">
                <div class="card-head"><h3><?= icon('download', 16) ?> Export</h3></div>
                <div class="card-body">
                    <p class="muted">Download your data as CSV. Exports respect the current permission scope.</p>
                    <div class="export-links">
                        <a class="btn btn-ghost btn-sm" href="<?= e(page_url('tasks', ['export' => 'csv'])) ?>"><?= icon('tasks', 15) ?> All tasks</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('index.php', ['page' => 'reports', 'tab' => 'team', 'export' => 'csv'])) ?>"><?= icon('users', 15) ?> Team performance</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('index.php', ['page' => 'reports', 'tab' => 'projects', 'export' => 'csv'])) ?>"><?= icon('projects', 15) ?> Projects</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('index.php', ['page' => 'activity', 'export' => 'csv'])) ?>"><?= icon('activity', 15) ?> Activity log</a>
                    </div>
                    <div class="alert alert-info"><?= icon('info', 16) ?>
                        <span>For a full database backup run <code>mysqldump -u USER -p DBNAME &gt; backup.sql</code> on your server.</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3><?= icon('refresh', 16) ?> Maintenance</h3></div>
                <div class="card-body">
                    <p class="muted">Old sessions are cleaned automatically on roughly one request in twenty,
                        so there is nothing to run by hand here.</p>
                    <p class="muted">Driver: <strong><?= e(Db::driver()) ?></strong> ·
                        PHP <strong><?= e(PHP_VERSION) ?></strong> ·
                        mbstring <strong><?= taskflow_has_mbstring() ? 'available' : 'missing (polyfill active)' ?></strong></p>
                </div>
            </div>

            <?php if (is_admin()): ?>
                <div class="card danger-zone">
                    <div class="card-head"><h3><?= icon('alert-triangle', 16) ?> Sensitive</h3></div>
                    <div class="card-body">
                        <p class="muted">Debug mode is currently <strong><?= APP_DEBUG ? 'ON' : 'OFF' ?></strong>.
                            Turn it off in <code>config/env.php</code> before going live so error details are never shown to visitors.</p>
                        <p class="muted">The installer <code>setup.php</code> is no longer present in this
                            deployment — the schema is imported from <code>database/setup.sql</code>.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script type="text/template" id="tpl-avatarModal">
<form class="modal-form" id="avatarForm" data-action="api/settings.php" method="post" enctype="multipart/form-data" data-reload="1">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="avatar">
    <div class="form-row">
        <label class="form-label" for="av-file">Choose an image</label>
        <input class="input" type="file" id="av-file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" required>
        <small class="form-hint">Square images look best. Max <?= e((string)setting('max_upload_mb', 8)) ?> MB.</small>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('upload', 16) ?> <span data-submit-label>Upload</span></button>
    </div>
</form>
</script>