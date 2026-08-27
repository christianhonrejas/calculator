<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Display – Diozabeth Fitness</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@400;600;700;800;900&family=Barlow+Condensed:wght@600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ── Reset ─────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

:root {
  --gold:       #f5c518;
  --gold-dim:   rgba(245,197,24,.12);
  --gold-glow:  0 0 28px rgba(245,197,24,.45);
  --bg:         #060b12;
  --panel:      #0c1420;
  --border:     rgba(245,197,24,.13);
  --text:       #dce6f2;
  --muted:      #4a5a6e;
  --walkin-c:   #38bdf8;
  --walkin-bg:  rgba(56,189,248,.10);
  --walkin-bdr: rgba(56,189,248,.25);
  --sub-c:      #a78bfa;
  --sub-bg:     rgba(167,139,250,.10);
  --sub-bdr:    rgba(167,139,250,.25);
  --green:      #22c55e;
}

html, body { height:100%; background:var(--bg); color:var(--text); font-family:'Barlow',sans-serif; overflow:hidden; }

/* ── Animated background grid ──────────────────────────────── */
.bg-grid {
  position:fixed; inset:0; z-index:0;
  background-image:
    linear-gradient(rgba(245,197,24,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(245,197,24,.025) 1px, transparent 1px);
  background-size:56px 56px;
  animation: gridMove 25s linear infinite;
}
@keyframes gridMove { to { background-position:56px 56px; } }

.bg-radial {
  position:fixed; inset:0; z-index:0; pointer-events:none;
  background: radial-gradient(ellipse 80% 40% at 50% 0%, rgba(245,197,24,.07) 0%, transparent 70%);
  animation: rPulse 5s ease-in-out infinite;
}
@keyframes rPulse { 0%,100%{opacity:.7} 50%{opacity:1} }

/* ── Root layout ───────────────────────────────────────────── */
.root {
  position:relative; z-index:1;
  height:100vh;
  display:grid;
  grid-template-rows: auto 1fr auto;
}

/* ── Header ────────────────────────────────────────────────── */
header {
  padding:16px 40px;
  display:flex; align-items:center; justify-content:space-between;
  border-bottom:1px solid var(--border);
  background:linear-gradient(to bottom, rgba(12,20,32,.98), rgba(12,20,32,.80));
  backdrop-filter:blur(12px);
  flex-shrink:0;
}

.brand { display:flex; align-items:center; gap:15px; }
.brand-icon {
  width:50px; height:50px;
  background:linear-gradient(135deg,var(--gold),#d4a800);
  border-radius:13px;
  display:flex; align-items:center; justify-content:center;
  font-size:22px;
  box-shadow:var(--gold-glow);
  flex-shrink:0;
}
.brand-name {
  font-family:'Bebas Neue',sans-serif;
  font-size:26px; letter-spacing:2.5px;
  color:var(--gold); text-shadow:var(--gold-glow); line-height:1;
}
.brand-tagline { font-size:10px; color:var(--muted); letter-spacing:3px; text-transform:uppercase; margin-top:2px; }

.header-right { text-align:right; }
.live-badge {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.28);
  color:var(--green); font-size:10px; font-weight:700;
  padding:3px 10px; border-radius:20px; letter-spacing:1.5px; text-transform:uppercase;
  margin-bottom:5px;
}
.live-dot {
  width:6px; height:6px; background:var(--green); border-radius:50%;
  animation: ld 1.5s ease-in-out infinite;
}
@keyframes ld { 0%,100%{opacity:1} 50%{opacity:.25} }
.hdr-date { font-size:12px; color:var(--muted); font-weight:600; }
.hdr-clock {
  font-family:'Barlow Condensed',sans-serif;
  font-size:22px; font-weight:700; letter-spacing:1px; color:var(--text);
}

/* ── Two-panel content ─────────────────────────────────────── */
.panels {
  display:grid; grid-template-columns:1fr 1fr;
  overflow:hidden; min-height:0;
}

.panel {
  display:flex; flex-direction:column; overflow:hidden;
  border-right:1px solid var(--border);
}
.panel:last-child { border-right:none; }

.panel-head {
  padding:14px 28px;
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
  flex-shrink:0;
  background:rgba(12,20,32,.6);
}
.panel-title {
  font-family:'Barlow Condensed',sans-serif;
  font-size:17px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase;
  display:flex; align-items:center; gap:9px;
}
.panel-title .dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.w-dot { background:var(--walkin-c); box-shadow:0 0 7px var(--walkin-c); }
.s-dot { background:var(--sub-c);    box-shadow:0 0 7px var(--sub-c); }

.panel-count {
  font-family:'Bebas Neue',sans-serif;
  font-size:30px; letter-spacing:1px; line-height:1;
}
.wc { color:var(--walkin-c); text-shadow:0 0 18px rgba(56,189,248,.5); }
.sc { color:var(--sub-c);    text-shadow:0 0 18px rgba(167,139,250,.5); }
.cnt-label { font-size:10px; color:var(--muted); letter-spacing:1.5px; text-transform:uppercase; text-align:right; }

/* ── Scroll list ───────────────────────────────────────────── */
.panel-body {
  flex:1; overflow-y:auto; padding:6px 0;
  scrollbar-width:thin; scrollbar-color:rgba(245,197,24,.15) transparent;
}
.panel-body::-webkit-scrollbar { width:3px; }
.panel-body::-webkit-scrollbar-thumb { background:rgba(245,197,24,.15); border-radius:2px; }

/* ── Member row ────────────────────────────────────────────── */
.mem-row {
  display:flex; align-items:center; gap:13px;
  padding:11px 28px;
  border-bottom:1px solid rgba(255,255,255,.025);
  animation: rowIn .45s cubic-bezier(.34,1.56,.64,1) both;
}
.mem-row.is-new { animation: rowIn .45s cubic-bezier(.34,1.56,.64,1) both, flashGold 1.8s ease forwards; }
@keyframes rowIn    { from{transform:translateX(-16px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes flashGold { 0%{background:rgba(245,197,24,.18)} 100%{background:transparent} }

.mem-avatar {
  width:42px; height:42px; border-radius:11px;
  display:flex; align-items:center; justify-content:center;
  font-family:'Bebas Neue',sans-serif; font-size:19px; flex-shrink:0;
}
.wa { background:var(--walkin-bg); border:1px solid var(--walkin-bdr); color:var(--walkin-c); }
.sa { background:var(--sub-bg);    border:1px solid var(--sub-bdr);    color:var(--sub-c); }

.mem-info  { flex:1; min-width:0; }
.mem-name  { font-size:15px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:.2px; }
.mem-id    { font-size:10px; color:var(--muted); font-family:'Barlow Condensed',sans-serif; letter-spacing:.5px; margin-top:1px; }
.mem-time  { font-family:'Barlow Condensed',sans-serif; font-size:15px; font-weight:700; flex-shrink:0; }
.wt { color:rgba(56,189,248,.65); }
.st { color:rgba(167,139,250,.65); }

/* ── Empty state ───────────────────────────────────────────── */
.empty {
  flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:10px; padding:40px; color:var(--muted);
}
.empty-ico  { font-size:36px; opacity:.2; }
.empty-txt  { font-size:12px; letter-spacing:1.2px; text-transform:uppercase; opacity:.4; }

/* ── Footer ────────────────────────────────────────────────── */
footer {
  padding:9px 40px;
  border-top:1px solid var(--border);
  background:rgba(6,11,18,.96);
  display:flex; align-items:center; justify-content:space-between;
  flex-shrink:0;
}
.total-wrap { display:flex; align-items:baseline; gap:7px; }
.total-num  {
  font-family:'Bebas Neue',sans-serif; font-size:34px; line-height:1;
  color:var(--gold); text-shadow:var(--gold-glow);
}
.total-lbl  { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:1.5px; }
.foot-quote { font-size:12px; color:var(--muted); font-style:italic; }
.foot-sync  { font-size:11px; color:var(--muted); display:flex; align-items:center; gap:6px; }
.spin {
  width:11px; height:11px;
  border:2px solid rgba(245,197,24,.2); border-top-color:var(--gold);
  border-radius:50%; animation:spn .9s linear infinite; display:none;
}
.spin.on { display:block; }
@keyframes spn { to{transform:rotate(360deg)} }

/* ── Welcome flash overlay ─────────────────────────────────── */
.welcome {
  position:fixed; inset:0; z-index:200;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  background:rgba(6,11,18,.92); backdrop-filter:blur(14px);
  opacity:0; pointer-events:none; transition:opacity .35s ease;
}
.welcome.on { opacity:1; pointer-events:auto; }
.wlc-check {
  font-size:78px; color:var(--green);
  text-shadow:0 0 40px rgba(34,197,94,.6);
  animation: wCheck .55s cubic-bezier(.34,1.56,.64,1);
  margin-bottom:14px;
}
@keyframes wCheck { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
.wlc-name {
  font-family:'Bebas Neue',sans-serif;
  font-size:clamp(40px,7vw,72px); letter-spacing:4px;
  color:var(--gold); text-shadow:var(--gold-glow);
  text-align:center; max-width:85vw; line-height:1;
  animation: wSlide .5s .1s cubic-bezier(.34,1.56,.64,1) both;
}
.wlc-label {
  font-size:15px; color:var(--muted); letter-spacing:3px; text-transform:uppercase;
  margin-top:8px; animation: wSlide .5s .18s cubic-bezier(.34,1.56,.64,1) both;
}
.wlc-time {
  margin-top:14px; background:rgba(34,197,94,.10);
  border:1px solid rgba(34,197,94,.28); color:var(--green);
  padding:7px 20px; border-radius:30px;
  font-weight:700; font-size:14px; letter-spacing:1px;
  animation: wSlide .5s .26s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes wSlide { from{transform:translateY(18px);opacity:0} to{transform:translateY(0);opacity:1} }
.wlc-bar {
  position:absolute; bottom:0; left:0; height:3px;
  background:linear-gradient(90deg,var(--green),var(--gold));
  animation: bShrink 3.2s linear forwards;
}
@keyframes bShrink { from{width:100%} to{width:0%} }

/* ── Responsive ────────────────────────────────────────────── */
@media(max-width:820px){
  .panels { grid-template-columns:1fr; grid-template-rows:1fr 1fr; }
  .panel  { border-right:none; border-bottom:1px solid var(--border); }
  .panel:last-child { border-bottom:none; }
  header { padding:12px 20px; }
  footer { padding:8px 20px; }
  .mem-row { padding:10px 18px; }
  .panel-head { padding:12px 18px; }
}
</style>
</head>
<body>

<div class="bg-grid"></div>
<div class="bg-radial"></div>

<!-- Welcome overlay -->
<div class="welcome" id="wlc">
  <div class="wlc-check">✓</div>
  <div class="wlc-name" id="wlcName">MEMBER</div>
  <div class="wlc-label">Checked In Successfully</div>
  <div class="wlc-time" id="wlcTime">12:00 PM</div>
  <div class="wlc-bar"></div>
</div>

<div class="root">

  <!-- Header -->
  <header>
    <div class="brand">
      <div class="brand-icon">🏋️</div>
      <div>
        <div class="brand-name">Diozabeth Fitness</div>
        <div class="brand-tagline">Attendance Board · Today's Check-ins</div>
      </div>
    </div>
    <div class="header-right">
      <div><span class="live-badge"><span class="live-dot"></span>Live</span></div>
      <div class="hdr-date" id="hdrDate"></div>
      <div class="hdr-clock" id="hdrClock"></div>
    </div>
  </header>

  <!-- Two panels -->
  <div class="panels">

    <!-- Walk-in -->
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title" style="color:var(--walkin-c);">
          <span class="dot w-dot"></span>Walk-in Members
        </div>
        <div>
          <div class="panel-count wc" id="wCount">0</div>
          <div class="cnt-label">today</div>
        </div>
      </div>
      <div class="panel-body" id="wList">
        <div class="empty">
          <div class="empty-ico">👟</div>
          <div class="empty-txt">No walk-ins yet today</div>
        </div>
      </div>
    </div>

    <!-- Subscribers -->
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title" style="color:var(--sub-c);">
          <span class="dot s-dot"></span>Subscribers
        </div>
        <div>
          <div class="panel-count sc" id="sCount">0</div>
          <div class="cnt-label">today</div>
        </div>
      </div>
      <div class="panel-body" id="sList">
        <div class="empty">
          <div class="empty-ico">🎫</div>
          <div class="empty-txt">No subscribers checked in yet</div>
        </div>
      </div>
    </div>

  </div>

  <!-- Footer -->
  <footer>
    <div class="total-wrap">
      <div class="total-num" id="totalNum">0</div>
      <div class="total-lbl">Members Checked In Today</div>
    </div>
    <div class="foot-quote">"Train Hard. Stay Consistent."</div>
    <div class="foot-sync">
      <div class="spin" id="spin"></div>
      <span id="syncTxt">Syncing…</span>
    </div>
  </footer>

</div>

<script>
// ── Clock ────────────────────────────────────────────────────
function tick() {
  const n = new Date();
  document.getElementById('hdrDate').textContent =
    n.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  document.getElementById('hdrClock').textContent =
    n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
}
setInterval(tick, 1000); tick();

// ── State ────────────────────────────────────────────────────
let knownIds   = new Set();
let lastTs     = null;
let isFirst    = true;
let wlcTimer   = null;

// ── Escape ───────────────────────────────────────────────────
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Build row ────────────────────────────────────────────────
function buildRow(m, isWalkin, isNew) {
  const cls = isWalkin ? 'wa wt' : 'sa st';
  const avCls = isWalkin ? 'wa' : 'sa';
  const timCls = isWalkin ? 'wt' : 'st';
  const div = document.createElement('div');
  div.className = 'mem-row' + (isNew ? ' is-new' : '');
  div.dataset.id = m.id;
  div.innerHTML = `
    <div class="mem-avatar ${avCls}">${esc(m.name.trim().charAt(0).toUpperCase())}</div>
    <div class="mem-info">
      <div class="mem-name">${esc(m.name)}</div>
      <div class="mem-id">${esc(m.id)}</div>
    </div>
    <div class="mem-time ${timCls}">${esc(m.time)}</div>
  `;
  return div;
}

// ── Render a panel ────────────────────────────────────────────
function renderPanel(containerId, members, isWalkin) {
  const container = document.getElementById(containerId);
  if (!members.length) {
    container.innerHTML = `
      <div class="empty">
        <div class="empty-ico">${isWalkin?'👟':'🎫'}</div>
        <div class="empty-txt">No ${isWalkin?'walk-ins':'subscribers'} yet today</div>
      </div>`;
    return;
  }

  const existingIds = new Set();
  container.querySelectorAll('.mem-row[data-id]').forEach(r => existingIds.add(r.dataset.id));

  let addedNew = false;
  members.forEach(m => {
    if (existingIds.has(m.id)) return;
    const isNew = !isFirst;
    const row = buildRow(m, isWalkin, isNew);
    // Prepend newest
    if (container.querySelector('.empty')) container.innerHTML = '';
    container.insertBefore(row, container.firstChild);
    knownIds.add(m.id);
    if (isNew) addedNew = true;
  });

  if (addedNew) container.scrollTop = 0;
}

// ── Welcome overlay ───────────────────────────────────────────
function showWelcome(name, time) {
  const overlay = document.getElementById('wlc');
  document.getElementById('wlcName').textContent = name.toUpperCase();
  document.getElementById('wlcTime').textContent = 'Checked in at ' + time;

  // Reset progress bar animation
  const bar = overlay.querySelector('.wlc-bar');
  const fresh = bar.cloneNode();
  bar.replaceWith(fresh);

  overlay.classList.add('on');
  if (wlcTimer) clearTimeout(wlcTimer);
  wlcTimer = setTimeout(() => overlay.classList.remove('on'), 3200);
}

// ── Poll ──────────────────────────────────────────────────────
async function poll() {
  const spin = document.getElementById('spin');
  spin.classList.add('on');
  try {
    const res  = await fetch('attendance_api.php?action=list&_=' + Date.now());
    const data = await res.json();
    if (!data.success) return;

    // Detect new entries for welcome screen
    const allNew = [];
    [...(data.walkins||[]), ...(data.subscribers||[])].forEach(m => {
      if (!knownIds.has(m.id)) allNew.push(m);
    });

    if (!isFirst && allNew.length > 0 && data.latest_ts !== lastTs) {
      showWelcome(allNew[0].name, allNew[0].time);
    }
    lastTs = data.latest_ts;

    renderPanel('wList', data.walkins     || [], true);
    renderPanel('sList', data.subscribers || [], false);

    document.getElementById('wCount').textContent = (data.walkins     || []).length;
    document.getElementById('sCount').textContent = (data.subscribers || []).length;
    document.getElementById('totalNum').textContent = data.total || 0;

    document.getElementById('syncTxt').textContent =
      'Updated ' + new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});

    isFirst = false;
  } catch(e) {
    document.getElementById('syncTxt').textContent = 'Connection error – retrying…';
  } finally {
    spin.classList.remove('on');
  }
}

// Poll every 3 seconds
poll();
setInterval(poll, 3000);
</script>
</body>
</html>
