<?php
// api/save_geofence.php
require_once 'config.php';

// Ambil input JSON
$input = getInput();

// Validasi input
if (!$input || !isset($input['user_id'], $input['name'], $input['coordinates'])) {
    sendJson(['error' => 'Invalid data: user_id, name, dan coordinates wajib'], 400);
}

// Ambil data
$user_id = $input['user_id']; // UUID string, bukan int
$name = trim($input['name']);
$coordinates = $input['coordinates']; // array of {lat, lng}
$type = $input['type'] ?? 'polygon';
$device_id = $input['device_id'] ?? null;

// Validasi koordinat
if (!is_array($coordinates) || count($coordinates) < 3) {
    sendJson(['error' => 'Koordinat harus array dengan minimal 3 titik'], 400);
}

// Data untuk Supabase
$payload = [
    'user_id' => $user_id,
    'name' => $name,
    'coordinates' => $coordinates,
    'type' => $type
];

if ($device_id) {
    $payload['device_id'] = $device_id;
}

// Simpan ke Supabase
$result = supabaseRequest('POST', 'geofences', $payload);

if ($result['status'] >= 200 && $result['status'] < 300) {
    sendJson([
        'status' => 'success',
        'id' => $result['body'][0]['id'] ?? null,
        'data' => $result['body']
    ]);
} else {
    sendJson([
        'error' => 'Gagal menyimpan geofence',
        'detail' => $result['body']
    ], $result['status'] >= 400 ? $result['status'] : 500);
}
?>
