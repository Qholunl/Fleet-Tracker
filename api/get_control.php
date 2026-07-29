<?php
// api/get_control.php
require_once 'config.php';

$device_id = $_GET['device_id'] ?? '';

if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// ============================================================
// 1. Ambil perintah pending terbaru untuk device ini
// ============================================================
$stmt = $pdo->prepare("
    SELECT id, command, payload FROM control_queue 
    WHERE device_id = ? AND status = 'pending' 
    ORDER BY id ASC LIMIT 1
");
$stmt->execute([$device_id]);
$cmd = $stmt->fetch();

if ($cmd) {
    // Tandai perintah sebagai sudah dikirim (sent)
    $stmt2 = $pdo->prepare("UPDATE control_queue SET status = 'sent', updated_at = NOW() WHERE id = ?");
    $stmt2->execute([$cmd['id']]);

    // ============================================================
    // 2. Proses perintah berdasarkan jenis command
    // ============================================================
    $command = $cmd['command'] ?? '';

    if ($command === 'SOLENOID_ON') {
        sendJson(['solenoid' => true]);
    } elseif ($command === 'SOLENOID_OFF') {
        sendJson(['solenoid' => false]);
    } elseif ($command === 'CUSTOM_PAYLOAD') {
        // Contoh untuk perintah custom di masa depan
        $payload = json_decode($cmd['payload'] ?? '{}', true);
        sendJson([
            'command' => $command,
            'payload' => $payload
        ]);
    } else {
        // Jika command tidak dikenal, fallback ke status terakhir
        sendJson(['error' => 'Unknown command'], 400);
    }
}

// ============================================================
// 3. Jika tidak ada perintah pending, kirim status terakhir dari realtime_data
// ============================================================
$stmt3 = $pdo->prepare("
    SELECT solenoid_state, last_update 
    FROM realtime_data 
    WHERE device_id = ?
");
$stmt3->execute([$device_id]);
$row = $stmt3->fetch();

if ($row) {
    $solenoid = (bool) $row['solenoid_state'];
    $last_update = $row['last_update'];
    sendJson([
        'solenoid' => $solenoid,
        'last_update' => $last_update,
        'status' => 'no_pending_command'
    ]);
} else {
    // Jika device belum pernah mengirim data, default OFF
    sendJson([
        'solenoid' => false,
        'status' => 'device_not_found'
    ]);
}
?>
