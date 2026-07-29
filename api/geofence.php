<?php
// api/geofences.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $device_id = $_GET['device_id'] ?? null;
    $sql = "SELECT * FROM geofences";
    $params = [];
    if ($device_id) {
        $sql .= " WHERE device_id = ? OR device_id IS NULL";
        $params[] = $device_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $geofences = $stmt->fetchAll();
    // Decode coordinates JSON
    foreach ($geofences as &$g) {
        $g['coordinates'] = json_decode($g['coordinates'], true);
    }
    sendJson($geofences);
}

if ($method === 'POST') {
    $input = getInput();
    $device_id = $input['device_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $coordinates = $input['coordinates'] ?? []; // array of {lat, lng}
    $type = $input['type'] ?? 'polygon';
    $center_lat = $input['center_lat'] ?? null;
    $center_lng = $input['center_lng'] ?? null;
    $radius = $input['radius_meters'] ?? null;

    if (empty($name) || empty($coordinates)) {
        sendJson(['error' => 'name dan coordinates harus diisi'], 400);
    }

    $coordJson = json_encode($coordinates);
    $stmt = $pdo->prepare("
        INSERT INTO geofences 
        (device_id, name, coordinates, type, center_lat, center_lng, radius_meters)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$device_id, $name, $coordJson, $type, $center_lat, $center_lng, $radius]);
    sendJson(['status' => 'saved', 'id' => $pdo->lastInsertId()]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        sendJson(['error' => 'id required'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM geofences WHERE id = ?");
    $stmt->execute([$id]);
    sendJson(['status' => 'deleted']);
}

sendJson(['error' => 'Method not allowed'], 405);
?>
