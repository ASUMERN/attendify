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
$method = $input['method'] ?? 'fingerprint';        // fingerprint | face | eye
$retryCount = (int)($input['retry_count'] ?? 0);
$sessionId = (int)($input['session_id'] ?? 0);
$biometricMode = $input['biometric_mode'] ?? 'legacy';
$biometricVerified = !empty($input['biometric_verified']);

if (!in_array($method, ['fingerprint', 'face', 'eye'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid scan method.']);
    exit;
}

$db = getDB();
$session = $db->prepare("SELECT * FROM class_sessions WHERE id = ?");
$session->execute([$sessionId]);
$session = $session->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    echo json_encode(['ok' => false, 'message' => 'No active class session found.']);
    exit;
}

function clientOrigin(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Extract hostname only (remove port for flexible matching)
    $hostname = explode(':', $host)[0];
    return $scheme . '://' . $hostname;
}

$verified = false;
$message = 'Scan not recognised. Please re-position and try again.';

if ($biometricMode === 'webauthn') {
    $credential = $db->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
    $credential->execute([$u['id']]);
    $credential = $credential->fetch(PDO::FETCH_ASSOC) ?: null;

    $payload = $input['biometric_payload'] ?? [];
    $clientDataJSON = $payload['response']['clientDataJSON'] ?? '';
    $rawId = $payload['rawId'] ?? ($payload['id'] ?? '');
    $clientData = json_decode(base64url_decode_string($clientDataJSON), true) ?: [];
    $challenge = get_biometric_challenge('auth');

    if (!$credential) {
        $message = 'This device is not enrolled for WebAuthn yet.';
    } elseif (!$challenge) {
        $message = 'WebAuthn challenge expired. Start the biometric scan again.';
    } elseif (!$biometricVerified) {
        $message = 'WebAuthn verification was not completed.';
    } elseif (($clientData['challenge'] ?? '') !== $challenge) {
        $message = 'WebAuthn challenge mismatch.';
    } elseif (($clientData['origin'] ?? '') !== clientOrigin()) {
        $message = 'WebAuthn origin mismatch.';
    } elseif (($clientData['type'] ?? '') !== 'webauthn.get') {
        $message = 'Invalid WebAuthn response type.';
    } elseif ($rawId !== $credential['credential_id']) {
        $message = 'Biometric device does not match the enrolled credential.';
    } else {
        $verified = true;
        $message = 'Platform biometric verified. Door unlocking...';
        clear_biometric_challenge('auth');
    }
} elseif ($biometricMode === 'camera_face') {
    if ($biometricVerified) {
        $verified = true;
        $message = 'Face recognition matched. Door unlocking...';
    }
} else {
    // --- Simulated biometric matching logic ---
    // A registered student succeeds with high probability; probability improves with each retry
    // to emulate a person re-positioning their finger/face, mirroring the "If unverified -> re-enter input" loop.
    $baseChance = $u['biometric_registered'] ? 0.72 : 0.15;
    $chance = min(0.97, $baseChance + ($retryCount * 0.15));
    $verified = (mt_rand(1, 1000) / 1000) <= $chance;
    if ($verified) {
        $message = 'Biometric match confirmed. Door unlocking...';
    }
}

$stmt = $db->prepare("INSERT INTO attendance_logs
    (session_id, student_id, scan_method, scan_result, retry_count, door_opened)
    VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $sessionId,
    $u['id'],
    $method,
    $verified ? 'verified' : 'unverified',
    $retryCount,
    $verified ? 1 : 0
]);
$logId = $db->lastInsertId();

if ($verified) {
    $db->prepare("UPDATE class_sessions SET door_status = 'open' WHERE id = ?")->execute([$sessionId]);
}

echo json_encode([
    'ok' => true,
    'verified' => $verified,
    'log_id' => $logId,
    'method' => $method,
    'message' => $message
]);
