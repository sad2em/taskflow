<?php
/**
 * TaskFlow — 403 Access denied
 */
$message = $message ?? 'You do not have permission to view this page.';
?>
<div class="error-page">
    <div class="error-code">403</div>
    <?= icon('shield', 56) ?>
    <h1>Access denied</h1>
    <p><?= e($message) ?></p>
    <p class="error-hint">If you believe this is a mistake, ask your administrator to grant the required permission
        from <strong>Roles &amp; Permissions</strong>.</p>
    <div class="error-actions">
        <a class="btn btn-primary" href="<?= e(page_url('dashboard')) ?>"><?= icon('dashboard', 16) ?> Back to dashboard</a>
        <?php if (is_logged_in()): ?>
            <a class="btn btn-ghost" href="<?= e(page_url('settings', ['tab' => 'profile'])) ?>"><?= icon('user', 16) ?> My profile</a>
        <?php else: ?>
            <a class="btn btn-ghost" href="<?= e(page_url('login')) ?>"><?= icon('login', 16) ?> Sign in</a>
        <?php endif; ?>
    </div>
</div>
