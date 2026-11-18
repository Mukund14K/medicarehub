<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$specialization = trim($_GET['specialization'] ?? '');

if (empty($specialization)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare('SELECT doctor_id AS id, doctor_id, name, specialization, phone FROM Doctor WHERE specialization = ? ORDER BY name ASC');
$stmt->execute([$specialization]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($doctors);
exit;
