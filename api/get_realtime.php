<?php
// api/get_realtime.php
require_once 'config.php';

$stmt = $pdo->query("
    SELECT 
        r.device_id,
        d.name,
        r.lat,
        r.lng,
        r.speed,
        r.fuel_level,
        r.flow_rate,
        r.fuel_consumption,
        r.engine_status,
        r.solenoid_state,
        r.status,
        r.last_update
    FROM realtime_data r
    LEFT JOIN devices d ON r.device_id = d.id
    ORDER BY r.last_update DESC
");
$data = $stmt->fetchAll();

// Format output sesuai yang diharapkan dashboard
$result = [];
foreach ($data as $row) {
    $result[] = [
        'device_id' => $row['device_id'],
        'name' => $row['name'] ?? 'Unnamed',
        'lat' => floatval($row['lat'] ?? 0),
        'lng' => floatval($row['lng'] ?? 0),
        'speed' => floatval($row['speed'] ?? 0),
        'fuel' => floatval($row['fuel_level'] ?? 0),
        'flow_rate' => floatval($row['flow_rate'] ?? 0),
        'fuel_consumption' => floatval($row['fuel_consumption'] ?? 0),
        'engine_status' => intval($row['engine_status'] ?? 0),
        'solenoid_state' => (bool)($row['solenoid_state'] ?? 0),
        'status' => $row['status'] ?? 'offline',
        'last_update' => $row['last_update'] ?? null
    ];
}

sendJson($result);
?>