<?php
header('Content-Type: application/json; charset=utf-8');

// ------------------- CONEXIÓN A DB -------------------
$host = "localhost";
$user = "hayel";
$pass = "Terminus10***";
$db   = "tlaxcalitabeach";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode([
        "success" => false, 
        "message" => "Error al conectar a DB: ".$conn->connect_error,
        "songs" => []
    ]);
    exit;
}

$conn->set_charset("utf8mb4");

// ------------------- OBTENER CANCIONES EXISTENTES -------------------
$sql = "SELECT nombre, artista, uri, hora, fecha, email_usuario, ip_usuario 
        FROM canciones_diarias 
        WHERE fecha = CURDATE() 
        ORDER BY hora DESC";

$result = $conn->query($sql);

$songs = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $songs[] = [
            "name" => $row["nombre"],
            "artist" => $row["artista"],
            "uri" => $row["uri"],
            "time" => substr($row["hora"], 0, 5), // Solo hora:minutos
            "date" => $row["fecha"],
            "email" => $row["email_usuario"],
            "ip" => $row["ip_usuario"],
            "id" => strtotime($row["fecha"] . " " . $row["hora"])
        ];
    }
}

$conn->close();

echo json_encode([
    "success" => true,
    "songs" => $songs,
    "count" => count($songs),
    "today" => date('Y-m-d')
]);
?>