<?php
require_once 'auth.php';
$pageTitle = 'Reports Dashboard';

// Revenue summaries (gym payments + supplement sales combined)
$dailyRev   = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE payment_date = CURDATE()")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE sale_date = CURDATE() AND payment_status='Paid'")->fetch_assoc()['r']);
$weeklyRev  = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEARWEEK(payment_date,1) = YEARWEEK(CURDATE(),1)")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEARWEEK(sale_date,1) = YEARWEEK(CURDATE(),1) AND payment_status='Paid'")->fetch_assoc()['r']);
$monthlyRev = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) AND payment_status='Paid'")->fetch_assoc()['r']);

$todayRev     = $dailyRev;
$yesterdayRev = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE payment_date = DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetch_assoc()['r'])
              + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE sale_date = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND payment_status='Paid'")->fetch_assoc()['r']);
$lastWeekRev  = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEARWEEK(payment_date,1) = YEARWEEK(DATE_SUB(CURDATE(),INTERVAL 7 DAY),1)")->fetch_assoc()['r'])
              + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEARWEEK(sale_date,1) = YEARWEEK(DATE_SUB(CURDATE(),INTERVAL 7 DAY),1) AND payment_status='Paid'")->fetch_assoc()['r']);
$lastMonthRev = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))")->fetch_assoc()['r'])
              + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND payment_status='Paid'")->fetch_assoc()['r']);

