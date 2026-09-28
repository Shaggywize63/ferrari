<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo      = db();
$admin    = currentAdmin();
$mustSet  = !empty($_SESSION['must_change_password']);
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current'] ?? '');
    $new     = (string)($_POST['new'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    $row = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $row->execute([$_SESSION['admin_id']]);
    $hash = (string)$row->fetchColumn();

    if (!adminPasswordOk($current, $hash))       $errors[] = 'Your current password is not correct.';
    if (strlen($new) < 10)                        $errors[] = 'Choose a new password of at least 10 characters.';
    elseif ($new === DEFAULT_ADMIN_PASSWORD)      $errors[] = 'Choose a password other than the default one.';
    elseif ($new === $current)                    $errors[] = 'The new password must be different from the current one.';
    if ($new !== $confirm)                        $errors[] = 'The two new passwords do not match.';

    if (!$errors) {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
        unset($_SESSION['must_change_password']);
        session_regenerate_id(true);
        flash('account', 'Password updated.');
        redirect(APP_URL . '/admin/account.php');
    }
}
$saved = getFlash('account');

pageHead('Account', true);
?>
<div class="d-flex">
<?php adminNav('account'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Account</span>
  </div>

  <div class="admin-content">
    <div class="card" style="max-width:640px">
      <div class="card-header px-4 py-3"><span><i class="bi bi-shield-lock me-2 text-ferrari"></i>Change Password</span></div>
      <div class="card-body p-4">
        <?php if ($mustSet): ?>
        <div class="mb-4" style="border-left:3px solid #D40000;background:#FFF4F4;padding:.8rem 1rem">
          You signed in with the default password. Set your own password to continue to the dashboard.
        </div>
        <?php endif; ?>
        <?php if ($saved): ?>
        <div class="mb-4" style="border-left:3px solid #1E8E3E;background:#F1FAF3;padding:.8rem 1rem;color:#1E8E3E"><?= sanitize($saved) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?>
        <div class="mb-2" style="border-left:3px solid #D40000;background:#FFF4F4;padding:.6rem 1rem;color:#D40000"><?= sanitize($e) ?></div>
        <?php endforeach; ?>

        <p class="eyebrow mb-3">Signed in as <span style="color:#0D1B3E"><?= sanitize($admin['username'] ?? '') ?></span></p>
        <form method="post" autocomplete="off">
          <div class="mb-3">
            <label class="form-label" for="current">Current password</label>
            <input type="password" id="current" name="current" class="form-control" required autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label" for="new">New password</label>
            <input type="password" id="new" name="new" class="form-control" required minlength="10" autocomplete="new-password">
            <small style="color:var(--text-muted)">At least 10 characters.</small>
          </div>
          <div class="mb-4">
            <label class="form-label" for="confirm">Confirm new password</label>
            <input type="password" id="confirm" name="confirm" class="form-control" required minlength="10" autocomplete="new-password">
          </div>
          <button class="btn btn-ferrari">Save Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
<?php pageFoot(); ?>
