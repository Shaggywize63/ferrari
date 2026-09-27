<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

// Token from URL (QR scan)
$token = trim($_GET['token'] ?? '');
if ($token) {
    $stmt = db()->prepare('SELECT id, name, qr_token FROM participants WHERE qr_token = ?');
    $stmt->execute([$token]);
    $p = $stmt->fetch();
    if ($p) {
        $_SESSION['participant_id']    = $p['id'];
        $_SESSION['participant_token'] = $token;
        redirect(APP_URL . '/participant.php?token=' . $token);
    }
    $error = 'Invalid QR code. Please try scanning again.';
}

// Manual email lookup
$error = $error ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $stmt = db()->prepare('SELECT id, qr_token FROM participants WHERE email = ?');
        $stmt->execute([$email]);
        $p = $stmt->fetch();
        if ($p) {
            $_SESSION['participant_id']    = $p['id'];
            $_SESSION['participant_token'] = $p['qr_token'];
            redirect(APP_URL . '/participant.php?token=' . $p['qr_token']);
        }
        $error = 'No participant found with this email. Please register first.';
    }
}

pageHead('Participant Login');
?>
<header class="public-header">
  <div class="logo-text">Pit <span>Lane</span></div>
  <div style="font-size:.8rem;color:#0096D6;font-weight:700;letter-spacing:1px">POWERED BY HP</div>
  <a href="<?= APP_URL ?>/register.php" class="btn btn-ferrari btn-sm">Register</a>
</header>

<div class="reg-container">
  <div class="reg-card shadow-dark">
    <div class="reg-card-header">
      <div style="font-size:2.5rem">📱</div>
      <h1 class="h4 fw-800 mb-1 mt-2" style="color:#fff">Participant Login</h1>
      <p class="mb-0" style="color:rgba(255,255,255,.7);font-size:.9rem">Scan your QR code or enter your email</p>
    </div>
    <div class="reg-card-body">
      <?php if ($error): ?>
      <div class="alert-ferrari mb-3"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <div class="text-center mb-4" style="padding:1.5rem;background:rgba(220,0,0,.05);border:1px dashed rgba(220,0,0,.3);border-radius:12px">
        <i class="bi bi-qr-code-scan" style="font-size:3rem;color:var(--ferrari-red)"></i>
        <div class="mt-2" style="color:var(--text-muted);font-size:.9rem">
          Ask the pit crew to scan your QR code at each stage
        </div>
      </div>

      <div class="d-flex align-items-center gap-3 mb-4">
        <hr class="flex-grow-1 divider"> <span style="color:var(--text-muted);font-size:.8rem">OR</span> <hr class="flex-grow-1 divider">
      </div>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Look up by Email</label>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
        </div>
        <button type="submit" class="btn btn-ferrari w-100 py-3">
          <i class="bi bi-search me-2"></i>Find My Profile
        </button>
      </form>

      <p class="text-center mt-3 mb-0" style="color:var(--text-muted);font-size:.85rem">
        Not registered yet?
        <a href="<?= APP_URL ?>/register.php" style="color:var(--ferrari-red)">Register now →</a>
      </p>
    </div>
  </div>
</div>
<?php pageFoot(); ?>
