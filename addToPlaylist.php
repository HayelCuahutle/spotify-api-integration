<?php
// ============================================
// CONFIGURACIÓN INICIAL
// ============================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/spotify_errors.log');

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// CONFIGURACIÓN SPOTIFY
// ============================================

$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
$refresh_token = "AQBmXX4v5xwhvvUh0zbTQ3cQe3aVXdnWRf5nV_P6fKd3x5K143CQbzkT1paH1__62V_0WpO7_Ztt3tWexC3_-uiKacUL3-qVBwmIhOjLzEOdvAw3NImp-UsCLkUD5lT8jlM";
$playlist_id = "4DsQj6WXGKDpmX9oY3bKVz";

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================

$host = "localhost";
$user = "hayel";
$pass = "Terminus10***";
$db = "tlaxcalitabeach";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    jsonResponse(["success" => false, "message" => "Error de conexión a la base de datos"]);
}

$conn->set_charset("utf8mb4");

// ============================================
// FUNCIONES SPOTIFY
// ============================================

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $json = json_decode($result, true);
    return $json["access_token"] ?? null;
}

function getActiveDevice($token) {
    $url = "https://api.spotify.com/v1/me/player/devices";
    $headers = ["Authorization: Bearer $token"];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $json = json_decode($result, true);
    
    if (!isset($json["devices"]) || empty($json["devices"])) {
        return null;
    }
    
    // Buscar dispositivo activo primero
    foreach ($json["devices"] as $d) {
        if ($d["is_active"] === true) {
            return $d["id"];
        }
    }
    
    // Si hay dispositivos pero ninguno activo, usar el primero
    return $json["devices"][0]["id"];
}

function getPlaybackState($token) {
    $url = "https://api.spotify.com/v1/me/player";
    $headers = ["Authorization: Bearer $token"];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 204) {
        return null;
    }
    if ($http_code !== 200) {
        return null;
    }
    
    return json_decode($result, true);
}

function addToQueue($token, $track_uri, $device_id) {
    $url = "https://api.spotify.com/v1/me/player/queue?uri=" . urlencode($track_uri);
    if ($device_id) {
        $url .= "&device_id=" . urlencode($device_id);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json",
        "Content-Length: 0"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code === 204);
}

