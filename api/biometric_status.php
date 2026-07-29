<?php
require __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$u = currentUser();
if (!$u || $u['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$db = getDB();

$credential = $db->prepare('SELECT credential_id, label, updated_at FROM webauthn_credentials WHERE user_id = ?');
$credential->execute([$u['id']]);
$credential = $credential->fetch(PDO::FETCH_ASSOC) ?: null;

$face = $db->prepare('SELECT descriptor_json, sample_count, updated_at FROM face_templates WHERE user_id = ?');
$face->execute([$u['id']]);
$face = $face->fetch(PDO::FETCH_ASSOC) ?: null;

echo json_encode([
    'ok' => true,
    'webauthn_enrolled' => (bool)$credential,
    'webauthn_label' => $credential['label'] ?? null,
    'face_enrolled' => (bool)$face,
    'face_samples' => (int)($face['sample_count'] ?? 0),
    'face_updated_at' => $face['updated_at'] ?? null,
]);