<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();

// Summary numbers
$summary = [
    'total'      => (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
    'active'     => (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE status='active'")->fetchColumn(),
    'eliminated' => (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE status='eliminated'")->fetchColumn(),
    'winner'     => (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE status='winner'")->fetchColumn(),
    'scored'     => (int)$pdo->query("SELECT COUNT(*) FROM participant_rounds WHERE status='scored'")->fetchColumn(),
    'check_ins'  => (int)$pdo->query("SELECT COUNT(*) FROM participant_rounds WHERE checked_in_at IS NOT NULL")->fetchColumn(),
    'scans'      => (int)$pdo->query('SELECT COUNT(*) FROM qr_scan_logs')->fetchColumn(),
];

// Registrations per day (last 30 days)
$regPerDay = $pdo->query("
    SELECT DATE(registered_at) AS day, COUNT(*) AS cnt
    FROM participants WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(registered_at) ORDER BY day
")->fetchAll();

// Score distribution per round
$roundScores = $pdo->query("
    SELECT r.round_number, r.name, r.max_score,
           COUNT(pr.id) AS participants,
           COALESCE(AVG(pr.score),0) AS avg_score,
           COALESCE(MIN(pr.score),0) AS min_score,
           COALESCE(MAX(pr.score),0) AS max_score_val,
           SUM(CASE WHEN pr.status='scored' THEN 1 ELSE 0 END) AS scored_count
    FROM rounds r
    LEFT JOIN participant_rounds pr ON pr.round_id = r.id
    GROUP BY r.id, r.round_number, r.name, r.max_score
    ORDER BY r.round_number
")->fetchAll();

// Top 10 leaderboard
$top10 = $pdo->query('SELECT * FROM v_leaderboard ORDER BY overall_rank LIMIT 10')->fetchAll();

// City distribution
$byCity = $pdo->query("
    SELECT COALESCE(NULLIF(city,''),'Unknown') AS city, COUNT(*) AS cnt
    FROM participants GROUP BY city ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// Status donut data
$statusData = [$summary['active'], $summary['eliminated'], $summary['winner']];

// Participation funnel (per round)
$funnelData = $pdo->query("
    SELECT r.round_number, r.name, COUNT(pr.id) AS enrolled,
           SUM(CASE WHEN pr.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in,
           SUM(CASE WHEN pr.status='scored' THEN 1 ELSE 0 END) AS scored
    FROM rounds r
    LEFT JOIN participant_rounds pr ON pr.round_id=r.id
    GROUP BY r.id ORDER BY r.round_number
")->fetchAll();

// Export CSV handler
if (isset($_GET['export']) && $_GET['export'] === 'participants') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="participants-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Email','Phone','City','Team','Status','Total Score','Rounds Played','Registered At']);
    $all = $pdo->query('SELECT l.*, p.phone, p.registered_at FROM v_leaderboard l JOIN participants p ON p.id=l.id')->fetchAll();
    foreach ($all as $row) {
        fputcsv($out, [$row['id'],$row['name'],$row['email'],$row['phone'],$row['city'],$row['team_name'],$row['status'],round($row['total_score'],1),$row['rounds_participated'],$row['registered_at']]);
    }
    fclose($out);
    exit;
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
        <i class="bi bi-download me-1"></i>Export CSV
      </a>
    </div>
  </div>

  <div class="admin-content">
    <!-- Summary stats row -->
    <div class="row g-3 mb-4">
      <?php
      $statItems = [
        ['Total Registered', $summary['total'],      'bi-people-fill',       'red'],
        ['Active',           $summary['active'],     'bi-person-check-fill', 'green'],
        ['Eliminated',       $summary['eliminated'], 'bi-person-x-fill',     'red'],
        ['Winners',          $summary['winner'],     'bi-trophy-fill',       'gold'],
        ['Scores Entered',   $summary['scored'],     'bi-123',               'blue'],
        ['Total Check-ins',  $summary['check_ins'],  'bi-qr-code-scan',      'blue'],
        ['QR Scans Logged',  $summary['scans'],      'bi-camera',            'gold'],
      ];
      foreach ($statItems as [$label, $val, $icon, $color]): ?>
      <div class="col-6 col-md-3">
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
            <i class="bi bi-bar-chart-line me-2 text-ferrari"></i>Registration Trend (30 Days)
          </div>
          <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="regTrend"></canvas></div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-pie-chart me-2 text-ferrari"></i>Participant Status
          </div>
          <div class="card-body d-flex align-items-center justify-content-center">
            <div class="chart-container" style="height:200px;width:200px"><canvas id="statusDonut"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts row 2 -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-funnel me-2 text-ferrari"></i>Participation Funnel by Round
          </div>
          <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="funnelChart"></canvas></div></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header px-4 py-3">
            <i class="bi bi-geo-alt me-2 text-ferrari"></i>Participants by City
          </div>
          <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="cityChart"></canvas></div></div>
        </div>
      </div>
    </div>

    <!-- Round breakdown table -->
    <div class="card mb-3">
      <div class="card-header px-4 py-3">
        <i class="bi bi-table me-2 text-ferrari"></i>Round-by-Round Breakdown
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0">
          <thead>
            <tr><th>Round</th><th>Name</th><th>Max Score</th><th>Enrolled</th><th>Scored</th><th>Avg Score</th><th>Top Score</th><th>Completion</th></tr>
          </thead>
          <tbody>
            <?php foreach ($roundScores as $rs): ?>
            <tr>
              <td class="fw-700">R<?= $rs['round_number'] ?></td>
              <td><?= sanitize($rs['name']) ?></td>
              <td><?= $rs['max_score'] ?></td>
              <td><?= $rs['participants'] ?></td>
              <td><?= $rs['scored_count'] ?></td>
              <td><span class="score-pill"><?= round($rs['avg_score'],1) ?></span></td>
              <td><span class="score-pill"><?= round($rs['max_score_val'],1) ?></span></td>
              <td>
                <?php $pct = $rs['participants'] > 0 ? round($rs['scored_count']/$rs['participants']*100) : 0; ?>
                <div style="width:100%;background:rgba(255,255,255,.1);border-radius:4px;height:6px">
                  <div style="width:<?= $pct ?>%;background:var(--ferrari-red);height:6px;border-radius:4px"></div>
                </div>
                <small style="color:var(--text-muted)"><?= $pct ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top 10 -->
    <div class="card">
      <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-trophy-fill me-2 text-ferrari"></i>Top 10 Overall</span>
        <a href="<?= APP_URL ?>/leaderboard.php" target="_blank" class="btn btn-outline-ferrari btn-sm">Full Leaderboard</a>
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0">
          <thead><tr><th>Rank</th><th>Name</th><th>City</th><th>Team</th><th>Rounds</th><th class="text-end">Score</th></tr></thead>
          <tbody>
            <?php foreach ($top10 as $e): ?>
            <tr>
              <td><?= getRankBadge((int)$e['overall_rank']) ?></td>
              <td class="fw-600"><?= sanitize($e['name']) ?></td>
              <td style="color:var(--text-muted)"><?= sanitize($e['city'] ?: '–') ?></td>
              <td style="color:var(--text-muted)"><?= sanitize($e['team_name'] ?: '–') ?></td>
              <td><?= $e['rounds_participated'] ?></td>
              <td class="text-end"><span class="score-pill"><?= formatScore($e['total_score']) ?></span></td>
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
Chart.defaults.color = '#888';
Chart.defaults.font.family = 'Inter';

const palette = {
  red:    '#DC0000',
  gold:   '#C8A84B',
  blue:   '#0096D6',
  green:  '#28a745',
  silver: '#B0B0B0',
  gridLine: 'rgba(255,255,255,.06)',
};

// Registration trend
new Chart('regTrend', {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($regPerDay, 'day')) ?>,
    datasets: [{
      label: 'Registrations',
      data:  <?= json_encode(array_map('intval', array_column($regPerDay, 'cnt'))) ?>,
      borderColor: palette.red,
      backgroundColor: 'rgba(220,0,0,.12)',
      fill: true,
      tension: .4,
      pointBackgroundColor: palette.red,
      pointRadius: 4,
    }]
  },
  options: { responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      x:{grid:{color:palette.gridLine},ticks:{color:'#888'}},
      y:{grid:{color:palette.gridLine},ticks:{color:'#888',precision:0},beginAtZero:true},
    }
  }
});

// Status donut
new Chart('statusDonut', {
  type: 'doughnut',
  data: {
    labels: ['Active','Eliminated','Winner'],
    datasets:[{ data:<?= json_encode($statusData) ?>, backgroundColor:[palette.green,palette.red,palette.gold], borderWidth:0 }]
  },
  options:{ responsive:true, maintainAspectRatio:true,
    plugins:{legend:{position:'bottom',labels:{color:'#888',padding:12,font:{size:11}}}},
    cutout:'68%',
  }
});

// Funnel
const funnelLabels = <?= json_encode(array_map(fn($r) => 'R'.$r['round_number'].': '.$r['name'], $funnelData)) ?>;
new Chart('funnelChart', {
  type: 'bar',
  data:{
    labels: funnelLabels,
    datasets:[
      {label:'Enrolled',   data:<?= json_encode(array_map('intval',array_column($funnelData,'enrolled'))) ?>,   backgroundColor:'rgba(176,176,176,.4)', borderRadius:4},
      {label:'Checked In', data:<?= json_encode(array_map('intval',array_column($funnelData,'checked_in'))) ?>, backgroundColor:'rgba(0,150,214,.6)',   borderRadius:4},
      {label:'Scored',     data:<?= json_encode(array_map('intval',array_column($funnelData,'scored'))) ?>,     backgroundColor:'rgba(220,0,0,.7)',      borderRadius:4},
    ]
  },
  options:{ responsive:true, maintainAspectRatio:false,
    plugins:{legend:{labels:{color:'#888',font:{size:11}}}},
    scales:{
      x:{grid:{color:palette.gridLine},ticks:{color:'#888'}},
      y:{grid:{color:palette.gridLine},ticks:{color:'#888',precision:0},beginAtZero:true},
    }
  }
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
</script>
<?php pageFoot(charts: true); ?>
