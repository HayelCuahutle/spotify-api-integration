<?php
// test_simple.php - Prueba SIN session_start problemático
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 TEST SIMPLE DE HUELLAS</h2>";

$host = "localhost";
$user = "hayel";
$pass = "Terminus10***";
$db = "tlaxcalitabeach";

// Conectar
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("❌ Error conexión: " . $conn->connect_error);
}
echo "✅ Conexión exitosa<br>";

// 1. Crear huella de prueba
$huella_test = md5('test_' . time() . rand(1000, 9999));
echo "Huella test: " . $huella_test . "<br>";

// 2. Insertar en huellas_usuarios (estructura simple)
$sql = "INSERT INTO huellas_usuarios (huella_hash, ip_cliente, user_agent) 
        VALUES (?, '127.0.0.1', 'Test Agent')";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $huella_test);
    if ($stmt->execute()) {
        echo "✅ Inserción exitosa en huellas_usuarios<br>";
    } else {
        echo "❌ Error inserción: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "❌ Error preparando statement: " . $conn->error . "<br>";
}

// 3. Verificar tabla canciones_diarias
echo "<h3>Insertar canción de prueba:</h3>";
$sql = "INSERT INTO canciones_diarias (nombre, artista, uri, hora, fecha, email_usuario, ip_usuario) 
        VALUES ('Test Song', 'Test Artist', 'spotify:track:test123', CURTIME(), CURDATE(), 'test@test.com', '127.0.0.1')";

if ($conn->query($sql) === TRUE) {
    echo "✅ Canción insertada (ID: " . $conn->insert_id . ")<br>";
} else {
    echo "❌ Error canción: " . $conn->error . "<br>";
}

// 4. Ver registros
echo "<h3>Registros en huellas_usuarios:</h3>";
$result = $conn->query("SELECT id, huella_hash, email, ip_cliente FROM huellas_usuarios ORDER BY id DESC LIMIT 3");
if ($result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Huella</th><th>Email</th><th>IP</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . substr($row['huella_hash'], 0, 10) . "...</td>";
        echo "<td>" . ($row['email'] ?: 'NULL') . "</td>";
        echo "<td>" . $row['ip_cliente'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>