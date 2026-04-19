<?php
// test_reproduccion_directa.php - PRUEBA MÍNIMA
header('Content-Type: text/html; charset=utf-8');

$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
$refresh_token = "AQBmXX4v5xwhvvUh0zbTQ3cQe3aVXdnWRf5nV_P6fKd3x5K143CQbzkT1paH1__62V_0WpO7_Ztt3tWexC3_-uiKacUL3-qVBwmIhOjLzEOdvAw3NImp-UsCLkUD5lT8jlM";
$playlist_id = "4DsQj6WXGKDpmX9oY3bKVz";

function getToken() {
    $ch = curl_init("https://accounts.spotify.com/api/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=refresh_token&refresh_token=" . urlencode($refresh_token));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic " . base64_encode("$client_id:$client_secret"),
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $data = json_decode($result, true);
    curl_close($ch);
    return $data['access_token'] ?? null;
}

function getDevices($token) {
    $ch = curl_init("https://api.spotify.com/v1/me/player/devices");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function playTrack($token, $device_id, $track_uri) {
    $ch = curl_init("https://api.spotify.com/v1/me/player/play?device_id=" . urlencode($device_id));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["uris" => [$track_uri]]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

$token = getToken();
$devices = getDevices($token);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Reproducción Directa</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        .device { border: 1px solid #333; padding: 10px; margin: 5px; border-radius: 5px; }
        .active { border-left: 5px solid #1DB954; }
        button { background: #1DB954; color: #000; padding: 10px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; }
        pre { background: #333; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🎯 PRUEBA DE REPRODUCCIÓN DIRECTA</h1>
    
    <h2>Dispositivos disponibles:</h2>
    <?php foreach ($devices['devices'] ?? [] as $d): ?>
    <div class="device <?php echo $d['is_active'] ? 'active' : ''; ?>">
        <strong><?php echo $d['name']; ?></strong> (<?php echo $d['type']; ?>)<br>
        ID: <?php echo $d['id']; ?><br>
        Activo: <?php echo $d['is_active'] ? 'SÍ' : 'NO'; ?><br>
        Volumen: <?php echo $d['volume_percent'] ?? '?'; ?>%
    </div>
    <?php endforeach; ?>
    
    <h2>Prueba manual:</h2>
    <form method="POST">
        <select name="device_id" required>
            <option value="">Selecciona dispositivo</option>
            <?php foreach ($devices['devices'] ?? [] as $d): ?>
            <option value="<?php echo $d['id']; ?>"><?php echo $d['name']; ?> (<?php echo $d['type']; ?>)</option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="track_uri" value="spotify:track:4cOdK2wGLETKBW3PvgPWqT" size="40">
        <button type="submit" name="play">▶️ REPRODUCIR AHORA</button>
    </form>
    
    <?php
    if (isset($_POST['play'])) {
        $code = playTrack($token, $_POST['device_id'], $_POST['track_uri']);
        echo "<h3>Resultado:</h3>";
        echo "<pre>Código HTTP: $code - " . ($code === 204 ? "✅ ÉXITO" : "❌ FALLÓ") . "</pre>";
        
        if ($code === 204) {
            echo "<p>✅ Comando enviado correctamente. Revisa tu dispositivo Spotify.</p>";
        }
    }
    ?>
    
    <h2>Instrucciones:</h2>
    <ol>
        <li>Abre Spotify en el dispositivo donde QUIERES escuchar</li>
        <li>Reproduce UNA CANCIÓN CUALQUIERA (para activar el dispositivo)</li>
        <li>Selecciona ese dispositivo en el menú de arriba</li>
        <li>Haz clic en "REPRODUCIR AHORA"</li>
        <li>¿Escuchaste la canción de prueba?</li>
    </ol>
</body>
</html>