<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();
$flash = null;

// Save score
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_score') {
    $prId  = (int)$_POST['pr_id'];
    $score = (float)$_POST['score'];
    $notes = trim($_POST['notes'] ?? '');
    $pdo->prepare("UPDATE participant_rounds SET score=?, notes=?, status='scored', updated_at=NOW() WHERE id=?")
        ->execute([$score, $notes, $prId]);
    flash('success', 'Score saved.');
    redirect(APP_URL . '/admin/scores.php?' . http_build_query(array_filter($_GET)));
}

// Bulk score
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_score') {
    $roundId = (int)$_POST['round_id'];
    $scores  = $_POST['scores'] ?? [];
    $stmt = $pdo->prepare("UPDATE participant_rounds SET score=?, status='scored', updated_at=NOW() WHERE id=?");
    foreach ($scores as $prId => $score) {
        if ($score !== '') $stmt->execute([(float)$score, (int)$prId]);
    }
    flash('success', 'Bulk scores saved.');
    redirect(APP_URL . '/admin/scores.php?round=' . $roundId);
}

$flash = getFlash('success');

$rounds = $pdo->query("
    SELECT r.id, r.round_number, r.name, r.max_score, r.status
    FROM rounds r JOIN events e ON e.id=r.event_id
    ORDER BY r.round_number
")->fetchAll();

$roundId       = (int)($_GET['round']       ?? ($rounds[0]['id'] ?? 0));
$participantId = (int)($_GET['participant'] ?? 0);

$currentRound = null;
foreach ($rounds as $r) {
    if ($r['id'] == $roundId) { $currentRound = $r; break; }
}

$where  = ['pr.round_id = ?'];
$params = [$roundId];
if ($participantId) { $where[] = 'p.id = ?'; $params[] = $participantId; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$entries = $pdo->prepare("
    SELECT pr.id AS pr_id, pr.score, pr.status AS pr_status, pr.notes, pr.checked_in_at,
           p.id AS p_id, p.name, p.email, p.city, p.team_name
    FROM participant_rounds pr
    JOIN participants p ON p.id = pr.participant_id
    {$whereSQL}
    ORDER BY pr.score DESC, p.name
");
$entries->execute($params);
$entries = $entries->fetchAll();

pageHead('Scores', true);
?>
<div class="d-flex">
<?php adminNav('scores'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Score Management</span>
  </div>

  <div class="admin-content">
    <?php if ($flash): ?>
    <div class="alert-success-custom mb-3" data-autohide="3000"><i class="bi bi-check-circle me-1"></i><?= sanitize($flash) ?></div>
    <?php endif; ?>

    <!-- Round tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4">
      <?php foreach ($rounds as $r): ?>
      <a href="<?= APP_URL ?>/admin/scores.php?round=<?= $r['id'] ?>"
         class="btn btn-sm <?= $roundId==$r['id'] ? 'btn-ferrari' : 'btn-outline-ferrari' ?>">
        R<?= $r['round_number'] ?>: <?= sanitize($r['name']) ?>
        <?php if ($r['status']==='active'): ?><span class="status-dot active ms-1"></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($currentRound): ?>
    <div class="card mb-4 p-3 d-flex flex-row align-items-center gap-3" style="border-left:3px solid var(--ferrari-red)">
      <div>
        <div class="fw-800"><?= sanitize($currentRound['name']) ?></div>
        <div style="color:var(--text-muted);font-size:.85rem">Max score: <strong class="text-ferrari"><?= $currentRound['max_score'] ?></strong> · Status: <?= $currentRound['status'] ?></div>
      </div>
      <div class="ms-auto fw-700" style="font-size:1.5rem"><?= count($entries) ?> participants</div>
    </div>
    <?php endif; ?>

    <!-- Bulk score form -->
    <div class="card">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-123 me-2 text-ferrari"></i>Enter Scores</span>
        <input id="tableSearch" class="form-control form-control-sm w-auto" placeholder="Search participant…" style="min-width:180px">
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="bulk_score">
        <input type="hidden" name="round_id" value="<?= $roundId ?>">
        <div class="table-responsive">
          <table class="table table-dark-custom mb-0">
            <thead>
              <tr><th>#</th><th>Name</th><th>Team</th><th>Status</th><th style="width:160px">Score / <?= $currentRound['max_score'] ?? '?' ?></th><th>Notes</th></tr>
            </thead>
            <tbody>
              <?php if ($entries): ?>
              <?php foreach ($entries as $i => $e): ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                <td>
                  <div class="fw-600"><?= sanitize($e['name']) ?></div>
                  <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($e['email']) ?></div>
                </td>
                <td style="color:var(--text-muted)"><?= sanitize($e['team_name'] ?: '–') ?></td>
                <td><span class="badge badge-<?= $e['pr_status'] ?>"><?= ucfirst(str_replace('_',' ',$e['pr_status'])) ?></span></td>
                <td>
                  <input type="number" name="scores[<?= $e['pr_id'] ?>]"
                         class="form-control form-control-sm"
                         value="<?= $e['pr_status']==='scored' ? $e['score'] : '' ?>"
                         min="0" max="<?= $currentRound['max_score'] ?? 999 ?>"
                         step="0.5" placeholder="–">
                </td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= sanitize($e['notes'] ?: '') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr><td colspan="6">
                <div class="empty-state">
                  <i class="bi bi-person-x"></i>
                  <div>No participants enrolled in this round.<br>
                    <a href="<?= APP_URL ?>/admin/rounds.php" style="color:var(--ferrari-red)">Go to Rounds → Enroll All</a>
                  </div>
                </div>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($entries): ?>
        <div class="p-3 border-top d-flex gap-2 justify-content-end" style="border-color:var(--card-border)">
          <button type="submit" class="btn btn-ferrari px-4">
            <i class="bi bi-save2 me-1"></i>Save All Scores
          </button>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
</div>
<?php pageFoot(); ?>
