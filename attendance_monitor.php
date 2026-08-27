<?php
require_once 'auth.php';
$pageTitle = 'Attendance Monitoring';

// Fetch subscription prices for the renew modal
$subPrices = $conn->query("SELECT * FROM price_settings WHERE type = 'subscription' AND is_active = 1 ORDER BY price ASC");

// Initial data for first render
$walkinAttend = $conn->query("
    SELECT a.*, m.status as mem_status
    FROM attendance_logs a
    LEFT JOIN members m ON a.member_id = m.member_id
    WHERE a.member_type = 'walkin' AND DATE(a.time_in) = CURDATE()
    ORDER BY a.time_in DESC
");
$subAttend = $conn->query("
    SELECT a.*, m.status as mem_status
    FROM attendance_logs a
    LEFT JOIN members m ON a.member_id = m.member_id
    WHERE a.member_type = 'subscription' AND DATE(a.time_in) = CURDATE()
    ORDER BY a.time_in DESC
");

$totalToday = $walkinAttend->num_rows + $subAttend->num_rows;
include 'header.php';
?>

<style>
/* ── Toast ────────────────────────────────────────────────────── */
#toastWrap { position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none; }
.toast-item { padding:13px 18px;border-radius:12px;font-size:13px;font-weight:600;max-width:360px;display:flex;align-items:center;gap:10px;pointer-events:auto;box-shadow:0 8px 24px rgba(0,0,0,.12);animation:tIn .35s cubic-bezier(.34,1.56,.64,1) both; }
.toast-success { background:#f0fff8;border:1px solid #a7f3d0;color:#065f46; }
.toast-error   { background:#fff0f0;border:1px solid #fca5a5;color:#991b1b; }
@keyframes tIn  { from{transform:translateX(40px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes tOut { from{transform:translateX(0);opacity:1}   to{transform:translateX(40px);opacity:0} }

/* ── Live pill ────────────────────────────────────────────────── */
.live-pill { display:inline-flex;align-items:center;gap:5px;background:rgba(16,185,129,.10);border:1px solid rgba(16,185,129,.30);color:#10b981;font-size:11px;font-weight:700;padding:3px 11px;border-radius:20px;letter-spacing:1px;text-transform:uppercase; }
.live-dot  { width:6px;height:6px;background:#10b981;border-radius:50%;animation:ldot 1.5s ease-in-out infinite; }
@keyframes ldot { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── Row pop ──────────────────────────────────────────────────── */
@keyframes rowPop { 0%{background:rgba(30,120,255,.18);transform:scale(1.005);} 100%{background:transparent;transform:scale(1);} }
.row-new td { animation:rowPop 2s ease forwards; }

/* ── Display card ─────────────────────────────────────────────── */
.display-card { display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,rgba(30,120,255,.08),rgba(30,120,255,.03));border:1px solid rgba(30,120,255,.20);border-radius:14px;padding:13px 18px;margin-bottom:20px;cursor:pointer;transition:all .2s;text-decoration:none;color:inherit; }
.display-card:hover { border-color:var(--primary);transform:translateY(-1px);box-shadow:0 4px 16px rgba(30,120,255,.15);color:inherit; }
.display-card-icon { width:40px;height:40px;border-radius:10px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0; }
</style>

<div id="toastWrap"></div>

<!-- Page Header -->
<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Attendance Monitoring</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Real-time check-in — <?= date('l, F d, Y') ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <span class="live-pill"><span class="live-dot"></span>Live</span>
    <div class="stat-card py-2 px-3" style="min-width:0;text-align:center;">
      <div class="card-label" style="font-size:11px;">Today's Check-ins</div>
      <div class="card-value" style="font-size:20px;" id="totalBadge"><?= $totalToday ?></div>
    </div>
  </div>
</div>

<!-- Display Screen Banner -->
<a href="attendance_display.php" target="_blank" class="display-card">
  <div class="display-card-icon"><i class="fas fa-display"></i></div>
  <div style="flex:1;">
    <div style="font-weight:700;font-size:14px;">Open Member Display Screen</div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Open on the front-desk PC. It shows live check-ins automatically.</div>
  </div>
  <i class="fas fa-external-link-alt" style="color:var(--text-muted);font-size:12px;flex-shrink:0;"></i>
</a>

<!-- Info note: search moved to Check-in page -->
<div style="background:rgba(30,120,255,.06);border:1px solid rgba(30,120,255,.18);border-radius:12px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:var(--text-main);display:flex;align-items:center;gap:10px;">
  <i class="fas fa-info-circle" style="color:var(--primary);font-size:16px;flex-shrink:0;"></i>
  <span>To check in a subscriber, use the <strong><a href="walkin_members.php" style="color:var(--primary);">Check-in page</a></strong> — search by name or UID and click <em>Check In</em>. Walk-ins are registered there too.</span>
</div>

<!-- ── Attendance Tables ──────────────────────────────────────── -->
<div class="row g-3">

  <!-- Walk-in Attendance -->
  <div class="col-12 col-lg-6">
    <div class="section-card">
      <div class="section-card-header" style="background:rgba(30,120,255,.04);">
        <span class="section-card-title"><i class="fas fa-person-walking me-2" style="color:var(--primary)"></i>Walk-in Attendance</span>
        <span class="badge-walkin" id="walkinCountBadge"><?= $walkinAttend->num_rows ?> today</span>
      </div>
      <div class="section-card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr><th style="width:50px;">No.</th><th>Name</th><th>Time In</th><th>Status</th></tr>
            </thead>
            <tbody id="walkinTbody">
              <?php if ($walkinAttend->num_rows > 0): $wno = 1;
                while ($row = $walkinAttend->fetch_assoc()): ?>
              <tr data-id="<?= htmlspecialchars($row['member_id']) ?>">
                <td style="color:var(--text-muted);font-weight:600;font-size:13px;"><?= $wno++ ?></td>
                <td style="font-size:13px;font-weight:600;"><?= htmlspecialchars($row['member_name']) ?></td>
                <td style="font-size:12px;"><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                <td><span class="badge-<?= $row['status'] ?? 'active' ?>"><?= ucfirst($row['status'] ?? 'Active') ?></span></td>
              </tr>
              <?php endwhile; else: ?>
              <tr id="walkinEmpty">
                <td colspan="4" class="text-center text-muted py-4" style="font-size:13px;"><i class="fas fa-inbox me-2"></i>No walk-in check-ins today</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Subscriber Attendance -->
  <div class="col-12 col-lg-6">
    <div class="section-card">
      <div class="section-card-header" style="background:rgba(139,92,246,.04);">
        <span class="section-card-title"><i class="fas fa-id-card me-2" style="color:#8b5cf6"></i>Subscriber Attendance</span>
        <span class="badge-subscription" id="subCountBadge"><?= $subAttend->num_rows ?> today</span>
      </div>
      <div class="section-card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr><th>User ID</th><th>Name</th><th>Time In</th><th>Status</th></tr>
            </thead>
            <tbody id="subTbody">
              <?php if ($subAttend->num_rows > 0):
                while ($row = $subAttend->fetch_assoc()): ?>
              <tr data-id="<?= htmlspecialchars($row['member_id']) ?>">
                <td><code style="background:#f0f4fb;padding:3px 8px;border-radius:6px;font-size:11px;"><?= htmlspecialchars($row['member_id']) ?></code></td>
                <td style="font-size:13px;font-weight:600;"><?= htmlspecialchars($row['member_name']) ?></td>
                <td style="font-size:12px;"><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                <td><span class="badge-<?= $row['status'] ?? 'active' ?>"><?= ucfirst($row['status'] ?? 'Active') ?></span></td>
              </tr>
              <?php endwhile; else: ?>
              <tr id="subEmpty">
                <td colspan="4" class="text-center text-muted py-4" style="font-size:13px;"><i class="fas fa-inbox me-2"></i>No subscriber check-ins today</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ── RENEW MODAL (for inactive/expired members found in search) ── -->
<div class="modal fade" id="renewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(245,158,11,.03));border-bottom:1px solid rgba(245,158,11,.2);">
        <h5 class="modal-title"><i class="fas fa-rotate me-2" style="color:#f59e0b;"></i>Renew Subscription — <span id="renewMemberName" style="color:#f59e0b;"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="renewMemberInfo" style="background:#f8faff;border:1px solid #e8ecf4;border-radius:12px;padding:16px 18px;margin-bottom:20px;">
          <div class="row g-2" style="font-size:13px;">
            <div class="col-6 col-md-3"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Member ID</span><div id="ri_id" style="font-weight:700;margin-top:3px;font-family:monospace;"></div></div>
            <div class="col-6 col-md-3"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Type</span><div style="margin-top:3px;"><span class="badge-subscription">Subscription</span></div></div>
            <div class="col-6 col-md-3"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Status</span><div id="ri_status" style="margin-top:3px;"></div></div>
            <div class="col-6 col-md-3"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Expired On</span><div id="ri_expiry" style="font-weight:700;margin-top:3px;color:#ef4444;"></div></div>
          </div>
        </div>
        <form method="POST" action="subscription_members.php" id="renewForm">
          <input type="hidden" name="action" value="renew_subscription">
          <input type="hidden" name="member_id" id="renewMemberId">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Renewal Price <span class="text-danger">*</span></label>
              <select name="payment_amount" id="renewPrice" class="form-select" required onchange="updateRenewTotal()">
                <option value="">-- Select Plan --</option>
                <?php if ($subPrices): $subPrices->data_seek(0); while($sp = $subPrices->fetch_assoc()): ?>
                <option value="<?= $sp['price'] ?>"><?= htmlspecialchars($sp['label']) ?></option>
                <?php endwhile; endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" name="start_date" id="renewStart" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" name="end_date" id="renewEnd" class="form-control" required>
            </div>
            <div class="col-12">
              <div id="renewTotalDisplay" style="background:#f0fff8;border:1px solid #a7f3d0;border-radius:10px;padding:12px 16px;font-size:15px;font-weight:700;color:#059669;display:none;">
                <i class="fas fa-check-circle me-2"></i>Renewal Amount: <span id="renewTotalAmt"></span>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="renewSubmitBtn" class="btn-primary-custom" onclick="submitRenew()" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 2px 8px rgba(245,158,11,.3);">
          <i class="fas fa-rotate me-1"></i> <span id="renewBtnText">Renew Subscription</span>
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
// ── Known IDs (detect new entries from polling) ──────────────
const knownIds = new Set();
document.querySelectorAll('#walkinTbody tr[data-id], #subTbody tr[data-id]').forEach(r => knownIds.add(r.dataset.id));

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type='success') {
  const wrap = document.getElementById('toastWrap');
  const el   = document.createElement('div');
  el.className = `toast-item toast-${type}`;
  el.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
  wrap.appendChild(el);
  setTimeout(() => {
    el.style.animation = 'tOut .35s ease forwards';
    setTimeout(() => el.remove(), 370);
  }, 4000);
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Renumber walk-in No. column ───────────────────────────────
function renumberWalkin() {
  document.querySelectorAll('#walkinTbody tr[data-id]').forEach((tr, i) => {
    const td = tr.cells[0];
    if (td) { td.textContent = i + 1; td.style.cssText = 'color:var(--text-muted);font-weight:600;font-size:13px;'; }
  });
}

// ── Build a table row ─────────────────────────────────────────
function buildRow(entry, isNew) {
  const tr = document.createElement('tr');
  tr.dataset.id = entry.id;
  if (isNew) tr.className = 'row-new';
  const isWalkin = entry.type === 'walkin';
  tr.innerHTML = `
    <td style="color:var(--text-muted);font-weight:600;font-size:13px;">${isWalkin ? '' : '<code style="background:#f0f4fb;padding:3px 8px;border-radius:6px;font-size:11px;">' + esc(entry.id) + '</code>'}</td>
    <td style="font-size:13px;font-weight:600;">${esc(entry.name)}</td>
    <td style="font-size:12px;">${esc(entry.time)}</td>
    <td><span class="badge-${esc(entry.status||'active')}">${esc((entry.status||'active').charAt(0).toUpperCase()+(entry.status||'active').slice(1))}</span></td>
  `;
  return tr;
}

// ── Update count badges ───────────────────────────────────────
function updateCounts() {
  const wCount = document.querySelectorAll('#walkinTbody tr[data-id]').length;
  const sCount = document.querySelectorAll('#subTbody tr[data-id]').length;
  document.getElementById('walkinCountBadge').textContent = wCount + ' today';
  document.getElementById('subCountBadge').textContent    = sCount + ' today';
  document.getElementById('totalBadge').textContent       = wCount + sCount;
}

// ── Background polling ────────────────────────────────────────
let lastTs = null;
async function pollAttendance() {
  try {
    const res  = await fetch('attendance_api.php?action=list&_=' + Date.now(), {credentials:'same-origin'});
    const data = await res.json();
    if (!data.success) return;
    if (data.latest_ts === lastTs && lastTs !== null) return;
    lastTs = data.latest_ts;
    const allEntries = [...(data.walkins||[]), ...(data.subscribers||[])];
    let addedAny = false;
    allEntries.forEach(entry => {
      if (knownIds.has(entry.id)) return;
      knownIds.add(entry.id);
      addedAny = true;
      const isWalkin = entry.type === 'walkin';
      const tbody    = isWalkin ? document.getElementById('walkinTbody') : document.getElementById('subTbody');
      const empty    = document.getElementById(isWalkin ? 'walkinEmpty' : 'subEmpty');
      if (empty) empty.remove();
      tbody.insertBefore(buildRow(entry, true), tbody.firstChild);
      if (isWalkin) renumberWalkin();
      toast(`<strong>${esc(entry.name)}</strong> checked in at ${esc(entry.time)} ✓`, 'success');
    });
    if (addedAny) updateCounts();
  } catch(e) { /* silent fail */ }
}
setInterval(pollAttendance, 6000);

// ── Renew Modal ────────────────────────────────────────────────
function openRenew(sub) {
  document.getElementById('renewMemberName').textContent = sub.name;
  document.getElementById('renewMemberId').value         = sub.member_id;
  document.getElementById('ri_id').textContent           = sub.member_id;
  document.getElementById('ri_expiry').textContent       = sub.end_date || '—';
  const st = sub.status || 'expired';
  const statusColors = { active:'badge-active', expired:'badge-expired', inactive:'badge-inactive', frozen:'badge-frozen' };
  document.getElementById('ri_status').innerHTML = `<span class="${statusColors[st]||'badge-expired'}">${st.charAt(0).toUpperCase()+st.slice(1)}</span>`;
  document.getElementById('renewPrice').value = '';
  document.getElementById('renewStart').value = new Date().toISOString().split('T')[0];
  document.getElementById('renewEnd').value   = '';
  document.getElementById('renewTotalDisplay').style.display = 'none';
  new bootstrap.Modal(document.getElementById('renewModal')).show();
}

function updateRenewTotal() {
  const price = parseFloat(document.getElementById('renewPrice').value) || 0;
  const disp  = document.getElementById('renewTotalDisplay');
  const amt   = document.getElementById('renewTotalAmt');
  if (price > 0) {
    disp.style.display = 'block';
    amt.textContent    = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits:2});
  } else {
    disp.style.display = 'none';
  }
}

function submitRenew() {
  const btn     = document.getElementById('renewSubmitBtn');
  const btnText = document.getElementById('renewBtnText');
  const form    = document.getElementById('renewForm');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  btn.disabled       = true;
  btn.style.opacity  = '0.7';
  btnText.textContent = 'Processing…';
  form.submit();
}
</script>
JS;
include 'footer.php';
?>
