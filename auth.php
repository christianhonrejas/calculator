<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// ── Role-Based Page Access Control ──────────────────────────────────
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['admin_role'] ?? 'staff';

// Pages staff (role=staff) are allowed to visit
$staffAllowed = [
    'walkin_members.php',
    'subscription_members.php',
    'attendance_monitor.php',
    'supplement_sales.php',
    'logout.php',
];

// Pages manager (role=admin) are allowed to visit
$managerAllowed = [
    'dashboard.php',
    'walkin_members.php',
    'subscription_members.php',
    'attendance_monitor.php',
    'attendance_logs.php',
    'supplement_sales.php',
    'reports_dashboard.php',
    'staff_accounts.php',
    'staff_salary.php',
    'backup.php',
    'backup_ajax.php',
    'backup_oauth.php',
    'backup_download.php',
    'logout.php',
];

if ($role === 'staff' && !in_array($currentPage, $staffAllowed)) {
    header('Location: walkin_members.php');
    exit;
}

if ($role === 'admin' && !in_array($currentPage, $managerAllowed)) {
    header('Location: dashboard.php');
    exit;
}
// superadmin can access everything — no restriction needed
?>
