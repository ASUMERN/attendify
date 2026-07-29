<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$u = currentUser();
if (!$u || $u['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$logId = (int)($input['log_id'] ?? 0);
$entered = !empty($input['entered']); // true = walked into classroom, false = did not enter

$db = getDB();
$log = $db->prepare("SELECT * FROM attendance_logs WHERE id = ? AND student_id = ?");
$log->execute([$logId, $u['id']]);
$log = $log->fetch(PDO::FETCH_ASSOC);

if (!$log || !$log['door_opened']) {
    echo json_encode(['ok' => false, 'message' => 'No open-door scan found for this attempt.']);
    exit;
}

$status = $entered ? 'verified' : 'unverified';

$stmt = $db->prepare("UPDATE attendance_logs
    SET entered_classroom = ?, attendance_status = ?
    WHERE id = ?");
$stmt->execute([$entered ? 1 : 0, $status, $logId]);

echo json_encode([
    'ok' => true,
    'attendance_status' => $status,
    'message' => $entered
        ? 'Entry detected — attendance recorded as VERIFIED and saved to the database.'
        : 'No entry detected at the doorway — attendance recorded as UNVERIFIED.'
]);
