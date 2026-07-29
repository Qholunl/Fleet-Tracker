<?php
// api/config.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// ========== KONFIGURASI DATABASE INFINITYFREE ==========
$host = 'sql211.infinityfree.com';
$db   = 'if0_42452384_tracker';
$user = 'if0_42452384';
$pass = 'Abdillah354';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=3306";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Fungsi helper response
function sendJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
?>