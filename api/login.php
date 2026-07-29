<?php
// api/login.php
require_once 'config.php';

$input = getInput();
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    sendJson(['status' => 'error', 'message' => 'Email dan password harus diisi'], 400);
}

$stmt = $pdo->prepare("SELECT id, name, email, username, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    sendJson(['status' => 'error', 'message' => 'Email atau password salah'], 401);
}

if (!password_verify($password, $user['password_hash'])) {
    sendJson(['status' => 'error', 'message' => 'Email atau password salah'], 401);
}

sendJson([
    'status' => 'success',
    'user_id' => $user['id'],
    'name' => $user['name'],
    'username' => $user['username'],
    'message' => 'Login berhasil'
]);
?>