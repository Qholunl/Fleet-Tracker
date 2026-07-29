<?php
require 'config.php';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, coordinates FROM geofences WHERE user_id = ?");
$stmt->execute([$user_id]);
$geofences = $stmt->fetchAll();
foreach ($geofences as &$g) {
    $g['coordinates'] = json_decode($g['coordinates'], true);
}
echo json_encode($geofences);
?>