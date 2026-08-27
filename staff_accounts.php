<?php
require_once 'auth.php';
$pageTitle = 'Staff Accounts';

// Superadmin and manager (admin) can access
if (!in_array($_SESSION['admin_role'], ['superadmin', 'admin'])) {
    header('Location: walkin_members.php');
    exit;
}

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_staff') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = 'staff'; // staff only — manager creation not allowed
        
        if (!$username || !$fullname || !$password) {
            $errorMsg = 'All fields are required.';
        } elseif (strlen($password) < 6) {
            $errorMsg = 'Password must be at least 6 characters.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed, $fullname, $role);
            if ($stmt->execute()) {
                $successMsg = "Staff account <strong>$username</strong> created successfully.";
            } else {
                $errorMsg = 'Username already exists.';
            }
            $stmt->close();
        }
    }
    
    if ($action === 'delete_staff') {
        $id = intval($_POST['staff_id'] ?? 0);
        $conn->query("DELETE FROM admins WHERE id = $id AND username != 'diozabeth'");
        $successMsg = "Staff account deleted.";
    }
    
    if ($action === 'update_staff') {
        $id = intval($_POST['staff_id'] ?? 0);
        $fullname = trim($_POST['full_name'] ?? '');
        $role = 'staff'; // staff only — manager role not allowed
        $password = $_POST['password'] ?? '';
        if ($fullname) {
            $safeFullname = $conn->real_escape_string($fullname);
            if ($password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $safeHash = $conn->real_escape_string($hashed);
                $conn->query("UPDATE admins SET full_name = '$safeFullname', role = '$role', password = '$safeHash' WHERE id = $id");
            } else {
                $conn->query("UPDATE admins SET full_name = '$safeFullname', role = '$role' WHERE id = $id");
            }
            $successMsg = "Staff account updated.";
        }
    }
}

$staffList = $conn->query("SELECT * FROM admins ORDER BY role DESC, full_name ASC");
include 'header.php';
?>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Staff Account Management</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Create and manage staff access accounts</p>
  </div>
  <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#createStaffModal">
    <i class="fas fa-user-plus"></i> Create Staff Account
  </button>
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

<div class="section-card">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-users-cog me-2" style="color:var(--text-muted)"></i>All Accounts</span>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">     <table class="table mb-0" id="staffTable">
        <thead>
          <tr><th>Full Name</th><th>Username</th><th>Role</th><th>Created</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php while($s = $staffList->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;background:linear-gradient(135deg,#1e78ff,#0d5ad4);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0;">
                  <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                </div>
                <strong><?= htmlspecialchars($s['full_name']) ?></strong>
              </div>
            </td>
            <td><code style="background:#f0f4fb;padding:3px 10px;border-radius:6px;font-size:12px;"><?= htmlspecialchars($s['username']) ?></code></td>
            <td>
              <?php if ($s['role'] === 'superadmin'): ?>
              <span style="background:rgba(239,68,68,0.12);color:#ef4444;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">Owner</span>
              <?php elseif ($s['role'] === 'admin'): ?>
              <span style="background:rgba(30,120,255,0.12);color:#1e78ff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">Manager</span>
              <?php else: ?>
              <span style="background:rgba(16,185,129,0.12);color:#10b981;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">Staff</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-muted);"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
            <td>
              <?php 
              $isDefaultAdmin = ($s['username'] === 'diozabeth');
              if ($isDefaultAdmin): ?>
              <span style="font-size:12px;color:var(--text-muted);"><i class="fas fa-shield-halved me-1"></i>Protected</span>
              <?php else: ?>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:11px;" onclick="openEditModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                  <i class="fas fa-edit me-1"></i>Edit
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this account?')">
                  <input type="hidden" name="action" value="delete_staff">
                  <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;">
                    <i class="fas fa-trash me-1"></i>Delete
                  </button>
                </form>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Staff Modal -->
<div class="modal fade" id="createStaffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i>Create Staff Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="action" value="create_staff">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
            </div>
            <div class="col-12">
              <label class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            <div class="col-12">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-primary-custom"><i class="fas fa-plus me-1"></i>Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit Staff Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="action" value="update_staff">
        <input type="hidden" name="staff_id" id="editStaffId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" id="editFullName" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">New Password <span style="font-size:11px;color:var(--text-muted);">(leave blank to keep current)</span></label>
              <input type="password" name="password" class="form-control" placeholder="New password (optional)">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-primary-custom"><i class="fas fa-save me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScripts = '
<script>
$(document).ready(function() {
  $("#staffTable").DataTable({ responsive: true, pageLength: -1, lengthMenu: [[10,25,50,100,-1],["10","25","50","100","All"]] });
});
function openEditModal(data) {
  document.getElementById("editStaffId").value = data.id;
  document.getElementById("editFullName").value = data.full_name;
  new bootstrap.Modal(document.getElementById("editStaffModal")).show();
}
</script>';
include 'footer.php';
?>
