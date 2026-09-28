<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();

// Stats
$stats = [
    'total_participants' => (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
    'active_participants'=> (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE status='active'")->fetchColumn(),
    'total_rounds'       => (int)$pdo->query('SELECT COUNT(*) FROM rounds')->fetchColumn(),
    'active_rounds'      => (int)$pdo->query("SELECT COUNT(*) FROM rounds WHERE status='active'")->fetchColumn(),
    'scores_entered'     => (int)$pdo->query("SELECT COUNT(*) FROM participant_rounds WHERE status='scored'")->fetchColumn(),
];

// Recent registrations
$recent = $pdo->query('SELECT name, email, city, access_code, registered_at FROM participants ORDER BY registered_at DESC LIMIT 10')->fetchAll();

// Registrations by day (last 7 days)
$regByDay = $pdo->query("
    SELECT DATE(registered_at) AS day, COUNT(*) AS cnt
    FROM participants
    WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(registered_at)
    ORDER BY day
")->fetchAll();

// Score distribution by round
$scoreByRound = $pdo->query("
    SELECT r.name AS round_name, AVG(pr.score) AS avg_score, MAX(pr.score) AS max_score, COUNT(pr.id) AS participants
    FROM participant_rounds pr
    JOIN rounds r ON r.id = pr.round_id
    WHERE pr.status = 'scored'
    GROUP BY pr.round_id, r.name
    ORDER BY r.round_number
")->fetchAll();

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
      <a href="<?= APP_URL ?>/leaderboard.php" target="_blank" class="btn btn-outline-ferrari btn-sm">
        <i class="bi bi-trophy me-1"></i>Leaderboard
      </a>
      <a href="<?= APP_URL ?>/register.php" target="_blank" class="btn btn-ferrari btn-sm">
        <i class="bi bi-person-plus me-1"></i>Register
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
          <div class="stat-value"><?= $stats['active_participants'] ?></div>
          <div class="stat-label">Active Participants</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card gold">
          <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
          <div class="stat-value"><?= $stats['total_rounds'] ?></div>
          <div class="stat-label">Total Rounds</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card blue">
          <div class="stat-icon"><i class="bi bi-123"></i></div>
          <div class="stat-value"><?= $stats['scores_entered'] ?></div>
          <div class="stat-label">Scores Entered</div>
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
            <i class="bi bi-graph-up-arrow me-2 text-ferrari"></i>Avg Score by Round
          </div>
          <div class="card-body">
            <div class="chart-container" style="height:220px">
              <canvas id="scoreChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Registrations -->
    <div class="card">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2 text-ferrari"></i>Recent Registrations</span>
        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline-ferrari btn-sm">View Reports</a>
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0">
          <thead>
            <tr><th>Name</th><th>Email</th><th>City</th><th>Access Code</th><th>Registered</th></tr>
          </thead>
          <tbody>
            <?php if (!$recent): ?>
            <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">No registrations yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="fw-600"><?= sanitize($r['name']) ?></td>
              <td style="color:var(--text-muted)"><?= sanitize($r['email']) ?></td>
              <td style="color:var(--text-muted)"><?= sanitize($r['city'] ?: '–') ?></td>
              <td>
                <code style="font-size:.8rem;color:var(--ferrari-red);letter-spacing:.08em"><?= sanitize($r['access_code']) ?></code>
                <button class="btn btn-sm p-0 ms-1" style="color:var(--text-muted)"
                        data-copy="<?= sanitize($r['access_code']) ?>" title="Copy access code">
                  <i class="bi bi-copy" style="font-size:.8rem"></i>
                </button>
              </td>
              <td style="color:var(--text-muted);font-size:.85rem"><?= timeAgo($r['registered_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<script>
const regLabels = <?= json_encode(array_column($regByDay, 'day')) ?>;
const regData   = <?= json_encode(array_map('intval', array_column($regByDay, 'cnt'))) ?>;
const rndLabels = <?= json_encode(array_column($scoreByRound, 'round_name')) ?>;
const avgScores = <?= json_encode(array_map('floatval', array_column($scoreByRound, 'avg_score'))) ?>;
const maxScores = <?= json_encode(array_map('floatval', array_column($scoreByRound, 'max_score'))) ?>;

const chartDefaults = {
  color: '#888',
  font: { family: 'Inter', size: 12 },
};
Chart.defaults.color = '#888';
Chart.defaults.font.family = 'Inter';

new Chart(document.getElementById('regChart'), {
  type: 'bar',
  data: {
    labels: regLabels,
    datasets: [{
      label: 'Registrations',
      data: regData,
      backgroundColor: 'rgba(220,0,0,.7)',
      borderColor: '#DC0000',
      borderWidth: 1,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(13,27,62,.09)' }, ticks: { color: '#888' } },
      y: { grid: { color: 'rgba(13,27,62,.09)' }, ticks: { color: '#888', precision: 0 }, beginAtZero: true },
    }
  }
});

new Chart(document.getElementById('scoreChart'), {
  type: 'bar',
  data: {
    labels: rndLabels,
    datasets: [
      { label: 'Avg Score', data: avgScores, backgroundColor: 'rgba(200,168,75,.7)', borderRadius: 6 },
      { label: 'Max Score', data: maxScores, backgroundColor: 'rgba(220,0,0,.5)', borderRadius: 6 },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#888', font: {size:11} } } },
    scales: {
      x: { grid: { color: 'rgba(13,27,62,.09)' }, ticks: { color: '#888' } },
      y: { grid: { color: 'rgba(13,27,62,.09)' }, ticks: { color: '#888' }, beginAtZero: true },
    }
  }
});
</script>
<?php pageFoot(charts: true); ?>
