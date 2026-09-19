<?php
/**
 * TaskFlow — Sign in page
 */

$errors = [];
$old = ['email' => '', 'remember' => false];

if (is_post()) {
    $old['email']    = (string)input('email', '');
    $old['remember'] = bool_input('remember');

    // كلمة المرور تُقرأ كما هي (بدون trim) لتطابق طريقة حفظها في api/settings.php
    $result = attempt_login((string)input('email', ''), (string)($_POST['password'] ?? ''));

    if ($result['ok']) {
        // "Keep me signed in": نمدّد عمر كوكي الجلسة بدل أن ينتهي بإغلاق المتصفح
        if ($old['remember'] && session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires'  => time() + (defined('SESSION_LIFETIME') ? (int)SESSION_LIFETIME : 43200),
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $intended = $_SESSION['intended_url'] ?? '';
        unset($_SESSION['intended_url']);
        if ($intended !== '' && strpos($intended, 'page=login') === false) {
            redirect($intended);
        }
        redirect(page_url('dashboard'));
    }

    $errors[] = $result['error'] ?? 'Sign in failed.';
}

$allowRegistration = (string)setting('allow_registration', '0') === '1';
$demoAdmin = Db::one("SELECT u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super_admin' ORDER BY u.id ASC LIMIT 1");
?>
<div class="auth-card">
    <div class="auth-head">
        <span class="brand-mark big"><?= icon('check', 22) ?></span>
        <h1>Welcome back</h1>
        <p>Sign in to your workspace to continue.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= icon('alert-triangle', 18) ?><span><?= e($error) ?></span></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('index.php', ['page' => 'login'])) ?>" class="auth-form" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <label class="form-label" for="email">Email address</label>
            <div class="input-icon">
                <?= icon('mail', 17) ?>
                <input class="input" type="email" id="email" name="email" required autocomplete="username"
                       value="<?= e($old['email']) ?>" placeholder="you@company.com" autofocus>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="password">Password</label>
            <div class="input-icon">
                <?= icon('lock', 17) ?>
                <input class="input" type="password" id="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••">
                <button type="button" class="input-suffix" data-toggle-password="#password" aria-label="Show password"><?= icon('eye', 16) ?></button>
            </div>
        </div>

        <div class="auth-options">
            <label class="checkbox">
                <input type="checkbox" name="remember" value="1" <?= $old['remember'] ? 'checked' : '' ?>>
                <span>Keep me signed in</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <?= icon('login', 17) ?> Sign in
        </button>
    </form>

    <?php if ($allowRegistration): ?>
        <p class="auth-alt">Need an account? <a href="<?= e(page_url('register')) ?>">Create one</a></p>
    <?php endif; ?>

    <?php if (APP_DEBUG): ?>
        <div class="auth-demo">
            <strong>Demo accounts</strong>
            <span class="demo-row"><code><?= e($demoAdmin['email'] ?? 'admin@taskflow.test') ?></code> / <code>Admin@123</code> — Super Admin</span>
            <span class="demo-row"><code>sara@taskflow.test</code> / <code>Passw0rd!</code> — Manager</span>
            <span class="demo-row"><code>lina@taskflow.test</code> / <code>Passw0rd!</code> — Member</span>
            <button type="button" class="link-btn" id="fillDemoAdmin">Fill admin credentials</button>
        </div>
        <script>
            document.getElementById('fillDemoAdmin')?.addEventListener('click', function () {
                document.getElementById('email').value = '<?= e($demoAdmin['email'] ?? 'admin@taskflow.test') ?>';
                document.getElementById('password').value = 'Admin@123';
                document.getElementById('password').focus();
            });
        </script>
    <?php endif; ?>

    <?php if (!$demoAdmin): ?>
        <div class="alert alert-info">
            <?= icon('info', 18) ?>
            <span>No accounts yet — import <code>database/setup.sql</code> then <code>database/setup.sql</code> into your database.</span>
        </div>
    <?php endif; ?>
</div>
