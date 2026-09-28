<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();
ensureKioskSchema($pdo);

// Summary numbers — real kiosk data only (registrations + race results)
$summary = [
    'total'     => (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
    'today'     => (int)$pdo->query('SELECT COUNT(*) FROM participants WHERE registered_at >= CURDATE()')->fetchColumn(),
    'races'     => (int)$pdo->query('SELECT COUNT(*) FROM race_scores')->fetchColumn(),
    'racers'    => (int)$pdo->query('SELECT COUNT(DISTINCT code) FROM race_scores')->fetchColumn(),
    'avg_score' => (int)round((float)$pdo->query('SELECT COALESCE(AVG(score), 0) FROM race_scores')->fetchColumn()),
    'top_score' => (int)$pdo->query('SELECT COALESCE(MAX(score), 0) FROM race_scores')->fetchColumn(),
];

// Registrations and races per day (last 30 days, zero-filled)
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['reg' => 0, 'races' => 0];
}
foreach ($pdo->query("SELECT DATE(registered_at) AS day, COUNT(*) AS cnt FROM participants
                      WHERE registered_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(registered_at)") as $r) {
    if (isset($days[$r['day']])) $days[$r['day']]['reg'] = (int)$r['cnt'];
}
foreach ($pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM race_scores
                      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at)") as $r) {
    if (isset($days[$r['day']])) $days[$r['day']]['races'] = (int)$r['cnt'];
}
$dayLabels = array_map(static fn($d) => date('j M', strtotime($d)), array_keys($days));

