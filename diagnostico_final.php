<?php
// diagnostico_final.php - EL DIAGNÓSTICO DEFINITIVO
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 DIAGNÓSTICO FINAL SPOTIFY</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        .success { color: #0f0; font-weight: bold; }
        .error { color: #f00; font-weight: bold; }
        .warning { color: #ff0; font-weight: bold; }
        .step { border: 1px solid #333; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .step h3 { margin-top: 0; color: #1DB954; }
        pre { background: #333; padding: 10px; border-radius: 5px; overflow: auto; }
        button { background: #1DB954; color: #000; padding: 15px 30px; font-size: 18px; border: none; border-radius: 5px; cursor: pointer; margin: 10px; }
        .activo { border-left: 5px solid #0f0; }
        .inactivo { border-left: 5px solid #f00; }
    </style>
</head>
<body>
    <h1>🔍 DIAGNÓSTICO FINAL DEL SISTEMA</h1>
    
    <?php
    // ============================================
    // CONFIGURACIÓN
    // ============================================
    $client_id = "a2b747acdbab4083b0a89ded7d546a77";
    $client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
    $refresh_token = "AQBmXX4v5xwhvvUh0zbTQ3cQe3aVXdnWRf5nV_P6fKd3x5K143CQbzkT1paH1__62V_0WpO7_Ztt3tWexC3_-uiKacUL3-qVBwmIhOjLzEOdvAw3NImp-UsCLkUD5lT8jlM";
    $playlist_id = "4DsQj6WXGKDpmX9oY3bKVz";
    
    $host = "localhost";
    $user = "hayel";
    $pass = "Terminus10***";
    $db = "tlaxcalitabeach";
    
    echo "<div class='step'>";
    echo "<h3>🔧 PASO 1: Verificando credenciales</h3>";
    
    // Verificar que las credenciales no estén vacías
    $errores = [];
    if (empty($client_id)) $errores[] = "client_id vacío";
    if (empty($client_secret)) $errores[] = "client_secret vacío";
    if (empty($refresh_token)) $errores[] = "refresh_token vacío";
    if (empty($playlist_id)) $errores[] = "playlist_id vacío";
    
    if (empty($errores)) {
        echo "<p class='success'>✅ Todas las credenciales están configuradas</p>";
    } else {
        echo "<p class='error'>❌ ERROR: " . implode(", ", $errores) . "</p>";
    }
    echo "</div>";
    
    // ============================================
    // PASO 2: PROBAR TOKEN
    // ============================================
    echo "<div class='step'>";
    echo "<h3>🔑 PASO 2: Probando refresh_token</h3>";
    
    function getTokenTest($client_id, $client_secret, $refresh_token) {
        $ch = curl_init("https://accounts.spotify.com/api/token");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=refresh_token&refresh_token=" . urlencode($refresh_token));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode("$client_id:$client_secret"),
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'http_code' => $info['http_code'],
            'response' => json_decode($result, true),
            'error' => $error
        ];
    }
    
    $token_test = getTokenTest($client_id, $client_secret, $refresh_token);
    
    echo "<p>Código HTTP: <strong>" . $token_test['http_code'] . "</strong></p>";
    
    if ($token_test['http_code'] === 200) {
        echo "<p class='success'>✅ Token obtenido correctamente</p>";
        echo "<p>Access Token: " . substr($token_test['response']['access_token'], 0, 20) . "...</p>";
        echo "<p>Expira en: " . $token_test['response']['expires_in'] . " segundos</p>";
        $token = $token_test['response']['access_token'];
    } else {
        echo "<p class='error'>❌ ERROR: No se pudo obtener token</p>";
        if ($token_test['error']) {
            echo "<p>Error CURL: " . $token_test['error'] . "</p>";
        }
        if ($token_test['response']) {
            echo "<pre>" . print_r($token_test['response'], true) . "</pre>";
        }
        $token = null;
    }
    echo "</div>";
    
    if (!$token) {
        echo "<div class='step error'>";
        echo "<h3>❌ DIAGNÓSTICO: El refresh_token NO funciona</h3>";
        echo "<p>Posibles causas:</p>";
        echo "<ul>";
        echo "<li>El refresh_token expiró (después de 90 días sin uso)</li>";
        echo "<li>Las credenciales client_id/client_secret son incorrectas</li>";
        echo "<li>La cuenta de Spotify fue desactivada</li>";
        echo "</ul>";
        echo "<p><strong>Solución:</strong> Genera un nuevo refresh_token</p>";
        echo "</div>";
        exit;
    }
    
    // ============================================
    // PASO 3: VERIFICAR LA CUENTA
    // ============================================
    echo "<div class='step'>";
    echo "<h3>👤 PASO 3: Verificando cuenta de Spotify</h3>";
    
    $ch = curl_init("https://api.spotify.com/v1/me");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $profile = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $profile_data = json_decode($profile, true);
        echo "<p class='success'>✅ Cuenta verificada</p>";
        echo "<ul>";
        echo "<li><strong>Usuario:</strong> " . ($profile_data['display_name'] ?? 'No disponible') . "</li>";
        echo "<li><strong>Email:</strong> " . ($profile_data['email'] ?? 'No disponible') . "</li>";
        echo "<li><strong>ID:</strong> " . ($profile_data['id'] ?? 'No disponible') . "</li>";
        echo "<li><strong>País:</strong> " . ($profile_data['country'] ?? 'No disponible') . "</li>";
        echo "<li><strong>Producto:</strong> " . ($profile_data['product'] ?? 'No disponible') . "</li>";
        echo "</ul>";
        
        // VERIFICACIÓN CRÍTICA: ¿La cuenta es PREMIUM?
        if (($profile_data['product'] ?? '') !== 'premium') {
            echo "<p class='error'>❌ ERROR CRÍTICO: La cuenta NO es Premium</p>";
            echo "<p>Spotify API SOLO funciona con cuentas Premium para control de reproducción</p>";
        } else {
            echo "<p class='success'>✅ Cuenta Premium - Todo bien</p>";
        }
    } else {
        echo "<p class='error'>❌ No se pudo obtener perfil. Código: $http_code</p>";
    }
    echo "</div>";
    
    // ============================================
    // PASO 4: VERIFICAR PLAYLIST
    // ============================================
    echo "<div class='step'>";
    echo "<h3>📋 PASO 4: Verificando playlist</h3>";
    
    $ch = curl_init("https://api.spotify.com/v1/playlists/$playlist_id");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $playlist = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $playlist_data = json_decode($playlist, true);
        echo "<p class='success'>✅ Playlist encontrada</p>";
        echo "<ul>";
        echo "<li><strong>Nombre:</strong> " . ($playlist_data['name'] ?? 'No disponible') . "</li>";
        echo "<li><strong>Dueño:</strong> " . ($playlist_data['owner']['display_name'] ?? 'No disponible') . "</li>";
        echo "<li><strong>Canciones:</strong> " . ($playlist_data['tracks']['total'] ?? '0') . "</li>";
        echo "</ul>";
        
        // Verificar que la playlist es de la misma cuenta
        if (($playlist_data['owner']['id'] ?? '') !== ($profile_data['id'] ?? '')) {
            echo "<p class='warning'>⚠️ ADVERTENCIA: La playlist NO es de tu cuenta</p>";
            echo "<p>Esto puede causar problemas de permisos</p>";
        }
    } else {
        echo "<p class='error'>❌ No se pudo acceder a la playlist. Código: $http_code</p>";
        echo "<p>Posibles causas:</p>";
        echo "<ul>";
        echo "<li>El playlist_id es incorrecto</li>";
        echo "<li>La playlist es privada y no pertenece a la cuenta</li>";
        echo "<li>No tienes permisos para acceder a ella</li>";
        echo "</ul>";
    }
    echo "</div>";
    
    // ============================================
    // PASO 5: VERIFICAR DISPOSITIVOS
    // ============================================
    echo "<div class='step'>";
    echo "<h3>📱 PASO 5: Verificando dispositivos activos</h3>";
    echo "<p class='warning'>⚠️ IMPORTANTE: Debes tener Spotify ABIERTO en ALGÚN dispositivo</p>";
    echo "<button onclick='window.location.reload()'>🔄 REFRESCAR</button>";
    
    $ch = curl_init("https://api.spotify.com/v1/me/player/devices");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $devices = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $devices_data = json_decode($devices, true);
        
        if (empty($devices_data['devices'])) {
            echo "<p class='error'>❌ NO HAY DISPOSITIVOS ACTIVOS</p>";
            echo "<p>Esto explica por qué no se reproduce nada</p>";
            echo "<p><strong>Solución:</strong></p>";
            echo "<ol>";
            echo "<li>Abre Spotify en tu teléfono o computadora</li>";
            echo "<li>Asegúrate de iniciar sesión con: <strong>" . ($profile_data['email'] ?? 'la cuenta correcta') . "</strong></li>";
            echo "<li>Reproduce UNA CANCIÓN (cualquiera)</li>";
            echo "<li>Refresca esta página</li>";
            echo "</ol>";
        } else {
            echo "<p class='success'>✅ Dispositivos encontrados: " . count($devices_data['devices']) . "</p>";
            
            $hay_activo = false;
            foreach ($devices_data['devices'] as $d) {
                $clase = $d['is_active'] ? 'activo' : 'inactivo';
                if ($d['is_active']) $hay_activo = true;
                
                echo "<div class='$clase' style='padding: 10px; margin: 5px; background: #333;'>";
                echo "<strong>{$d['name']}</strong> ({$d['type']})<br>";
                echo "ID: {$d['id']}<br>";
                echo "Activo: " . ($d['is_active'] ? '🟢 SÍ' : '⚫ NO') . "<br>";
                echo "Volumen: {$d['volume_percent']}%<br>";
                echo "</div>";
            }
            
            if (!$hay_activo) {
                echo "<p class='warning'>⚠️ Hay dispositivos pero NINGUNO está activo</p>";
                echo "<p>Reproduce una canción en el dispositivo que quieras usar</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ Error obteniendo dispositivos. Código: $http_code</p>";
    }
    echo "</div>";
    
    // ============================================
    // PASO 6: PRUEBA DE REPRODUCCIÓN DIRECTA
    // ============================================
    echo "<div class='step'>";
    echo "<h3>🎵 PASO 6: Prueba de reproducción DIRECTA</h3>";
    
    if (!empty($devices_data['devices'])) {
        $primer_device = $devices_data['devices'][0]['id'];
        $track_prueba = "spotify:track:4cOdK2wGLETKBW3PvgPWqT"; // Blinding Lights
        
        echo "<form method='POST'>";
        echo "<input type='hidden' name='test_device' value='$primer_device'>";
        echo "<input type='hidden' name='test_track' value='$track_prueba'>";
        echo "<button type='submit' name='probar' style='background: #1DB954;'>🎵 PROBAR REPRODUCCIÓN AHORA</button>";
        echo "</form>";
        
        if (isset($_POST['probar'])) {
            $device_id = $_POST['test_device'];
            $track_uri = $_POST['test_track'];
            
            echo "<h4>Resultado de la prueba:</h4>";
            
            // PRIMERO: Intentar reproducir
            $ch = curl_init("https://api.spotify.com/v1/me/player/play?device_id=" . urlencode($device_id));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["uris" => [$track_uri]]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 204) {
                echo "<p class='success'>✅ Comando enviado (código 204)</p>";
                echo "<p>La canción DEBERÍA estar sonando en: <strong>{$devices_data['devices'][0]['name']}</strong></p>";
                echo "<p>¿Escuchas algo?</p>";
            } else {
                echo "<p class='error'>❌ Error al reproducir. Código: $http_code</p>";
            }
            
            // SEGUNDO: Verificar estado después
            sleep(2);
            $ch = curl_init("https://api.spotify.com/v1/me/player");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $estado = curl_exec($ch);
            curl_close($ch);
            $estado_data = json_decode($estado, true);
            
            if ($estado_data && isset($estado_data['is_playing'])) {
                echo "<p>Estado actual: " . ($estado_data['is_playing'] ? 'REPRODUCIENDO' : 'PAUSADO') . "</p>";
                if ($estado_data['is_playing']) {
                    echo "<p>Canción actual: " . ($estado_data['item']['name'] ?? 'Desconocida') . "</p>";
                }
            }
        }
    } else {
        echo "<p class='error'>❌ No hay dispositivos para probar</p>";
    }
    echo "</div>";
    
    // ============================================
    // RESUMEN FINAL
    // ============================================
    echo "<div class='step'>";
    echo "<h3>📊 RESUMEN DEL DIAGNÓSTICO</h3>";
    
    $puede_funcionar = true;
    $problemas = [];
    
    if ($token_test['http_code'] !== 200) {
        $puede_funcionar = false;
        $problemas[] = "Token no funciona";
    }
    
    if (empty($devices_data['devices'])) {
        $puede_funcionar = false;
        $problemas[] = "No hay dispositivos activos";
    }
    
    if (($profile_data['product'] ?? '') !== 'premium') {
        $puede_funcionar = false;
        $problemas[] = "Cuenta no es Premium";
    }
    
    if ($http_code !== 200) {
        $puede_funcionar = false;
        $problemas[] = "Playlist no accesible";
    }
    
    if ($puede_funcionar) {
        echo "<p class='success'>✅ TODO ESTÁ CORRECTO - El sistema DEBERÍA funcionar</p>";
        echo "<p>Si no funciona, el problema es de la API de Spotify (inestabilidad)</p>";
    } else {
        echo "<p class='error'>❌ HAY PROBLEMAS QUE IMPIDEN EL FUNCIONAMIENTO:</p>";
        echo "<ul>";
        foreach ($problemas as $p) {
            echo "<li class='error'>$p</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    ?>
    
    <div class='step'>
        <h3>📋 INSTRUCCIONES FINALES</h3>
        <ol>
            <li><strong>VERIFICA</strong> que el email de la cuenta sea: <?php echo $profile_data['email'] ?? 'DESCONOCIDO'; ?></li>
            <li><strong>ABRE SPOTIFY</strong> en ese dispositivo con ESA cuenta</li>
            <li><strong>REPRODUCE</strong> una canción manualmente</li>
            <li><strong>REFRESCA</strong> esta página</li>
            <li><strong>PRUEBA</strong> el botón de reproducción directa</li>
        </ol>
    </div>
</body>
</html>