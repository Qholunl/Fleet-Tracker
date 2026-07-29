<?php
// api/receive_data.php
require_once 'config.php';

$input = getInput();
$device_id = $input['device_id'] ?? '';
if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Ambil data
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;
$speed = floatval($input['speed'] ?? 0);
$fuel_level = floatval($input['fuel'] ?? 100);
$flow_rate = floatval($input['flow_rate'] ?? 0);
$fuel_consumption = floatval($input['fuel_consumption'] ?? 0);
$engine_status = intval($input['engine_status'] ?? 0);
$timestamp = intval($input['timestamp'] ?? time());

// Insert ke telemetry
$stmt = $pdo->prepare("
    INSERT INTO telemetry 
    (device_id, lat, lng, speed, fuel_level, flow_rate, fuel_consumption, engine_status, `timestamp`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$device_id, $lat, $lng, $speed, $fuel_level, $flow_rate, $fuel_consumption, $engine_status, $timestamp]);

// Insert juga ke history (jika diperlukan)
$stmt2 = $pdo->prepare("
    INSERT INTO history 
    (device_id, lat, lng, speed, fuel_level, flow_rate, fuel_consumption, engine_status, `timestamp`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt2->execute([$device_id, $lat, $lng, $speed, $fuel_level, $flow_rate, $fuel_consumption, $engine_status, $timestamp]);

// Update realtime_data
$stmt3 = $pdo->prepare("
    UPDATE realtime_data SET
        lat = ?, lng = ?, speed = ?, fuel_level = ?, 
        flow_rate = ?, fuel_consumption = ?, engine_status = ?,
        status = 'online', last_update = NOW()
    WHERE device_id = ?
");
$stmt3->execute([$lat, $lng, $speed, $fuel_level, $flow_rate, $fuel_consumption, $engine_status, $device_id]);

// Jika device belum ada di realtime_data, insert
if ($stmt3->rowCount() === 0) {
    $stmt4 = $pdo->prepare("
        INSERT INTO realtime_data 
        (device_id, lat, lng, speed, fuel_level, flow_rate, fuel_consumption, engine_status, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'online')
    ");
    $stmt4->execute([$device_id, $lat, $lng, $speed, $fuel_level, $flow_rate, $fuel_consumption, $engine_status]);
}

// Update last_active di devices
$stmt5 = $pdo->prepare("UPDATE devices SET last_active = NOW() WHERE id = ?");
$stmt5->execute([$device_id]);

sendJson(['status' => 'ok']);
?>