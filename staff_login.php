<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once 'database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && $row['role'] === 'staff' && password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_username'] = $row['username'];
            $_SESSION['admin_name'] = $row['full_name'];
            $_SESSION['admin_role'] = $row['role'];
            header('Location: walkin_members.php');
            exit;
        } else {
            $error = 'Invalid credentials or not a staff account.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login – Diozabeth Fitness</title>
<link href="<?= file_exists(__DIR__.'/assets/css/bootstrap.min.css') ? 'assets/css/bootstrap.min.css' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/fontawesome/all.min.css') ? 'assets/css/fontawesome/all.min.css' : 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/barlow.css') ? 'assets/css/barlow.css' : 'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap' ?>" rel="stylesheet">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Barlow',sans-serif; min-height:100vh; background:linear-gradient(135deg,#0a2818 0%,#0d3520 30%,#1a5e3a 60%,#0f3d28 100%); display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; padding:20px; }
body::before { content:''; position:absolute; width:600px; height:600px; background:radial-gradient(circle,rgba(16,185,129,0.15) 0%,transparent 70%); top:-100px; right:-100px; border-radius:50%; }
body::after { content:''; position:absolute; width:400px; height:400px; background:radial-gradient(circle,rgba(0,180,100,0.08) 0%,transparent 70%); bottom:-50px; left:-50px; border-radius:50%; }
.login-card { background:rgba(255,255,255,0.97); border-radius:24px; padding:44px 40px; width:100%; max-width:420px; box-shadow:0 32px 80px rgba(0,0,0,0.4); position:relative; z-index:10; animation:slideUp 0.5s ease; }
@keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
.logo-wrap { width:64px; height:64px; background:linear-gradient(135deg,#10b981,#059669); border-radius:18px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; box-shadow:0 8px 24px rgba(16,185,129,0.4); }
.logo-wrap i { font-size:28px; color:#fff; }
.login-type-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(16,185,129,0.1); color:#059669; font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:1px; }
.gym-title { font-family:'Barlow Condensed',sans-serif; font-size:24px; font-weight:800; color:#0a1628; text-align:center; line-height:1.2; }
.gym-subtitle { color:#6b7a99; font-size:12px; text-align:center; margin-top:3px; text-transform:uppercase; letter-spacing:1.5px; }
.form-label { font-size:13px; font-weight:600; color:#344361; margin-bottom:6px; display:block; }
.form-control { border:2px solid #e8ecf4; border-radius:12px; padding:12px 16px; font-size:14px; color:#0a1628; transition:all 0.2s; background:#f8faff; width:100%; }
.form-control:focus { border-color:#10b981; box-shadow:0 0 0 4px rgba(16,185,129,0.12); background:#fff; outline:none; }
.input-group-text { background:#f8faff; border:2px solid #e8ecf4; border-right:none; border-radius:12px 0 0 12px; color:#6b7a99; padding:0 14px; }
.input-group .form-control { border-left:none; border-radius:0 12px 12px 0; }
.btn-login { background:linear-gradient(135deg,#10b981,#059669); border:none; border-radius:12px; padding:14px; font-size:15px; font-weight:700; color:#fff; width:100%; cursor:pointer; transition:all 0.2s; box-shadow:0 4px 16px rgba(16,185,129,0.4); }
.btn-login:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(16,185,129,0.5); }
.back-link { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:18px; color:#6b7a99; font-size:13px; text-decoration:none; transition:color 0.2s; }
.back-link:hover { color:#10b981; }
.alert-danger { border-radius:12px; border:none; background:#fff0f0; color:#c0392b; font-size:13px; padding:12px 16px; border-left:4px solid #e74c3c; margin-bottom:16px; }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo-wrap"><i class="fa-solid fa-user-tie"></i></div>
  <div style="text-align:center; margin-bottom:4px;">
    <span class="login-type-badge"><i class="fas fa-id-badge"></i> Staff Access</span>
  </div>
  <div class="gym-title">Staff Login</div>
  <div class="gym-subtitle">Diozabeth Fitness</div>
  <hr style="border-color:#e8ecf4; margin:22px 0;">
  <?php if ($error): ?>
  <div class="alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" action="">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-user"></i></span>
        <input type="text" name="username" class="form-control" placeholder="Staff username" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" name="password" class="form-control" placeholder="Staff password" required autocomplete="current-password">
      </div>
    </div>
    <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt me-2"></i>Login as Staff</button>
  </form>
  <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login Selection</a>
</div>
<script src="<?= file_exists(__DIR__.'/assets/js/bootstrap.bundle.min.js') ? 'assets/js/bootstrap.bundle.min.js' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js' ?>"></script>
</body>
</html>
