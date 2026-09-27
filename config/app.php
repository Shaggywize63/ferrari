<?php
define('APP_NAME',    'A Pit Lane of Ferrari');
define('APP_TAGLINE', 'Powered by HP');
define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));
define('QR_DIR',      __DIR__ . '/../qrcodes');
define('QR_URL',      APP_URL . '/qrcodes');
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: 'ferrari-hp-secret-2024');
