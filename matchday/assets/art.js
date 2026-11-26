/* HP Matchday · drawing library. Every illustration (kits, players, ball, stadium) is drawn here as SVG.
   The only logo anywhere is HP's: no sponsor, kit-maker or club marks. */
(function () {
  'use strict';
  var NAVY = '#0D1B3E', HP = '#024AD8', GOLD = '#C9A227';
  var FONT = 'Inter Tight,Inter,sans-serif';

  // Official HP logo artwork (from /assets/img/hp.svg), drawn in a 100 x 100 box
  var HPG = '<g transform="matrix(1.25,0,0,-1.25,-67.822513,702.43127)"><g transform="matrix(1.5180439,0,0,1.5180439,-28.149474,-249.71011)"><g transform="translate(106.93201,508.32201)"><path d="m 0,0 c 0,14.524 -11.773,26.297 -26.297,26.297 -0.396,0 -0.79,-0.01 -1.182,-0.028 l -5.379,-14.784 4.685,0 c 2.787,0 4.289,-2.146 3.335,-4.767 l -6.635,-18.234 -5.571,10e-4 7.12,19.544 -4.189,0 -7.12,-19.544 -5.573,0 8.372,23 10e-4,0 5.036,13.841 C -44.471,22.228 -52.594,12.063 -52.594,0 c 0,-12.421 8.613,-22.83 20.192,-25.583 l 4.88,13.411 0.004,0 8.609,23.657 10.261,0 c 2.79,0 4.291,-2.146 3.337,-4.767 l -5.83,-16.015 c -0.444,-1.22 -1.869,-2.218 -3.167,-2.218 l -7.396,0 -5.374,-14.77 c 0.259,-0.007 0.52,-0.012 0.781,-0.012 C -11.773,-26.297 0,-14.523 0,0"/></g><g transform="translate(96.546997,516.36501)"><path d="m 0,0 -4.187,0 -5.864,-16.089 4.187,0 L 0,0"/></g></g></g>';

  function f1(n) { return (Math.round(n * 10) / 10).toString(); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function rng(seed) {   // small seeded generator, so the crowd and confetti look the same every time
    var a = seed >>> 0;
    return function () { a |= 0; a = a + 0x6D2B79F5 | 0; var t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  }

  /** HP logo inside an SVG: a disc in `fg` with the mark in `bg`. */
  function hp(cx, cy, r, bg, fg) {
    bg = bg || HP; fg = fg || '#fff';
    return '<circle cx="' + cx + '" cy="' + cy + '" r="' + f1(r * .92) + '" fill="' + fg + '"/>' +
      '<svg x="' + f1(cx - r) + '" y="' + f1(cy - r) + '" width="' + f1(2 * r) + '" height="' + f1(2 * r) + '" viewBox="0 0 100 100"><g fill="' + bg + '">' + HPG + '</g></svg>';
  }
  /** HP logo as a standalone element. */
  function hph(size, bg, fg) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 100 100" style="display:block;flex:none">' +
      '<circle cx="50" cy="50" r="46" fill="' + (fg || '#fff') + '"/><g fill="' + (bg || HP) + '">' + HPG + '</g></svg>';
  }

  // ------------------------------------------------------------ the five kits
  var KITS = {
    white: { label: 'White', kit: '#FFFFFF', inner: '#D5DBE5', trim: '#14161B', cw: 5, pipe: '#E3A52A', tape: '#14161B', collar: 'crew', pattern: null, ink: '#14161B', hpbg: HP, hpfg: '#fff', shorts: '#FFFFFF', socks: '#FFFFFF', band: '#14161B' },
    black: { label: 'Black', kit: '#15161A', inner: '#000000', trim: '#FFFFFF', cw: 9, pipe: null, tape: '#FFFFFF', collar: 'polo', pattern: 'diamond', ink: '#FFFFFF', hpbg: HP, hpfg: '#fff', shorts: '#15161A', socks: '#15161A', band: '#FFFFFF' },
    blue: { label: 'Blue', kit: '#1F6FD6', inner: '#134C9C', trim: '#FFFFFF', cw: 8, pipe: null, tape: '#FFFFFF', collar: 'crew', pattern: 'diag', ink: '#FFFFFF', hpbg: '#FFFFFF', hpfg: HP, shorts: '#1F6FD6', socks: '#1F6FD6', band: '#FFFFFF' },
    navy: { label: 'Navy', kit: '#3B456F', inner: '#252C4C', trim: '#BDEBC8', cw: 3.5, pipe: '#BDEBC8', tape: null, collar: 'crew', pattern: 'hstripe', ink: '#E4E9F4', hpbg: HP, hpfg: '#fff', shorts: '#3B456F', socks: '#3B456F', band: '#BDEBC8' },
    green: { label: 'Green', kit: '#0F4A43', inner: '#072E2A', trim: '#EFE8CF', cw: 9, pipe: null, tape: '#EFE8CF', collar: 'vnotch', pattern: 'dots', ink: '#EFE8CF', hpbg: HP, hpfg: '#fff', shorts: '#0F4A43', socks: '#0F4A43', band: '#EFE8CF' }
  };
  var ORDER = ['white', 'black', 'blue', 'navy', 'green'];
  var OPP_GK = { kit: '#FF7A1A', trim: '#14161B', ink: '#14161B', pipe: null, tape: null, shorts: '#14161B', socks: '#FF7A1A', band: '#14161B' };
  var OPP_ST = { kit: '#B8232F', trim: '#FFFFFF', ink: '#FFFFFF', pipe: null, tape: null, shorts: '#FFFFFF', socks: '#B8232F', band: '#FFFFFF' };
  function kitOf(k) { return typeof k === 'string' ? (KITS[k] || KITS.white) : k; }

  var uid = 0;
  /** Flat jersey. Front: HP logo only. Back: player name (optional) and number. */
  function jersey(k, o) {
    o = o || {};
    var K = kitOf(k), side = o.side || 'front', w = o.w || 330, u = ++uid;
    var d = { crew: 74, polo: 72, vnotch: 92 }[K.collar];
    var neck = side === 'back' ? 'Q200 54 150 38' : K.collar === 'vnotch' ? 'L200 ' + d + ' L150 38' : 'Q200 ' + d + ' 150 38';
    var body = 'M150 38 L62 74 L18 150 L78 176 L104 132 L100 410 Q200 424 300 410 L296 132 L322 176 L382 150 L338 74 L250 38 ' + neck + ' Z';
    var s = '<svg viewBox="0 0 400 440" width="' + w + '" style="display:block;flex:none;' + (o.shadow === false ? '' : 'filter:drop-shadow(0 18px 20px rgba(13,27,62,.22))') + '">';
    s += '<defs><clipPath id="jc' + u + '"><path d="' + body + '"/></clipPath></defs>';
    if (side === 'front') {
      s += '<path d="M150 38 Q200 54 250 38 L250 60 L200 ' + (d + 6) + ' L150 60Z" fill="' + K.inner + '"/>';
      s += '<path d="M150 38 Q200 54 250 38" fill="none" stroke="' + K.trim + '" stroke-width="5"/>';
    }
    s += '<path d="' + body + '" fill="' + K.kit + '"/><g clip-path="url(#jc' + u + ')">';
    if (K.pattern) s += '<rect width="400" height="440" fill="url(#p-' + K.pattern + ')"/>';
    s += '<rect width="400" height="440" fill="url(#jshade)"/>';
    s += '<path d="M118 52 Q98 92 104 132M282 52 Q302 92 296 132" fill="none" stroke="rgba(0,0,0,.16)" stroke-width="1.5"/>';
    if (K.tape) s += '<path d="M151 48 L68 83 L28 151M249 48 L332 83 L372 151" fill="none" stroke="' + K.tape + '" stroke-width="7" stroke-linejoin="round"/>';
    if (K.pipe) s += '<path d="M158 46 Q118 86 111 136 L107 414M242 46 Q282 86 289 136 L293 414" fill="none" stroke="' + K.pipe + '" stroke-width="2.6"/>';
    s += '<path d="M18 150 L78 176 L83 165 L23 139ZM382 150 L322 176 L317 165 L377 139Z" fill="' + K.trim + '"/></g>';
    s += '<path d="' + body + '" fill="none" stroke="rgba(13,27,62,.24)" stroke-width="1.5" stroke-linejoin="round"/>';
    if (side === 'back') {
      if (K.collar === 'polo') s += '<path d="M146 34 Q200 50 254 34 L258 52 Q200 70 142 52Z" fill="' + K.trim + '" stroke="rgba(13,27,62,.2)"/>';
      else s += '<path d="M150 38 Q200 54 250 38" fill="none" stroke="' + K.trim + '" stroke-width="' + K.cw + '"/>';
      var nm = esc((o.name || '').toUpperCase()), fs = nm.length > 9 ? 22 : nm.length > 7 ? 26 : 30;
      if (nm) s += '<text x="200" y="134" text-anchor="middle" font-size="' + fs + '" font-weight="700" letter-spacing="3" fill="' + K.ink + '" font-family="' + FONT + '">' + nm + '</text>';
      if (o.num != null && o.num !== '') s += '<text x="200" y="300" text-anchor="middle" font-size="156" font-weight="800" letter-spacing="-4" fill="' + K.ink + '" font-family="' + FONT + '">' + esc(o.num) + '</text>';
    } else {
      if (K.collar === 'polo') {
        s += '<path d="M146 34 L200 62 L181 98 L134 54Z M254 34 L200 62 L219 98 L266 54Z" fill="' + K.trim + '" stroke="rgba(13,27,62,.22)" stroke-width="1.2"/>';
        s += '<path d="M200 64V108" stroke="rgba(255,255,255,.22)" stroke-width="1.5"/><circle cx="200" cy="90" r="3" fill="#3a3c44"/>';
      } else if (K.collar === 'vnotch') {
        s += '<path d="M150 38 L200 ' + d + ' L250 38" fill="none" stroke="' + K.trim + '" stroke-width="' + K.cw + '"/>';
        s += '<path d="M191 ' + (d + 2) + ' L209 ' + (d + 2) + ' L200 ' + (d + 22) + 'Z" fill="' + K.inner + '"/>';
      } else s += '<path d="M150 38 Q200 ' + d + ' 250 38" fill="none" stroke="' + K.trim + '" stroke-width="' + K.cw + '"/>';
      s += hp(200, 190, 36, K.hpbg, K.hpfg);
    }
    return s + '</svg>';
  }

  // ------------------------------------------------------------ player figure (200 x 430 units)
  var ARMS = { down: 7, ready: 58, up: 150, cheer: 138 };
  function arm(side, ang, K, skin, gloves) {
    var g = '<g transform="translate(' + (100 + side * 47) + ' 113) rotate(' + (-side * ang) + ')">';
    g += '<path d="M0 44V114" stroke="' + skin + '" stroke-width="16" stroke-linecap="round"/>';
    g += gloves ? '<rect x="-14" y="100" width="28" height="38" rx="12" fill="' + gloves + '" stroke="rgba(13,27,62,.3)"/><rect x="-13" y="98" width="26" height="8" rx="3" fill="#FFFFFF"/>'
      : '<circle cy="120" r="10" fill="' + skin + '"/>';
    g += '<path d="M-15 46 V0 A15 15 0 0 1 15 0 V46Z" fill="' + K.kit + '" stroke="rgba(13,27,62,.22)" stroke-width="1.2"/>';
    return g + '<rect x="-15" y="40" width="30" height="6" fill="' + K.trim + '"/></g>';
  }
  /** opts: view front|back, arms down|ready|up|cheer, name, num, skin, hair, style short|long|shaved, gloves, logo, shadow */
  function playerG(k, o) {
    o = o || {};
    var K = kitOf(k), view = o.view || 'front', ang = ARMS[o.arms || 'down'];
    var skin = o.skin || '#C98C64', hair = o.hair || '#1E140F', style = o.style || 'short';
    var long = style === 'long', ho = style === 'shaved' ? ' opacity=".32"' : '';
    var s = '';
    if (o.shadow !== false) s += '<ellipse cx="100" cy="416" rx="62" ry="9" fill="rgba(0,0,0,.24)"/>';
    if (long && view === 'front') s += '<path d="M67 44 Q58 98 72 114 L128 114 Q142 98 133 44Z" fill="' + hair + '"/>';
    [80, 120].forEach(function (cx) {
      s += '<rect x="' + (cx - 10) + '" y="262" width="20" height="70" rx="9" fill="' + skin + '"/>';
      s += '<rect x="' + (cx - 10.5) + '" y="322" width="21" height="76" rx="8" fill="' + K.socks + '" stroke="rgba(13,27,62,.2)"/>';
      s += '<rect x="' + (cx - 10.5) + '" y="322" width="21" height="7" fill="' + K.band + '"/>';
      s += '<rect x="' + (cx - 18) + '" y="392" width="36" height="22" rx="9" fill="#15171C"/>';
    });
    s += '<path d="M62 206 L138 206 L144 278 L104 278 L100 246 L96 278 L56 278Z" fill="' + K.shorts + '" stroke="rgba(13,27,62,.22)" stroke-width="1.2" stroke-linejoin="round"/>';
    s += arm(-1, ang, K, skin, o.gloves) + arm(1, ang, K, skin, o.gloves);
    if (view === 'back') s += '<rect x="90" y="78" width="20" height="26" rx="8" fill="' + skin + '"/>';
    s += '<path d="M72 98 Q100 114 128 98 L150 105 Q160 112 152 128 L138 142 L136 214 L64 214 L62 142 L48 128 Q40 112 50 105Z" fill="' + K.kit + '" stroke="rgba(13,27,62,.22)" stroke-width="1.2" stroke-linejoin="round"/>';
    if (K.tape) s += '<path d="M74 100 L50 107M126 100 L150 107" stroke="' + K.tape + '" stroke-width="4"/>';
    if (K.pipe) s += '<path d="M79 102 Q65 122 65 142 L66 212M121 102 Q135 122 135 142 L134 212" fill="none" stroke="' + K.pipe + '" stroke-width="1.6"/>';
    if (view === 'front') {
      s += '<path d="M88 82 h24 v16 q-12 12 -24 0z" fill="' + skin + '"/>';
      s += '<path d="M74 99 Q100 117 126 99" fill="none" stroke="' + K.trim + '" stroke-width="4.5"/>';
      if (o.logo !== false) s += hp(100, 142, 13, K.hpbg, K.hpfg);
    } else {
      s += '<path d="M76 99 Q100 109 124 99" fill="none" stroke="' + K.trim + '" stroke-width="4"/>';
      var nm = esc((o.name || '').toUpperCase());
      if (nm) s += '<text x="100" y="134" text-anchor="middle" font-size="' + (nm.length > 9 ? 8.5 : nm.length > 7 ? 10 : 11.5) + '" font-weight="700" letter-spacing="1.2" fill="' + K.ink + '" font-family="' + FONT + '">' + nm + '</text>';
      if (o.num != null && o.num !== '') s += '<text x="100" y="188" text-anchor="middle" font-size="50" font-weight="800" letter-spacing="-1" fill="' + K.ink + '" font-family="' + FONT + '">' + esc(o.num) + '</text>';
    }
    s += '<circle cx="70" cy="56" r="6.5" fill="' + skin + '"/><circle cx="130" cy="56" r="6.5" fill="' + skin + '"/>';
    s += '<ellipse cx="100" cy="52" rx="30" ry="34" fill="' + skin + '"/>';
    if (view === 'front') {
      s += '<path d="M69 50 C66 22 84 9 100 9 C118 9 134 22 131 50 C126 40 120 34 112 32 C98 37 84 37 69 50Z" fill="' + hair + '"' + ho + '/>';
      s += '<circle cx="89" cy="57" r="3.6" fill="#1F2430"/><circle cx="111" cy="57" r="3.6" fill="#1F2430"/>' +
        '<path d="M83 48q6-4 12 0M105 48q6-4 12 0" fill="none" stroke="#3A2A20" stroke-width="2.2" stroke-linecap="round"/>' +
        '<path d="M90 72 Q100 80 110 72" fill="none" stroke="#7A2E2E" stroke-width="2.8" stroke-linecap="round"/>';
    } else {
      s += '<path d="M68 48 C68 22 84 9 100 9 C116 9 132 22 132 48 L132 66 Q100 54 68 66Z" fill="' + hair + '"' + ho + '/>';
      if (long) s += '<path d="M91 48 Q84 94 93 118 Q100 126 107 118 Q116 94 109 48Z" fill="' + hair + '"/>';
    }
    return s;
  }
  /** The same player seen side-on, facing right (mirror it for the other side). */
  function playerSide(k, o) {
    o = o || {};
    var K = kitOf(k), skin = o.skin || '#C98C64', hair = o.hair || '#1E140F', style = o.style || 'short';
    var ho = style === 'shaved' ? ' opacity=".32"' : '', ln = 'stroke="rgba(13,27,62,.22)" stroke-width="1.2" stroke-linejoin="round"';
    var s = '<ellipse cx="102" cy="416" rx="44" ry="8" fill="rgba(0,0,0,.24)"/>';
    if (style === 'long') s += '<path d="M76 46 Q62 92 74 122 L94 118 Q88 84 94 54Z" fill="' + hair + '"/>';
    [108, 94].forEach(function (cx) {   // far leg, then near leg
      s += '<g><rect x="' + (cx - 10) + '" y="262" width="20" height="70" rx="9" fill="' + skin + '"/>';
      s += '<rect x="' + (cx - 10.5) + '" y="322" width="21" height="76" rx="8" fill="' + K.socks + '" stroke="rgba(13,27,62,.2)"/>';
      s += '<rect x="' + (cx - 10.5) + '" y="322" width="21" height="7" fill="' + K.band + '"/>';
      s += '<path d="M' + (cx - 13) + ' 401 q0 -9 9 -9 h14 q20 2 22 14 q0 8 -8 8 h-28 q-9 0 -9 -9z" fill="#15171C"/></g>';
    });
    s += '<path d="M76 206 L124 206 L128 278 L82 278Z" fill="' + K.shorts + '" ' + ln + '/>';
    s += '<rect x="91" y="78" width="20" height="28" rx="8" fill="' + skin + '"/>';
    s += '<path d="M80 108 Q100 94 120 106 Q130 150 124 214 L76 214 Q72 150 80 108Z" fill="' + K.kit + '" ' + ln + '/>';
    if (K.pipe) s += '<path d="M118 110 Q126 150 121 212" fill="none" stroke="' + K.pipe + '" stroke-width="1.6"/>';
    s += '<path d="M84 103 Q100 112 118 102" fill="none" stroke="' + K.trim + '" stroke-width="4.5"/>';
    s += '<path d="M99 150 V224" stroke="' + skin + '" stroke-width="15" stroke-linecap="round"/><circle cx="99" cy="230" r="9.5" fill="' + skin + '"/>';
    s += '<path d="M84 112 Q99 98 114 112 L113 156 L85 156Z" fill="' + K.kit + '" ' + ln + '/>';
    if (K.tape) s += '<path d="M99 104 V150" stroke="' + K.tape + '" stroke-width="4"/>';
    s += '<rect x="85" y="150" width="28" height="6" fill="' + K.trim + '"/>';
    s += '<ellipse cx="102" cy="52" rx="29" ry="34" fill="' + skin + '"/><path d="M129 50 q9 7 1 13z" fill="' + skin + '"/>';
    s += '<ellipse cx="96" cy="57" rx="6" ry="8" fill="' + skin + '" stroke="rgba(0,0,0,.14)" stroke-width="1.5"/>';
    s += '<path d="M73 60 C68 26 86 8 106 9 C122 10 133 22 131 42 C124 36 114 33 106 34 C104 46 100 54 90 58 L88 70 Q78 70 73 60Z" fill="' + hair + '"' + ho + '/>';
    s += '<circle cx="119" cy="55" r="3.5" fill="#1F2430"/><path d="M113 46q6-4 11 0" fill="none" stroke="#3A2A20" stroke-width="2.2" stroke-linecap="round"/>' +
      '<path d="M117 73 q6 3 11 -1" fill="none" stroke="#7A2E2E" stroke-width="2.6" stroke-linecap="round"/>';
    return s;
  }
  function player(k, h, o) {
    return '<svg viewBox="0 0 200 430" height="' + h + '" style="display:block;flex:none;overflow:visible">' + (o && o.view === 'side' ? playerSide(k, o) : playerG(k, o)) + '</svg>';
  }
  /** Head-and-shoulders avatar (200 x 230 units). */
  function bust(k, w, o) {
    o = o || {};
    var K = kitOf(k), skin = o.skin || '#C98C64', hair = o.hair || '#1E140F', style = o.style || 'short';
    var s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 230" width="' + w + '" height="' + f1(w * 1.15) + '" style="display:block;flex:none">';
    s += '<path d="M18 230c0-52 34-80 82-80s82 28 82 80z" fill="' + K.kit + '" stroke="rgba(13,27,62,.2)" stroke-width="1.2"/>';
    if (K.pipe) s += '<path d="M60 160 Q40 186 38 230M140 160 Q160 186 162 230" fill="none" stroke="' + K.pipe + '" stroke-width="2.4"/>';
    if (K.tape) s += '<path d="M66 155 Q40 166 26 196M134 155 Q160 166 174 196" fill="none" stroke="' + K.tape + '" stroke-width="5"/>';
    s += '<path d="M72 156 q28 24 56 0" fill="none" stroke="' + K.trim + '" stroke-width="6"/>';
    s += hp(100, 205, 15, K.hpbg, K.hpfg);
    if (style === 'long') s += '<path d="M50 70q-10 80 8 104q16 12 34 2V60zM150 70q10 80-8 104q-16 12-34 2V60z" fill="' + hair + '"/>';
    s += '<rect x="84" y="128" width="32" height="30" rx="10" fill="' + skin + '"/>';
    s += '<ellipse cx="54" cy="94" rx="11" ry="15" fill="' + skin + '"/><ellipse cx="146" cy="94" rx="11" ry="15" fill="' + skin + '"/>';
    s += '<rect x="54" y="34" width="92" height="112" rx="46" fill="' + skin + '"/>';
    s += '<path d="M52 84c-4-36 18-60 50-60 30 0 52 20 48 58-6-14-14-24-26-28-22 4-46 6-72 30z" fill="' + hair + '"' + (style === 'shaved' ? ' opacity=".32"' : '') + '/>';
    s += '<ellipse cx="82" cy="94" rx="5.5" ry="6.5" fill="#1F2430"/><ellipse cx="118" cy="94" rx="5.5" ry="6.5" fill="#1F2430"/>' +
      '<path d="M72 80q10-6 20 0M108 80q10-6 20 0" fill="none" stroke="#3A2A20" stroke-width="3.5" stroke-linecap="round"/>' +
      '<path d="M97 102q3 10-3 13" fill="none" stroke="rgba(0,0,0,.18)" stroke-width="3" stroke-linecap="round"/>' +
      '<path d="M84 120q16 12 32 0" fill="none" stroke="#7A2E2E" stroke-width="4" stroke-linecap="round"/></svg>';
    return s;
  }

  function ballG(r) {   // ball centred on 0,0
    var s = '<circle r="' + r + '" fill="url(#ballg)" stroke="#0D1B3E" stroke-width="' + f1(Math.max(1, r * .05)) + '"/>', pts = [], i;
    for (i = 0; i < 5; i++) { var a = (-90 + 72 * i) * Math.PI / 180; pts.push([Math.cos(a), Math.sin(a)]); }
    s += '<polygon points="' + pts.map(function (p) { return f1(p[0] * r * .36) + ',' + f1(p[1] * r * .36); }).join(' ') + '" fill="#0D1B3E"/>';
    pts.forEach(function (p) {
      s += '<path d="M' + f1(p[0] * r * .36) + ' ' + f1(p[1] * r * .36) + 'L' + f1(p[0] * r * .74) + ' ' + f1(p[1] * r * .74) + '" stroke="#0D1B3E" stroke-width="' + f1(Math.max(1, r * .07)) + '"/>';
      var a = Math.atan2(p[1], p[0]);
      s += '<polygon points="' + [[-.34, .97], [-.2, .74], [.2, .74], [.34, .97]].map(function (e) { return f1(Math.cos(a + e[0]) * e[1] * r) + ',' + f1(Math.sin(a + e[0]) * e[1] * r); }).join(' ') + '" fill="#0D1B3E"/>';
    });
    return s;
  }
  function ball(cx, cy, r, shadow) {
    return '<g transform="translate(' + cx + ' ' + cy + ')">' +
      (shadow === false ? '' : '<ellipse cy="' + f1(r * 1.02) + '" rx="' + f1(r * 1.15) + '" ry="' + f1(r * .22) + '" fill="rgba(0,0,0,.28)"/>') + ballG(r) + '</g>';
  }

  // ------------------------------------------------------------ stadium
  function crowd(pid, seed) {
    var R = rng(seed), cs = ['#FFFFFF', '#E8EFF7', '#C9A227', '#8FA3C8', '#2F55A8', '#FFFFFF', '#B7C4DC', '#F3D98A'], d = '', i;
    for (i = 0; i < 150; i++) d += '<circle cx="' + f1(R() * 140) + '" cy="' + f1(R() * 44) + '" r="' + f1(1.5 + R() * 1.2) + '" fill="' + cs[Math.floor(R() * cs.length)] + '" opacity="' + (.3 + R() * .6).toFixed(2) + '"/>';
    return '<pattern id="' + pid + '" width="140" height="44" patternUnits="userSpaceOnUse"><rect width="140" height="44" fill="#13224C"/>' + d + '</pattern>';
  }
  function boards(y, h, n, fs) {
    n = n || 8; fs = fs || 15;
    var segs = [[HP, '#fff', null], ['#FFFFFF', NAVY, 'FROM PITCH TO TECH'], [NAVY, '#E3C355', 'EVERY TOUCH COUNTS'], [HP, '#fff', 'HP MATCHDAY']], w = 1600 / n, s = '', i;
    for (i = 0; i < n; i++) {
      var g = segs[i % 4], x = i * w;
      s += '<rect x="' + f1(x) + '" y="' + y + '" width="' + f1(w) + '" height="' + h + '" fill="' + g[0] + '"/>';
      s += g[2] ? '<text x="' + f1(x + w / 2) + '" y="' + f1(y + h / 2 + fs * .36) + '" text-anchor="middle" font-size="' + fs + '" font-weight="700" letter-spacing="2" fill="' + g[1] + '" font-family="' + FONT + '">' + g[2] + '</text>'
        : hp(x + w / 2, y + h / 2, h * .4, '#FFFFFF', HP);
    }
    return s + '<rect y="' + y + '" width="1600" height="' + h + '" fill="url(#boardgloss)"/><path d="M0 ' + (y + h) + 'H1600" stroke="rgba(0,0,0,.35)" stroke-width="2"/>';
  }
  function stands(tiers, bow, roofY, roofDip) {
    var s = '<g filter="url(#soft)">', x;
    tiers.forEach(function (t) {
      var a = t[0], b = t[1];
      s += '<path d="M0 ' + (a + bow) + ' Q800 ' + (a - bow) + ' 1600 ' + (a + bow) + ' V' + (b + bow) + ' Q800 ' + (b - bow) + ' 0 ' + (b + bow) + 'Z" fill="url(#crowd)"/>';
      s += '<path d="M0 ' + (b + bow) + ' Q800 ' + (b - bow) + ' 1600 ' + (b + bow) + ' v9 Q800 ' + (b - bow + 9) + ' 0 ' + (b + bow + 9) + 'Z" fill="#070F26"/>';
      s += '<path d="M0 ' + (b + bow + 2) + ' Q800 ' + (b - bow + 2) + ' 1600 ' + (b + bow + 2) + '" fill="none" stroke="#C9A227" stroke-width="1.4" opacity=".55"/>';
    });
    s += '</g><path d="M0 0H1600V' + roofY + ' Q800 ' + (roofY - roofDip) + ' 0 ' + roofY + 'Z" fill="#050A1A"/>';
    for (x = 50; x < 1600; x += 62) {
      var t = x / 1600, y = roofY - roofDip * 2 * t * (1 - t);
      s += '<circle cx="' + x + '" cy="' + f1(y - 4) + '" r="46" fill="url(#glow)" opacity=".32"/><rect x="' + (x - 15) + '" y="' + f1(y - 8) + '" width="30" height="6" rx="2" fill="#FFFFFF"/>';
    }
    return s;
  }
  function confetti(n, seed) {
    var R = rng(seed || 7), cs = ['#E3C355', '#FFFFFF', HP, '#E3C355'], s = '', i;
    for (i = 0; i < n; i++) {
      var x = 40 + R() * 1520, y = 90 + R() * 710;
      s += '<rect x="' + f1(x) + '" y="' + f1(y) + '" width="' + f1(8 + R() * 8) + '" height="' + f1(5 + R() * 4) + '" fill="' + cs[Math.floor(R() * 4)] + '" opacity="' + (.7 + R() * .3).toFixed(2) + '" transform="rotate(' + Math.floor(R() * 180) + ' ' + f1(x) + ' ' + f1(y) + ')"/>';
    }
    return s;
  }

  /** Stadium behind the goal, as the penalty taker sees it. Static parts only. */
  function pitchShoot() {
    var s = '<rect width="1600" height="900" fill="url(#sky)"/>' + stands([[66, 148], [160, 236], [248, 314]], 20, 84, 44);
    s += '<rect y="60" width="1600" height="300" fill="url(#haze)"/>' + boards(322, 32) + '<rect y="354" width="1600" height="546" fill="url(#pitch)"/>';
    var k = (900 - 250) / (354 - 250), i;
    for (i = -12; i < 12; i += 2) s += '<polygon points="' + (800 + i * 86) + ',354 ' + (800 + (i + 1) * 86) + ',354 ' + Math.round(800 + (i + 1) * 86 * k) + ',900 ' + Math.round(800 + i * 86 * k) + ',900" fill="rgba(255,255,255,.05)"/>';
    s += '<rect y="354" width="1600" height="46" fill="url(#shade-top)"/>';
    s += '<path d="M0 402H1600" stroke="#fff" stroke-width="4" opacity=".92"/><path d="M0 482H1600" stroke="#fff" stroke-width="6" opacity=".9"/>';
    s += '<path d="M440 416 L466 402 H1134 L1160 416Z" fill="rgba(0,0,0,.2)"/><path d="M476 186H1124V402H476Z" fill="rgba(5,10,28,.52)"/>';
    s += '<rect x="512" y="206" width="576" height="178" fill="url(#net)"/><path d="M476 186 L1124 186 L1088 206 L512 206Z" fill="url(#net)"/>';
    s += '<path d="M476 186 L512 206 L512 384 L476 402Z M1124 186 L1088 206 L1088 384 L1124 402Z" fill="url(#net)"/><path d="M512 384H1088" stroke="rgba(255,255,255,.55)" stroke-width="2"/>';
    return s;
  }
  var GOAL_FRAME = '<rect x="464" y="174" width="12" height="228" fill="url(#post)"/><rect x="1124" y="174" width="12" height="228" fill="url(#post)"/><rect x="464" y="174" width="672" height="12" rx="2" fill="url(#bar)"/>';

  /** Pitch as the goalkeeper sees it, looking out from the goal line. Static parts only. */
  function pitchKeep() {
    var s = '<rect width="1600" height="900" fill="url(#sky)"/>' + stands([[52, 112], [122, 190]], 10, 62, 26);
    s += '<rect y="40" width="1600" height="200" fill="url(#haze)"/>' + boards(198, 24, 10, 11) + '<rect y="224" width="1600" height="676" fill="url(#pitch)"/>';
    var k = (900 - 120) / (224 - 120), i;
    for (i = -34; i < 34; i += 2) s += '<polygon points="' + (800 + i * 24) + ',224 ' + (800 + (i + 1) * 24) + ',224 ' + Math.round(800 + (i + 1) * 24 * k) + ',900 ' + Math.round(800 + i * 24 * k) + ',900" fill="rgba(255,255,255,.05)"/>';
    s += '<rect y="224" width="1600" height="40" fill="url(#shade-top)"/>';
    s += '<g fill="none" stroke="#fff" opacity=".9"><path d="M0 250H1600" stroke-width="2.5"/><ellipse cx="800" cy="250" rx="130" ry="11" stroke-width="2.5"/>' +
      '<path d="M-260 760 L300 352 H1300 L1860 760" stroke-width="5"/><path d="M640 352 Q800 314 960 352" stroke-width="4"/><path d="M-80 900 L240 628 H1360 L1680 900" stroke-width="7"/></g>';
    s += '<path d="M772 224v-16h56v16" fill="none" stroke="#fff" stroke-width="2.5"/><ellipse cx="800" cy="452" rx="10" ry="4" fill="#fff"/>';
    return s;
  }
  var KEEP_FRAME = '<rect x="16" y="12" width="1568" height="26" rx="4" fill="url(#bar)"/><rect x="16" y="12" width="26" height="900" fill="url(#post)"/><rect x="1558" y="12" width="26" height="900" fill="url(#post)"/>';

  /** Shared patterns and gradients. Inserted once per page. */
  function defs() {
    return '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>' +
      '<symbol id="stripes" viewBox="0 0 150 88"><path d="M32 0h30L30 88H0zM102 0h30l-32 88H70z" fill="#024AD8"/></symbol>' +
      '<linearGradient id="jshade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#000" stop-opacity=".16"/><stop offset=".3" stop-color="#000" stop-opacity="0"/><stop offset=".7" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".16"/></linearGradient>' +
      '<pattern id="p-diamond" width="34" height="34" patternUnits="userSpaceOnUse"><path d="M17 3 L31 17 L17 31 L3 17Z" fill="none" stroke="#fff" stroke-opacity=".075" stroke-width="1.6"/></pattern>' +
      '<pattern id="p-diag" width="30" height="30" patternUnits="userSpaceOnUse" patternTransform="rotate(38)"><rect width="15" height="30" fill="#fff" fill-opacity=".075"/></pattern>' +
      '<pattern id="p-hstripe" width="400" height="11" patternUnits="userSpaceOnUse"><rect width="400" height="4.5" fill="#fff" fill-opacity=".07"/></pattern>' +
      '<pattern id="p-dots" width="13" height="13" patternUnits="userSpaceOnUse"><circle cx="6.5" cy="6.5" r="1.7" fill="#fff" fill-opacity=".1"/></pattern>' +
      '<pattern id="net" width="15" height="15" patternUnits="userSpaceOnUse"><path d="M0 0H15M0 0V15" stroke="#fff" stroke-opacity=".6" stroke-width="1.3"/></pattern>' +
      crowd('crowd', 11) +
      '<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#060C1F"/><stop offset=".4" stop-color="#12234F"/><stop offset="1" stop-color="#1C3A78"/></linearGradient>' +
      '<linearGradient id="haze" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9DB8F2" stop-opacity=".2"/><stop offset="1" stop-color="#9DB8F2" stop-opacity="0"/></linearGradient>' +
      '<linearGradient id="pitch" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#34A065"/><stop offset=".5" stop-color="#27824F"/><stop offset="1" stop-color="#1A6239"/></linearGradient>' +
      '<linearGradient id="shade-top" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#000" stop-opacity=".3"/><stop offset="1" stop-color="#000" stop-opacity="0"/></linearGradient>' +
      '<linearGradient id="boardgloss" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".18"/></linearGradient>' +
      '<linearGradient id="post" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#B9C3D4"/></linearGradient>' +
      '<linearGradient id="bar" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#B9C3D4"/></linearGradient>' +
      '<radialGradient id="glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fff" stop-opacity=".95"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>' +
      '<radialGradient id="ballg" cx=".36" cy=".3" r=".8"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#C9D1DE"/></radialGradient>' +
      '<radialGradient id="vig" cx=".5" cy=".5" r=".75"><stop offset=".6" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".42"/></radialGradient>' +
      '<filter id="soft" x="-2%" y="-10%" width="104%" height="120%"><feGaussianBlur stdDeviation="0.9"/></filter>' +
      '</defs></svg>';
  }

  window.Art = {
    NAVY: NAVY, HP: HP, GOLD: GOLD, FONT: FONT, KITS: KITS, ORDER: ORDER, OPP_GK: OPP_GK, OPP_ST: OPP_ST,
    esc: esc, rng: rng, hp: hp, hph: hph, jersey: jersey, playerG: playerG, player: player, bust: bust,
    ball: ball, ballG: ballG, confetti: confetti, pitchShoot: pitchShoot, pitchKeep: pitchKeep,
    GOAL_FRAME: GOAL_FRAME, KEEP_FRAME: KEEP_FRAME, defs: defs
  };
})();
