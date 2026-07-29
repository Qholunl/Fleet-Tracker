<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=UTF-8");

include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// 1. KETIKA ESP32 MENGIRIM DATA (POST)
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['device_id'], $data['lat'], $data['lng'])) {
        $device_id        = $conn->real_escape_string($data['device_id']);
        $lat              = $conn->real_escape_string($data['lat']);
        $lng              = $conn->real_escape_string($data['lng']);
        $speed            = isset($data['speed']) ? $conn->real_escape_string($data['speed']) : 0;
        $flow_rate        = isset($data['flow_rate']) ? $conn->real_escape_string($data['flow_rate']) : 0;
        $fuel             = isset($data['fuel']) ? $conn->real_escape_string($data['fuel']) : 0;
        $fuel_consumption = isset($data['fuel_consumption']) ? $conn->real_escape_string($data['fuel_consumption']) : 0;
        $engine_status    = isset($data['engine_status']) ? $conn->real_escape_string($data['engine_status']) : 0;

        $sql = "INSERT INTO telemetry (device_id, lat, lng, speed, flow_rate, fuel, fuel_consumption, engine_status) 
                VALUES ('$device_id', '$lat', '$lng', '$speed', '$flow_rate', '$fuel', '$fuel_consumption', '$engine_status')";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
    }
}

// 2. KETIKA DASHBOARD HTML/JS MEMINTA DATA (GET)
elseif ($method === 'GET') {
    $device_id = isset($_GET['device_id']) ? $conn->real_escape_string($_GET['device_id']) : '';

    if (!empty($device_id)) {
        $sql = "SELECT * FROM telemetry WHERE device_id = '$device_id' ORDER BY id DESC LIMIT 20";
    } else {
        $sql = "SELECT * FROM telemetry ORDER BY id DESC LIMIT 20";
    }

    $result = $conn->query($sql);
    $rows = array();

    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    echo json_encode($rows);
}

$conn->close();
?>