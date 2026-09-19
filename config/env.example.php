<?php
/**
 * TaskFlow — Environment configuration
 * -------------------------------------------------------------
 * Copy this file to "env.php" (or edit it directly) and set your
 * own database credentials. env.php is ignored by version control.
 */

return [
    /* ---------- Database ---------- */
    'DB_DRIVER' => 'mysql',        // mysql | mariadb | sqlite
    'DB_HOST'   => '127.0.0.1',
    'DB_PORT'   => '3306',
    'DB_NAME'   => 'taskflow',
    'DB_USER'   => 'root',
    'DB_PASS'   => '',
    'DB_CHARSET'=> 'utf8mb4',
    'DB_SOCKET' => '',             // leave empty on Windows/XAMPP

    /* ---------- Application ---------- */
    'APP_NAME'  => 'TaskFlow',
    'BASE_URL'  => '',             // '' = auto-detect (works on XAMPP sub-folders)
    'APP_DEBUG' => false,           // set to false in production
    'APP_ENV'   => 'production',  // development | production

    /* ---------- Security ---------- */
    'SESSION_NAME'       => 'taskflow_session',
    'SESSION_LIFETIME'   => 43200, // seconds of inactivity before logout (12h)
    'CSRF_TOKEN_NAME'    => '_token',
    'PASSWORD_MIN_LENGTH'=> 8,
    'BCRYPT_COST'        => 10,

    /* ---------- Uploads ---------- */
    'UPLOAD_DIR'     => __DIR__ . '/../uploads',
    'MAX_UPLOAD_MB'  => 8,

    /* ---------- Mail (optional) ---------- */
    'MAIL_ENABLED'   => false,
    'MAIL_FROM'      => 'no-reply@taskflow.test',
    'MAIL_FROM_NAME' => 'TaskFlow',
    'MAIL_TRANSPORT' => 'mail',    // mail (PHP mail())

    /* ---------- Timezone ---------- */
    'TIMEZONE' => 'Asia/Beirut',
];
