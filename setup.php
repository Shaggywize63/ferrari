<?php
/**
 * One-time setup script.
 * Run once from the browser or CLI: php setup.php
 * DELETE this file after setup is complete.
 */

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'ferrari_competition';

try {
    $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');

    // Split by statement (naively but works for our schema)
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt) {
            try { $pdo->exec($stmt . ';'); } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (!str_contains($e->getMessage(), 'already exists')) throw $e;
            }
        }
    }

    // Ensure qrcodes directory exists and is writable
    $qrDir = __DIR__ . '/qrcodes';
    if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

    echo "<pre style='font-family:monospace;background:#1a1a1a;color:#0f0;padding:2rem'>";
    echo "✅ Database schema created.\n";
    echo "✅ QR codes directory ready.\n";
    echo "\n📋 Default admin credentials:\n";
    echo "   Username: admin\n";
    echo "   Password: Admin@123\n";
    echo "\n🔴 IMPORTANT: Delete setup.php after first run!\n";
    echo "\n🔗 Links:\n";
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    echo "   Register:   {$base}/register.php\n";
    echo "   Leaderboard:{$base}/leaderboard.php\n";
    echo "   Admin:      {$base}/admin/login.php\n";
    echo "</pre>";
} catch (Exception $e) {
    echo "<pre style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
