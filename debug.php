<?php
// debug.php - Archivo de depuración para /rating/
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_error.log');

// Crear archivo de log si no existe
$log_file = __DIR__ . '/debug_log.txt';

function debug_log($mensaje, $data = null) {
    global $log_file;
    $fecha = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $log = "[$fecha][IP: $ip] $mensaje";
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log .= " - DATA: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $log .= " - DATA: $data";
        }
    }
    $log .= "\n";
    
    file_put_contents($log_file, $log, FILE_APPEND);
}
?>