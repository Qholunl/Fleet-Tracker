<?php
// api/get_geofences.php
require_once 'config.php';

// Ambil parameter
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id] : 0;
$device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;

// Validasi user_id
if ($user_id === 0) {
    sendJson(['error' => 'user_id required'], 400);
}

// Query dasar
$sql = "SELECT id, name, coordinates, type, center_lat, center_lng, radius_meters 
        FROM geofences 
        WHERE user_id = ?";
$params = [$user_id];

// Filter berdasarkan device_id jika ada (bisa null untuk global)
if ($device_id !== null && $device_id !== '') {
    $sql .= " AND (device_id = ? OR device_id IS NULL)";
    $params[] = $device_id;
}

// Order by nama
$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$geofences = $stmt->fetchAll();

// Decode coordinates JSON dan tambahkan fallback jika invalid
foreach ($geofences as &$g) {
    $coords = json_decode($g['coordinates'], true);
    if (is_array($coords)) {
        $g['coordinates'] = $coords;
    } else {
        $g['coordinates'] = []; // fallback jika JSON tidak valid
    }
}

// Kirim response
sendJson($geofences);
?>
