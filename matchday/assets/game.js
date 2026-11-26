/* HP Matchday · Station 03 · Penalty challenge.
   Two modes on a 1600 x 900 stage: penalty taker (striker, playmaker, defender) and goalkeeper.
   Game.start(root, { mode, seconds, me, sfx, onEnd }) -> { stop() } */
(function () {
  'use strict';
  var A = window.Art, FONT = A.FONT;
  var CIRC = 2 * Math.PI * 40;

  function $(id) { return document.getElementById(id); }
  function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function easeOut(t) { return 1 - Math.pow(1 - clamp(t, 0, 1), 3); }
  function rnd(a, b) { return a + Math.random() * (b - a); }
  function mmss(s) { s = Math.max(0, Math.ceil(s)); return Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2); }

  function keysHtml(items) {
    return items.map(function (it) {
      return '<span class="keys">' + it[0].map(function (k) { return '<kbd>' + k + '</kbd>'; }).join('') + '<span>' + it[1] + '</span></span>';
    }).join('');
  }

  function hud(me, labels, offset) {
    var av = me.av || {};
    return '<div class="hud" style="left:44px;top:' + offset + 'px;display:flex;align-items:center;gap:14px">' +
      '<div class="glass" style="display:flex;align-items:center;gap:12px;padding:10px 18px 10px 12px">' + A.hph(40) +
      '<div><div style="font-size:11px;letter-spacing:.22em;opacity:.7">STATION 03</div><div class="h" style="font-size:19px;font-weight:700;letter-spacing:.04em">PENALTY CHALLENGE</div></div></div></div>' +
      '<div class="hud glass" style="left:50%;top:' + offset + 'px;transform:translateX(-50%);display:flex;align-items:center">' +
      '<div style="padding:10px 34px;text-align:center;min-width:150px"><div class="lbl">' + labels[0] + '</div><div class="h num" id="g-made" style="color:#E3C355">0</div></div>' +
      '<div style="position:relative;width:108px;height:108px;margin:6px 0"><svg viewBox="0 0 100 100" width="108" height="108"><circle cx="50" cy="50" r="40" fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.18)" stroke-width="7"/>' +
      '<circle id="g-ring" cx="50" cy="50" r="40" fill="none" stroke="#E3C355" stroke-width="7" stroke-linecap="round" stroke-dasharray="' + CIRC.toFixed(1) + ' ' + CIRC.toFixed(1) + '" transform="rotate(-90 50 50)"/></svg>' +
      '<div class="h" id="g-clock" style="position:absolute;inset:0;display:grid;place-items:center;font-size:34px;font-weight:800">1:00</div></div>' +
      '<div style="padding:10px 34px;text-align:center;min-width:150px"><div class="lbl">' + labels[1] + '</div><div class="h num" id="g-shots">0</div></div></div>' +
      '<div class="hud glass" style="right:44px;top:' + offset + 'px;display:flex;align-items:center;gap:14px;padding:10px 20px 10px 10px">' +
      '<div style="width:62px;height:62px;border-radius:50%;overflow:hidden;background:linear-gradient(170deg,#F2F7FC,#C9D8EC);display:grid;place-items:end center">' + A.bust(me.kit, 60, av) + '</div>' +
      '<div><div class="h" style="font-size:22px;font-weight:700;letter-spacing:.02em;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + A.esc(me.name.toUpperCase()) + '</div>' +
      '<div style="font-size:12px;letter-spacing:.2em;color:#E3C355">' + A.esc(me.roleLabel.toUpperCase()) + ' · NO. ' + me.num + '</div></div></div>';
  }
  function banner(text, accent, size, bx, by) {
    bx = bx || 930; by = by || 668;
    var s = '';
    [[26, 'rgba(0,0,0,.35)'], [10, accent], [0, '#FFFFFF']].forEach(function (l) {
      s += '<text x="' + bx + '" y="' + (by + l[0] * size / 250) + '" text-anchor="middle" font-size="' + size + '" font-weight="800" font-style="italic" letter-spacing="' + (-size / 25) + '" fill="' + l[1] + '" font-family="' + FONT + '">' + text + '</text>';
    });
    return '<g class="pop">' + s + '</g>';
  }
  function lastHtml(last) {
    return last.slice(-5).map(function (ok, i, a) { return '<i class="shot ' + (ok ? 'g' : 'x') + (i === a.length - 1 ? ' new' : '') + '">' + (ok ? '' : '✕') + '</i>'; }).join('') + '<i class="shot now"></i>';
  }

  function start(root, opts) {
    var mode = opts.mode === 'keeper' ? 'keeper' : 'taker';
    var me = opts.me, total = opts.seconds || 60, sfx = opts.sfx || function () { };
    var fig = { name: me.showName ? me.pname : '', num: me.num, skin: me.av.skin, hair: me.av.hair, style: me.av.style };
    var S = { phase: 'count', remain: total, made: 0, shots: 0, score: 0, top: 0, streak: 0, best: 0, last: [], map: [] };
    var raf = 0, prev = 0, dead = false, held = {}, tEnd = 0, timers = [];
    var core = mode === 'keeper' ? keeperGame() : takerGame();

    root.innerHTML = '<div class="screen bare"><svg id="g-svg" viewBox="0 0 1600 900" width="1600" height="900">' + core.svg +
      '<rect width="1600" height="900" fill="url(#vig)" pointer-events="none"/></svg>' + core.hud +
      '<div class="overlay" id="g-over"><div class="n" id="g-n">3</div></div></div>';
    var svg = $('g-svg');
    core.init();

    function later(fn, ms) { timers.push(setTimeout(function () { if (!dead) fn(); }, ms)); }
    function paintHud() {
      $('g-made').textContent = S.made; $('g-shots').textContent = S.shots;
      $('g-clock').textContent = mmss(S.remain);
      $('g-ring').setAttribute('stroke-dasharray', (CIRC * clamp(S.remain / total, 0, 1)).toFixed(1) + ' ' + CIRC.toFixed(1));
      $('g-last').innerHTML = lastHtml(S.last);
    }
    function record(ok, pts, nx, ny) {
      S.shots++; S.last.push(ok); S.map.push([+nx.toFixed(3), +ny.toFixed(3), ok ? 1 : 0]);
      if (ok) { S.made++; S.streak++; S.best = Math.max(S.best, S.streak); S.score += pts; } else S.streak = 0;
      paintHud();
    }
    function finish() {
      if (S.phase === 'over') return;
      S.phase = 'over'; sfx('whistle');
      $('g-over').style.display = ''; $('g-n').style.fontSize = '170px'; $('g-n').textContent = 'FULL TIME';
      later(function () {
        stop();
        opts.onEnd({ mode: mode, made: S.made, shots: S.shots, score: S.score, top: S.top, streak: S.best, map: S.map.slice(0, 40), ts: Date.now() });
      }, 1700);
    }
    function loop(now) {
      if (dead) return;
      var dt = Math.min(.05, (now - prev) / 1000); prev = now;
      if (S.phase !== 'count' && S.phase !== 'over') {
        S.remain -= dt;
        $('g-clock').textContent = mmss(S.remain);
        $('g-ring').setAttribute('stroke-dasharray', (CIRC * clamp(S.remain / total, 0, 1)).toFixed(1) + ' ' + CIRC.toFixed(1));
        core.tick(now, dt);
      }
      raf = requestAnimationFrame(loop);
    }
    function svgPoint(e) {
      var r = svg.getBoundingClientRect();
      return { x: (e.clientX - r.left) / r.width * 1600, y: (e.clientY - r.top) / r.height * 900 };
    }
    function onKey(e) {
      var k = e.key === ' ' || e.code === 'Space' ? 'Space' : e.key;
      if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Space', 'Enter'].indexOf(k) < 0) return;
      e.preventDefault();
      if (e.type === 'keydown') { if (!held[k]) core.key(k); held[k] = 1; } else delete held[k];
    }
    function stop() {
      dead = true; cancelAnimationFrame(raf); timers.forEach(clearTimeout);
      window.removeEventListener('keydown', onKey, true); window.removeEventListener('keyup', onKey, true);
    }
    window.addEventListener('keydown', onKey, true); window.addEventListener('keyup', onKey, true);
    svg.addEventListener('pointerdown', function (e) { core.point(svgPoint(e), true); });
    svg.addEventListener('pointermove', function (e) { core.point(svgPoint(e), false); });

    // 3 · 2 · 1 · kick-off
    paintHud();
    [['3', 0], ['2', 750], ['1', 1500], ['GO!', 2250]].forEach(function (c) { later(function () { $('g-n').textContent = c[0]; if (c[0] === 'GO!') sfx('whistle'); }, c[1]); });
    later(function () { $('g-over').style.display = 'none'; S.phase = 'play'; core.begin(performance.now()); }, 2900);
    prev = performance.now(); raf = requestAnimationFrame(loop);

    // ------------------------------------------------------------------ penalty taker
    function takerGame() {
      var Z0 = .62, Z1 = .84, SPOT = { x: 846, y: 708 }, BR = 15;
      var aim = { x: 1000, y: 250 }, pw = 0, pdir = 1, st = 'aim', t0 = 0, shot = null, lastCol = 9, kph = Math.random() * 6;
      var svgStr = A.pitchShoot() + '<ellipse id="g-bulge" rx="64" ry="48" fill="rgba(255,255,255,.16)" opacity="0"/>' +
        '<g id="g-keeper"></g>' + A.GOAL_FRAME +
        '<g id="g-aimui"><rect x="480" y="190" width="110" height="74" rx="4" fill="rgba(201,162,39,.2)" stroke="#E3C355" stroke-width="2" stroke-dasharray="7 6"/>' +
        '<rect x="1010" y="190" width="110" height="74" rx="4" fill="rgba(201,162,39,.2)" stroke="#E3C355" stroke-width="2" stroke-dasharray="7 6"/>' +
        '<text x="535" y="236" text-anchor="middle" font-size="24" font-weight="800" fill="#E3C355" font-family="' + FONT + '">x2</text>' +
        '<text x="1065" y="236" text-anchor="middle" font-size="24" font-weight="800" fill="#E3C355" font-family="' + FONT + '">x2</text>' +
        '<path id="g-path" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-dasharray="1 16" opacity=".9"/>' +
        '<g id="g-aim"><circle r="40" fill="rgba(13,27,62,.25)" stroke="#fff" stroke-width="4"/><circle r="26" fill="none" stroke="#E3C355" stroke-width="3"/><circle r="6" fill="#E3C355"/>' +
        '<path d="M-58 0h-16M58 0h16M0-58v-16M0 58v16" stroke="#fff" stroke-width="4" stroke-linecap="round"/></g></g>' +
        '<ellipse id="g-bsh" rx="37" ry="7" fill="rgba(0,0,0,.28)"/><g id="g-ball">' + A.ballG(32) + '</g>' +
        '<g id="g-fx"></g><g id="g-me" transform="translate(400 418) scale(1.1)"></g>';
      var hudStr = hud(me, ['GOALS', 'SHOTS'], 28) +
        '<div class="hud pill" id="g-tip" style="left:1170px;top:212px;font-size:13px;padding:9px 16px;color:#E3C355">TOP CORNER · DOUBLE POINTS</div>' +
        '<div class="hud" id="g-pills" style="left:260px;right:0;top:724px;display:flex;justify-content:center;gap:12px"></div>' +
        '<div class="hud glass" style="left:44px;bottom:36px;padding:14px 20px"><div class="lbl" style="text-align:left;margin-bottom:10px">LAST 5 SHOTS</div><div id="g-last" style="display:flex;gap:10px"></div></div>' +
        '<div class="hud glass" style="left:50%;bottom:36px;transform:translateX(-50%);padding:16px 14px;font-size:14px;letter-spacing:.16em;white-space:nowrap">' +
        keysHtml([[['←', '→', '↑', '↓'], 'AIM'], [['SPACE'], 'SHOOT']]) + '</div>' +
        '<button class="shootbtn" id="g-shoot">SHOOT</button>' +
        '<div class="hud glass gbtn" id="g-power" style="right:44px;bottom:36px;padding:14px 20px;width:380px">' +
        '<div style="display:flex;justify-content:space-between;align-items:baseline"><span class="lbl">POWER</span><b class="h" id="g-pp" style="font-size:22px;color:#E3C355">0%</b></div>' +
        '<div style="position:relative;height:20px;margin-top:10px;border-radius:10px;background:linear-gradient(90deg,#2E8B57 0,#7CC36B 45%,#E3C355 70%,#D8452E 100%)">' +
        '<div style="position:absolute;left:' + Z0 * 100 + '%;width:' + (Z1 - Z0) * 100 + '%;top:-5px;bottom:-5px;border:2px solid #fff;border-radius:8px"></div>' +
        '<div id="g-pm" style="position:absolute;left:0;top:-9px;bottom:-9px;width:6px;margin-left:-3px;background:#fff;border-radius:3px;box-shadow:0 0 12px #fff"></div></div>' +
        '<div style="margin-top:10px;font-size:12px;letter-spacing:.14em;opacity:.75">HIT SPACE INSIDE THE WHITE ZONE</div></div>';

      function keeper(arms) { $('g-keeper').innerHTML = A.playerG(A.OPP_GK, { arms: arms, skin: '#B97A55', hair: '#17110D', gloves: '#FFFFFF', logo: false, shadow: arms === 'ready' }); }
      function keeperAt(cx, cy, ang) { $('g-keeper').setAttribute('transform', 'translate(' + cx.toFixed(1) + ' ' + cy.toFixed(1) + ') rotate(' + ang.toFixed(1) + ') scale(.41) translate(-100 -215)'); }
      function meFig(arms) { $('g-me').innerHTML = A.playerG(me.kit, { view: 'back', arms: arms, name: fig.name, num: fig.num, skin: fig.skin, hair: fig.hair, style: fig.style }); }
      function ballAt(x, y, r, o) {
        $('g-ball').setAttribute('transform', 'translate(' + x.toFixed(1) + ' ' + y.toFixed(1) + ') scale(' + (r / 32).toFixed(3) + ')');
        $('g-ball').setAttribute('opacity', o == null ? 1 : o);
      }
      function paintAim() {
        $('g-aim').setAttribute('transform', 'translate(' + aim.x.toFixed(1) + ' ' + aim.y.toFixed(1) + ')');
        $('g-path').setAttribute('d', 'M846 704 Q' + ((846 + aim.x) / 2 + 50).toFixed(0) + ' ' + (aim.y + (704 - aim.y) * .42).toFixed(0) + ' ' + aim.x.toFixed(0) + ' ' + (aim.y + 12).toFixed(0));
      }
      function reset() {
        st = 'aim'; shot = null;
        keeper('ready'); meFig('down'); ballAt(SPOT.x, SPOT.y, 32);
        $('g-bsh').setAttribute('transform', 'translate(846 741)'); $('g-bsh').setAttribute('opacity', 1);
        $('g-fx').innerHTML = ''; $('g-pills').innerHTML = ''; $('g-bulge').setAttribute('opacity', 0);
        $('g-aimui').style.display = ''; $('g-tip').style.display = ''; $('g-me').setAttribute('transform', 'translate(400 418) scale(1.1)');
        paintAim();
      }
      function shoot(now) {
        if (st !== 'aim' || S.phase !== 'play') return;
        var p = pw, under = Math.max(0, Z0 - p), over = Math.max(0, p - Z1), sc = 12 + Math.abs(p - (Z0 + Z1) / 2) * 220;
        var side = aim.x < 800 ? -1 : 1;
        var tx = aim.x + rnd(-sc, sc) + side * over * 380;
        var ty = Math.min(392, aim.y + rnd(-sc, sc) * .6 - over * 520 + under * 260);
        var inGoal = tx > 476 + BR && tx < 1124 - BR && ty > 186 + BR;
        var post = !inGoal && tx > 476 - BR && tx < 1124 + BR && ty > 186 - BR;
        var col = tx < 692 ? -1 : tx > 908 ? 1 : 0, high = ty < 270, corner = high && (tx < 600 || tx > 1000);
        var res, kcol;
        if (!inGoal) { res = post ? 'post' : ty <= 186 + BR ? 'over' : 'wide'; kcol = [-1, 0, 1][Math.floor(Math.random() * 3)]; }
        else {
          var read = Math.min(.88, .4 + under * 2.2 + Math.min(.2, S.streak * .06) + (col === lastCol ? .12 : 0));
          if (Math.random() < read) kcol = col; else { var others = [-1, 0, 1].filter(function (c) { return c !== col; }); kcol = others[Math.floor(Math.random() * 2)]; }
          if (kcol !== col) res = 'goal';
          else if (!under && Math.random() < (corner ? .65 : col === 0 && high ? .5 : .12)) res = 'goal';
          else res = 'save';
        }
        lastCol = col;
        // where the keeper ends up
        var k = { x: 800, y: 320, a: 0 };
        if (kcol) {
          k.a = 74 * kcol;
          if (res === 'save') { k.x = tx - kcol * 92; k.y = Math.min(372, ty + 26); }
          else if (kcol === col && inGoal) { k.x = tx - kcol * 140; k.y = Math.min(372, ty + 60); }
          else { k.x = 800 + kcol * 165; k.y = 352; }
        } else if (res === 'save') { k.x = clamp(tx, 730, 870); k.y = clamp(ty + 96, 286, 320); }
        shot = { tx: tx, ty: ty, dur: 520 + under * 1500, res: res, k: k, corner: corner, kx0: kx(now), arms: false };
        st = 'fly'; t0 = now; sfx('kick');
        $('g-aimui').style.display = 'none'; $('g-tip').style.display = 'none';
      }
      function kx(now) { return 800 + Math.sin(now / 420 + kph) * 30; }
      function resolve(now) {
        var r = shot.res, ok = r === 'goal', pts = 0, pills = '';
        if (ok) {
          pts = shot.corner ? 200 : 100;
          var bonus = Math.min(100, 20 * S.streak);   // S.streak is the run before this goal
          if (shot.corner) { S.top++; pills += '<span class="pill gold">+200 · TOP CORNER x2</span>'; } else pills += '<span class="pill gold">+100</span>';
          if (bonus) pills += '<span class="pill">STREAK · ' + (S.streak + 1) + ' IN A ROW · +' + bonus + '</span>';
          pts += bonus;
          $('g-bulge').setAttribute('cx', shot.tx); $('g-bulge').setAttribute('cy', shot.ty); $('g-bulge').setAttribute('opacity', 1);
          $('g-fx').innerHTML = A.confetti(90, Math.floor(Math.random() * 999)) + banner('GOAL!', '#C9A227', 250);
          meFig('cheer'); sfx('goal');
        } else {
          var txt = { save: 'SAVED!', over: 'OVER!', wide: 'WIDE!', post: 'POST!' }[r];
          $('g-fx').innerHTML = banner(txt, '#D8452E', 220);
          pills = '<span class="pill">' + ({ save: 'THE KEEPER READ IT', over: 'TOO MUCH POWER', wide: 'JUST WIDE', post: 'OFF THE WOODWORK' }[r]) + '</span>';
          sfx(r === 'save' ? 'save' : 'miss');
        }
        $('g-pills').innerHTML = pills;
        record(ok, pts, clamp((shot.tx - 476) / 648, -.12, 1.12), clamp((shot.ty - 186) / 216, -.2, 1));
        st = 'result'; t0 = now; tEnd = now + 1600;
      }
      return {
        svg: svgStr, hud: hudStr,
        init: function () {
          reset(); pw = 0;
          $('g-shoot').addEventListener('pointerdown', function (e) { e.preventDefault(); shoot(performance.now()); });
          $('g-power').addEventListener('pointerdown', function (e) { e.preventDefault(); shoot(performance.now()); });
        },
        begin: function () { },
        key: function (k) { if (k === 'Space' || k === 'Enter') shoot(performance.now()); },
        point: function (p, down) {
          if (st !== 'aim' || S.phase !== 'play') return;
          if (p.x > 430 && p.x < 1170 && p.y > 150 && p.y < 430) { aim.x = clamp(p.x, 490, 1110); aim.y = clamp(p.y, 200, 388); paintAim(); }
        },
        tick: function (now, dt) {
          if (st === 'aim') {
            if (S.remain <= 0) return finish();
            var v = 430 * dt;
            if (held.ArrowLeft) aim.x -= v; if (held.ArrowRight) aim.x += v; if (held.ArrowUp) aim.y -= v * .7; if (held.ArrowDown) aim.y += v * .7;
            aim.x = clamp(aim.x, 490, 1110); aim.y = clamp(aim.y, 200, 388); paintAim();
            var period = Math.max(.95, 1.5 - S.made * .05);
            pw += pdir * dt * 2 / period; if (pw >= 1) { pw = 1; pdir = -1; } else if (pw <= 0) { pw = 0; pdir = 1; }
            $('g-pm').style.left = (pw * 100).toFixed(1) + '%'; $('g-pp').textContent = Math.round(pw * 100) + '%';
            keeperAt(kx(now), 320, 0);
          } else if (st === 'fly') {
            var u = (now - t0) / shot.dur, e = easeOut(Math.min(1, u) * .92);
            var bx = lerp(SPOT.x, shot.tx, e), by = lerp(SPOT.y, shot.ty, e) - Math.sin(Math.min(1, u) * Math.PI) * 26;
            ballAt(bx, by, lerp(32, BR, e));
            $('g-bsh').setAttribute('opacity', (1 - e) * .9); $('g-bsh').setAttribute('transform', 'translate(' + bx.toFixed(0) + ' ' + lerp(741, 404, e).toFixed(0) + ') scale(' + lerp(1, .4, e).toFixed(2) + ')');
            var ku = easeOut((u - .2) / .72);
            if (u > .2 && !shot.arms && (shot.k.a || shot.res === 'save')) { shot.arms = true; keeper('up'); }
            keeperAt(lerp(shot.kx0, shot.k.x, ku), lerp(320, shot.k.y, ku), shot.k.a * ku);
            $('g-me').setAttribute('transform', 'translate(' + (400 + Math.sin(Math.min(1, u * 3) * Math.PI) * 26).toFixed(1) + ' ' + (418 - Math.sin(Math.min(1, u * 3) * Math.PI) * 14).toFixed(1) + ') scale(1.1)');
            if (u >= 1) resolve(now);
          } else if (st === 'result') {
            var w = clamp((now - t0) / 520, 0, 1), q = easeOut(w), r = shot.res, sx = shot.tx < 800 ? -1 : 1;
            if (r === 'goal') { ballAt(shot.tx, lerp(shot.ty, Math.max(shot.ty, 372), w * w), BR); $('g-bulge').setAttribute('opacity', (1 - w).toFixed(2)); }
            else if (r === 'save') ballAt(shot.tx + sx * 70 * q, lerp(shot.ty, 436, w * w), lerp(BR, 20, q));
            else if (r === 'post') ballAt(shot.tx - sx * 90 * q, lerp(shot.ty, 470, w * w), lerp(BR, 22, q));
            else ballAt(shot.tx + (shot.tx - SPOT.x) * .16 * q, shot.ty + (shot.ty - SPOT.y) * .16 * q, lerp(BR, 10, q), 1 - w * .8);
            if (now >= tEnd) { if (S.remain <= 0) finish(); else reset(); }
          }
        }
      };
    }

    // ------------------------------------------------------------------ goalkeeper
    function keeperGame() {
      var st = 'wait', t0 = 0, tShot = 0, T = 1050, dir = 0, pick = null, tPick = 0, dive = null;
      var TG = { '-1': { x: 316, y: 598 }, '0': { x: 800, y: 548 }, '1': { x: 1284, y: 598 } };
      var NAME = { '-1': 'DIVE LEFT!', '0': 'STAY!', '1': 'DIVE RIGHT!' };
      function arrow(id, x, flip) {
        return '<g id="' + id + '" transform="translate(' + x + ' 590)" opacity=".55"><circle r="62" fill="rgba(7,14,36,.6)" stroke="#fff" stroke-width="3"/>' +
          '<path d="' + (flip ? 'M-14 -26 L18 0 L-14 26' : 'M14 -26 L-18 0 L14 26') + '" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/></g>';
      }
      var svgStr = A.pitchKeep() + '<g id="g-striker"></g><ellipse id="g-bsh" rx="12" ry="4" fill="rgba(0,0,0,.3)"/><g id="g-ball">' + A.ballG(32) + '</g>' +
        arrow('g-al', 316, false) + arrow('g-ar', 1284, true) +
        '<g id="g-me"></g><g id="g-fx"></g>' + A.KEEP_FRAME;
      var hudStr = hud(me, ['SAVES', 'FACED'], 58) +
        '<div class="hud" style="left:0;right:0;top:196px;display:flex;justify-content:center"><div class="glass" id="g-panel" style="padding:12px 22px;text-align:center;min-width:420px;background:rgba(7,14,36,.96)">' +
        '<div class="h" id="g-call" style="font-size:26px;font-weight:800;letter-spacing:.06em;color:#fff">GET READY…</div>' +
        '<div style="height:6px;border-radius:3px;background:rgba(255,255,255,.18);margin-top:8px;overflow:hidden"><div id="g-win" style="width:0;height:100%;background:#E3C355"></div></div></div></div>' +
        '<div class="hud h" id="g-tl" style="left:226px;top:682px;width:180px;text-align:center;color:#fff;opacity:.6;font-size:20px;font-weight:800;letter-spacing:.14em">DIVE LEFT</div>' +
        '<div class="hud h" id="g-tr" style="left:1194px;top:682px;width:180px;text-align:center;color:#fff;opacity:.6;font-size:20px;font-weight:800;letter-spacing:.14em">DIVE RIGHT</div>' +
        '<div class="hud" id="g-pills" style="left:0;right:0;top:446px;display:flex;justify-content:center;gap:12px"></div>' +
        '<div class="hud glass" style="left:70px;bottom:36px;padding:14px 20px"><div class="lbl" style="text-align:left;margin-bottom:10px">LAST 5 SHOTS</div><div id="g-last" style="display:flex;gap:10px"></div></div>' +
        '<div class="hud glass" style="right:70px;bottom:36px;padding:16px 14px;font-size:14px;letter-spacing:.16em;white-space:nowrap">' +
        keysHtml([[['←'], 'DIVE LEFT'], [['↓'], 'STAY'], [['→'], 'DIVE RIGHT']]) + '</div>';

      function meFig(arms) { $('g-me').innerHTML = A.playerG(me.kit, { view: 'back', arms: arms, name: fig.name, num: fig.num, skin: fig.skin, hair: fig.hair, style: fig.style, gloves: A.HP, shadow: arms === 'ready' }); }
      function meAt(cx, cy, a) { $('g-me').setAttribute('transform', 'translate(' + cx.toFixed(1) + ' ' + cy.toFixed(1) + ') rotate(' + a.toFixed(1) + ') scale(1.12) translate(-100 -215)'); }
      function ballAt(x, y, r, o) {
        $('g-ball').setAttribute('transform', 'translate(' + x.toFixed(1) + ' ' + y.toFixed(1) + ') scale(' + (r / 32).toFixed(3) + ')');
        $('g-ball').setAttribute('opacity', o == null ? 1 : o);
      }
      function light(d) {   // highlight the direction being called
        [['g-al', -1, 'g-tl'], ['g-ar', 1, 'g-tr']].forEach(function (x) {
          var on = d === x[1], g = $(x[0]);
          g.setAttribute('opacity', on ? 1 : .55);
          g.firstChild.setAttribute('fill', on ? '#E3C355' : 'rgba(7,14,36,.6)'); g.firstChild.setAttribute('stroke', on ? '#E3C355' : '#fff');
          g.lastChild.setAttribute('stroke', on ? '#0D1B3E' : '#fff');
          $(x[2]).style.color = on ? '#E3C355' : '#fff'; $(x[2]).style.opacity = on ? 1 : .6;
        });
      }
      function next(now) {
        st = 'wait'; pick = null; dive = null; tShot = now + rnd(850, 1550); T = Math.max(560, 1050 - S.made * 34);
        $('g-striker').innerHTML = A.playerG(A.OPP_ST, { arms: 'down', skin: '#E0A878', hair: '#3A2413', logo: false });
        $('g-striker').setAttribute('transform', 'translate(756 262) scale(.44)');
        meFig('ready'); meAt(800, 677, 0); ballAt(800, 446, 10); $('g-bsh').setAttribute('transform', 'translate(800 456)'); $('g-bsh').setAttribute('opacity', 1);
        $('g-fx').innerHTML = ''; $('g-pills').innerHTML = ''; light(9);
        $('g-call').textContent = 'GET READY…'; $('g-call').style.color = '#fff'; $('g-panel').style.borderColor = 'rgba(255,255,255,.18)'; $('g-win').style.width = '0';
      }
      function commit(d, now) {
        if (pick !== null || (st !== 'wait' && st !== 'shot') || S.phase !== 'play') return;
        pick = d; tPick = now; meFig('up');
        dive = { x: 800 + d * 255, y: d ? 705 : 677, a: d * 68, t: now };
      }
      function resolve(now) {
        var ok = pick === dir, pts = 0, g = TG[dir];
        if (ok) {
          var react = clamp((tPick - t0) / T, 0, 1), quick = Math.round(40 * (1 - react)), bonus = Math.min(40, 8 * S.streak);
          pts = 100 + quick + bonus;
          $('g-fx').innerHTML = banner('SAVED!', '#C9A227', 150, 800, 412);
          $('g-pills').innerHTML = '<span class="pill gold">+' + (100 + quick) + ' · ' + (quick >= 27 ? 'LIGHTNING REFLEXES' : quick >= 15 ? 'QUICK HANDS' : 'GOOD SAVE') + '</span>' +
            (bonus ? '<span class="pill">STREAK · ' + (S.streak + 1) + ' IN A ROW · +' + bonus + '</span>' : '');
          sfx('save');
        } else {
          $('g-fx').innerHTML = banner('BEATEN!', '#D8452E', 150, 800, 412);
          $('g-pills').innerHTML = '<span class="pill">' + (pick === null ? 'TOO LATE' : 'WRONG WAY') + '</span>';
          sfx('goal');
        }
        record(ok, pts, (dir + 1) / 2 * .8 + .1, dir ? .62 : .4);
        st = 'result'; t0 = now; tEnd = now + 1150;
        $('g-call').textContent = ok ? 'GREAT SAVE!' : 'GOAL CONCEDED'; $('g-win').style.width = '0'; light(9);
        dive = dive || { x: 800, y: 677, a: 0, t: now };
        dive.ok = ok; dive.g = g;
      }
      return {
        svg: svgStr, hud: hudStr,
        init: function () { next(performance.now()); },
        begin: function (now) { next(now); },
        key: function (k) { var d = k === 'ArrowLeft' ? -1 : k === 'ArrowRight' ? 1 : (k === 'ArrowDown' || k === 'Space') ? 0 : null; if (d !== null) commit(d, performance.now()); },
        point: function (p, down) { if (down) commit(p.x < 533 ? -1 : p.x > 1067 ? 1 : 0, performance.now()); },
        tick: function (now) {
          if (dive) { var du = easeOut((now - dive.t) / 300); meAt(lerp(800, dive.x, du), lerp(677, dive.y, du), dive.a * du); }
          if (st === 'wait') {
            if (S.remain <= 0) return finish();
            $('g-striker').setAttribute('transform', 'translate(756 ' + (262 + Math.sin(now / 160) * 3).toFixed(1) + ') scale(.44)');
            if (now >= tShot) {
              // a keeper who has already gone gets sent the wrong way most of the time
              var all = [-1, -1, 0, 1, 1];
              dir = all[Math.floor(Math.random() * 5)];
              if (pick !== null && Math.random() < .8) { var o = [-1, 0, 1].filter(function (c) { return c !== pick; }); dir = o[Math.floor(Math.random() * 2)]; }
              st = 'shot'; t0 = now; sfx('kick'); light(dir);
              $('g-call').textContent = 'SHOT INCOMING · ' + NAME[dir]; $('g-call').style.color = '#E3C355'; $('g-panel').style.borderColor = '#E3C355';
            }
          } else if (st === 'shot') {
            var u = clamp((now - t0) / T, 0, 1), g = TG[dir], e = u * u * .55 + u * .45;
            var bx = lerp(800, g.x, e), by = lerp(446, g.y, e) - Math.sin(u * Math.PI) * 70;
            ballAt(bx, by, lerp(10, 54, e));
            $('g-bsh').setAttribute('transform', 'translate(' + bx.toFixed(0) + ' ' + lerp(456, 700, e).toFixed(0) + ') scale(' + lerp(1, 4, e).toFixed(2) + ')'); $('g-bsh').setAttribute('opacity', (.9 - e * .5).toFixed(2));
            $('g-win').style.width = ((1 - u) * 100).toFixed(1) + '%';
            if (u >= 1) resolve(now);
          } else if (st === 'result') {
            var w = clamp((now - t0) / 480, 0, 1), q = easeOut(w), t = dive.g;
            if (dive.ok) ballAt(t.x + dir * 120 * q, t.y - 150 * q + 60 * w * w, lerp(54, 30, q));
            else ballAt(t.x + dir * 70 * q, t.y + 150 * q, lerp(54, 96, q), 1 - w);
            $('g-bsh').setAttribute('opacity', 0);
            if (now >= tEnd) { if (S.remain <= 0) finish(); else next(now); }
          }
        }
      };
    }

    return { stop: stop };
  }

  window.Game = { start: start };
})();
