<?php
/**
 * hardware_checkin.php
 * -----------------------------------------------------------
 * Endpoint called DIRECTLY by the ESP32 (no browser session).
 * Place this file in the same folder as database.php.
 *
 * IMPORTANT: change HARDWARE_API_KEY below to a long random string,
 * and set the exact same string in the ESP32 sketch's API_KEY constant.
 * This key is what stands in for a login, since the door reader can't
 * hold a PHP session like the admin dashboard does.
 */

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

define('HARDWARE_API_KEY', 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET'); // must match the ESP32 sketch

require_once __DIR__ . '/database.php';
ob_clean();

// ── Read the JSON body sent by the ESP32 ───────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$uid    = isset($data['uid'])     ? trim($data['uid'])     : '';
$apiKey = isset($data['api_key']) ? trim($data['api_key']) : '';

// ── Auth check (replaces the session check used by the web dashboard) ──
if (!hash_equals(HARDWARE_API_KEY, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized device']);
    exit;
}

if (!$uid) {
    echo json_encode(['success' => false, 'message' => 'No card UID received']);
    exit;
}

// ── Look up the member by their RFID UID ────────────────────────────────
// NOTE: this assumes you add an `rfid_uid` column to the members table
// (see migration note below) so each member's card UID is on file.
$stmt = $conn->prepare("SELECT * FROM members WHERE rfid_uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    echo json_encode(['success' => false, 'message' => 'Card not registered']);
    exit;
}

if ($member['status'] === 'frozen') {
    echo json_encode(['success' => false, 'message' => 'Membership frozen']);
    exit;
}

if (in_array($member['status'], ['inactive', 'expired'])) {
    echo json_encode(['success' => false, 'message' => 'Subscription expired']);
    exit;
}

// ── Prevent duplicate check-ins on the same day ─────────────────────────
$stmtDup = $conn->prepare("SELECT id FROM attendance_logs WHERE member_id = ? AND DATE(time_in) = CURDATE()");
$stmtDup->bind_param("s", $member['member_id']);
$stmtDup->execute();
$existing = $stmtDup->get_result()->fetch_assoc();
$stmtDup->close();

if ($existing) {
    // Already checked in today - still unlock the door (they're a valid member),
    // just don't log a duplicate attendance row.
    echo json_encode([
        'success' => true,
        'message' => 'Already checked in today',
        'name'    => $member['name'],
    ]);
    exit;
}

// ── Log the attendance and unlock ───────────────────────────────────────
$now = date('Y-m-d H:i:s');
$stmt2 = $conn->prepare("INSERT INTO attendance_logs (member_id, member_name, member_type, time_in, access_result, status) VALUES (?,?,?,?,'granted',?)");
$stmt2->bind_param("sssss", $member['member_id'], $member['name'], $member['member_type'], $now, $member['status']);
$stmt2->execute();
$stmt2->close();

echo json_encode([
    'success' => true,
    'message' => 'Welcome',
    'name'    => $member['name'],
]);
