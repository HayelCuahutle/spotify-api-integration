<?php
// debug_safari.php - Script de diagnóstico
header('Content-Type: application/json');

$data = [
    'server' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'https' => isset($_SERVER['HTTPS']) ? 'Sí' : 'No',
    ],
    'client' => [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'No detectado',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'is_safari' => (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Safari') !== false && 
                        strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Chrome') === false) ? 'Sí' : 'No',
    ],
    'cookies' => $_COOKIE,
    'session' => session_status() === PHP_SESSION_ACTIVE ? 'Activa' : 'Inactiva',
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'ok'
];

echo json_encode($data, JSON_PRETTY_PRINT);