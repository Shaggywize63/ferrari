<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();
ensureKioskSchema($pdo);

// Filters: free-text search + journey stage
$q     = trim((string)($_GET['q'] ?? ''));
$stage = (string)($_GET['stage'] ?? '');
$stageFilters = [
    ''         => 'All stages',
    'none'     => 'Registered only',
    'driver'   => 'Driver Check done',
    'car'      => 'Car Design done',
    'race'     => 'Race done',
    'pit'      => 'Pit Stop done',
    'complete' => 'Journey complete',
];
if (!isset($stageFilters[$stage])) $stage = '';

$race = '(k.race_done = 1 OR s.races IS NOT NULL)';
$stageSql = [
    'none'     => "COALESCE(k.driver_done,0) = 0 AND COALESCE(k.car_done,0) = 0 AND COALESCE(k.pit_done,0) = 0 AND NOT $race",
    'driver'   => 'k.driver_done = 1',
    'car'      => 'k.car_done = 1',
    'race'     => $race,
    'pit'      => 'k.pit_done = 1',
    'complete' => "k.driver_done = 1 AND k.car_done = 1 AND k.pit_done = 1 AND $race",
];

$where = [];
$args  = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.access_code LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    array_push($args, $like, $like, $like, $like);
}
if ($stage !== '') {
    $where[] = $stageSql[$stage];
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// CSV of the filtered list
if (($_GET['export'] ?? '') === 'csv') {
    $st = $pdo->prepare(PARTICIPANT_JOURNEY_SQL . $whereSql . ' ORDER BY p.registered_at');
    $st->execute($args);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="participants-journey-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Unique ID', 'Name', 'Mobile', 'Email', 'City', 'Registered At',
                   'Driver Check', 'Driver Type', 'Car Design', 'Race', 'Best Race Score', 'Races',
                   'Pit Stop', 'Pit Stop Score', 'Stage', 'Last Activity']);
    foreach ($st as $r) {
        $f = participantFlags($r);
        fputcsv($out, [
            $r['access_code'], $r['name'], $r['phone'], $r['email'], $r['city'] ?? '', $r['registered_at'],
            $f['driver'] ? 'Yes' : 'No', $r['tag'] ?? '', $f['car'] ? 'Yes' : 'No', $f['race'] ? 'Yes' : 'No',
            max((int)$r['best'], (int)$r['race_score']) ?: '', (int)$r['races'],
            $f['pit'] ? 'Yes' : 'No', $r['pit_score'] ?? '', journeyStage($f)['label'],
            max((string)$r['last_seen'], (string)$r['last_race'], (string)$r['registered_at']),
        ]);
    }
    fclose($out);
    exit;
}

$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));
$ct = $pdo->prepare('SELECT COUNT(*) FROM (' . PARTICIPANT_JOURNEY_SQL . $whereSql . ') x');
$ct->execute($args);
$total = (int)$ct->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);

