<?php
require_once 'auth.php';
$pageTitle = 'Dashboard';

// Stats
$totalMembers = $conn->query("SELECT COUNT(*) as c FROM members")->fetch_assoc()['c'];
$walkinToday = $conn->query("SELECT COUNT(*) as c FROM attendance_logs WHERE DATE(time_in) = CURDATE() AND member_type = 'walkin'")->fetch_assoc()['c'];
$activeSubscriptions = $conn->query("SELECT COUNT(*) as c FROM members m JOIN subscriptions s ON m.member_id = s.member_id WHERE s.status = 'active' AND s.end_date >= CURDATE()")->fetch_assoc()['c'];
$expiredMembers = $conn->query("SELECT COUNT(*) as c FROM members m JOIN subscriptions s ON m.member_id = s.member_id WHERE s.end_date < CURDATE() OR s.status = 'expired'")->fetch_assoc()['c'];

// Revenue stats (gym payments + supplement sales combined)
$dailyRev   = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE payment_date = CURDATE()")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE sale_date = CURDATE() AND payment_status='Paid'")->fetch_assoc()['r']);
$weeklyRev  = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEARWEEK(sale_date, 1) = YEARWEEK(CURDATE(), 1) AND payment_status='Paid'")->fetch_assoc()['r']);
$monthlyRev = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) AND payment_status='Paid'")->fetch_assoc()['r']);
$totalRev   = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments")->fetch_assoc()['r'])
            + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE payment_status='Paid'")->fetch_assoc()['r']);

// Revenue comparison
$todayRev     = $dailyRev;
$yesterdayRev = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE payment_date = DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetch_assoc()['r'])
              + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE sale_date = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND payment_status='Paid'")->fetch_assoc()['r']);
$lastWeekRev  = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEARWEEK(payment_date,1) = YEARWEEK(DATE_SUB(CURDATE(),INTERVAL 7 DAY),1)")->fetch_assoc()['r'])
              + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEARWEEK(sale_date,1) = YEARWEEK(DATE_SUB(CURDATE(),INTERVAL 7 DAY),1) AND payment_status='Paid'")->fetch_assoc()['r']);
