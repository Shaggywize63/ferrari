<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();
$msg = null;

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM participants WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('success', 'Participant deleted.');
        redirect(APP_URL . '/admin/participants.php');
    }
    if ($action === 'set_status' && isset($_POST['id'], $_POST['status'])) {
        $pdo->prepare("UPDATE participants SET status = ? WHERE id = ?")->execute([$_POST['status'], (int)$_POST['id']]);
        flash('success', 'Status updated.');
        redirect(APP_URL . '/admin/participants.php');
    }
}

$flash = getFlash('success');

// Filters
$search  = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$round   = (int)($_GET['round'] ?? 0);

$where  = [];
$params = [];
if ($search)  { $where[] = '(p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status)  { $where[] = 'p.status = ?'; $params[] = $status; }
if ($round)   { $where[] = 'EXISTS (SELECT 1 FROM participant_rounds pr2 WHERE pr2.participant_id=p.id AND pr2.round_id=?)'; $params[] = $round; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$participants = $pdo->prepare("
    SELECT p.*,
           COALESCE(SUM(pr.score),0) AS total_score,
           COUNT(DISTINCT pr.round_id) AS rounds_played
    FROM participants p
    LEFT JOIN participant_rounds pr ON pr.participant_id = p.id AND pr.status IN ('scored','checked_in')
    {$whereSQL}
    GROUP BY p.id
    ORDER BY p.registered_at DESC
");
$participants->execute($params);
$participants = $participants->fetchAll();

$rounds = $pdo->query('SELECT r.id, r.name FROM rounds r JOIN events e ON e.id=r.event_id ORDER BY r.round_number')->fetchAll();

pageHead('Participants', true);
?>
<div class="d-flex">
<?php adminNav('participants'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Participants</span>
    <div class="ms-auto">
      <a href="<?= APP_URL ?>/register.php" target="_blank" class="btn btn-ferrari btn-sm">
        <i class="bi bi-person-plus me-1"></i>Add Participant
      </a>
    </div>
  </div>

  <div class="admin-content">
    <?php if ($flash): ?>
    <div class="alert-success-custom mb-3" data-autohide="3000">
      <i class="bi bi-check-circle me-1"></i><?= sanitize($flash) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="card p-3 mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Search</label>
          <input type="text" name="q" class="form-control" placeholder="Name, email, phone…" value="<?= sanitize($search) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="active"     <?= $status==='active'     ?'selected':'' ?>>Active</option>
            <option value="eliminated" <?= $status==='eliminated' ?'selected':'' ?>>Eliminated</option>
            <option value="winner"     <?= $status==='winner'     ?'selected':'' ?>>Winner</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Round</label>
          <select name="round" class="form-select">
            <option value="">All Rounds</option>
            <?php foreach ($rounds as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $round==$r['id']?'selected':'' ?>><?= sanitize($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-ferrari flex-grow-1">Filter</button>
          <a href="<?= APP_URL ?>/admin/participants.php" class="btn btn-outline-ferrari">Reset</a>
        </div>
      </div>
    </form>

    <!-- Table -->
    <div class="card">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-people-fill me-2 text-ferrari"></i>
          <?= count($participants) ?> Participant<?= count($participants)!==1?'s':'' ?>
        </span>
        <input id="tableSearch" class="form-control form-control-sm w-auto" placeholder="Quick search…" style="min-width:180px">
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0">
          <thead>
            <tr>
              <th>ID</th><th>Name</th><th>Email</th><th>City</th><th>Team</th>
              <th>Status</th><th>Score</th><th>Rounds</th><th>QR</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($participants as $p): ?>
            <tr>
              <td><small style="color:var(--text-muted)">#<?= $p['id'] ?></small></td>
              <td class="fw-600"><?= sanitize($p['name']) ?></td>
              <td style="font-size:.85rem;color:var(--text-muted)"><?= sanitize($p['email']) ?></td>
              <td style="font-size:.85rem;color:var(--text-muted)"><?= sanitize($p['city'] ?: '–') ?></td>
              <td style="font-size:.85rem"><?= sanitize($p['team_name'] ?: '–') ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <select name="status" class="form-select form-select-sm"
                          style="background:transparent;border:none;color:inherit;font-size:.8rem;padding:0"
                          onchange="this.form.submit()">
                    <?php foreach (['active','eliminated','winner'] as $st): ?>
                    <option value="<?= $st ?>" <?= $p['status']===$st?'selected':'' ?>>
                      <?= ucfirst($st) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td><span class="score-pill"><?= formatScore($p['total_score']) ?></span></td>
              <td style="color:var(--text-muted)"><?= $p['rounds_played'] ?></td>
              <td>
                <a href="<?= APP_URL ?>/participant.php?token=<?= urlencode($p['qr_token']) ?>"
                   target="_blank" class="btn btn-sm" style="color:var(--ferrari-red)" title="View QR">
                  <i class="bi bi-qr-code"></i>
                </a>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="<?= APP_URL ?>/admin/scores.php?participant=<?= $p['id'] ?>"
                     class="btn btn-sm" style="color:var(--ferrari-gold)" title="Manage Scores">
                    <i class="bi bi-123"></i>
                  </a>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('Delete <?= sanitize($p['name']) ?>? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="color:var(--ferrari-red)" title="Delete">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<?php pageFoot(); ?>
