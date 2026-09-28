<?php
/**
 * One-time migration runner.
 * Visit /migrate.php?key=ferrari2026 to run, then delete this file.
 */
if (($_GET['key'] ?? '') !== 'ferrari2026') {
    http_response_code(403);
    die('Access denied. Append ?key=ferrari2026 to the URL.');
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';

$steps = [];

function step(string $label, callable $fn): void {
    global $steps;
    try {
        $fn();
        $steps[] = ['ok', $label];
    } catch (Throwable $e) {
        $steps[] = ['err', $label . ' — ' . $e->getMessage()];
    }
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1. Add access_code column if missing
step('Add access_code column', function() use ($pdo) {
    $cols = $pdo->query("SHOW COLUMNS FROM participants LIKE 'access_code'")->fetchAll();
    if (!$cols) {
        $pdo->exec("ALTER TABLE participants ADD COLUMN access_code CHAR(6) NULL AFTER team_name");
    }
});

// 2. Backfill access_code for existing rows
step('Backfill access codes for existing participants', function() use ($pdo) {
    $rows = $pdo->query("SELECT id FROM participants WHERE access_code IS NULL OR access_code = ''")->fetchAll();
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    foreach ($rows as $row) {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
            $exists = $pdo->prepare("SELECT id FROM participants WHERE access_code = ?");
            $exists->execute([$code]);
        } while ($exists->fetch());
        $pdo->prepare("UPDATE participants SET access_code = ? WHERE id = ?")->execute([$code, $row['id']]);
    }
});

// 3. Make access_code NOT NULL
step('Set access_code NOT NULL + unique index', function() use ($pdo) {
    $pdo->exec("ALTER TABLE participants MODIFY COLUMN access_code CHAR(6) NOT NULL");
    // Add unique key if not already there
    $idx = $pdo->query("SHOW INDEX FROM participants WHERE Key_name = 'uq_participants_access_code'")->fetchAll();
    if (!$idx) {
        $pdo->exec("ALTER TABLE participants ADD UNIQUE KEY uq_participants_access_code (access_code)");
    }
});

// 4. Make phone NOT NULL (backfill empty first)
step('Set phone NOT NULL', function() use ($pdo) {
    $pdo->exec("UPDATE participants SET phone = '' WHERE phone IS NULL");
    $pdo->exec("ALTER TABLE participants MODIFY COLUMN phone VARCHAR(25) NOT NULL DEFAULT ''");
});

// 5. Drop qr_token if exists
step('Drop qr_token column (if exists)', function() use ($pdo) {
    $cols = $pdo->query("SHOW COLUMNS FROM participants LIKE 'qr_token'")->fetchAll();
    if ($cols) $pdo->exec("ALTER TABLE participants DROP COLUMN qr_token");
});

// 6. Drop qr_image if exists
step('Drop qr_image column (if exists)', function() use ($pdo) {
    $cols = $pdo->query("SHOW COLUMNS FROM participants LIKE 'qr_image'")->fetchAll();
    if ($cols) $pdo->exec("ALTER TABLE participants DROP COLUMN qr_image");
});

$ok  = count(array_filter($steps, fn($s) => $s[0] === 'ok'));
$err = count(array_filter($steps, fn($s) => $s[0] === 'err'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Migration</title>
<style>
  body { font-family: system-ui, sans-serif; background: #0d0d0f; color: #f4f2ee; padding: 2rem; max-width: 700px; margin: auto; }
  h1   { color: #dc0000; }
  .step { display:flex; align-items:flex-start; gap:.75rem; margin:.5rem 0; padding:.6rem 1rem; border-radius:6px; }
  .ok  { background: rgba(40,167,69,.12); }
  .err { background: rgba(220,0,0,.12); }
  .icon { font-size:1.1rem; flex-shrink:0; }
  .summary { margin-top:1.5rem; padding:1rem; border-radius:8px; font-size:1rem; }
  .all-ok  { background:rgba(40,167,69,.15); border:1px solid rgba(40,167,69,.4); }
  .has-err { background:rgba(220,0,0,.15); border:1px solid rgba(220,0,0,.4); }
  code { background:rgba(255,255,255,.08); padding:.1rem .4rem; border-radius:4px; font-size:.85rem; }
</style>
</head>
<body>
<h1>🏎 Migration Runner</h1>
<p style="color:#888">Running database migration: QR → Access Codes</p>

<?php foreach ($steps as [$status, $msg]): ?>
<div class="step <?= $status ?>">
  <span class="icon"><?= $status === 'ok' ? '✅' : '❌' ?></span>
  <span><?= htmlspecialchars($msg) ?></span>
</div>
<?php endforeach; ?>

<div class="summary <?= $err === 0 ? 'all-ok' : 'has-err' ?>">
  <?php if ($err === 0): ?>
  <strong>✅ Migration complete!</strong> <?= $ok ?> steps ran successfully.<br>
  <span style="color:#aaa;font-size:.9rem">
    <strong>Delete this file now:</strong> <code>migrate.php</code> — it has no further use.
  </span>
  <?php else: ?>
  <strong>⚠️ <?= $err ?> step(s) failed.</strong> <?= $ok ?> succeeded. Check the errors above.
  <?php endif; ?>
</div>
</body>
</html>
