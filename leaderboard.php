<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

// Rounds for filter
$rounds = $pdo->query('
    SELECT r.id, r.round_number, r.name, r.status
    FROM rounds r JOIN events e ON e.id=r.event_id
    ORDER BY r.round_number
')->fetchAll();

$roundId = (int)($_GET['round'] ?? 0);

if ($roundId) {
    $stmt = $pdo->prepare('SELECT * FROM v_round_leaderboard WHERE round_id = ? ORDER BY round_rank LIMIT 200');
    $stmt->execute([$roundId]);
    $entries = $stmt->fetchAll();
} else {
    $entries = $pdo->query('SELECT * FROM v_leaderboard ORDER BY overall_rank LIMIT 200')->fetchAll();
}

$currentRound = null;
if ($roundId) {
    foreach ($rounds as $r) {
        if ($r['id'] == $roundId) { $currentRound = $r; break; }
    }
}

pageHead('Leaderboard');
?>
<header class="public-header">
  <div class="logo-text">Pit <span>Lane</span></div>
  <div style="font-size:.8rem;color:#0096D6;font-weight:700;letter-spacing:1px">POWERED BY HP</div>
  <a href="<?= APP_URL ?>/register.php" class="btn btn-ferrari btn-sm">Register</a>
</header>

<div class="container py-4">
  <!-- Header -->
  <div class="text-center mb-4">
    <h1 class="fw-900" style="font-size:clamp(1.75rem,4vw,3rem);letter-spacing:-1px">
      <span style="color:var(--ferrari-red)">🏆</span> Leaderboard
    </h1>
    <p style="color:var(--text-muted)">Live standings · updates every 30 seconds</p>
  </div>

  <!-- Round filter pills -->
  <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
    <a href="<?= APP_URL ?>/leaderboard.php"
       class="btn btn-sm <?= !$roundId ? 'btn-ferrari' : 'btn-outline-ferrari' ?>">
      Overall
    </a>
    <?php foreach ($rounds as $r): ?>
    <a href="<?= APP_URL ?>/leaderboard.php?round=<?= $r['id'] ?>"
       class="btn btn-sm <?= $roundId == $r['id'] ? 'btn-ferrari' : 'btn-outline-ferrari' ?>">
      <?= sanitize($r['name']) ?>
      <?php if ($r['status'] === 'active'): ?>
      <span class="status-dot active ms-1"></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Top 3 podium -->
  <?php if (count($entries) >= 3): ?>
  <div class="row g-3 mb-4 justify-content-center">
    <?php
    $rankKey   = $roundId ? 'round_rank'  : 'overall_rank';
    $scoreKey  = $roundId ? 'score'       : 'total_score';
    $nameKey   = $roundId ? 'participant_name' : 'name';
    $top3 = array_slice($entries, 0, 3);
    $order = [1, 0, 2]; // Silver, Gold, Bronze display order
    $heights = [' ', 'podium-1st', ' ', 'podium-3rd'];
    $podiumColors = ['var(--ferrari-silver)', 'var(--ferrari-gold)', '#CD7F32'];
    ?>
    <?php foreach ([1,0,2] as $idx): ?>
    <?php if (isset($top3[$idx])): $t = $top3[$idx]; ?>
    <div class="col-md-3 col-6">
      <div class="card p-3 text-center" style="border-top:3px solid <?= $podiumColors[$idx] ?>">
        <div style="font-size:1.5rem;margin-bottom:.5rem">
          <?= $idx===0 ? '🥇' : ($idx===1 ? '🥈' : '🥉') ?>
        </div>
        <div class="fw-700"><?= sanitize($t[$nameKey]) ?></div>
        <?php if ($t['city'] ?? ''): ?>
        <div style="font-size:.8rem;color:var(--text-muted)"><?= sanitize($t['city']) ?></div>
        <?php endif; ?>
        <div class="score-pill mt-2"><?= formatScore($t[$scoreKey]) ?> pts</div>
      </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Full table -->
  <div class="card leaderboard-auto-refresh">
    <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
      <span>
        <i class="bi bi-list-ol me-2 text-ferrari"></i>
        <?= $currentRound ? sanitize($currentRound['name']) . ' Rankings' : 'Overall Rankings' ?>
      </span>
      <span style="font-size:.8rem;color:var(--text-muted)"><?= count($entries) ?> participants</span>
    </div>
    <div class="table-responsive">
      <table class="table table-dark-custom mb-0">
        <thead>
          <tr>
            <th style="width:80px">Rank</th>
            <th>Participant</th>
            <th>Team</th>
            <?php if (!$roundId): ?>
            <th>Rounds</th>
            <?php endif; ?>
            <th class="text-end">Score</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($entries): ?>
          <?php foreach ($entries as $e): ?>
          <tr class="leaderboard-row">
            <td><?= getRankBadge((int)$e[$rankKey]) ?></td>
            <td>
              <div class="fw-600"><?= sanitize($e[$nameKey]) ?></div>
              <?php if (!$roundId && ($e['city'] ?? '')): ?>
              <div style="font-size:.8rem;color:var(--text-muted)"><?= sanitize($e['city']) ?></div>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-muted);font-size:.875rem">
              <?= sanitize($e['team_name'] ?? '–') ?>
            </td>
            <?php if (!$roundId): ?>
            <td><span style="color:var(--text-muted)"><?= $e['rounds_participated'] ?></span></td>
            <?php endif; ?>
            <td class="text-end">
              <span class="score-pill"><?= formatScore($e[$scoreKey]) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <i class="bi bi-trophy"></i>
              <div>No rankings yet. Be the first to score!</div>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php pageFoot(); ?>
