<?php
// api/get_control.php
require_once 'config.php';

$device_id = $_GET['device_id'] ?? '';

if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Ambil perintah pending terbaru
$stmt = $pdo->prepare("
    SELECT id, command, payload FROM control_queue 
    WHERE device_id = ? AND status = 'pending' 
    ORDER BY id ASC LIMIT 1
");
$stmt->execute([$device_id]);
$cmd = $stmt->fetch();

if ($cmd) {
    // Tandai sebagai sent
    $stmt2 = $pdo->prepare("UPDATE control_queue SET status = 'sent', updated_at = NOW() WHERE id = ?");
    $stmt2->execute([$cmd['id']]);

    $solenoid = ($cmd['command'] === 'SOLENOID_ON');
    sendJson(['solenoid' => $solenoid]);
} else {
    // Jika tidak ada perintah, kirim status terakhir dari realtime_data
    $stmt3 = $pdo->prepare("SELECT solenoid_state FROM realtime_data WHERE device_id = ?");
    $stmt3->execute([$device_id]);
    $row = $stmt3->fetch();
    $solenoid = $row ? (bool)$row['solenoid_state'] : false;
    sendJson(['solenoid' => $solenoid]);
}
?>