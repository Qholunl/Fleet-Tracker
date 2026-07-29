<?php
// api/get_history.php
require_once 'config.php';

// ============================================================
// 1. Ambil parameter
// ============================================================
$device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Validasi device_id
if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Batasi limit maksimal 2000 agar tidak overload
if ($limit > 2000) {
    $limit = 2000;
}

// ============================================================
// 2. Bangun query dengan filter tanggal (opsional)
// ============================================================
// Cek apakah tabel history ada, jika tidak gunakan telemetry
// Kita gunakan telemetry sebagai sumber utama (karena history juga ada, tapi telemetry lebih lengkap)
// Untuk kompatibilitas, kita gunakan UNION atau prioritas ke history jika ada

$sql = "
    SELECT 
        lat, 
        lng, 
        speed, 
        flow_rate, 
        fuel_consumption, 
        engine_status,
        fuel,
        `timestamp`,
        FROM_UNIXTIME(`timestamp`) as time_str
    FROM telemetry
    WHERE device_id = ?
";

$params = [$device_id];

// Filter tanggal (optional)
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND DATE(FROM_UNIXTIME(`timestamp`)) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $sql .= " AND DATE(FROM_UNIXTIME(`timestamp`)) >= ?";
    $params[] = $start_date;
} elseif (!empty($end_date)) {
    $sql .= " AND DATE(FROM_UNIXTIME(`timestamp`)) <= ?";
    $params[] = $end_date;
} else {
    // Default: 30 hari terakhir
    $sql .= " AND FROM_UNIXTIME(`timestamp`) > DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY `timestamp` DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ============================================================
// 3. Jika tidak ada data di telemetry, coba ambil dari history
// ============================================================
if (empty($rows)) {
    // Cek apakah tabel history ada
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'history'");
        if ($check->rowCount() > 0) {
            $sqlHistory = "
                SELECT 
                    lat, lng, speed, flow_rate, fuel_consumption, 
                    engine_status, fuel, `timestamp`,
                    FROM_UNIXTIME(`timestamp`) as time_str
                FROM history
                WHERE device_id = ?
            ";
            $paramsHistory = [$device_id];
            
            if (!empty($start_date) && !empty($end_date)) {
                $sqlHistory .= " AND DATE(FROM_UNIXTIME(`timestamp`)) BETWEEN ? AND ?";
                $paramsHistory[] = $start_date;
                $paramsHistory[] = $end_date;
            } elseif (!empty($start_date)) {
                $sqlHistory .= " AND DATE(FROM_UNIXTIME(`timestamp`)) >= ?";
                $paramsHistory[] = $start_date;
            } elseif (!empty($end_date)) {
                $sqlHistory .= " AND DATE(FROM_UNIXTIME(`timestamp`)) <= ?";
                $paramsHistory[] = $end_date;
            }
            
            $sqlHistory .= " ORDER BY `timestamp` DESC LIMIT ?";
            $paramsHistory[] = $limit;
            
            $stmtHistory = $pdo->prepare($sqlHistory);
            $stmtHistory->execute($paramsHistory);
            $rows = $stmtHistory->fetchAll();
        }
    } catch (PDOException $e) {
        // Tabel history mungkin tidak ada, abaikan
    }
}

// ============================================================
// 4. Format hasil (ascending untuk playback)
// ============================================================
$rows = array_reverse($rows); // Ubah ke ascending

$result = [];
foreach ($rows as $row) {
    $result[] = [
        'lat' => (float) ($row['lat'] ?? 0),
        'lng' => (float) ($row['lng'] ?? 0),
        'speed' => (float) ($row['speed'] ?? 0),
        'flow_rate' => (float) ($row['flow_rate'] ?? 0),
        'fuel_consumption' => (float) ($row['fuel_consumption'] ?? 0),
        'engine_status' => (int) ($row['engine_status'] ?? 0),
        'fuel' => (float) ($row['fuel'] ?? 100),
        'timestamp' => (int) ($row['timestamp'] ?? 0),
        'timeStr' => $row['time_str'] ?? ''
    ];
}

// ============================================================
// 5. Kirim response
// ============================================================
sendJson($result);
?>
