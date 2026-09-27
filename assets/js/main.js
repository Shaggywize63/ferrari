/* Ferrari Pit Lane – Main JS */
'use strict';

// Auto-dismiss alerts
document.querySelectorAll('.alert[data-autohide]').forEach(el => {
  setTimeout(() => el.style.opacity = '0', parseInt(el.dataset.autohide) || 3000);
  setTimeout(() => el.remove(), (parseInt(el.dataset.autohide) || 3000) + 400);
});

// Mobile sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar       = document.querySelector('.admin-sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
}

// Confirm destructive actions
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// Live search in tables
const tableSearch = document.getElementById('tableSearch');
if (tableSearch) {
  tableSearch.addEventListener('input', () => {
    const q = tableSearch.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// Copy to clipboard
document.querySelectorAll('[data-copy]').forEach(btn => {
  btn.addEventListener('click', () => {
    navigator.clipboard.writeText(btn.dataset.copy).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check2"></i>';
      setTimeout(() => btn.innerHTML = orig, 1500);
    });
  });
});

// Auto-refresh leaderboard
if (document.querySelector('.leaderboard-auto-refresh')) {
  setInterval(() => window.location.reload(), 30000);
}
