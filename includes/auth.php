<?php
/**
 * TaskFlow — Authentication
 */

/**
 * Check whether login attempts should be throttled.
 * Limits repeated failed attempts from the same IP or for the same email
 * during the last 15 minutes.
 */
function login_is_throttled(string $ip, string $email): bool
{
    $windowMinutes = 15;
    $maxAttempts = 5;

    $sql = "
        SELECT COUNT(*)
        FROM login_attempts
        WHERE success = 0
          AND attempted_at >= DATE_SUB(NOW(), INTERVAL {$windowMinutes} MINUTE)
          AND (ip_address = ? OR email = ?)
    ";

    return Db::count($sql, [$ip, $email]) >= $maxAttempts;
}

/**
 * Record a login attempt.
 */
function record_login_attempt(string $ip, string $email, bool $success): void
{
    try {
        Db::insert('login_attempts', [
            'ip_address'   => substr($ip, 0, 45),
            'email'        => $email !== '' ? substr($email, 0, 190) : null,
            'success'      => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('[TaskFlow] Failed to record login attempt: ' . $e->getMessage());
    }
}

/** Full record of the signed-in user (cached per request). */
function current_user(bool $refresh = false): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded && !$refresh) {
        return $user;
    }
    $loaded = true;
    $user = null;

    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    try {
        $user = Db::one(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug, d.name AS department_name, d.color AS department_color
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ?',
            [$id]
        );
    } catch (Throwable $e) {
        $user = null;
    }

    if (!$user || $user['status'] !== 'active') {
        $_SESSION['user_id'] = null;
        unset($_SESSION['user_id'], $_SESSION['permissions'], $_SESSION['role_slug']);
        permission_cache(null, true);
        return null;
    }

    $_SESSION['role_slug'] = (string)$user['role_slug'];
    return $user;
}

function user_id(): int
{
    return (int)(current_user()['id'] ?? 0);
}

function user_name(): string
{
    return (string)(current_user()['name'] ?? 'Guest');
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $slug = (string)(current_user()['role_slug'] ?? '');
    return $slug === 'super_admin';
}

/**
 * Attempt to sign a user in.
 * @return array{ok:bool, error?:string, user?:array}
 */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter your email and password.'];
    }

    $ip = client_ip();
    if (login_is_throttled($ip, $email)) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Please wait 15 minutes and try again.'];
    }

    $user = Db::one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        record_login_attempt($ip, $email, false);
        log_activity(null, null, 'login.failed', null, null, 'Failed sign-in attempt for ' . $email);
        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'This account is ' . $user['status'] . '. Contact your administrator.'];
    }

    // Successful authentication
    record_login_attempt($ip, $email, true);

    // ترقية التجزئة إذا تغيّرت كلفة bcrypt في الإعدادات
    $cost = defined('BCRYPT_COST') ? BCRYPT_COST : 10;
    if (password_needs_rehash((string)$user['password_hash'], PASSWORD_BCRYPT, ['cost' => $cost])) {
        try {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]),
                (int)$user['id'],
            ]);
        } catch (Throwable $e) { /* لا يمنع تسجيل الدخول */ }
    }

    session_renew();

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['logged_in_at'] = time();
    // Persisted per-user language (newer schemas); fall back to English for older databases.
    $savedLocale = (string)($user['locale'] ?? 'en');
    $_SESSION['locale'] = in_array($savedLocale, ['en', 'ar'], true) ? $savedLocale : 'en';
    // لا نفرض "light": نبدأ من الوضع الافتراضي المضبوط في إعدادات النظام
    $sessionTheme = (string)($_SESSION['theme'] ?? '');
    if ($sessionTheme !== 'dark' && $sessionTheme !== 'light') {
        $sessionTheme = (string)setting('theme', 'light') === 'dark' ? 'dark' : 'light';
    }
    $_SESSION['theme'] = $sessionTheme;
    load_user_permissions((int)$user['id']);

    Db::run('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), $user['id']]);
    csrf_token();
    log_activity((int)$user['id'], $user['name'], 'login', 'user', (int)$user['id'], 'Signed in');

    return ['ok' => true, 'user' => current_user(true)];
}

function logout_user(): void
{
    $uid = user_id();
    $name = user_name();
    if ($uid) {
        log_activity($uid, $name, 'logout', 'user', $uid, 'Signed out');
    }
    try {
        Db::run('DELETE FROM sessions WHERE id = ?', [session_id()]);
    } catch (Throwable $e) {}
    permission_cache(null, true);
    $_SESSION = [];
    session_destroy_all();
}

/** Require an authenticated user, otherwise bounce to the login page. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        if (is_ajax()) {
            json_response(['ok' => false, 'error' => 'Unauthorized', 'redirect' => url('index.php') . '?page=login'], 401);
        }
        redirect(url('index.php', ['page' => 'login']));
    }
}

/** Require a permission slug (or any of the given slugs). */
function require_permission($permissions, string $message = 'You do not have permission to perform this action.'): void
{
    if (!can($permissions)) {
        if (is_ajax()) {
            json_response(['ok' => false, 'error' => $message], 403);
        }
        http_response_code(403);
        render('403', ['message' => $message], 'Access denied');
        exit;
    }
}

/** Register a brand new account (used by setup + optional public registration). */
function register_user(array $data): array
{
    $name  = trim((string)($data['name'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $pass  = (string)($data['password'] ?? '');

    if (mb_strlen($name) < 2) {
        return ['ok' => false, 'error' => 'Please enter your full name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }
    $min = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
    if (mb_strlen($pass) < $min) {
        return ['ok' => false, 'error' => 'Password must be at least ' . $min . ' characters.'];
    }
    if (Db::count('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        return ['ok' => false, 'error' => 'That email is already registered.'];
    }

    $id = Db::insert('users', [
        'name'          => $name,
        'email'         => $email,
        'password_hash' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 10]),
        'role_id'       => (int)($data['role_id'] ?? (Db::value("SELECT id FROM roles WHERE slug = 'member'") ?: 0)),
        'department_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
        'job_title'     => $data['job_title'] ?? null,
        'color'         => random_avatar_color(),
        'status'        => 'active',
    ]);

    log_activity($id, $name, 'user.create', 'user', $id, 'Account created');
    return ['ok' => true, 'id' => $id];
}

function random_avatar_color(): string
{
    $palette = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444', '#0ea5e9', '#14b8a6', '#f97316'];
    return $palette[array_rand($palette)];
}