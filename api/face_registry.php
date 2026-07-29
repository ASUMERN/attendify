<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$u = currentUser();
if (!$u || !in_array($u['role'], ['admin', 'lecturer'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$db = getDB();
$templates = $db->query("
    SELECT u.reg_no, ft.descriptor_json, ft.updated_at
    FROM face_templates ft
    JOIN users u ON u.id = ft.user_id
    WHERE u.role = 'student' AND u.reg_no IS NOT NULL
    ORDER BY u.reg_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok' => true,
    'students' => array_map(static fn($template) => [
        'reg_no' => $template['reg_no'],
        'descriptor' => json_decode($template['descriptor_json'], true),
        'updated_at' => $template['updated_at'],
    ], $templates),
]);
