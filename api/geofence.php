<?php
// api/geofences.php
require_once 'config.php';

// Tangani preflight OPTIONS request (jika config.php belum menangani)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// 1. GET — Ambil daftar geofence
// ============================================================
if ($method === 'GET') {
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    $sql = "SELECT * FROM geofences WHERE 1=1";
    $params = [];

    if (!empty($device_id)) {
        $sql .= " AND (device_id = ? OR device_id IS NULL)";
        $params[] = $device_id;
    }

    // Jika ada user_id, filter berdasarkan kepemilikan
    if ($user_id > 0) {
        $sql .= " AND user_id = ?";
        $params[] = $user_id;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $geofences = $stmt->fetchAll();

    // Decode coordinates JSON
    foreach ($geofences as &$g) {
        $g['coordinates'] = json_decode($g['coordinates'], true);
        if (!is_array($g['coordinates'])) {
            $g['coordinates'] = []; // fallback jika JSON invalid
        }
    }

    sendJson($geofences);
}

// ============================================================
// 2. POST — Simpan geofence baru
// ============================================================
if ($method === 'POST') {
    $input = getInput();

    $device_id = isset($input['device_id']) ? trim($input['device_id']) : null;
    $user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    $name = isset($input['name']) ? trim($input['name']) : '';
    $coordinates = $input['coordinates'] ?? [];
    $type = isset($input['type']) ? trim($input['type']) : 'polygon';
    $center_lat = isset($input['center_lat']) ? (float) $input['center_lat'] : null;
    $center_lng = isset($input['center_lng']) ? (float) $input['center_lng'] : null;
    $radius = isset($input['radius_meters']) ? (float) $input['radius_meters'] : null;

    // Validasi
    if (empty($name)) {
        sendJson(['error' => 'Nama geofence harus diisi'], 400);
    }

    if ($user_id === 0) {
        sendJson(['error' => 'user_id diperlukan untuk menyimpan geofence'], 400);
    }

    if ($type === 'polygon' && (empty($coordinates) || count($coordinates) < 3)) {
        sendJson(['error' => 'Polygon membutuhkan minimal 3 titik koordinat'], 400);
    }

    if ($type === 'circle' && ($center_lat === null || $center_lng === null || $radius === null || $radius <= 0)) {
        sendJson(['error' => 'Lingkaran membutuhkan center_lat, center_lng, dan radius_meters > 0'], 400);
    }

    // Jika polygon, koordinat harus array of {lat, lng}
    if ($type === 'polygon' && !is_array($coordinates)) {
        sendJson(['error' => 'Koordinat harus berupa array'], 400);
    }

    // Encode coordinates ke JSON
    $coordJson = json_encode($coordinates);
    if ($coordJson === false) {
        sendJson(['error' => 'Gagal encode coordinates'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO geofences 
        (user_id, device_id, name, coordinates, type, center_lat, center_lng, radius_meters)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $user_id,
        $device_id,
        $name,
        $coordJson,
        $type,
        $center_lat,
        $center_lng,
        $radius
    ]);

    sendJson([
        'status' => 'saved',
        'id' => $pdo->lastInsertId()
    ]);
}

// ============================================================
// 3. DELETE — Hapus geofence
// ============================================================
if ($method === 'DELETE') {
    // Cek apakah id dikirim via query string atau body
    $input = getInput();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($input['id']) ? (int) $input['id'] : 0);
    $user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;

    if ($id === 0) {
        sendJson(['error' => 'id geofence diperlukan'], 400);
    }

    if ($user_id === 0) {
        sendJson(['error' => 'user_id diperlukan untuk menghapus geofence'], 401);
    }

    // Hapus hanya jika geofence milik user tersebut
    $stmt = $pdo->prepare("DELETE FROM geofences WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);

    if ($stmt->rowCount() > 0) {
        sendJson(['status' => 'deleted']);
    } else {
        sendJson(['error' => 'Geofence tidak ditemukan atau bukan milik Anda'], 404);
    }
}

// ============================================================
// 4. Method tidak dikenal
// ============================================================
sendJson(['error' => 'Method not allowed'], 405);
?>
