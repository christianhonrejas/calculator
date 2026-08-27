<?php
date_default_timezone_set('Asia/Manila');
session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diozabeth Fitness Gym Management System</title>
<link href="<?= file_exists(__DIR__.'/assets/css/bootstrap.min.css') ? 'assets/css/bootstrap.min.css' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/fontawesome/all.min.css') ? 'assets/css/fontawesome/all.min.css' : 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/barlow.css') ? 'assets/css/barlow.css' : 'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap' ?>" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Barlow', sans-serif; min-height: 100vh;
  background: linear-gradient(135deg, #0a1628 0%, #0d2040 30%, #1a3a6b 60%, #0f2d55 100%);
  display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden; padding: 20px;
}
body::before { content:''; position:absolute; width:600px; height:600px; background:radial-gradient(circle,rgba(30,120,255,0.15) 0%,transparent 70%); top:-100px; right:-100px; border-radius:50%; }
body::after { content:''; position:absolute; width:400px; height:400px; background:radial-gradient(circle,rgba(0,200,150,0.08) 0%,transparent 70%); bottom:-50px; left:-50px; border-radius:50%; }
.landing-card { background:rgba(255,255,255,0.97); border-radius:24px; padding:48px 40px; width:100%; max-width:480px; box-shadow:0 32px 80px rgba(0,0,0,0.4); position:relative; z-index:10; animation:slideUp 0.5s ease; }
@keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
.logo-wrap { width:72px; height:72px; background:linear-gradient(135deg,#1e78ff,#0d5ad4); border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; box-shadow:0 8px 24px rgba(30,120,255,0.4); }
.logo-wrap i { font-size:32px; color:#fff; }
.gym-title { font-family:'Barlow Condensed',sans-serif; font-size:26px; font-weight:800; color:#0a1628; text-align:center; line-height:1.2; letter-spacing:-0.5px; }
.gym-subtitle { color:#6b7a99; font-size:13px; text-align:center; margin-top:4px; font-weight:500; text-transform:uppercase; letter-spacing:1.5px; }
.choose-label { text-align:center; font-size:12px; font-weight:700; color:#6b7a99; margin-bottom:16px; text-transform:uppercase; letter-spacing:1.2px; }
.login-btn { display:flex; align-items:center; gap:16px; width:100%; padding:18px 22px; border-radius:14px; border:2px solid #e8ecf4; background:#f8faff; cursor:pointer; text-decoration:none; color:#0a1628; transition:all 0.2s; margin-bottom:12px; }
.login-btn:hover { border-color:#1e78ff; background:#fff; transform:translateY(-2px); box-shadow:0 8px 24px rgba(30,120,255,0.15); color:#0a1628; }
.login-btn:last-child { margin-bottom:0; }
.btn-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.admin-icon { background:linear-gradient(135deg,#1e78ff,#0d5ad4); color:#fff; }
.staff-icon { background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
.btn-text { text-align:left; }
.btn-title { font-size:15px; font-weight:700; }
.btn-desc { font-size:12px; color:#6b7a99; margin-top:2px; }
.btn-arrow { margin-left:auto; color:#c0c9d9; font-size:16px; transition:color 0.2s; }
.login-btn:hover .btn-arrow { color:#1e78ff; }
hr { border-color:#e8ecf4; margin:24px 0; }
</style>
</head>
<body>
<div class="landing-card">
  <div class="logo-wrap"><i class="fa-solid fa-dumbbell"></i></div>
  <div class="gym-title">Diozabeth Fitness Gym<br>Management System</div>
  <div class="gym-subtitle">Admin Web Portal</div>
  <hr>
  <div class="choose-label">Select Login Type</div>
  <a href="admin_login.php" class="login-btn">
    <div class="btn-icon admin-icon"><i class="fas fa-user-shield"></i></div>
    <div class="btn-text">
      <div class="btn-title">Admin Login</div>
      <div class="btn-desc">For owner / admin and managers</div>
    </div>
    <i class="fas fa-chevron-right btn-arrow"></i>
  </a>
  <a href="staff_login.php" class="login-btn">
    <div class="btn-icon staff-icon"><i class="fas fa-user-tie"></i></div>
    <div class="btn-text">
      <div class="btn-title">Staff Login</div>
      <div class="btn-desc">For gym staff members</div>
    </div>
    <i class="fas fa-chevron-right btn-arrow"></i>
  </a>
</div>
<script src="<?= file_exists(__DIR__.'/assets/js/bootstrap.bundle.min.js') ? 'assets/js/bootstrap.bundle.min.js' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js' ?>"></script>
</body>
</html>