$st = $pdo->prepare(PARTICIPANT_JOURNEY_SQL . $whereSql . ' ORDER BY p.registered_at DESC, p.id DESC LIMIT '
    . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
$st->execute($args);
$rows = $st->fetchAll();

$counts = journeyCounts($pdo);
$qs = static fn(array $over) => '?' . http_build_query(array_filter(
    array_merge(['q' => $q, 'stage' => $stage, 'page' => $page], $over),
    static fn($v) => $v !== '' && $v !== null
));
$tick = static fn(bool $done, string $extra = '') => $done
    ? '<span style="color:#1E8E3E;font-weight:600"><i class="bi bi-check-circle-fill me-1"></i>' . $extra . '</span>'
    : '<span style="color:#B7C1CE"><i class="bi bi-circle"></i></span>';

pageHead('Participants', true);
?>
<div class="d-flex">
<?php adminNav('participants'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Participants</span>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= sanitize($qs(['export' => 'csv', 'page' => null])) ?>" class="btn btn-outline-ferrari btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
      </a>
    </div>
  </div>

  <div class="admin-content">
    <!-- Journey funnel: click a stage to filter -->
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['',         'Registered',   $counts['registered'], 'bi-people-fill',       'red'],
        ['driver',   'Driver Check', $counts['driver'],     'bi-person-bounding-box','blue'],
        ['car',      'Car Design',   $counts['car'],        'bi-palette-fill',      'gold'],
        ['race',     'Race',         $counts['race'],       'bi-flag-fill',         'blue'],
        ['pit',      'Pit Stop',     $counts['pit'],        'bi-tools',             'gold'],
        ['complete', 'Complete',     $counts['complete'],   'bi-trophy-fill',       'green'],
      ];
      foreach ($cards as [$key, $label, $val, $icon, $color]): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= sanitize($qs(['stage' => $key, 'page' => null])) ?>" class="text-decoration-none">
          <div class="stat-card <?= $color ?>" style="<?= $stage === $key ? 'outline:2px solid #0096D6' : '' ?>">
            <div class="stat-icon"><i class="bi <?= $icon ?>"></i></div>
            <div class="stat-value"><?= number_format($val) ?></div>
            <div class="stat-label"><?= $label ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card-header px-4 py-3">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
          <input type="search" name="q" value="<?= sanitize($q) ?>" class="form-control form-control-sm"
                 style="max-width:280px" placeholder="Search name, mobile, email or unique ID">
          <select name="stage" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
            <?php foreach ($stageFilters as $k => $label): ?>
            <option value="<?= $k ?>" <?= $stage === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-ferrari btn-sm"><i class="bi bi-search me-1"></i>Search</button>
          <?php if ($q !== '' || $stage !== ''): ?>
          <a href="?" class="btn btn-sm" style="color:var(--text-muted)">Clear</a>
          <?php endif; ?>
          <span class="ms-auto" style="color:var(--text-muted);font-size:.85rem"><?= number_format($total) ?> driver<?= $total === 1 ? '' : 's' ?></span>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-dark-custom mb-0 align-middle">
          <thead>
            <tr>
              <th>Driver</th><th>Contact</th><th>Registered</th>
              <th class="text-center">Driver Check</th><th class="text-center">Car Design</th>
              <th class="text-center">Race</th><th class="text-center">Pit Stop</th>
              <th>Stage</th><th>Last Activity</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-center py-4" style="color:var(--text-muted)">
              <?= $q !== '' || $stage !== '' ? 'No drivers match this filter.' : 'No registrations yet.' ?>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
              $f    = participantFlags($r);
              $best = max((int)$r['best'], (int)$r['race_score']);
              $last = max((string)$r['last_seen'], (string)$r['last_race'], (string)$r['registered_at']);
            ?>
            <tr>
              <td>
                <div class="fw-600"><?= sanitize($r['name']) ?></div>
                <code style="font-size:.8rem;color:#0096D6;letter-spacing:.08em"><?= sanitize($r['access_code']) ?></code>
                <button class="btn btn-sm p-0 ms-1" style="color:var(--text-muted)" data-copy="<?= sanitize($r['access_code']) ?>" title="Copy unique ID">
                  <i class="bi bi-copy" style="font-size:.75rem"></i>
                </button>
              </td>
              <td style="font-size:.85rem">
                <div><?= sanitize($r['phone'] ?: '–') ?></div>
                <div style="color:var(--text-muted)"><?= sanitize($r['email'] ?: '–') ?></div>
              </td>
              <td style="color:var(--text-muted);font-size:.85rem;white-space:nowrap"><?= date('j M, H:i', strtotime($r['registered_at'])) ?></td>
              <td class="text-center" style="font-size:.8rem"><?= $tick($f['driver'], sanitize((string)($r['tag'] ?? ''))) ?></td>
              <td class="text-center"><?= $tick($f['car']) ?></td>
              <td class="text-center" style="font-size:.85rem"><?= $tick($f['race'], $best ? number_format($best) : '') ?></td>
              <td class="text-center" style="font-size:.85rem"><?= $tick($f['pit'], $r['pit_score'] !== null ? number_format((int)$r['pit_score']) : '') ?></td>
              <td><?= stageBadge($f) ?></td>
              <td style="color:var(--text-muted);font-size:.85rem;white-space:nowrap"><?= timeAgo($last) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
      <div class="card-body d-flex justify-content-between align-items-center">
        <span style="color:var(--text-muted);font-size:.85rem">Page <?= $page ?> of <?= $pages ?></span>
        <div class="d-flex gap-2">
          <?php if ($page > 1): ?><a class="btn btn-outline-ferrari btn-sm" href="<?= sanitize($qs(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
          <?php if ($page < $pages): ?><a class="btn btn-outline-ferrari btn-sm" href="<?= sanitize($qs(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <p class="mt-3" style="color:var(--text-muted);font-size:.8rem">
      Stations tick off as drivers complete them at any kiosk. Drivers who registered before journey tracking was
      switched on show their races only, until their next station.
    </p>
  </div>
</div>
</div>
<?php pageFoot(); ?>
