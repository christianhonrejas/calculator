<?php
require_once 'auth.php';
$pageTitle = 'Staff Salaries';

// Superadmin and manager only
if (!in_array($_SESSION['admin_role'], ['superadmin', 'admin'])) {
    header('Location: walkin_members.php');
    exit;
}

// Ensure table exists — uses free-text staff_name and staff_role (no FK)
$conn->query("CREATE TABLE IF NOT EXISTS staff_salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    staff_name VARCHAR(100) NOT NULL,
    staff_role VARCHAR(100) NOT NULL DEFAULT 'Staff',
    amount DECIMAL(10,2) NOT NULL,
    month DATE NOT NULL COMMENT 'First day of the salary month e.g. 2026-03-01',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Migrate old table: add staff_role column if missing
$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM staff_salaries");
if ($colRes) { while($c = $colRes->fetch_assoc()) $cols[] = $c['Field']; }
if (!in_array('staff_role', $cols))
    $conn->query("ALTER TABLE staff_salaries ADD COLUMN staff_role VARCHAR(100) NOT NULL DEFAULT 'Staff' AFTER staff_name");

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_salary') {
        $staffName = trim($_POST['staff_name'] ?? '');
        $staffRole = trim($_POST['staff_role'] ?? '');
        $amount    = floatval($_POST['amount'] ?? 0);
        $month     = $_POST['month'] ?? '';

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $monthDate = $month . '-01';
        } else {
            $errorMsg = 'Invalid month format.';
        }

        if (!$errorMsg) {
            if ($monthDate > date('Y-m-01')) {
                $errorMsg = 'Cannot record salary for a future month. Please select the current month or a past month.';
            } elseif (!$staffName || !$staffRole || $amount <= 0) {
                $errorMsg = 'Please fill in all fields and enter a valid amount.';
            } else {
                $adminId = intval($_SESSION['admin_id'] ?? 0);
                $stmt = $conn->prepare("INSERT INTO staff_salaries (admin_id, staff_name, staff_role, amount, month) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issds", $adminId, $staffName, $staffRole, $amount, $monthDate);
                $stmt->execute();
                $stmt->close();
                $successMsg = "Salary of <strong>&#8369;" . number_format($amount, 2) . "</strong> recorded for <strong>" . htmlspecialchars($staffName) . "</strong>.";
            }
        }
    }

    if ($action === 'delete_salary') {
        $id = intval($_POST['salary_id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM staff_salaries WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        $successMsg = "Salary record deleted.";
    }
}

// Salary records - use prepared statements
$filterMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$filterDate  = $filterMonth . '-01';

$salStmt = $conn->prepare("SELECT * FROM staff_salaries WHERE month = ? ORDER BY staff_name ASC");
$salStmt->bind_param("s", $filterDate);
$salStmt->execute();
$salaries = $salStmt->get_result();
$salStmt->close();

$totStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as t FROM staff_salaries WHERE month = ?");
$totStmt->bind_param("s", $filterDate);
$totStmt->execute();
$totalSalary = $totStmt->get_result()->fetch_assoc()['t'];
$totStmt->close();

include 'header.php';
?>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Staff Salaries</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Record and manage monthly staff salary expenses</p>
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

<!-- Add Salary Form -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-plus-circle me-2" style="color:var(--primary)"></i>Record Salary</span>
  </div>
  <div class="section-card-body">
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_salary">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Staff Name <span class="text-danger">*</span></label>
          <input type="text" name="staff_name" class="form-control" placeholder="e.g. Juan dela Cruz" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Role <span class="text-danger">*</span></label>
          <input type="text" name="staff_role" class="form-control" placeholder="e.g. Trainer, Cleaner" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Month <span class="text-danger">*</span></label>
          <input type="month" name="month" class="form-control" value="<?= date('Y-m') ?>" max="<?= date('Y-m') ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Amount (&#8369;) <span class="text-danger">*</span></label>
          <input type="number" name="amount" class="form-control" placeholder="e.g. 8000" min="1" step="0.01" required>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn-primary-custom w-100" style="justify-content:center;">
            <i class="fas fa-plus me-1"></i> Record
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Filter + Summary -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-money-bill-wave me-2" style="color:#ef4444"></i>Salary Records</span>
    <div class="d-flex align-items-center gap-3">
      <form method="GET" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0" style="font-size:13px;white-space:nowrap;">Filter Month:</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($filterMonth) ?>" style="width:160px;">
        <button type="submit" class="btn-primary-custom" style="padding:7px 14px;font-size:12px;"><i class="fas fa-filter"></i></button>
      </form>
      <div class="stat-card py-2 px-3" style="min-width:0;text-align:center;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.15);">
        <div class="card-label" style="font-size:11px;color:#ef4444;">Total Salary — <?= date('F Y', strtotime($filterDate)) ?></div>
        <div class="card-value" style="font-size:18px;color:#ef4444;">&#8369;<?= number_format($totalSalary, 2) ?></div>
      </div>
    </div>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0" id="salaryTable">
        <thead>
          <tr><th>Staff Name</th><th>Role</th><th>Month</th><th>Amount</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if ($salaries && $salaries->num_rows > 0):
          while($s = $salaries->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['staff_name']) ?></strong></td>
            <td>
              <span style="background:rgba(16,185,129,0.12);color:#059669;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">
                <?= htmlspecialchars($s['staff_role']) ?>
              </span>
            </td>
            <td style="font-size:13px;"><?= date('F Y', strtotime($s['month'])) ?></td>
            <td><strong style="color:#ef4444;">&#8369;<?= number_format($s['amount'], 2) ?></strong></td>
            <td>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this salary record?')">
                <input type="hidden" name="action" value="delete_salary">
                <input type="hidden" name="salary_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No salary records for <?= date('F Y', strtotime($filterDate)) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScripts = '';
include 'footer.php';
?>
