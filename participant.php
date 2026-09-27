<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$code = strtoupper(trim($_GET['code'] ?? $_SESSION['participant_code'] ?? ''));
if (!$code) redirect(APP_URL . '/participant-login.php');

$pdo  = db();
$stmt = $pdo->prepare('SELECT * FROM participants WHERE access_code = ?');
$stmt->execute([$code]);
$p = $stmt->fetch();
if (!$p) redirect(APP_URL . '/participant-login.php');

$_SESSION['participant_id']   = $p['id'];
$_SESSION['participant_code'] = $code;

// Fetch leaderboard position
$lb = $pdo->prepare('SELECT overall_rank, total_score, rounds_participated, highest_round FROM v_leaderboard WHERE id = ?');
$lb->execute([$p['id']]);
$rank = $lb->fetch();

// Fetch rounds history
$rounds = $pdo->prepare('
    SELECT r.round_number, r.name AS round_name, r.max_score, r.status AS round_status,
           pr.score, pr.status, pr.checked_in_at
    FROM participant_rounds pr
    JOIN rounds r ON r.id = pr.round_id
    WHERE pr.participant_id = ?
    ORDER BY r.round_number
');
$rounds->execute([$p['id']]);
$roundHistory = $rounds->fetchAll();

// All rounds for progress bar
$allRounds = $pdo->query('
    SELECT r.* FROM rounds r
    JOIN events e ON e.id = r.event_id
    WHERE e.status IN ("active","upcoming")
    ORDER BY r.round_number
')->fetchAll();

$flash = getFlash('success');

pageHead('My Dashboard');
?>
<header class="participant-header">
  <div class="container-fluid d-flex align-items-center justify-content-between">
    <div class="logo-text fw-800" style="font-size:1.25rem">Pit <span style="color:var(--ferrari-red)">Lane</span></div>
    <div class="d-flex align-items-center gap-3">
      <a href="<?= APP_URL ?>/leaderboard.php" class="btn btn-outline-ferrari btn-sm">
        <i class="bi bi-trophy me-1"></i>Leaderboard
      </a>
      <a href="<?= APP_URL ?>/logout.php" class="btn btn-sm" style="color:var(--text-muted)">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</header>

<div class="container py-4">
  <?php if ($flash): ?>
  <div class="alert-success-custom mb-3" data-autohide="4000">
    <i class="bi bi-check-circle me-2"></i><?= sanitize($flash) ?>
  </div>
  <?php endif; ?>

  <!-- Hero greeting -->
  <div class="row g-4 mb-4">
    <div class="col-md-8">
      <div class="card p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="avatar" style="width:56px;height:56px;font-size:1.25rem"><?= strtoupper(substr($p['name'],0,1)) ?></div>
          <div>
            <h2 class="h4 fw-800 mb-0"><?= sanitize($p['name']) ?></h2>
            <div style="color:var(--text-muted);font-size:.85rem">
              <?= sanitize($p['email']) ?>
              <?php if ($p['city']): ?> · <?= sanitize($p['city']) ?><?php endif; ?>
              <?php if ($p['team_name']): ?> · <span style="color:var(--ferrari-gold)"><?= sanitize($p['team_name']) ?></span><?php endif; ?>
            </div>
          </div>
          <span class="badge ms-auto" style="background:rgba(40,167,69,.15);color:#28a745;font-size:.8rem">
            <span class="status-dot active me-1"></span>Active
          </span>
        </div>
        <!-- Stage progress -->
        <?php if ($allRounds): ?>
        <div class="progress-stage flex-wrap gap-2 mt-1">
          <?php
          $completedRoundIds = array_column(
              array_filter($roundHistory, fn($r) => in_array($r['status'], ['scored','checked_in','eliminated'])),
              'round_name'
          );
          foreach ($allRounds as $i => $ar):
            $participated = array_filter($roundHistory, fn($rh) => $rh['round_name'] === $ar['name']);
            $pr = reset($participated);
            $cls = $pr ? (in_array($pr['status'], ['scored']) ? 'completed' : 'active') : '';
          ?>
          <?php if ($i > 0): ?><div class="stage-line <?= $pr || $ar['status']==='completed' ? 'completed' : '' ?>"></div><?php endif; ?>
          <div class="stage-item">
            <div class="stage-dot <?= $cls ?>">
              <?php if ($cls === 'completed'): ?><i class="bi bi-check2" style="font-size:.7rem"></i>
              <?php else: ?><?= $ar['round_number'] ?><?php endif; ?>
            </div>
            <span style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($ar['name']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Access Code card -->
    <div class="col-md-4">
      <div class="card p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
        <div class="qr-print-area">
          <div style="margin-bottom:.75rem;font-size:.75rem;letter-spacing:.18em;text-transform:uppercase;color:var(--text-muted)">Your Access Code</div>
          <div id="accessCode" style="
            font-size:clamp(2rem,5vw,3.5rem);font-weight:800;letter-spacing:.25em;
            color:var(--ferrari-red);font-family:'forma-djr-display',system-ui,sans-serif;
            background:rgba(220,0,0,.07);border:2px solid rgba(220,0,0,.3);
            border-radius:12px;padding:.6rem 1.5rem;display:inline-block;
          "><?= sanitize($p['access_code']) ?></div>
          <div class="print-name"><?= sanitize($p['name']) ?></div>
          <div class="print-event">A Pit Lane of Ferrari · Powered by HP</div>
        </div>
        <div class="mt-3" style="font-size:.8rem;color:var(--text-muted)">Show this code at each stage to check in</div>
        <div class="d-flex gap-2 mt-2">
          <button onclick="copyCode()" class="btn btn-outline-ferrari btn-sm" id="copyBtn">
            <i class="bi bi-copy me-1"></i>Copy Code
          </button>
          <button onclick="window.print()" class="btn btn-outline-ferrari btn-sm">
            <i class="bi bi-printer me-1"></i>Print
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card red">
        <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
        <div class="stat-value"><?= $rank ? '#' . $rank['overall_rank'] : '–' ?></div>
        <div class="stat-label">Overall Rank</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card gold">
        <div class="stat-icon"><i class="bi bi-star-fill"></i></div>
        <div class="stat-value"><?= $rank ? formatScore($rank['total_score']) : '0' ?></div>
        <div class="stat-label">Total Score</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="bi bi-flag-fill"></i></div>
        <div class="stat-value"><?= $rank ? $rank['rounds_participated'] : '0' ?></div>
        <div class="stat-label">Rounds Played</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="stat-value"><?= $rank ? 'R' . ($rank['highest_round'] ?: '–') : '–' ?></div>
        <div class="stat-label">Highest Round</div>
      </div>
    </div>
  </div>

  <!-- Round history -->
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between px-4 py-3">
      <span><i class="bi bi-list-ol me-2 text-ferrari"></i>My Round History</span>
    </div>
    <div class="table-responsive">
      <table class="table table-dark-custom mb-0">
        <thead>
          <tr>
            <th>Round</th>
            <th>Name</th>
            <th>Status</th>
            <th>Score</th>
            <th>Checked In</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($roundHistory): ?>
          <?php foreach ($roundHistory as $rh): ?>
          <tr>
            <td><span class="fw-700">R<?= $rh['round_number'] ?></span></td>
            <td><?= sanitize($rh['round_name']) ?></td>
            <td><span class="badge badge-<?= $rh['status'] ?>"><?= ucfirst(str_replace('_',' ',$rh['status'])) ?></span></td>
            <td>
              <?php if ($rh['status'] === 'scored'): ?>
              <span class="score-pill"><?= formatScore($rh['score']) ?> / <?= $rh['max_score'] ?></span>
              <?php else: ?>
              <span style="color:var(--text-muted)">–</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.85rem;color:var(--text-muted)">
              <?= $rh['checked_in_at'] ? date('d M Y, h:i A', strtotime($rh['checked_in_at'])) : '–' ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">
            No rounds participated yet. Check in at the next round!
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function copyCode() {
  const code = document.getElementById('accessCode').textContent.trim();
  navigator.clipboard.writeText(code).then(() => {
    const btn = document.getElementById('copyBtn');
    btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied!';
    setTimeout(() => { btn.innerHTML = '<i class="bi bi-copy me-1"></i>Copy Code'; }, 2000);
  });
}
</script>
<?php pageFoot(); ?>
