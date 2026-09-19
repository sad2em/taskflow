<?php
/**
 * TaskFlow — CSRF protection
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $name = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : '_token';
    return '<input type="hidden" name="' . e($name) . '" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $name = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : '_token';
    $sent = (string)($_POST[$name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $real = (string)($_SESSION['csrf_token'] ?? '');
    return $sent !== '' && $real !== '' && hash_equals($real, $sent);
}

/** Abort the request when the CSRF token is missing/invalid. */
function csrf_verify(): void
{
    if (!csrf_check()) {
        if (is_ajax()) {
            json_response(['ok' => false, 'error' => 'Invalid or expired security token. Please refresh the page.'], 419);
        }
        http_response_code(419);
        flash_error('Your session token expired. Please try again.');
        back();
    }
}

/** Verify CSRF for every state-changing request. Call once during bootstrap. */
function csrf_guard(): void
{
    if (is_post()) {
        csrf_verify();
    }
}
