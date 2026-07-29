<?php
require 'config.php';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) { echo json_encode(['error' => 'user_id required']); exit; }

$stmt = $pdo->prepare("SELECT data FROM user_data WHERE user_id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch();
echo json_encode(['data' => $row ? $row['data'] : '{}']);
?>