<?php
/**
 * Server setting: config/local.php (server-only, never in git or overwritten by deploys),
 * then the environment — getenv(), or $_SERVER as set by .htaccess SetEnv (some hosts
 * only expose it there, sometimes with a REDIRECT_ prefix after a rewrite).
 */
function env(string $key, string $default = ''): string {
    static $local = null;
    if ($local === null) {
        $f = __DIR__ . '/local.php';
        $local = is_file($f) ? (array)(include $f) : [];
    }
    foreach ([$local[$key] ?? null, getenv($key), $_SERVER[$key] ?? null,
              $_SERVER['REDIRECT_' . $key] ?? null, $_ENV[$key] ?? null] as $v) {
        if ($v !== null && $v !== false && $v !== '') return (string)$v;
    }
    return $default;
}

define('APP_NAME',    'A Pit Lane of Ferrari');
define('APP_TAGLINE', 'Powered by HP');
define('APP_URL',     rtrim(env('APP_URL', 'http://localhost'), '/'));
define('QR_DIR',      __DIR__ . '/../qrcodes');
define('QR_URL',      APP_URL . '/qrcodes');
define('SESSION_SECRET', env('SESSION_SECRET', 'ferrari-hp-secret-2024'));
