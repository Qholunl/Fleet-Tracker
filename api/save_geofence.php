<?php
require 'config.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['user_id'], $input['name'], $input['coordinates'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$user_id = (int)$input['user_id'];
$name = trim($input['name']);
$coordinates = json_encode($input['coordinates']); // array of {lat, lng}

$stmt = $pdo->prepare("INSERT INTO geofences (user_id, name, coordinates) VALUES (?, ?, ?)");
if ($stmt->execute([$user_id, $name, $coordinates])) {
    echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);
} else {
    echo json_encode(['error' => 'Gagal menyimpan geofence']);
}
?>