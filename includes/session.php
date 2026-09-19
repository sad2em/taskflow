<?php
/**
 * TaskFlow — Session handling (database-backed sessions)
 * -------------------------------------------------------------
 * Why a dedicated connection?
 * PHP closes the session at script shutdown, after static objects (including
 * the shared PDO instance) may already have been destroyed. The handler
 * therefore keeps its own PDO connection and creates it lazily.
 *
 * If the database is unavailable it transparently falls back to PHP's default
 * file handler so the app still runs (the installer relies on this).
 */

final class DbSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;
    private bool $failed = false;

    private function db(): ?PDO
    {
        if ($this->failed) {
            return null;
        }
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        try {
            $driver = strtolower((string)(defined('DB_DRIVER') ? DB_DRIVER : 'mysql'));
            if ($driver === 'sqlite') {
                $file = defined('DB_NAME') ? DB_NAME : 'taskflow.sqlite';
                if (!is_file($file)) {
                    $this->failed = true;
                    return null;
                }
                $this->pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } else {
                $host    = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
                $port    = defined('DB_PORT') ? DB_PORT : '3306';
                $name    = defined('DB_NAME') ? DB_NAME : 'taskflow';
                $user    = defined('DB_USER') ? DB_USER : 'root';
                $pass    = defined('DB_PASS') ? DB_PASS : '';
                $socket  = defined('DB_SOCKET') ? DB_SOCKET : '';
                $dsn = $socket !== ''
                    ? "mysql:unix_socket={$socket};dbname={$name};charset=utf8mb4"
                    : "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }
            return $this->pdo;
        } catch (Throwable $e) {
            $this->failed = true;
            return null;
        }
    }

    public function open($path, $name): bool
    {
        return $this->db() !== null;
    }

    public function close(): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        $pdo = $this->db();
        if (!$pdo) { return ''; }
        try {
            $stmt = $pdo->prepare('SELECT payload FROM sessions WHERE id = ?');
            $stmt->execute([substr((string)$id, 0, 128)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && $row['payload'] !== null ? (string)$row['payload'] : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data)
    {
        $pdo = $this->db();
        if (!$pdo) { return false; }
        try {
            $id   = substr((string)$id, 0, 128);
            $uidRaw = (int)($_SESSION['user_id'] ?? 0);
            $uid  = $uidRaw > 0 ? $uidRaw : null; // NULL for guests (FK-safe)
            $now  = time();
            $ip   = substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
            $ua   = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
            $data = (string)$data;

            $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                   VALUES (?,?,?,?,?,?)
                   ON CONFLICT(id) DO UPDATE SET payload = excluded.payload,
                       last_activity = excluded.last_activity, user_id = excluded.user_id'
                : 'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                   VALUES (?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE payload = VALUES(payload),
                       last_activity = VALUES(last_activity), user_id = VALUES(user_id)';

            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$id, $uid, $ip, $ua, $data, $now]);
        } catch (Throwable $e) {
            error_log('[TaskFlow] session write failed: ' . $e->getMessage());
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id)
    {
        $pdo = $this->db();
        if (!$pdo) { return true; }
        try {
            $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([substr((string)$id, 0, 128)]);
        } catch (Throwable $e) {
            // ignore
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime)
    {
        $pdo = $this->db();
        if (!$pdo) { return 0; }
        try {
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE last_activity < ?');
            $stmt->execute([time() - (int)$maxlifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/**
 * Bootstrap the session safely (usable from CLI too).
 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }

    $name   = defined('SESSION_NAME') ? SESSION_NAME : 'taskflow_session';
    $life   = defined('SESSION_LIFETIME') ? (int)SESSION_LIFETIME : 43200;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $handler = new DbSessionHandler();
    $useDbHandler = $handler->open('', $name);
    if ($useDbHandler) {
        session_set_save_handler($handler, true);
    }
    // otherwise PHP keeps its default file handler

    session_name($name);
    // Path is always "/" so the same session works for index.php, api/*.php
    // and setup.php regardless of where the app is deployed.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)$life);
    if (!$useDbHandler) {
        $tmp = sys_get_temp_dir();
        if (!is_writable((string)ini_get('session.save_path'))) {
            @ini_set('session.save_path', $tmp);
        }
    }

    @session_start();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Last-resort fallback: writable temp directory with the file handler
        @ini_set('session.save_handler', 'files');
        @ini_set('session.save_path', sys_get_temp_dir());
        @session_start();
    }

    // Idle timeout
    $last = (int)($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > $life) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Your session expired, please sign in again.'];
    }
    $_SESSION['last_activity'] = time();

    // Periodic cleanup of stale rows (roughly 1 request in 20)
    if ($useDbHandler && random_int(1, 20) === 1) {
        $handler->gc($life);
    }
}

/** Regenerate the session id (call after login / privilege change). */
function session_renew(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** Destroy the session completely. */
function session_destroy_all(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
