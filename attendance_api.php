<?php
ob_start();
error_reporting(0);
ini_set('display_errors', '0');

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/database.php';
ob_clean();

$action = $_GET['action'] ?? 'list';

// ── AJAX Check-in ──────────────────────────────────────────────────────────
if ($action === 'checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
        exit;
    }

    $memberId   = trim($_POST['member_id']   ?? '');
    $memberType = trim($_POST['member_type'] ?? '');

    if (!$memberId || !$memberType) {
        echo json_encode(['success' => false, 'message' => 'Please provide member ID and type.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
    $stmt->bind_param("s", $memberId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Member not found.']);
        exit;
    }
    if ($member['status'] === 'frozen') {
        echo json_encode(['success' => false, 'message' => 'This member is currently frozen and cannot check in.']);
        exit;
    }
    if (in_array($member['status'], ['inactive', 'expired'])) {
        echo json_encode(['success' => false, 'message' => "This member's subscription has expired."]);
        exit;
    }

    $stmtDup = $conn->prepare("SELECT id FROM attendance_logs WHERE member_id = ? AND DATE(time_in) = CURDATE()");
    $stmtDup->bind_param("s", $memberId);
    $stmtDup->execute();
    $existing = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($existing) {
        echo json_encode(['success' => false, 'message' => htmlspecialchars($member['name']) . ' has already checked in today.']);
        exit;
    }

    $now   = date('Y-m-d H:i:s');
    $stmt2 = $conn->prepare("INSERT INTO attendance_logs (member_id, member_name, member_type, time_in, access_result, status) VALUES (?,?,?,?,'granted',?)");
    $stmt2->bind_param("sssss", $memberId, $member['name'], $memberType, $now, $member['status']);
    if ($stmt2->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Check-in recorded!',
            'entry'   => [
                'id'     => $memberId,
                'name'   => $member['name'],
                'type'   => $memberType,
                'time'   => date('h:i A', strtotime($now)),
                'status' => $member['status'],
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt2->close();
    exit;
}

// ── Today's Attendance List ────────────────────────────────────────────────
if ($action === 'list') {
    $walkins = $conn->query("SELECT member_id, member_name, member_type, time_in, status FROM attendance_logs WHERE DATE(time_in) = CURDATE() AND member_type = 'walkin' ORDER BY time_in DESC");
    $subs    = $conn->query("SELECT member_id, member_name, member_type, time_in, status FROM attendance_logs WHERE DATE(time_in) = CURDATE() AND member_type = 'subscription' ORDER BY time_in DESC");
    $latestR = $conn->query("SELECT MAX(time_in) as ts FROM attendance_logs WHERE DATE(time_in) = CURDATE()");
    $latest  = $latestR ? $latestR->fetch_assoc() : ['ts' => null];

    $walkinRows = [];
    if ($walkins) while ($r = $walkins->fetch_assoc()) {
        $walkinRows[] = ['id' => $r['member_id'], 'name' => $r['member_name'], 'type' => $r['member_type'], 'time' => date('h:i A', strtotime($r['time_in'])), 'status' => $r['status']];
    }
    $subRows = [];
    if ($subs) while ($r = $subs->fetch_assoc()) {
        $subRows[] = ['id' => $r['member_id'], 'name' => $r['member_name'], 'type' => $r['member_type'], 'time' => date('h:i A', strtotime($r['time_in'])), 'status' => $r['status']];
    }

    echo json_encode(['success' => true, 'date' => date('l, F d, Y'), 'latest_ts' => $latest['ts'] ?? null, 'total' => count($walkinRows) + count($subRows), 'walkins' => $walkinRows, 'subscribers' => $subRows]);
    exit;
}

// ── Subscriber Live Search ─────────────────────────────────────────────────
if ($action === 'search') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please refresh and log in again.']);
        exit;
    }

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $escaped = $conn->real_escape_string($q);
    $like    = '%' . $escaped . '%';

    // Get the latest subscription per member using same pattern as subscription_members.php
    // This ensures we always find members regardless of sub status
    $sql = "
        SELECT
            m.member_id,
            m.name,
            m.status,
            m.phone,
            s.end_date,
            s.status        AS sub_status,
            s.payment_amount,
            (
                SELECT COUNT(*)
                FROM attendance_logs al
                WHERE al.member_id = m.member_id
                  AND DATE(al.time_in) = CURDATE()
            ) AS checked_in_today
        FROM members m
        LEFT JOIN subscriptions s ON s.id = (
            SELECT id FROM subscriptions
            WHERE member_id = m.member_id
            ORDER BY end_date DESC
            LIMIT 1
        )
        WHERE m.member_type = 'subscription'
          AND (m.member_id LIKE '$like' OR m.name LIKE '$like')
        ORDER BY
            CASE WHEN m.status = 'active' THEN 0 ELSE 1 END,
            m.name ASC
        LIMIT 8
    ";

    $rows = $conn->query($sql);
    if (!$rows) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
        exit;
    }

    $results = [];
    while ($r = $rows->fetch_assoc()) {
        $results[] = [
            'member_id'        => $r['member_id'],
            'name'             => $r['name'],
            'status'           => $r['status'],
            'sub_status'       => $r['sub_status'] ?? 'expired',
            'end_date'         => $r['end_date'] ? date('M d, Y', strtotime($r['end_date'])) : null,
            'phone'            => $r['phone'] ?? '',
            'payment_amount'   => $r['payment_amount'] ?? 0,
            'checked_in_today' => (int)$r['checked_in_today'] > 0,
        ];
    }

    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
ob_end_flush();
