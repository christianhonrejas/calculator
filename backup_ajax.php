<?php
/**
 * backup_ajax.php — Backup AJAX Endpoint v2 (OAuth 2.0)
 * Session-protected. Superadmin + admin only.
 */
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'] ?? '', ['superadmin', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/gdrive_backup.php';

// ── Ensure tables exist ───────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(200) NOT NULL,
    file_id VARCHAR(200) NULL,
    folder_id VARCHAR(200) NULL,
    file_size INT NOT NULL DEFAULT 0,
    status ENUM('success','failed') NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    created_by VARCHAR(100) NULL,
    backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS backup_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Migrate backup_type column if missing in older installs
$bkCols = [];
$bkCr = $conn->query("SHOW COLUMNS FROM backup_logs");
if ($bkCr) while ($c = $bkCr->fetch_assoc()) $bkCols[] = $c['Field'];
if (!in_array('backup_type', $bkCols)) {
    $conn->query("ALTER TABLE backup_logs ADD COLUMN backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive' AFTER error_message");
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$engine = new GDriveBackup($conn);

// ─────────────────────────────────────────────────────────────────────────────
// save_settings
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'save_settings') {
    if ($_SESSION['admin_role'] !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Only the owner can change backup settings.']);
        exit;
    }
    foreach (['oauth_client_id', 'oauth_client_secret', 'oauth_redirect_uri', 'keep_count'] as $f) {
        if (array_key_exists($f, $_POST)) {
            $val = trim($_POST[$f]);
            if ($f === 'keep_count') $val = (string)max(1, min(30, (int)$val));
            $engine->saveSetting($f, $val);
        }
    }
    echo json_encode(['success' => true, 'message' => 'Settings saved.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// get_auth_url
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_auth_url') {
    if ($_SESSION['admin_role'] !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Only the owner can connect Google Drive.']);
        exit;
    }
    try {
        echo json_encode(['success' => true, 'url' => $engine->getAuthUrl()]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// disconnect
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'disconnect') {
    if ($_SESSION['admin_role'] !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Only the owner can disconnect.']);
        exit;
    }
    try { $engine->disconnect(); } catch (Throwable $e) { /* ignore */ }
    echo json_encode(['success' => true, 'message' => 'Google Drive disconnected.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// run_backup — upload to Google Drive
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'run_backup') {
    $keepCount = max(1, (int)$engine->getSetting('keep_count', '10'));
    $by        = $conn->real_escape_string($_SESSION['admin_name'] ?? 'admin');
    try {
        $result = $engine->runBackup($keepCount);
        $fn   = $conn->real_escape_string($result['filename']);
        $fid  = $conn->real_escape_string($result['file_id']);
        $fold = $conn->real_escape_string($result['folder_id']);
        $size = (int)$result['file_size'];
        $conn->query("INSERT INTO backup_logs (filename,file_id,folder_id,file_size,status,created_by,backup_type)
            VALUES ('$fn','$fid','$fold',$size,'success','$by','drive')");

        echo json_encode([
            'success'   => true,
            'message'   => 'Backup uploaded to your Google Drive!',
            'filename'  => $result['filename'],
            'file_size' => number_format($result['file_size'] / 1024, 1) . ' KB',
        ]);
    } catch (Throwable $e) {
        $err = $conn->real_escape_string($e->getMessage());
        $fn  = $conn->real_escape_string('drive_fail_' . date('Ymd_His'));
        $conn->query("INSERT INTO backup_logs (filename,status,error_message,created_by,backup_type)
            VALUES ('$fn','failed','$err','$by','drive')");
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// get_status
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_status') {
    $connected   = (bool)$engine->getSetting('oauth_refresh_token');
    $connectedAt = $engine->getSetting('oauth_connected_at');
    $clientId    = $engine->getSetting('oauth_client_id');
    $redirectUri = $engine->getSetting('oauth_redirect_uri');
    $keepCount   = $engine->getSetting('keep_count', '10');
    $folderId    = $engine->getSetting('drive_folder_id');
    $accountEmail= '';

    if ($connected && $clientId) {
        try {
            $tok          = $engine->getAccessToken();
            $accountEmail = $engine->getAccountEmail($tok);
        } catch (Throwable $e) {
            $connected = false;
        }
    }

    $lastRow = null;
    $lr = $conn->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 1");
    if ($lr) $lastRow = $lr->fetch_assoc();

    $logRows = [];
    $logs = $conn->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20");
    if ($logs) while ($r = $logs->fetch_assoc()) {
        $logRows[] = [
            'id'          => $r['id'],
            'filename'    => $r['filename'],
            'file_size'   => $r['file_size'] ? number_format($r['file_size'] / 1024, 1) . ' KB' : '—',
            'status'      => $r['status'],
            'backup_type' => $r['backup_type'] ?? 'drive',
            'error'       => $r['error_message'],
            'by'          => $r['created_by'],
            'date'        => date('M d, Y h:i A', strtotime($r['created_at'])),
            'ago'         => humanAgo($r['created_at']),
        ];
    }

    echo json_encode([
        'success'       => true,
        'connected'     => $connected,
        'account_email' => $accountEmail,
        'connected_at'  => $connectedAt ? date('M d, Y h:i A', strtotime($connectedAt)) : null,
        'client_id_set' => (bool)$clientId,
        'redirect_uri'  => $redirectUri,
        'keep_count'    => $keepCount,
        'folder_id'     => $folderId,
        'needs_backup'  => !$lastRow || (time() - strtotime($lastRow['created_at'])) > 86400,
        'last_backup'   => $lastRow ? date('M d, Y h:i A', strtotime($lastRow['created_at'])) : null,
        'last_status'   => $lastRow['status'] ?? null,
        'logs'          => $logRows,
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// delete_log
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_log' && $_SESSION['admin_role'] === 'superadmin') {
    $id = intval($_POST['log_id'] ?? 0);
    if ($id) $conn->query("DELETE FROM backup_logs WHERE id = $id");
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);

function humanAgo(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d/60) . ' min ago';
    if ($d < 86400)  return floor($d/3600) . ' hr ago';
    if ($d < 604800) return floor($d/86400) . ' day(s) ago';
    return date('M d', strtotime($dt));
}
