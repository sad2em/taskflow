<?php
/**
 * TaskFlow — Shared bootstrap for every API endpoint (api/*.php)
 */

require_once dirname(__DIR__) . '/includes/app.php';

taskflow_require_database();
session_boot();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'You must be signed in.', 'redirect' => url('index.php') . '?page=login'], 401);
}

if (is_post()) {
    csrf_verify();
}

function api_ok(array $payload = []): void
{
    json_response(array_merge(['ok' => true], $payload));
}

function api_fail(string $message, int $status = 400, array $extra = []): void
{
    json_response(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function api_action(): string
{
    $action = (string)input('action', 'list');
    return preg_replace('/[^a-z0-9_\-]/i', '', $action) ?: 'list';
}
