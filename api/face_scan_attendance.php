<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user || !in_array($user['role'], ['admin', 'lecturer'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$db = getDB();

// The scanner uses this to let the operator choose the class currently being held.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessions = $db->query("SELECT id, title, course, session_date, start_time, end_time
                            FROM class_sessions
                            WHERE session_date = date('now')
                            ORDER BY start_time ASC, id ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'sessions' => $sessions]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (int)($input['session_id'] ?? 0);
$regNo = trim((string)($input['reg_no'] ?? ''));

if (!$sessionId || $regNo === '') {
    echo json_encode(['ok' => false, 'message' => 'A class and recognised registration number are required.']);
    exit;
}

$sessionStmt = $db->prepare("SELECT * FROM class_sessions WHERE id = ? AND session_date = date('now')");
$sessionStmt->execute([$sessionId]);
$session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    echo json_encode(['ok' => false, 'message' => 'The selected class is not active today.']);
    exit;
}

$studentStmt = $db->prepare("SELECT id, reg_no, course FROM users WHERE role = 'student' AND reg_no = ?");
$studentStmt->execute([$regNo]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    echo json_encode(['ok' => true, 'outcome' => 'not_recognised', 'message' => 'Student not recognised.']);
    exit;
}

$sameClass = strcasecmp(trim((string)$student['course']), trim((string)$session['course'])) === 0;
$verified = $sameClass ? 1 : 0;

$db->beginTransaction();
try {
    // One verified record per student and class session; repeat scans keep the original attendance.
    $existing = $db->prepare("SELECT id FROM attendance_logs
                              WHERE session_id = ? AND student_id = ? AND attendance_status = 'verified'
                              LIMIT 1");
    $existing->execute([$sessionId, $student['id']]);

    if (!$existing->fetch() || !$sameClass) {
        $log = $db->prepare("INSERT INTO attendance_logs
            (session_id, student_id, scan_method, scan_result, retry_count, door_opened, entered_classroom, attendance_status)
            VALUES (?, ?, 'face', ?, 0, ?, ?, ?)");
        $log->execute([
            $sessionId,
            $student['id'],
            $verified ? 'verified' : 'unverified',
            $verified,
            $verified,
            $verified ? 'verified' : 'unverified',
        ]);
    }

    if ($sameClass) {
        $db->prepare("UPDATE class_sessions SET door_status = 'open' WHERE id = ?")->execute([$sessionId]);
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not record attendance.']);
    exit;
}

echo json_encode($sameClass
    ? ['ok' => true, 'outcome' => 'attended', 'reg_no' => $student['reg_no'], 'message' => $student['reg_no'] . ' attended.']
    : ['ok' => true, 'outcome' => 'wrong_class', 'reg_no' => $student['reg_no'], 'message' => $student['reg_no'] . ' wrong Class.']
);
