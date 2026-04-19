<?php
// test_dj.php - Script para probar la inserción en modo DJ

$host = "localhost";
$user = "hayel";
$pass = "Terminus10***";
$db = "tlaxcalitabeach";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

echo "<h2>Test de Inserción DJ</h2>";

// 1. Verificar configuración DJ
$result = $conn->query("SELECT config_value FROM sistema_config WHERE config_key = 'dj_activo'");
$row = $result->fetch_assoc();
$dj_activo = $row['config_value'] ?? '0';

echo "Modo DJ activo: " . ($dj_activo == '1' ? 'SÍ' : 'NO') . "<br>";

// 2. Verificar tabla canciones_dj
$result = $conn->query("SHOW COLUMNS FROM canciones_dj");
echo "<h3>Estructura de tabla canciones_dj:</h3>";
while ($col = $result->fetch_assoc()) {
    echo $col['Field'] . " (" . $col['Type'] . ")<br>";
}

// 3. Contar registros actuales
$result = $conn->query("SELECT COUNT(*) as total FROM canciones_dj");
$row = $result->fetch_assoc();
echo "<h3>Total registros en canciones_dj: " . $row['total'] . "</h3>";

// 4. Probar inserción directa
$sql_test = "INSERT INTO canciones_dj 
            (nombre, artista, url, email_usuario, ip_usuario, hora, fecha, estado, dj_id, fecha_solicitud, admin_agregada) 
            VALUES ('Test Song', 'Test Artist', 'spotify:track:12345', 'test@test.com', '127.0.0.1', '12:00:00', CURDATE(), 'pendiente', 1, NOW(), 0)";

if ($conn->query($sql_test)) {
    echo "<p style='color: green;'>✅ Inserción de prueba EXITOSA</p>";
    
    // Mostrar último registro
    $result = $conn->query("SELECT * FROM canciones_dj ORDER BY id DESC LIMIT 1");
    $row = $result->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ Error en inserción: " . $conn->error . "</p>";
}

$conn->close();
?>