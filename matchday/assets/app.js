/* HP Matchday · kiosk app.
   One page, four stations. The station is chosen by the address:
     /matchday/              Station 01 · sign up, avatar, position
     /matchday/?station=2    Station 02 · design your jersey
     /matchday/?station=3    Station 03 · penalty challenge
     /matchday/?station=4    Station 04 · your card
     /matchday/?station=all  the whole journey on one device (testing, small set-ups)
   Stations 02 to 04 open on the login-ID screen. */
(function () {
  'use strict';
  var A = window.Art, C = window.MD_CONFIG, esc = A.esc;
  var stage = document.getElementById('stage');
  var Q = new URLSearchParams(location.search), stq = (Q.get('station') || '').toLowerCase();
  var ALL = stq === 'all', STN = ['2', '3', '4'].indexOf(stq) >= 0 ? +stq : 1;
  var API = 'api/';

  var me = null;          // { code, login, key, rec } for the player at this station
  var cur = '', onLeave = null, lastInput = Date.now();
  var ui = {};            // per-screen working state

  var ROLES = {
    striker: { label: 'Striker', abbr: 'ST', num: 9, stats: ['Finishing', 'Pace', 'Composure'], line: 'You live for the last touch. Give you half a chance and it’s in the net.' },
    playmaker: { label: 'Playmaker', abbr: 'CAM', num: 10, stats: ['Vision', 'Passing', 'Composure'], line: 'You see the game two passes ahead. You create the chances others finish.' },
    defender: { label: 'Defender', abbr: 'CB', num: 4, stats: ['Tackling', 'Strength', 'Positioning'], line: 'Nothing gets past you. You read the danger and win the ball back.' },
    goalkeeper: { label: 'Goalkeeper', abbr: 'GK', num: 1, stats: ['Reflexes', 'Handling', 'Positioning'], line: 'Last line, first to react. You make the saves that win matches.' }
  };
  var ROLE_KEYS = ['striker', 'playmaker', 'defender', 'goalkeeper'];
  var QUIZ = [
    { q: 'What’s your biggest strength on the pitch?', opts: [['Finishing', 'Clinical in front of goal'], ['Vision', 'Sees the pass first'], ['Tackling', 'Wins the ball back'], ['Reflexes', 'Reacts in a split second']] },
    { q: 'Last minute, one chance. What do you do?', opts: [['Go for goal', 'Back yourself'], ['Find a teammate', 'Pick the smart pass'], ['Stay calm', 'Wait for the right moment'], ['Take charge', 'Organise everyone around you']] }
  ];
  var SKINS = ['#F3D2B8', '#E6B894', '#C98C64', '#A9714B', '#7F4F32', '#56341F'];
  var HAIRS = ['#17110D', '#3A2413', '#6B4423', '#B8863B', '#D9C08A', '#8E9299'];
  var STYLES = [['short', 'Short'], ['long', 'Long'], ['shaved', 'Shaved']];
  var NUM_PICKS = [1, 4, 7, 9, 10, 11, 23];
  var STATION_NAMES = { 1: 'Registration &amp; avatar', 2: 'Design your jersey', 3: 'Penalty challenge', 4: 'Your card' };

  var ICONS = {
    cam: '<svg width="38" height="38" viewBox="0 0 48 48" fill="none" stroke="#0D1B3E" stroke-width="2.6" stroke-linejoin="round"><rect x="5" y="14" width="38" height="26" rx="5"/><path d="M16 14l3.5-5h9l3.5 5"/><circle cx="24" cy="27" r="7.5"/></svg>',
    screen: '<svg width="38" height="38" viewBox="0 0 48 48" fill="none" stroke="#0D1B3E" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="40" height="26" rx="3"/><path d="M24 35v6M16 41h16"/></svg>',
    bolt: '<svg width="38" height="38" viewBox="0 0 48 48" fill="none" stroke="#0D1B3E" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M27 4L11 27h11l-3 17 17-24H25z"/></svg>',
    glove: '<svg width="36" height="36" viewBox="0 0 48 48" fill="none" stroke="#E3C355" stroke-width="2.8" stroke-linejoin="round" stroke-linecap="round"><path d="M14 26V11a3 3 0 0 1 6 0v10V8a3 3 0 0 1 6 0v13V10a3 3 0 0 1 6 0v13l3-4a3 3 0 0 1 5 3l-6 12a10 10 0 0 1-9 6h-3a8 8 0 0 1-8-8z"/></svg>'
  };

  // ------------------------------------------------------------------ helpers
  function $(id) { return document.getElementById(id); }
  function pad2(n) { return ('0' + n).slice(-2); }
  function mmss(s) { s = Math.max(0, Math.round(s)); return pad2(Math.floor(s / 60)) + ':' + pad2(s % 60); }
  function firstName(n) { return String(n || '').trim().split(/\s+/)[0] || ''; }
  function hash(s) { var h = 2166136261, i; for (i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }
  function defaultJerseyName(n) {
    var p = String(n || '').trim().split(/\s+/), w = p.length > 1 ? p[p.length - 1] : p[0] || '';
    return w.replace(/[^A-Za-z'\- ]/g, '').toUpperCase().slice(0, 12);
  }
  function api(path, body) {
    return fetch(API + path, body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : { cache: 'no-store' })
      .then(function (r) { return r.json().then(function (j) { j.status = r.status; return j; }, function () { return { success: false, status: r.status, message: 'The server did not answer properly.' }; }); });
  }
  function toast(msg, ms) {
    var t = document.createElement('div'); t.className = 'toast'; t.textContent = msg; stage.appendChild(t);
    setTimeout(function () { t.remove(); }, ms || 3200);
  }
  function confirmBox(title, text, yes, no, cb) {
    var m = document.createElement('div'); m.className = 'modal';
    m.innerHTML = '<div><h3>' + title + '</h3><p>' + text + '</p><div class="row"><button class="cta ghost" data-m="0">' + no + '</button><button class="cta" data-m="1">' + yes + '</button></div></div>';
    m.addEventListener('click', function (e) { var b = e.target.closest('[data-m]'); if (!b) return; m.remove(); if (b.dataset.m === '1') cb(); });
    stage.appendChild(m);
  }

  /** Fills in everything a station may need that the player has not chosen yet. */
  function norm(rec) {
    // A record comes back from the server, so nothing in it is trusted: every value is checked before it is drawn.
    rec = rec && typeof rec === 'object' ? rec : {};
    var hex = /^#[0-9a-fA-F]{6}$/, av = rec.av && typeof rec.av === 'object' ? rec.av : {};
    rec.av = {
      skin: hex.test(av.skin) ? av.skin : SKINS[2], hair: hex.test(av.hair) ? av.hair : HAIRS[0],
      style: ['short', 'long', 'shaved'].indexOf(av.style) >= 0 ? av.style : (rec.gender === 'female' ? 'long' : 'short')
    };
    rec.name = String(rec.name || '').slice(0, 60);
    if (!ROLES.hasOwnProperty(rec.role)) { delete rec.role; delete rec.stats; }
    if (rec.role) {
      var st = Array.isArray(rec.stats) ? rec.stats : [];
      rec.stats = ROLES[rec.role].stats.map(function (n, i) { var v = Math.round(+(st[i] || [])[1]); return [n, v >= 40 && v <= 99 ? v : 80]; });
    }
    if (!/^data:image\/jpeg;base64,[A-Za-z0-9+\/=]+$/.test(rec.photo || '') || rec.photo.length > 300000) rec.photo = '';
    rec.kit = A.KITS.hasOwnProperty(rec.kit) ? rec.kit : 'white';
    rec.num = Math.round(+rec.num);
    if (!(rec.num >= 1 && rec.num <= 99)) rec.num = rec.role ? ROLES[rec.role].num : 10;
    rec.pname = rec.pname == null ? defaultJerseyName(rec.name) : String(rec.pname).toUpperCase().replace(/[^A-Z'\- ]/g, '').slice(0, 12);
    rec.showName = rec.showName == null ? true : !!rec.showName;
    rec.jerseySecs = rec.jerseySecs == null ? null : Math.max(0, Math.min(5999, Math.round(+rec.jerseySecs) || 0));
    ['game', 'best'].forEach(function (k) {
      var g = rec[k]; if (!g || typeof g !== 'object') { delete rec[k]; return; }
      var n = function (v, max) { return Math.max(0, Math.min(max, Math.round(+v) || 0)); };
      rec[k] = {
        mode: g.mode === 'keeper' ? 'keeper' : 'taker', made: n(g.made, 99), shots: n(g.shots, 99), score: n(g.score, 9999), top: n(g.top, 99), streak: n(g.streak, 99), ts: n(g.ts, 9e15),
        map: (Array.isArray(g.map) ? g.map : []).slice(0, 40).map(function (m) { return [Math.max(-.2, Math.min(1.2, +m[0] || 0)), Math.max(-.2, Math.min(1.2, +m[1] || 0)), m[2] ? 1 : 0]; })
      };
    });
    rec.quiz = Array.isArray(rec.quiz) ? rec.quiz.slice(0, 2).map(function (v) { return Math.max(0, Math.min(3, Math.round(+v) || 0)); }) : [];
    rec.tries = Math.max(0, Math.round(+rec.tries) || 0); rec.done = rec.done && typeof rec.done === 'object' ? rec.done : {};
    return rec;
  }
  function makeStats(rec) {
    var R = ROLES[rec.role], h = hash(me.code + '|' + rec.role), v = [88 + h % 7, 82 + (h >>> 3) % 8, 76 + (h >>> 6) % 9];
    v[(rec.quiz && rec.quiz[1] || 0) % 3] += 3;
    return R.stats.map(function (n, i) { return [n, v[i]]; });
  }
  function rating(rec) {
    var st = rec.stats || [], base = st.length ? (st[0][1] + st[1][1] + st[2][1]) / 3 : 78, g = rec.best || rec.game;
    return Math.max(60, Math.min(99, Math.round(g ? base * .7 + Math.min(99, 62 + g.score / 45) * .3 : base - 3)));
  }
  function fig(rec, extra) {   // drawing options for this player's figure
    var o = { skin: rec.av.skin, hair: rec.av.hair, style: rec.av.style, name: rec.showName ? rec.pname : '', num: rec.num };
    for (var k in (extra || {})) o[k] = extra[k];
    return o;
  }
  function gameMode(rec) { return rec.role === 'goalkeeper' ? 'keeper' : 'taker'; }

  // ------------------------------------------------------------------ saving
  var pending = {};
  try { pending = JSON.parse(localStorage.getItem('md.pending') || '{}') || {}; } catch (e) { pending = {}; }
  function storePending() { try { localStorage.setItem('md.pending', JSON.stringify(pending)); } catch (e) { } }
  function paintSync() { var w = document.querySelector('.foot .who'); if (w) w.classList.toggle('off', me && !!pending[me.code]); }
  function push(code) {
    var p = pending[code]; if (!p) return Promise.resolve(null);
    return api('player.php', p).then(function (r) {
      if (r.success || (r.status >= 400 && r.status < 500)) { if (pending[code] === p) { delete pending[code]; storePending(); } paintSync(); }
      return r;
    }, function () { return null; });
  }
  /** Saves the player's record. Resolves with the server's answer, or null when offline (it is retried). */
  function save() {
    if (!me) return Promise.resolve(null);
    me.rec._ts = Date.now();
    pending[me.code] = { code: me.code, key: me.key, rec: JSON.parse(JSON.stringify(me.rec)) };
    storePending(); paintSync();
    return push(me.code);
  }
  setInterval(function () { Object.keys(pending).forEach(push); }, 9000);

  // ------------------------------------------------------------------ sound (silent until files are supplied)
  var snd = { on: false, music: null };
  try { snd.muted = localStorage.getItem('md.muted') === '1'; } catch (e) { }
  var hasSound = C.sounds && Object.keys(C.sounds).length > 0;
  function sfx(name) {
    if (!hasSound || snd.muted || !C.sounds[name]) return;
    try { var a = new Audio(C.sounds[name]); a.volume = .9; a.play().catch(function () { }); } catch (e) { }
  }
  function music() {
    if (!hasSound || !C.sounds.music) return;
    if (!snd.music) { snd.music = new Audio(C.sounds.music); snd.music.loop = true; snd.music.volume = .5; }
    if (snd.muted) snd.music.pause(); else snd.music.play().catch(function () { });
  }

  // ------------------------------------------------------------------ chrome
  function top(st, title, on) {
    var steps = '';
    if (on) { steps = '<div class="steps">'; for (var i = 1; i <= 4; i++) steps += '<i class="' + (i === on ? 'on' : i < on ? 'done' : '') + '">0' + i + '</i>'; steps += '</div>'; }
    return '<div class="top">' + A.hph(44) + '<div class="sep"></div>' +
      (C.crest ? '<img class="crestimg" src="' + esc(C.crest) + '" alt="">' : '<span class="wordmark">Matchday</span>') +
      (title ? '<div class="title"><small>' + st + '</small><span>' + title + '</span></div>' : '') +
      '<svg class="stripes"><use href="#stripes"/></svg>' + steps +
      '<div style="display:flex;gap:10px' + (on ? '' : ';margin-left:auto') + '">' +
      (hasSound ? '<button class="tbtn" data-act="mute">' + (snd.muted ? 'Sound off' : 'Sound on') + '</button>' : '') +
      '<button class="tbtn" data-act="fs">Fullscreen</button></div></div>';
  }
  function foot(left, mid) {
    var l = left === 'restart' ? '<button data-act="restart">Restart</button>' : left ? '<button data-act="go:' + left + '">← Back</button>' : '<span></span>';
    return '<div class="foot">' + l + '<span>' + (mid || '') + '</span>' + (me ? '<span class="who' + (pending[me.code] ? ' off' : '') + '">' + esc(me.rec.name) + '</span>' : '<span></span>') + '</div>';
  }
  function scr(html, bare) { return '<div class="screen' + (bare ? ' bare' : '') + '">' + html + '</div>'; }
  function keeperFig() { return '<g transform="translate(759 232) scale(.41)">' + A.playerG(A.OPP_GK, { arms: 'ready', skin: '#B97A55', hair: '#17110D', gloves: '#FFFFFF', logo: false }) + '</g>'; }
  function heroScene() {
    return '<svg viewBox="396 24 900 876" preserveAspectRatio="xMidYMid slice" style="width:100%;height:100%;display:block">' + A.pitchShoot() + keeperFig() + A.GOAL_FRAME +
      '<g transform="translate(900 372)">' + A.playerG('white', {}) + '</g>' + A.ball(820, 776, 30) + '</svg>';
  }

  // ------------------------------------------------------------------ navigation
  var SCREENS = {}, INIT = {};
  function go(name, arg) {
    if (onLeave) { try { onLeave(); } catch (e) { } onLeave = null; }
    cur = name; lastInput = Date.now();
    var html = SCREENS[name](arg);
    if (html != null) stage.innerHTML = html;
    if (INIT[name]) INIT[name](arg);
  }
  function home() { me = null; ui = {}; go(STN === 1 ? 'welcome' : 'login'); }
  function fit() { stage.style.setProperty('--s', Math.min(innerWidth / 1600, innerHeight / 900).toFixed(4)); }

  var ACT = {
    go: function (a) { if (a === 'end3') go('end', 3); else go(a); },
    home: home,
    restart: function () { confirmBox('Start over?', 'This screen goes back to the start for the next player. Progress so far is saved.', 'Restart', 'Keep going', home); },
    fs: function () { if (document.fullscreenElement) document.exitFullscreen(); else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(function () { }); },
    mute: function (a, el) { snd.muted = !snd.muted; try { localStorage.setItem('md.muted', snd.muted ? '1' : '0'); } catch (e) { } el.textContent = snd.muted ? 'Sound off' : 'Sound on'; music(); }
  };
  stage.addEventListener('click', function (e) {
    var el = e.target.closest('[data-act]'); if (!el || el.disabled) return;
    var p = el.dataset.act.split(':'), f = ACT[p[0]];
    if (f) f(p[1], el, e);
  });
  ['pointerdown', 'keydown'].forEach(function (ev) { window.addEventListener(ev, function () { lastInput = Date.now(); if (!snd.on) { snd.on = true; music(); } }, true); });
  setInterval(function () {
    if (['welcome', 'login', 'game'].indexOf(cur) >= 0) return;
    if (Date.now() - lastInput > (C.idleSeconds || 150) * 1000) home();
  }, 5000);

  // ------------------------------------------------------------------ 01 · opening
  SCREENS.welcome = function () {
    return scr(top('', '', 0) + `
<div class="body" style="grid-template-columns:52fr 48fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:26px">
    <span class="kick" style="color:var(--muted);font-weight:500">Every touch counts</span>
    <h2 class="big h" style="font-size:112px">HP<br>Matchday</h2>
    <div style="display:flex;align-items:center;gap:22px"><span style="width:60px;height:3px;background:var(--gold)"></span><span class="h" style="font-size:26px;font-weight:700;letter-spacing:.2em">FROM PITCH TO TECH</span></div>
    <p class="lead" style="max-width:30ch">Create your player. Design your jersey. Step up to the spot. Earn your HP Matchday card.</p>
    <div><button class="cta" data-act="go:register">Sign up <span>→</span></button></div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:6px">
      <div class="stp"><b>01</b>Register &amp; avatar</div><div class="stp"><b>02</b>Design your jersey</div><div class="stp"><b>03</b>Penalty challenge</div><div class="stp"><b>04</b>Your trump card</div>
    </div>
  </div>
  <div style="position:relative;overflow:hidden">${heroScene()}
    <span style="position:absolute;left:36px;bottom:26px;color:#fff;opacity:.85;font-size:13px;letter-spacing:.3em">ONE CARD · FOUR STOPS</span>
    <span style="position:absolute;right:36px;bottom:26px;color:#E3C355;font-size:13px;letter-spacing:.3em">POWERED BY HP</span>
  </div>
</div>`);
  };

  // ------------------------------------------------------------------ stations 02-04 · login
  SCREENS.login = function () {
    var art = STN === 2 ? '<div style="display:flex;gap:26px;align-items:center">' + A.jersey('white', { w: 250 }) + A.jersey('white', { side: 'back', name: 'YOU', num: 10, w: 250 }) + '</div>'
      : STN === 3 ? '<div style="position:absolute;inset:0">' + heroScene() + '</div>'
        : '<div style="width:300px;height:464px;border-radius:20px;background:linear-gradient(165deg,#FFFFFF,#F6EDD3 50%,#D4AE38);border:2px solid #C9A227;box-shadow:0 30px 60px rgba(13,27,62,.28);display:grid;place-items:end center;overflow:hidden;transform:rotate(-4deg)">' + A.bust('white', 260, {}) + '</div>';
    return scr(top('Station 0' + STN, STATION_NAMES[STN], STN) + `
<div class="body" style="grid-template-columns:7fr 5fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:26px">
    <span class="kick">Station 0${STN}</span>
    <h2 class="mid h">Welcome back.<br>Log in to play</h2>
    <form id="lf" style="display:grid;gap:30px;max-width:620px" autocomplete="off">
      <div class="field" id="f-code"><label>Login ID</label><input id="code" maxlength="8" placeholder="e.g. ANA981" autocapitalize="characters" spellcheck="false" style="text-transform:uppercase;letter-spacing:.14em;font-weight:600"></div>
      <div class="field" id="f-mob" style="display:none"><label>Contact no. <em>(two players share this ID, so we need to check)</em></label><input id="mob" inputmode="numeric" maxlength="14" placeholder="10-digit number"></div>
      <div class="formerr" id="lerr"></div>
      <div><button class="cta" id="lbtn" type="submit">Log in <span>→</span></button></div>
    </form>
    <div class="card" style="padding:20px 22px;border-left:4px solid var(--gold);max-width:620px"><div class="kick gold" style="font-size:12px">Your login ID</div>
      <div style="margin-top:8px;font-size:17px;line-height:1.5">First 3 letters of your first name + first 3 digits of your contact number.<br><span style="color:var(--muted)">Ananya, 98123 45678 → </span><b>ANA981</b></div></div>
  </div>
  <div class="panel" style="position:relative;display:grid;place-items:center;overflow:hidden">${art}</div>
</div>` + foot('', 'Station 0' + STN + ' · ' + STATION_NAMES[STN]));
  };
  INIT.login = function () {
    var code = $('code'), busy = false;
    setTimeout(function () { code.focus(); }, 50);
    code.addEventListener('input', function () { code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, ''); });
    $('lf').addEventListener('submit', function (e) {
      e.preventDefault(); if (busy) return;
      var id = code.value.trim(), mob = $('mob').value.replace(/\D/g, ''), needMob = $('f-mob').style.display !== 'none';
      if (id.length < 6) { $('lerr').textContent = 'Enter your 6-character login ID.'; return; }
      if (needMob && mob.length < 10) { $('lerr').textContent = 'Enter your 10-digit contact number.'; return; }
      busy = true; $('lbtn').disabled = true; $('lerr').textContent = '';
      (needMob ? api('player.php', { login: id, mobile: mob }) : api('player.php?code=' + encodeURIComponent(id))).then(function (r) {
        busy = false; $('lbtn').disabled = false;
        if (r.status === 409 && r.ambiguous) { $('f-mob').style.display = ''; $('mob').focus(); $('lerr').textContent = ''; return; }
        if (!r.success) { $('lerr').textContent = r.status === 404 ? (r.message + ' Check it, or ask a crew member.') : (r.message || 'Something went wrong. Please try again.'); return; }
        if (!r.rec.role) { $('lerr').textContent = 'Hi ' + firstName(r.rec.name) + '! Please finish Station 01 first, then come back here.'; return; }
        me = { code: r.code, login: id, key: r.key, rec: norm(r.rec) };
        go(STN === 2 ? 'jersey' : STN === 3 ? 'gintro' : 'card');
      }, function () { busy = false; $('lbtn').disabled = false; $('lerr').textContent = 'No connection. Please try again in a moment.'; });
    });
  };

  // ------------------------------------------------------------------ 02 · registration
  SCREENS.register = function () {
    return scr(top('Station 01', 'Player registration', 1) + `
<div class="body" style="grid-template-columns:7fr 5fr">
  <form class="pad" id="rf" autocomplete="off" novalidate style="display:flex;flex-direction:column;justify-content:center;gap:26px">
    <span class="kick">Station 01</span>
    <h2 class="mid h">Sign the<br>contract</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:34px 34px;max-width:820px">
      <div class="field" id="f-name" style="grid-column:1/-1"><label>Full name</label><input id="name" maxlength="60" placeholder="Your name" autocapitalize="words" spellcheck="false"></div>
      <div class="field" id="f-phone"><label>Contact no.</label><input id="phone" inputmode="numeric" maxlength="14" placeholder="10-digit number"></div>
      <div class="field" id="f-email"><label>Email <em>(optional)</em></label><input id="email" type="email" maxlength="120" placeholder="name@email.com" autocapitalize="none" spellcheck="false"></div>
      <div class="field" id="f-gender" style="grid-column:1/-1"><label>Gender</label><div class="seg" id="gender"><button type="button" data-g="male">Male</button><button type="button" data-g="female">Female</button><button type="button" data-g="other">Prefer not to say</button></div></div>
    </div>
    <div style="display:flex;align-items:center;gap:24px"><button class="cta" id="rbtn" type="submit">Create my player <span>→</span></button>
      <span class="note" style="max-width:36ch">Your details and photo are used only for this HP Matchday experience.</span></div>
    <div class="formerr" id="rerr" style="margin-top:-14px"></div>
  </form>
  <div class="panel" style="display:flex;flex-direction:column;justify-content:center;gap:22px;padding:56px">
    <div class="cam"><svg viewBox="0 0 400 300"><ellipse cx="200" cy="140" rx="70" ry="92" fill="none" stroke="#E3C355" stroke-width="3" stroke-dasharray="10 8"/><path d="M20 40V20h20M380 40V20h-20M20 260v20h20M380 260v20h-20" stroke="#fff" stroke-width="3" fill="none"/></svg>
      <span class="camst">Up next · your player photo</span></div>
    <div class="card" style="display:flex;gap:18px;align-items:center;padding:20px 22px;border-left:4px solid var(--hp)">
      <div class="icon">${ICONS.cam}</div>
      <div><div class="h" style="font-size:20px;font-weight:700;text-transform:uppercase">5MP IR camera</div><div class="note" style="font-size:15px">Your player photo, shot on the <b class="blue">HP OmniBook 5</b>.</div></div>
    </div>
  </div>
</div>` + foot('welcome', 'Station 01 · Registration &amp; avatar'));
  };
  INIT.register = function () {
    var gender = '', busy = false;
    setTimeout(function () { $('name').focus(); }, 50);
    $('gender').addEventListener('click', function (e) {
      var b = e.target.closest('[data-g]'); if (!b) return; gender = b.dataset.g;
      [].forEach.call($('gender').children, function (c) { c.classList.toggle('on', c === b); }); bad('gender', '');
    });
    $('phone').addEventListener('input', function () { this.value = this.value.replace(/[^\d+ ]/g, ''); });
    function bad(f, msg) {
      var el = $('f-' + f), old = el.querySelector('.err'); if (old) old.remove();
      el.classList.toggle('bad', !!msg);
      if (msg) el.insertAdjacentHTML('beforeend', '<span class="err">' + esc(msg) + '</span>');
      return !msg;
    }
    $('rf').addEventListener('submit', function (e) {
      e.preventDefault(); if (busy) return;
      var name = $('name').value.replace(/\s+/g, ' ').trim(), phone = $('phone').value.replace(/\D/g, ''), email = $('email').value.trim();
      if (phone.length === 12 && phone.slice(0, 2) === '91') phone = phone.slice(2);
      if (phone.length === 11 && phone[0] === '0') phone = phone.slice(1);
      var ok = bad('name', name.length < 2 || !/[A-Za-zÀ-￿]/.test(name) ? 'Please enter your full name.' : '');
      ok = bad('phone', phone.length !== 10 ? 'Please enter a 10-digit contact number.' : '') && ok;
      ok = bad('email', email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email) ? 'That email address does not look right.' : '') && ok;
      ok = bad('gender', gender ? '' : 'Please choose an option.') && ok;
      if (!ok) return;
      busy = true; $('rbtn').disabled = true; $('rerr').textContent = '';
      api('register.php', { name: name, phone: phone, email: email, gender: gender }).then(function (r) {
        busy = false; $('rbtn').disabled = false;
        if (!r.success) { if (r.field) bad(r.field, r.message); else $('rerr').textContent = r.message || 'Sign-up failed. Please try again.'; return; }
        me = { code: r.code, login: r.login, key: r.key, rec: norm(r.rec) };
        go('avatar');
      }, function () { busy = false; $('rbtn').disabled = false; $('rerr').textContent = 'No connection. Please check the Wi-Fi and try again.'; });
    });
  };

  // ------------------------------------------------------------------ avatar (camera + look)
  SCREENS.avatar = function () {
    var av = me.rec.av;
    function sw(list, key) { return '<div class="sw" data-sw="' + key + '">' + list.map(function (c) { return '<button style="background:' + c + '" data-v="' + c + '" class="' + (av[key] === c ? 'on' : '') + '" aria-label="' + key + '"></button>'; }).join('') + '</div>'; }
    return scr(top('Station 01', 'Create your player', 1) + `
<div class="body" style="grid-template-columns:1fr 1fr">
  <div class="panel" style="display:flex;flex-direction:column;justify-content:center;gap:18px;padding:40px 64px">
    <div class="cam" id="cam"><video id="vid" autoplay playsinline muted></video>
      <svg viewBox="0 0 400 300"><ellipse cx="200" cy="140" rx="70" ry="92" fill="none" stroke="#E3C355" stroke-width="3" stroke-dasharray="10 8"/><path d="M20 40V20h20M380 40V20h-20M20 260v20h20M380 260v20h-20" stroke="#fff" stroke-width="3" fill="none"/></svg>
      <span class="camst" id="camst">Starting camera…</span></div>
    <div style="display:flex;gap:12px;align-items:center"><button class="cta center" id="snap" style="height:64px;min-width:190px;flex:none" disabled>Take photo</button>
      <span class="note" style="font-size:14px">Line your face up with the oval. Your player’s skin tone and hair colour are picked from the photo; change them on the right if you like.</span></div>
    <div class="card" style="display:flex;gap:18px;align-items:center;padding:16px 20px;border-left:4px solid var(--hp)">
      <div class="icon" style="width:60px;height:60px">${ICONS.cam}</div>
      <div><div class="h" style="font-size:18px;font-weight:700;text-transform:uppercase">5MP IR camera</div><div class="note" style="font-size:14px">Your player photo, shot on the <b class="blue">HP OmniBook 5</b>.</div></div>
    </div>
  </div>
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:20px;padding:40px 64px">
    <span class="kick">Your player</span>
    <div style="display:flex;gap:30px;align-items:flex-end">
      <div class="avframe" id="avp" style="width:290px;height:320px;flex:none"></div>
      <div style="display:grid;gap:18px">
        <div><div class="kick" style="font-size:12px;color:var(--muted);margin-bottom:10px">Skin tone</div>${sw(SKINS, 'skin')}</div>
        <div><div class="kick" style="font-size:12px;color:var(--muted);margin-bottom:10px">Hair colour</div>${sw(HAIRS, 'hair')}</div>
        <div><div class="kick" style="font-size:12px;color:var(--muted);margin-bottom:10px">Hair style</div>
          <div class="seg" data-sw="style" style="width:330px">${STYLES.map(function (s) { return '<button data-v="' + s[0] + '" class="' + (av.style === s[0] ? 'on' : '') + '" style="height:48px;font-size:13px">' + s[1] + '</button>'; }).join('')}</div></div>
      </div>
    </div>
    <div style="display:flex;align-items:baseline;justify-content:space-between;max-width:650px"><span class="h" style="font-size:28px;font-weight:700;text-transform:uppercase">${esc(me.rec.name)}</span><span style="color:var(--muted);letter-spacing:.1em">Login ID · <b style="color:var(--ink)">${esc(me.login)}</b></span></div>
    <div><button class="cta" data-act="go:quiz">Looks good <span>→</span></button></div>
  </div>
</div>` + foot('restart', 'Station 01 · Registration &amp; avatar'));
  };
  INIT.avatar = function () {
    var av = me.rec.av, vid = $('vid'), stream = null, shot = false, timer = 0;
    function paint() { $('avp').innerHTML = A.bust('white', 270, av); }
    function nearest(list, rgb) {
      var best = list[0], bd = 1e9;
      list.forEach(function (c) { var n = parseInt(c.slice(1), 16), d = Math.pow((n >> 16) - rgb[0], 2) + Math.pow((n >> 8 & 255) - rgb[1], 2) + Math.pow((n & 255) - rgb[2], 2); if (d < bd) { bd = d; best = c; } });
      return best;
    }
    function avg(ctx, x, y, w, h) {
      var d = ctx.getImageData(x, y, w, h).data, r = 0, g = 0, b = 0, n = d.length / 4, i;
      for (i = 0; i < d.length; i += 4) { r += d[i]; g += d[i + 1]; b += d[i + 2]; }
      return [r / n, g / n, b / n];
    }
    function select(key, v) {
      av[key] = v;
      [].forEach.call(stage.querySelectorAll('[data-sw="' + key + '"] button'), function (b) { b.classList.toggle('on', b.dataset.v === v); });
      paint();
    }
    stage.querySelectorAll('[data-sw]').forEach(function (g) {
      g.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) select(g.dataset.sw, b.dataset.v); });
    });
    paint();
    if (me.rec.photo) showStill(me.rec.photo); else startCam();

    function startCam() {
      shot = false; $('snap').textContent = 'Take photo';
      var old = $('cam').querySelector('img'); if (old) old.remove();
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return noCam();
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false }).then(function (s) {
        if (cur !== 'avatar') { s.getTracks().forEach(function (t) { t.stop(); }); return; }
        stream = s; vid.srcObject = s; vid.style.display = '';
        $('camst').textContent = '● Camera ready'; $('snap').disabled = false;
      }, noCam);
    }
    function noCam() { $('camst').textContent = 'Camera not available · choose your look on the right'; $('snap').disabled = true; vid.style.display = 'none'; }
    function stopCam() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } }
    function showStill(src) {
      shot = true; vid.style.display = 'none';
      var old = $('cam').querySelector('img'); if (old) old.remove();
      $('cam').insertAdjacentHTML('afterbegin', '<img src="' + src + '" alt="">');
      $('camst').textContent = 'Photo taken'; $('snap').textContent = 'Retake'; $('snap').disabled = false;
    }
    function capture() {
      var vw = vid.videoWidth, vh = vid.videoHeight; if (!vw) return;
      var side = Math.min(vw, vh) * .78, sx = (vw - side) / 2, sy = (vh - side) / 2 - vh * .03;
      var c = document.createElement('canvas'); c.width = c.height = 240;
      var x = c.getContext('2d', { willReadFrequently: true });
      x.translate(240, 0); x.scale(-1, 1); x.drawImage(vid, sx, Math.max(0, sy), side, side, 0, 0, 240, 240);
      // look suggestion: cheeks for the skin tone, the top of the head for the hair
      select('skin', nearest(SKINS, avg(x, 96, 118, 48, 34)));
      select('hair', nearest(HAIRS, avg(x, 96, 22, 48, 16)));
      me.rec.photo = c.toDataURL('image/jpeg', .74);
      stopCam(); showStill(me.rec.photo);
      $('cam').insertAdjacentHTML('beforeend', '<div class="flash"></div>'); sfx('shutter');
    }
    $('snap').addEventListener('click', function () {
      if (shot) { me.rec.photo = ''; startCam(); return; }
      if (timer) return;
      var n = 3, el = document.createElement('div'); el.className = 'count'; el.textContent = n; $('cam').appendChild(el);
      $('snap').disabled = true;
      timer = setInterval(function () { n--; if (n > 0) { el.textContent = n; return; } clearInterval(timer); timer = 0; el.remove(); capture(); }, 800);
    });
    onLeave = function () { clearInterval(timer); stopCam(); };
  };

  // ------------------------------------------------------------------ 03 · questionnaire
  SCREENS.quiz = function (step) {
    step = step || 0;
    var Qn = QUIZ[step], rec = me.rec, sel = (rec.quiz || [])[step];
    return scr(top('Station 01', 'Find your position', 1) + `
<div class="body" style="grid-template-columns:5fr 7fr">
  <div class="panel" style="display:flex;flex-direction:column;justify-content:center;align-items:center;gap:18px;padding:40px">
    <div class="avframe" style="width:380px;height:420px">${A.bust('white', 360, rec.av)}</div>
    <div style="display:flex;justify-content:space-between;width:380px;align-items:baseline;gap:14px"><span class="h" style="font-size:24px;font-weight:700;text-transform:uppercase;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(rec.name)}</span><span style="color:var(--muted);letter-spacing:.1em;white-space:nowrap">Login ID · ${esc(me.login)}</span></div>
  </div>
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:26px">
    <span class="kick">Question ${step + 1} of ${QUIZ.length}</span>
    <h3 class="h" style="margin:0;font-size:48px;font-weight:700;line-height:1.05;max-width:16ch">${Qn.q}</h3>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px" id="opts">
      ${Qn.opts.map(function (o, i) { return '<button class="opt' + (sel === i ? ' on' : '') + '" data-i="' + i + '" style="height:124px"><b>' + o[0] + '</b><small>' + o[1] + '</small></button>'; }).join('')}
    </div>
    <div style="display:flex;gap:8px">${QUIZ.map(function (_, i) { return '<span style="width:54px;height:4px;background:' + (i <= step ? 'var(--hp)' : '#E3E8EF') + '"></span>'; }).join('')}</div>
  </div>
</div>` + '<div class="foot"><button data-act="' + (step ? 'quiz:' + (step - 1) : 'go:avatar') + '">← Back</button><span>Station 01 · Registration &amp; avatar</span><span class="who">' + esc(rec.name) + '</span></div>');
  };
  ACT.quiz = function (a) { go('quiz', +a); };
  INIT.quiz = function (step) {
    step = step || 0; var locked = false;
    $('opts').addEventListener('click', function (e) {
      var b = e.target.closest('[data-i]'); if (!b || locked) return; locked = true;
      [].forEach.call($('opts').children, function (c) { c.classList.toggle('on', c === b); });
      me.rec.quiz = me.rec.quiz || []; me.rec.quiz[step] = +b.dataset.i;
      setTimeout(function () {
        if (cur !== 'quiz') return;
        if (step + 1 < QUIZ.length) return go('quiz', step + 1);
        var rec = me.rec, old = rec.role;
        rec.role = ROLE_KEYS[rec.quiz[0]]; rec.stats = makeStats(rec);
        if (old !== rec.role || !rec.numSet) rec.num = ROLES[rec.role].num;
        go('role');
      }, 280);
    });
  };

  // ------------------------------------------------------------------ 04 · role reveal
  SCREENS.role = function () {
    var rec = me.rec, R = ROLES[rec.role];
    return scr(top('Station 01', 'Your role', 1) + `
<div class="body" style="grid-template-columns:1fr 1fr">
  <div style="position:relative;background:linear-gradient(160deg,#0D1B3E,#1F3D7A);display:grid;place-items:end center;overflow:hidden">
    <svg viewBox="0 0 400 400" style="position:absolute;inset:0;width:100%;height:100%" preserveAspectRatio="xMidYMid slice"><circle cx="200" cy="170" r="150" fill="none" stroke="#C9A227" stroke-width="2" opacity=".5"/><circle cx="200" cy="170" r="110" fill="none" stroke="#fff" stroke-width="1" opacity=".2"/><path d="M0 330h400" stroke="#fff" opacity=".15"/></svg>
    <div style="position:relative">${A.bust('white', 520, rec.av)}</div>
  </div>
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:24px">
    <span class="kick gold">Your position</span>
    <h2 class="big h" style="font-size:${R.label.length > 9 ? 92 : R.label.length > 8 ? 102 : 110}px">${R.label}</h2>
    <p class="lead" style="max-width:32ch">${R.line}</p>
    <div style="display:grid;gap:16px;max-width:520px">
      ${rec.stats.map(function (s) { return '<div><div class="brow"><span>' + s[0] + '</span><b>' + s[1] + '</b></div><div class="bar"><i data-w="' + s[1] + '"></i></div></div>'; }).join('')}
    </div>
    <div><button class="cta" id="rolego">Continue <span>→</span></button></div>
  </div>
</div>` + foot('restart', 'Station 01 · Registration &amp; avatar'));
  };
  INIT.role = function () {
    setTimeout(function () { stage.querySelectorAll('.bar i').forEach(function (i) { i.style.width = i.dataset.w + '%'; }); }, 80);
    me.rec.done[1] = Date.now(); save();
    $('rolego').addEventListener('click', function () { go('end', 1); });
  };

  // ------------------------------------------------------------------ 05 · end of a station
  SCREENS.end = function (n) {
    var P = C.products[n], rec = me.rec, nextN = n + 1;
    var pics = n === 1 ? '<div style="display:flex;gap:16px;align-items:center">' +
      (rec.photo ? '<div style="text-align:center"><img src="' + rec.photo + '" alt="" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 8px 20px rgba(13,27,62,.18);display:block"><div class="note" style="margin-top:6px;letter-spacing:.14em;text-transform:uppercase;font-size:11px">Your photo</div></div>' : '') +
      '<div style="text-align:center"><div style="width:120px;height:120px;border-radius:50%;overflow:hidden;background:linear-gradient(170deg,#F7FAFD,#C9D8EC);border:3px solid #fff;box-shadow:0 8px 20px rgba(13,27,62,.18);display:grid;place-items:end center">' + A.bust('white', 112, rec.av) + '</div><div class="note" style="margin-top:6px;letter-spacing:.14em;text-transform:uppercase;font-size:11px">Your player</div></div></div>' : '';
    return scr(top('Station 0' + nextN, STATION_NAMES[nextN], nextN) + `
<div class="body" style="grid-template-columns:13fr 8fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:20px">
    <span class="kick gold">Station 0${n} complete</span>
    <h2 class="mid h" style="font-size:${P.head.length > 40 ? 56 : 66}px;max-width:17ch">${P.head}</h2>
    <p class="lead" style="font-size:30px">You just experienced <b class="blue" style="text-transform:uppercase">${P.prod}</b>!</p>
    <div class="card" style="display:flex;gap:20px;align-items:center;padding:20px 24px;border-left:4px solid var(--hp);max-width:880px">
      <div class="icon">${ICONS[P.icon]}</div>
      <div><div class="h" style="font-size:22px;font-weight:700">${P.heroTitle}</div><div class="note" style="font-size:17px">${P.heroText}</div></div>
    </div>
    <div class="kick" style="font-size:12px">There’s more to discover</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:880px">
      ${P.feat.map(function (f) { return '<div class="card" style="padding:16px;border-radius:12px"><b style="font-size:15px">' + f[0] + '</b><div class="note">' + f[1] + '</div></div>'; }).join('')}
    </div>
  </div>
  <div class="panel" style="position:relative;display:flex;flex-direction:column;justify-content:center;gap:22px;padding:48px 56px">
    <span class="h" style="position:absolute;top:30px;right:40px;font-size:220px;font-weight:800;color:rgba(13,27,62,.06);line-height:.8">0${nextN}</span>
    ${pics}
    <span class="kick">Next up →</span>
    <h3 class="h" style="margin:0;font-size:${n === 1 && rec.photo ? 38 : 44}px;font-weight:800;text-transform:uppercase;line-height:1">${P.nextHead}</h3>
    <div class="card" style="padding:20px 22px;border-left:4px solid var(--gold)"><div class="kick gold" style="font-size:12px">Your login ID for the next station</div>
      <div class="h" style="font-size:54px;font-weight:800;letter-spacing:.06em;line-height:1.1;margin-top:4px">${esc(me.login)}</div>
      <div class="note" style="font-size:14px">First 3 letters of your first name + first 3 digits of your contact number.</div></div>
    <button class="cta center" id="endgo">${ALL ? 'Continue' : P.nextCta} <span>→</span></button>
  </div>
</div>` + foot('restart', ''));
  };
  INIT.end = function (n) {
    $('endgo').addEventListener('click', function () {
      if (ALL) return go(n === 1 ? 'jersey' : n === 2 ? 'gintro' : 'card');
      var b = this; b.disabled = true;
      (pending[me.code] ? push(me.code) : Promise.resolve()).then(home);
    });
  };

  // ------------------------------------------------------------------ 06 · jersey designer
  SCREENS.jersey = function () {
    var rec = me.rec;
    if (!ui.jt0) ui.jt0 = Date.now();
    return scr(top('Station 02', 'Design your jersey', 2) + `
<div class="body" style="grid-template-columns:11fr 10fr">
  <div class="panel" style="position:relative;display:flex;align-items:center;justify-content:center;gap:34px;padding:40px">
    <span class="chipw" id="jtime" style="position:absolute;top:26px;right:30px">DESIGN TIME 00:00</span>
    <div class="jcol"><div id="jf"></div><b>Front</b><small>HP logo only</small></div>
    <div class="jcol"><div id="jb"></div><b>Back</b><small>Player name + number</small></div>
  </div>
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:24px;padding:40px 56px">
    <div><div class="kick" style="font-size:12px">01 · Choose your kit</div><div id="kits" style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:12px">
      ${A.ORDER.map(function (k) { return '<button class="kit" data-k="' + k + '">' + A.jersey(k, { w: 92, shadow: false }) + '<span>' + A.KITS[k].label + '</span><i>✓</i></button>'; }).join('')}</div></div>
    <div><div class="kick" style="font-size:12px">02 · Player number</div>
      <div style="display:flex;gap:10px;margin-top:12px;align-items:stretch">
        <input class="numbox" id="num" inputmode="numeric" maxlength="2" value="${rec.num}">
        <div style="flex:1;display:grid;grid-template-columns:repeat(7,1fr);gap:6px;align-content:start" id="picks">
          ${NUM_PICKS.map(function (n) { return '<button class="pick" data-n="' + n + '">' + n + '</button>'; }).join('')}
          <span style="grid-column:1/-1;font-size:13px;color:var(--muted);align-self:center;margin-top:6px">Pick a favourite or type any number from 1 to 99</span>
        </div>
      </div></div>
    <div><div class="kick" style="font-size:12px">03 · Player name</div>
      <div style="display:flex;gap:10px;margin-top:12px">
        <label class="namebox"><input id="pname" maxlength="12" value="${esc(rec.pname)}" spellcheck="false" autocomplete="off"><small>up to 12 letters</small></label>
        <div class="seg" id="shown" style="width:250px"><button data-s="1">Print name</button><button data-s="0">No name</button></div>
      </div></div>
    <div style="display:flex;align-items:center;gap:20px"><button class="cta" id="jgo">See it in 360° <span>→</span></button><span class="note">Front: HP logo only.<br>Back: player name and number.</span></div>
  </div>
</div>` + foot('restart', 'Station 02 · Design your jersey'));
  };
  INIT.jersey = function () {
    var rec = me.rec;
    function paint() {
      $('jf').innerHTML = A.jersey(rec.kit, { w: 350 });
      $('jb').innerHTML = A.jersey(rec.kit, { side: 'back', name: rec.showName ? rec.pname : '', num: rec.num, w: 350 });
      [].forEach.call($('kits').children, function (b) { b.classList.toggle('on', b.dataset.k === rec.kit); });
      stage.querySelectorAll('#picks .pick').forEach(function (b) { b.classList.toggle('on', +b.dataset.n === rec.num); });
      [].forEach.call($('shown').children, function (b) { b.classList.toggle('on', (b.dataset.s === '1') === !!rec.showName); });
      $('pname').parentNode.style.opacity = rec.showName ? 1 : .45;
    }
    $('kits').addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (b) { rec.kit = b.dataset.k; paint(); } });
    $('picks').addEventListener('click', function (e) { var b = e.target.closest('[data-n]'); if (b) { rec.num = +b.dataset.n; rec.numSet = true; $('num').value = rec.num; paint(); } });
    $('num').addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 2);
      var n = +this.value; if (n >= 1 && n <= 99) { rec.num = n; rec.numSet = true; paint(); }
    });
    $('num').addEventListener('blur', function () { this.value = rec.num; });
    $('pname').addEventListener('input', function () {
      this.value = this.value.toUpperCase().replace(/[^A-Z'\- ]/g, '').slice(0, 12);
      rec.pname = this.value.trim(); if (rec.pname && !rec.showName) rec.showName = true; paint();
    });
    $('shown').addEventListener('click', function (e) { var b = e.target.closest('[data-s]'); if (b) { rec.showName = b.dataset.s === '1'; paint(); } });
    $('jgo').addEventListener('click', function () { if (!rec.pname) rec.showName = false; go('spin'); });
    function tick() { var el = $('jtime'); if (el) el.textContent = 'DESIGN TIME ' + mmss((Date.now() - ui.jt0) / 1000); }
    var t = setInterval(tick, 500); tick(); paint();
    onLeave = function () { clearInterval(t); };
  };

  // ------------------------------------------------------------------ 08 · 360° view
  SCREENS.spin = function () {
    var rec = me.rec, K = A.KITS[rec.kit];
    ui.jsecs = Math.round((Date.now() - ui.jt0) / 1000);
    return scr(top('Station 02', 'Your jersey · 360°', 2) + `
<div class="body" style="grid-template-columns:8fr 5fr">
  <div style="position:relative;background:radial-gradient(60% 55% at 50% 60%,#FFFFFF,#D3E0F0);display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:34px;overflow:hidden">
    <svg viewBox="0 0 700 140" style="position:absolute;bottom:64px;width:700px"><ellipse cx="350" cy="70" rx="320" ry="54" fill="rgba(13,27,62,.05)" stroke="#C9A227" stroke-width="3"/><ellipse cx="350" cy="70" rx="250" ry="40" fill="none" stroke="rgba(13,27,62,.14)" stroke-width="2"/><path d="M634 46l30 18-32 12" fill="none" stroke="#C9A227" stroke-width="3" stroke-linecap="round"/></svg>
    <div class="spin" id="spin" style="width:700px">
      <div id="spf">${A.player(rec.kit, 600, fig(rec, { view: 'front' }))}</div>
      <div id="spb" style="display:none">${A.player(rec.kit, 600, fig(rec, { view: 'back' }))}</div>
      <div id="sps" style="display:none">${A.player(rec.kit, 600, fig(rec, { view: 'side' }))}</div>
    </div>
    <div style="position:relative;display:flex;gap:10px;margin-top:20px" id="chips"><button class="chipw" data-a="0">0° Front</button><button class="chipw" data-a="90">90°</button><button class="chipw" data-a="180">180° Back</button><button class="chipw" data-a="270">270°</button></div>
    <span style="position:absolute;top:26px;left:34px;font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted)">Drag to rotate ⟲</span>
  </div>
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:22px;padding:56px">
    <span class="kick gold">Jersey ready</span>
    <h2 class="mid h" style="font-size:${rec.showName && rec.pname.length > 8 ? 54 : 72}px">${rec.showName && rec.pname ? esc(rec.pname) + '<br>' : ''}<span class="blue">No. ${rec.num}</span></h2>
    <p class="lead">${K.label} kit · ${rec.showName && rec.pname ? 'Name on the back' : 'No name on the back'}<br>Designed in <b style="color:var(--ink)">${mmss(ui.jsecs)}</b></p>
    <div style="display:flex;gap:12px;align-items:flex-end">${A.jersey(rec.kit, { w: 170, shadow: false })}${A.jersey(rec.kit, { side: 'back', name: rec.showName ? rec.pname : '', num: rec.num, w: 170, shadow: false })}</div>
    <div style="display:flex;gap:12px"><button class="cta" id="lock" style="min-width:0">Lock it in <span>→</span></button><button class="cta ghost" data-act="go:jersey" style="min-width:0">Edit</button></div>
  </div>
</div>` + foot('jersey', 'Station 02 · Design your jersey'));
  };
  INIT.spin = function () {
    var el = $('spin'), views = [$('spf'), $('sps'), $('spb'), $('sps')], ang = 0, auto = true, drag = null, raf = 0, prev = performance.now(), resume = 0, dead = false;
    function paint() {
      // four drawn views (front, side, back, other side); each narrows as it turns away, then hands over to the next
      var a = ((ang % 360) + 360) % 360, q = Math.round(a / 90) % 4, off = (a - Math.round(a / 90) * 90) * Math.PI / 180, near = q * 90;
      var w = q % 2 ? Math.cos(off * .7) : Math.cos(off * 1.42), on = views[q];
      views.forEach(function (v) { if (v !== on) v.style.display = 'none'; });
      on.style.display = ''; on.style.transform = 'scaleX(' + (q === 3 ? -w : w).toFixed(3) + ')';
      [].forEach.call($('chips').children, function (ch) { ch.classList.toggle('on', +ch.dataset.a === near); });
    }
    function loop(now) { if (dead) return; var dt = (now - prev) / 1000; prev = now; if (auto && !drag) ang += dt * 50; paint(); raf = requestAnimationFrame(loop); }
    function hold() { auto = false; clearTimeout(resume); resume = setTimeout(function () { auto = true; }, 3500); }
    el.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, a: ang }; el.setPointerCapture(e.pointerId); hold(); });
    el.addEventListener('pointermove', function (e) { if (drag) { ang = drag.a + (e.clientX - drag.x) * .7; hold(); } });
    el.addEventListener('pointerup', function () { drag = null; });
    el.addEventListener('pointercancel', function () { drag = null; });
    $('chips').addEventListener('click', function (e) { var c = e.target.closest('[data-a]'); if (c) { ang = +c.dataset.a; hold(); } });
    $('lock').addEventListener('click', function () {
      var rec = me.rec; rec.jerseySecs = ui.jsecs; rec.done[2] = Date.now(); ui.jt0 = 0;
      save(); go('end', 2);
    });
    raf = requestAnimationFrame(loop);
    onLeave = function () { dead = true; cancelAnimationFrame(raf); clearTimeout(resume); };
  };

  // ------------------------------------------------------------------ 09 · game intro
  SCREENS.gintro = function () {
    var rec = me.rec, R = ROLES[rec.role], keep = gameMode(rec) === 'keeper', left = (C.maxGames || 2) - rec.tries;
    var scene = '<svg viewBox="0 0 1600 900" width="1600" height="900" style="display:block">' + A.pitchShoot() + keeperFig() + A.GOAL_FRAME + A.ball(846, 708, 32) +
      '<g transform="translate(400 418) scale(1.1)">' + A.playerG(rec.kit, fig(rec, { view: 'back' })) + '</g><rect width="1600" height="900" fill="url(#vig)"/></svg>';
    var how = keep
      ? [['var(--hp)', '', 'Watch', 'The striker shoots left, right or straight at you.'], ['var(--gold)', 'gold', 'Dive', 'Press <kbd class="lt">←</kbd> <kbd class="lt">↓</kbd> <kbd class="lt">→</kbd> or tap that side before the ball arrives.'], ['var(--navy)', '', 'Score', 'Faster saves score more. Every save goes on your card.']]
      : [['var(--hp)', '', 'Aim', 'Move the target with <kbd class="lt">←</kbd> <kbd class="lt">→</kbd> <kbd class="lt">↑</kbd> <kbd class="lt">↓</kbd> or tap the goal.'], ['var(--gold)', 'gold', 'Power', 'Press <kbd class="lt">SPACE</kbd> when the bar is in the white zone.'], ['var(--navy)', '', 'Score', 'Top corners count double. Every goal goes on your card.']];
    return scr(top('Station 03', 'Penalty challenge', 3) + `
<div class="body" style="grid-template-columns:11fr 10fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:22px">
    <span class="kick gold">${R.label} · No. ${rec.num}</span>
    <h2 class="mid h" style="font-size:70px">How many can you ${keep ? 'save' : 'score'} in <span class="blue">${C.gameSeconds || 60} seconds?</span></h2>
    <p class="lead" style="max-width:34ch">${keep ? 'You’re in goal. Read the shot, pick your side and keep it out.' : 'You’re on penalty duty. Pick your corner, time your power and beat the keeper.'}</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:820px">
      ${how.map(function (h) { return '<div class="card" style="padding:18px;border-top:3px solid ' + h[0] + '"><div class="kick ' + h[1] + '" style="font-size:12px' + (h[0] === 'var(--navy)' ? ';color:var(--navy)' : '') + '">' + h[2] + '</div><div class="how">' + h[3] + '</div></div>'; }).join('')}
    </div>
    <div style="display:flex;align-items:center;gap:20px">${left > 0 ? '<button class="cta" data-act="go:game">' + (keep ? 'Get in goal' : 'Take your shot') + ' <span>→</span></button>' : '<button class="cta" data-act="go:fulltime">See my result <span>→</span></button>'}
      <span class="note" style="font-size:14px">${left > 0 ? (rec.tries ? 'One more go. Your best score counts.' : '') : 'You have played all your games. Your best score counts.'}</span></div>
  </div>
  <div style="position:relative;overflow:hidden;background:#0D1B3E">
    <div style="position:absolute;left:50%;top:50%;width:1600px;height:900px;transform:translate(-50%,-52%) scale(.82)">${scene}</div>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(13,27,62,0) 45%,rgba(13,27,62,.92) 88%)"></div>
    <div style="position:absolute;left:36px;right:36px;bottom:30px;display:grid;grid-template-columns:1fr 1fr;gap:12px;color:#fff">
      <div class="glass" style="padding:16px 18px;display:flex;gap:14px;align-items:center;${keep ? 'opacity:.6' : 'border-color:#E3C355'}"><svg width="40" height="40" viewBox="-20 -20 40 40" style="flex:none">${A.ball(0, 0, 17, false)}</svg><div><b style="font-size:17px">Striker · Playmaker · Defender</b><div style="font-size:14px;opacity:.8">Take penalties. Score as many as you can.</div></div></div>
      <div class="glass" style="padding:16px 18px;display:flex;gap:14px;align-items:center;${keep ? 'border-color:#E3C355' : 'opacity:.6'}">${ICONS.glove}<div><b style="font-size:17px">Goalkeeper</b><div style="font-size:14px;opacity:.8">Dive left, right or stay. Save as many as you can.</div></div></div>
    </div>
    <div class="glass" style="position:absolute;right:30px;top:26px;text-align:right;padding:14px 20px 10px"><div style="font-size:12px;letter-spacing:.22em;opacity:.8">ON THE CLOCK</div><div class="h" style="font-size:80px;font-weight:800;line-height:.95;color:#E3C355">${C.gameSeconds || 60}<small style="font-size:28px;color:#fff;opacity:.8"> sec</small></div></div>
  </div>
</div>` + foot('restart', 'Station 03 · Penalty challenge'));
  };

  // ------------------------------------------------------------------ 10-12 · the game
  SCREENS.game = function () {
    var rec = me.rec, g;
    g = window.Game.start(stage, {
      mode: gameMode(rec), seconds: C.gameSeconds || 60, sfx: sfx,
      me: { name: rec.name, roleLabel: ROLES[rec.role].label, num: rec.num, kit: rec.kit, pname: rec.pname, showName: rec.showName, av: rec.av },
      onEnd: function (res) {
        rec.game = res; rec.tries = (rec.tries || 0) + 1;
        if (!rec.best || res.score > rec.best.score) rec.best = res;
        rec.done[3] = Date.now(); ui.rank = null;
        save().then(function (r) { if (r && r.success) { ui.rank = r.rank; var el = $('rank'); if (el && r.rank) el.innerHTML = rankHtml(r.rank); } });
        go('fulltime');
      }
    });
    onLeave = function () { g.stop(); };
    return null;
  };
  function rankHtml(n) { return 'You are <b style="color:var(--hp)">#' + n + '</b> on the leaderboard'; }

  // ------------------------------------------------------------------ 13 · full time
  SCREENS.fulltime = function () {
    var rec = me.rec, g = rec.game || rec.best, keep = g.mode === 'keeper', left = (C.maxGames || 2) - rec.tries;
    var acc = g.shots ? Math.round(g.made / g.shots * 100) : 0, isBest = rec.best && rec.best.ts === g.ts;
    var dots = (g.map || []).map(function (m) {
      var x = 112 + m[0] * 576, y = 152 + m[1] * 260;
      return m[2] ? A.ball(x.toFixed(0), y.toFixed(0), 20, false)
        : '<g stroke="#FF8A7A" stroke-width="7" stroke-linecap="round"><path d="M' + (x - 12) + ' ' + (y - 12) + 'l24 24M' + (x + 12) + ' ' + (y - 12) + 'l-24 24"/></g>';
    }).join('');
    return scr(top('Station 03', 'Full time', 3) + `
<div class="body" style="grid-template-columns:1fr 1fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:20px">
    <span class="kick gold">Full time${rec.tries > 1 && isBest ? ' · new personal best' : ''}</span>
    <h2 class="big h" style="font-size:150px;line-height:.85">${g.made} <span style="font-size:60px">${keep ? (g.made === 1 ? 'save' : 'saves') : (g.made === 1 ? 'goal' : 'goals')}</span></h2>
    <p class="lead">from ${g.shots} ${g.shots === 1 ? 'penalty' : 'penalties'} in ${C.gameSeconds || 60} seconds</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:640px;margin-top:6px">
      <div class="card" style="padding:18px"><div class="kick" style="font-size:11px;color:var(--muted)">${keep ? 'Save rate' : 'Accuracy'}</div><div class="h" style="font-size:40px;font-weight:800">${acc}%</div></div>
      <div class="card" style="padding:18px"><div class="kick" style="font-size:11px;color:var(--muted)">${keep ? 'Best streak' : 'Top corner'}</div><div class="h" style="font-size:40px;font-weight:800">${keep ? g.streak : g.top}</div></div>
      <div class="card" style="padding:18px"><div class="kick" style="font-size:11px;color:var(--muted)">Match score</div><div class="h" style="font-size:40px;font-weight:800;color:var(--hp)">${g.score}</div></div>
    </div>
    <div class="lead" id="rank" style="font-size:20px;min-height:28px">${ui.rank ? rankHtml(ui.rank) : ''}</div>
    <div style="display:flex;gap:12px"><button class="cta" data-act="go:end3">Next: your card <span>→</span></button>${left > 0 ? '<button class="cta ghost" data-act="go:game" style="min-width:0">Try again</button>' : ''}</div>
  </div>
  <div style="position:relative;overflow:hidden;background:linear-gradient(180deg,#0D1B3E,#1D3972 55%,#2A8553 55%,#1B6B40)">
    <svg viewBox="0 0 800 748" style="position:absolute;inset:0;width:100%;height:100%">
      <rect x="110" y="150" width="580" height="262" fill="rgba(5,10,28,.4)"/><rect x="110" y="150" width="580" height="262" fill="url(#net)"/>
      <rect x="100" y="140" width="12" height="272" fill="url(#post)"/><rect x="688" y="140" width="12" height="272" fill="url(#post)"/><rect x="100" y="140" width="600" height="12" rx="2" fill="url(#bar)"/>
      <path d="M0 412H800" stroke="#fff" stroke-width="4" opacity=".9"/>
      <g stroke="rgba(255,255,255,.22)" stroke-width="2" stroke-dasharray="6 8"><path d="M303 152V412M497 152V412M112 282H688"/></g>
      ${dots}
      <text x="400" y="96" text-anchor="middle" font-size="16" letter-spacing="4" fill="#fff" opacity=".85" font-family="Inter">SHOT MAP · ${g.made} ${keep ? 'SAVED' : (g.made === 1 ? 'GOAL' : 'GOALS')} · ${g.shots - g.made} ${keep ? 'CONCEDED' : 'SAVED OR MISSED'}</text>
    </svg>
    <div style="position:absolute;left:0;right:0;bottom:0;height:250px;display:flex;justify-content:center;align-items:flex-end">${A.player(rec.kit, 300, fig(rec, { view: 'back', arms: 'cheer', gloves: keep ? A.HP : null }))}</div>
  </div>
</div>` + foot('restart', 'Station 03 · Penalty challenge'));
  };

  // ------------------------------------------------------------------ 14 · the trump card
  function loadImg(src) { return new Promise(function (res) { if (!src) return res(null); var i = new Image(); i.onload = function () { res(i); }; i.onerror = function () { res(null); }; i.src = src; }); }
  function svgImg(svg) { return loadImg('data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg)); }
  function rr(x, a, b, w, h, r) { x.beginPath(); x.moveTo(a + r, b); x.arcTo(a + w, b, a + w, b + h, r); x.arcTo(a + w, b + h, a, b + h, r); x.arcTo(a, b + h, a, b, r); x.arcTo(a, b, a + w, b, r); x.closePath(); }
  function sp(x, v) { if ('letterSpacing' in x) x.letterSpacing = v + 'px'; }
  function cardFacts(rec) {
    var g = rec.best || rec.game, keep = gameMode(rec) === 'keeper', st = rec.stats || [['—', '—'], ['—', '—'], ['—', '—']];
    return {
      rating: rating(rec), abbr: ROLES[rec.role].abbr, role: ROLES[rec.role].label,
      rows: [[st[0][0], st[0][1]], [keep ? 'Saves' : 'Goals', g ? g.made : '—'], [st[1][0], st[1][1]], [keep ? 'Shots faced' : 'Penalties', g ? g.shots : '—'],
        ['Jersey time', rec.jerseySecs != null ? mmss(rec.jerseySecs) : '—'], ['Match score', g ? g.score : '—']]
    };
  }
  function drawFront(rec) {
    var F = cardFacts(rec);
    return Promise.all([svgImg(A.bust(rec.kit, 296, rec.av)), svgImg(A.hph(72)), loadImg(rec.photo), loadImg(C.crest)]).then(function (im) {
      var c = document.createElement('canvas'); c.width = 840; c.height = 1300;
      var x = c.getContext('2d'); x.scale(2, 2);
      rr(x, 0, 0, 420, 650, 26); x.clip();
      var g = x.createLinearGradient(110, 0, 310, 650); g.addColorStop(0, '#FFFFFF'); g.addColorStop(.5, '#F6EDD3'); g.addColorStop(1, '#D4AE38');
      x.fillStyle = g; x.fillRect(0, 0, 420, 650);
      x.fillStyle = 'rgba(2,74,216,.07)';
      [[250, 76], [342, 44]].forEach(function (s) { x.beginPath(); x.moveTo(s[0], 0); x.lineTo(s[0] + s[1], 0); x.lineTo(s[0] + s[1] - 130, 650); x.lineTo(s[0] - 130, 650); x.closePath(); x.fill(); });
      x.fillStyle = 'rgba(255,255,255,.55)'; x.beginPath(); x.arc(262, 230, 150, 0, 7); x.fill();
      if (im[0]) x.drawImage(im[0], 128, 44, 296, 340.4);
      x.fillStyle = '#0D1B3E'; x.textAlign = 'center';
      x.font = '800 72px "Inter Tight", Inter, sans-serif'; sp(x, -2); x.fillText(F.rating, 67, 88);
      x.font = '700 15px Inter, sans-serif'; sp(x, 2.4); x.fillText(F.abbr, 69, 112);
      x.fillRect(51, 124, 34, 1);
      var y = 136;
      if (im[1]) { x.drawImage(im[1], 50, y, 36, 36); y += 46; }
      if (im[3]) { var ch = 36, cw = Math.min(44, im[3].width / im[3].height * ch); x.drawImage(im[3], 68 - cw / 2, y, cw, ch); y += 46; }
      if (im[2]) {
        x.save(); x.beginPath(); x.arc(68, y + 26, 26, 0, 7); x.clip(); x.drawImage(im[2], 42, y, 52, 52); x.restore();
        x.strokeStyle = '#C9A227'; x.lineWidth = 2; x.beginPath(); x.arc(68, y + 26, 26, 0, 7); x.stroke();
      }
      var name = String(rec.name || '').toUpperCase(), fs = 36;
      sp(x, -1); do { x.font = '800 ' + fs + 'px "Inter Tight", Inter, sans-serif'; fs -= 2; } while (x.measureText(name).width > 372 && fs > 16);
      x.fillText(name, 210, 440);
      x.fillStyle = '#7a5f0c'; x.font = '700 14px Inter, sans-serif'; sp(x, 3); x.fillText(F.role.toUpperCase() + ' · NO. ' + rec.num, 211, 466);
      x.fillStyle = 'rgba(13,27,62,.3)'; x.fillRect(28, 508, 364, 1);
      sp(x, 0);
      F.rows.forEach(function (r, i) {
        var cx = i % 2 ? 222 : 28, cy = 540 + Math.floor(i / 2) * 30;
        x.fillStyle = '#0D1B3E'; x.textAlign = 'left'; x.font = '400 15.5px Inter, sans-serif'; x.fillText(r[0], cx, cy);
        x.textAlign = 'right'; x.font = '700 15.5px Inter, sans-serif'; x.fillText(r[1], cx + 170, cy);
      });
      x.strokeStyle = '#C9A227'; x.lineWidth = 4; rr(x, 0, 0, 420, 650, 26); x.stroke();
      return c;
    });
  }
  var NOTES = [['Respect every player.', 'Teammates, opponents, officials and fans: everyone makes the game.'], ['Win the right way.', 'No shortcuts. Effort and fair play over the result.'],
    ['Look out for each other.', 'Speak up against discrimination, on and off the pitch.'], ['Your data, your choice.', 'Your photo and details are used only for this experience.']];
  function drawBack(rec) {
    return svgImg(A.hph(88)).then(function (logo) {
      var c = document.createElement('canvas'); c.width = 840; c.height = 1300;
      var x = c.getContext('2d'); x.scale(2, 2);
      rr(x, 0, 0, 420, 650, 26); x.clip();
      var g = x.createLinearGradient(160, 0, 260, 650); g.addColorStop(0, '#0D1B3E'); g.addColorStop(1, '#142A5E');
      x.fillStyle = g; x.fillRect(0, 0, 420, 650);
      if (logo) x.drawImage(logo, 30, 34, 44, 44);
      x.textAlign = 'right'; x.fillStyle = '#E3C355'; x.font = '500 12px Inter, sans-serif'; sp(x, 2.9); x.fillText('PLAYER CODE', 390, 61);
      x.textAlign = 'left'; x.fillStyle = '#fff'; x.font = '800 30px "Inter Tight", Inter, sans-serif'; sp(x, -.9);
      x.fillText('PLAY FAIR.', 30, 124); x.fillText('PLAY TOGETHER.', 30, 156);
      var lg = x.createLinearGradient(30, 0, 390, 0); lg.addColorStop(0, '#C9A227'); lg.addColorStop(1, 'rgba(201,162,39,0)'); x.fillStyle = lg; x.fillRect(30, 174, 360, 2);
      sp(x, 0);
      var y = 208;
      NOTES.forEach(function (n) {
        var words = n[0].split(' ').map(function (w) { return [w, 1]; }).concat(n[1].split(' ').map(function (w) { return [w, 0]; })), cx = 30;
        words.forEach(function (w) {
          x.font = (w[1] ? '700' : '400') + ' 15px Inter, sans-serif';
          var ww = x.measureText(w[0] + ' ').width;
          if (cx + ww - 4 > 390) { cx = 30; y += 21.75; }
          x.fillStyle = w[1] ? '#fff' : '#D7DFEA'; x.fillText(w[0], cx, y); cx += ww;
        });
        y += 21.75 + 14;
      });
      x.textAlign = 'right'; x.fillStyle = 'rgba(255,255,255,.07)'; x.font = '800 190px "Inter Tight", Inter, sans-serif'; sp(x, -6); x.fillText(String(rec.num), 392, 580);
      x.textAlign = 'left';
      x.fillStyle = '#9FB0C8'; x.font = '500 12px Inter, sans-serif'; sp(x, 2.2);
      x.fillText('HP MATCHDAY', 30, 602); x.fillText('EXPERIENCE ZONE', 30, 618);
      x.textAlign = 'right'; x.fillStyle = '#E3C355'; x.fillText(String(me.login || rec.id || ''), 390, 618);
      x.strokeStyle = '#C9A227'; x.lineWidth = 4; rr(x, 0, 0, 420, 650, 26); x.stroke();
      return c;
    });
  }

  SCREENS.card = function () {
    var rec = me.rec;
    return scr(top('Station 04', 'Your HP Matchday card', 4) + `
<div class="body" style="grid-template-columns:4fr 8fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:18px;padding:40px 56px 40px 80px">
    <span class="kick">Station 04 · Your card</span>
    <h2 class="mid h" style="font-size:50px">Your HP Matchday card</h2>
    <p class="lead" style="font-size:19px">Print it or download it, and take your matchday home.</p>
    <div style="border-top:1px solid var(--line);padding-top:14px"><div class="kick" style="font-size:12px;color:var(--muted)">Overall rating</div><div class="h" style="font-size:84px;font-weight:800;line-height:.9">${rating(rec)}<small style="font-size:24px;color:var(--muted)"> / 99</small></div></div>
    <button class="cta ghost" id="cdl" style="height:64px" disabled>Download my card</button><button class="cta ghost" id="cpr" style="height:64px" disabled>Print my card</button><button class="cta" id="cfin" style="height:70px">Finish <span>→</span></button>
  </div>
  <div style="display:flex;align-items:center;justify-content:center;gap:40px;padding:30px;background:radial-gradient(70% 70% at 50% 50%,#F4F7FB,#E3EBF5)">
    <img class="tcard" id="cfront" alt="Card front"><img class="tcard" id="cback" alt="Card back">
  </div>
</div>` + foot('restart', 'Station 04 · Your card'));
  };
  INIT.card = function () {
    var rec = me.rec, cv = null;
    var fonts = document.fonts && document.fonts.load ? Promise.all(['800 72px "Inter Tight"', '700 15px Inter', '400 15px Inter', '500 12px Inter'].map(function (f) { return document.fonts.load(f).catch(function () { }); })) : Promise.resolve();
    fonts.then(function () { return Promise.all([drawFront(rec), drawBack(rec)]); }).then(function (c) {
      if (cur !== 'card') return;
      cv = c; $('cfront').src = c[0].toDataURL('image/png'); $('cback').src = c[1].toDataURL('image/png');
      $('cdl').disabled = false; $('cpr').disabled = false;
    });
    $('cdl').addEventListener('click', function () {
      var out = document.createElement('canvas'); out.width = 1840; out.height = 1420;
      var x = out.getContext('2d'); x.fillStyle = '#EEF2F7'; x.fillRect(0, 0, 1840, 1420); x.drawImage(cv[0], 60, 60); x.drawImage(cv[1], 940, 60);
      out.toBlob(function (b) {
        var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'HP-Matchday-card-' + me.login + '.png';
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
        toast('Card downloaded');
      }, 'image/png');
    });
    $('cpr').addEventListener('click', function () {
      $('print').innerHTML = '<div class="pg"><img src="' + $('cfront').src + '"></div><div class="pg"><img src="' + $('cback').src + '"></div>';
      var imgs = $('print').querySelectorAll('img');
      Promise.all([].map.call(imgs, function (i) { return i.decode ? i.decode().catch(function () { }) : null; })).then(function () { window.print(); });
    });
    $('cfin').addEventListener('click', function () { rec.done[4] = Date.now(); save(); go('thanks'); });
  };

  SCREENS.thanks = function () {
    var rec = me.rec, list = [1, 2, 3].map(function (n) { return C.products[n].prod; });
    return scr(top('', '', 0) + `
<div class="body" style="grid-template-columns:52fr 48fr">
  <div class="pad" style="display:flex;flex-direction:column;justify-content:center;gap:26px">
    <span class="kick gold">Full time</span>
    <h2 class="big h" style="font-size:96px">Thanks for playing, ${esc(firstName(rec.name))}!</h2>
    <p class="lead" style="max-width:32ch">Check the big screen to see where you stand on the leaderboard.</p>
    <div><div class="kick" style="font-size:12px;color:var(--muted)">Today you played on</div>
      <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">${list.map(function (p) { return '<span class="chipw" style="font-size:14px;color:var(--hp);font-weight:700;border-color:var(--hp)">' + p + '</span>'; }).join('')}</div></div>
    <div><button class="cta" data-act="home">Done <span>→</span></button></div>
  </div>
  <div style="position:relative;overflow:hidden">${heroScene()}<span style="position:absolute;right:36px;bottom:26px;color:#E3C355;font-size:13px;letter-spacing:.3em">POWERED BY HP</span></div>
</div>`);
  };
  INIT.thanks = function () { var t = setTimeout(function () { if (cur === 'thanks') home(); }, 20000); onLeave = function () { clearTimeout(t); }; };

  // ------------------------------------------------------------------ boot
  document.body.insertAdjacentHTML('afterbegin', A.defs());
  window.addEventListener('resize', fit); fit();
  window.addEventListener('afterprint', function () { $('print').innerHTML = ''; });
  home();
  window.MD = { go: go, state: function () { return { cur: cur, me: me, ui: ui, station: ALL ? 'all' : STN }; } };   // for the crew's browser console
})();
