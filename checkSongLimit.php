<?php
$host = "localhost";
$user = "hayel";
$pass = "Terminus10***";
$db   = "tlaxcalitabeach";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Error de conexión"]));
}

// Obtener IP del usuario
$ip_address = $_SERVER['REMOTE_ADDR'];

// Verificar si puede agregar canción
$sql = "SELECT ultima_cancion, bloqueado_until FROM control_canciones 
        WHERE ip_address = ? ORDER BY ultima_cancion DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$result = $stmt->get_result();

$puede_agregar = true;
$tiempo_restante = 0;
$mensaje = "";

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ultima_cancion = strtotime($row['ultima_cancion']);
    $ahora = time();
    $diferencia_segundos = ($ahora - $ultima_cancion);
    
    // Verificar si ha pasado 1 hora (3600 segundos)
    if ($diferencia_segundos < 3600) {
        $puede_agregar = false;
        $tiempo_restante = 3600 - $diferencia_segundos;
        $minutos = ceil($tiempo_restante / 60);
        $mensaje = "Debes esperar " . $minutos . " minuto" . ($minutos > 1 ? "s" : "") . " para agregar otra canción";
    }
}

$conn->close();

echo json_encode([
    "puede_agregar" => $puede_agregar,
    "tiempo_restante" => $tiempo_restante,
    "mensaje" => $mensaje,
    "ip" => $ip_address
]);
?>