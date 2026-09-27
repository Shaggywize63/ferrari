<?php
function pageHead(string $title, bool $isAdmin = false): void {
    $base = APP_URL;
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} – A Pit Lane of Ferrari</title>
<link rel="stylesheet" href="https://use.typekit.net/uef7mgf.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{$base}/assets/css/main.css">
</head>
<body>
HTML;
}

function pageFoot(bool $charts = false): void {
    $base = APP_URL;
    $chartScript = $charts
        ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>'
        : '';
    echo <<<HTML
{$chartScript}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{$base}/assets/js/main.js"></script>
</body>
</html>
HTML;
}

function adminNav(string $active = ''): void {
    $admin = currentAdmin();
    $base  = APP_URL;
    $items = [
        'dashboard'    => ['Dashboard',     'bi-speedometer2',   '/admin/index.php'],
        'participants' => ['Participants',   'bi-people-fill',    '/admin/participants.php'],
        'rounds'       => ['Rounds',         'bi-trophy-fill',    '/admin/rounds.php'],
        'scores'       => ['Scores',         'bi-123',            '/admin/scores.php'],
        'scanner'      => ['Check-in',        'bi-person-check-fill', '/admin/scanner.php'],
        'reports'      => ['Reports',        'bi-bar-chart-fill', '/admin/reports.php'],
    ];
    $initials = strtoupper(substr($admin['name'], 0, 1));
    $links = '';
    foreach ($items as $key => [$label, $icon, $href]) {
        $cls = $active === $key ? 'active' : '';
        $links .= <<<HTML
<li class="nav-item">
  <a class="nav-link {$cls}" href="{$base}{$href}">
    <i class="bi {$icon} me-2"></i>{$label}
  </a>
</li>
HTML;
    }
    echo <<<HTML
<nav class="admin-sidebar d-flex flex-column">
  <div class="sidebar-brand">
    <div class="ferrari-logo">🏎</div>
    <div>
      <div class="brand-name">Pit Lane</div>
      <div class="brand-sub">Admin Portal</div>
    </div>
  </div>
  <ul class="nav flex-column flex-grow-1 px-2">
    {$links}
  </ul>
  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <div class="avatar">{$initials}</div>
      <div>
        <div class="fw-600 small">{$admin['name']}</div>
        <div class="text-muted" style="font-size:.75rem">{$admin['role']}</div>
      </div>
      <a href="{$base}/admin/logout.php" class="ms-auto text-muted" title="Logout">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>
HTML;
}
