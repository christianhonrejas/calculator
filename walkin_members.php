<?php
require_once 'auth.php';
$pageTitle = 'Check-in';

// Handle Add Walk-in POST
$successMsg = $errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

// Delete walk-in record
if ($_POST['action'] === 'delete_walkin' && in_array($_SESSION['admin_role'], ['superadmin', 'admin'])) {
    $mid = trim($_POST['member_id'] ?? '');
    if ($mid) {
        $tables = ['attendance_logs', 'walkins', 'payments', 'members'];
        foreach ($tables as $tbl) {
            $d = $conn->prepare("DELETE FROM `$tbl` WHERE member_id = ?");
            $d->bind_param("s", $mid);
            $d->execute();
            $d->close();
        }
        $successMsg = "Walk-in record deleted successfully.";
    }
}

if ($_POST['action'] === 'add_walkin') {
    $name    = trim($_POST['name'] ?? '');
    $payment = floatval($_POST['payment'] ?? 0);

    if (!$name || !$payment) {
        $errorMsg = 'Please fill in all required fields.';
    } else {
        $today    = date('Y-m-d');
        $countToday = (int)$conn->query("SELECT COUNT(*) as c FROM members WHERE member_type = 'walkin' AND DATE(created_at) = '$today'")->fetch_assoc()['c'];
        $seq      = str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
        $memberId = 'W-' . date('Ymd') . '-' . $seq;

        // 1. Insert member
        $stmt = $conn->prepare("INSERT INTO members (member_id, name, member_type, status) VALUES (?, ?, 'walkin', 'active')");
        if (!$stmt) { $errorMsg = 'DB error: ' . $conn->error; }
        else {
            $stmt->bind_param("ss", $memberId, $name);
            if ($stmt->execute()) {
                $stmt->close();

                // 2. Insert walkins record
                $stmt2 = $conn->prepare("INSERT INTO walkins (member_id, payment_amount, visit_date) VALUES (?, ?, ?)");
                $stmt2->bind_param("sds", $memberId, $payment, $today);
                $stmt2->execute();
                $stmt2->close();

                // 3. Insert into payments table
                $stmt3 = $conn->prepare("INSERT INTO payments (member_id, member_type, amount, payment_date) VALUES (?, 'walkin', ?, ?)");
                $stmt3->bind_param("sds", $memberId, $payment, $today);
                $stmt3->execute();
                $stmt3->close();

                // 4. Auto check-in attendance
                $now   = date('Y-m-d H:i:s');
                $stmt4 = $conn->prepare("INSERT INTO attendance_logs (member_id, member_name, member_type, time_in, access_result, status) VALUES (?, ?, 'walkin', ?, 'granted', 'active')");
                $stmt4->bind_param("sss", $memberId, $name, $now);
                $stmt4->execute();
                $stmt4->close();

                $successMsg = "Walk-in member <strong>" . htmlspecialchars($name) . "</strong> registered and checked in successfully at <strong>" . date('h:i A') . "</strong>.";
            } else {
                $stmt->close();
                $errorMsg = 'Failed to add member: ' . $conn->error;
            }
        }
    }
} // end add_walkin
} // end POST

// Fetch walk-in prices
$prices = $conn->query("SELECT * FROM price_settings WHERE type = 'walkin' AND is_active = 1 ORDER BY price ASC");

// Fetch subscription prices for the renew modal
$subPrices = $conn->query("SELECT * FROM price_settings WHERE type = 'subscription' AND is_active = 1 ORDER BY price ASC");

