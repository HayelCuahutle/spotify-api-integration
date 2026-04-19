<?php
$host = "localhost";
$user = "hayel"; // Cambia según tu usuario
$pass = "Terminus10***";     // Cambia según tu contraseña
$db   = "tlaxcalitabeach";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