function playInPlaylistContext($token, $device_id, $track_uri, $playlist_id) {
    $url = "https://api.spotify.com/v1/me/player/play";
    if ($device_id) {
        $url .= "?device_id=" . urlencode($device_id);
    }
    
    $play_data = [
        "context_uri" => "spotify:playlist:$playlist_id",
        "offset" => ["uri" => $track_uri],
        "position_ms" => 0
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($play_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code === 204);
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

function getRealIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function obtenerHuellaDispositivo($conn, $email = '') {
    if (isset($_COOKIE['user_fingerprint'])) {
        return $_COOKIE['user_fingerprint'];
    }
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    $components = [
        'user_agent' => $user_agent,
        'ip' => getRealIP(),
        'time' => floor(time() / 86400)
    ];
    
    $huella = 'huella_' . hash('sha256', json_encode($components));
    
    setcookie('user_fingerprint', $huella, [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    return $huella;
}

function validarTiempoEspera($conn, $email, $huella) {
    $sql = "SELECT config_value FROM sistema_config WHERE config_key = 'minutos_limite'";
    $result = $conn->query($sql);
    $minutos_limite = 30;
    if ($row = $result->fetch_assoc()) {
        $valor = (int)$row['config_value'];
        if ($valor >= 0 && $valor <= 60) $minutos_limite = $valor;
    }
    
    if ($minutos_limite === 0) return ["puede" => true];
    
    $sql = "SELECT fecha, hora FROM (
                SELECT fecha, hora FROM canciones_diarias WHERE email_usuario = ?
                UNION ALL
                SELECT fecha, hora FROM canciones_dj WHERE email_usuario = ?
                UNION ALL
                SELECT fecha, hora FROM canciones_diarias WHERE email_usuario = (
                    SELECT email FROM huellas_usuarios WHERE huella_hash = ? LIMIT 1
                )
                UNION ALL
                SELECT fecha, hora FROM canciones_dj WHERE email_usuario = (
                    SELECT email FROM huellas_usuarios WHERE huella_hash = ? LIMIT 1
                )
            ) AS todas_canciones
            WHERE fecha IS NOT NULL AND hora IS NOT NULL
            ORDER BY fecha DESC, hora DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $email, $email, $huella, $huella);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $ultimo_timestamp = strtotime($row['fecha'] . ' ' . $row['hora']);
        $minutos_transcurridos = round((time() - $ultimo_timestamp) / 60);
        
        if ($minutos_transcurridos < $minutos_limite) {
            $minutos_restantes = ceil($minutos_limite - $minutos_transcurridos);
            return [
                "puede" => false,
                "mensaje" => "Límite: 1 canción cada {$minutos_limite} minutos. Espera $minutos_restantes " . 
                             ($minutos_restantes == 1 ? "minuto" : "minutos")
            ];
        }
    }
    
    return ["puede" => true];
}

// ============================================
// PROCESAR PETICIÓN POST
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trackUri = trim($_POST['trackUri'] ?? '');
    $trackName = trim($_POST['trackName'] ?? 'Sin nombre');
    $trackArtist = trim($_POST['trackArtist'] ?? 'Desconocido');
    $userEmail = trim($_POST['userEmail'] ?? '');
    
    if (empty($userEmail) && isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
        $userEmail = $_SESSION['user_email'];
    }
    
    if (!empty($userEmail)) {
        $_SESSION['user_email'] = $userEmail;
    }
    
    if (empty($trackUri)) {
        jsonResponse(["success" => false, "message" => "No se recibió trackUri"]);
    }
    
    if (!preg_match('/^spotify:track:[a-zA-Z0-9]{22}$/', $trackUri)) {
        jsonResponse(["success" => false, "message" => "URI de Spotify inválida"]);
    }
    
    $huella = obtenerHuellaDispositivo($conn, $userEmail);
    
    $validacion = validarTiempoEspera($conn, $userEmail, $huella);
    if (!$validacion["puede"]) {
        jsonResponse([
            "success" => false, 
            "message" => "time_limit", 
            "error_message" => $validacion["mensaje"]
        ]);
    }
    
    $dj_check = $conn->query("SELECT config_value FROM sistema_config WHERE config_key = 'dj_activo'");
    $dj_row = $dj_check->fetch_assoc();
    $dj_activo = $dj_row['config_value'] ?? '0';
    
    if ($dj_activo == '1') {
        $hora = date('H:i:s');
        $fecha = date('Y-m-d');
        $ip = getRealIP();
        
        $sql = "INSERT INTO canciones_dj (nombre, artista, uri, email_usuario, ip_usuario, hora, fecha, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $trackName, $trackArtist, $trackUri, $userEmail, $ip, $hora, $fecha);
        
        if ($stmt->execute()) {
            $stmt->close();
            jsonResponse([
                "success" => true, 
                "message" => "dj_mode",
                "dj_activo" => true,
                "data" => [
                    "nombre" => $trackName, 
                    "artista" => $trackArtist,
                    "hora" => $hora,
                    "fecha" => $fecha
                ]
            ]);
        }
        $stmt->close();
    }
    
    $access_token = getAccessToken($client_id, $client_secret, $refresh_token);
    
    if (!$access_token) {
        jsonResponse(["success" => false, "message" => "No se pudo obtener access token de Spotify"]);
    }
    
    // 1. Agregar a playlist de Spotify
    $ch = curl_init("https://api.spotify.com/v1/playlists/$playlist_id/tracks");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["uris" => [$trackUri]]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    curl_close($ch);
    
    // 2. Guardar en BD
    $hora = date('H:i:s');
    $fecha = date('Y-m-d');
    $ip = getRealIP();
    
    $sql = "INSERT INTO canciones_diarias (nombre, artista, uri, hora, fecha, email_usuario, ip_usuario) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $trackName, $trackArtist, $trackUri, $hora, $fecha, $userEmail, $ip);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        jsonResponse(["success" => false, "message" => "Error al guardar en base de datos: " . $error]);
    }
    
    $db_id = $stmt->insert_id;
    $stmt->close();
    
    // ============================================
    // 🎯 LÓGICA FINAL - LA QUE PEDISTE
    // ============================================
    
    $device_id = getActiveDevice($access_token);
    
    if ($device_id) {
        // Obtener estado actual
        $playback_state = getPlaybackState($access_token);
        
        // CASO 1: NO HAY REPRODUCCIÓN ACTIVA
        if (!$playback_state || !isset($playback_state['is_playing']) || $playback_state['is_playing'] === false) {
            playInPlaylistContext($access_token, $device_id, $trackUri, $playlist_id);
        } 
        // HAY REPRODUCCIÓN ACTIVA
        else {
            $current_context = $playback_state['context']['uri'] ?? '';
            $is_our_playlist = strpos($current_context, "spotify:playlist:$playlist_id") !== false;
            
            // CASO 2: ESTÁ SONANDO NUESTRA PLAYLIST
            if ($is_our_playlist) {
                addToQueue($access_token, $trackUri, $device_id);
            } 
            // CASO 3: ESTÁ SONANDO OTRA COSA
            else {
                playInPlaylistContext($access_token, $device_id, $trackUri, $playlist_id);
            }
        }
    }
    
    jsonResponse([
        "success" => true,
        "data" => [
            "id" => $db_id,
            "nombre" => $trackName,
            "artista" => $trackArtist,
            "hora" => $hora,
            "fecha" => $fecha
        ]
    ]);
}

jsonResponse(["success" => false, "message" => "Método no permitido"]);
?>