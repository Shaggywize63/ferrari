<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();
$activeRound = $pdo->query("
    SELECT r.id, r.round_number, r.name
    FROM rounds r JOIN events e ON e.id=r.event_id
    WHERE r.status='active'
    ORDER BY r.round_number LIMIT 1
")->fetch();

pageHead('QR Scanner', true);
?>
<div class="d-flex">
<?php adminNav('scanner'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">QR Scanner</span>
    <?php if ($activeRound): ?>
    <div class="ms-auto">
      <span class="badge" style="background:rgba(220,0,0,.15);color:var(--ferrari-red);font-size:.85rem">
        <span class="status-dot active me-1"></span>
        Active: <?= sanitize($activeRound['name']) ?>
      </span>
    </div>
    <?php endif; ?>
  </div>

  <div class="admin-content">
    <div class="row g-4">
      <!-- Camera scanner -->
      <div class="col-md-6">
        <div class="card p-4 text-center">
          <h5 class="fw-700 mb-3"><i class="bi bi-camera-video me-2 text-ferrari"></i>Camera Scanner</h5>

          <?php if (!$activeRound): ?>
          <div class="alert-ferrari">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No active round. Please activate a round first.
          </div>
          <?php else: ?>
          <div style="position:relative;display:inline-block">
            <video id="scanner-video" autoplay playsinline muted></video>
            <canvas id="scanner-canvas" style="display:none"></canvas>
            <div id="scan-overlay" style="
              position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
              width:200px;height:200px;
              border:2px solid var(--ferrari-red);
              border-radius:8px;
              pointer-events:none;
              box-shadow:0 0 0 9999px rgba(0,0,0,.4);
            "></div>
          </div>
          <div class="mt-3 d-flex gap-2 justify-content-center">
            <button id="startCam" class="btn btn-ferrari">
              <i class="bi bi-camera-video me-1"></i>Start Camera
            </button>
            <button id="stopCam" class="btn btn-outline-ferrari" style="display:none">
              <i class="bi bi-stop-circle me-1"></i>Stop
            </button>
          </div>
          <?php endif; ?>

          <div id="scan-result" class="mt-3"></div>

          <!-- Scan log -->
          <div id="scan-log" class="mt-4 text-start" style="max-height:200px;overflow-y:auto"></div>
        </div>
      </div>

      <!-- Manual token entry -->
      <div class="col-md-6">
        <div class="card p-4">
          <h5 class="fw-700 mb-3"><i class="bi bi-keyboard me-2 text-ferrari"></i>Manual Token Entry</h5>
          <p style="color:var(--text-muted);font-size:.875rem">Enter a QR token or participant email to check them in.</p>

          <form id="manualForm">
            <div class="mb-3">
              <label class="form-label">QR Token or Email</label>
              <input type="text" id="manualToken" class="form-control" placeholder="Paste token or email…" autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">Round</label>
              <select id="manualRound" class="form-select">
                <?php
                $rounds = $pdo->query("SELECT r.id, r.round_number, r.name FROM rounds r JOIN events e ON e.id=r.event_id ORDER BY r.round_number")->fetchAll();
                foreach ($rounds as $r):
                ?>
                <option value="<?= $r['id'] ?>" <?= ($activeRound && $activeRound['id']==$r['id']) ? 'selected' : '' ?>>
                  R<?= $r['round_number'] ?>: <?= sanitize($r['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-ferrari w-100">
              <i class="bi bi-qr-code-scan me-1"></i>Check In
            </button>
          </form>
          <div id="manual-result" class="mt-3"></div>
        </div>

        <!-- Recent check-ins -->
        <div class="card mt-3 p-0">
          <div class="card-header px-3 py-2">
            <i class="bi bi-clock-history me-1 text-ferrari"></i>Recent Check-ins
          </div>
          <div class="table-responsive" style="max-height:300px;overflow-y:auto">
            <table class="table table-dark-custom mb-0" style="font-size:.85rem">
              <thead><tr><th>Participant</th><th>Round</th><th>When</th></tr></thead>
              <tbody id="recentCheckins">
                <?php
                $recent = $pdo->query("
                    SELECT p.name, r.name AS round_name, pr.checked_in_at
                    FROM participant_rounds pr
                    JOIN participants p ON p.id=pr.participant_id
                    JOIN rounds r ON r.id=pr.round_id
                    WHERE pr.checked_in_at IS NOT NULL
                    ORDER BY pr.checked_in_at DESC LIMIT 20
                ")->fetchAll();
                foreach ($recent as $ci):
                ?>
                <tr>
                  <td class="fw-600"><?= sanitize($ci['name']) ?></td>
                  <td style="color:var(--text-muted)"><?= sanitize($ci['round_name']) ?></td>
                  <td style="color:var(--text-muted)"><?= timeAgo($ci['checked_in_at']) ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const API_URL  = '<?= APP_URL ?>/api/scan.php';
const roundId  = document.getElementById('manualRound')?.value;

// ── Camera scanner ──
const video    = document.getElementById('scanner-video');
const canvas   = document.getElementById('scanner-canvas');
const ctx      = canvas?.getContext('2d');
const startBtn = document.getElementById('startCam');
const stopBtn  = document.getElementById('stopCam');
const result   = document.getElementById('scan-result');
const scanLog  = document.getElementById('scan-log');
let stream = null, scanning = false, lastToken = null, lastTime = 0;

startBtn?.addEventListener('click', async () => {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = stream;
    startBtn.style.display = 'none';
    stopBtn.style.display  = '';
    scanning = true;
    requestAnimationFrame(tick);
  } catch (e) {
    showResult(result, '❌ Camera access denied. Use manual entry.', 'error');
  }
});

stopBtn?.addEventListener('click', () => {
  scanning = false;
  stream?.getTracks().forEach(t => t.stop());
  startBtn.style.display = '';
  stopBtn.style.display  = 'none';
});

function tick() {
  if (!scanning) return;
  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(img.data, img.width, img.height);
    if (code && code.data) {
      const now = Date.now();
      if (code.data !== lastToken || now - lastTime > 3000) {
        lastToken = code.data; lastTime = now;
        processToken(code.data, document.getElementById('manualRound')?.value, result, scanLog);
      }
    }
  }
  requestAnimationFrame(tick);
}

// ── Manual form ──
document.getElementById('manualForm')?.addEventListener('submit', e => {
  e.preventDefault();
  const token   = document.getElementById('manualToken').value.trim();
  const roundId = document.getElementById('manualRound').value;
  if (!token) return;
  processToken(token, roundId, document.getElementById('manual-result'), null);
});

async function processToken(token, roundId, resultEl, logEl) {
  resultEl.style.display = '';
  resultEl.className     = '';
  resultEl.innerHTML     = '<div class="d-flex align-items-center gap-2"><div class="spinner-ferrari"></div> Checking in…</div>';

  try {
    const res = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, round_id: parseInt(roundId) })
    });
    const data = await res.json();

    if (data.success) {
      resultEl.className = 'scan-result success';
      resultEl.innerHTML = `
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-check-circle-fill" style="color:#28a745;font-size:1.25rem"></i>
          <strong>${data.participant.name}</strong>
        </div>
        <div style="font-size:.85rem;color:var(--text-muted)">
          ${data.participant.email} · ${data.participant.city || ''}
        </div>
        <div style="font-size:.8rem;margin-top:.5rem">
          Round: <strong>${data.round.name}</strong>
        </div>`;
      if (logEl) {
        const row = document.createElement('div');
        row.className = 'p-2 mb-1 rounded';
        row.style.background = 'rgba(40,167,69,.1)';
        row.innerHTML = `<strong>${data.participant.name}</strong> <small style="color:#888">checked in</small>`;
        logEl.prepend(row);
      }
    } else {
      resultEl.className = 'scan-result error';
      resultEl.innerHTML = `<i class="bi bi-x-circle-fill me-2" style="color:var(--ferrari-red)"></i>${data.message}`;
    }
    resultEl.style.display = 'block';
  } catch (e) {
    resultEl.className = 'scan-result error';
    resultEl.innerHTML = '❌ Network error. Try again.';
    resultEl.style.display = 'block';
  }
}
</script>
<?php pageFoot(); ?>
