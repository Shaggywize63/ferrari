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

$rounds = $pdo->query("SELECT r.id, r.round_number, r.name FROM rounds r JOIN events e ON e.id=r.event_id ORDER BY r.round_number")->fetchAll();

pageHead('Check-in Station', true);
?>
<div class="d-flex">
<?php adminNav('scanner'); ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button id="sidebarToggle" class="btn btn-sm d-md-none me-2" style="color:var(--text-muted)"><i class="bi bi-list fs-5"></i></button>
    <span class="page-title">Check-in Station</span>
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
      <!-- Check-in form -->
      <div class="col-md-6">
        <div class="card p-4">
          <h5 class="fw-700 mb-1"><i class="bi bi-person-check-fill me-2 text-ferrari"></i>Check In Participant</h5>
          <p style="color:var(--text-muted);font-size:.875rem;margin-bottom:1.5rem">Enter the participant's access code, mobile number, or email.</p>

          <?php if (!$activeRound): ?>
          <div class="alert-ferrari mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No active round. Please activate a round first.
          </div>
          <?php endif; ?>

          <form id="checkinForm">
            <div class="mb-3">
              <label class="form-label">Access Code / Mobile / Email</label>
              <input type="text" id="codeInput" class="form-control form-control-lg"
                     placeholder="e.g. A3X7Q2 or +91 99999 99999"
                     style="letter-spacing:.05em;font-weight:600"
                     autocomplete="off" autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">Round</label>
              <select id="roundSelect" class="form-select">
                <?php foreach ($rounds as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($activeRound && $activeRound['id']==$r['id']) ? 'selected' : '' ?>>
                  R<?= $r['round_number'] ?>: <?= sanitize($r['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-ferrari w-100 py-3" <?= !$activeRound ? 'disabled' : '' ?>>
              <i class="bi bi-person-check-fill me-2"></i>Check In
            </button>
          </form>
          <div id="checkin-result" class="mt-3" style="display:none"></div>
        </div>
      </div>

      <!-- Recent check-ins -->
      <div class="col-md-6">
        <div class="card p-0">
          <div class="card-header px-4 py-3">
            <i class="bi bi-clock-history me-2 text-ferrari"></i>Recent Check-ins
          </div>
          <div id="recentList" class="table-responsive" style="max-height:500px;overflow-y:auto">
            <table class="table table-dark-custom mb-0" style="font-size:.875rem">
              <thead><tr><th>Participant</th><th>Round</th><th>When</th></tr></thead>
              <tbody>
                <?php
                $recent = $pdo->query("
                    SELECT p.name, p.access_code, r.name AS round_name, pr.checked_in_at
                    FROM participant_rounds pr
                    JOIN participants p ON p.id=pr.participant_id
                    JOIN rounds r ON r.id=pr.round_id
                    WHERE pr.checked_in_at IS NOT NULL
                    ORDER BY pr.checked_in_at DESC LIMIT 30
                ")->fetchAll();
                foreach ($recent as $ci):
                ?>
                <tr>
                  <td>
                    <div class="fw-600"><?= sanitize($ci['name']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($ci['access_code']) ?></div>
                  </td>
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

<script>
const API_URL = '<?= APP_URL ?>/api/scan.php';

document.getElementById('checkinForm').addEventListener('submit', async e => {
  e.preventDefault();
  const input   = document.getElementById('codeInput').value.trim();
  const roundId = document.getElementById('roundSelect').value;
  const resultEl = document.getElementById('checkin-result');
  if (!input) return;

  resultEl.style.display = 'block';
  resultEl.className     = '';
  resultEl.innerHTML     = '<div class="d-flex align-items-center gap-2"><div class="spinner-ferrari"></div> Looking up…</div>';

  try {
    const res  = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: input, round_id: parseInt(roundId) })
    });
    const data = await res.json();

    if (data.success) {
      resultEl.className = 'scan-result success';
      resultEl.innerHTML = `
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-check-circle-fill" style="color:#28a745;font-size:1.5rem"></i>
          <strong style="font-size:1.1rem">${data.participant.name}</strong>
        </div>
        <div style="font-size:.85rem;color:var(--text-muted)">${data.participant.email}</div>
        <div style="font-size:.85rem;margin-top:.4rem">Checked into: <strong>${data.round.name}</strong></div>`;
      document.getElementById('codeInput').value = '';
      document.getElementById('codeInput').focus();
    } else {
      resultEl.className = 'scan-result error';
      resultEl.innerHTML = `<i class="bi bi-x-circle-fill me-2" style="color:var(--ferrari-red)"></i>${data.message}`;
    }
  } catch (err) {
    resultEl.className = 'scan-result error';
    resultEl.innerHTML = '❌ Network error. Try again.';
  }
});
</script>
<?php pageFoot(); ?>
