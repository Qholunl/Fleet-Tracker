<?php
// api/get_history.php
require_once 'config.php';

$device_id = $_GET['device_id'] ?? '';
$limit = intval($_GET['limit'] ?? 500);

if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Gunakan tabel history (atau telemetry jika history kosong)
// Karena history mungkin tabel, kita ambil dari telemetry agar konsisten
$stmt = $pdo->prepare("
    SELECT 
        lat, lng, speed, fuel_level, flow_rate, fuel_consumption,
        engine_status, solenoid_state, `timestamp`,
        FROM_UNIXTIME(`timestamp`) as time_str
    FROM telemetry
    WHERE device_id = ?
    ORDER BY `timestamp` DESC
    LIMIT ?
");
$stmt->execute([$device_id, $limit]);
$rows = $stmt->fetchAll();

// Balik urutan agar ascending (untuk playback)
$rows = array_reverse($rows);

$result = [];
foreach ($rows as $row) {
    $result[] = [
        'lat' => floatval($row['lat'] ?? 0),
        'lng' => floatval($row['lng'] ?? 0),
        'speed' => floatval($row['speed'] ?? 0),
        'flow_rate' => floatval($row['flow_rate'] ?? 0),
        'fuel_consumption' => floatval($row['fuel_consumption'] ?? 0),
        'engine_status' => intval($row['engine_status'] ?? 0),
        'fuel' => floatval($row['fuel_level'] ?? 0),
        'timestamp' => intval($row['timestamp'] ?? 0),
        'timeStr' => $row['time_str'] ?? ''
    ];
}

sendJson($result);
?>