<?php
// debug_add.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>🔧 DEBUG DE addToPlaylist.php</h2>";

// Simular datos POST
$_POST = [
    'trackUri' => 'spotify:track:4cOdK2wGLETKBW3PvgPWqT',
    'trackName' => 'Canción de prueba DEBUG',
    'trackArtist' => 'Artista DEBUG',
    'userEmail' => 'test@debug.com'
];

$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Debug';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
;
session_start();

// Mostrar datos simulados
echo "<h3>Datos simulados:</h3>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h3>Ejecutando addToPlaylist.php...</h3>";
echo "<hr>";

// Incluir addToPlaylist.php
include 'addToPlaylist.php';
?>