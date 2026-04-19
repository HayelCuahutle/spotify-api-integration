<?php
// test_add_song.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Simular datos POST
$_POST = [
    'trackUri' => 'spotify:track:4cOdK2wGLETKBW3PvgPWqT', // ID de canción de prueba
    'trackName' => 'Canción de prueba',
    'trackArtist' => 'Artista de prueba',
    'userEmail' => 'test@example.com'
];

// Incluir addToPlaylist.php
include 'addToPlaylist.php';
?>