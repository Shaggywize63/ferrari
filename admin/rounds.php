<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $pdo->prepare("
            INSERT INTO rounds (event_id, round_number, name, description, max_score, start_date, end_date, status)
            VALUES (?,?,?,?,?,?,?,?)
        ")->execute([
            (int)$_POST['event_id'],
            (int)$_POST['round_number'],
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            (int)($_POST['max_score'] ?: 100),
            $_POST['start_date'] ?: null,
            $_POST['end_date']   ?: null,
            $_POST['status'] ?: 'upcoming',
        ]);
        flash('success', 'Round created successfully.');
        redirect(APP_URL . '/admin/rounds.php');
    }

    if ($action === 'set_status' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE rounds SET status=? WHERE id=?")->execute([$_POST['status'], (int)$_POST['id']]);
        flash('success', 'Round status updated.');
        redirect(APP_URL . '/admin/rounds.php');
    }

    if ($action === 'enroll_all' && isset($_POST['round_id'])) {
        // Enroll all active participants in this round
        $roundId = (int)$_POST['round_id'];
        $ps = $pdo->query("SELECT id FROM participants WHERE status='active'")->fetchAll();
        $stmt = $pdo->prepare('INSERT IGNORE INTO participant_rounds (participant_id, round_id) VALUES (?,?)');
        foreach ($ps as $p) $stmt->execute([$p['id'], $roundId]);
        flash('success', count($ps) . ' participants enrolled.');
        redirect(APP_URL . '/admin/rounds.php');
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM rounds WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'Round deleted.');
        redirect(APP_URL . '/admin/rounds.php');
    }
}

$flash  = getFlash('success');
$events = $pdo->query('SELECT id, name FROM events ORDER BY id DESC')->fetchAll();
$rounds = $pdo->query("
    SELECT r.*, e.name AS event_name,
           COUNT(pr.id) AS enrolled_count,
           SUM(CASE WHEN pr.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in_count
    FROM rounds r
    LEFT JOIN events e ON e.id = r.event_id
    LEFT JOIN participant_rounds pr ON pr.round_id = r.id
    GROUP BY r.id
    ORDER BY r.event_id, r.round_number
")->fetchAll();

pageHead('Rounds', true);
?>
<div class="d-flex">
<?php adminNav('rounds'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Rounds</span>
    <div class="ms-auto">
      <button class="btn btn-ferrari btn-sm" data-bs-toggle="modal" data-bs-target="#createRoundModal">
        <i class="bi bi-plus-lg me-1"></i>Create Round
      </button>
    </div>
  </div>

  <div class="admin-content">
    <?php if ($flash): ?>
    <div class="alert-success-custom mb-3" data-autohide="3000"><i class="bi bi-check-circle me-1"></i><?= sanitize($flash) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <?php foreach ($rounds as $r): ?>
      <div class="col-md-6 col-xl-4">
        <div class="card p-0 overflow-hidden" style="border-top:3px solid <?= $r['status']==='active' ? 'var(--ferrari-red)' : ($r['status']==='completed' ? '#28a745' : 'var(--card-border)') ?>">
          <div class="p-4">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <div>
                <div class="fw-800" style="font-size:1.1rem">Round <?= $r['round_number'] ?></div>
                <div style="color:var(--text-muted);font-size:.8rem"><?= sanitize($r['event_name']) ?></div>
              </div>
              <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
            </div>
            <div class="fw-600 mb-1"><?= sanitize($r['name']) ?></div>
            <?php if ($r['description']): ?>
            <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:.75rem"><?= sanitize($r['description']) ?></div>
            <?php endif; ?>

            <div class="row g-2 text-center mt-2 pt-2" style="border-top:1px solid var(--card-border)">
              <div class="col-4">
                <div class="fw-700"><?= $r['enrolled_count'] ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)">Enrolled</div>
              </div>
              <div class="col-4">
                <div class="fw-700"><?= $r['checked_in_count'] ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)">Checked In</div>
              </div>
              <div class="col-4">
                <div class="fw-700"><?= $r['max_score'] ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)">Max Score</div>
              </div>
            </div>
          </div>

          <div class="px-3 pb-3 d-flex gap-2 flex-wrap">
            <!-- Status change -->
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="set_status">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;display:inline-block">
                <?php foreach (['upcoming','active','completed'] as $st): ?>
                <option value="<?= $st ?>" <?= $r['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
              </select>
            </form>

            <!-- Enroll all -->
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="enroll_all">
              <input type="hidden" name="round_id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-outline-ferrari btn-sm"
                      onclick="return confirm('Enroll all active participants in this round?')">
                <i class="bi bi-person-plus"></i> Enroll All
              </button>
            </form>

            <a href="<?= APP_URL ?>/admin/scores.php?round=<?= $r['id'] ?>" class="btn btn-sm" style="background:rgba(200,168,75,.1);color:var(--ferrari-gold)">
              <i class="bi bi-123 me-1"></i>Scores
            </a>

            <form method="POST" class="ms-auto d-inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm" style="color:var(--text-muted)"
                      onclick="return confirm('Delete this round and all its scores?')">
                <i class="bi bi-trash3"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>

<!-- Create Round Modal -->
<div class="modal fade" id="createRoundModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background:var(--card-bg);border:1px solid var(--card-border)">
      <div class="modal-header" style="border-color:var(--card-border)">
        <h5 class="modal-title fw-700">Create New Round</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Event</label>
            <select name="event_id" class="form-select" required>
              <?php foreach ($events as $ev): ?>
              <option value="<?= $ev['id'] ?>"><?= sanitize($ev['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-5">
              <label class="form-label">Round Number</label>
              <input type="number" name="round_number" class="form-control" min="1" value="<?= count($rounds)+1 ?>" required>
            </div>
            <div class="col-7">
              <label class="form-label">Max Score</label>
              <input type="number" name="max_score" class="form-control" min="1" value="100" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Round Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Grand Finale" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional description…"></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label">Start Date/Time</label>
              <input type="datetime-local" name="start_date" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">End Date/Time</label>
              <input type="datetime-local" name="end_date" class="form-control">
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="upcoming">Upcoming</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>
        <div class="modal-footer" style="border-color:var(--card-border)">
          <button type="button" class="btn btn-outline-ferrari" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-ferrari"><i class="bi bi-plus-lg me-1"></i>Create Round</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php pageFoot(); ?>
