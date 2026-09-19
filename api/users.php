<?php
/**
 * TaskFlow — Users API
 * Actions: create, update, delete, toggle_status, reset_password, options
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    /* ================= LIST (lightweight, for pickers) ================= */
    case 'options':
        require_permission('users.view');
        $rows = Db::all(
            "SELECT id, name, email, color, avatar, job_title, department_id,
                    (SELECT name FROM departments d WHERE d.id = users.department_id) AS department_name
             FROM users WHERE status = 'active' ORDER BY name ASC"
        );
        api_ok(['users' => $rows]);
        break;

    /* ================= CREATE ================= */
    case 'create':
        require_permission('users.create');
        $name     = (string)input('name', '');
        $email    = strtolower((string)input('email', ''));
        $password = (string)($_POST['password'] ?? ''); // بدون trim: يطابق مسار تسجيل الدخول

        if (mb_strlen($name) < 2)  { api_fail('Please enter the member name.'); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { api_fail('Please enter a valid email address.'); }
        if (Db::count('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) { api_fail('That email is already in use.'); }

        $min = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
        if (mb_strlen($password) < $min) { api_fail('Password must be at least ' . $min . ' characters.'); }

        $roleId = int_input('role_id');
        if (!$roleId || !Db::count('SELECT COUNT(*) FROM roles WHERE id = ?', [$roleId])) {
            $roleId = (int)Db::value("SELECT id FROM roles WHERE slug = 'member'") ?: (int)Db::value("SELECT id FROM roles WHERE slug = 'supervisor'");
        }

        $userId = Db::insert('users', [
            'name'          => mb_substr($name, 0, 100),
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 10]),
            'role_id'       => $roleId,
            'department_id' => int_input('department_id') > 0 ? int_input('department_id') : null,
            'manager_id'    => int_input('manager_id') > 0 ? int_input('manager_id') : null,
            'job_title'     => (string)input('job_title', '') ?: null,
            'phone'         => (string)input('phone', '') ?: null,
            'color'         => random_avatar_color(),
            'status'        => in_array((string)input('status'), ['active', 'inactive', 'suspended'], true) ? (string)input('status') : 'active',
            'email_notifications' => bool_input('email_notifications') ? 1 : 0,
        ]);

        activity('user.create', 'user', $userId, 'Added team member ' . $name);
        notify_user($userId, 'system', 'Welcome to ' . (string)setting('company_name', APP_NAME),
            user_name() . ' created your account. Sign in to see your assigned tasks.', 'index.php?page=dashboard');

        api_ok(['id' => $userId, 'message' => $name . ' was added to the team.']);
        break;

    /* ================= UPDATE ================= */
    case 'update':
        require_permission('users.edit');
        $userId = int_input('id');
        $target = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$target) { api_fail('User not found.', 404); }

        $data = [];
        foreach (['name', 'job_title', 'phone'] as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = mb_substr(trim((string)$_POST[$field]), 0, 100) ?: null;
            }
        }
        if (isset($_POST['email'])) {
            $email = strtolower(trim((string)$_POST['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { api_fail('Invalid email address.'); }
            if (Db::count('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $userId]) > 0) {
                api_fail('That email is already in use.');
            }
            $data['email'] = $email;
        }
        if (isset($_POST['role_id']) && int_input('role_id') > 0) {
            if ($userId === user_id() && Db::count("SELECT COUNT(*) FROM roles WHERE id = ? AND slug = 'super_admin'", [(int)$target['role_id']])) { api_fail('You cannot demote your own Super Admin role.'); }
            $data['role_id'] = int_input('role_id');
        }
        if (isset($_POST['department_id'])) { $data['department_id'] = int_input('department_id') > 0 ? int_input('department_id') : null; }
        if (isset($_POST['manager_id']))    { $data['manager_id']    = int_input('manager_id') > 0 ? int_input('manager_id') : null; }
        if (isset($_POST['color']) && preg_match('/^#[0-9a-f]{6}$/i', (string)$_POST['color'])) { $data['color'] = $_POST['color']; }
        if (isset($_POST['status'])) {
            $status = (string)$_POST['status'];
            if (!in_array($status, ['active', 'inactive', 'suspended'], true)) { api_fail('Invalid status.'); }
            if ($userId === user_id() && $status !== 'active') { api_fail('You cannot deactivate your own account.'); }
            $data['status'] = $status;
        }
        if (isset($_POST['email_notifications'])) { $data['email_notifications'] = bool_input('email_notifications') ? 1 : 0; }

        $password = (string)($_POST['password'] ?? ''); // بدون trim: يطابق مسار تسجيل الدخول
        if ($password !== '') {
            $min = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
            if (mb_strlen($password) < $min) { api_fail('Password must be at least ' . $min . ' characters.'); }
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 10]);
        }

        if (!$data) { api_fail('Nothing to update.'); }
        Db::update('users', $data, 'id = :id', ['id' => $userId]);

        // Refresh the session cache when the user edits themselves
        if ($userId === user_id() && (isset($data['role_id']) || isset($data['name']) || isset($data['email']))) {
            load_user_permissions($userId);
            current_user(true);
        }

        activity('user.update', 'user', $userId, 'Updated member ' . ($data['name'] ?? $target['name']));
        api_ok(['message' => 'Member updated.', 'id' => $userId]);
        break;

    /* ================= STATUS TOGGLE ================= */
    case 'toggle_status':
        require_permission('users.edit');
        $userId = int_input('id');
        if ($userId === user_id()) { api_fail('You cannot change your own status.'); }
        $target = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$target) { api_fail('User not found.', 404); }
        $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
        Db::update('users', ['status' => $newStatus], 'id = :id', ['id' => $userId]);
        activity('user.status', 'user', $userId, 'Set ' . $target['name'] . ' to ' . $newStatus);
        api_ok(['status' => $newStatus, 'message' => $target['name'] . ' is now ' . $newStatus . '.']);
        break;

    /* ================= PASSWORD RESET (admin) ================= */
    case 'reset_password':
        require_permission('users.edit');
        $userId = int_input('id');
        $target = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$target) { api_fail('User not found.', 404); }
        $newPassword = bin2hex(random_bytes(5)) . strtoupper(bin2hex(random_bytes(1)));
        Db::update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 10]),
            'reset_token'   => null,
            'reset_expires' => null,
        ], 'id = :id', ['id' => $userId]);
        activity('user.reset_password', 'user', $userId, 'Reset the password of ' . $target['name']);
        api_ok(['message' => 'Password reset. Share it securely with ' . $target['name'] . '.', 'password' => $newPassword]);
        break;

    /* ================= DELETE ================= */
    case 'delete':
        require_permission('users.delete');
        $userId = int_input('id');
        if ($userId === user_id()) { api_fail('You cannot delete your own account.'); }
        $target = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$target) { api_fail('User not found.', 404); }
        if (Db::count("SELECT COUNT(*) FROM roles WHERE id = ? AND slug = 'super_admin'", [(int)$target['role_id']]) && Db::count("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super_admin'") <= 1) {
            api_fail('You must keep at least one Super Admin.');
        }

        // Re-assign their work instead of breaking foreign keys
        Db::run('UPDATE tasks SET assignee_id = NULL WHERE assignee_id = ?', [$userId]);
        Db::run('UPDATE departments SET manager_id = NULL WHERE manager_id = ?', [$userId]);
        Db::run('UPDATE users SET manager_id = NULL WHERE manager_id = ?', [$userId]);
        Db::run('DELETE FROM project_members WHERE user_id = ?', [$userId]);
        Db::run('DELETE FROM task_watchers WHERE user_id = ?', [$userId]);
        Db::run('DELETE FROM sessions WHERE user_id = ?', [$userId]);
        Db::run('DELETE FROM users WHERE id = ?', [$userId]);

        activity('user.delete', 'user', $userId, 'Deleted member ' . $target['name']);
        api_ok(['message' => $target['name'] . ' was removed. Their open tasks are now unassigned.']);
        break;

    default:
        api_fail('Unknown action.', 404);
}
