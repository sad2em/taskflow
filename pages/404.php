<?php
/**
 * TaskFlow — 404 Not found
 */
?>
<div class="error-page">
    <div class="error-code">404</div>
    <?= icon('search', 56) ?>
    <h1>Page not found</h1>
    <p>The page you are looking for was moved, deleted, or never existed.</p>
    <div class="error-actions">
        <a class="btn btn-primary" href="<?= e(page_url('dashboard')) ?>"><?= icon('dashboard', 16) ?> Back to dashboard</a>
        <a class="btn btn-ghost" href="<?= e(page_url('board')) ?>"><?= icon('board', 16) ?> Task board</a>
    </div>
</div>
