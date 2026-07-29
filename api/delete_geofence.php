<?php
require 'config.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$id = (int)$input['id'];
$stmt = $pdo->prepare("DELETE FROM geofences WHERE id = ?");
if ($stmt->execute([$id])) {
    echo json_encode(['status' => 'deleted']);
} else {
    echo json_encode(['error' => 'Gagal menghapus']);
}
?>