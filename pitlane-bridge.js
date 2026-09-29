/**
 * pitlane-bridge.js
 * Connects the standalone Supercraft app (window.PitLane) to the PHP backend.
 * - Store.save also registers the participant (/api/register.php) and uploads the
 *   driver's journey record (/api/driver.php) so they can log in at any kiosk.
 * - Store.post also submits race scores to /api/leaderboard.php (live big-screen leaderboard).
 * - window.PitLaneSync.claim(driver) reserves the driver's login ID at sign-up.
 * - window.PitLaneSync.pull(loginId, mobile?) fetches a driver's newest record for station login.
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

  function withTimeout(p, ms) {
    return Promise.race([p, new Promise(function (r) { setTimeout(function () { r(null); }, ms); })]);
  }

  /* A code the server replaced (registered offline, then found taken) -> the server's code */
  var ALIAS_KEY = 'pitlane.alias';
  var alias = {};
  try { alias = JSON.parse(localStorage.getItem(ALIAS_KEY) || '{}') || {}; } catch (e) { alias = {}; }
  function setAlias(from, to) {
    alias[from] = to;
    try { localStorage.setItem(ALIAS_KEY, JSON.stringify(alias)); } catch (e) { /* private mode */ }
  }
  function mapped(rec) {
    return rec && rec.id && alias[rec.id] ? Object.assign({}, rec, { id: alias[rec.id] }) : rec;
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
      var reg = rec.mobile
        ? post('/api/register.php', {
            name:    rec.name,
            phone:   rec.mobile || '',
            email:   rec.email  || '',
            fp26_id: rec.id     || '',
          }).then(function (resp) { return resp.json(); }).catch(function () { return null; })
        : Promise.resolve(null);
      return reg.then(function (j) {
        var code = j && j.success && j.access_code ? cleanCode(j.access_code) : '';
        if (code && code !== rec.id) {
          // the server gave this driver a different code: keep using it from now on
          setAlias(rec.id, code);
          rec = Object.assign({}, rec, { id: code });
          origSave(rec);
        }
        var body = { code: rec.id, rec: Object.assign({}, rec) };
        delete body.rec.mobile;
        delete body.rec.email;
        delete body.rec._k;
        if (rec.mobile) body.mobile = rec.mobile;
        if (rec._k) body.key = rec._k;
        return post('/api/driver.php', body);
      })
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
      var stamped = Object.assign({}, mapped(rec), { _ts: Date.now() });
      origSave(stamped);   // always save locally first
      queue(stamped);
    };

    /* ── Station login at any kiosk: newest record wins ── */
    window.PitLaneSync = {
      /* Sign-up: reserve the login ID on the server; resolves to the code to use (or null offline) */
      claim: function (d) {
        return withTimeout(post('/api/register.php', {
          name:    d.name,
          phone:   d.mobile || '',
          email:   d.email  || '',
          fp26_id: d.code   || '',
        }).then(function (resp) { return resp.json(); }).then(function (j) {
          return j && j.success && j.access_code ? cleanCode(j.access_code) : null;
        }).catch(function () { return null; }), 5000);
      },

      /* Station login. Resolves to { rec }, { ambiguous: true } (confirm with the mobile
         number) or null (not found / offline: the caller falls back to this kiosk's copy). */
      pull: function (v, mobile) {
        var login = cleanCode(v);
        if (!login) return Promise.resolve(null);
        mobile = String(mobile || '').replace(/\D/g, '');
        var ctl = window.AbortController ? new AbortController() : null;
        var t = setTimeout(function () { if (ctl) ctl.abort(); }, 5000);
        var opts = { credentials: 'same-origin', cache: 'no-store', signal: ctl ? ctl.signal : undefined };
        var req = mobile
          ? fetch(API_BASE + '/api/driver.php', Object.assign(opts, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ login: login, mobile: mobile }),
            }))
          : fetch(API_BASE + '/api/driver.php?code=' + encodeURIComponent(login), opts);
        return req.then(function (resp) {
          return resp.ok || resp.status === 409 ? resp.json() : null;
        }).then(function (data) {
          if (data && data.ambiguous) return { ambiguous: true };
          if (!data || !data.success || !data.rec) return null;
          var code = cleanCode(data.rec.id);
          if (code.indexOf(login) !== 0) return null;
          var local = PL.Store.get(code);
          if (local && (local._ts || 0) > (data.rec._ts || 0)) {
            // this kiosk holds a newer copy (e.g. saved while offline): keep it and send it up
            var mine = Object.assign({}, local, { _k: data.key });
            origSave(mine);
            queue(mine);
            return { rec: mine };
          }
          var rec = Object.assign({}, data.rec, { _k: data.key });
          if (local) {   // same driver: keep contact details this kiosk already had
            if (local.mobile) rec.mobile = local.mobile;
            if (local.email) rec.email = local.email;
          }
          origSave(rec);
          return { rec: rec };
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
      entry = mapped(entry);
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