// Race activity by hour of day (today)
$byHour = array_fill(0, 24, 0);
foreach ($pdo->query("SELECT HOUR(created_at) AS h, COUNT(*) AS cnt FROM race_scores
                      WHERE created_at >= CURDATE() GROUP BY HOUR(created_at)") as $r) {
    $byHour[(int)$r['h']] = (int)$r['cnt'];
}

// City distribution
$byCity = $pdo->query("
    SELECT COALESCE(NULLIF(city,''),'Unknown') AS city, COUNT(*) AS cnt
    FROM participants GROUP BY COALESCE(NULLIF(city,''),'Unknown') ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// Best race score per driver
$bestSql = "
    SELECT b.code, b.name, b.score, b.created_at, p.city, x.races
    FROM (
        SELECT code, name, score, created_at,
               ROW_NUMBER() OVER (PARTITION BY code ORDER BY score DESC, created_at ASC) AS rn
        FROM race_scores
    ) b
    JOIN (SELECT code, COUNT(*) AS races FROM race_scores GROUP BY code) x ON x.code = b.code
    LEFT JOIN participants p ON p.access_code = b.code
    WHERE b.rn = 1
    ORDER BY b.score DESC, b.created_at ASC";
$top10 = $pdo->query($bestSql . ' LIMIT 10')->fetchAll();

// Most recent races
$recentRaces = $pdo->query('SELECT code, name, race_num, score, created_at FROM race_scores ORDER BY created_at DESC, id DESC LIMIT 15')->fetchAll();

// Export CSV handlers
if (isset($_GET['export'])) {
    $export = $_GET['export'];
    if ($export === 'participants') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="participants-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Unique ID','Name','Email','Phone','City','Races','Best Score','Registered At']);
        $all = $pdo->query("
            SELECT p.id, p.access_code, p.name, p.email, p.phone, p.city, p.registered_at,
                   COUNT(rs.id) AS races, MAX(rs.score) AS best
            FROM participants p
            LEFT JOIN race_scores rs ON rs.code = p.access_code
            GROUP BY p.id, p.access_code, p.name, p.email, p.phone, p.city, p.registered_at
            ORDER BY p.registered_at
        ")->fetchAll();
        foreach ($all as $row) {
            fputcsv($out, [$row['id'],$row['access_code'],$row['name'],$row['email'],$row['phone'],$row['city'],(int)$row['races'],$row['best'] ?? '',$row['registered_at']]);
        }
        fclose($out);
        exit;
    }
    if ($export === 'races') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="race-results-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Unique ID','Driver','Car #','Score','Raced At']);
        foreach ($pdo->query('SELECT code, name, race_num, score, created_at FROM race_scores ORDER BY created_at') as $r) {
            fputcsv($out, [$r['code'],$r['name'],$r['race_num'],$r['score'],$r['created_at']]);
        }
        fclose($out);
        exit;
    }
}

pageHead('Reports', true);
?>
<div class="d-flex">
<?php adminNav('reports'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Reports &amp; Analytics</span>
    <div class="ms-auto d-flex gap-2">
      <a href="?export=participants" class="btn btn-outline-ferrari btn-sm">
        <i class="bi bi-download me-1"></i>Participants CSV
      </a>
      <a href="?export=races" class="btn btn-outline-ferrari btn-sm">
        <i class="bi bi-download me-1"></i>Race Results CSV
      </a>
    </div>
  </div>

  <div class="admin-content">
    <!-- Summary stats row -->
    <div class="row g-3 mb-4">
      <?php
      $statItems = [
        ['Total Registered', $summary['total'],     'bi-people-fill',       'red'],
        ['Registered Today', $summary['today'],     'bi-person-check-fill', 'green'],
        ['Races Completed',  $summary['races'],     'bi-flag-fill',         'blue'],
        ['Drivers Raced',    $summary['racers'],    'bi-person-badge',      'gold'],
        ['Average Score',    $summary['avg_score'], 'bi-speedometer2',      'blue'],
        ['Top Score',        $summary['top_score'], 'bi-trophy-fill',       'gold'],
      ];
      foreach ($statItems as [$label, $val, $icon, $color]): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card <?= $color ?>">
          <div class="stat-icon"><i class="bi <?= $icon ?>"></i></div>
          <div class="stat-value"><?= number_format($val) ?></div>
          <div class="stat-label"><?= $label ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts row 1 -->
    <div class="row g-3 mb-3">
      <div class="col-md-8">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-bar-chart-line me-2 text-ferrari"></i>Registrations &amp; Races (30 Days)
          </div>
          <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="regTrend"></canvas></div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-geo-alt me-2 text-ferrari"></i>Participants by City
          </div>
          <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="cityChart"></canvas></div></div>
        </div>
      </div>
    </div>

    <!-- Charts row 2 -->
    <div class="row g-3 mb-3">
      <div class="col-12">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-clock me-2 text-ferrari"></i>Races by Hour (Today)
          </div>
          <div class="card-body"><div class="chart-container" style="height:200px"><canvas id="hourChart"></canvas></div></div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Top 10 -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
            <span><i class="bi bi-trophy-fill me-2 text-ferrari"></i>Top 10 Drivers</span>
            <a href="<?= APP_URL ?>/leaderboard.html" target="_blank" class="btn btn-outline-ferrari btn-sm">Live Board</a>
          </div>
          <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
              <thead><tr><th>Rank</th><th>Driver</th><th>City</th><th>Races</th><th class="text-end">Best</th></tr></thead>
              <tbody>
                <?php if (!$top10): ?>
                <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">No races yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($top10 as $i => $e): ?>
                <tr>
                  <td><?= getRankBadge($i + 1) ?></td>
                  <td class="fw-600"><?= sanitize($e['name']) ?>
                    <div><code style="font-size:.75rem;color:#0096D6;letter-spacing:.08em"><?= sanitize($e['code']) ?></code></div>
                  </td>
                  <td style="color:var(--text-muted)"><?= sanitize($e['city'] ?: '–') ?></td>
                  <td><?= (int)$e['races'] ?></td>
                  <td class="text-end"><span class="score-pill"><?= number_format((int)$e['score']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent races -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-clock-history me-2 text-ferrari"></i>Latest Races
          </div>
          <div class="table-responsive">
            <table class="table table-dark-custom mb-0">
              <thead><tr><th>Driver</th><th>Unique ID</th><th class="text-end">Score</th><th>When</th></tr></thead>
              <tbody>
                <?php if (!$recentRaces): ?>
                <tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)">No races yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentRaces as $r): ?>
                <tr>
                  <td class="fw-600"><?= sanitize($r['name']) ?></td>
                  <td><code style="font-size:.8rem;color:#0096D6;letter-spacing:.08em"><?= sanitize($r['code']) ?></code></td>
                  <td class="text-end fw-600"><?= number_format((int)$r['score']) ?></td>
                  <td style="color:var(--text-muted);font-size:.85rem"><?= timeAgo($r['created_at']) ?></td>
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
Chart.defaults.color = '#888';
Chart.defaults.font.family = 'Inter';

const palette = {
  red:    '#DC0000',
  gold:   '#C8A84B',
  blue:   '#0096D6',
  gridLine: 'rgba(13,27,62,.09)',
};
const axes = {
  x:{grid:{color:palette.gridLine},ticks:{color:'#888'}},
  y:{grid:{color:palette.gridLine},ticks:{color:'#888',precision:0},beginAtZero:true},
};

// Registrations & races trend
new Chart('regTrend', {
  type: 'line',
  data: {
    labels: <?= json_encode($dayLabels) ?>,
    datasets: [{
      label: 'Registrations',
      data:  <?= json_encode(array_column(array_values($days), 'reg')) ?>,
      borderColor: palette.red, backgroundColor: 'rgba(220,0,0,.10)',
      fill: true, tension: .35, pointRadius: 2,
    }, {
      label: 'Races',
      data:  <?= json_encode(array_column(array_values($days), 'races')) ?>,
      borderColor: palette.blue, backgroundColor: 'rgba(0,150,214,.10)',
      fill: true, tension: .35, pointRadius: 2,
    }]
  },
  options: { responsive:true, maintainAspectRatio:false,
    plugins:{legend:{labels:{color:'#888',font:{size:11}}}},
    scales: axes,
  }
});

// Races by hour (today)
new Chart('hourChart', {
  type: 'bar',
  data:{
    labels: <?= json_encode(array_map(static fn($h) => sprintf('%02d:00', $h), range(0, 23))) ?>,
    datasets:[{ label:'Races', data:<?= json_encode($byHour) ?>, backgroundColor:'rgba(0,150,214,.7)', borderRadius:4 }]
  },
  options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales: axes }
});

// City chart
new Chart('cityChart', {
  type: 'bar',
  data:{
    labels:<?= json_encode(array_column($byCity,'city')) ?>,
    datasets:[{
      label:'Participants',
      data:<?= json_encode(array_map('intval',array_column($byCity,'cnt'))) ?>,
      backgroundColor:'rgba(200,168,75,.7)',
      borderRadius:4,
    }]
  },
  options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      x:{grid:{color:palette.gridLine},ticks:{color:'#888',precision:0},beginAtZero:true},
      y:{grid:{color:palette.gridLine},ticks:{color:'#888'}},
    }
  }
});
});
</script>
<?php pageFoot(charts: true); ?>