// Monthly revenue data (gym + supplements)
$monthlyRevData    = [];
$monthlyWalkinData = [];
$monthlySubData    = [];
$monthlySuppData   = [];
$monthlySalaryData = [];
for ($m = 1; $m <= 12; $m++) {
    $rw   = (float)$conn->query("SELECT COALESCE(SUM(p.amount),0) as r FROM payments p LEFT JOIN members m ON p.member_id = m.member_id WHERE YEAR(p.payment_date) = YEAR(CURDATE()) AND MONTH(p.payment_date) = $m AND m.member_type = 'walkin'")->fetch_assoc()['r'];
    $rs   = (float)$conn->query("SELECT COALESCE(SUM(p.amount),0) as r FROM payments p LEFT JOIN members m ON p.member_id = m.member_id WHERE YEAR(p.payment_date) = YEAR(CURDATE()) AND MONTH(p.payment_date) = $m AND m.member_type = 'subscription'")->fetch_assoc()['r'];
    $rsp  = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = $m AND payment_status='Paid'")->fetch_assoc()['r'];
    $mDate = date('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-01';
    $rsal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as r FROM staff_salaries WHERE month = '$mDate'")->fetch_assoc()['r'];
    $monthlyRevData[]    = $rw + $rs + $rsp;
    $monthlyWalkinData[] = $rw;
    $monthlySubData[]    = $rs;
    $monthlySuppData[]   = $rsp;
    $monthlySalaryData[] = $rsal;
}

// Report generation
$reportData = null;
$fromDate = date('Y-m-01');
$toDate = date('Y-m-d');
$memberType = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $fromDate = (isset($_POST['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['from'])) ? $_POST['from'] : date('Y-m-01');
    $toDate   = (isset($_POST['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['to']))   ? $_POST['to']   : date('Y-m-d');
    $memberType = $_POST['member_type'] ?? 'all';
    if (!in_array($memberType, ['all', 'walkin', 'subscription'])) { $memberType = 'all'; }
    
    $typeWhere = '';
    if ($memberType !== 'all') {
        $safe = $conn->real_escape_string($memberType);
        $typeWhere = " AND m.member_type = '$safe'";
    }

    // Total members = distinct members who checked in during the period
    $tmWhere = "DATE(al.time_in) BETWEEN '$fromDate' AND '$toDate'" . ($memberType !== 'all' ? " AND al.member_type = '$memberType'" : "");
    $totalMembers = (int)$conn->query("SELECT COUNT(DISTINCT al.member_id) as c FROM attendance_logs al WHERE $tmWhere")->fetch_assoc()['c'];

    // Total attendance = every check-in row in the period
    $taWhere = "DATE(time_in) BETWEEN '$fromDate' AND '$toDate'" . ($memberType !== 'all' ? " AND member_type = '$memberType'" : "");
    $totalAttend = (int)$conn->query("SELECT COUNT(*) as c FROM attendance_logs WHERE $taWhere")->fetch_assoc()['c'];

    // Active members currently in the members table
    $amWhere = "status = 'active'" . ($memberType !== 'all' ? " AND member_type = '$memberType'" : "");
    $activeMembers = (int)$conn->query("SELECT COUNT(*) as c FROM members WHERE $amWhere")->fetch_assoc()['c'];

    // Walk-in count = distinct walk-in members who checked in during period
    $walkinCount = ($memberType === 'subscription') ? 0
        : (int)$conn->query("SELECT COUNT(DISTINCT member_id) as c FROM attendance_logs WHERE DATE(time_in) BETWEEN '$fromDate' AND '$toDate' AND member_type = 'walkin'")->fetch_assoc()['c'];

    // Subscription count = distinct subscribers who checked in during period
    $subCount = ($memberType === 'walkin') ? 0
        : (int)$conn->query("SELECT COUNT(DISTINCT member_id) as c FROM attendance_logs WHERE DATE(time_in) BETWEEN '$fromDate' AND '$toDate' AND member_type = 'subscription'")->fetch_assoc()['c'];

    // Revenue from payments table (actual payment records)
    $walkinRev = ($memberType === 'subscription') ? 0
        : (float)$conn->query("SELECT COALESCE(SUM(p.amount),0) as r FROM payments p LEFT JOIN members m ON p.member_id = m.member_id WHERE p.payment_date BETWEEN '$fromDate' AND '$toDate' AND m.member_type = 'walkin'")->fetch_assoc()['r'];
    $subRev = ($memberType === 'walkin') ? 0
        : (float)$conn->query("SELECT COALESCE(SUM(p.amount),0) as r FROM payments p LEFT JOIN members m ON p.member_id = m.member_id WHERE p.payment_date BETWEEN '$fromDate' AND '$toDate' AND m.member_type = 'subscription'")->fetch_assoc()['r'];
    $suppRev    = $conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE sale_date BETWEEN '$fromDate' AND '$toDate' AND payment_status='Paid'")->fetch_assoc()['r'];
    $gymRev     = $conn->query("SELECT COALESCE(SUM(p.amount),0) as r FROM payments p LEFT JOIN members m ON p.member_id = m.member_id WHERE p.payment_date BETWEEN '$fromDate' AND '$toDate'$typeWhere")->fetch_assoc()['r'];
    $overallRev = $gymRev + ($memberType === 'all' ? $suppRev : 0);

    // Salary expenses for the period (by month overlap)
    $salaryExpense = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as r FROM staff_salaries WHERE month BETWEEN DATE_FORMAT('$fromDate','%Y-%m-01') AND DATE_FORMAT('$toDate','%Y-%m-01')")->fetch_assoc()['r'];
    $netRevenue    = $overallRev - $salaryExpense;

    // Salary breakdown for the period
    $salaryHistory = $conn->query("SELECT s.staff_name, s.staff_role, s.amount, s.month FROM staff_salaries s WHERE s.month BETWEEN DATE_FORMAT('$fromDate','%Y-%m-01') AND DATE_FORMAT('$toDate','%Y-%m-01') ORDER BY s.month DESC, s.staff_name ASC");

    $reportData = [
        'total_members'         => $totalMembers,
        'total_attendance'      => $totalAttend,
        'active_members'        => $activeMembers,
        'walkin_count'          => $walkinCount,
        'sub_count'             => $subCount,
        'walkin_revenue'        => $walkinRev,
        'subscription_revenue'  => $subRev,
        'supplement_revenue'    => $suppRev,
        'overall_revenue'       => $overallRev,
        'salary_expense'        => $salaryExpense,
        'net_revenue'           => $netRevenue,
    ];
    
    // Payment history
    $paymentHistory = $conn->query("
        SELECT p.*, m.name 
        FROM payments p 
        LEFT JOIN members m ON p.member_id = m.member_id 
        WHERE p.payment_date BETWEEN '$fromDate' AND '$toDate'$typeWhere
        ORDER BY p.payment_date DESC 
    ");

    // Supplement sales history
    $suppHistory = $conn->query("
        SELECT receipt_no, member_name, supplement_name, category, quantity, price, total_amount, payment_method, sale_date, sale_time
        FROM supplement_sales
        WHERE sale_date BETWEEN '$fromDate' AND '$toDate'
        ORDER BY sale_date DESC, sale_time DESC
    ");
}

include 'header.php';
?>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Reports Dashboard</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Attendance activity, revenue records, and payment history</p>
  </div>
</div>

<!-- Revenue Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Daily Revenue (Today)</div>
          <div class="card-value" style="font-size:24px;">₱<?= number_format($dailyRev, 2) ?></div>
        </div>
        <div class="card-icon icon-green"><i class="fas fa-calendar-day"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Weekly Revenue</div>
          <div class="card-value" style="font-size:24px;">₱<?= number_format($weeklyRev, 2) ?></div>
        </div>
        <div class="card-icon icon-blue"><i class="fas fa-calendar-week"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Monthly Revenue</div>
          <div class="card-value" style="font-size:24px;">₱<?= number_format($monthlyRev, 2) ?></div>
        </div>
        <div class="card-icon icon-orange"><i class="fas fa-calendar-alt"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Revenue Comparison Chart -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="section-card">
      <div class="section-card-header">
        <span class="section-card-title"><i class="fas fa-chart-bar me-2" style="color:var(--primary)"></i>Revenue Comparison</span>
      </div>
      <div class="section-card-body">
        <div class="chart-container"><canvas id="revCompChart" height="220"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="section-card">
      <div class="section-card-header">
        <span class="section-card-title"><i class="fas fa-chart-line me-2" style="color:var(--success)"></i>Monthly Revenue Breakdown (<?= date('Y') ?>)</span>
      </div>
      <div class="section-card-body">
        <div class="chart-container"><canvas id="monthlyBreakChart" height="220"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Report Filters -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-sliders me-2" style="color:var(--primary)"></i>Report Filters</span>
  </div>
  <div class="section-card-body">
    <form method="POST" action="">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Membership Type</label>
          <select name="member_type" class="form-select">
            <option value="all" <?= $memberType === 'all' ? 'selected' : '' ?>>All</option>
            <option value="walkin" <?= $memberType === 'walkin' ? 'selected' : '' ?>>Walk-in</option>
            <option value="subscription" <?= $memberType === 'subscription' ? 'selected' : '' ?>>Subscription</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" name="generate" class="btn-primary-custom w-100" style="justify-content:center;">
            <i class="fas fa-chart-bar"></i> Generate Report
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Report Summary -->
<?php if ($reportData): ?>
<div class="section-card mb-4" id="reportSummary">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-chart-pie me-2" style="color:var(--success)"></i>Report Summary</span>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn-outline-custom" onclick="window.print()" style="padding:7px 14px;font-size:12px;">
        <i class="fas fa-print me-1"></i>Print Report
      </button>
      <button class="btn-outline-custom" onclick="exportReportPDF()" style="padding:7px 14px;font-size:12px;">
        <i class="fas fa-file-pdf me-1"></i>Export PDF
      </button>
      <button class="btn-outline-custom" onclick="exportReportCSV()" style="padding:7px 14px;font-size:12px;">
        <i class="fas fa-file-csv me-1"></i>Export CSV
      </button>
    </div>
  </div>
  <div class="section-card-body">
    <div class="alert" style="background:#f0fff8;border:1px solid #a7f3d0;border-radius:10px;padding:12px 18px;color:#065f46;font-size:14px;font-weight:600;margin-bottom:20px;">
      <i class="fas fa-check-circle me-2"></i>Report Ready — Period: <?= date('M d, Y', strtotime($fromDate)) ?> to <?= date('M d, Y', strtotime($toDate)) ?>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:var(--text-muted);font-weight:600;">Total Members</div>
          <div style="font-size:24px;font-weight:800;color:var(--text-main);"><?= number_format($reportData['total_members']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:var(--text-muted);font-weight:600;">Total Attendance</div>
          <div style="font-size:24px;font-weight:800;color:var(--text-main);"><?= number_format($reportData['total_attendance']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:var(--text-muted);font-weight:600;">Active Members</div>
          <div style="font-size:24px;font-weight:800;color:var(--text-main);"><?= number_format($reportData['active_members']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:var(--text-muted);font-weight:600;">Walk-in Members</div>
          <div style="font-size:24px;font-weight:800;color:var(--text-main);"><?= number_format($reportData['walkin_count']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:#8b5cf6;font-weight:600;">Subscription Members</div>
          <div style="font-size:24px;font-weight:800;color:var(--text-main);"><?= number_format($reportData['sub_count']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:var(--text-muted);font-weight:600;">Total Walk-in Revenue</div>
          <div style="font-size:24px;font-weight:800;color:var(--success);">₱<?= number_format($reportData['walkin_revenue'], 2) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#f8faff;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:var(--text-muted);font-weight:600;">Total Subscription Revenue</div>
          <div style="font-size:24px;font-weight:800;color:#8b5cf6;">₱<?= number_format($reportData['subscription_revenue'], 2) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#fff8e6;border-radius:12px;padding:16px;">
          <div style="font-size:12px;color:#d97706;font-weight:600;">Supplement Sales Revenue</div>
          <div style="font-size:24px;font-weight:800;color:#f59e0b;">₱<?= number_format($reportData['supplement_revenue'], 2) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div style="background:#fff0f0;border-radius:12px;padding:16px;border:1px solid rgba(239,68,68,0.15);">
          <div style="font-size:12px;color:#ef4444;font-weight:600;"><i class="fas fa-minus-circle me-1"></i>Staff Salary Expenses</div>
          <div style="font-size:24px;font-weight:800;color:#ef4444;">₱<?= number_format($reportData['salary_expense'], 2) ?></div>
        </div>
      </div>
      <div class="col-12">
        <div style="background:linear-gradient(135deg,rgba(30,120,255,0.08),rgba(16,185,129,0.08));border:1.5px solid rgba(30,120,255,0.15);border-radius:12px;padding:20px;">
          <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end;">
            <div style="flex:1;min-width:180px;">
              <div style="font-size:13px;color:var(--text-muted);font-weight:600;margin-bottom:4px;">Gross Revenue</div>
              <div style="font-size:28px;font-weight:800;color:var(--primary);font-family:'Barlow Condensed',sans-serif;">₱<?= number_format($reportData['overall_revenue'], 2) ?></div>
            </div>
            <div style="font-size:28px;color:#ef4444;font-weight:700;padding-bottom:4px;">−</div>
            <div style="flex:1;min-width:180px;">
              <div style="font-size:13px;color:#ef4444;font-weight:600;margin-bottom:4px;">Salary Expenses</div>
              <div style="font-size:28px;font-weight:800;color:#ef4444;font-family:'Barlow Condensed',sans-serif;">₱<?= number_format($reportData['salary_expense'], 2) ?></div>
            </div>
            <div style="font-size:28px;color:#10b981;font-weight:700;padding-bottom:4px;">=</div>
            <div style="flex:2;min-width:200px;">
              <div style="font-size:13px;color:#10b981;font-weight:600;margin-bottom:4px;">Net Revenue</div>
              <div style="font-size:36px;font-weight:800;color:<?= $reportData['net_revenue'] >= 0 ? '#10b981' : '#ef4444' ?>;font-family:'Barlow Condensed',sans-serif;">₱<?= number_format($reportData['net_revenue'], 2) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment History Table -->
    <?php if (isset($paymentHistory) && $paymentHistory->num_rows > 0): ?>
    <h6 class="fw-700 mb-3" style="font-size:14px;margin-top:20px;"><i class="fas fa-money-bill-wave me-2" style="color:var(--primary)"></i>Payment History (Walk-ins &amp; New Subscriptions)</h6>
    <div class="table-responsive">
      <table class="table" id="paymentTable" style="font-size:13px;">
        <thead>
          <tr><th>Member ID</th><th>Name</th><th>Type</th><th>Amount</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php while($p = $paymentHistory->fetch_assoc()): ?>
          <tr>
            <td><code style="font-size:11px;"><?= htmlspecialchars($p['member_id']) ?></code></td>
            <td><?= htmlspecialchars($p['name'] ?? 'Unknown') ?></td>
            <td><span class="badge-<?= $p['member_type'] === 'walkin' ? 'walkin' : 'subscription' ?>"><?= $p['member_type'] === 'walkin' ? 'Walk-in' : 'Subscription' ?></span></td>
            <td><strong>₱<?= number_format($p['amount'], 2) ?></strong></td>
            <td><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Attendance History Table -->
    <?php
    $ahWhere = "DATE(al.time_in) BETWEEN '$fromDate' AND '$toDate'";
    if ($memberType !== 'all') $ahWhere .= " AND al.member_type = '$memberType'";
    $attendanceHistory = $conn->query("
        SELECT al.member_id, al.member_name, al.member_type, al.time_in, al.access_result
        FROM attendance_logs al
        WHERE $ahWhere
        ORDER BY al.time_in DESC
    ");
    ?>
    <?php if ($attendanceHistory && $attendanceHistory->num_rows > 0): ?>
    <h6 class="fw-700 mb-3" style="font-size:14px;margin-top:24px;"><i class="fas fa-clipboard-list me-2" style="color:var(--primary)"></i>Attendance Records During Period</h6>
    <div class="table-responsive">
      <table class="table" id="attendanceHistoryTable" style="font-size:13px;">
        <thead>
          <tr><th>Member Name</th><th>UID</th><th>Type</th><th>Date</th><th>Time-In</th><th>Access</th></tr>
        </thead>
        <tbody>
          <?php while($ar = $attendanceHistory->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($ar['member_name']) ?></strong></td>
            <td><code style="font-size:11px;"><?= htmlspecialchars($ar['member_id']) ?></code></td>
            <td><span class="badge-<?= $ar['member_type'] === 'walkin' ? 'walkin' : 'subscription' ?>"><?= $ar['member_type'] === 'walkin' ? 'Walk-in' : 'Subscriber' ?></span></td>
            <td><?= date('M d, Y', strtotime($ar['time_in'])) ?></td>
            <td><?= date('h:i A', strtotime($ar['time_in'])) ?></td>
            <td>
              <?php if ($ar['access_result'] === 'granted'): ?>
              <span style="color:var(--success);font-weight:600;font-size:12px;"><i class="fas fa-check-circle me-1"></i>Granted</span>
              <?php else: ?>
              <span style="color:#ef4444;font-weight:600;font-size:12px;"><i class="fas fa-times-circle me-1"></i>Denied</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Supplement Sales History Table -->
    <?php if (isset($suppHistory) && $suppHistory->num_rows > 0): ?>
    <h6 class="fw-700 mb-3" style="font-size:14px;margin-top:24px;"><i class="fas fa-flask me-2" style="color:#f59e0b"></i>Supplement Sales History</h6>
    <div class="table-responsive">
      <table class="table" id="suppHistoryTable" style="font-size:13px;">
        <thead>
          <tr><th>Receipt</th><th>Customer</th><th>Supplement</th><th>Category</th><th>Qty</th><th>Total</th><th>Payment</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php while($s = $suppHistory->fetch_assoc()): ?>
          <tr>
            <td><code style="font-size:11px;"><?= htmlspecialchars($s['receipt_no']) ?></code></td>
            <td><?= htmlspecialchars($s['member_name']) ?></td>
            <td><strong><?= htmlspecialchars($s['supplement_name']) ?></strong></td>
            <td><span style="background:rgba(245,158,11,.12);color:#d97706;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;"><?= htmlspecialchars($s['category'] ?? '—') ?></span></td>
            <td><?= (int)$s['quantity'] ?></td>
            <td><strong style="color:#f59e0b;">₱<?= number_format($s['total_amount'], 2) ?></strong></td>
            <td style="font-size:12px;"><?= htmlspecialchars($s['payment_method']) ?></td>
            <td><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Salary Expenses Table -->
    <?php if (isset($salaryHistory) && $salaryHistory->num_rows > 0): ?>
    <h6 class="fw-700 mb-3" style="font-size:14px;margin-top:24px;"><i class="fas fa-money-bill-wave me-2" style="color:#ef4444"></i>Staff Salary Expenses</h6>
    <div class="table-responsive">
      <table class="table" id="salaryHistoryTable" style="font-size:13px;">
        <thead>
          <tr><th>Staff Name</th><th>Role</th><th>Month</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <?php while($s = $salaryHistory->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['staff_name']) ?></strong></td>
            <td>
              <span style="background:rgba(16,185,129,0.12);color:#059669;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">
                <?= htmlspecialchars($s['staff_role'] ?? 'Staff') ?>
              </span>
            </td>
            <td><?= date('F Y', strtotime($s['month'])) ?></td>
            <td><strong style="color:#ef4444;">₱<?= number_format($s['amount'], 2) ?></strong></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Print styles -->
<style>
@media print {
  .sidebar, .top-navbar, .section-card:first-child, form, .btn-outline-custom, .btn-primary-custom { display: none !important; }
  .main-wrapper { margin-left: 0 !important; }
  body { background: white; }
  .section-card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
  canvas { max-height: 200px !important; }
  #printReportHeader { display: block !important; }
}
#printReportHeader { display: none; margin-bottom: 20px; }
</style>
<div id="printReportHeader">
  <h3 style="font-family:'Barlow Condensed',sans-serif;font-weight:800;">Diozabeth Fitness Gym Management System</h3>
  <p>Reports Dashboard | Period: <?= date('M d, Y', strtotime($fromDate)) ?> – <?= date('M d, Y', strtotime($toDate)) ?></p>
  <p>Generated: <?= date('M d, Y h:i A') ?></p>
  <hr>
</div>

<?php
$extraScripts = '
' . '<script src="' . (file_exists(__DIR__.'/assets/js/chart.umd.min.js') ? 'assets/js/chart.umd.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js') . '"></script>' . '
' . '<script src="' . (file_exists(__DIR__.'/assets/js/jspdf.umd.min.js') ? 'assets/js/jspdf.umd.min.js' : 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js') . '"></script>' . '
' . '<script src="' . (file_exists(__DIR__.'/assets/js/jspdf.plugin.autotable.min.js') ? 'assets/js/jspdf.plugin.autotable.min.js' : 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.6.0/jspdf.plugin.autotable.min.js') . '"></script>' . '
<script>
Chart.defaults.font.family = "Barlow, sans-serif";

// Revenue Comparison
new Chart(document.getElementById("revCompChart"), {
  type: "bar",
  data: {
    labels: ["Yesterday","Today","Last Week","Last Month"],
    datasets: [{
      label: "Revenue (₱)",
      data: [' . $yesterdayRev . ',' . $todayRev . ',' . $lastWeekRev . ',' . $lastMonthRev . '],
      backgroundColor: ["rgba(245,158,11,0.7)","rgba(30,120,255,0.7)","rgba(139,92,246,0.7)","rgba(16,185,129,0.7)"],
      borderColor: ["#f59e0b","#1e78ff","#8b5cf6","#10b981"],
      borderWidth: 2, borderRadius: 8,
    }]
  },
  options: {
    responsive: true, plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { callback: v => "₱" + v.toLocaleString() } },
      x: { grid: { display: false } }
    }
  }
});

// Monthly Breakdown
new Chart(document.getElementById("monthlyBreakChart"), {
  type: "bar",
  data: {
    labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
    datasets: [
      { label: "Walk-in", data: ' . json_encode($monthlyWalkinData) . ', backgroundColor: "rgba(30,120,255,0.7)", borderRadius: 6 },
      { label: "Subscription", data: ' . json_encode($monthlySubData) . ', backgroundColor: "rgba(139,92,246,0.7)", borderRadius: 6 },
      { label: "Supplements", data: ' . json_encode($monthlySuppData) . ', backgroundColor: "rgba(245,158,11,0.7)", borderRadius: 6 },
      { label: "Salary Expenses", data: ' . json_encode($monthlySalaryData) . ', backgroundColor: "rgba(239,68,68,0.7)", borderRadius: 6, type: "line", borderColor: "#ef4444", borderWidth: 2, pointBackgroundColor: "#ef4444", fill: false, yAxisID: "y" }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: "top" } },
    scales: {
      y: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { callback: v => "₱" + v.toLocaleString() }, stacked: true },
      x: { grid: { display: false }, stacked: true }
    }
  }
});

$(document).ready(function() {
  if (document.getElementById("paymentTable")) {
    $("#paymentTable").DataTable({ responsive: true, order: [[4,"desc"]], pageLength: -1, lengthMenu: [[10,25,50,100,-1],["10","25","50","100","All"]] });
  }
  if (document.getElementById("attendanceHistoryTable")) {
    $("#attendanceHistoryTable").DataTable({ responsive: true, order: [[3,"desc"],[4,"desc"]], pageLength: -1, lengthMenu: [[10,25,50,100,-1],["10","25","50","100","All"]] });
  }
  if (document.getElementById("suppHistoryTable")) {
    $("#suppHistoryTable").DataTable({ responsive: true, order: [[7,"desc"]], pageLength: -1, lengthMenu: [[10,25,50,100,-1],["10","25","50","100","All"]] });
  }
});

function exportReportPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  doc.setFontSize(16);
  doc.setFont("helvetica","bold");
  doc.text("Diozabeth Fitness Gym Management System", 14, 18);
  doc.setFontSize(11);
  doc.setFont("helvetica","normal");
  doc.text("Reports Dashboard", 14, 26);
  doc.text("Generated: " + new Date().toLocaleString("en-PH"), 14, 33);
  
  ' . ($reportData ? '
  const summary = [
    ["Total Members", "' . number_format($reportData["total_members"]) . '"],
    ["Total Attendance", "' . number_format($reportData["total_attendance"]) . '"],
    ["Active Members", "' . number_format($reportData["active_members"]) . '"],
    ["Walk-in Members", "' . number_format($reportData["walkin_count"]) . '"],
    ["Subscription Members", "' . number_format($reportData["sub_count"]) . '"],
    ["Walk-in Revenue", "₱' . number_format($reportData["walkin_revenue"], 2) . '"],
    ["Subscription Revenue", "₱' . number_format($reportData["subscription_revenue"], 2) . '"],
    ["Supplement Revenue", "₱' . number_format($reportData["supplement_revenue"], 2) . '"],
    ["Gross Revenue", "₱' . number_format($reportData["overall_revenue"], 2) . '"],
    ["Staff Salary Expenses", "- ₱' . number_format($reportData["salary_expense"], 2) . '"],
    ["Net Revenue", "₱' . number_format($reportData["net_revenue"], 2) . '"]
  ];
  doc.autoTable({
    head: [["Metric","Value"]],
    body: summary,
    startY: 40,
    styles: { fontSize: 10 },
    headStyles: { fillColor: [30,120,255], textColor: 255 },
    columnStyles: { 1: { fontStyle: "bold" } },
    didParseCell: function(data) {
      if (data.row.index === 9 && data.column.index === 1) { data.cell.styles.textColor = [239,68,68]; }
      if (data.row.index === 10 && data.column.index === 1) { data.cell.styles.textColor = [16,185,129]; data.cell.styles.fontStyle = "bold"; }
    }
  });
  ' : '') . '
  
  doc.save("gym_report_" + new Date().toISOString().split("T")[0] + ".pdf");
}

function exportReportCSV() {
  const rows = [];
  const period = "' . date('M d, Y', strtotime($fromDate)) . ' to ' . date('M d, Y', strtotime($toDate)) . '";
  const generated = new Date().toLocaleString("en-PH");

  rows.push(["Diozabeth Fitness Gym Management System"]);
  rows.push(["Reports Dashboard"]);
  rows.push(["Period:", period]);
  rows.push(["Generated:", generated]);
  rows.push([]);

  ' . ($reportData ? '
  rows.push(["=== REPORT SUMMARY ==="]);
  rows.push(["Metric", "Value"]);
  rows.push(["Total Members", "' . number_format($reportData["total_members"]) . '"]);
  rows.push(["Total Attendance", "' . number_format($reportData["total_attendance"]) . '"]);
  rows.push(["Active Members", "' . number_format($reportData["active_members"]) . '"]);
  rows.push(["Walk-in Members", "' . number_format($reportData["walkin_count"]) . '"]);
  rows.push(["Subscription Members", "' . number_format($reportData["sub_count"]) . '"]);
  rows.push(["Walk-in Revenue", "PHP ' . number_format($reportData["walkin_revenue"], 2) . '"]);
  rows.push(["Subscription Revenue", "PHP ' . number_format($reportData["subscription_revenue"], 2) . '"]);
  rows.push(["Supplement Revenue", "PHP ' . number_format($reportData["supplement_revenue"], 2) . '"]);
  rows.push(["Gross Revenue", "PHP ' . number_format($reportData["overall_revenue"], 2) . '"]);
  rows.push(["Staff Salary Expenses", "- PHP ' . number_format($reportData["salary_expense"], 2) . '"]);
  rows.push(["Net Revenue", "PHP ' . number_format($reportData["net_revenue"], 2) . '"]);
  rows.push([]);
  ' : '') . '

  const tbl = document.getElementById("paymentTable");
  if (tbl) {
    rows.push(["=== MEMBER PAYMENT HISTORY ==="]);
    rows.push(["Member ID", "Name", "Type", "Amount", "Date"]);
    tbl.querySelectorAll("tbody tr").forEach(tr => {
      const cells = tr.querySelectorAll("td");
      if (cells.length >= 5) {
        rows.push([cells[0].innerText.trim(),cells[1].innerText.trim(),cells[2].innerText.trim(),cells[3].innerText.trim(),cells[4].innerText.trim()]);
      }
    });
    rows.push([]);
  }

  const atbl = document.getElementById("attendanceHistoryTable");
  if (atbl) {
    rows.push(["=== ATTENDANCE RECORDS ==="]);
    rows.push(["Member Name", "UID", "Type", "Date", "Time-In", "Access"]);
    atbl.querySelectorAll("tbody tr").forEach(tr => {
      const cells = tr.querySelectorAll("td");
      if (cells.length >= 6) {
        rows.push([cells[0].innerText.trim(),cells[1].innerText.trim(),cells[2].innerText.trim(),cells[3].innerText.trim(),cells[4].innerText.trim(),cells[5].innerText.trim()]);
      }
    });
    rows.push([]);
  }

  const stbl = document.getElementById("suppHistoryTable");
  if (stbl) {
    rows.push(["=== SUPPLEMENT SALES HISTORY ==="]);
    rows.push(["Receipt", "Customer", "Supplement", "Category", "Qty", "Total", "Payment", "Date"]);
    stbl.querySelectorAll("tbody tr").forEach(tr => {
      const cells = tr.querySelectorAll("td");
      if (cells.length >= 8) {
        rows.push([cells[0].innerText.trim(),cells[1].innerText.trim(),cells[2].innerText.trim(),cells[3].innerText.trim(),cells[4].innerText.trim(),cells[5].innerText.trim(),cells[6].innerText.trim(),cells[7].innerText.trim()]);
      }
    });
    rows.push([]);
  }

  const saltbl = document.getElementById("salaryHistoryTable");
  if (saltbl) {
    rows.push(["=== STAFF SALARY EXPENSES ==="]);
    rows.push(["Staff Name", "Role", "Month", "Amount"]);
    saltbl.querySelectorAll("tbody tr").forEach(tr => {
      const cells = tr.querySelectorAll("td");
      if (cells.length >= 4) {
        rows.push([cells[0].innerText.trim(),cells[1].innerText.trim(),cells[2].innerText.trim(),cells[3].innerText.trim()]);
      }
    });
  }

  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, "\\"")}"`).join(",")).join("\n");
  const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "gym_report_" + new Date().toISOString().split("T")[0] + ".csv";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}
</script>';
include 'footer.php';
?>