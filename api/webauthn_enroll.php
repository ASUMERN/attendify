<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$u = currentUser();
if (!$u) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

// Get the input first
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Admin can enroll for a different student
$targetUserId = $u['id'];
if ($u['role'] === 'admin' && !empty($input['student_id'])) {
    $targetUserId = (int)$input['student_id'];
    $student = getDB()->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
    $student->execute([$targetUserId, 'student']);
    if (!$student->fetch()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid student ID.']);
        exit;
    }
} elseif ($u['role'] === 'student' && !empty($input['student_id'])) {
    // Students can only enroll for themselves
    $targetUserId = (int)$input['student_id'];
    if ($targetUserId !== $u['id']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Cannot enroll another student.']);
        exit;
    }
} elseif ($u['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}
$credentialId = $input['id'] ?? ($input['rawId'] ?? '');
$clientDataJSON = $input['response']['clientDataJSON'] ?? '';
$attestationObject = $input['response']['attestationObject'] ?? '';
$mode = 'register';
$expectedChallenge = get_biometric_challenge($mode);

if (!$expectedChallenge || !$credentialId || !$clientDataJSON || !$attestationObject) {
    echo json_encode(['ok' => false, 'message' => 'Invalid enrollment payload - missing required WebAuthn fields.']);
    exit;
}

$clientData = json_decode(base64url_decode_string($clientDataJSON), true) ?: [];
if (($clientData['challenge'] ?? '') !== $expectedChallenge) {
    echo json_encode(['ok' => false, 'message' => 'Enrollment challenge mismatch.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('INSERT INTO webauthn_credentials (user_id, credential_id, label, updated_at) VALUES (?, ?, ?, datetime(\'now\')) ON CONFLICT(user_id) DO UPDATE SET credential_id = excluded.credential_id, label = excluded.label, updated_at = datetime(\'now\')');
$stmt->execute([$targetUserId, $credentialId, $input['label'] ?? 'This device']);
$db->prepare('UPDATE users SET biometric_registered = 1 WHERE id = ?')->execute([$targetUserId]);
clear_biometric_challenge($mode);

echo json_encode(['ok' => true, 'message' => 'This device is now enrolled for biometric sign-in.']);