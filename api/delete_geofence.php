<?php
// api/delete_geofence.php
require_once 'config.php';

// Ambil input JSON
$input = getInput();

if (!$input || !isset($input['id'])) {
    sendJson(['error' => 'id geofence diperlukan'], 400);
}

$id = $input['id']; // Bisa int atau string, Supabase mendukung keduanya

// Pastikan user_id dikirim untuk keamanan (agar hanya pemilik yang bisa hapus)
$user_id = $input['user_id'] ?? null;

if (!$user_id) {
    sendJson(['error' => 'user_id diperlukan'], 401);
}

// Hapus dengan filter user_id untuk keamanan
$endpoint = "geofences?id=eq.$id&user_id=eq.$user_id";
$result = supabaseRequest('DELETE', $endpoint);

if ($result['status'] >= 200 && $result['status'] < 300) {
    // Supabase DELETE mengembalikan status 204 jika berhasil
    sendJson(['status' => 'deleted']);
} else {
    sendJson([
        'error' => 'Gagal menghapus geofence',
        'detail' => $result['body']
    ], $result['status'] >= 400 ? $result['status'] : 500);
}
?>
