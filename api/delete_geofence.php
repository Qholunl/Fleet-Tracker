<?php
require 'config.php';

$input = getInput();
if (!$input || !isset($input['id'])) {
    sendJson(['error' => 'Invalid data'], 400);
}

$id = (int) $input['id'];

$stmt = $pdo->prepare("DELETE FROM geofences WHERE id = ?");
if ($stmt->execute([$id])) {
    if ($stmt->rowCount() > 0) {
        sendJson(['status' => 'deleted']);
    } else {
        sendJson(['error' => 'Geofence not found'], 404);
    }
} else {
    sendJson(['error' => 'Delete failed'], 500);
}
?>
