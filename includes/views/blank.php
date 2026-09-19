<?php
/**
 * TaskFlow — Blank layout (login / register / setup / error pages)
 */
$__company = $GLOBALS['COMPANY'] ?? (string)setting('company_name', APP_NAME);
$__theme   = $_SESSION['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-theme="<?= e($__theme) ?>">
<head>
<script>(function(){try{var t=localStorage.getItem('taskflow_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (locale() === 'ar'): ?>
<script>window.TASKFLOW_TRANSLATIONS = <?= json_encode(locale_translations(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php endif; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(APP_BASE_URL) ?>">
<title><?= e($GLOBALS['PAGE_TITLE'] . ' · ' . $__company) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%234f46e5'/><path d='M9 17l4 4 10-11' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="blank-body<?= $__theme === 'dark' ? ' theme-dark' : '' ?>">
<div class="blank-wrap">
    <div class="blank-aside">
        <div class="blank-brand"><span class="brand-mark"><?= icon('check', 20) ?></span> <?= e($__company) ?></div>
        <h2>Every task. Every teammate.<br>One clear picture.</h2>
        <p>Plan projects, assign work, track progress in real time and keep the whole company aligned — the way the best teams operate.</p>
        <ul class="blank-points">
            <li><?= icon('check-circle', 16) ?> Kanban boards with drag &amp; drop</li>
            <li><?= icon('check-circle', 16) ?> Fine-grained roles &amp; permissions</li>
            <li><?= icon('check-circle', 16) ?> Workload, velocity and productivity reports</li>
            <li><?= icon('check-circle', 16) ?> Mentions, attachments and full audit trail</li>
        </ul>
        <div class="blank-foot">&copy; <?= date('Y') ?> <?= e($__company) ?> · TaskFlow <?= e(APP_VERSION) ?></div>
    </div>
    <div class="blank-main">
        <button class="icon-btn blank-theme" id="themeToggle" title="Toggle dark mode"><?= icon($__theme === 'dark' ? 'sun' : 'moon', 18) ?></button>
        <div class="flash-zone"><?= render_flashes() ?></div>
        <?php require $GLOBALS['VIEW_PATH']; ?>
    </div>
</div>
<div class="toast-zone" id="toastZone" aria-live="polite"></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>