<?php
// api/manage_devices.php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Ambil semua device (bisa filter user_id jika perlu)
    $user_id = $_GET['user_id'] ?? null;
    $sql = "SELECT * FROM devices";
    $params = [];
    if ($user_id) {
        $sql .= " WHERE user_id = ? OR user_id IS NULL";
        $params[] = $user_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $devices = $stmt->fetchAll();
    sendJson($devices);
}

if ($method === 'POST') {
    $input = getInput();
    $action = $input['action'] ?? '';
    $device_id = strtoupper(trim($input['id'] ?? ''));
    $name = trim($input['name'] ?? '');
    $user_id = $input['user_id'] ?? null;

    if ($action === 'add') {
        if (empty($device_id) || empty($name)) {
            sendJson(['error' => 'ID dan nama perangkat harus diisi'], 400);
        }
        // Cek apakah sudah ada
        $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ?");
        $stmt->execute([$device_id]);
        if ($stmt->fetch()) {
            sendJson(['error' => 'Perangkat dengan ID tersebut sudah terdaftar'], 409);
        }
        $stmt = $pdo->prepare("INSERT INTO devices (id, name, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$device_id, $name, $user_id]);
        // Tambahkan juga ke realtime_data agar ada barisnya
        $stmt2 = $pdo->prepare("INSERT IGNORE INTO realtime_data (device_id) VALUES (?)");
        $stmt2->execute([$device_id]);
        sendJson(['status' => 'added', 'message' => 'Perangkat berhasil ditambahkan']);
    }

    if ($action === 'delete') {
        if (empty($device_id)) {
            sendJson(['error' => 'ID perangkat harus diisi'], 400);
        }
        $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
        $stmt->execute([$device_id]);
        // Hapus juga dari realtime_data
        $stmt2 = $pdo->prepare("DELETE FROM realtime_data WHERE device_id = ?");
        $stmt2->execute([$device_id]);
        sendJson(['status' => 'deleted', 'message' => 'Perangkat berhasil dihapus']);
    }

    sendJson(['error' => 'Aksi tidak dikenal'], 400);
}

sendJson(['error' => 'Method tidak didukung'], 405);
?>