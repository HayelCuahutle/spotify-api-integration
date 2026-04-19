<?php
session_start();
header('Content-Type: application/json');

$email = $_POST['email'] ?? '';

if (!empty($email)) {
    $_SESSION['user_email'] = $email;
    echo json_encode([
        'success' => true,
        'message' => 'Email actualizado',
        'email' => $email,
        'session_id' => session_id()
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No se recibió email'
    ]);
}
?>