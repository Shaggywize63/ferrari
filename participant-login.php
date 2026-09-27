<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['input'] ?? '');
    if ($input) {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT id, access_code FROM participants WHERE access_code = ? OR phone = ? OR email = ? LIMIT 1');
        $stmt->execute([strtoupper($input), $input, $input]);
        $p = $stmt->fetch();
        if ($p) {
            $_SESSION['participant_id']   = $p['id'];
            $_SESSION['participant_code'] = $p['access_code'];
            redirect(APP_URL . '/participant.php?code=' . $p['access_code']);
        }
        $error = 'No participant found. Please check your access code or mobile number and try again.';
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
      <div style="font-size:2.5rem">🏁</div>
      <h1 class="h4 fw-800 mb-1 mt-2" style="color:#fff">Participant Login</h1>
      <p class="mb-0" style="color:rgba(255,255,255,.7);font-size:.9rem">Enter your access code or mobile number</p>
    </div>
    <div class="reg-card-body">
      <?php if ($error): ?>
      <div class="alert-ferrari mb-3"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-4">
          <label class="form-label">Access Code or Mobile Number</label>
          <input type="text" name="input" class="form-control form-control-lg text-center"
                 placeholder="e.g. A3X7Q2 or +91 99999 99999"
                 style="letter-spacing:.12em;font-size:1.1rem;font-weight:600"
                 autocomplete="off" autofocus required>
          <div class="mt-2" style="color:var(--text-muted);font-size:.8rem;text-align:center">
            Your 6-character access code was shown after registration
          </div>
        </div>
        <button type="submit" class="btn btn-ferrari w-100 py-3">
          <i class="bi bi-box-arrow-in-right me-2"></i>Login
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
