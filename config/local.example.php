<?php
/**
 * Copy to config/local.php ON THE SERVER (Hostinger File Manager) and fill in.
 * config/local.php is never committed and deploys never overwrite or delete it.
 * Any value left out falls back to .htaccess SetEnv / environment variables.
 */
return [
    'DB_HOST'        => 'localhost',
    'DB_NAME'        => '',
    'DB_USER'        => '',
    'DB_PASS'        => '',
    'SESSION_SECRET' => '',   // long random string
];
