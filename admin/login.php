<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

if (isAdminLoggedIn()) redirect(APP_URL . '/admin/index.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_role'] = $admin['role'];
        redirect(APP_URL . '/admin/index.php');
    }
    $error = 'Invalid username or password.';
}

pageHead('Admin Login', true);
?>
<div class="admin-login">
  <header>
    <img src="<?= APP_URL ?>/assets/img/scuderia-ferrari-hp.svg" alt="Scuderia Ferrari HP">
    <div class="text-center" style="position:relative;z-index:1">
      <div class="eyebrow red">Admin Portal</div>
      <div style="font-size:1.1rem;font-weight:600;letter-spacing:.03em;text-transform:uppercase">Sign In</div>
    </div>
    <span style="width:100px"></span>
  </header>

  <main>
    <section>
      <div class="eyebrow">A Pit Lane of Ferrari · Powered by HP</div>
      <h1>Race<br>Control</h1>
      <p class="lead-copy">Registrations, journey progress and race results from every kiosk, live.</p>
    </section>

    <section>
      <?php if ($error): ?>
      <div class="error mb-2"><?= sanitize($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="field">
          <label class="form-label" for="username">Username</label>
          <input type="text" id="username" name="username" placeholder="Your username" required autofocus autocomplete="username">
        </div>
        <div class="field">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-ferrari">Enter Race Control <span aria-hidden="true">→</span></button>
      </form>
    </section>
  </main>

  <footer>
    <span class="eyebrow">A Pit Lane of Ferrari · Powered by HP</span>
    <a class="eyebrow" style="color:var(--navy)" href="<?= APP_URL ?>/leaderboard.html">Live Leaderboard →</a>
  </footer>
</div>
<?php pageFoot(); ?>
