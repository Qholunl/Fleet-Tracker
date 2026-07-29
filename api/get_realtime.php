<?php
/**
 * api.php — Endpoint untuk ESP32 dan dashboard (jika masih menggunakan PHP)
 * 
 * SEKARANG: Menggunakan Supabase sebagai database, bukan MySQL.
 * 
 * Cara penggunaan:
 * - POST /api.php : ESP32 mengirim data JSON, disimpan ke tabel vehicle_data di Supabase.
 * - GET /api.php?device_id=xxx : Mengambil 20 data terakhir untuk device tertentu.
 * 
 * Catatan: Untuk keamanan, gunakan SUPABASE_SERVICE_ROLE_KEY di environment variable.
 */

// ================================================================
// 1. KONFIGURASI SUPABASE (ubah sesuai project Anda)
// ================================================================
$SUPABASE_URL = 'https://your-project.supabase.co'; // Ganti dengan URL Supabase Anda
$SUPABASE_KEY  = 'your-service-role-key'; // Ganti dengan service role key (jangan bocorkan)

// ================================================================
// 2. FUNGSI REQUEST KE SUPABASE REST API
// ================================================================
function supabase_request($method, $path, $body = null) {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $url = rtrim($SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/');
    
    $ch = curl_init($url);
    $headers = [
        'apikey: ' . $SUPABASE_KEY,
        'Authorization: Bearer ' . $SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpcode,
        'body' => json_decode($response, true)
    ];
}

// ================================================================
// 3. HEADER CORS
// ================================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=UTF-8");

// ================================================================
// 4. PROSES REQUEST
// ================================================================
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // --- ESP32 mengirim data ---
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['device_id'], $data['lat'], $data['lng'])) {
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap (device_id, lat, lng wajib)"]);
        exit;
    }
    
    // Mapping ke kolom Supabase (sesuai skema vehicle_data)
    $record = [
        'device_id'        => $data['device_id'],
        'lat'              => (float) $data['lat'],
        'lng'              => (float) $data['lng'],
        'speed'            => (float) ($data['speed'] ?? 0),
        'flow_rate'        => (float) ($data['flow_rate'] ?? 0),
        'fuel_level'       => (float) ($data['fuel'] ?? 0),
        'fuel_consumption' => (float) ($data['fuel_consumption'] ?? 0),
        'engine_status'    => (int) ($data['engine_status'] ?? 0),
        'recorded_at'      => date('Y-m-d H:i:s')
    ];
    
    $result = supabase_request('POST', 'vehicle_data', $record);
    
    if ($result['code'] >= 200 && $result['code'] < 300) {
        echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menyimpan ke Supabase",
            "detail" => $result['body'] ?? null
        ]);
    }
    
} elseif ($method === 'GET') {
    // --- Dashboard / Analytics meminta data ---
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
    
    $path = 'vehicle_data?select=*&order=recorded_at.desc&limit=20';
    if (!empty($device_id)) {
        $path .= '&device_id=eq.' . urlencode($device_id);
    }
    
    $result = supabase_request('GET', $path);
    
    if ($result['code'] == 200) {
        echo json_encode($result['body']);
    } else {
        echo json_encode([]);
    }
    
} else {
    // Method tidak diizinkan
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
