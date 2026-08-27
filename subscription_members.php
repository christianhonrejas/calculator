<?php
require_once 'auth.php';
$pageTitle = 'Subscriptions';

// AJAX: Generate unique User ID based on member's name initials
if (isset($_GET['generate_id'])) {
    $rawName = trim($_GET['name'] ?? '');
    // Build initials from each word in the name (letters only, uppercase)
    $initials = '';
    foreach (preg_split('/\s+/', $rawName) as $word) {
        $first = preg_replace('/[^A-Za-z]/', '', $word);
        if ($first !== '') $initials .= strtoupper($first[0]);
    }
    if ($initials === '') $initials = 'S'; // fallback
    $prefix = $initials . '-';
    do {
        $rand  = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
        $newId = $prefix . $rand;
        $r = $conn->query("SELECT id FROM members WHERE member_id = '" . $conn->real_escape_string($newId) . "'");
    } while ($r && $r->num_rows > 0);
    header('Content-Type: application/json');
    echo json_encode(['id' => $newId]);
    exit;
}

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add new subscription member ──────────────────────────────────────────
    if ($action === 'add_subscription') {
        $name      = trim($_POST['name'] ?? '');
        $payment   = floatval($_POST['payment'] ?? 0);
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $endDate   = $_POST['end_date'] ?? '';
        $memberId  = trim($_POST['member_id_input'] ?? '');

        if (!$memberId)                          { $errorMsg = 'Please enter a User ID.'; }
        elseif (!$name || !$payment || !$endDate){ $errorMsg = 'Please fill in all required fields.'; }
        else {
            $idCheck = $conn->query("SELECT id FROM members WHERE member_id = '" . $conn->real_escape_string($memberId) . "'");
            if ($idCheck && $idCheck->num_rows > 0) {
                $errorMsg = 'User ID already exists. Please choose a different ID.';
            } else {
                $stmt = $conn->prepare("INSERT INTO members (member_id, name, member_type, status) VALUES (?, ?, 'subscription', 'active')");
                $stmt->bind_param("ss", $memberId, $name);
                if ($stmt->execute()) {
                    $stmt->close();
                    $stmt2 = $conn->prepare("INSERT INTO subscriptions (member_id, payment_amount, start_date, end_date, status) VALUES (?,?,?,?,'active')");
                    $stmt2->bind_param("sdss", $memberId, $payment, $startDate, $endDate);
                    $stmt2->execute(); $stmt2->close();
                    $stmt3 = $conn->prepare("INSERT INTO payments (member_id, member_type, amount, payment_date) VALUES (?, 'subscription', ?, ?)");
                    $stmt3->bind_param("sds", $memberId, $payment, $startDate);
                    $stmt3->execute(); $stmt3->close();
                    $successMsg = "Member <strong>$name</strong> registered with ID: <strong>$memberId</strong>.";
                } else {
                    $stmt->close();
                    $errorMsg = 'Failed to register member.';
                }
            }
        }
    }

    // ── Renew subscription (Advance Renewal Allowed) ─────────────────────────
    if ($action === 'renew_subscription') {
        $mid       = $conn->real_escape_string($_POST['member_id'] ?? '');
        $payment   = floatval($_POST['payment'] ?? 0);
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $endDate   = $_POST['end_date'] ?? '';
        if ($mid && $payment && $startDate && $endDate) {
            // Check if there's a currently active subscription (advance renewal)
            $activeSub = $conn->query("SELECT end_date FROM subscriptions WHERE member_id = '$mid' AND status = 'active' AND end_date > CURDATE() ORDER BY end_date DESC LIMIT 1")->fetch_assoc();
            if ($activeSub) {
                // Advance renewal: keep current active sub, add new sub starting after current end date
                // Update start/end dates to begin from the current subscription's end date if not already beyond it
                if ($startDate <= $activeSub['end_date']) {
                    $startDate = date('Y-m-d', strtotime($activeSub['end_date'] . ' +1 day'));
                    $newEnd = new DateTime($startDate);
                    $newEnd->modify('+1 month');
                    $endDate = $newEnd->format('Y-m-d');
                }
                $advanceNote = " (Advance renewal — starts " . date('F d, Y', strtotime($startDate)) . ")";
            } else {
                // Normal renewal: mark all old subscriptions as expired first
                $conn->query("UPDATE subscriptions SET status = 'expired' WHERE member_id = '$mid'");
                $advanceNote = '';
            }
            // Insert new subscription
            $stmt = $conn->prepare("INSERT INTO subscriptions (member_id, payment_amount, start_date, end_date, status) VALUES (?,?,?,?,'active')");
            $stmt->bind_param("sdss", $mid, $payment, $startDate, $endDate);
            $stmt->execute(); $stmt->close();
            // Record payment
            $paymentDate = date('Y-m-d');
            $stmt2 = $conn->prepare("INSERT INTO payments (member_id, member_type, amount, payment_date) VALUES (?, 'subscription', ?, ?)");
            $stmt2->bind_param("sds", $mid, $payment, $paymentDate);
            $stmt2->execute(); $stmt2->close();
            // Reactivate member
            $conn->query("UPDATE members SET status = 'active' WHERE member_id = '$mid'");
            $successMsg = "Subscription renewed successfully.$advanceNote";
        } else {
            $errorMsg = 'Please fill in all renewal fields.';
        }
    }

    // ── Freeze / Unfreeze ────────────────────────────────────────────────────
    if ($action === 'freeze_member') {
        $mid = $conn->real_escape_string($_POST['member_id'] ?? '');
        $existing = $conn->query("SELECT * FROM freeze_records WHERE member_id = '$mid' AND status = 'active'")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE freeze_records SET status = 'ended', unfreeze_date = CURDATE() WHERE member_id = '$mid' AND status = 'active'");
            $conn->query("UPDATE members SET status = 'active' WHERE member_id = '$mid'");
            $conn->query("UPDATE subscriptions SET status = 'active' WHERE member_id = '$mid' AND status = 'frozen'");
            $successMsg = "Member unfrozen successfully.";
        } else {
            $conn->query("INSERT INTO freeze_records (member_id, freeze_date, status) VALUES ('$mid', CURDATE(), 'active')");
            $conn->query("UPDATE members SET status = 'frozen' WHERE member_id = '$mid'");
            $conn->query("UPDATE subscriptions SET status = 'frozen' WHERE member_id = '$mid' AND status = 'active'");
            $successMsg = "Member frozen successfully.";
        }
    }

    // ── Delete subscription member ───────────────────────────────────────────
    if ($action === 'delete_member' && in_array($_SESSION['admin_role'], ['superadmin', 'admin'])) {
        $mid = $conn->real_escape_string($_POST['member_id'] ?? '');
        if ($mid) {
            $conn->query("DELETE FROM attendance_logs WHERE member_id = '$mid'");
            $conn->query("DELETE FROM freeze_records WHERE member_id = '$mid'");
            $conn->query("DELETE FROM subscriptions WHERE member_id = '$mid'");
            $conn->query("DELETE FROM payments WHERE member_id = '$mid'");
            $conn->query("DELETE FROM members WHERE member_id = '$mid'");
            $successMsg = "Member record deleted successfully.";
        }
    }

    // ── Update member profile ────────────────────────────────────────────────
    if ($action === 'update_member') {
        $mid  = $_POST['member_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($mid && $name) {
            $stmt = $conn->prepare("UPDATE members SET name=? WHERE member_id=?");
            $stmt->bind_param("ss", $name, $mid);
            $stmt->execute(); $stmt->close();
            $successMsg = "Member updated successfully.";
        }
    }
}

