<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']      ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $city      = trim($_POST['city']      ?? '');
    $dob       = trim($_POST['dob']       ?? '');
    $team_name = trim($_POST['team_name'] ?? '');

    if (!$name)                           $errors[] = 'Full name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    if (!$errors) {
        try {
            $pdo = db();

            // Check duplicate
            $chk = $pdo->prepare('SELECT id FROM participants WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'This email is already registered. <a href="' . APP_URL . '/participant-login.php" class="alert-link">Login instead →</a>';
            } else {
                $token = generateToken(32);

                $stmt = $pdo->prepare('
                    INSERT INTO participants (name, email, phone, city, dob, team_name, qr_token)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$name, $email, $phone, $city, $dob ?: null, $team_name, $token]);
                $participantId = (int)$pdo->lastInsertId();

                // Generate QR image
                $qrUrl = generateQrCode($token);

                // Update record with image path
                $pdo->prepare('UPDATE participants SET qr_image = ? WHERE id = ?')
                    ->execute([$qrUrl, $participantId]);

                // Auto-enroll in active round
                $activeRound = $pdo->query(
                    'SELECT r.id FROM rounds r JOIN events e ON e.id=r.event_id
                     WHERE e.status="active" AND r.status="active"
                     ORDER BY r.round_number LIMIT 1'
                )->fetch();
                if ($activeRound) {
                    $pdo->prepare('INSERT IGNORE INTO participant_rounds (participant_id, round_id) VALUES (?, ?)')
                        ->execute([$participantId, $activeRound['id']]);
                }

                // Log
                $pdo->prepare('INSERT INTO qr_scan_logs (participant_id, scan_type, ip_address, user_agent) VALUES (?,?,?,?)')
                    ->execute([$participantId, 'registration', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

                flash('success', 'Registration successful!');
                redirect(APP_URL . '/participant.php?token=' . $token);
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error. Please try again.';
        }
    }
}

pageHead('Register');
?>
<header class="public-header">
  <div class="logo-text">Pit <span>Lane</span></div>
  <div style="font-size:.8rem;color:#0096D6;font-weight:700;letter-spacing:1px">POWERED BY HP</div>
  <a href="<?= APP_URL ?>/leaderboard.php" class="btn btn-outline-ferrari btn-sm">Leaderboard</a>
</header>

<div class="reg-container">
  <div class="reg-card shadow-dark">
    <div class="reg-card-header">
      <div style="font-size:2.5rem">🏎</div>
      <h1 class="h4 fw-800 mb-1 mt-2" style="color:#fff">Register to Compete</h1>
      <p class="mb-0" style="color:rgba(255,255,255,.7);font-size:.9rem">A Pit Lane of Ferrari · HP Challenge</p>
    </div>
    <div class="reg-card-body">
      <?php foreach ($errors as $e): ?>
      <div class="alert-ferrari mb-3"><?= $e ?></div>
      <?php endforeach; ?>

      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" placeholder="John Doe"
                 value="<?= sanitize($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com"
                 value="<?= sanitize($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" placeholder="+91 9999999999"
                   value="<?= sanitize($_POST['phone'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control" placeholder="Mumbai"
                   value="<?= sanitize($_POST['city'] ?? '') ?>">
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="form-control"
                   value="<?= sanitize($_POST['dob'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label">Team Name</label>
            <input type="text" name="team_name" class="form-control" placeholder="Team Scuderia"
                   value="<?= sanitize($_POST['team_name'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-ferrari w-100 py-3 mt-2">
          <i class="bi bi-flag-fill me-2"></i>Register & Get My QR Code
        </button>
      </form>

      <p class="text-center mt-3 mb-0" style="color:var(--text-muted);font-size:.85rem">
        Already registered?
        <a href="<?= APP_URL ?>/participant-login.php" style="color:var(--ferrari-red)">Login with QR →</a>
      </p>
    </div>
  </div>
</div>
<?php pageFoot(); ?>
