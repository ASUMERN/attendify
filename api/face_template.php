<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$u = currentUser();
if (!$u) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

// Admin can enroll for a different student
$targetUserId = $u['id'];
if ($u['role'] === 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($input['student_id'])) {
            $targetUserId = (int)$input['student_id'];
        }
    } elseif (!empty($_GET['student_id'])) {
        $targetUserId = (int)$_GET['student_id'];
    }
    
    if ($targetUserId !== $u['id']) {
        $student = getDB()->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
        $student->execute([$targetUserId, 'student']);
        if (!$student->fetch()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Invalid student ID.']);
            exit;
        }
    }
} elseif ($u['role'] === 'student') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($input['student_id'])) {
            $targetUserId = (int)$input['student_id'];
            // Students can only enroll for themselves
            if ($targetUserId !== $u['id']) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Cannot enroll another student.']);
                exit;
            }
        }
    } elseif (!empty($_GET['student_id'])) {
        $targetUserId = (int)$_GET['student_id'];
        if ($targetUserId !== $u['id']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Cannot enroll another student.']);
            exit;
        }
    }
} else {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT descriptor_json, sample_count, updated_at FROM face_templates WHERE user_id = ?');
    $stmt->execute([$targetUserId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    echo json_encode([
        'ok' => true,
        'enrolled' => (bool)$template,
        'descriptor' => $template['descriptor_json'] ?? null,
        'sample_count' => (int)($template['sample_count'] ?? 0),
        'updated_at' => $template['updated_at'] ?? null,
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$descriptor = $input['descriptor'] ?? null;
$sampleCount = max(1, (int)($input['sample_count'] ?? 1));

if (!is_array($descriptor) || !$descriptor) {
    echo json_encode(['ok' => false, 'message' => 'Descriptor missing.']);
    exit;
}

$stmt = $db->prepare('INSERT INTO face_templates (user_id, descriptor_json, sample_count, updated_at) VALUES (?, ?, ?, datetime(\'now\')) ON CONFLICT(user_id) DO UPDATE SET descriptor_json = excluded.descriptor_json, sample_count = excluded.sample_count, updated_at = datetime(\'now\')');
$stmt->execute([$targetUserId, json_encode($descriptor), $sampleCount]);
$db->prepare('UPDATE users SET biometric_registered = 1 WHERE id = ?')->execute([$targetUserId]);

echo json_encode(['ok' => true, 'message' => 'Face template saved.']);