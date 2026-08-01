<?php
// api/geofence.php - Router untuk operasi geofence
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET — Ambil daftar geofence
// ============================================================
if ($method === 'GET') {
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;

    if (!$user_id) {
        sendJson(['error' => 'user_id required'], 400);
    }

    $filters = ["user_id=eq.$user_id"];
    if ($device_id !== null && $device_id !== '') {
        $filters[] = "device_id=eq.$device_id";
    }

    $endpoint = "geofences?" . implode('&', $filters) . "&order=name.asc";
    $result = supabaseRequest('GET', $endpoint);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        sendJson($result['body']);
    } else {
        sendJson(['error' => 'Gagal mengambil geofence', 'detail' => $result['body']], $result['status']);
    }
}

// ============================================================
// POST — Simpan geofence baru
// ============================================================
if ($method === 'POST') {
    $input = getInput();

    $user_id = $input['user_id'] ?? null;
    $device_id = $input['device_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $coordinates = $input['coordinates'] ?? [];
    $type = $input['type'] ?? 'polygon';

    // Validasi
    if (empty($name)) {
        sendJson(['error' => 'Nama geofence harus diisi'], 400);
    }
    if (!$user_id) {
        sendJson(['error' => 'user_id diperlukan'], 400);
    }
    if ($type === 'polygon' && (!is_array($coordinates) || count($coordinates) < 3)) {
        sendJson(['error' => 'Polygon membutuhkan minimal 3 titik koordinat'], 400);
    }

    $payload = [
        'user_id' => $user_id,
        'name' => $name,
        'coordinates' => $coordinates,
        'type' => $type
    ];
    if ($device_id) $payload['device_id'] = $device_id;

    // Simpan
    $result = supabaseRequest('POST', 'geofences', $payload);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        sendJson([
            'status' => 'saved',
            'id' => $result['body'][0]['id'] ?? null,
            'data' => $result['body']
        ]);
    } else {
        sendJson(['error' => 'Gagal menyimpan geofence', 'detail' => $result['body']], $result['status']);
    }
}

// ============================================================
// DELETE — Hapus geofence
// ============================================================
if ($method === 'DELETE') {
    $input = getInput();
    $id = $input['id'] ?? null;
    $user_id = $input['user_id'] ?? null;

    if (!$id) {
        sendJson(['error' => 'id geofence diperlukan'], 400);
    }
    if (!$user_id) {
        sendJson(['error' => 'user_id diperlukan'], 401);
    }

    $endpoint = "geofences?id=eq.$id&user_id=eq.$user_id";
    $result = supabaseRequest('DELETE', $endpoint);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        sendJson(['status' => 'deleted']);
    } else {
        sendJson(['error' => 'Gagal menghapus geofence', 'detail' => $result['body']], $result['status']);
    }
}

// ============================================================
// Method tidak dikenali
// ============================================================
sendJson(['error' => 'Method not allowed'], 405);
?>
