<?php
// api/settings.php
// Endpoint untuk menyimpan dan mengambil pengaturan user

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Ambil user_id dari GET (untuk GET dan DELETE) atau dari POST body
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Untuk POST, user_id bisa juga ada di body JSON
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['user_id'])) {
        $user_id = intval($input['user_id']);
    }
}

// ============================================================
// GET: Ambil pengaturan user
// ============================================================
if ($method === 'GET') {
    if (!$user_id) {
        sendJson(['error' => 'user_id required'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();

    if ($settings) {
        sendJson($settings);
    } else {
        // Tidak ditemukan, kirim status not_found agar frontend menggunakan default
        sendJson(['status' => 'not_found', 'message' => 'Settings not found for this user'], 404);
    }
}

// ============================================================
// POST: Simpan atau update pengaturan
// ============================================================
if ($method === 'POST') {
    if (!$user_id) {
        sendJson(['error' => 'user_id required'], 400);
    }

    // Kolom yang bisa di-update (sesuai tabel settings)
    $fields = [
        'full_name', 'email', 'phone', 'timezone', 'pref_lang', 'avatar',
        'twofa', 'speed_limit', 'service_interval', 'solenoid_lock', 'fuel_drop_threshold',
        'notif_whatsapp', 'notif_sound', 'notif_email',
        'alert_speeding', 'alert_geofence', 'alert_fuel_theft', 'alert_offline',
        'api_base', 'baud_rate', 'polling_interval', 'webhook_url', 'json_payload'
    ];

    // Ambil nilai dari input, default null jika tidak ada
    $values = [];
    foreach ($fields as $f) {
        $values[$f] = isset($input[$f]) ? $input[$f] : null;
    }

    // Cek apakah sudah ada data untuk user ini
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // UPDATE
        $setParts = [];
        $params = [];
        foreach ($fields as $f) {
            $setParts[] = "$f = ?";
            $params[] = $values[$f];
        }
        $params[] = $user_id; // untuk WHERE
        $sql = "UPDATE settings SET " . implode(', ', $setParts) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // INSERT
        $columns = implode(', ', $fields);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $params = [$user_id];
        foreach ($fields as $f) {
            $params[] = $values[$f];
        }
        $sql = "INSERT INTO settings (user_id, $columns) VALUES (?, $placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    sendJson(['status' => 'success', 'message' => 'Settings saved']);
}

// ============================================================
// DELETE: Hapus pengaturan user (reset ke default)
// ============================================================
if ($method === 'DELETE') {
    if (!$user_id) {
        sendJson(['error' => 'user_id required'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM settings WHERE user_id = ?");
    $stmt->execute([$user_id]);

    sendJson(['status' => 'success', 'message' => 'Settings reset to default']);
}

// ============================================================
// Method tidak didukung
// ============================================================
sendJson(['error' => 'Method not allowed'], 405);
?>