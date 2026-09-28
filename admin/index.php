<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();

ensureKioskSchema($pdo);

// Stats — real kiosk data only (registrations + race results)
$stats = [
    'total_participants' => (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
    'registered_today'   => (int)$pdo->query('SELECT COUNT(*) FROM participants WHERE registered_at >= CURDATE()')->fetchColumn(),
    'races'              => (int)$pdo->query('SELECT COUNT(*) FROM race_scores')->fetchColumn(),
    'top_score'          => (int)$pdo->query('SELECT COALESCE(MAX(score), 0) FROM race_scores')->fetchColumn(),
];

// Recent registrations
$recent = $pdo->query(PARTICIPANT_JOURNEY_SQL . ' ORDER BY p.registered_at DESC, p.id DESC LIMIT 10')->fetchAll();

// Journey progress: drivers who have finished each station
$journey = journeyCounts($pdo);

// Best race score per driver
$topRacers = bestRaceScores($pdo, 0, 10);

// Registrations and races by day (last 7 days, zero-filled)
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['reg' => 0, 'races' => 0];
}
foreach ($pdo->query("SELECT DATE(registered_at) AS day, COUNT(*) AS cnt FROM participants
                      WHERE registered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(registered_at)") as $r) {
    if (isset($days[$r['day']])) $days[$r['day']]['reg'] = (int)$r['cnt'];
}
foreach ($pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM race_scores
                      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)") as $r) {
    if (isset($days[$r['day']])) $days[$r['day']]['races'] = (int)$r['cnt'];
}
$dayLabels = array_map(static fn($d) => date('D j M', strtotime($d)), array_keys($days));

