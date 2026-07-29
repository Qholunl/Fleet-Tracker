<?php
// api/send_control.php
require_once 'config.php';

$input = getInput();
$device_id = $input['device_id'] ?? '';
$active = (bool)($input['active'] ?? false);

if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Simpan perintah ke control_queue
$command = $active ? 'SOLENOID_ON' : 'SOLENOID_OFF';
$payload = json_encode(['active' => $active]);

$stmt = $pdo->prepare("INSERT INTO control_queue (device_id, command, payload, status) VALUES (?, ?, ?, 'pending')");
$stmt->execute([$device_id, $command, $payload]);

// Update realtime_data solenoid_state agar dashboard langsung berubah
$stmt2 = $pdo->prepare("UPDATE realtime_data SET solenoid_state = ? WHERE device_id = ?");
$stmt2->execute([$active ? 1 : 0, $device_id]);

sendJson(['status' => 'ok', 'command' => $command]);
?>