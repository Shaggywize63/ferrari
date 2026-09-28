<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Never leave a blank white page: log the error, and on admin pages show what went wrong.
set_exception_handler(static function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isAdmin = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') && !empty($_SESSION['admin_id']);
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        return;
    }
    $detail = $isAdmin
        ? '<pre style="white-space:pre-wrap;background:#F4F7FB;padding:12px;border-radius:8px;font-size:13px">'
          . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'
        : '';
    // Database set-up problems get a plain-language hint for everyone (never any values)
    if ($e instanceof PDOException && ($hint = dbErrorHint($e)) !== null) {
        $set = [];
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
            $set[] = $k . (env($k) !== '' ? ' ✓' : ' ✗ missing');
        }
        $detail = '<p style="background:#FFF4E5;border-left:4px solid #D40000;padding:10px 12px;border-radius:6px">'
                . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p style="font-size:13px;color:#5B6B7F">Server settings: ' . implode(' · ', $set) . '</p>'
                . $detail;
    }
    echo '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:48px auto;padding:24px;'
       . 'border:1px solid #E3E9F2;border-radius:12px;color:#0D1B3E">'
       . '<h2 style="margin-top:0;color:#D40000">Something went wrong</h2>'
       . '<p>The page could not be loaded. Please refresh; if it keeps happening, share the message below with support.</p>'
       . $detail . '</div>';
});
