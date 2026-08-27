<?php
require_once 'auth.php';
$pageTitle = 'Attendance Logs';

$fromDate = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-d');
$toDate   = (isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   ? $_GET['to']   : date('Y-m-d');
$memberFilter = trim($_GET['member'] ?? '');

// ── Secure parameterised query (no SQL injection) ─────────────────────
if ($memberFilter && $memberFilter !== 'all') {
    $stmt = $conn->prepare("SELECT a.* FROM attendance_logs a WHERE DATE(a.time_in) BETWEEN ? AND ? AND a.member_id = ? ORDER BY a.time_in DESC");
    $stmt->bind_param("sss", $fromDate, $toDate, $memberFilter);
} else {
    $stmt = $conn->prepare("SELECT a.* FROM attendance_logs a WHERE DATE(a.time_in) BETWEEN ? AND ? ORDER BY a.time_in DESC");
    $stmt->bind_param("ss", $fromDate, $toDate);
}
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();
$allMembers = $conn->query("SELECT DISTINCT member_id, member_name FROM attendance_logs ORDER BY member_name ASC");

include 'header.php';
?>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Attendance Logs</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Filter logs and export/print reports</p>
  </div>
</div>

<!-- Filters -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-filter me-2" style="color:var(--primary)"></i>Filters</span>
  </div>
  <div class="section-card-body">
    <form method="GET" action="">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Member</label>
          <select name="member" class="form-select">
            <option value="all">All Members</option>
            <?php while($m = $allMembers->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($m['member_id']) ?>" <?= $memberFilter === $m['member_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($m['member_id'] . ' – ' . $m['member_name']) ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn-primary-custom w-100" style="justify-content:center;">
            <i class="fas fa-filter"></i> Apply
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Logs Table -->
<div class="section-card">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-clipboard-list me-2" style="color:var(--text-muted)"></i>Attendance Logs</span>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn-outline-custom" onclick="window.print()" style="padding:7px 14px;font-size:12px;">
        <i class="fas fa-print me-1"></i>Print
      </button>
      <button class="btn-outline-custom" onclick="exportPDF()" style="padding:7px 14px;font-size:12px;">
        <i class="fas fa-file-pdf me-1"></i>Export PDF
      </button>
      <button class="btn-outline-custom" onclick="exportCSV()" style="padding:7px 14px;font-size:12px;">
        <i class="fas fa-file-csv me-1"></i>CSV
      </button>
    </div>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">     <table class="table mb-0" id="logsTable">
        <thead>
          <tr>
            <th>Member Name</th>
            <th>UID</th>
            <th>Type</th>
            <th>Date</th>
            <th>Time-In</th>
            <th>Access Result</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $logs->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['member_name']) ?></strong></td>
            <td><code style="background:#f0f4fb;padding:3px 8px;border-radius:6px;font-size:11px;"><?= htmlspecialchars($row['member_id']) ?></code></td>
            <td><span class="badge-<?= $row['member_type'] === 'walkin' ? 'walkin' : 'subscription' ?>"><?= $row['member_type'] === 'walkin' ? 'Walk-in' : 'Subscriber' ?></span></td>
            <td style="font-size:13px;"><?= date('M d, Y', strtotime($row['time_in'])) ?></td>
            <td style="font-size:13px;"><?= date('h:i A', strtotime($row['time_in'])) ?></td>
            <td>
              <?php if ($row['access_result'] === 'granted'): ?>
              <span style="color:var(--success);font-weight:600;font-size:12px;"><i class="fas fa-check-circle me-1"></i>Granted</span>
              <?php else: ?>
              <span style="color:var(--danger);font-weight:600;font-size:12px;"><i class="fas fa-times-circle me-1"></i>Denied</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<style>
@media print {
  .sidebar, .top-navbar, .page-footer, .btn-outline-custom, .section-card-header .d-flex, form { display: none !important; }
  .main-wrapper { margin-left: 0 !important; }
  body { background: white; }
  .section-card { box-shadow: none; border: 1px solid #ddd; }
  h4 { font-size: 20px; }
  .table { font-size: 11px; }
  .page-content { padding: 0; }
  #printHeader { display: block !important; }
}
#printHeader { display: none; margin-bottom: 20px; }
</style>

<div id="printHeader">
  <h3 style="font-family:'Barlow Condensed',sans-serif;font-weight:800;">Diozabeth Fitness Gym Management System</h3>
  <p>Attendance Logs Report | Period: <?= date('M d, Y', strtotime($fromDate)) ?> – <?= date('M d, Y', strtotime($toDate)) ?></p>
  <p>Generated: <?= date('M d, Y h:i A') ?></p>
  <hr>
</div>

<?php
$extraScripts = '
' . '<script src="' . (file_exists(__DIR__.'/assets/js/jspdf.umd.min.js') ? 'assets/js/jspdf.umd.min.js' : 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js') . '"></script>' . '
' . '<script src="' . (file_exists(__DIR__.'/assets/js/jspdf.plugin.autotable.min.js') ? 'assets/js/jspdf.plugin.autotable.min.js' : 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.6.0/jspdf.plugin.autotable.min.js') . '"></script>' . '
<script>
$(document).ready(function() {
  $("#logsTable").DataTable({
    responsive: true,
    order: [[3,"desc"],[4,"desc"]],
    pageLength: -1, lengthMenu: [[10,25,50,100,-1],["10","25","50","100","All"]],
    language: {
      emptyTable: "No attendance records found for the selected filter",
      zeroRecords: "No matching records found"
    }
  });
});

function exportPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  doc.setFontSize(14);
  doc.setFont("helvetica","bold");
  doc.text("Diozabeth Fitness Gym - Attendance Logs", 14, 18);
  doc.setFontSize(10);
  doc.setFont("helvetica","normal");
  doc.text("Generated: " + new Date().toLocaleString("en-PH"), 14, 26);
  
  const rows = [];
  document.querySelectorAll("#logsTable tbody tr").forEach(tr => {
    const cells = tr.querySelectorAll("td");
    if (cells.length >= 6) {
      rows.push([
        cells[0].innerText.trim(),
        cells[1].innerText.trim(),
        cells[2].innerText.trim(),
        cells[3].innerText.trim(),
        cells[4].innerText.trim(),
        cells[5].innerText.trim()
      ]);
    }
  });
  
  doc.autoTable({
    head: [["Member Name","UID","Type","Date","Time-In","Access Result"]],
    body: rows,
    startY: 32,
    styles: { fontSize: 9, cellPadding: 3 },
    headStyles: { fillColor: [30,120,255], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [248,250,255] }
  });
  
  doc.save("attendance_logs_" + new Date().toISOString().split("T")[0] + ".pdf");
}

function exportCSV() {
  const rows = [["Member Name","UID","Type","Date","Time-In","Access Result"]];
  document.querySelectorAll("#logsTable tbody tr").forEach(tr => {
    const cells = tr.querySelectorAll("td");
    if (cells.length >= 6) {
      rows.push([
        cells[0].innerText.trim(),
        cells[1].innerText.trim(),
        cells[2].innerText.trim(),
        cells[3].innerText.trim(),
        cells[4].innerText.trim(),
        cells[5].innerText.trim()
      ]);
    }
  });
  const csv = rows.map(r => r.map(c => `"${c}"`).join(",")).join("\n");
  const blob = new Blob([csv], { type: "text/csv" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "attendance_logs.csv";
  a.click();
}
</script>';
include 'footer.php';
?>
