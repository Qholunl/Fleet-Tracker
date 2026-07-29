<?php
// api/register.php
require_once 'config.php';

$input = getInput();
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($name) || empty($email) || empty($username) || empty($password)) {
    sendJson(['status' => 'error', 'message' => 'Semua field harus diisi'], 400);
}
if (strlen($password) < 6) {
    sendJson(['status' => 'error', 'message' => 'Password minimal 6 karakter'], 400);
}

// Cek duplikat email atau username
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    sendJson(['status' => 'error', 'message' => 'Email atau username sudah terdaftar'], 409);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, username, password_hash) VALUES (?, ?, ?, ?)");
if ($stmt->execute([$name, $email, $username, $hashed])) {
    $userId = $pdo->lastInsertId();
    sendJson([
        'status' => 'success',
        'user_id' => $userId,
        'message' => 'Registrasi berhasil'
    ]);
} else {
    sendJson(['status' => 'error', 'message' => 'Gagal menyimpan data'], 500);
}
?>