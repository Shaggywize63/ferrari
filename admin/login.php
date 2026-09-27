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
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem">
  <div style="width:100%;max-width:400px">
    <div class="text-center mb-4">
      <div style="font-size:3rem">🏎</div>
      <h1 class="h4 fw-800">Admin Portal</h1>
      <div style="color:var(--text-muted);font-size:.875rem">A Pit Lane of Ferrari</div>
    </div>
    <div class="card p-4">
      <?php if ($error): ?>
      <div class="alert-ferrari mb-3"><?= sanitize($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" placeholder="admin" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-ferrari w-100 py-3">
          <i class="bi bi-shield-lock me-2"></i>Login to Admin
        </button>
      </form>
      <div class="mt-3 text-center" style="font-size:.8rem;color:var(--text-muted)">
        Default: admin / Admin@123
      </div>
    </div>
    <div class="text-center mt-3">
      <a href="<?= APP_URL ?>/leaderboard.php" style="color:var(--text-muted);font-size:.85rem">
        ← Back to Leaderboard
      </a>
    </div>
  </div>
</div>
<?php pageFoot(); ?>
