<?php
/**
 * TaskFlow — Public registration (only when enabled in Settings → System)
 */

if ((string)setting('allow_registration', '0') !== '1') {
    flash_error('Self-registration is disabled. Ask an administrator to create your account.');
    redirect(url('index.php', ['page' => 'login']));
}

$errors = [];
$old = ['name' => '', 'email' => '', 'job_title' => ''];

if (is_post()) {
    $old = [
        'name'      => (string)input('name', ''),
        'email'     => (string)input('email', ''),
        'job_title' => (string)input('job_title', ''),
    ];

    $password  = (string)($_POST['password'] ?? '');            // بدون trim
    $confirm   = (string)($_POST['confirm_password'] ?? '');    // بدون trim

    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $result = register_user([
            'name'      => $old['name'],
            'email'     => $old['email'],
            'password'  => $password,
            'job_title' => $old['job_title'],
            'role_id'   => (int)Db::value("SELECT id FROM roles WHERE slug = 'member'") ?: 4,
        ]);

        if ($result['ok']) {
            $login = attempt_login($old['email'], $password);
            if ($login['ok']) {
                flash_success('Welcome aboard! Your account is ready.');
                redirect(page_url('dashboard'));
            }
            flash_success('Account created. You can now sign in.');
            redirect(url('index.php', ['page' => 'login']));
        }
        $errors[] = $result['error'];
    }
}
?>
<div class="auth-card">
    <div class="auth-head">
        <span class="brand-mark big"><?= icon('user', 22) ?></span>
        <h1>Create your account</h1>
        <p>Join <?= e((string)setting('company_name', APP_NAME)) ?> and start collaborating.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= icon('alert-triangle', 18) ?><span><?= e($error) ?></span></div>
    <?php endforeach; ?>

    <form method="post" class="auth-form" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <label class="form-label" for="name">Full name</label>
            <input class="input" type="text" id="name" name="name" required maxlength="100" value="<?= e($old['name']) ?>" placeholder="Jane Cooper">
        </div>

        <div class="form-row">
            <label class="form-label" for="email">Work email</label>
            <input class="input" type="email" id="email" name="email" required value="<?= e($old['email']) ?>" placeholder="you@company.com">
        </div>

        <div class="form-row">
            <label class="form-label" for="job_title">Job title</label>
            <input class="input" type="text" id="job_title" name="job_title" maxlength="100" value="<?= e($old['job_title']) ?>" placeholder="Frontend Developer">
        </div>

        <div class="form-grid-2">
            <div class="form-row">
                <label class="form-label" for="password">Password</label>
                <input class="input" type="password" id="password" name="password" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
            </div>
            <div class="form-row">
                <label class="form-label" for="confirm_password">Confirm password</label>
                <input class="input" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg"><?= icon('check', 17) ?> Create account</button>
    </form>

    <p class="auth-alt">Already registered? <a href="<?= e(page_url('login')) ?>">Sign in</a></p>
</div>