// ── Auto-expire subscriptions ────────────────────────────────────────────────
$conn->query("UPDATE members m JOIN subscriptions s ON m.member_id = s.member_id
    SET s.status = 'expired', m.status = 'inactive'
    WHERE s.end_date <= CURDATE() AND s.status = 'active'");
// Auto-unfreeze after 30 days
$conn->query("UPDATE members m JOIN freeze_records f ON m.member_id = f.member_id
    SET m.status = 'active', f.status = 'ended', f.unfreeze_date = CURDATE()
    WHERE f.status = 'active' AND DATEDIFF(CURDATE(), f.freeze_date) >= 30");

// ── Queries ──────────────────────────────────────────────────────────────────
$prices = $conn->query("SELECT * FROM price_settings WHERE type = 'subscription' AND is_active = 1 ORDER BY price ASC");

// ACTIVE members — soonest expiring first
$activeMembers = $conn->query("
    SELECT m.*, s.payment_amount, s.start_date, s.end_date, s.status as sub_status,
        (SELECT freeze_date FROM freeze_records WHERE member_id = m.member_id AND status = 'active' LIMIT 1) as freeze_date
    FROM members m
    INNER JOIN subscriptions s ON s.id = (
        SELECT id FROM subscriptions WHERE member_id = m.member_id ORDER BY end_date DESC LIMIT 1
    )
    WHERE m.member_type = 'subscription'
        AND m.status IN ('active', 'frozen')
    ORDER BY s.end_date ASC
");

// EXPIRED members — join only the latest subscription per member
$expiredMembers = $conn->query("
    SELECT m.*, s.payment_amount, s.start_date, s.end_date, s.status as sub_status
    FROM members m
    INNER JOIN subscriptions s ON s.id = (
        SELECT id FROM subscriptions WHERE member_id = m.member_id ORDER BY end_date DESC LIMIT 1
    )
    WHERE m.member_type = 'subscription'
        AND m.status IN ('inactive', 'expired')
    ORDER BY s.end_date DESC
");

include 'header.php';
?>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Subscriptions</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Register and manage subscription memberships</p>
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

<!-- Register Form (always visible) -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-user-plus me-2" style="color:var(--primary)"></i>Register Subscription Member</span>
  </div>
  <div class="section-card-body">
    <form method="POST" action="" id="subRegForm">
      <input type="hidden" name="action" value="add_subscription">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="memberNameInput" class="form-control" placeholder="Enter full name" required
            oninput="document.getElementById('userIdInput').value='';document.getElementById('userIdStatus').innerHTML='<span style=\'color:#6b7a99;font-size:12px;\'>Click Generate after entering name.</span>';document.getElementById('generateBtn').innerHTML='<i class=\'fas fa-wand-magic-sparkles me-1\'></i>Generate';">
        </div>
        <div class="col-md-6">
          <label class="form-label">User ID <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="text" name="member_id_input" id="userIdInput" class="form-control" placeholder="Enter name first, then Generate" required readonly style="background:#f8faff;font-weight:600;letter-spacing:1px;">
            <button type="button" class="btn btn-primary" onclick="generateUserId()" id="generateBtn" style="font-size:13px;padding:0 16px;border-radius:0 10px 10px 0;">
              <i class="fas fa-wand-magic-sparkles me-1"></i>Generate
            </button>
          </div>
          <div id="userIdStatus" style="font-size:12px;margin-top:4px;"></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Select Payment <span class="text-danger">*</span></label>
          <select name="payment" class="form-select" required id="paymentSelect">
            <option value="">-- Select payment --</option>
            <?php while($p = $prices->fetch_assoc()): ?>
            <option value="<?= $p['price'] ?>">₱<?= number_format($p['price'], 2) ?> – <?= htmlspecialchars($p['label']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Start Date <span class="text-danger">*</span></label>
          <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" id="startDate" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">End Date <span class="text-danger">*</span></label>
          <input type="date" name="end_date" class="form-control" id="endDate" required>
        </div>
        <div class="col-12">
          <button type="submit" id="subRegBtn" class="btn-primary-custom"><i class="fas fa-user-plus"></i> <span id="subRegBtnText">Register Member</span></button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ── ACTIVE SUBSCRIPTIONS ──────────────────────────────────────────────── -->
<div class="section-card mb-4">
  <div class="section-card-header" style="background:rgba(16,185,129,0.04);">
    <span class="section-card-title"><i class="fas fa-id-card me-2" style="color:#10b981"></i>Active Subscriptions</span>
    <span class="badge-active" id="activeBadge"><?= $activeMembers->num_rows ?> member<?= $activeMembers->num_rows != 1 ? 's' : '' ?></span>
  </div>

  <!-- Search & Entries Toolbar -->
  <div style="padding:14px 18px;border-bottom:1px solid var(--border-color,#e8ecf4);background:#fafbff;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <label style="font-size:12px;font-weight:600;color:#6b7a99;white-space:nowrap;">Show</label>
      <select id="activeEntriesSelect" style="border:2px solid #e8ecf4;border-radius:8px;padding:5px 10px;font-size:13px;color:#0a1628;background:#fff;cursor:pointer;" onchange="renderActiveTable()">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="-1">All</option>
      </select>
      <label style="font-size:12px;font-weight:600;color:#6b7a99;white-space:nowrap;">entries</label>
    </div>
    <div style="position:relative;flex:1;max-width:320px;min-width:180px;">
      <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ba8be;font-size:13px;pointer-events:none;"></i>
      <input type="text" id="activeSearch" placeholder="Search by User ID or Name…"
        style="width:100%;border:2px solid #e8ecf4;border-radius:10px;padding:7px 12px 7px 32px;font-size:13px;color:#0a1628;background:#fff;transition:border-color .2s;outline:none;"
        oninput="renderActiveTable()" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e8ecf4'">
      <button id="activeClearBtn" onclick="document.getElementById('activeSearch').value='';renderActiveTable();"
        style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ba8be;cursor:pointer;font-size:13px;padding:0;">
        <i class="fas fa-times-circle"></i>
      </button>
    </div>
  </div>

  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0" id="activeTable">
        <thead>
          <tr>
            <th>User ID</th><th>Name</th><th>Payment</th><th>Expires</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($activeMembers->num_rows > 0):
          while($row = $activeMembers->fetch_assoc()):
            $status   = $row['status'];
            $daysLeft = $row['end_date'] ? (int)floor((strtotime($row['end_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
            $expiringSoon = $daysLeft !== null && $daysLeft <= 7;
            $rowStyle = $expiringSoon ? 'background:rgba(239,68,68,0.04);' : '';
          ?>
          <tr style="<?= $rowStyle ?>">
            <td><code style="background:#f0f4fb;padding:3px 8px;border-radius:6px;font-size:12px;"><?= htmlspecialchars($row['member_id']) ?></code></td>
            <td>
              <strong><?= htmlspecialchars($row['name']) ?></strong><br>
              <span class="badge-<?= $status ?>"><?= ucfirst($status) ?></span>
            </td>
            <td>₱<?= number_format($row['payment_amount'] ?? 0, 2) ?></td>
            <td>
              <?php if ($row['end_date']): ?>
                <span style="font-size:12px;<?= $expiringSoon ? 'color:#ef4444;font-weight:700;' : '' ?>"><?= date('F d, Y', strtotime($row['end_date'])) ?></span>
                <?php if ($daysLeft !== null && $daysLeft <= 3): ?>
                <br><span style="background:rgba(239,68,68,0.12);color:#ef4444;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;">
                  <i class="fas fa-exclamation-circle me-1"></i><?= $daysLeft <= 0 ? 'Expires Today!' : $daysLeft . ' day(s) left' ?>
                </span>
                <?php elseif ($expiringSoon): ?>
                <br><span class="badge-frozen" style="font-size:10px;"><?= $daysLeft ?> day(s) left</span>
                <?php endif; ?>
              <?php else: ?> - <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <button class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:11px;padding:3px 10px;" onclick="openUpdateModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                  <i class="fas fa-edit me-1"></i>Update
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $status === 'frozen' ? 'Unfreeze' : 'Freeze' ?> this member?')">
                  <input type="hidden" name="action" value="freeze_member">
                  <input type="hidden" name="member_id" value="<?= $row['member_id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $status === 'frozen' ? 'btn-warning' : 'btn-outline-warning' ?> rounded-pill" style="font-size:11px;padding:3px 10px;">
                    <i class="fas fa-snowflake me-1"></i><?= $status === 'frozen' ? 'Unfreeze' : 'Freeze' ?>
                  </button>
                </form>
                <?php if (in_array($_SESSION['admin_role'], ['superadmin','admin'])): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this member and all their records permanently?')">
                  <input type="hidden" name="action" value="delete_member">
                  <input type="hidden" name="member_id" value="<?= htmlspecialchars($row['member_id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;padding:3px 10px;">
                    <i class="fas fa-trash me-1"></i>Delete
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No active subscription members</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Info + Pagination row -->
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-color,#e8ecf4);gap:10px;background:#fafbff;">
      <div id="activeInfo" style="font-size:12px;color:#6b7a99;"></div>
      <div id="activePagination" style="display:flex;gap:4px;flex-wrap:wrap;"></div>
    </div>
  </div>
</div>

<!-- ── EXPIRED SUBSCRIPTION HISTORY ─────────────────────────────────────── -->
<div class="section-card">
  <div class="section-card-header" style="background:rgba(239,68,68,0.04);">
    <span class="section-card-title"><i class="fas fa-clock-rotate-left me-2" style="color:#ef4444"></i>Expired Subscription History</span>
    <span class="badge-expired" id="expiredBadge"><?= $expiredMembers->num_rows ?> record<?= $expiredMembers->num_rows != 1 ? 's' : '' ?></span>
  </div>

  <!-- Search & Entries Toolbar -->
  <div style="padding:14px 18px;border-bottom:1px solid var(--border-color,#e8ecf4);background:#fafbff;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <label style="font-size:12px;font-weight:600;color:#6b7a99;white-space:nowrap;">Show</label>
      <select id="expiredEntriesSelect" style="border:2px solid #e8ecf4;border-radius:8px;padding:5px 10px;font-size:13px;color:#0a1628;background:#fff;cursor:pointer;" onchange="renderExpiredTable()">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="-1">All</option>
      </select>
      <label style="font-size:12px;font-weight:600;color:#6b7a99;white-space:nowrap;">entries</label>
    </div>
    <div style="position:relative;flex:1;max-width:320px;min-width:180px;">
      <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ba8be;font-size:13px;pointer-events:none;"></i>
      <input type="text" id="expiredSearch" placeholder="Search by User ID or Name…"
        style="width:100%;border:2px solid #e8ecf4;border-radius:10px;padding:7px 12px 7px 32px;font-size:13px;color:#0a1628;background:#fff;transition:border-color .2s;outline:none;"
        oninput="renderExpiredTable()" onfocus="this.style.borderColor='#ef4444'" onblur="this.style.borderColor='#e8ecf4'">
      <button id="expiredClearBtn" onclick="document.getElementById('expiredSearch').value='';renderExpiredTable();"
        style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ba8be;cursor:pointer;font-size:13px;padding:0;">
        <i class="fas fa-times-circle"></i>
      </button>
    </div>
  </div>

  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0" id="expiredTable">
        <thead>
          <tr>
            <th>User ID</th><th>Name</th><th>Last Payment</th><th>Expired On</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($expiredMembers->num_rows > 0):
          while($row = $expiredMembers->fetch_assoc()): ?>
          <tr>
            <td><code style="background:#fff0f0;padding:3px 8px;border-radius:6px;font-size:12px;"><?= htmlspecialchars($row['member_id']) ?></code></td>
            <td>
              <strong><?= htmlspecialchars($row['name']) ?></strong><br>
              <span class="badge-expired">Expired</span>
            </td>
            <td>₱<?= number_format($row['payment_amount'] ?? 0, 2) ?></td>
            <td><span style="font-size:12px;color:#ef4444;"><?= $row['end_date'] ? date('F d, Y', strtotime($row['end_date'])) : '-' ?></span></td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
              <button class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:11px;padding:3px 10px;" onclick="openUpdateModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                <i class="fas fa-edit me-1"></i>Update
              </button>
              <?php if (in_array($_SESSION['admin_role'], ['superadmin','admin'])): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this member and all their records permanently?')">
                <input type="hidden" name="action" value="delete_member">
                <input type="hidden" name="member_id" value="<?= htmlspecialchars($row['member_id']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;padding:3px 10px;">
                  <i class="fas fa-trash me-1"></i>Delete
                </button>
              </form>
              <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No expired subscriptions</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Info + Pagination row -->
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-color,#e8ecf4);gap:10px;background:#fafbff;">
      <div id="expiredInfo" style="font-size:12px;color:#6b7a99;"></div>
      <div id="expiredPagination" style="display:flex;gap:4px;flex-wrap:wrap;"></div>
    </div>
  </div>
</div>

<!-- Combined Update + Renew Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Member Profile — <span id="modalMemberName" style="color:var(--primary);"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        <!-- Tab Navigation -->
        <div style="display:flex;border-bottom:2px solid #e8ecf4;background:#fafbff;">
          <button id="tabBtnUpdate" onclick="switchTab('update')"
            style="flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;color:var(--primary);border-bottom:3px solid var(--primary);cursor:pointer;">
            <i class="fas fa-user-pen me-2"></i>Update Info
          </button>
          <button id="tabBtnRenew"
            style="flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;color:#6b7a99;border-bottom:3px solid transparent;cursor:pointer;">
            <i class="fas fa-rotate-right me-2"></i>Renew Subscription
          </button>
        </div>

        <!-- Tab: Update Info -->
        <div id="tabUpdate" style="padding:20px;">
          <form method="POST" action="" id="formUpdate">
            <input type="hidden" name="action" value="update_member">
            <input type="hidden" name="member_id" id="updateMemberId">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="updateName" class="form-control" required>
              </div>
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
              <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn-primary-custom"><i class="fas fa-save me-1"></i>Save Changes</button>
            </div>
          </form>
        </div>

        <!-- Tab: Renew Subscription -->
        <div id="tabRenew" style="padding:20px;display:none;">
          <!-- Advance renewal notice (shown when expiring within 3 days) -->
          <div id="advanceRenewAlert" style="display:none;background:rgba(245,158,11,0.10);border:1.5px solid #fde68a;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400e;">
            <i class="fas fa-clock me-2"></i><strong>Expiring Soon!</strong> This member's subscription expires in <strong><span id="daysLeftText"></span> day(s)</strong>.
            Renewing now will queue the new subscription to start right after the current one ends.
          </div>
          <!-- Already expired notice -->
          <div id="expiredRenewAlert" style="display:none;background:rgba(239,68,68,0.08);border:1.5px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#991b1b;">
            <i class="fas fa-exclamation-circle me-2"></i>This member's subscription has <strong>expired</strong>. Renewing will reactivate their membership immediately.
          </div>
          <!-- Normal active — renewal blocked, handled by JS popup -->

          <form method="POST" action="" id="formRenew">
            <input type="hidden" name="action" value="renew_subscription">
            <input type="hidden" name="member_id" id="renewMemberId">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Select Payment <span class="text-danger">*</span></label>
                <select name="payment" class="form-select" required>
                  <option value="">-- Select payment --</option>
                  <?php
                  $renewPrices = $conn->query("SELECT * FROM price_settings WHERE type = 'subscription' AND is_active = 1 ORDER BY price ASC");
                  while($rp = $renewPrices->fetch_assoc()):
                  ?>
                  <option value="<?= $rp['price'] ?>">₱<?= number_format($rp['price'], 2) ?> – <?= htmlspecialchars($rp['label']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="renewStartDate" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">End Date <span class="text-danger">*</span></label>
                <input type="date" name="end_date" id="renewEndDate" class="form-control" required>
              </div>
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
              <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn-success-custom"><i class="fas fa-rotate-right me-1"></i>Confirm Renewal</button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
/* ── Live-Search Table Engine ─────────────────────── */
.sub-table-row-hidden { display: none !important; }
.sub-highlight {
  background: #fffde6;
  color: #92400e;
  font-weight: 700;
  border-radius: 3px;
  padding: 0 2px;
}
.sub-pg-btn {
  min-width: 32px; height: 32px;
  border: 2px solid #e8ecf4;
  background: #fff;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #344361;
  cursor: pointer;
  transition: all .15s;
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0 8px;
}
.sub-pg-btn:hover:not(:disabled) { border-color: var(--primary, #10b981); color: var(--primary, #10b981); }
.sub-pg-btn.active {
  background: var(--primary, #10b981);
  border-color: var(--primary, #10b981);
  color: #fff;
}
.sub-pg-btn:disabled { opacity: .4; cursor: default; }
@media (max-width: 576px) {
  #activeSearch, #expiredSearch { font-size: 12px; }
}
</style>

<script>
/* ═══════════════════════════════════════════════════════════
   Subscription Members — Live Search + Pagination Engine
   ═══════════════════════════════════════════════════════════ */
(function () {
  /* ── helpers ───────────────────────────────────────── */
  function escapeRx(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  function highlight(text, term) {
    if (!term) return escapeHtml(text);
    var rx = new RegExp('(' + escapeRx(term) + ')', 'gi');
    return escapeHtml(text).replace(rx, '<mark class="sub-highlight">$1</mark>');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ── snapshot original rows ────────────────────────── */
  function snapshotRows(tableId) {
    var tbody = document.querySelector('#' + tableId + ' tbody');
    if (!tbody) return [];
    return Array.from(tbody.rows).map(function (tr) {
      /* col 0 = User ID, col 1 = Name cell (may contain <strong> + badge) */
      var uid  = tr.cells[0] ? tr.cells[0].textContent.trim() : '';
      var name = tr.cells[1] ? tr.cells[1].textContent.trim() : '';
      return { tr: tr, uid: uid, name: name, origHtml: [
        tr.cells[0] ? tr.cells[0].innerHTML : '',
        tr.cells[1] ? tr.cells[1].innerHTML : ''
      ]};
    });
  }

  /* ── core renderer ─────────────────────────────────── */
  function buildRenderer(cfg) {
    /* cfg: { tableId, searchId, entriesId, infoId, paginationId,
              clearBtnId, badgeId, accentColor, colSpan } */
    var rows = [];
    var currentPage = 1;

    function getPerPage() {
      return parseInt(document.getElementById(cfg.entriesId).value, 10);
    }

    function getQuery() {
      return (document.getElementById(cfg.searchId).value || '').trim().toLowerCase();
    }

    function filtered() {
      var q = getQuery();
      if (!q) return rows;
      return rows.filter(function (r) {
        return r.uid.toLowerCase().includes(q) || r.name.toLowerCase().includes(q);
      });
    }

    function renderPagination(total, perPage) {
      var pg = document.getElementById(cfg.paginationId);
      pg.innerHTML = '';
      if (perPage === -1 || total === 0) return;
      var pages = Math.ceil(total / perPage);
      if (pages <= 1) return;

      function btn(label, page, disabled, active) {
        var b = document.createElement('button');
        b.className = 'sub-pg-btn' + (active ? ' active' : '');
        b.disabled = disabled;
        b.innerHTML = label;
        b.onclick = function () { currentPage = page; render(); };
        pg.appendChild(b);
      }

      btn('<i class="fas fa-chevron-left"></i>', currentPage - 1, currentPage === 1, false);
      var start = Math.max(1, currentPage - 2);
      var end   = Math.min(pages, start + 4);
      start = Math.max(1, end - 4);
      if (start > 1) { btn('1', 1, false, false); if (start > 2) { var sp=document.createElement('span'); sp.textContent='…'; sp.style='font-size:12px;color:#9ba8be;align-self:center;'; pg.appendChild(sp); } }
      for (var i = start; i <= end; i++) btn(i, i, false, i === currentPage);
      if (end < pages) { if (end < pages - 1) { var sp2=document.createElement('span'); sp2.textContent='…'; sp2.style='font-size:12px;color:#9ba8be;align-self:center;'; pg.appendChild(sp2); } btn(pages, pages, false, false); }
      btn('<i class="fas fa-chevron-right"></i>', currentPage + 1, currentPage === pages, false);
    }

    function render() {
      var q = getQuery();
      var perPage = getPerPage();
      var list = filtered();
      var total = list.length;

      /* clamp page */
      var maxPage = perPage === -1 ? 1 : Math.max(1, Math.ceil(total / perPage));
      if (currentPage > maxPage) currentPage = maxPage;

      var start = perPage === -1 ? 0 : (currentPage - 1) * perPage;
      var end   = perPage === -1 ? total : Math.min(start + perPage, total);

      /* show/hide all rows */
      rows.forEach(function (r) { r.tr.classList.add('sub-table-row-hidden'); });

      /* handle no-results */
      var tbody = document.querySelector('#' + cfg.tableId + ' tbody');
      var noResultRow = tbody.querySelector('.no-result-row');
      if (!noResultRow) {
        noResultRow = document.createElement('tr');
        noResultRow.className = 'no-result-row';
        noResultRow.innerHTML = '<td colspan="' + cfg.colSpan + '" class="text-center py-4" style="color:#9ba8be;font-size:13px;"><i class="fas fa-search me-2"></i>No matching users found</td>';
        tbody.appendChild(noResultRow);
      }

      if (total === 0) {
        noResultRow.style.display = '';
      } else {
        noResultRow.style.display = 'none';
        list.slice(start, end).forEach(function (r) {
          r.tr.classList.remove('sub-table-row-hidden');
          /* restore then highlight */
          if (r.tr.cells[0]) r.tr.cells[0].innerHTML = r.origHtml[0];
          if (r.tr.cells[1]) r.tr.cells[1].innerHTML = r.origHtml[1];
          if (q) {
            if (r.tr.cells[0]) {
              var codeEl = r.tr.cells[0].querySelector('code');
              if (codeEl) codeEl.innerHTML = highlight(r.uid, q);
            }
            if (r.tr.cells[1]) {
              var strongEl = r.tr.cells[1].querySelector('strong');
              if (strongEl) strongEl.innerHTML = highlight(r.name.replace(/\s+\(.*\)$/, ''), q);
            }
          }
        });
      }

      /* info text */
      var info = document.getElementById(cfg.infoId);
      if (total === 0) {
        info.textContent = q ? 'No results for "' + q + '"' : 'No entries';
      } else if (perPage === -1) {
        info.textContent = 'Showing all ' + total + ' entr' + (total === 1 ? 'y' : 'ies');
      } else {
        info.textContent = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entr' + (total === 1 ? 'y' : 'ies') + (q ? ' (filtered)' : '');
      }

      /* clear-btn visibility */
      var cb = document.getElementById(cfg.clearBtnId);
      if (cb) cb.style.display = q ? 'block' : 'none';

      renderPagination(total, perPage);
    }

    /* init */
    document.addEventListener('DOMContentLoaded', function () {
      rows = snapshotRows(cfg.tableId);
      render();
      document.getElementById(cfg.searchId).addEventListener('input', function () {
        currentPage = 1; render();
      });
      document.getElementById(cfg.entriesId).addEventListener('change', function () {
        currentPage = 1; render();
      });
    });

    return { render: render, reset: function () { currentPage = 1; render(); } };
  }

  /* ── init both tables ──────────────────────────────── */
  window._activeRenderer = buildRenderer({
    tableId: 'activeTable',
    searchId: 'activeSearch',
    entriesId: 'activeEntriesSelect',
    infoId: 'activeInfo',
    paginationId: 'activePagination',
    clearBtnId: 'activeClearBtn',
    badgeId: 'activeBadge',
    colSpan: 5
  });

  window._expiredRenderer = buildRenderer({
    tableId: 'expiredTable',
    searchId: 'expiredSearch',
    entriesId: 'expiredEntriesSelect',
    infoId: 'expiredInfo',
    paginationId: 'expiredPagination',
    clearBtnId: 'expiredClearBtn',
    badgeId: 'expiredBadge',
    colSpan: 5
  });

  /* proxy for external callers */
  window.renderActiveTable  = function () { if (window._activeRenderer)  window._activeRenderer.render(); };
  window.renderExpiredTable = function () { if (window._expiredRenderer) window._expiredRenderer.render(); };
})();

/* ── Combined Update + Renew Modal ─────────────────── */
function switchTab(tab) {
  var isUpdate = tab === 'update';
  document.getElementById('tabUpdate').style.display = isUpdate ? 'block' : 'none';
  document.getElementById('tabRenew').style.display  = isUpdate ? 'none'  : 'block';
  document.getElementById('tabBtnUpdate').style.cssText = isUpdate
    ? 'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;color:var(--primary);border-bottom:3px solid var(--primary);cursor:pointer;'
    : 'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;color:#6b7a99;border-bottom:3px solid transparent;cursor:pointer;';
  // tabBtnRenew styling is managed exclusively by openUpdateModal — do NOT touch it here
}

function openUpdateModal(data, openTab) {
  // Populate shared fields
  document.getElementById('updateMemberId').value        = data.member_id;
  document.getElementById('renewMemberId').value         = data.member_id;
  document.getElementById('updateName').value            = data.name || '';
  document.getElementById('modalMemberName').textContent = data.name || '';

  // tabBtnUpdate always works the same
  document.getElementById('tabBtnUpdate').onclick = function () {
    switchTab('update');
    document.getElementById('tabBtnUpdate').style.cssText =
      'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;color:var(--primary);border-bottom:3px solid var(--primary);cursor:pointer;';
  };

  // Calculate days left
  var daysLeft  = null;
  var isExpired = false;
  // Trust the DB status first — a member in expired history is always renewable
  if (data.status === 'inactive' || data.status === 'expired' || data.sub_status === 'expired') {
    isExpired = true;
  } else if (data.end_date) {
    var todayD = new Date(); todayD.setHours(0,0,0,0);
    var endDay = new Date(data.end_date); endDay.setHours(0,0,0,0);
    daysLeft   = Math.round((endDay - todayD) / (1000 * 60 * 60 * 24));
    isExpired  = daysLeft < 0;
  }

  // ── Block renewal if more than 3 days remaining ──────────
  var canRenew = isExpired || (daysLeft !== null && daysLeft <= 3);
  var renewBtn = document.getElementById('tabBtnRenew');

  if (!canRenew && daysLeft !== null) {
    // Can't use onclick on disabled button in some browsers — use data attribute + wrapper click
    renewBtn.disabled = false; // keep enabled so onclick fires
    renewBtn.setAttribute('data-blocked', 'true');
    renewBtn.setAttribute('data-days', daysLeft);
    renewBtn.title = 'Available when 3 days or less remain';
    renewBtn.style.cssText =
      'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;' +
      'color:#c0c9d9;border-bottom:3px solid transparent;cursor:not-allowed;opacity:0.5;pointer-events:auto;';
    renewBtn.onclick = function (e) {
      e.preventDefault();
      var d = parseInt(renewBtn.getAttribute('data-days'), 10);
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'info',
          title: 'Not Yet Available',
          html: 'Advance renewal is only allowed when <strong>3 days or less</strong> remain.<br><br>' +
                'This member still has <strong>' + d + ' day(s)</strong> left.',
          confirmButtonColor: '#1e78ff',
          confirmButtonText: 'Understood'
        });
      } else {
        alert('Not Yet Available\n\nAdvance renewal is only allowed when 3 days or less remain.\nThis member still has ' + d + ' day(s) left.');
      }
    };
  } else {
    // Enable the Renew tab normally
    renewBtn.disabled = false;
    renewBtn.title    = '';
    renewBtn.onclick  = function () {
      switchTab('renew');
      renewBtn.style.cssText =
        'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;' +
        'color:#10b981;border-bottom:3px solid #10b981;cursor:pointer;opacity:1;';
      document.getElementById('tabBtnUpdate').style.cssText =
        'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;' +
        'color:#6b7a99;border-bottom:3px solid transparent;cursor:pointer;';
    };
    renewBtn.style.cssText =
      'flex:1;padding:13px;font-size:13px;font-weight:700;border:none;background:transparent;' +
      'color:#6b7a99;border-bottom:3px solid transparent;cursor:pointer;opacity:1;';
  }

  // Set up renewal start/end dates
  var now = new Date();
  document.getElementById('renewStartDate').value = now.toISOString().split('T')[0];
  var renewEnd = new Date(now);
  renewEnd.setMonth(renewEnd.getMonth() + 1);
  document.getElementById('renewEndDate').value = renewEnd.toISOString().split('T')[0];

  // Show correct renewal alert banner
  document.getElementById('advanceRenewAlert').style.display = 'none';
  document.getElementById('expiredRenewAlert').style.display = 'none';

  if (isExpired) {
    document.getElementById('expiredRenewAlert').style.display = 'block';
  } else if (daysLeft !== null && daysLeft <= 3) {
    document.getElementById('daysLeftText').textContent = daysLeft;
    document.getElementById('advanceRenewAlert').style.display = 'block';
  }

  // Auto-open renew tab only if eligible
  var startTab = openTab || (canRenew ? 'renew' : 'update');
  switchTab(startTab);

  new bootstrap.Modal(document.getElementById('updateModal')).show();
}

function generateUserId() {
  var nameVal  = (document.getElementById("memberNameInput").value || '').trim();
  var btn      = document.getElementById("generateBtn");
  var statusEl = document.getElementById("userIdStatus");

  if (!nameVal) {
    statusEl.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-exclamation-circle me-1"></i>Please enter the full name first.</span>';
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
  statusEl.innerHTML = '';

  fetch("subscription_members.php?generate_id=1&name=" + encodeURIComponent(nameVal))
    .then(function(r) { return r.json(); })
    .then(function(d) {
      document.getElementById("userIdInput").value = d.id;
      statusEl.innerHTML = '<span style="color:#10b981;"><i class="fas fa-check-circle me-1"></i>User ID <strong>' + d.id + '</strong> is ready to use.</span>';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-rotate-right me-1"></i>Regenerate';
    })
    .catch(function() {
      statusEl.innerHTML = '<span style="color:#ef4444;">Failed to generate. Try again.</span>';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>Generate';
    });
}

/* ── Auto end-date for renew form inside combined modal ── */
document.addEventListener('DOMContentLoaded', function () {
  function updateEndDate() {
    var s = document.getElementById("startDate");
    var e = document.getElementById("endDate");
    if (s && e && s.value) {
      var d = new Date(s.value);
      d.setMonth(d.getMonth() + 1);
      e.value = d.toISOString().split("T")[0];
    }
  }
  var sd = document.getElementById("startDate");
  if (sd) { sd.addEventListener("change", updateEndDate); updateEndDate(); }

  var rs = document.getElementById("renewStartDate");
  if (rs) {
    rs.addEventListener("change", function () {
      var d = new Date(this.value);
      d.setMonth(d.getMonth() + 1);
      document.getElementById("renewEndDate").value = d.toISOString().split("T")[0];
    });
  }
});
// ── Prevent duplicate registration submission ─────────────
(function() {
  var form = document.getElementById('subRegForm');
  var btn  = document.getElementById('subRegBtn');
  var txt  = document.getElementById('subRegBtnText');
  var submitted = false;
  if (form) {
    form.addEventListener('submit', function(e) {
      if (submitted) { e.preventDefault(); return false; }
      submitted = true;
      btn.disabled      = true;
      btn.style.opacity = '0.7';
      txt.textContent   = 'Registering…';
    });
  }
})();

</script>
<?php include 'footer.php'; ?>