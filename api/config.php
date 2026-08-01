<?php
// api/config.php - Konfigurasi untuk Supabase

// Set CORS agar frontend bisa mengakses
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey');

// Tangani preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Konfigurasi Supabase
define('SUPABASE_URL', 'https://gapcqlcqowmhcpzbqygr.supabase.co');
define('SUPABASE_ANON_KEY', 'sb_secret_ruYKHGf-xjO-hGnGaM8JsA_fQ2c53TD');
// Jika Anda memiliki service_role key untuk operasi admin (opsional)
// define('SUPABASE_SERVICE_KEY', 'your-service-role-key');

// Fungsi untuk mengirim response JSON
function sendJson($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Fungsi untuk mengambil input JSON dari request body
function getInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

// Fungsi untuk melakukan request ke Supabase REST API
function supabaseRequest($method, $endpoint, $data = null, $headers = []) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    // Default headers
    $defaultHeaders = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY
    ];
    
    // Gabungkan dengan headers tambahan
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => json_decode($response, true)
    ];
}
?>
