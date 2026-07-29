<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=UTF-8");

// Tangani preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// 1. ESP32 MENGIRIM DATA (POST)
// ============================================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    // Validasi minimal
    if (!isset($input['device_id']) || !isset($input['lat']) || !isset($input['lng'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap (device_id, lat, lng required)"]);
        exit;
    }

    $device_id = trim($input['device_id']);
    if (empty($device_id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "device_id tidak boleh kosong"]);
        exit;
    }

    // Ambil data dengan default
    $lat              = (float) $input['lat'];
    $lng              = (float) $input['lng'];
    $speed            = isset($input['speed']) ? (float) $input['speed'] : 0;
    $flow_rate        = isset($input['flow_rate']) ? (float) $input['flow_rate'] : 0;
    $fuel             = isset($input['fuel']) ? (float) $input['fuel'] : 0;
    $fuel_consumption = isset($input['fuel_consumption']) ? (float) $input['fuel_consumption'] : 0;
    $engine_status    = isset($input['engine_status']) ? (int) $input['engine_status'] : 0;
    $timestamp        = isset($input['timestamp']) ? (int) $input['timestamp'] : time();

    // Prepared Statement untuk keamanan
    $stmt = $conn->prepare("INSERT INTO telemetry 
        (device_id, lat, lng, speed, flow_rate, fuel, fuel_consumption, engine_status, timestamp) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "sddddddii", 
        $device_id, 
        $lat, 
        $lng, 
        $speed, 
        $flow_rate, 
        $fuel, 
        $fuel_consumption, 
        $engine_status,
        $timestamp
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan: " . $stmt->error]);
    }
    $stmt->close();
}

// ============================================================
// 2. DASHBOARD MEMINTA DATA (GET)
// ============================================================
elseif ($method === 'GET') {
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
    $limit     = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // Bangun query dengan filter
    $sql = "SELECT * FROM telemetry WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($device_id)) {
        $sql .= " AND device_id = ?";
        $params[] = $device_id;
        $types .= "s";
    }

    if (!empty($start_date)) {
        $sql .= " AND timestamp >= ?";
        $params[] = strtotime($start_date . " 00:00:00");
        $types .= "i";
    }

    if (!empty($end_date)) {
        $sql .= " AND timestamp <= ?";
        $params[] = strtotime($end_date . " 23:59:59");
        $types .= "i";
    }

    $sql .= " ORDER BY timestamp DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    
    // Bind parameter jika ada
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // Konversi timestamp ke format yang lebih mudah
        $row['timestamp'] = (int) $row['timestamp'];
        $row['date'] = date('Y-m-d H:i:s', $row['timestamp']);
        $rows[] = $row;
    }

    echo json_encode($rows);
    $stmt->close();
}

// ============================================================
// 3. METODE LAIN (PUT, DELETE, dll) — TIDAK DIDUKUNG
// ============================================================
else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}

$conn->close();
?>
