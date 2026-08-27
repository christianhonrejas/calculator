<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= $pageTitle ?? 'Dashboard' ?> – Diozabeth Fitness</title>
<link href="<?= file_exists(__DIR__.'/assets/css/bootstrap.min.css') ? 'assets/css/bootstrap.min.css' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/fontawesome/all.min.css') ? 'assets/css/fontawesome/all.min.css' : 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/dataTables.bootstrap5.min.css') ? 'assets/css/dataTables.bootstrap5.min.css' : 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css' ?>" rel="stylesheet">
<link href="<?= file_exists(__DIR__.'/assets/css/barlow.css') ? 'assets/css/barlow.css' : 'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap' ?>" rel="stylesheet">
<style>
/* ===================== CSS VARIABLES ===================== */
:root {
  --primary: #1e78ff;
  --primary-dark: #0d5ad4;
  --sidebar-bg: #0d1b35;
  --sidebar-hover: #1a2f55;
  --sidebar-active: #1e78ff;
  --sidebar-w: 240px;
  --sidebar-w-icon: 68px;
  --navbar-h: 60px;

  /* Theme color maps */
  --theme-blue-p:    #1e78ff; --theme-blue-d:    #0d5ad4; --theme-blue-sb:   #0d1b35; --theme-blue-sh:   #1a2f55;
  --theme-green-p:   #10b981; --theme-green-d:   #059669; --theme-green-sb:  #0a2018; --theme-green-sh:  #14302a;
  --theme-purple-p:  #8b5cf6; --theme-purple-d:  #7c3aed; --theme-purple-sb: #16103a; --theme-purple-sh: #251852;
  --theme-red-p:     #ef4444; --theme-red-d:     #dc2626; --theme-red-sb:    #2a0a0a; --theme-red-sh:    #3d1010;
  --theme-orange-p:  #f97316; --theme-orange-d:  #ea6c0b; --theme-orange-sb: #281508; --theme-orange-sh: #3d2010;
  --theme-pink-p:    #ec4899; --theme-pink-d:    #db2777; --theme-pink-sb:   #2a0a1a; --theme-pink-sh:   #3d1030;
  --theme-teal-p:    #14b8a6; --theme-teal-d:    #0d9488; --theme-teal-sb:   #081e1e; --theme-teal-sh:   #0d2e2e;
  --theme-gold-p:    #f59e0b; --theme-gold-d:    #d97706; --theme-gold-sb:   #1e1608; --theme-gold-sh:   #2d2010;

  --body-bg: #f0f4fb;
  --card-bg: #ffffff;
  --text-main: #0a1628;
  --text-muted: #6b7a99;
  --border: #e4e9f2;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
}

/* ===================== BASE ===================== */
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Barlow', sans-serif;
  background: var(--body-bg);
  color: var(--text-main);
  margin: 0; padding: 0;
  overflow-x: hidden;
}

/* ===================== SIDEBAR ===================== */
.sidebar {
  position: fixed;
  left: 0; top: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  z-index: 1050;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: width 0.3s ease, transform 0.3s ease;
  will-change: transform, width;
}

