<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'user_email' => $_SESSION['user_email'] ?? null,
    'session_data' => $_SESSION
]);
?>