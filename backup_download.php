<?php
/**
 * backup_download.php — Download Database Backup to PC / USB
 * Streams the SQL dump directly to the browser as a file download.
 * Auth: superadmin + admin only.
 */
date_default_timezone_set('Asia/Manila');
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/gdrive_backup.php';

// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'] ?? '', ['superadmin', 'admin'])) {
    http_response_code(403);
    die('Unauthorized.');
}

set_time_limit(300);
ini_set('memory_limit', '128M');

try {
    $engine   = new GDriveBackup($conn);
    $sql      = $engine->generateSQLDump();
    $filename = 'gym_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // ── Log this download ────────────────────────────────────────────────────
    $conn->query("CREATE TABLE IF NOT EXISTS backup_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(200) NOT NULL,
        file_id VARCHAR(200) NULL,
        folder_id VARCHAR(200) NULL,
        file_size INT NOT NULL DEFAULT 0,
        status ENUM('success','failed') NOT NULL DEFAULT 'success',
        error_message TEXT NULL,
        created_by VARCHAR(100) NULL,
        backup_type ENUM('drive','local') NOT NULL DEFAULT 'local',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Migrate backup_type column if older table exists
    $cols = [];
    $cr = $conn->query("SHOW COLUMNS FROM backup_logs");
    if ($cr) while ($c = $cr->fetch_assoc()) $cols[] = $c['Field'];
    if (!in_array('backup_type', $cols)) {
        $conn->query("ALTER TABLE backup_logs ADD COLUMN backup_type ENUM('drive','local') NOT NULL DEFAULT 'local' AFTER error_message");
    }

    $fn   = $conn->real_escape_string($filename);
    $by   = $conn->real_escape_string($_SESSION['admin_name'] ?? 'admin');
    $size = strlen($sql);
    $conn->query("INSERT INTO backup_logs (filename,file_size,status,created_by,backup_type)
        VALUES ('$fn',$size,'success','$by','local')");

    // ── Stream file to browser ────────────────────────────────────────────────
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $sql;

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Backup generation failed: ' . htmlspecialchars($e->getMessage());
}
exit;
