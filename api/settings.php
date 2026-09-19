<?php
/**
 * TaskFlow — Settings API
 * Actions: profile, password, avatar, company, system, theme
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    /* ================= MY PROFILE ================= */
    case 'profile':
        require_permission('settings.profile');
        $uid  = user_id();
        $data = [];

        foreach (['name', 'job_title', 'phone'] as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = mb_substr(trim((string)$_POST[$field]), 0, 100) ?: null;
            }
        }
        if (isset($_POST['email'])) {
            $email = strtolower(trim((string)$_POST['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { api_fail('Please enter a valid email address.'); }
            if (Db::count('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $uid]) > 0) {
                api_fail('That email is already used by another account.');
            }
            $data['email'] = $email;
        }
        if (isset($_POST['locale'])) {
            $newLocale = set_locale((string)$_POST['locale']);
            // Persist language for the account when the upgraded schema is installed.
            try {
                Db::run('UPDATE users SET locale = ? WHERE id = ?', [$newLocale, $uid]);
            } catch (Throwable $e) { /* Backward compatible with pre-locale databases. */ }
        }
        if (isset($_POST['color']) && preg_match('/^#[0-9a-f]{6}$/i', (string)$_POST['color'])) {
            $data['color'] = $_POST['color'];
        }
        // خانة الاختيار غير المؤشّرة لا تُرسل أصلاً، لذلك نعتمد على حقل مخفي
        // اسمه email_notifications_present حتى يمكن إطفاؤها فعلاً.
        if (isset($_POST['email_notifications']) || isset($_POST['email_notifications_present'])) {
            $data['email_notifications'] = bool_input('email_notifications') ? 1 : 0;
        }
        if (!$data) { api_fail('Nothing to update.'); }

        Db::update('users', $data, 'id = :id', ['id' => $uid]);
        current_user(true);
        activity('profile.update', 'user', $uid, 'Updated own profile');
        api_ok(['message' => 'Your profile was saved.', 'user' => current_user(true)]);
        break;

    /* ================= PASSWORD ================= */
    case 'password':
        require_permission('settings.profile');
        $uid      = user_id();
        // مهم: لا نستخدم input() هنا لأنها تقصّ المسافات — كلمة المرور تُقرأ كما هي حرفياً
        $current  = (string)($_POST['current_password'] ?? '');
        $new      = (string)($_POST['new_password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');
        $min      = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;

        $user = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$user || !password_verify($current, (string)$user['password_hash'])) {
            api_fail('Your current password is incorrect.');
        }
        if (mb_strlen($new) < $min) { api_fail('The new password must be at least ' . $min . ' characters.'); }
        if ($new !== $confirm) { api_fail('The two new passwords do not match.'); }
        if ($new === $current) { api_fail('Choose a password you have not used before.'); }

        Db::update('users', [
            'password_hash' => password_hash($new, PASSWORD_BCRYPT, ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 10]),
        ], 'id = :id', ['id' => $uid]);

        // تدوير معرّف الجلسة الحالية (حماية من session fixation) قبل حذف الباقي
        $oldSessionId = session_id();
        session_renew();
        try {
            Db::run('DELETE FROM sessions WHERE id = ?', [$oldSessionId]);
        } catch (Throwable $e) { /* لا يوقف العملية */ }

        // Sign the user out everywhere else
        Db::run('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$uid, session_id()]);
        activity('profile.password', 'user', $uid, 'Changed own password');
        api_ok(['message' => 'Password changed successfully.']);
        break;

    /* ================= AVATAR ================= */
    case 'avatar':
        require_permission('settings.profile');
        if (empty($_FILES['avatar'])) { api_fail('No image received.'); }
        $result = handle_upload($_FILES['avatar'], 'avatars');
        if (!$result['ok']) { api_fail($result['error']); }

        $uid  = user_id();
        $old  = (string)(current_user()['avatar'] ?? '');
        Db::update('users', ['avatar' => $result['name']], 'id = :id', ['id' => $uid]);
        if ($old !== '' && is_file(UPLOAD_PATH . '/' . $old)) { @unlink(UPLOAD_PATH . '/' . $old); }

        activity('profile.avatar', 'user', $uid, 'Updated profile picture');
        api_ok(['message' => 'Profile picture updated.', 'url' => upload_url($result['name'])]);
        break;

    /* ================= COMPANY SETTINGS ================= */
    case 'company':
        require_permission('settings.company');
        foreach (['company_name', 'company_email', 'company_phone', 'company_address'] as $key) {
            if (isset($_POST[$key])) {
                save_setting($key, mb_substr(trim((string)$_POST[$key]), 0, 255));
            }
        }
        if (!empty($_FILES['logo']['name'])) {
            $result = handle_upload($_FILES['logo'], 'branding');
            if ($result['ok']) {
                $previous = (string)setting('company_logo', '');
                save_setting('company_logo', $result['name']);
                if ($previous !== '' && is_file(UPLOAD_PATH . '/' . $previous)) { @unlink(UPLOAD_PATH . '/' . $previous); }
            }
        }
        activity('settings.company', null, null, 'Updated company settings');
        api_ok(['message' => 'Company settings saved.', 'company_name' => (string)setting('company_name')]);
        break;

    /* ================= SYSTEM SETTINGS ================= */
    case 'system':
        require_permission('settings.system');
        $map = [
            'theme'              => ['light', 'dark'],
            'items_per_page'     => null,
            'max_upload_mb'      => null,
            'allowed_extensions' => null,
            'mail_enabled'       => null,
            'mail_from'          => null,
            'mail_from_name'     => null,
            'due_soon_days'      => null,
            'allow_registration' => null,
        ];
        $checkboxes = ['mail_enabled', 'allow_registration'];
        foreach ($map as $key => $allowed) {
            $isCheckbox = in_array($key, $checkboxes, true);
            // خانات الاختيار: نحفظها كلما أُرسل النموذج (الحقل المخفي *_present)،
            // وإلا استحال إطفاؤها لأن المتصفح لا يرسل خانة غير مؤشّرة.
            if ($isCheckbox) {
                if (!isset($_POST[$key]) && !isset($_POST[$key . '_present'])) { continue; }
                save_setting($key, bool_input($key) ? '1' : '0');
                continue;
            }
            if (!isset($_POST[$key])) { continue; }
            $value = trim((string)$_POST[$key]);
            if (is_array($allowed) && !in_array($value, $allowed, true)) { continue; }
            // لكل إعداد مداه الخاص بدل مدى موحّد 1..100
            $ranges = [
                'items_per_page' => [5, 100],
                'max_upload_mb'  => [1, 64],
                'due_soon_days'  => [1, 30],
            ];
            if (isset($ranges[$key])) {
                [$lo, $hi] = $ranges[$key];
                $value = (string)max($lo, min($hi, (int)$value));
            }
            save_setting($key, $value);
        }
        activity('settings.system', null, null, 'Updated system settings');
        api_ok(['message' => 'System settings saved.']);
        break;

    /* ================= THEME (no permission needed) ================= */
    case 'theme':
        $theme = input('theme') === 'dark' ? 'dark' : 'light';
        $_SESSION['theme'] = $theme;
        api_ok(['theme' => $theme]);
        break;

    default:
        api_fail('Unknown action.', 404);
}
