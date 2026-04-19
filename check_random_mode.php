<?php
// check_random_mode.php
// Archivo para verificar periódicamente si necesita activar música aleatoria

session_start();

// ------------------- CONFIG SPOTIFY -------------------
$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
$refresh_token = "AQBmXX4v5xwhvvUh0zbTQ3cQe3aVXdnWRf5nV_P6fKd3x5K143CQbzkT1paH1__62V_0WpO7_Ztt3tWexC3_-uiKacUL3-qVBwmIhOjLzEOdvAw3NImp-UsCLkUD5lT8jlM";
$playlist_id = "4DsQj6WXGKDpmX9oY3bKVz";

// Función segura para JSON
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para obtener token (copiada del archivo principal)
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
    curl_close($ch);

    $json = json_decode($result, true);
    return $json["access_token"] ?? null;
}

// Función para obtener dispositivo activo (copiada del archivo principal)
function getActiveDevice($token) {
    $url = "https://api.spotify.com/v1/me/player/devices";

    $headers = [
        "Authorization: Bearer $token"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($result, true);

    if (!isset($json["devices"])) {
        return null;
    }

    // Si hay dispositivo activo
    foreach ($json["devices"] as $d) {
        if ($d["is_active"] === true) {
            return $d["id"];
        }
    }

    // Si no hay activo, usar el primero
    if (count($json["devices"]) > 0) {
        return $json["devices"][0]["id"];
    }

    return null;
}

// Función para verificar estado de reproducción (copiada del archivo principal)
function checkPlaybackState($access_token) {
    $ch = curl_init("https://api.spotify.com/v1/me/player");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($result, true);
    }
    
    // Si no hay nada reproduciendo (204) o error (otros códigos)
    return [
        'is_playing' => false,
        'device' => null,
        'context' => null
    ];
}

// Función para reproducir música aleatoria (copiada del archivo principal)
function playRandomMusic($access_token, $device_id = null) {
    // Si no hay device_id, intentar obtener uno activo
    if (!$device_id) {
        $device_id = getActiveDevice($access_token);
        if (!$device_id) {
            error_log("CHECK_RANDOM: No hay dispositivo activo");
            return false;
        }
    }
    
    // Lista de playlists de música aleatoria
    $random_playlists = [
        "spotify:playlist:37i9dQZF1DXcBWIGoYBM5M", // Today's Top Hits
        "spotify:playlist:37i9dQZF1DX0XUsuxWHRQd", // RapCaviar
        "spotify:playlist:37i9dQZF1DX4dyzvuaRJ0n", // mint
        "spotify:playlist:37i9dQZF1DX4o1oenSJRJd", // All Out 2000s
        "spotify:playlist:37i9dQZF1DX4UtSsGT1Sbe", // All Out 80s
    ];
    
    // Seleccionar una playlist aleatoria
    $random_playlist = $random_playlists[array_rand($random_playlists)];
    
    // Configurar reproducción aleatoria
    $play_data = [
        "context_uri" => $random_playlist,
        "offset" => ["position" => rand(0, 20)],
        "position_ms" => 0
    ];
    
    $ch = curl_init("https://api.spotify.com/v1/me/player/play?device_id=$device_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($play_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 204) {
        error_log("CHECK_RANDOM: Activando música aleatoria - $random_playlist");
        
        // Guardar en sesión que estamos en modo aleatorio
        $_SESSION['random_mode_active'] = true;
        $_SESSION['random_playlist_uri'] = $random_playlist;
        $_SESSION['random_mode_started'] = time();
        
        return true;
    }
    
    error_log("CHECK_RANDOM: Error al reproducir. Código: $http_code");
    return false;
}

// ------------------- LÓGICA PRINCIPAL -------------------

// Solo ejecutar si hay modo aleatorio activo O si no hay nada reproduciendo
$access_token = getAccessToken($client_id, $client_secret, $refresh_token);

if ($access_token) {
    $playback_state = checkPlaybackState($access_token);
    $device_id = getActiveDevice($access_token);
    
    $should_activate_random = false;
    
    // Condición 1: Si ya está en modo aleatorio pero dejó de reproducir
    if (isset($_SESSION['random_mode_active']) && $_SESSION['random_mode_active'] === true) {
        if (!$playback_state['is_playing']) {
            $should_activate_random = true;
            error_log("CHECK_RANDOM: Modo aleatorio activo pero pausado - reiniciando");
        }
    }
    // Condición 2: Si no hay modo aleatorio pero no se está reproduciendo nada
    elseif (!$playback_state['is_playing'] && $device_id) {
        $should_activate_random = true;
        error_log("CHECK_RANDOM: Nada reproduciendo - activando modo aleatorio");
    }
    
    // Activar música aleatoria si es necesario
    if ($should_activate_random && $device_id) {
        $success = playRandomMusic($access_token, $device_id);
        
        jsonResponse([
            'status' => 'checked',
            'action_taken' => $success ? 'random_music_started' : 'failed_to_start',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        jsonResponse([
            'status' => 'checked',
            'action_taken' => 'no_action_needed',
            'is_playing' => $playback_state['is_playing'] ?? false,
            'device_available' => !empty($device_id),
            'random_mode_active' => $_SESSION['random_mode_active'] ?? false,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
} else {
    jsonResponse([
        'status' => 'error',
        'message' => 'No se pudo obtener access token',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>