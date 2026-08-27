<?php
/**
 * backup_oauth.php — Google OAuth2 Callback Handler
 * Called by Google after the user authorises the app.
 * Must be listed as an Authorised Redirect URI in Google Cloud Console.
 */
date_default_timezone_set('Asia/Manila');
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/gdrive_backup.php';

// Must be logged in as superadmin to connect Drive
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header('Location: index.php');
    exit;
}

$engine = new GDriveBackup($conn);

// ── Handle errors from Google ────────────────────────────────────────────────
if (isset($_GET['error'])) {
    $_SESSION['backup_msg'] = ['type' => 'error', 'text' => 'Google authorisation was denied or cancelled: ' . htmlspecialchars($_GET['error'])];
    header('Location: backup.php');
    exit;
}

// ── Exchange the code for tokens ─────────────────────────────────────────────
if (isset($_GET['code'])) {
    try {
        $engine->handleOAuthCallback($_GET['code']);
        $_SESSION['backup_msg'] = ['type' => 'success', 'text' => 'Google Drive connected successfully! You can now run backups.'];
    } catch (Throwable $e) {
        $_SESSION['backup_msg'] = ['type' => 'error', 'text' => 'Connection failed: ' . $e->getMessage()];
    }
    header('Location: backup.php');
    exit;
}

// ── No code — unexpected landing ─────────────────────────────────────────────
header('Location: backup.php');
exit;
