/**
 * pitlane-bridge.js
 * Connects the standalone Supercraft app (window.PitLane) to the PHP backend.
 * Patches Store.save to also register the participant via /api/register.php,
 * and Store.post to also submit race scores to /api/leaderboard.php (live big-screen leaderboard).
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
          // The app already shows the driver their unique code; just keep the server's copy
          origSave(Object.assign({}, rec, { accessCode: data.access_code }));
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