$lastMonthRev = ($conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))")->fetch_assoc()['r'])
              + ($conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND payment_status='Paid'")->fetch_assoc()['r']);

// Monthly attendance chart (this year - by month)
$monthlyAttend = [];
for ($m = 1; $m <= 12; $m++) {
    $r = $conn->query("SELECT COUNT(*) as c FROM attendance_logs WHERE YEAR(time_in) = YEAR(CURDATE()) AND MONTH(time_in) = $m")->fetch_assoc()['c'];
    $monthlyAttend[] = (int)$r;
}

// Monthly revenue (this year - by month, gym + supplements)
$monthlyRevData = [];
for ($m = 1; $m <= 12; $m++) {
    $gym  = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as r FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = $m")->fetch_assoc()['r'];
    $supp = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = $m AND payment_status='Paid'")->fetch_assoc()['r'];
    $monthlyRevData[] = $gym + $supp;
}

// Weekly attendance (this week Mon-Sun)
$weeklyAttend = [];
$weekDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
for ($d = 0; $d < 7; $d++) {
    $date = date('Y-m-d', strtotime('monday this week +' . $d . ' days'));
    $r = $conn->query("SELECT COUNT(*) as c FROM attendance_logs WHERE DATE(time_in) = '$date'")->fetch_assoc()['c'];
    $weeklyAttend[] = (int)$r;
}

// Supplement revenue today
$suppRevToday = $conn->query("SELECT COALESCE(SUM(total_amount),0) as r FROM supplement_sales WHERE sale_date = CURDATE() AND payment_status='Paid'")->fetch_assoc()['r'];

// Recent attendance
$recentAttend = $conn->query("SELECT * FROM attendance_logs ORDER BY time_in DESC LIMIT 10");

include 'header.php';
?>

<!-- Page Header -->
<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;">Dashboard</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Welcome back, <?= htmlspecialchars($_SESSION['admin_name']) ?>! Here's what's happening today.</p>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Total Members</div>
          <div class="card-value"><?= number_format($totalMembers) ?></div>
        </div>
        <div class="card-icon icon-blue"><i class="fas fa-users"></i></div>
      </div>
      <div class="card-footer-text"><i class="fas fa-arrow-up me-1" style="color:var(--success)"></i>All registered</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Walk-in Today</div>
          <div class="card-value"><?= number_format($walkinToday) ?></div>
        </div>
        <div class="card-icon icon-green"><i class="fas fa-person-walking"></i></div>
      </div>
      <div class="card-footer-text"><i class="fas fa-clock me-1"></i>Today's visitors</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Active Subscriptions</div>
          <div class="card-value"><?= number_format($activeSubscriptions) ?></div>
        </div>
        <div class="card-icon icon-purple"><i class="fas fa-id-card"></i></div>
      </div>
      <div class="card-footer-text"><i class="fas fa-check me-1" style="color:var(--success)"></i>Currently active</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Expired Members</div>
          <div class="card-value"><?= number_format($expiredMembers) ?></div>
        </div>
        <div class="card-icon icon-red"><i class="fas fa-calendar-xmark"></i></div>
      </div>
      <div class="card-footer-text"><i class="fas fa-exclamation me-1" style="color:var(--danger)"></i>Need renewal</div>
    </div>
  </div>
</div>

<!-- Revenue Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Daily Revenue</div>
          <div class="card-value" style="">₱<?= number_format($dailyRev, 2) ?></div>
        </div>
        <div class="card-icon icon-green"><i class="fas fa-peso-sign"></i></div>
      </div>
      <div class="card-footer-text">Today</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Weekly Revenue</div>
          <div class="card-value" style="">₱<?= number_format($weeklyRev, 2) ?></div>
        </div>
        <div class="card-icon icon-blue"><i class="fas fa-peso-sign"></i></div>
      </div>
      <div class="card-footer-text">This week</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Monthly Revenue</div>
          <div class="card-value" style="">₱<?= number_format($monthlyRev, 2) ?></div>
        </div>
        <div class="card-icon icon-orange"><i class="fas fa-peso-sign"></i></div>
      </div>
      <div class="card-footer-text">This month</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="card-label">Total Revenue</div>
          <div class="card-value" style="">₱<?= number_format($totalRev, 2) ?></div>
        </div>
        <div class="card-icon icon-teal"><i class="fas fa-peso-sign"></i></div>
      </div>
      <div class="card-footer-text">All time</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <!-- Revenue Comparison -->
  <div class="col-md-6">
    <div class="section-card">
      <div class="section-card-header">
        <span class="section-card-title"><i class="fas fa-chart-bar me-2" style="color:var(--primary)"></i>Revenue Comparison</span>
        <select class="form-select form-select-sm" style="width:auto;" id="revCompFilter">
          <option value="all">All Periods</option>
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="lastweek">Last Week</option>
          <option value="lastmonth">Last Month</option>
        </select>
      </div>
      <div class="section-card-body">
        <div class="chart-container"><canvas id="revenueCompChart"></canvas></div>
      </div>
    </div>
  </div>
  <!-- Monthly Revenue (This Year) -->
  <div class="col-md-6">
    <div class="section-card">
      <div class="section-card-header">
        <span class="section-card-title"><i class="fas fa-chart-line me-2" style="color:var(--success)"></i>Monthly Revenue (<?= date('Y') ?>)</span>
      </div>
      <div class="section-card-body">
        <div class="chart-container"><canvas id="monthlyRevChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Daily Attendance This Week -->
<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="section-card">
      <div class="section-card-header">
        <span class="section-card-title"><i class="fas fa-calendar-week me-2" style="color:var(--warning)"></i>Daily Attendance (This Week)</span>
      </div>
      <div class="section-card-body">
        <div class="chart-container"><canvas id="weeklyAttendChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="section-card h-100">
      <div class="section-card-header">
        <span class="section-card-title"><i class="fas fa-circle-info me-2" style="color:var(--primary)"></i>Quick Stats</span>
      </div>
      <div class="section-card-body">
        <div class="d-flex flex-column gap-3">
          <div class="d-flex justify-content-between align-items-center p-3" style="background:#f8faff;border-radius:10px;">
            <span style="font-size:13px;font-weight:600;">Supplement Revenue Today</span>
            <span style="background:rgba(245,158,11,0.12);color:#d97706;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;">₱<?= number_format($suppRevToday, 2) ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-3" style="background:#f8faff;border-radius:10px;">
            <span style="font-size:13px;font-weight:600;">Today's Check-ins</span>
            <span class="badge-active"><?= $conn->query("SELECT COUNT(*) as c FROM attendance_logs WHERE DATE(time_in) = CURDATE()")->fetch_assoc()['c'] ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-3" style="background:#f8faff;border-radius:10px;">
            <span style="font-size:13px;font-weight:600;">Frozen Members</span>
            <span class="badge-frozen"><?= $conn->query("SELECT COUNT(*) as c FROM members WHERE status = 'frozen'")->fetch_assoc()['c'] ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-3" style="background:#f8faff;border-radius:10px;">
            <span style="font-size:13px;font-weight:600;">Walk-in Members</span>
            <span class="badge-walkin"><?= $conn->query("SELECT COUNT(*) as c FROM members WHERE member_type = 'walkin' AND DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-3" style="background:#f8faff;border-radius:10px;">
            <span style="font-size:13px;font-weight:600;">Total Subscribers</span>
            <span class="badge-subscription"><?= $conn->query("SELECT COUNT(*) as c FROM members WHERE member_type = 'subscription'")->fetch_assoc()['c'] ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Attendance -->
<div class="section-card">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-clock me-2" style="color:var(--text-muted)"></i>Recent Attendance</span>
    <a href="attendance_logs.php" class="btn-outline-custom" style="padding:6px 14px;font-size:12px;">View All</a>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>User ID</th>
            <th>Name</th>
            <th>Type</th>
            <th>Time In</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recentAttend->num_rows > 0): ?>
          <?php while($row = $recentAttend->fetch_assoc()): ?>
          <tr>
            <td><code><?= htmlspecialchars($row['member_id']) ?></code></td>
            <td><strong><?= htmlspecialchars($row['member_name']) ?></strong></td>
            <td><span class="badge-<?= $row['member_type'] === 'walkin' ? 'walkin' : 'subscription' ?>"><?= $row['member_type'] === 'walkin' ? 'Walk-in' : 'Subscriber' ?></span></td>
            <td><?= date('M d, Y h:i A', strtotime($row['time_in'])) ?></td>
            <td><span class="badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
          </tr>
          <?php endwhile; ?>
          <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No attendance records yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScripts =
'<script src="' . (file_exists(__DIR__.'/assets/js/chart.umd.min.js') ? 'assets/js/chart.umd.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js') . '"></script>
<script>
Chart.defaults.font.family = "Barlow, sans-serif";
Chart.defaults.color = "#6b7a99";

// Revenue Comparison Bar Chart
const revAllData = {
  labels: ["Yesterday","Today","Last Week","Last Month"],
  values: [' . (float)$yesterdayRev . ',' . (float)$todayRev . ',' . (float)$lastWeekRev . ',' . (float)$lastMonthRev . '],
  colors: ["rgba(245,158,11,0.7)","rgba(30,120,255,0.7)","rgba(139,92,246,0.7)","rgba(16,185,129,0.7)"],
  borders: ["#f59e0b","#1e78ff","#8b5cf6","#10b981"]
};
const filterMap = { all: [0,1,2,3], yesterday: [0], today: [1], lastweek: [2], lastmonth: [3] };

const revCompCtx = document.getElementById("revenueCompChart").getContext("2d");
const revCompChart = new Chart(revCompCtx, {
  type: "bar",
  data: {
    labels: revAllData.labels,
    datasets: [{
      label: "Revenue (\u20b1)",
      data: revAllData.values,
      backgroundColor: revAllData.colors,
      borderColor: revAllData.borders,
      borderWidth: 2,
      borderRadius: 8,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { callback: v => "\u20b1" + v.toLocaleString() } },
      x: { grid: { display: false } }
    }
  }
});

document.getElementById("revCompFilter").addEventListener("change", function() {
  const indices = filterMap[this.value] || [0,1,2,3];
  revCompChart.data.labels = indices.map(i => revAllData.labels[i]);
  revCompChart.data.datasets[0].data = indices.map(i => revAllData.values[i]);
  revCompChart.data.datasets[0].backgroundColor = indices.map(i => revAllData.colors[i]);
  revCompChart.data.datasets[0].borderColor = indices.map(i => revAllData.borders[i]);
  revCompChart.update();
});

// Monthly Revenue Line Chart
const monthlyRevCtx = document.getElementById("monthlyRevChart").getContext("2d");
new Chart(monthlyRevCtx, {
  type: "line",
  data: {
    labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
    datasets: [{
      label: "Monthly Revenue (\u20b1)",
      data: ' . json_encode($monthlyRevData) . ',
      borderColor: "#10b981",
      backgroundColor: "rgba(16,185,129,0.08)",
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#10b981",
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { callback: v => "\u20b1" + v.toLocaleString() } },
      x: { grid: { display: false } }
    }
  }
});

// Weekly Attendance Bar Chart
const weeklyCtx = document.getElementById("weeklyAttendChart").getContext("2d");
new Chart(weeklyCtx, {
  type: "bar",
  data: {
    labels: ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"],
    datasets: [{
      label: "Visitors",
      data: ' . json_encode($weeklyAttend) . ',
      backgroundColor: "rgba(30,120,255,0.7)",
      borderColor: "#1e78ff",
      borderWidth: 2,
      borderRadius: 8,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { stepSize: 1 } },
      x: { grid: { display: false } }
    }
  }
});
</script>';
include 'footer.php';
?>
