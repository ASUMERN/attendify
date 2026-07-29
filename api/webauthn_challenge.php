<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$u = currentUser();
if (!$u) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'auth') === 'register' ? 'register' : 'auth';
$db = getDB();
$targetUser = $u;

if (!empty($_GET['student_id']) || !empty($_POST['student_id'])) {
    $targetUserId = (int)($_GET['student_id'] ?? $_POST['student_id']);

    if ($u['role'] === 'admin') {
        $student = $db->prepare('SELECT id, username, full_name FROM users WHERE id = ? AND role = ?');
        $student->execute([$targetUserId, 'student']);
        $targetUser = $student->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$targetUser) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Invalid student ID.']);
            exit;
        }
    } elseif ($u['role'] === 'student' && $targetUserId !== (int)$u['id']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Cannot enroll another student.']);
        exit;
    } elseif ($u['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
        exit;
    }
} elseif ($u['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$existing = $db->prepare('SELECT credential_id, label FROM webauthn_credentials WHERE user_id = ?');
$existing->execute([$targetUser['id']]);
$existing = $existing->fetch(PDO::FETCH_ASSOC) ?: null;

$challenge = create_biometric_challenge($mode);
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rpId = explode(':', $host, 2)[0];

$payload = [
    'challenge' => $challenge,
    'rp' => [
        'name' => 'Attend Biometric System',
        'id' => $rpId,
    ],
    'user' => [
        'id' => base64url_encode_string((string)$targetUser['id']),
        'name' => $targetUser['username'],
        'displayName' => $targetUser['full_name'],
    ],
    'registered' => (bool)$existing,
    'label' => $existing['label'] ?? null,
];

if ($mode === 'auth') {
    $payload['allowCredentials'] = $existing ? [[
        'type' => 'public-key',
        'id' => $existing['credential_id'],
    ]] : [];
    $payload['timeout'] = 60000;
    $payload['userVerification'] = 'preferred';
} else {
    $payload['pubKeyCredParams'] = [
        ['type' => 'public-key', 'alg' => -7],
        ['type' => 'public-key', 'alg' => -257],
    ];
    $payload['timeout'] = 60000;
    $payload['userVerification'] = 'preferred';
    $payload['authenticatorSelection'] = [
        'authenticatorAttachment' => 'platform',
        'residentKey' => 'preferred',
        'requireResidentKey' => false,
        'userVerification' => 'preferred',
    ];
    $payload['attestation'] = 'none';
}

echo json_encode(['ok' => true, 'mode' => $mode, 'options' => $payload]);
