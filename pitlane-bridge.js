/**
 * pitlane-bridge.js
 * Connects the standalone Supercraft app (window.PitLane) to the PHP backend.
 * Patches Store.save to also register the participant via /api/register.php,
 * and Store.post to also submit scores to /api/leaderboard.php.
 */
(function () {
  'use strict';

  var API_BASE = (function () {
    var s = document.currentScript;
    if (s && s.src) {
      var u = new URL(s.src);
      return u.origin;
    }
    return window.location.origin;
  }());

  function post(path, data) {
    return fetch(API_BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      credentials: 'same-origin',
    }).catch(function () { /* silent fail — offline / CORS */ });
  }

  function patchPitLane() {
    var PL = window.PitLane;
    if (!PL || !PL.Store || PL.__bridged) return;
    PL.__bridged = true;

    var origSave = PL.Store.save.bind(PL.Store);
    var origPost = PL.Store.post.bind(PL.Store);

    /* ── Registration ── */
    PL.Store.save = function (rec) {
      origSave(rec);   // always save locally first

      if (rec && rec.name && (rec.mobile || rec.email)) {
        post('/api/register.php', {
          name:    rec.name,
          phone:   rec.mobile  || '',
          email:   rec.email   || '',
          fp26_id: rec.id      || '',
        }).then(function (resp) {
          if (!resp || !resp.ok) return;
          return resp.json();
        }).then(function (data) {
          if (!data || !data.success) return;
          // Store the PHP access-code alongside the local record
          var updated = Object.assign({}, rec, { accessCode: data.access_code });
          origSave(updated);
          // Surface it to the UI if a banner container exists
          showAccessCode(updated.name.split(' ')[0], data.access_code);
        }).catch(function () {});
      }
    };

    /* ── Score posting ── */
    PL.Store.post = function (entry) {
      var rank = origPost(entry);   // local leaderboard update
      if (entry && entry.id && entry.score != null) {
        post('/api/leaderboard.php', {
          fp26_id: entry.id,
          name:    entry.name  || '',
          score:   entry.score,
          num:     entry.num   || '',
          ts:      entry.ts    || Date.now(),
        });
      }
      return rank;
    };
  }

  /* ── Show a dismissible overlay with the PHP access code ── */
  function showAccessCode(firstName, code) {
    if (!code) return;
    // Avoid showing if already shown this session
    try { if (sessionStorage.getItem('plAC_' + code)) return; sessionStorage.setItem('plAC_' + code, '1'); } catch (e) {}

    var el = document.createElement('div');
    el.id = '__pl_access_code';
    el.style.cssText = [
      'position:fixed', 'bottom:32px', 'left:50%', 'transform:translateX(-50%)',
      'background:#1a1a1a', 'border:1px solid rgba(212,0,0,.6)', 'border-radius:12px',
      'padding:20px 28px', 'z-index:99999', 'font-family:inherit',
      'box-shadow:0 8px 32px rgba(0,0,0,.7)', 'max-width:420px', 'width:90%',
      'display:flex', 'flex-direction:column', 'gap:10px',
    ].join(';');

    var title = document.createElement('span');
    title.style.cssText = 'font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:#DC0000';
    title.textContent = 'Check-in Code · ' + (firstName || 'Driver');

    var codeEl = document.createElement('span');
    codeEl.style.cssText = 'font-size:48px;font-weight:600;letter-spacing:.18em;color:#F4F2EE;text-align:center';
    codeEl.textContent = code;

    var hint = document.createElement('span');
    hint.style.cssText = 'font-size:13px;color:#888;text-align:center';
    hint.textContent = 'Show this at every station for check-in';

    var close = document.createElement('button');
    close.style.cssText = 'margin-top:6px;background:#DC0000;border:none;color:#fff;font-size:13px;letter-spacing:.12em;text-transform:uppercase;padding:10px 0;border-radius:6px;cursor:pointer';
    close.textContent = 'Got it';
    close.onclick = function () { el.parentNode && el.parentNode.removeChild(el); };

    el.appendChild(title);
    el.appendChild(codeEl);
    el.appendChild(hint);
    el.appendChild(close);
    document.body.appendChild(el);
  }

  /* ── Poll for window.PitLane (set by the compiled bundle) ── */
  var checks = 0;
  var iv = setInterval(function () {
    if (window.PitLane && window.PitLane.Store) {
      clearInterval(iv);
      patchPitLane();
    } else if (++checks > 300) {   // give up after ~15 s
      clearInterval(iv);
    }
  }, 50);
}());
