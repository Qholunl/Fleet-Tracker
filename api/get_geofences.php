<?php
// api/get_geofences.php
require_once 'config.php';

// Ambil parameter
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;

if (!$user_id) {
    sendJson(['error' => 'user_id required'], 400);
}

// Bangun filter untuk Supabase
$filters = [];
$filters[] = "user_id=eq." . $user_id;

if ($device_id !== null && $device_id !== '') {
    $filters[] = "device_id=eq." . $device_id;
}

// Tambahkan filter untuk device_id NULL jika device_id tidak diberikan?
// Secara default kita ambil semua device milik user

$queryString = implode('&', $filters);
$endpoint = "geofences?$queryString&order=name.asc";

$result = supabaseRequest('GET', $endpoint);

if ($result['status'] >= 200 && $result['status'] < 300) {
    // Data sudah dalam bentuk array dari Supabase
    sendJson($result['body']);
} else {
    sendJson([
        'error' => 'Gagal mengambil geofence',
        'detail' => $result['body']
    ], $result['status'] >= 400 ? $result['status'] : 500);
}
?>
