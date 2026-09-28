/**
 * pitlane-bridge.js
 * Connects the standalone Supercraft app (window.PitLane) to the PHP backend.
 * - Store.save also registers the participant (/api/register.php) and uploads the
 *   driver's journey record (/api/driver.php) so they can log in at any kiosk.
 * - Store.post also submits race scores to /api/leaderboard.php (live big-screen leaderboard).
 * - window.PitLaneSync.pull(code) fetches a driver's newest record for station login.
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
    });
  }

  function cleanCode(v) {
    return String(v || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
  }

  function patchPitLane() {
    var PL = window.PitLane;
    if (!PL || !PL.Store || PL.__bridged) return;
    PL.__bridged = true;

    var origSave = PL.Store.save.bind(PL.Store);
    var origPost = PL.Store.post.bind(PL.Store);

    /* ── Record upload: debounced per driver, retried while offline ── */
    var pending = {};   // code -> newest record not yet on the server
    var timer = null;

    function upload(rec) {
      var body = { code: rec.id, rec: Object.assign({}, rec) };
      delete body.rec.mobile;
      delete body.rec.email;
      delete body.rec._k;
      if (rec.mobile) body.mobile = rec.mobile;
      if (rec._k) body.key = rec._k;
      var reg = rec.mobile
        ? post('/api/register.php', {
            name:    rec.name,
            phone:   rec.mobile || '',
            email:   rec.email  || '',
            fp26_id: rec.id     || '',
          })
        : Promise.resolve();
      return reg.then(function () { return post('/api/driver.php', body); })
        .then(function (resp) {
          // 4xx won't get better by retrying (unknown code, not this driver's record)
          if (resp.status >= 500) throw new Error('server ' + resp.status);
        });
    }

    function flush() {
      timer = null;
      Object.keys(pending).forEach(function (code) {
        var rec = pending[code];
        upload(rec).then(function () {
          if (pending[code] === rec) delete pending[code];
        }).catch(function () {
          if (!timer) timer = setTimeout(flush, 15000);   // offline: try again shortly
        });
      });
    }

    function queue(rec) {
      if (!rec || !rec.id || !rec.name) return;
      pending[rec.id] = rec;
      clearTimeout(timer);
      timer = setTimeout(flush, 400);
    }
    window.addEventListener('online', function () { clearTimeout(timer); flush(); });

    /* ── Saves: stamp, keep locally, sync to the server ── */
    PL.Store.save = function (rec) {
      if (!rec) return origSave(rec);
      var stamped = Object.assign({}, rec, { _ts: Date.now() });
      origSave(stamped);   // always save locally first
      queue(stamped);
    };

    /* ── Station login at any kiosk: newest record wins ── */
    window.PitLaneSync = {
      pull: function (v) {
        var code = cleanCode(v);
        if (!code) return Promise.resolve(null);
        var ctl = window.AbortController ? new AbortController() : null;
        var t = setTimeout(function () { if (ctl) ctl.abort(); }, 5000);
        return fetch(API_BASE + '/api/driver.php?code=' + encodeURIComponent(code), {
          credentials: 'same-origin',
          cache: 'no-store',
          signal: ctl ? ctl.signal : undefined,
        }).then(function (resp) {
          return resp.ok ? resp.json() : null;
        }).then(function (data) {
          if (!data || !data.success || !data.rec || cleanCode(data.rec.id) !== code) return null;
          var local = PL.Store.get(code);
          if (local && (local._ts || 0) > (data.rec._ts || 0)) {
            // this kiosk holds a newer copy (e.g. saved while offline): keep it and send it up
            var mine = Object.assign({}, local, { _k: data.key });
            origSave(mine);
            queue(mine);
            return mine;
          }
          var rec = Object.assign({}, data.rec, { _k: data.key });
          if (local) {   // same driver: keep contact details this kiosk already had
            if (local.mobile) rec.mobile = local.mobile;
            if (local.email) rec.email = local.email;
          }
          origSave(rec);
          return rec;
        }).catch(function () {
          return null;   // offline: fall back to this kiosk's copy
        }).then(function (r) {
          clearTimeout(t);
          return r;
        });
      },
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
        }).catch(function () { /* silent fail — offline */ });
      }
      return rank;
    };
  }

  /* ── Patch window.PitLane whenever an unpatched one appears ──
     The compiled bundle can assign PitLane more than once while it boots, and may be slow
     on event Wi-Fi, so keep a cheap watch running rather than giving up. */
  function check() {
    var PL = window.PitLane;
    if (PL && PL.Store && !PL.__bridged) patchPitLane();
  }
  check();
  setInterval(check, 250);
}());