pageHead('Dashboard', true);
?>
<div class="d-flex">
<?php adminNav('dashboard'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)">
      <i class="bi bi-list fs-5"></i>
    </button>
    <span class="page-title">Dashboard</span>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= APP_URL ?>/leaderboard.html" target="_blank" class="btn btn-outline-ferrari btn-sm">
        <i class="bi bi-trophy me-1"></i>Leaderboard
      </a>
      <a href="<?= APP_URL ?>/" target="_blank" class="btn btn-ferrari btn-sm">
        <i class="bi bi-display me-1"></i>Kiosk App
      </a>
    </div>
  </div>

  <div class="admin-content">
    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <div class="stat-card red">
          <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
          <div class="stat-value"><?= $stats['total_participants'] ?></div>
          <div class="stat-label">Total Registered</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card green">
          <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
          <div class="stat-value"><?= $stats['registered_today'] ?></div>
          <div class="stat-label">Registered Today</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card blue">
          <div class="stat-icon"><i class="bi bi-flag-fill"></i></div>
          <div class="stat-value"><?= $stats['races'] ?></div>
          <div class="stat-label">Races Completed</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card gold">
          <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
          <div class="stat-value"><?= number_format($stats['top_score']) ?></div>
          <div class="stat-label">Top Race Score</div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="row g-3 mb-4">
      <div class="col-md-7">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-bar-chart-fill me-2 text-ferrari"></i>Registrations (Last 7 Days)
          </div>
          <div class="card-body">
            <div class="chart-container" style="height:220px">
              <canvas id="regChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-flag-fill me-2 text-ferrari"></i>Races Completed (Last 7 Days)
          </div>
          <div class="card-body">
            <div class="chart-container" style="height:220px">
              <canvas id="raceChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Journey progress -->
    <div class="card mb-4">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-signpost-split me-2 text-ferrari"></i>Journey Progress</span>
        <a href="<?= APP_URL ?>/admin/participants.php" class="btn btn-outline-ferrari btn-sm">All Participants</a>
      </div>
      <div class="card-body">
        <?php
        $steps = ['registered' => 'Registered', 'driver' => 'Driver Check', 'car' => 'Car Design',
                  'race' => 'Race', 'pit' => 'Pit Stop', 'complete' => 'Journey Complete'];
        $base  = max(1, $journey['registered']);
        foreach ($steps as $k => $label):
          $n = $journey[$k]; $pct = (int)round($n / $base * 100);
          $link = $k === 'registered' ? '' : '?stage=' . $k;
        ?>
        <a href="<?= APP_URL ?>/admin/participants.php<?= $link ?>" class="d-flex align-items-center gap-3 mb-2 text-decoration-none" style="color:inherit">
          <span class="eyebrow" style="width:170px;color:#0D1B3E"><?= $label ?></span>
          <div style="flex:1;height:10px;background:#EEF4FB">
            <div style="height:12px;width:<?= $pct ?>%;background:<?= $k === 'complete' ? '#1E8E3E' : ($k === 'registered' ? '#D40000' : '#1140D8') ?>"></div>
          </div>
          <span style="width:90px;text-align:right;font-size:.85rem"><b><?= number_format($n) ?></b> <span style="color:var(--text-muted)"><?= $pct ?>%</span></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-3">
    <!-- Top Race Scores -->
    <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-trophy me-2 text-ferrari"></i>Top Race Scores</span>
        <a href="<?= APP_URL ?>/leaderboard.html" target="_blank" class="btn btn-outline-ferrari btn-sm">Live Board</a>
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0">
          <thead>
            <tr><th>#</th><th>Driver</th><th>Code</th><th class="text-end">Score</th></tr>
          </thead>
          <tbody>
            <?php if (!$topRacers): ?>
            <tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)">No races yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($topRacers as $i => $t): ?>
            <tr>
              <td class="fw-600"><?= $i + 1 ?></td>
              <td class="fw-600"><?= sanitize($t['name']) ?></td>
              <td><code style="font-size:.8rem;color:#0096D6;letter-spacing:.08em"><?= sanitize($t['code']) ?></code></td>
              <td class="text-end fw-600"><?= number_format((int)$t['score']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    </div>

    <!-- Recent Registrations -->
    <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2 text-ferrari"></i>Recent Registrations</span>
        <a href="<?= APP_URL ?>/admin/participants.php" class="btn btn-outline-ferrari btn-sm">All Participants</a>
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0">
          <thead>
            <tr><th>Name</th><th>Unique ID</th><th>Stage</th><th>Registered</th></tr>
          </thead>
          <tbody>
            <?php if (!$recent): ?>
            <tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)">No registrations yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="fw-600"><?= sanitize($r['name']) ?></td>
              <td class="id-cell">
                <code style="font-size:.8rem;color:#0096D6;letter-spacing:.08em"><?= sanitize($r['access_code']) ?></code>
                <button class="btn btn-sm p-0 ms-1" style="color:var(--text-muted)"
                        data-copy="<?= sanitize($r['access_code']) ?>" title="Copy unique ID">
                  <i class="bi bi-copy" style="font-size:.8rem"></i>
                </button>
              </td>
              <td><?= stageBadge(participantFlags($r)) ?></td>
              <td style="color:var(--text-muted);font-size:.85rem;white-space:nowrap"><?= timeAgo($r['registered_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    </div>
    </div>
  </div>
</div>
</div>

<script>
// Chart.js is loaded by pageFoot() below, so build the charts once the page has parsed.
document.addEventListener('DOMContentLoaded', () => {
const dayLabels = <?= json_encode($dayLabels) ?>;
const regData   = <?= json_encode(array_column(array_values($days), 'reg')) ?>;
const raceData  = <?= json_encode(array_column(array_values($days), 'races')) ?>;

Chart.defaults.color = '#5B6B7F';
Chart.defaults.font.family = "'forma-djr-text', system-ui, sans-serif";

const barOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: '#E3E9F2' }, ticks: { color: '#5B6B7F' } },
    y: { grid: { color: '#E3E9F2' }, ticks: { color: '#5B6B7F', precision: 0 }, beginAtZero: true },
  }
};

new Chart(document.getElementById('regChart'), {
  type: 'bar',
  data: { labels: dayLabels, datasets: [{
    label: 'Registrations', data: regData,
    backgroundColor: '#D40000', borderRadius: 0,
  }] },
  options: barOpts
});

new Chart(document.getElementById('raceChart'), {
  type: 'bar',
  data: { labels: dayLabels, datasets: [{
    label: 'Races', data: raceData,
    backgroundColor: '#1140D8', borderRadius: 0,
  }] },
  options: barOpts
});
});
</script>
<?php pageFoot(charts: true); ?>
