<?php
// test_spotify.php - PRUEBA DIRECTA DE LA API
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Spotify API</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        .success { color: #1DB954; }
        .error { color: #e22134; }
        .warning { color: #ffc107; }
        pre { background: #333; padding: 10px; border-radius: 5px; overflow: auto; }
        button { background: #1DB954; color: #000; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #1ed760; }
        .step { border-left: 3px solid #1DB954; padding-left: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔍 DIAGNÓSTICO DE SPOTIFY API</h1>
    
    <?php
    // Configuración
    $client_id = "a2b747acdbab4083b0a89ded7d546a77";
    $client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
    $refresh_token = "AQBmXX4v5xwhvvUh0zbTQ3cQe3aVXdnWRf5nV_P6fKd3x5K143CQbzkT1paH1__62V_0WpO7_Ztt3tWexC3_-uiKacUL3-qVBwmIhOjLzEOdvAw3NImp-UsCLkUD5lT8jlM";
    $playlist_id = "4DsQj6WXGKDpmX9oY3bKVz";
    $track_uri = "spotify:track:4cOdK2wGLETKBW3PvgPWqT"; // Una canción de prueba
    
    function debug_test($message, $data = null) {
        echo "<div class='step'>";
        echo "<h3>$message</h3>";
        if ($data !== null) {
            echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
        }
        echo "</div>";
    }
    
    function call_spotify_api($url, $token, $method = 'GET', $data = null) {
        $ch = curl_init($url);
        $headers = ["Authorization: Bearer $token"];
        
        if ($method === 'POST' || $method === 'PUT') {
            $headers[] = 'Content-Type: application/json';
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'code' => $http_code,
            'response' => $result ? json_decode($result, true) : null,
            'error' => $error
        ];
    }
    
    function getAccessToken($client_id, $client_secret, $refresh_token) {
        $url = "https://accounts.spotify.com/api/token";
        $headers = [
            "Authorization: Basic " . base64_encode("$client_id:$client_secret"),
            "Content-Type: application/x-www-form-urlencoded"
        ];
        $data = http_build_query([
            "grant_type" => "refresh_token",
            "refresh_token" => $refresh_token
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) return null;
        
        $json = json_decode($result, true);
        return $json["access_token"] ?? null;
    }
    
    // ============================================
    // INICIAR PRUEBAS
    // ============================================
    
    echo "<h2>🎯 PRUEBA 1: OBTENER TOKEN</h2>";
    $token = getAccessToken($client_id, $client_secret, $refresh_token);
    
    if (!$token) {
        echo "<p class='error'>❌ ERROR CRÍTICO: No se pudo obtener token</p>";
        exit;
    }
    echo "<p class='success'>✅ Token obtenido: " . substr($token, 0, 20) . "...</p>";
    
    echo "<h2>🎯 PRUEBA 2: VER DISPOSITIVOS ACTIVOS</h2>";
    $devices = call_spotify_api("https://api.spotify.com/v1/me/player/devices", $token);
    
    if ($devices['code'] === 200) {
        echo "<p class='success'>✅ Dispositivos encontrados:</p>";
        echo "<pre>";
        foreach ($devices['response']['devices'] as $d) {
            echo "• " . $d['name'] . " - " . ($d['is_active'] ? '🟢 ACTIVO' : '⚫ INACTIVO') . "\n";
        }
        echo "</pre>";
        
        $active_device = null;
        foreach ($devices['response']['devices'] as $d) {
            if ($d['is_active']) {
                $active_device = $d['id'];
                break;
            }
        }
        if (!$active_device && !empty($devices['response']['devices'])) {
            $active_device = $devices['response']['devices'][0]['id'];
            echo "<p class='warning'>⚠️ Usando primer dispositivo como respaldo</p>";
        }
    } else {
        echo "<p class='error'>❌ Error obteniendo dispositivos: Código " . $devices['code'] . "</p>";
        $active_device = null;
    }
    
    if (!$active_device) {
        echo "<p class='error'>❌ No hay dispositivos disponibles. Abre Spotify en algún dispositivo.</p>";
    }
    
    echo "<h2>🎯 PRUEBA 3: VER ESTADO ACTUAL</h2>";
    $state = call_spotify_api("https://api.spotify.com/v1/me/player", $token);
    
    if ($state['code'] === 200) {
        $is_playing = $state['response']['is_playing'] ?? false;
        $context = $state['response']['context']['uri'] ?? 'Sin contexto';
        $track = $state['response']['item']['name'] ?? 'Ninguna';
        $artist = $state['response']['item']['artists'][0]['name'] ?? '';
        
        echo "<p class='success'>✅ Estado actual:</p>";
        echo "<pre>";
        echo "Reproduciendo: " . ($is_playing ? 'SÍ' : 'NO') . "\n";
        echo "Contexto: $context\n";
        echo "Canción: $track - $artist\n";
        echo "</pre>";
    } elseif ($state['code'] === 204) {
        echo "<p class='warning'>⚠️ No hay reproducción activa (204)</p>";
    } else {
        echo "<p class='error'>❌ Error obteniendo estado: Código " . $state['code'] . "</p>";
    }
    
    echo "<h2>🎯 PRUEBA 4: AGREGAR A PLAYLIST</h2>";
    if ($active_device) {
        $add = call_spotify_api(
            "https://api.spotify.com/v1/playlists/$playlist_id/tracks",
            $token,
            'POST',
            ["uris" => [$track_uri]]
        );
        
        if ($add['code'] === 201 || $add['code'] === 200) {
            echo "<p class='success'>✅ Canción agregada a playlist correctamente</p>";
        } else {
            echo "<p class='error'>❌ Error agregando a playlist: Código " . $add['code'] . "</p>";
            echo "<pre>" . print_r($add['response'], true) . "</pre>";
        }
    }
    
    echo "<h2>🎯 PRUEBA 5: PROBAR CADA MÉTODO</h2>";
    
    if ($active_device) {
        // Botones para probar cada método manualmente
        echo "<form method='POST' style='margin:20px 0;'>";
        echo "<input type='hidden' name='device_id' value='$active_device'>";
        echo "<button type='submit' name='action' value='play_single'>🎵 Probar playSingleTrack</button>";
        echo "<button type='submit' name='action' value='play_context'>📋 Probar playInPlaylistContext</button>";
        echo "<button type='submit' name='action' value='add_queue'>➕ Probar addToQueue</button>";
        echo "<button type='submit' name='action' value='pause'>⏸️ Probar pause</button>";
        echo "<button type='submit' name='action' value='full_test'>🔄 Probar secuencia completa</button>";
        echo "</form>";
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];
            $device_id = $_POST['device_id'];
            
            echo "<h3>Resultado de: $action</h3>";
            
            switch($action) {
                case 'play_single':
                    $data = ["uris" => [$track_uri], "position_ms" => 0];
                    $result = call_spotify_api(
                        "https://api.spotify.com/v1/me/player/play?device_id=" . urlencode($device_id),
                        $token,
                        'PUT',
                        $data
                    );
                    echo "<p>Código HTTP: " . $result['code'] . "</p>";
                    echo "<p>" . ($result['code'] === 204 ? "✅ ÉXITO" : "❌ FALLÓ") . "</p>";
                    break;
                    
                case 'play_context':
                    $data = [
                        "context_uri" => "spotify:playlist:$playlist_id",
                        "offset" => ["uri" => $track_uri],
                        "position_ms" => 0
                    ];
                    $result = call_spotify_api(
                        "https://api.spotify.com/v1/me/player/play?device_id=" . urlencode($device_id),
                        $token,
                        'PUT',
                        $data
                    );
                    echo "<p>Código HTTP: " . $result['code'] . "</p>";
                    echo "<p>" . ($result['code'] === 204 ? "✅ ÉXITO" : "❌ FALLÓ") . "</p>";
                    break;
                    
                case 'add_queue':
                    $result = call_spotify_api(
                        "https://api.spotify.com/v1/me/player/queue?uri=" . urlencode($track_uri) . "&device_id=" . urlencode($device_id),
                        $token,
                        'POST'
                    );
                    echo "<p>Código HTTP: " . $result['code'] . "</p>";
                    echo "<p>" . ($result['code'] === 204 ? "✅ ÉXITO" : "❌ FALLÓ") . "</p>";
                    break;
                    
                case 'pause':
                    $result = call_spotify_api(
                        "https://api.spotify.com/v1/me/player/pause?device_id=" . urlencode($device_id),
                        $token,
                        'PUT'
                    );
                    echo "<p>Código HTTP: " . $result['code'] . "</p>";
                    echo "<p>" . ($result['code'] === 204 ? "✅ ÉXITO" : "❌ FALLÓ") . "</p>";
                    break;
                    
                case 'full_test':
                    echo "<h4>Paso 1: Pausar</h4>";
                    $pause = call_spotify_api(
                        "https://api.spotify.com/v1/me/player/pause?device_id=" . urlencode($device_id),
                        $token,
                        'PUT'
                    );
                    echo "<p>Código: " . $pause['code'] . "</p>";
                    
                    echo "<h4>Esperando 1 segundo...</h4>";
                    sleep(1);
                    
                    echo "<h4>Paso 2: Reproducir en contexto</h4>";
                    $data = [
                        "context_uri" => "spotify:playlist:$playlist_id",
                        "offset" => ["uri" => $track_uri],
                        "position_ms" => 0
                    ];
                    $play = call_spotify_api(
                        "https://api.spotify.com/v1/me/player/play?device_id=" . urlencode($device_id),
                        $token,
                        'PUT',
                        $data
                    );
                    echo "<p>Código: " . $play['code'] . "</p>";
                    
                    echo "<h4>Resultado final:</h4>";
                    echo "<p>" . (($pause['code'] === 204 && $play['code'] === 204) ? "✅ TODO OK" : "❌ ALGO FALLÓ") . "</p>";
                    break;
            }
        }
    }
    
    echo "<h2>📊 RESUMEN DE DIAGNÓSTICO</h2>";
    echo "<ul>";
    echo "<li><strong>Token:</strong> " . ($token ? "✅ OK" : "❌ ERROR") . "</li>";
    echo "<li><strong>Dispositivos:</strong> " . (isset($active_device) ? "✅ OK" : "❌ ERROR") . "</li>";
    echo "<li><strong>Playlist ID:</strong> $playlist_id</li>";
    echo "<li><strong>Track prueba:</strong> $track_uri</li>";
    echo "</ul>";
    
    if (!$token) echo "<p class='error'>🔥 PROBLEMA CRÍTICO: El refresh_token podría haber expirado</p>";
    if (!$active_device) echo "<p class='error'>🔥 PROBLEMA CRÍTICO: No hay dispositivos Spotify activos</p>";
    ?>
    
    <h2>🔧 VERIFICACIONES MANUALES</h2>
    <ol>
        <li>Abre Spotify en tu teléfono o computadora</li>
        <li>Reproduce UNA CANCIÓN (cualquiera)</li>
        <li>Refresca esta página y verifica que aparezca como "ACTIVO"</li>
        <li>Prueba cada botón individualmente</li>
    </ol>
</body>
</html>