<?php
// api/get_analytics.php
require_once 'config.php';

// ============================================================
// 1. Validasi Input
// ============================================================
$device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Batasi limit untuk performa
if ($limit > 5000) $limit = 5000;
if ($limit < 1) $limit = 1;

// ============================================================
// 2. Query Telemetry
// ============================================================
$sql = "
    SELECT 
        id,
        lat, 
        lng, 
        speed, 
        fuel_level,
        flow_rate, 
        fuel_consumption,
        engine_status, 
        solenoid_state, 
        `timestamp`,
        FROM_UNIXTIME(`timestamp`) as time_str
    FROM telemetry
    WHERE device_id = ?
";

$params = [$device_id];

// Filter tanggal jika ada
if (!empty($start_date) && !empty($end_date)) {
    // Pastikan format tanggal valid
    $start_ts = strtotime($start_date . ' 00:00:00');
    $end_ts = strtotime($end_date . ' 23:59:59');
    if ($start_ts && $end_ts && $start_ts <= $end_ts) {
        $sql .= " AND `timestamp` BETWEEN ? AND ?";
        $params[] = $start_ts;
        $params[] = $end_ts;
    } else {
        sendJson(['error' => 'Invalid date range'], 400);
    }
} else {
    // Default: 30 hari terakhir
    $sql .= " AND `timestamp` > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))";
}

$sql .= " ORDER BY `timestamp` ASC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

// ============================================================
// 3. Query Alerts (dengan filter user jika tersedia)
// ============================================================
$sqlAlert = "
    SELECT 
        id, 
        type, 
        message, 
        lat, 
        lng, 
        is_resolved, 
        created_at,
        device_id
    FROM alerts
    WHERE device_id = ?
";
$alertParams = [$device_id];

// Jika user_id diberikan, filter berdasarkan user_id (asumsi ada kolom user_id di alerts)
if ($user_id > 0) {
    // Cek apakah tabel alerts punya kolom user_id
    // Jika belum, tambahkan atau abaikan
    // $sqlAlert .= " AND user_id = ?";
    // $alertParams[] = $user_id;
}

if (!empty($start_date) && !empty($end_date)) {
    $start_ts = strtotime($start_date . ' 00:00:00');
    $end_ts = strtotime($end_date . ' 23:59:59');
    if ($start_ts && $end_ts) {
        $sqlAlert .= " AND UNIX_TIMESTAMP(created_at) BETWEEN ? AND ?";
        $alertParams[] = $start_ts;
        $alertParams[] = $end_ts;
    }
}

$sqlAlert .= " ORDER BY created_at DESC LIMIT 100";

$stmtAlert = $pdo->prepare($sqlAlert);
$stmtAlert->execute($alertParams);
$alerts = $stmtAlert->fetchAll();

// ============================================================
// 4. Hitung Statistik
// ============================================================
$total_dist = 0;
$total_fuel = 0;
$total_fuel_original = 0; // untuk fallback
$theft = 0;
$speeding = 0;
$geofence = 0;

foreach ($alerts as $a) {
    $type = $a['type'] ?? '';
    if ($type === 'theft') $theft++;
    elseif ($type === 'speeding') $speeding++;
    elseif ($type === 'geofence') $geofence++;
}

// Hitung jarak dan konsumsi dari history
if (count($history) > 1) {
    // Ambil fuel_consumption dari titik pertama dan terakhir
    $first_fuel = (float) ($history[0]['fuel_consumption'] ?? 0);
    $last_fuel = (float) ($history[count($history)-1]['fuel_consumption'] ?? 0);
    $total_fuel = abs($last_fuel - $first_fuel);

    // Jika tidak ada fuel_consumption, gunakan fuel_level untuk estimasi
    if ($total_fuel == 0 && isset($history[0]['fuel_level']) && isset($history[count($history)-1]['fuel_level'])) {
        $first_level = (float) $history[0]['fuel_level'];
        $last_level = (float) $history[count($history)-1]['fuel_level'];
        // Asumsi kapasitas tangki 100L (atau bisa disesuaikan)
        $tank_capacity = 100;
        $total_fuel = abs($last_level - $first_level) / 100 * $tank_capacity;
    }

    // Hitung jarak menggunakan Haversine
    for ($i = 1; $i < count($history); $i++) {
        $lat1 = (float) ($history[$i-1]['lat'] ?? 0);
        $lon1 = (float) ($history[$i-1]['lng'] ?? 0);
        $lat2 = (float) ($history[$i]['lat'] ?? 0);
        $lon2 = (float) ($history[$i]['lng'] ?? 0);
        
        if ($lat1 == 0 && $lon1 == 0 && $lat2 == 0 && $lon2 == 0) continue;
        
        $total_dist += haversine($lat1, $lon1, $lat2, $lon2);
    }
}

$efficiency = ($total_fuel > 0) ? $total_dist / $total_fuel : 0;

// ============================================================
// 5. Kirim Response
// ============================================================
sendJson([
    'device_id' => $device_id,
    'history' => $history,
    'alerts' => $alerts,
    'summary' => [
        'total_distance_km' => round($total_dist, 2),
        'total_fuel_consumed_l' => round($total_fuel, 2),
        'efficiency_km_per_l' => round($efficiency, 2),
        'theft_alerts' => $theft,
        'speeding_alerts' => $speeding,
        'geofence_alerts' => $geofence,
        'total_alerts' => count($alerts),
        'data_points' => count($history),
        'date_range' => [
            'start' => !empty($start_date) ? $start_date : null,
            'end' => !empty($end_date) ? $end_date : null
        ]
    ]
]);

// ============================================================
// Fungsi Haversine (jika belum ada di config)
// ============================================================
function haversine($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earthRadius * $c;
}
?>
