<?php
// api/get_analytics.php
require_once 'config.php';

$device_id = $_GET['device_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$limit = intval($_GET['limit'] ?? 500);

if (empty($device_id)) {
    sendJson(['error' => 'device_id required'], 400);
}

// Query telemetry dengan filter tanggal
$sql = "
    SELECT 
        lat, lng, speed, fuel_level, flow_rate, fuel_consumption,
        engine_status, solenoid_state, `timestamp`,
        FROM_UNIXTIME(`timestamp`) as time_str
    FROM telemetry
    WHERE device_id = ?
";
$params = [$device_id];

if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND DATE(FROM_UNIXTIME(`timestamp`)) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} else {
    // Default: 30 hari terakhir
    $sql .= " AND FROM_UNIXTIME(`timestamp`) > DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY `timestamp` DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();
$history = array_reverse($history); // ascending

// Query alerts
$sqlAlert = "
    SELECT 
        id, type, message, lat, lng, is_resolved, created_at
    FROM alerts
    WHERE device_id = ?
";
$alertParams = [$device_id];
if (!empty($start_date) && !empty($end_date)) {
    $sqlAlert .= " AND DATE(created_at) BETWEEN ? AND ?";
    $alertParams[] = $start_date;
    $alertParams[] = $end_date;
}
$sqlAlert .= " ORDER BY created_at DESC LIMIT 100";
$stmtAlert = $pdo->prepare($sqlAlert);
$stmtAlert->execute($alertParams);
$alerts = $stmtAlert->fetchAll();

// Hitung statistik
$total_dist = 0;
$total_fuel = 0;
$theft = 0;
$speeding = 0;
$geofence = 0;

foreach ($alerts as $a) {
    if ($a['type'] === 'theft') $theft++;
    elseif ($a['type'] === 'speeding') $speeding++;
    elseif ($a['type'] === 'geofence') $geofence++;
}

// Hitung jarak dan konsumsi dari history
if (count($history) > 1) {
    $first_fuel = $history[0]['fuel_consumption'] ?? 0;
    $last_fuel = $history[count($history)-1]['fuel_consumption'] ?? 0;
    $total_fuel = abs($last_fuel - $first_fuel);

    for ($i = 1; $i < count($history); $i++) {
        $lat1 = floatval($history[$i-1]['lat'] ?? 0);
        $lon1 = floatval($history[$i-1]['lng'] ?? 0);
        $lat2 = floatval($history[$i]['lat'] ?? 0);
        $lon2 = floatval($history[$i]['lng'] ?? 0);
        // Haversine
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        $dist = 6371 * $c; // km
        $total_dist += $dist;
    }
}

$efficiency = ($total_fuel > 0) ? $total_dist / $total_fuel : 0;

sendJson([
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
        'data_points' => count($history)
    ]
]);
?>