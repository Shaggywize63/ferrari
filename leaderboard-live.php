<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();

$entries = $pdo->query('SELECT * FROM v_leaderboard ORDER BY overall_rank LIMIT 100')->fetchAll();

$p1   = $entries[0] ?? null;
$p23  = array_slice($entries, 1, 2);
$rows = array_slice($entries, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Race Leaderboard · Pit Lane Experience</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
<style>
  html, body { margin:0; height:100%; background:#0B0B0C; overflow:hidden; color:#F4F2EE; }
  * { box-sizing:border-box; }
  body { font-family:'forma-djr-text','Forma DJR Text',system-ui,sans-serif; display:flex; flex-direction:column; }

  header {
    display:flex; align-items:center; justify-content:space-between;
    padding:clamp(10px,1.2vw,18px) clamp(16px,2.5vw,40px);
    border-bottom:1px solid rgba(244,242,238,.1);
    flex-shrink:0;
  }
  .hdr-brand { display:flex; align-items:center; gap:12px; }
  .hdr-brand-name { font-size:clamp(13px,1.1vw,16px); font-weight:500; letter-spacing:.18em; text-transform:uppercase; }
  .hdr-title { font-size:clamp(12px,1vw,14px); letter-spacing:.22em; text-transform:uppercase; color:#D40000; }
  .live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#28a745; margin-right:6px; animation:pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
  .hdr-actions { display:flex; gap:10px; }
  .btn-hdr {
    height:42px; padding:0 16px; border:1px solid rgba(244,242,238,.24);
    background:transparent; color:#F4F2EE; font-size:12px; letter-spacing:.18em;
    text-transform:uppercase; cursor:pointer; border-radius:4px; transition:background .2s;
  }
  .btn-hdr:hover { background:rgba(255,255,255,.08); }

  main { flex:1; display:grid; grid-template-columns:minmax(0,5fr) minmax(0,7fr); gap:clamp(20px,3vw,48px); padding:clamp(16px,2vw,32px) clamp(16px,2.5vw,40px); min-height:0; overflow:hidden; }

  /* Left: top-3 podium */
  .podium { display:flex; flex-direction:column; gap:clamp(12px,1.5vw,24px); min-height:0; }
  .p1-card {
    flex:1; background:#151517; border:1px solid rgba(244,242,238,.12);
    border-top:3px solid var(--ferrari-gold,#C8A84B);
    border-radius:8px; padding:clamp(16px,2vw,32px); display:flex; flex-direction:column; justify-content:flex-end; position:relative; overflow:hidden;
  }
  .p1-num {
    position:absolute; right:-.04em; bottom:-.2em;
    font-size:clamp(140px,16vw,280px); line-height:.85; font-weight:700;
    letter-spacing:-.04em; opacity:.07; color:#F4F2EE; pointer-events:none;
  }
  .p1-rank { font-size:13px; letter-spacing:.22em; text-transform:uppercase; color:#D40000; margin-bottom:6px; }
  .p1-name { font-size:clamp(28px,3.5vw,64px); line-height:.9; font-weight:500; letter-spacing:-.03em; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .p1-score { font-size:clamp(40px,5vw,96px); line-height:.85; font-weight:500; letter-spacing:-.04em; color:var(--ferrari-gold,#C8A84B); }

  .p23-grid { display:grid; grid-template-columns:1fr 1fr; gap:clamp(8px,.8vw,14px); }
  .p23-card {
    background:#151517; border:1px solid rgba(244,242,238,.12); border-radius:8px;
    padding:clamp(12px,1.5vw,24px);
  }
  .p23-rank { font-size:12px; letter-spacing:.18em; text-transform:uppercase; color:#D40000; margin-bottom:4px; }
  .p23-name { font-size:clamp(16px,1.8vw,28px); font-weight:500; letter-spacing:-.02em; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .p23-score { font-size:clamp(22px,2.8vw,52px); line-height:.85; font-weight:500; letter-spacing:-.03em; }

  /* Right: rows list */
  .rows-section { display:flex; flex-direction:column; min-height:0; }
  .rows-header { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:0; padding:0 clamp(8px,1vw,16px) 8px; }
  .rows-header span { font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:#5f5c58; }
  .rows-header .col-score { text-align:right; }
  .rows-list { display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden; gap:4px; }
  .row-item {
    flex:1; max-height:88px; min-height:48px; display:grid;
    grid-template-columns:minmax(48px,6%) minmax(0,1fr) auto;
    align-items:center; gap:12px;
    border-radius:6px; padding:0 clamp(8px,1vw,16px);
    background:#151517; border:1px solid rgba(244,242,238,.06);
  }
  .row-rank { font-size:clamp(18px,1.8vw,32px); font-weight:500; letter-spacing:-.02em; color:#D40000; padding-left:2px; }
  .row-name { font-size:clamp(14px,1.3vw,22px); font-weight:500; letter-spacing:-.01em; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .row-score { font-size:clamp(16px,1.6vw,28px); font-weight:500; letter-spacing:-.02em; text-align:right; color:#A7A39C; }

  .empty { color:#5f5c58; text-align:center; padding:3rem; font-size:1rem; letter-spacing:.1em; text-transform:uppercase; }
</style>
</head>
<body>

<header>
  <div class="hdr-brand">
    <div>
      <div class="hdr-brand-name">Pit Lane Experience</div>
      <div class="hdr-title">Powered by HP</div>
    </div>
  </div>
  <div style="font-size:13px;letter-spacing:.14em;text-transform:uppercase">
    <span class="live-dot"></span> Live
  </div>
  <div class="hdr-actions">
    <a href="<?= APP_URL ?>/leaderboard.php" style="color:#F4F2EE;text-decoration:none">
      <button class="btn-hdr">Full Table</button>
    </a>
    <button class="btn-hdr" id="fsBtn" onclick="toggleFs()">Fullscreen</button>
  </div>
</header>

<main>
  <!-- Left: top 3 -->
  <div class="podium">
    <?php if ($p1): ?>
    <div class="p1-card">
      <span class="p1-num"><?= $p1['overall_rank'] ?></span>
      <div class="p1-rank">1st place</div>
      <div class="p1-name"><?= htmlspecialchars($p1['name']) ?></div>
      <div class="p1-score"><?= number_format($p1['total_score'],1) ?></div>
    </div>
    <?php else: ?>
    <div class="p1-card"><div class="empty">Waiting for scores…</div></div>
    <?php endif; ?>

    <div class="p23-grid">
      <?php foreach ([0,1] as $i): ?>
      <?php $p = $p23[$i] ?? null; ?>
      <div class="p23-card">
        <?php if ($p): ?>
        <div class="p23-rank"><?= $p['overall_rank'] === 2 ? '2nd' : '3rd' ?> place</div>
        <div class="p23-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="p23-score"><?= number_format($p['total_score'],1) ?></div>
        <?php else: ?>
        <div style="color:#333;font-size:.8rem;padding:.5rem">–</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right: rows 4+ -->
  <div class="rows-section">
    <div class="rows-header">
      <span>Participant</span>
      <span></span>
      <span class="col-score">Score</span>
    </div>
    <?php if ($rows): ?>
    <div class="rows-list">
      <?php foreach ($rows as $r): ?>
      <div class="row-item">
        <span class="row-rank"><?= $r['overall_rank'] ?></span>
        <span class="row-name"><?= htmlspecialchars($r['name']) ?></span>
        <span class="row-score"><?= number_format($r['total_score'],1) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty">Scores will appear here</div>
    <?php endif; ?>
  </div>
</main>

<script>
function toggleFs() {
  const btn = document.getElementById('fsBtn');
  if (!document.fullscreenElement && !document.webkitFullscreenElement) {
    const el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    btn.textContent = 'Exit Fullscreen';
  } else {
    if (document.exitFullscreen) document.exitFullscreen();
    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
    btn.textContent = 'Fullscreen';
  }
}
['fullscreenchange','webkitfullscreenchange'].forEach(e => document.addEventListener(e, () => {
  if (!document.fullscreenElement && !document.webkitFullscreenElement)
    document.getElementById('fsBtn').textContent = 'Fullscreen';
}));

// Auto-refresh data every 30 seconds
setInterval(() => location.reload(), 30000);
</script>
</body>
</html>