/* Logo area */
.sidebar-logo {
  padding: 16px 16px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  display: flex;
  align-items: center;
  gap: 10px;
  overflow: hidden;
  min-height: 64px;
  flex-shrink: 0;
}
.logo-icon {
  width: 40px; height: 40px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(30,120,255,0.4);
}
.logo-icon i { color: #fff; font-size: 17px; }
.logo-text { overflow: hidden; white-space: nowrap; transition: opacity 0.2s; }
.logo-text .gym-name {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 14px; font-weight: 800;
  line-height: 1.1; color: #fff;
  letter-spacing: -0.3px;
}
.logo-text .gym-sub {
  font-size: 10px;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* Nav */
.sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 0; }
.sidebar-nav::-webkit-scrollbar { width: 3px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

.nav-section {
  padding: 14px 18px 5px;
  font-size: 10px;
  font-weight: 700;
  color: rgba(255,255,255,0.3);
  text-transform: uppercase;
  letter-spacing: 1.5px;
  white-space: nowrap;
  overflow: hidden;
  transition: opacity 0.2s;
}
.nav-item a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 18px;
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
  border-left: 3px solid transparent;
  transition: all 0.2s;
  white-space: nowrap;
  overflow: hidden;
}
.nav-item a:hover { background: var(--sidebar-hover); color: #fff; border-left-color: rgba(30,120,255,0.5); }
.nav-item a.active { background: rgba(30,120,255,0.15); color: #fff; border-left-color: var(--primary); }
.nav-item a i { width: 20px; text-align: center; font-size: 15px; flex-shrink: 0; }
.nav-label { overflow: hidden; white-space: nowrap; transition: opacity 0.2s; }

/* Sidebar footer */
.sidebar-footer {
  padding: 10px 18px;
  border-top: 1px solid rgba(255,255,255,0.08);
  flex-shrink: 0;
}
.sidebar-footer a {
  display: flex; align-items: center; gap: 12px;
  color: rgba(255,255,255,0.5);
  text-decoration: none;
  font-size: 13.5px; font-weight: 500;
  padding: 8px 0;
  white-space: nowrap;
  overflow: hidden;
  transition: color 0.2s;
}
.sidebar-footer a:hover { color: #ff6b6b; }
.sidebar-footer a i { width: 20px; text-align: center; flex-shrink: 0; }

/* Sidebar tooltip for icon-only mode */
.nav-item { position: relative; }

/* ===================== MAIN WRAPPER ===================== */
.main-wrapper {
  margin-left: var(--sidebar-w);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: margin-left 0.3s ease;
}

/* ===================== TOP NAVBAR ===================== */
.top-navbar {
  position: sticky; top: 0; z-index: 100;
  height: var(--navbar-h);
  background: var(--card-bg);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  gap: 12px;
}
.navbar-left { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.navbar-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

.page-title-nav {
  font-size: 16px; font-weight: 700; color: var(--text-main);
  white-space: nowrap;
}
.datetime-badge {
  font-size: 11.5px; color: var(--text-muted); font-weight: 500;
  background: var(--body-bg);
  padding: 5px 12px; border-radius: 20px;
  border: 1px solid var(--border);
  white-space: nowrap;
}
.user-dropdown .dropdown-toggle {
  display: flex; align-items: center; gap: 8px;
  background: var(--body-bg); border: 1px solid var(--border);
  border-radius: 20px; padding: 5px 12px 5px 6px;
  cursor: pointer; text-decoration: none; color: var(--text-main);
  font-size: 13px; font-weight: 600;
  transition: all 0.2s;
  white-space: nowrap;
}
.user-dropdown .dropdown-toggle:hover { border-color: var(--primary); }
.user-avatar {
  width: 28px; height: 28px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 12px; font-weight: 700;
  flex-shrink: 0;
}
.dropdown-menu {
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  padding: 8px;
  min-width: 230px;
}
.dropdown-item { border-radius: 8px; font-size: 13px; padding: 8px 12px; }
.dropdown-item:hover { background: var(--body-bg); }
.dropdown-divider { margin: 6px 0; }

/* Hamburger */
.btn-hamburger {
  background: none; border: none;
  font-size: 19px; color: var(--text-main);
  cursor: pointer; padding: 6px 8px;
  border-radius: 8px;
  transition: background 0.2s;
  display: none;
  flex-shrink: 0;
  line-height: 1;
}
.btn-hamburger:hover { background: var(--body-bg); }

/* ===================== PAGE CONTENT ===================== */
.page-content { padding: 24px; flex: 1; }

/* ===================== STAT CARDS ===================== */
.stat-card {
  background: var(--card-bg); border-radius: 14px;
  padding: 18px 20px;
  border: 1px solid var(--border);
  transition: all 0.25s;
  height: 100%;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,0.09); }
.stat-card .card-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; }
.stat-card .card-value { font-size: 26px; font-weight: 800; color: var(--text-main); margin: 4px 0; line-height: 1; }
.stat-card .card-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
.stat-card .card-footer-text { font-size: 11px; color: var(--text-muted); margin-top: 8px; }
.icon-blue { background: rgba(30,120,255,0.12); color: var(--primary); }
.icon-green { background: rgba(16,185,129,0.12); color: var(--success); }
.icon-orange { background: rgba(245,158,11,0.12); color: var(--warning); }
.icon-red { background: rgba(239,68,68,0.12); color: var(--danger); }
.icon-purple { background: rgba(139,92,246,0.12); color: #8b5cf6; }
.icon-teal { background: rgba(20,184,166,0.12); color: #14b8a6; }

/* ===================== SECTION CARDS ===================== */
.section-card { background: var(--card-bg); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; }
.section-card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.section-card-title { font-size: 14px; font-weight: 700; color: var(--text-main); }
.section-card-body { padding: 18px 20px; }

/* ===================== BADGES ===================== */
.badge-active { background: rgba(16,185,129,0.12); color: var(--success); font-weight: 600; font-size: 11px; padding: 3px 9px; border-radius: 20px; }
.badge-inactive { background: rgba(239,68,68,0.12); color: var(--danger); font-weight: 600; font-size: 11px; padding: 3px 9px; border-radius: 20px; }
.badge-frozen { background: rgba(245,158,11,0.12); color: var(--warning); font-weight: 600; font-size: 11px; padding: 3px 9px; border-radius: 20px; }
.badge-expired { background: rgba(107,122,153,0.12); color: var(--text-muted); font-weight: 600; font-size: 11px; padding: 3px 9px; border-radius: 20px; }
.badge-walkin { background: rgba(30,120,255,0.12); color: var(--primary); font-weight: 600; font-size: 11px; padding: 3px 9px; border-radius: 20px; }
.badge-subscription { background: rgba(139,92,246,0.12); color: #8b5cf6; font-weight: 600; font-size: 11px; padding: 3px 9px; border-radius: 20px; }

/* ===================== BUTTONS ===================== */
.btn-primary-custom {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  border: none; color: #fff; font-weight: 600; font-size: 13px;
  padding: 9px 18px; border-radius: 10px; cursor: pointer;
  transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px;
  box-shadow: 0 2px 8px rgba(30,120,255,0.3);
  text-decoration: none; white-space: nowrap;
}
.btn-primary-custom:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(30,120,255,0.4); color: #fff; }
.btn-success-custom {
  background: linear-gradient(135deg, #10b981, #059669);
  border: none; color: #fff; font-weight: 600; font-size: 13px;
  padding: 9px 18px; border-radius: 10px; cursor: pointer;
  transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px;
  text-decoration: none; white-space: nowrap;
}
.btn-success-custom:hover { transform: translateY(-1px); color: #fff; }
.btn-outline-custom {
  background: transparent; border: 1.5px solid var(--border);
  color: var(--text-main); font-weight: 600; font-size: 13px;
  padding: 8px 16px; border-radius: 10px; cursor: pointer;
  transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px;
  text-decoration: none; white-space: nowrap;
}
.btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); }

/* ===================== FORMS ===================== */
.form-control, .form-select {
  border: 1.5px solid var(--border);
  border-radius: 10px; padding: 9px 13px;
  font-size: 14px; color: var(--text-main);
  background: #fff; transition: all 0.2s;
  width: 100%;
}
.form-control:focus, .form-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(30,120,255,0.12);
  outline: none;
}
.form-label { font-size: 13px; font-weight: 600; color: #344361; margin-bottom: 5px; display: block; }

/* ===================== TABLES ===================== */
.table-responsive { border-radius: 10px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table { font-size: 13px; margin-bottom: 0; }
.table th {
  font-weight: 700; font-size: 11.5px; text-transform: uppercase;
  letter-spacing: 0.5px; color: var(--text-muted);
  background: var(--body-bg); border-bottom: 1px solid var(--border);
  padding: 11px 14px; white-space: nowrap;
}
.table td { padding: 11px 14px; vertical-align: middle; border-bottom: 1px solid #f0f4fb; }
.table tbody tr:hover td { background: #f8faff; }
.table tbody tr:last-child td { border-bottom: none; }

/* DataTables override */
.dataTables_wrapper .dataTables_filter input { border: 1.5px solid var(--border); border-radius: 8px; padding: 6px 11px; font-size: 13px; }
.dataTables_wrapper .dataTables_length select { border: 1.5px solid var(--border); border-radius: 8px; padding: 4px 8px; font-size: 13px; }
.dataTables_wrapper .dataTables_info { font-size: 12px; color: var(--text-muted); }
.dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 8px !important; font-size: 12px !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; }
.dataTables_wrapper { overflow-x: auto; }

/* ===================== MODALS ===================== */
.modal-content { border: none; border-radius: 16px; box-shadow: 0 24px 64px rgba(0,0,0,0.2); }
.modal-header { border-bottom: 1px solid var(--border); padding: 18px 22px; }
.modal-title { font-weight: 700; font-size: 15px; }
.modal-body { padding: 22px; }
.modal-footer { border-top: 1px solid var(--border); padding: 14px 22px; }

/* ===================== PAGE FOOTER ===================== */
.page-footer {
  padding: 14px 24px;
  background: var(--card-bg);
  border-top: 1px solid var(--border);
  font-size: 12px; color: var(--text-muted);
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 6px;
}

/* ===================== SIDEBAR OVERLAY ===================== */
.sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1040;
  backdrop-filter: blur(2px);
}
.sidebar-overlay.active { display: block; }

/* ===================== CHART CONTAINERS ===================== */
.chart-container { position: relative; width: 100%; }
.chart-container canvas { max-width: 100%; }

/* ===================== PAGE HEADER ===================== */
.page-header {
  display: flex; align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
  margin-bottom: 20px;
}
.page-header-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

/* ===================== RESPONSIVE UTILITIES ===================== */
.text-responsive { word-break: break-word; }

/* =====================================================================
   TABLET (577px – 991px) — Icon-only sidebar
   ===================================================================== */
@media (min-width: 577px) and (max-width: 991px) {
  :root { --sidebar-w: var(--sidebar-w-icon); }

  .sidebar { width: var(--sidebar-w-icon); }
  .logo-text { opacity: 0; width: 0; pointer-events: none; }
  .nav-label { opacity: 0; width: 0; pointer-events: none; }
  .nav-section { opacity: 0; height: 10px; padding: 5px 0; overflow: hidden; }
  .sidebar-footer a span { opacity: 0; width: 0; overflow: hidden; }

  .nav-item a { padding: 12px 0; justify-content: center; gap: 0; border-left: none; border-bottom: 3px solid transparent; }
  .nav-item a.active { border-left: none; border-bottom-color: var(--primary); }
  .nav-item a:hover { border-left: none; border-bottom-color: rgba(30,120,255,0.5); }
  .nav-item a i { width: auto; font-size: 18px; }
  .sidebar-footer { padding: 10px 0; }
  .sidebar-footer a { justify-content: center; padding: 10px 0; }
  .sidebar-footer a i { width: auto; font-size: 18px; }

  /* Tooltip on hover */
  .nav-item { position: relative; }
  .nav-item a::after {
    content: attr(data-tooltip);
    position: absolute;
    left: calc(var(--sidebar-w-icon) + 8px);
    top: 50%; transform: translateY(-50%);
    background: #1a2f55;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 7px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 2000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  }
  .nav-item a:hover::after { opacity: 1; }

  .main-wrapper { margin-left: var(--sidebar-w-icon); }
  .datetime-badge { display: none; }
  .btn-hamburger { display: none; }
  .page-content { padding: 18px; }
  .stat-card .card-value { font-size: 22px; }
}

/* =====================================================================
   MOBILE (≤576px) — Slide-out sidebar
   ===================================================================== */
@media (max-width: 576px) {
  :root { --navbar-h: 56px; }

  .sidebar {
    width: var(--sidebar-w);
    transform: translateX(-100%);
  }
  .sidebar.open { transform: translateX(0); }

  .main-wrapper { margin-left: 0; }
  .btn-hamburger { display: flex; align-items: center; justify-content: center; }

  .top-navbar { padding: 0 14px; }
  .page-title-nav { font-size: 15px; }
  .datetime-badge { display: none; }
  .user-dropdown .dropdown-toggle { padding: 5px 10px 5px 5px; font-size: 12px; gap: 6px; }
  .admin-name-text { display: none; }

  .page-content { padding: 14px; }

  /* Cards: 2 col on small mobile */
  .stat-card { padding: 14px 16px; }
  .stat-card .card-value { font-size: 20px; }
  .stat-card .card-label { font-size: 10px; }
  .stat-card .card-icon { width: 36px; height: 36px; font-size: 15px; }

  /* Page header stacks */
  .page-header { flex-direction: column; align-items: flex-start; }
  .page-header-actions { width: 100%; }
  .page-header-actions .btn-primary-custom,
  .page-header-actions .btn-success-custom,
  .page-header-actions .btn-outline-custom { width: 100%; justify-content: center; }

  /* Section card header stacks */
  .section-card-header { flex-direction: column; align-items: flex-start; }

  /* Section card body less padding */
  .section-card-body { padding: 14px; }

  /* Tables: horizontal scroll */
  .table { font-size: 12px; }
  .table th { padding: 9px 10px; font-size: 10.5px; }
  .table td { padding: 9px 10px; }

  /* DataTables filter/length stack */
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dataTables_length { float: none; text-align: left; margin-bottom: 8px; }
  .dataTables_wrapper .dataTables_filter input { width: 100%; margin-left: 0; }

  /* Modals full screen */
  .modal-dialog { margin: 0.5rem; }
  .modal-content { border-radius: 12px; }

  /* Buttons full width in forms */
  .btn-mobile-full { width: 100%; justify-content: center; }

  /* Charts */
  .chart-container { height: 220px !important; }

  /* Page footer */
  .page-footer { flex-direction: column; text-align: center; gap: 4px; padding: 12px 14px; }
  .page-footer span:last-child { display: none; }

  /* Hide datetime in header */
  h4.fw-800 { font-size: 20px !important; }
}

/* =====================================================================
   DESKTOP (≥992px) — Full sidebar
   ===================================================================== */
@media (min-width: 992px) {
  .btn-hamburger { display: none !important; }
  .sidebar { transform: translateX(0) !important; width: var(--sidebar-w); }
  .main-wrapper { margin-left: var(--sidebar-w); }
  .page-content { padding: 26px; }
}

/* =====================================================================
   LARGE DESKTOP (≥1400px)
   ===================================================================== */
@media (min-width: 1400px) {
  .page-content { padding: 30px; }
  .stat-card .card-value { font-size: 30px; }
}

/* ===================== MISC HELPERS ===================== */
.gap-responsive { gap: 12px; }
.overflow-x-auto { overflow-x: auto; -webkit-overflow-scrolling: touch; }
/* ── Theme Picker (dropdown) ─────────────────────────── */
.theme-swatches {
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
  padding: 4px 2px 2px;
}
.swatch {
  width: 24px; height: 24px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  outline: none;
  padding: 0;
}
.swatch:hover { transform: scale(1.2); border-color: #adb5bd; }
.swatch.active { border-color: #343a40; transform: scale(1.15); box-shadow: 0 0 0 2px rgba(0,0,0,0.15); }
</style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><i class="fa-solid fa-dumbbell"></i></div>
    <div class="logo-text">
      <div class="gym-name">Diozabeth Fitness</div>
      <div class="gym-sub">Gym Management</div>
    </div>
  </div>

  <div class="sidebar-nav">
    <?php if ($adminRole !== 'staff'): ?>
    <div class="nav-section">Main</div>
    <div class="nav-item">
      <a href="dashboard.php" data-tooltip="Dashboard"
         class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-line"></i>
        <span class="nav-label">Dashboard</span>
      </a>
    </div>
    <?php endif; ?>

    <div class="nav-section">Members</div>
    <div class="nav-item">
      <a href="walkin_members.php" data-tooltip="Check-in"
         class="<?= $currentPage === 'walkin_members.php' ? 'active' : '' ?>">
        <i class="fas fa-person-walking"></i>
        <span class="nav-label">Check-in</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="subscription_members.php" data-tooltip="Subscriptions"
         class="<?= $currentPage === 'subscription_members.php' ? 'active' : '' ?>">
        <i class="fas fa-id-card"></i>
        <span class="nav-label">Subscriptions</span>
      </a>
    </div>

    <div class="nav-section">Operations</div>
    <div class="nav-item">
      <a href="attendance_monitor.php" data-tooltip="Attendance"
         class="<?= $currentPage === 'attendance_monitor.php' ? 'active' : '' ?>">
        <i class="fas fa-person-booth"></i>
        <span class="nav-label">Attendance Monitoring</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="supplement_sales.php" data-tooltip="Supplement Sales"
         class="<?= $currentPage === 'supplement_sales.php' ? 'active' : '' ?>">
        <i class="fas fa-flask"></i>
        <span class="nav-label">Supplement Sales</span>
      </a>
    </div>
    <?php if ($adminRole !== 'staff'): ?>
    <div class="nav-item">
      <a href="attendance_logs.php" data-tooltip="Logs"
         class="<?= $currentPage === 'attendance_logs.php' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i>
        <span class="nav-label">Attendance Logs</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="staff_salary.php" data-tooltip="Staff Salary"
         class="<?= $currentPage === 'staff_salary.php' ? 'active' : '' ?>">
        <i class="fas fa-money-bill-wave"></i>
        <span class="nav-label">Staff Salary</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="reports_dashboard.php" data-tooltip="Reports"
         class="<?= $currentPage === 'reports_dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-pie"></i>
        <span class="nav-label">Reports Dashboard</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="backup.php" data-tooltip="Drive Backup"
         class="<?= $currentPage === 'backup.php' ? 'active' : '' ?>">
        <i class="fab fa-google-drive"></i>
        <span class="nav-label">Drive Backup</span>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <a href="logout.php" data-tooltip="Logout" onclick="return confirm('Are you sure you want to logout?')">
      <i class="fas fa-sign-out-alt"></i>
      <span class="nav-label">Log out</span>
    </a>
  </div>
</nav>

<!-- Main Wrapper -->
<div class="main-wrapper">

  <!-- Top Navbar -->
  <div class="top-navbar">
    <div class="navbar-left">
      <button class="btn-hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
      </button>
      <span class="page-title-nav">
        <i class="fa-solid fa-dumbbell me-2" style="color:var(--primary);font-size:14px;"></i>Diozabeth Fitness
      </span>
    </div>
    <div class="navbar-right">
      <div class="datetime-badge" id="liveClock">
        <i class="fas fa-clock me-1"></i><?= date('D, M d Y') ?> • <?= date('h:i A') ?>
      </div>
      <div class="user-dropdown dropdown">
        <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="user-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
          <span class="admin-name-text"><?= htmlspecialchars($adminName) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><h6 class="dropdown-header"><?= htmlspecialchars($adminName) ?></h6></li>
          <li><span class="dropdown-item-text text-muted" style="font-size:12px;padding:2px 16px;">
            <?php
              if ($adminRole === 'superadmin') echo 'Owner';
              elseif ($adminRole === 'admin') echo 'Manager';
              else echo 'Staff';
            ?>
          </span></li>
          <li><hr class="dropdown-divider"></li>
          <?php if (in_array($adminRole, ['superadmin', 'admin'])): ?>
          <li><a class="dropdown-item" href="staff_accounts.php"><i class="fas fa-users-cog me-2 text-muted"></i>Manage Staff</a></li>
          <?php endif; ?>
          <?php if ($adminRole === 'superadmin'): ?>
          <li><a class="dropdown-item" href="price_settings.php"><i class="fas fa-tags me-2 text-muted"></i>Price Settings</a></li>
          <?php endif; ?>
          <?php if (in_array($adminRole, ['superadmin', 'admin'])): ?>
          <li><hr class="dropdown-divider"></li>
          <?php endif; ?>
          <li>
            <div style="padding:4px 12px 8px;">
              <div style="font-size:11px;font-weight:700;color:#6b7a99;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
                <i class="fas fa-palette me-1"></i> Theme Color
              </div>
              <div class="theme-swatches">
                <button class="swatch" data-theme="blue"   title="Blue"   style="background:#1e78ff;"></button>
                <button class="swatch" data-theme="green"  title="Green"  style="background:#10b981;"></button>
                <button class="swatch" data-theme="purple" title="Purple" style="background:#8b5cf6;"></button>
                <button class="swatch" data-theme="red"    title="Red"    style="background:#ef4444;"></button>
                <button class="swatch" data-theme="orange" title="Orange" style="background:#f97316;"></button>
                <button class="swatch" data-theme="pink"   title="Pink"   style="background:#ec4899;"></button>
                <button class="swatch" data-theme="teal"   title="Teal"   style="background:#14b8a6;"></button>
                <button class="swatch" data-theme="gold"   title="Gold"   style="background:#f59e0b;"></button>
              </div>
            </div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Page Content -->
  <div class="page-content">