// Fetch today's walk-ins
$todayWalkins = $conn->query("
    SELECT m.*, w.payment_amount, w.visit_date
    FROM members m
    LEFT JOIN walkins w ON m.member_id = w.member_id AND w.visit_date = CURDATE()
    WHERE m.member_type = 'walkin' AND DATE(m.created_at) = CURDATE()
    ORDER BY m.created_at DESC
");

// All walk-in history
$allWalkins = $conn->query("
    SELECT m.*, w.payment_amount, w.visit_date, a.time_in
    FROM members m
    LEFT JOIN walkins w ON m.member_id = w.member_id
    LEFT JOIN attendance_logs a ON m.member_id = a.member_id AND DATE(a.time_in) = w.visit_date
    WHERE m.member_type = 'walkin'
    ORDER BY m.created_at DESC
    LIMIT 100
");

include 'header.php';
?>

<style>
/* ── Smart Subscriber Search (copied from attendance_monitor) ── */
.search-wrap { position: relative; }
.search-input-group {
  display: flex; align-items: center;
  border: 2px solid var(--border); border-radius: 12px;
  background: #fff; transition: border-color .2s, box-shadow .2s; overflow: hidden;
}
.search-input-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(30,120,255,.10); }
.search-icon { padding: 0 14px; color: var(--text-muted); font-size: 15px; flex-shrink: 0; }
#subSearchInput { flex:1; border:none; outline:none; padding:12px 0; font-size:14px; font-family:inherit; background:transparent; color:var(--text-main); }
#subSearchInput::placeholder { color:#adb5bd; }
.search-clear { padding:0 14px; color:var(--text-muted); cursor:pointer; font-size:14px; display:none; background:none; border:none; transition:color .15s; }
.search-clear:hover { color:var(--danger); }

#searchDropdown {
  position:absolute; top:calc(100% + 6px); left:0; right:0;
  background:var(--card-bg,#fff); border:1.5px solid var(--border);
  border-radius:14px; box-shadow:0 12px 40px rgba(0,0,0,.15);
  z-index:1030; max-height:380px; overflow-y:auto; display:none;
}
#searchDropdown.open { display:block; }
#searchDropdown::-webkit-scrollbar { width:4px; }
#searchDropdown::-webkit-scrollbar-thumb { background:#dee2e6; border-radius:4px; }
.dd-header { padding:10px 16px 6px; font-size:10px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1.2px; border-bottom:1px solid var(--border); }
.dd-item { display:flex; align-items:center; gap:12px; padding:11px 16px; border-bottom:1px solid #f0f4fb; transition:background .15s; cursor:default; }
.dd-item:last-child { border-bottom:none; }
.dd-item:hover { background:var(--body-bg,#f8faff); }
.dd-avatar { width:38px; height:38px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:#fff; }
.dd-avatar.active   { background:linear-gradient(135deg,#10b981,#059669); }
.dd-avatar.frozen   { background:linear-gradient(135deg,#f59e0b,#d97706); }
.dd-avatar.expired  { background:linear-gradient(135deg,#6b7a99,#4b5563); }
.dd-avatar.inactive { background:linear-gradient(135deg,#ef4444,#dc2626); }
.dd-info { flex:1; min-width:0; }
.dd-name { font-size:13px; font-weight:700; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dd-meta { display:flex; align-items:center; gap:6px; margin-top:2px; flex-wrap:wrap; }
.dd-id { font-size:10px; background:#f0f4fb; padding:1px 7px; border-radius:6px; color:var(--text-muted); font-family:monospace; }
.dd-sub-date { font-size:10px; color:var(--text-muted); }
.dd-btn-wrap { flex-shrink:0; }
.btn-checkin {
  display:inline-flex; align-items:center; gap:6px;
  background:linear-gradient(135deg,#10b981,#059669);
  color:#fff; border:none; border-radius:9px; padding:7px 14px;
  font-size:12px; font-weight:700; cursor:pointer; transition:all .2s; white-space:nowrap;
  box-shadow:0 2px 8px rgba(16,185,129,.30);
}
.btn-checkin:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 4px 14px rgba(16,185,129,.40); }
.btn-checkin:disabled { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#059669; cursor:not-allowed; box-shadow:none; transform:none; }
.btn-renew-dd {
  display:inline-flex; align-items:center; gap:6px;
  background:linear-gradient(135deg,#f59e0b,#d97706);
  color:#fff; border:none; border-radius:9px; padding:7px 14px;
  font-size:12px; font-weight:700; cursor:pointer; transition:all .2s; white-space:nowrap;
  box-shadow:0 2px 8px rgba(245,158,11,.30);
}
.btn-renew-dd:hover { transform:translateY(-1px); box-shadow:0 4px 14px rgba(245,158,11,.40); }
.dd-empty { padding:28px 16px; text-align:center; color:var(--text-muted); font-size:13px; }
.dd-loading { padding:20px 16px; text-align:center; color:var(--text-muted); font-size:13px; }
.search-hint { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
.hint-chip { font-size:11px; color:var(--text-muted); background:var(--body-bg); border:1px solid var(--border); border-radius:20px; padding:2px 10px; display:inline-flex; align-items:center; gap:4px; }

/* Toast */
#toastWrap { position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none; }
.toast-item { padding:13px 18px; border-radius:12px; font-size:13px; font-weight:600; max-width:360px; display:flex; align-items:center; gap:10px; pointer-events:auto; box-shadow:0 8px 24px rgba(0,0,0,.12); animation:tIn .35s cubic-bezier(.34,1.56,.64,1) both; }
.toast-success { background:#f0fff8; border:1px solid #a7f3d0; color:#065f46; }
.toast-error   { background:#fff0f0; border:1px solid #fca5a5; color:#991b1b; }
@keyframes tIn  { from{transform:translateX(40px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes tOut { from{transform:translateX(0);opacity:1}   to{transform:translateX(40px);opacity:0} }
</style>

<div id="toastWrap"></div>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Check-in</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Register walk-in payments &amp; check in subscribers</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <div class="stat-card py-2 px-3" style="min-width:0;">
      <div class="card-label" style="font-size:11px;">Today's Walk-ins</div>
      <div class="card-value" style="font-size:20px;"><?= $todayWalkins->num_rows ?></div>
    </div>
  </div>
</div>

<?php if ($successMsg): ?>
<div class="alert" style="background:#f0fff8;border:1px solid #a7f3d0;border-radius:12px;padding:14px 18px;color:#065f46;font-size:14px;margin-bottom:20px;">
  <i class="fas fa-check-circle me-2"></i><?= $successMsg ?>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert" style="background:#fff0f0;border:1px solid #fca5a5;border-radius:12px;padding:14px 18px;color:#991b1b;font-size:14px;margin-bottom:20px;">
  <i class="fas fa-exclamation-circle me-2"></i><?= $errorMsg ?>
</div>
<?php endif; ?>

<!-- ── SUBSCRIBER CHECK-IN SEARCH ──────────────────────────── -->
<div class="section-card mb-4" style="overflow:visible;">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-user-check me-2" style="color:var(--primary)"></i>Check-in Subscriber</span>
    <span style="font-size:12px;color:var(--text-muted);">Search by name or UID — walk-in members are auto-checked in below</span>
  </div>
  <div class="section-card-body" style="overflow:visible;">
    <div class="search-wrap">
      <div class="search-input-group">
        <span class="search-icon"><i class="fas fa-search"></i></span>
        <input type="text" id="subSearchInput" autocomplete="off"
          placeholder="Search subscriber by name or User ID (e.g. Juan, AB-12345)…">
        <button class="search-clear" id="btnClearSearch" title="Clear"><i class="fas fa-times"></i></button>
      </div>
      <div class="search-hint">
        <span class="hint-chip"><i class="fas fa-keyboard" style="font-size:9px;"></i> Type to search</span>
        <span class="hint-chip"><i class="fas fa-id-badge" style="font-size:9px;"></i> UID or name</span>
        <span class="hint-chip"><i class="fas fa-bolt" style="font-size:9px;"></i> Live results</span>
      </div>
      <div id="searchDropdown">
        <div class="dd-header">Matching Subscribers</div>
        <div id="ddBody"></div>
      </div>
    </div>
  </div>
</div>

<!-- ── ADD WALK-IN FORM ──────────────────────────────────────── -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-plus-circle me-2" style="color:var(--success)"></i>Add Walk-in Member</span>
  </div>
  <div class="section-card-body">
    <form method="POST" action="" id="walkinForm">
      <input type="hidden" name="action" value="add_walkin">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Visitor Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Select Payment <span class="text-danger">*</span></label>
          <select name="payment" class="form-select" required>
            <option value="">-- Select Payment --</option>
            <?php while($p = $prices->fetch_assoc()): ?>
            <option value="<?= $p['price'] ?>">₱<?= number_format($p['price'], 2) ?> – <?= htmlspecialchars($p['label']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Payment Date</label>
          <input type="text" class="form-control" value="<?= date('M d, Y') ?>" readonly style="background:#f8faff;">
          <button type="submit" id="walkinSubmitBtn" class="btn-success-custom w-100 mt-2" style="justify-content:center;padding:10px;">
            <i class="fas fa-user-plus"></i> <span id="walkinBtnText">Add Walk-in</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Today's Walk-ins Table -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-calendar-day me-2" style="color:var(--success)"></i>Today's Walk-ins – <?= date('M d, Y') ?></span>
    <span class="badge-active"><?= $todayWalkins->num_rows ?> today</span>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr><th style="width:50px;">No.</th><th>Name</th><th>Payment</th><th>Date</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php
          $todayWalkins->data_seek(0);
          $no = 1;
          if ($todayWalkins->num_rows > 0):
          while($row = $todayWalkins->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--text-muted);font-weight:600;font-size:13px;"><?= $no++ ?></td>
            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
            <td><span style="color:var(--success);font-weight:700;">₱<?= number_format($row['payment_amount'], 2) ?></span></td>
            <td><?= date('F d, Y', strtotime($row['visit_date'])) ?></td>
            <td>
              <?php if (in_array($_SESSION['admin_role'], ['superadmin','admin'])): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this walk-in record permanently?')">
                <input type="hidden" name="action" value="delete_walkin">
                <input type="hidden" name="member_id" value="<?= htmlspecialchars($row['member_id']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash me-1"></i>Delete</button>
              </form>
              <?php else: ?>
              <span style="font-size:12px;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No walk-in members today yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-history me-2" style="color:var(--text-muted)"></i>Walk-in History</span>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0" id="walkinHistoryTable">
        <thead>
          <tr><th style="width:50px;">No.</th><th>Name</th><th>Payment</th><th>Date</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php while($row = $allWalkins->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--text-muted);font-weight:600;font-size:13px;"></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td>₱<?= number_format($row['payment_amount'] ?? 0, 2) ?></td>
            <td><?= $row['visit_date'] ? date('F d, Y', strtotime($row['visit_date'])) : date('F d, Y', strtotime($row['created_at'])) ?></td>
            <td>
              <?php if (in_array($_SESSION['admin_role'], ['superadmin','admin'])): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this walk-in record permanently?')">
                <input type="hidden" name="action" value="delete_walkin">
                <input type="hidden" name="member_id" value="<?= htmlspecialchars($row['member_id']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash me-1"></i>Delete</button>
              </form>
              <?php else: ?>
              <span style="font-size:12px;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── RENEW MODAL ────────────────────────────────────────────── -->
<style>
/* ── Renew Modal ─────────────────────────────────────────── */
#renewModal { z-index: 1055; }
#renewModal .modal-content {
  border-radius: 20px;
  border: none;
  box-shadow: 0 24px 60px rgba(0,0,0,.18);
  overflow: hidden;
}
#renewModal .modal-header {
  background: linear-gradient(135deg, #fff9ee 0%, #fffdf7 100%);
  border-bottom: 2px solid rgba(245,158,11,.15);
  padding: 20px 24px 18px;
}
#renewModal .modal-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 20px;
  font-weight: 800;
  color: #0a1628;
  display: flex;
  align-items: center;
  gap: 10px;
}
#renewModal .modal-title .title-icon {
  width: 36px; height: 36px; border-radius: 10px;
  background: linear-gradient(135deg,#f59e0b,#d97706);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 16px;
  box-shadow: 0 4px 12px rgba(245,158,11,.4);
  flex-shrink: 0;
}
#renewModal .member-card {
  background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
  border: 1.5px solid #e0e8ff;
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 22px;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}
@media(max-width:576px) { #renewModal .member-card { grid-template-columns: repeat(2,1fr); } }
#renewModal .member-card-item .mc-label {
  font-size: 10px; font-weight: 700; color: #8a96b0;
  text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;
}
#renewModal .member-card-item .mc-value {
  font-size: 14px; font-weight: 700; color: #0a1628;
}
#renewModal .plan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px;
  margin-bottom: 20px;
}
#renewModal .plan-card {
  border: 2px solid #e8ecf4;
  border-radius: 12px;
  padding: 14px 12px;
  text-align: center;
  cursor: pointer;
  transition: all .18s;
  background: #fff;
  position: relative;
}
#renewModal .plan-card:hover {
  border-color: #f59e0b;
  background: #fffdf5;
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(245,158,11,.2);
}
#renewModal .plan-card.selected {
  border-color: #f59e0b;
  background: linear-gradient(135deg, #fffbeb, #fff9e0);
  box-shadow: 0 4px 16px rgba(245,158,11,.25);
}
#renewModal .plan-card.selected::after {
  content: '00c';
  font-family: 'Font Awesome 6 Free';
  font-weight: 900;
  position: absolute; top: 8px; right: 10px;
  color: #f59e0b; font-size: 11px;
}
#renewModal .plan-price {
  font-size: 18px; font-weight: 800; color: #f59e0b; line-height: 1.2;
}
#renewModal .plan-label {
  font-size: 11px; color: #6b7a99; margin-top: 3px; font-weight: 600;
}
#renewModal .date-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;
}
@media(max-width:480px) { #renewModal .date-row { grid-template-columns: 1fr; } }
#renewModal .total-banner {
  background: linear-gradient(135deg, #f0fff8, #e6ffef);
  border: 1.5px solid #a7f3d0;
  border-radius: 12px;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
#renewModal .total-banner .tb-label {
  font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: .8px;
}
#renewModal .total-banner .tb-amount {
  font-size: 22px; font-weight: 800; color: #059669;
  font-family: 'Barlow Condensed', sans-serif;
}
#renewModal .modal-footer {
  background: #f8faff;
  border-top: 1px solid #e8ecf4;
  padding: 16px 24px;
  gap: 10px;
}
.btn-renew-confirm {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  border: none; color: #fff; font-weight: 700; font-size: 14px;
  padding: 11px 24px; border-radius: 10px; cursor: pointer;
  transition: all .2s; display: flex; align-items: center; gap: 7px;
  box-shadow: 0 4px 14px rgba(245,158,11,.4);
}
.btn-renew-confirm:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(245,158,11,.5);
}
.btn-renew-confirm:disabled { opacity: .6; cursor: not-allowed; transform: none; }
</style>

<div class="modal fade" id="renewModal" tabindex="-1" data-bs-backdrop="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header">
        <div class="modal-title">
          <span class="title-icon"><i class="fas fa-rotate"></i></span>
          Renew Subscription
          <span style="color:#f59e0b;margin-left:4px;" id="renewMemberName"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Body -->
      <div class="modal-body" style="padding:24px;">

        <!-- Member Info Card -->
        <div class="member-card">
          <div class="member-card-item">
            <div class="mc-label">Member ID</div>
            <div class="mc-value" id="ri_id" style="font-family:monospace;font-size:13px;"></div>
          </div>
          <div class="member-card-item">
            <div class="mc-label">Type</div>
            <div class="mc-value" style="margin-top:1px;"><span class="badge-subscription">Subscription</span></div>
          </div>
          <div class="member-card-item">
            <div class="mc-label">Status</div>
            <div id="ri_status" style="margin-top:1px;"></div>
          </div>
          <div class="member-card-item">
            <div class="mc-label">Expired On</div>
            <div class="mc-value" id="ri_expiry" style="color:#ef4444;"></div>
          </div>
        </div>

        <!-- Renewal Form -->
        <form method="POST" action="subscription_members.php" id="renewForm">
          <input type="hidden" name="action" value="renew_subscription">
          <input type="hidden" name="member_id" id="renewMemberId">
          <input type="hidden" name="payment_amount" id="renewPriceHidden" value="">

          <!-- Plan selector -->
          <div style="font-size:12px;font-weight:700;color:#6b7a99;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;">Select Renewal Plan *</div>
          <div class="plan-grid" id="planGrid">
            <?php if ($subPrices): $subPrices->data_seek(0); while($sp = $subPrices->fetch_assoc()): ?>
            <div class="plan-card" onclick="selectPlan(this, <?= $sp['price'] ?>)">
              <div class="plan-price">₱<?= number_format($sp['price'], 0) ?></div>
              <div class="plan-label"><?= htmlspecialchars($sp['label']) ?></div>
            </div>
            <?php endwhile; endif; ?>
          </div>

          <!-- Date row -->
          <div class="date-row">
            <div>
              <label class="form-label" style="font-size:12px;font-weight:700;color:#6b7a99;text-transform:uppercase;letter-spacing:.8px;">Start Date *</label>
              <input type="date" name="start_date" id="renewStart" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div>
              <label class="form-label" style="font-size:12px;font-weight:700;color:#6b7a99;text-transform:uppercase;letter-spacing:.8px;">End Date *</label>
              <input type="date" name="end_date" id="renewEnd" class="form-control" required>
            </div>
          </div>

          <!-- Total -->
          <div id="renewTotalDisplay" class="total-banner" style="display:none;">
            <div>
              <div class="tb-label">Total Renewal Amount</div>
              <div style="font-size:12px;color:#059669;margin-top:2px;" id="renewPlanName"></div>
            </div>
            <div class="tb-amount" id="renewTotalAmt">₱0.00</div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="renewSubmitBtn" class="btn-renew-confirm" onclick="submitRenew()">
          <i class="fas fa-rotate"></i>
          <span id="renewBtnText">Renew Subscription</span>
        </button>
      </div>

    </div>
  </div>
</div>

<?php
// nowdoc — PHP treats everything here as a literal string
$extraScripts = <<<'ENDJS'
<script>
$(document).ready(function() {
  var table = $("#walkinHistoryTable").DataTable({
    responsive: true,
    order: [[3,"desc"]],
    pageLength: -1, lengthMenu: [[10,25,50,100,-1],["10","25","50","100","All"]],
    language: { emptyTable: "No walk-in history found" },
    columnDefs: [{ orderable: false, targets: 0 }],
    drawCallback: function() {
      var info = this.api().page.info();
      $("#walkinHistoryTable tbody tr").each(function(i) {
        $(this).find("td:first").html(info.start + i + 1);
      });
    }
  });
});

// ── Prevent duplicate walk-in form submission ─────────────────
(function() {
  var form    = document.getElementById("walkinForm");
  var btn     = document.getElementById("walkinSubmitBtn");
  var btnText = document.getElementById("walkinBtnText");
  var submitted = false;
  if (form) {
    form.addEventListener("submit", function(e) {
      if (submitted) { e.preventDefault(); return false; }
      submitted = true;
      btn.disabled = true;
      btn.style.opacity = "0.7";
      btn.style.cursor  = "not-allowed";
      btnText.textContent = "Processing…";
    });
  }
})();

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type) {
  type = type || "success";
  var wrap = document.getElementById("toastWrap");
  var el   = document.createElement("div");
  el.className = "toast-item toast-" + type;
  el.innerHTML = "<i class=\"fas fa-" + (type==="success"?"check-circle":"exclamation-circle") + "\"></i> " + msg;
  wrap.appendChild(el);
  setTimeout(function() {
    el.style.animation = "tOut .35s ease forwards";
    setTimeout(function(){ el.remove(); }, 370);
  }, 4000);
}

function esc(s) {
  return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
}
function highlight(text, query) {
  if (!query) return esc(text);
  var re = new RegExp("(" + query.replace(/[.*+?^${}()|[\\]\\\\]/g,"\\\\$&") + ")", "gi");
  return esc(text).replace(re, "<mark style=\"background:rgba(30,120,255,.15);color:var(--primary);border-radius:3px;padding:0 2px;\">$1</mark>");
}

// ── Subscriber Search ─────────────────────────────────────────
(function() {
  var searchInput = document.getElementById("subSearchInput");
  var dropdown    = document.getElementById("searchDropdown");
  var ddBody      = document.getElementById("ddBody");
  var btnClear    = document.getElementById("btnClearSearch");
  var searchTimer = null;
  var activeXhr   = null; // track active fetch so we can abort stale results

  if (!searchInput) return; // safety guard

  function openDD()  { dropdown.classList.add("open"); }
  function closeDD() { dropdown.classList.remove("open"); }

  function renderItem(sub, query) {
    var initials  = sub.name.split(" ").map(function(w){ return w[0]||""; }).join("").slice(0,2).toUpperCase();
    var st        = sub.status || "expired";
    var alreadyIn = sub.checked_in_today;

    var btnHtml;
    if (alreadyIn) {
      btnHtml = '<button class="btn-checkin" disabled style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#059669;cursor:not-allowed;"><i class="fas fa-check-circle"></i> Checked In</button>';
    } else if (st === "frozen") {
      btnHtml = '<button class="btn-checkin" disabled style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;"><i class="fas fa-snowflake"></i> Frozen</button>';
    } else if (st === "active") {
      btnHtml = '<button class="btn-checkin" onclick="doCheckin(this,\'' + sub.member_id.replace(/'/g,"\\'\\'") + '\')"><i class="fas fa-sign-in-alt"></i> Check In</button>';
    } else {
      var subJson = JSON.stringify(sub).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      btnHtml = '<button class="btn-renew-dd" onclick="openRenew(JSON.parse(decodeURIComponent(\'' + encodeURIComponent(JSON.stringify(sub)) + '\')))" ><i class="fas fa-rotate"></i> Renew</button>';
    }

    var statusColors = {
      active:   { bg:"rgba(16,185,129,0.12)",  color:"#059669" },
      frozen:   { bg:"rgba(245,158,11,0.12)",   color:"#d97706" },
      expired:  { bg:"rgba(107,122,153,0.12)",  color:"#6b7a99" },
      inactive: { bg:"rgba(239,68,68,0.12)",    color:"#dc2626" }
    };
    var sc = statusColors[st] || statusColors.expired;
    var statusLabel = st.charAt(0).toUpperCase() + st.slice(1);

    var expiryHtml = sub.end_date
      ? '<span class="dd-sub-date"><i class="fas fa-calendar-check" style="font-size:9px;color:#10b981;"></i> Expires: <strong>' + esc(sub.end_date) + '</strong></span>'
      : '<span class="dd-sub-date" style="color:#ef4444;"><i class="fas fa-calendar-times" style="font-size:9px;"></i> No active subscription</span>';

    var phoneHtml = sub.phone
      ? '<span class="dd-sub-date"><i class="fas fa-phone" style="font-size:9px;"></i> ' + esc(sub.phone) + '</span>'
      : '';

    var inTodayBadge = alreadyIn
      ? '<span style="background:rgba(16,185,129,0.12);color:#059669;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;"><i class="fas fa-check" style="font-size:9px;"></i> In Today</span>'
      : '';

    return '<div class="dd-item" style="padding:14px 16px;align-items:flex-start;gap:14px;">'
      + '<div class="dd-avatar ' + esc(st) + '" style="margin-top:2px;width:42px;height:42px;font-size:15px;flex-shrink:0;">' + initials + '</div>'
      + '<div class="dd-info" style="flex:1;min-width:0;">'
        + '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px;">'
          + '<div class="dd-name" style="font-size:14px;">' + highlight(sub.name, query) + '</div>'
          + '<span style="background:' + sc.bg + ';color:' + sc.color + ';font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;">' + statusLabel + '</span>'
          + inTodayBadge
        + '</div>'
        + '<div class="dd-meta" style="gap:8px;">'
          + '<span class="dd-id" style="font-size:11px;">' + highlight(sub.member_id, query) + '</span>'
          + expiryHtml + phoneHtml
        + '</div>'
      + '</div>'
      + '<div class="dd-btn-wrap" style="flex-shrink:0;margin-top:2px;">' + btnHtml + '</div>'
      + '</div>';
  }

  function runSearch(q) {
    if (q.length < 1) { closeDD(); ddBody.innerHTML = ''; return; }

    // Cancel previous pending fetch
    if (activeXhr) { activeXhr.abort = true; }
    var token = { abort: false };
    activeXhr = token;

    ddBody.innerHTML = '<div class="dd-loading"><i class="fas fa-spinner fa-spin me-2"></i>Searching…</div>';
    openDD();

    fetch('attendance_api.php?action=search&q=' + encodeURIComponent(q) + '&_=' + Date.now(), { credentials: 'same-origin' })
      .then(function(res) { return res.text(); })
      .then(function(text) {
        if (token.abort) return; // stale result, discard
        var data;
        try { data = JSON.parse(text); }
        catch(e) {
          ddBody.innerHTML = '<div class="dd-empty"><i class="fas fa-exclamation-circle me-2"></i>Server error — check PHP logs.</div>';
          return;
        }
        if (!data.success) {
          ddBody.innerHTML = '<div class="dd-empty"><i class="fas fa-exclamation-circle me-2"></i>' + esc(data.message || 'Search error.') + '</div>';
          return;
        }
        if (!data.results || data.results.length === 0) {
          ddBody.innerHTML = '<div class="dd-empty"><i class="fas fa-user-slash" style="font-size:20px;margin-bottom:6px;display:block;"></i>No subscribers found matching "<strong>' + esc(q) + '</strong>"</div>';
          return;
        }
        ddBody.innerHTML = data.results.map(function(s) { return renderItem(s, q); }).join('');
      })
      .catch(function(err) {
        if (token.abort) return;
        ddBody.innerHTML = '<div class="dd-empty"><i class="fas fa-wifi me-2"></i>Connection error. Please try again.</div>';
      });
  }

  // Input handler — debounce 250ms, NO lastQuery dedup (was causing stuck results)
  searchInput.addEventListener('input', function() {
    var q = this.value.trim();
    btnClear.style.display = q ? 'block' : 'none';
    clearTimeout(searchTimer);
    if (q.length === 0) { closeDD(); ddBody.innerHTML = ''; return; }
    searchTimer = setTimeout(function() { runSearch(q); }, 250);
  });

  // Escape to close
  searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeDD(); this.blur(); }
  });

  // Clear button
  btnClear.addEventListener('click', function() {
    searchInput.value = '';
    btnClear.style.display = 'none';
    closeDD();
    ddBody.innerHTML = '';
    searchInput.focus();
  });

  // Close when clicking outside
  document.addEventListener('click', function(e) {
    var wrap = document.getElementById('subSearchInput');
    var drop = document.getElementById('searchDropdown');
    if (wrap && drop && !wrap.contains(e.target) && !drop.contains(e.target)) closeDD();
  });

  // Re-open on focus if there are results
  searchInput.addEventListener('focus', function() {
    if (this.value.trim().length > 0 && ddBody.innerHTML.trim() !== '') openDD();
  });

  // Expose openRenew and doCheckin to global scope for inline onclick handlers
  window._subSearch_openDD  = openDD;
  window._subSearch_closeDD = closeDD;
  window._subSearch_ddBody  = ddBody;
  window._subSearch_input   = searchInput;
  window._subSearch_btnClear = btnClear;
})();

// ── AJAX Check-in ──────────────────────────────────────────────
function doCheckin(btn, memberId) {
  btn.classList.add("loading");
  btn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Checking in…";
  btn.disabled  = true;
  var fd = new FormData();
  fd.append("member_id",   memberId);
  fd.append("member_type", "subscription");
  fetch("attendance_api.php?action=checkin", { method:"POST", body:fd, credentials:"same-origin" })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.success) {
        btn.innerHTML = "<i class=\"fas fa-check\"></i> Checked In";
        btn.style.cssText = "background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#059669;cursor:not-allowed;box-shadow:none;";
        toast("<strong>" + esc(data.entry.name) + "</strong> checked in at " + esc(data.entry.time) + " ✓", "success");
        setTimeout(function(){
          if(window._subSearch_input) { window._subSearch_input.value = ""; window._subSearch_input.dispatchEvent(new Event("input")); } window._subSearch_closeDD && window._subSearch_closeDD();
        }, 1200);
      } else {
        btn.classList.remove("loading");
        btn.innerHTML = "<i class=\"fas fa-sign-in-alt\"></i> Check In";
        btn.disabled = false;
        toast(data.message || "Check-in failed.", "error");
      }
    })
    .catch(function(){
      btn.classList.remove("loading");
      btn.innerHTML = "<i class=\"fas fa-sign-in-alt\"></i> Check In";
      btn.disabled = false;
      toast("Network error. Please try again.", "error");
    });
}

// ── Renew Modal ────────────────────────────────────────────────
function openRenew(sub) {
  // Close search dropdown before opening modal
  window._subSearch_closeDD && window._subSearch_closeDD();
  var inp = window._subSearch_input;
  if (inp) { inp.value = ''; }
  var bc = document.getElementById('btnClearSearch');
  if (bc) bc.style.display = 'none';

  document.getElementById("renewMemberName").textContent = sub.name;
  document.getElementById("renewMemberId").value         = sub.member_id;
  document.getElementById("ri_id").textContent           = sub.member_id;
  document.getElementById("ri_expiry").textContent       = sub.end_date || "—";

  var st = sub.status || "expired";
  var statusColors = { active:"badge-active", expired:"badge-expired", inactive:"badge-inactive", frozen:"badge-frozen" };
  var statusEl = document.getElementById("ri_status");
  statusEl.innerHTML = "<span class=\"" + (statusColors[st]||"badge-expired") + "\">" + st.charAt(0).toUpperCase()+st.slice(1) + "</span>";

  document.getElementById("renewPrice").value = "";
  document.getElementById("renewStart").value = new Date().toISOString().split("T")[0];
  document.getElementById("renewEnd").value   = "";
  document.getElementById("renewTotalDisplay").style.display = "none";

  var modal = new bootstrap.Modal(document.getElementById("renewModal"));
  modal.show();
}

function updateRenewTotal() {
  var price = parseFloat(document.getElementById("renewPrice").value) || 0;
  var disp  = document.getElementById("renewTotalDisplay");
  var amt   = document.getElementById("renewTotalAmt");
  if (price > 0) {
    disp.style.display = "block";
    amt.textContent    = "₱" + price.toLocaleString("en-PH", { minimumFractionDigits:2 });
  } else {
    disp.style.display = "none";
  }
}

function submitRenew() {
  var btn     = document.getElementById("renewSubmitBtn");
  var btnText = document.getElementById("renewBtnText");
  var form    = document.getElementById("renewForm");
  if (!form.checkValidity()) { form.reportValidity(); return; }
  btn.disabled       = true;
  btn.style.opacity  = "0.7";
  btnText.textContent = "Processing…";
  form.submit();
}
// ── Close search dropdown whenever any modal opens ──────────
document.addEventListener('show.bs.modal', function() {
  window._subSearch_closeDD && window._subSearch_closeDD();
  var dd = document.getElementById('ddBody');
  if (dd) dd.innerHTML = '';
  var inp = window._subSearch_input;
  if (inp) inp.value = '';
  var bc = document.getElementById('btnClearSearch');
  if (bc) bc.style.display = 'none';
});
</script>
ENDJS;
include 'footer.php';
?>