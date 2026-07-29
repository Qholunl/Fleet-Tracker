<?php
require 'config.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['user_id'], $input['data'])) {
    echo json_encode(['error' => 'Invalid data']); exit;
}
$user_id = (int)$input['user_id'];
$data = $input['data'];

$stmt = $pdo->prepare("INSERT INTO user_data (user_id, data) VALUES (?, ?) ON DUPLICATE KEY UPDATE data = ?");
$stmt->execute([$user_id, $data, $data]);
echo json_encode(['status' => 'ok']);
?>