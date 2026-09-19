<?php
/**
 * TaskFlow — shared application bootstrap.
 * Loaded by index.php and every api/*.php endpoint.
 */

declare(strict_types=1);

if (defined('TASKFLOW_BOOTED')) {
    return;
}
define('TASKFLOW_BOOTED', true);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PAGES_PATH', BASE_PATH . '/pages');
define('API_PATH', BASE_PATH . '/api');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('DATABASE_PATH', BASE_PATH . '/database');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('APP_VERSION', '2.1.0');

require_once INCLUDES_PATH . '/polyfill.php';

$env = taskflow_read_env();

define('APP_NAME', (string)($env['APP_NAME'] ?? 'TaskFlow'));
define('APP_DEBUG', (bool)($env['APP_DEBUG'] ?? false));
define('APP_ENV', (string)($env['APP_ENV'] ?? (APP_DEBUG ? 'development' : 'production')));
define('DB_DRIVER', (string)($env['DB_DRIVER'] ?? 'mysql'));
define('DB_HOST', (string)($env['DB_HOST'] ?? '127.0.0.1'));
define('DB_PORT', (string)($env['DB_PORT'] ?? '3306'));
define('DB_NAME', (string)($env['DB_NAME'] ?? 'taskflow'));
define('DB_USER', (string)($env['DB_USER'] ?? 'root'));
define('DB_PASS', (string)($env['DB_PASS'] ?? ''));
define('DB_CHARSET', (string)($env['DB_CHARSET'] ?? 'utf8mb4'));
define('DB_SOCKET', (string)($env['DB_SOCKET'] ?? ''));
define('SESSION_NAME', (string)($env['SESSION_NAME'] ?? 'taskflow_session'));
define('SESSION_LIFETIME', (int)($env['SESSION_LIFETIME'] ?? 43200));
define('CSRF_TOKEN_NAME', (string)($env['CSRF_TOKEN_NAME'] ?? '_token'));
define('PASSWORD_MIN_LENGTH', (int)($env['PASSWORD_MIN_LENGTH'] ?? 8));
define('BCRYPT_COST', (int)($env['BCRYPT_COST'] ?? 10));
define('MAX_UPLOAD_MB', (int)($env['MAX_UPLOAD_MB'] ?? 8));

$tz = (string)($env['TIMEZONE'] ?? 'UTC');
if (@date_default_timezone_set($tz) === false) {
    date_default_timezone_set('UTC');
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', taskflow_detect_base_url($env['BASE_URL'] ?? ''));
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/csrf.php';
require_once INCLUDES_PATH . '/session.php';
require_once INCLUDES_PATH . '/locale.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/permissions.php';
require_once INCLUDES_PATH . '/notifier.php';
require_once INCLUDES_PATH . '/view.php';

/** Load local configuration, with a legacy config fallback. */
function taskflow_read_env(): array
{
    $envFile = CONFIG_PATH . '/env.php';
    $legacy  = CONFIG_PATH . '/config.php';
    $example = CONFIG_PATH . '/env.example.php';

    if (is_file($envFile)) {
        $data = include $envFile;
        if (is_array($data) && isset($data['DB_HOST'])) {
            return $data;
        }
        if (is_array($data) && isset($data['db_host'])) {
            return taskflow_map_legacy_config($data);
        }
    }

    if (is_file($legacy)) {
        $data = include $legacy;
        if (is_array($data) && isset($data['DB_HOST'])) {
            return $data;
        }
        if (is_array($data) && isset($data['db_host'])) {
            return taskflow_map_legacy_config($data);
        }
    }

    if (is_file($example)) {
        $data = include $example;
        return is_array($data) ? $data : [];
    }

    return [];
}

function taskflow_map_legacy_config(array $config): array
{
    return [
        'DB_DRIVER' => 'mysql',
        'DB_HOST'   => (string)($config['db_host'] ?? '127.0.0.1'),
        'DB_PORT'   => (string)($config['db_port'] ?? '3306'),
        'DB_NAME'   => (string)($config['db_name'] ?? 'taskflow'),
        'DB_USER'   => (string)($config['db_user'] ?? 'root'),
        'DB_PASS'   => (string)($config['db_pass'] ?? ''),
        'DB_CHARSET'=> (string)($config['db_charset'] ?? 'utf8mb4'),
        'DB_SOCKET' => '',
        'APP_NAME'  => 'TaskFlow',
        'BASE_URL'  => '',
        'APP_DEBUG' => true,
        'APP_ENV'   => 'development',
    ];
}

function taskflow_detect_base_url(string $configured): string
{
    $configured = rtrim(str_replace('\\', '/', $configured), '/');
    if ($configured !== '') {
        return $configured;
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(dirname($script), '/');
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $dir = '';
    }
    if (substr($dir, -4) === '/api') {
        $dir = substr($dir, 0, -4);
    }
    return $dir === '/' ? '' : $dir;
}

function taskflow_is_installed(): bool
{
    if (is_file(STORAGE_PATH . '/installed.flag')) {
        return true;
    }
    try {
        Db::pdo()->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function taskflow_redirect_setup(string $message = ''): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, ($message !== '' ? $message : 'TaskFlow is not installed.') . PHP_EOL);
        exit(1);
    }
    // setup.php لم يعد موجوداً في هذا النشر: نعرض رسالة واضحة بدل تحويل إلى 404
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="font-family:system-ui,sans-serif;max-width:720px;margin:60px auto;padding:28px;'
       . 'border:1px solid #fecaca;background:#fff7f7;border-radius:14px;color:#7f1d1d">'
       . '<h2 style="margin-top:0">TaskFlow is not ready</h2>'
       . '<p>' . htmlspecialchars($message !== '' ? $message : 'The application is not installed.', ENT_QUOTES) . '</p>'
       . '<p>Check <code>config/env.php</code> and make sure the TaskFlow database is available. '
       . 'Import <code>database/setup.sql</code> into your TaskFlow database.</p></div>';
    exit;
}

function taskflow_require_database(): void
{
    try {
        Db::pdo()->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $e) {
        taskflow_redirect_setup('Database is not ready. Check config/env.php (or the default database settings) and make sure the TaskFlow database is available.');
    }
}
