<?php
session_start();

// Configuración de base de datos
$host = 'localhost';
$dbname = 'tlaxcalitabeach';
$username = 'hayel';
$password = 'Terminus10***';

// Conexión única a la base de datos
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("Error de conexión a la base de datos. Por favor, intenta más tarde.");
}

// ============================================
// VERIFICAR SI LAS CALIFICACIONES ESTÁN ACTIVAS
// ============================================

$calificaciones_activas = true;

try {
    $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'calificaciones_activas'");
    $config = $stmt->fetch();
    $calificaciones_activas = ($config && $config['config_value'] == '1');
} catch (Exception $e) {
    error_log("Error al verificar calificaciones: " . $e->getMessage());
}

// ============================================
// FLUJO DE VERIFICACIÓN
// ============================================

if ($calificaciones_activas) {
    // CALIFICACIONES ACTIVAS → Usar el flujo normal de verificación
    
    function verificarEstadoUsuarioMejorado($pdo) {
        $email = $_SESSION['user_email'] ?? null;
        $huella_hash = obtenerHuellaUsuarioPDO($pdo, $email);
        return verificarPorHuellaPDO($pdo, $huella_hash, $email);
    }

    function obtenerHuellaUsuarioPDO($pdo, $email = '') {
        if (isset($_COOKIE['user_fingerprint'])) {
            $huella_hash = $_COOKIE['user_fingerprint'];
            
            $stmt = $pdo->prepare("SELECT id FROM huellas_usuarios WHERE huella_hash = ?");
            $stmt->execute([$huella_hash]);
            
            if ($stmt->rowCount() > 0) {
                $updateStmt = $pdo->prepare("UPDATE huellas_usuarios SET ultima_actividad = NOW() WHERE huella_hash = ?");
                $updateStmt->execute([$huella_hash]);
                return $huella_hash;
            }
        }
        
        $components = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'time' => time(),
            'session_id' => session_id(),
            'random' => bin2hex(random_bytes(16))
        ];
        
        $huella_hash = hash('sha256', json_encode($components));
        
        setcookie('user_fingerprint', $huella_hash, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO huellas_usuarios (huella_hash, email, ip_cliente, user_agent, dispositivo_id) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                email = COALESCE(VALUES(email), email),
                ultima_actividad = NOW()
        ");
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $dispositivo_id = 'device_' . substr(hash('sha256', $user_agent . session_id()), 0, 12);
        
        $stmt->execute([
            $huella_hash, 
            !empty($email) ? $email : NULL,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $user_agent,
            $dispositivo_id
        ]);
        
        return $huella_hash;
    }

    function verificarPorHuellaPDO($pdo, $huella_hash, $email = '') {
        $minutos_limite = obtenerTiempoLimiteConfiguradoPDO($pdo);
        
        if (empty($email)) {
            $stmt = $pdo->prepare("
                SELECT fecha, hora 
                FROM (
                    SELECT fecha, hora FROM canciones_diarias WHERE email_usuario = (
                        SELECT email FROM huellas_usuarios WHERE huella_hash = ? AND email IS NOT NULL LIMIT 1
                    )
                    UNION ALL
                    SELECT fecha, hora FROM canciones_dj WHERE email_usuario = (
                        SELECT email FROM huellas_usuarios WHERE huella_hash = ? AND email IS NOT NULL LIMIT 1
                    )
                ) AS canciones
                WHERE fecha IS NOT NULL AND hora IS NOT NULL
                ORDER BY fecha DESC, hora DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$huella_hash, $huella_hash]);
            
            if ($stmt->rowCount() > 0) {
                $ultima = $stmt->fetch();
                
                if (!empty($ultima['fecha']) && !empty($ultima['hora'])) {
                    $ultimoTimestamp = strtotime($ultima['fecha'] . ' ' . $ultima['hora']);
                    $ahora = time();
                    $minutosTranscurridos = round(($ahora - $ultimoTimestamp) / 60);
                    
                    if ($minutosTranscurridos < $minutos_limite) {
                        header("Location: index.php");
                        exit();
                    } else {
                        return true;
                    }
                }
            }
            
            header("Location: index.php");
            exit();
        }
        
        return verificarPorEmail($pdo, $email, true);
    }

    function obtenerTiempoLimiteConfiguradoPDO($pdo) {
        try {
            $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'minutos_limite'");
            $config = $stmt->fetch();
            
            if ($config && $config['config_value'] !== null) {
                $valor = (int)$config['config_value'];
                if ($valor >= 0 && $valor <= 60) {
                    return $valor;
                }
            }
        } catch (Exception $e) {
            error_log("Error al obtener tiempo límite: " . $e->getMessage());
        }
        return 30;
    }

    function verificarPorEmail($pdo, $email, $enBuscador = false) {
        $stmtCalificacion = $pdo->prepare("SELECT COUNT(*) as total FROM calificaciones_servicio WHERE correo = ? AND DATE(fecha_registro) = CURDATE()");
        $stmtCalificacion->execute([$email]);
        $yaCalificoHoy = $stmtCalificacion->fetch()['total'] > 0;
        
        if (!$yaCalificoHoy) {
            header("Location: index.php");
            exit();
        }
        
        $stmtCancion = $pdo->prepare("SELECT fecha, hora FROM canciones_diarias WHERE email_usuario = ? 
                                     UNION ALL 
                                     SELECT fecha, hora FROM canciones_dj WHERE email_usuario = ? 
                                     ORDER BY fecha DESC, hora DESC LIMIT 1");
        $stmtCancion->execute([$email, $email]);
        
        if ($stmtCancion->rowCount() > 0) {
            $ultimaCancion = $stmtCancion->fetch();
            $ultimoTimestamp = strtotime($ultimaCancion['fecha'] . ' ' . $ultimaCancion['hora']);
            $ahora = time();
            $minutosTranscurridos = round(($ahora - $ultimoTimestamp) / 60);
            
            $minutos_limite = obtenerTiempoLimiteConfiguradoPDO($pdo);
            
            if ($minutosTranscurridos < $minutos_limite) {
                header("Location: index.php");
                exit();
            }
        }
        
        return true;
    }

    try {
        $estadoUsuario = verificarEstadoUsuarioMejorado($pdo);
        if ($estadoUsuario !== true) {
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Error en verificación: " . $e->getMessage());
        header("Location: index.php");
        exit();
    }

} else {
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        $_SESSION['user_email'] = $_POST['email'];
    }
    
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        $_SESSION['user_email'] = 'usuario@directo.com';
    }
}

// ============================================
// OBTENER CONFIGURACIÓN DEL SISTEMA
// ============================================

$minutos_limite = 30;
$mensaje_limite = "Límite de 1 canción por 30 minutos";
$canciones_activas = true;
$dj_activo = '0';

try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM sistema_config WHERE config_key IN ('minutos_limite', 'canciones_activas', 'dj_activo')");
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (isset($configs['minutos_limite'])) {
        $valor = (int)$configs['minutos_limite'];
        if ($valor >= 0 && $valor <= 60) {
            $minutos_limite = $valor;
        }
    }
    
    if (isset($configs['canciones_activas'])) {
        $canciones_activas = ($configs['canciones_activas'] == '1');
    }
    
    if (isset($configs['dj_activo'])) {
        $dj_activo = $configs['dj_activo'];
    }
    
    if ($minutos_limite === 0) {
        $mensaje_limite = "Sin límite de tiempo";
    } elseif ($minutos_limite === 1) {
        $mensaje_limite = "1 canción por minuto";
    } elseif ($minutos_limite === 60) {
        $mensaje_limite = "1 canción por hora";
    } else {
        $mensaje_limite = "1 canción cada {$minutos_limite} minutos";
    }
    
} catch (Exception $e) {
    error_log("Error al obtener configuración: " . $e->getMessage());
}

// ============================================
// VERIFICAR SI LAS CANCIONES ESTÁN ACTIVAS
// ============================================

if (!$canciones_activas) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema en Mantenimiento - Tlaxcalita Beach</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .maintenance-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .maintenance-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1 class="text-warning">🎵 Sistema en Mantenimiento</h1>
        <p class="lead mt-3">
            El sistema de búsqueda de canciones se encuentra temporalmente desactivado 
            para mantenimiento y mejoras.
        </p>
        <p class="text-muted">
            Estamos trabajando para mejorar tu experiencia. Por favor, intenta más tarde.
        </p>
        <div class="mt-4">
            <a href="../" class="btn btn-primary btn-lg">
                <i class="fas fa-home me-2"></i>Volver al Inicio
            </a>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Spotify - Buscador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --spotify-green: #1DB954;
      --spotify-dark-green: #1ed760;
      --spotify-red: #e22134;
      --spotify-dark-red: #c41a2a;
      --spotify-black: #121212;
      --spotify-dark-gray: #181818;
      --spotify-light-gray: #b3b3b3;
      --spotify-white: #FFFFFF;
    }
    body { background: linear-gradient(180deg, #121212, #000000); color: var(--spotify-white); font-family: 'Inter', sans-serif; margin: 0; padding: 0; min-height: 100vh; scroll-behavior: smooth; }
    .spotify-container { max-width: 1200px; margin: 0 auto; padding: 20px; }

    .spotify-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid var(--spotify-dark-gray); margin-bottom: 25px; }
    .spotify-logo { font-size: 28px; font-weight: 700; color: var(--spotify-white); letter-spacing: -0.5px; }
    .spotify-logo span { color: var(--spotify-green); }

    .search-container { position: relative; width: 100%; max-width: 650px; margin: 0 auto 40px; }
    .search-input { width: 100%; padding: 15px 20px; border-radius: 50px; border: none; background-color: var(--spotify-dark-gray); font-size: 16px; color: var(--spotify-white); transition: all 0.3s ease; }
    .search-input::placeholder { color: #FFFFFF !important; opacity: 0.9 !important; }
    .search-input:focus { outline: none; background-color: #2a2a2a; box-shadow: 0 0 0 2px var(--spotify-green); }
    .search-button { position: absolute; right: 5px; top: 5px; background-color: var(--spotify-green); border: none; border-radius: 50px; padding: 10px 25px; color: var(--spotify-white); font-weight: bold; cursor: pointer; transition: all 0.2s ease; }
    .search-button:hover { background-color: var(--spotify-dark-green); transform: scale(1.05); }

    .autocomplete-container { position: absolute; top: 100%; left: 0; right: 0; background: var(--spotify-dark-gray); border-radius: 8px; margin-top: 5px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6); z-index: 1000; max-height: 320px; overflow-y: auto; display: none; }
    .autocomplete-item { padding: 12px 18px; cursor: pointer; display: flex; align-items: center; gap: 15px; transition: background 0.2s; }
    .autocomplete-item:hover { background-color: rgba(255, 255, 255, 0.08); }
    .autocomplete-image { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
    .autocomplete-name { font-weight: 600; font-size: 15px; margin-bottom: 2px; }
    .autocomplete-artist { color: var(--spotify-light-gray); font-size: 13px; }
    .autocomplete-type { margin-left: auto; background: var(--spotify-green); color: var(--spotify-black); padding: 3px 9px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }

    .welcome-section { text-align: center; padding: 70px 20px; color: var(--spotify-light-gray); }
    .welcome-title { font-size: 36px; font-weight: 700; color: var(--spotify-white); margin-bottom: 15px; }
    .welcome-subtitle { font-size: 18px; margin-bottom: 30px; }

    .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-top: 20px; }
    .track-card { background: #181818; border-radius: 10px; padding: 15px; cursor: pointer; transition: all 0.25s ease; display: flex; flex-direction: column; height: 100%; position: relative; }
    .track-card:hover { background: #242424; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3); }
    .track-image { width: 100%; border-radius: 8px; margin-bottom: 12px; aspect-ratio: 1 / 1; object-fit: cover; }
    .track-name { font-size: 16px; font-weight: 600; margin-bottom: 5px; }
    .track-artist { font-size: 14px; color: var(--spotify-light-gray); margin-bottom: 8px; }
    .track-album { font-size: 13px; color: var(--spotify-light-gray); }
    .track-duration { margin-top: auto; text-align: right; font-size: 13px; color: var(--spotify-light-gray); }
    .no-results, .loading { text-align: center; color: var(--spotify-light-gray); font-size: 18px; margin-top: 40px; }

    .spotify-footer { text-align: center; margin-top: 60px; padding: 20px; color: var(--spotify-light-gray); font-size: 13px; border-top: 1px solid var(--spotify-dark-gray); }

    #dailyListSection { display: none; transition: all 0.5s ease; border-radius: 12px; padding: 20px; margin-top: 40px; background: var(--spotify-dark-gray); }
    #dailyListTable thead { background: var(--spotify-green); color: #000; font-weight: bold; }
    #dailyListTable tbody tr { background: #181818; border-bottom: 1px solid #333; transition: all 0.3s ease; }
    #dailyListTable tbody tr:hover { background: #282828; transform: translateX(5px); }
    #dailyListTable tbody tr:last-child { background: rgba(29, 185, 84, 0.1); border-left: 3px solid var(--spotify-green); }

    .list-badge { background: var(--spotify-green); color: #000; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-left: 12px; text-transform: uppercase; letter-spacing: 0.5px; }

    .toast-container-center { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 2000; }
    .toast-spotify { background: linear-gradient(135deg, #1a1a1a, #2d2d2d) !important; border-radius: 12px; border: 1px solid #404040; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7); min-width: 350px; overflow: hidden; }
    .toast-body-spotify { font-weight: 700; font-size: 17px; text-align: center; padding: 25px 30px; color: #fff; position: relative; }
    .toast-body-spotify::before { content: "✓"; font-size: 24px; display: block; margin-bottom: 12px; background: var(--spotify-green); color: #000; width: 50px; height: 50px; border-radius: 50%; line-height: 50px; margin: 0 auto 15px auto; animation: checkmark 0.6s ease-in-out; }
    
    .toast-spotify-delete { background: linear-gradient(135deg, #1a1a1a, #2d2d2d) !important; border-radius: 12px; border: 1px solid #404040; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7); min-width: 350px; overflow: hidden; }
    .toast-body-spotify-delete { font-weight: 700; font-size: 17px; text-align: center; padding: 25px 30px; color: #fff; position: relative; }
    .toast-body-spotify-delete::before { content: "🗑️"; font-size: 24px; display: block; margin-bottom: 12px; background: var(--spotify-red); color: #fff; width: 50px; height: 50px; border-radius: 50%; line-height: 50px; margin: 0 auto 15px auto; animation: deleteAnimation 0.6s ease-in-out; }

    .toast-spotify-time-limit { 
        background: linear-gradient(135deg, #1a1a1a, #2d2d2d) !important; 
        border-radius: 12px; 
        border: 1px solid #404040; 
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7); 
        min-width: 350px; 
        overflow: hidden; 
    }
    .toast-body-spotify-time-limit { 
        font-weight: 700; 
        font-size: 17px; 
        text-align: center; 
        padding: 25px 30px; 
        color: #fff; 
        position: relative; 
    }
    .toast-body-spotify-time-limit::before { 
        content: "⏰"; 
        font-size: 24px; 
        display: block; 
        margin-bottom: 12px; 
        background: var(--spotify-red); 
        color: #fff; 
        width: 50px; 
        height: 50px; 
        border-radius: 50%; 
        line-height: 50px; 
        margin: 0 auto 15px auto; 
        animation: pulse 2s infinite ease-in-out; 
    }

    .toast-spotify-error { background: linear-gradient(135deg, #2a1a1a, #3d2d2d) !important; border-radius: 12px; border: 1px solid #604040; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7); min-width: 350px; overflow: hidden; }
    .toast-body-spotify-error { font-weight: 700; font-size: 17px; text-align: center; padding: 25px 30px; color: #fff; position: relative; }
    .toast-body-spotify-error::before { content: "⚠️"; font-size: 24px; display: block; margin-bottom: 12px; background: var(--spotify-red); color: #fff; width: 50px; height: 50px; border-radius: 50%; line-height: 50px; margin: 0 auto 15px auto; animation: pulse 0.6s ease-in-out; }

    @keyframes checkmark { 
      0% { transform: scale(0); opacity: 0; } 
      50% { transform: scale(1.2); } 
      100% { transform: scale(1); opacity: 1; } 
    }

    @keyframes deleteAnimation { 
      0% { transform: scale(0) rotate(-30deg); opacity: 0; } 
      50% { transform: scale(1.2) rotate(10deg); } 
      100% { transform: scale(1) rotate(0deg); opacity: 1; } 
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .fade-out-up {
      animation: fadeOutUp 0.5s ease-in-out forwards;
    }

    .empty-list {
      text-align: center;
      padding: 40px 20px;
      color: var(--spotify-light-gray);
    }
    
    .empty-list-icon {
      font-size: 48px;
      margin-bottom: 15px;
    }

    .btn-outline-danger {
      border-color: var(--spotify-red) !important;
      color: var(--spotify-red) !important;
      background: transparent !important;
      font-weight: 600;
      font-size: 13px;
      padding: 6px 12px;
      border-radius: 20px;
      transition: all 0.3s ease;
    }

    .btn-outline-danger:hover {
      background-color: var(--spotify-red) !important;
      color: white !important;
      transform: scale(1.05);
      box-shadow: 0 5px 15px rgba(226, 33, 52, 0.3);
    }

    .empty-list-message {
      text-align: center;
      padding: 40px 20px;
      color: var(--spotify-light-gray);
      font-style: italic;
    }
  </style>
</head>
<body>
<div class="spotify-container">
  <div class="spotify-header">
    <div class="spotify-logo">Spotify<span>®</span></div>
    <div></div>
  </div>

  <div class="search-container">
    <input type="text" id="searchInput" class="search-input" placeholder="¿Qué quieres escuchar?">
    <button class="search-button" onclick="searchSong()">Buscar</button>
    <div id="autocomplete" class="autocomplete-container"></div>
  </div>

  <div id="welcomeSection" class="welcome-section">
    <h1 class="welcome-title">Encuentra tu música favorita</h1>
    <p class="welcome-subtitle">Busca canciones, artistas o álbumes y añádelos a tu lista</p>
  </div>
  <div class="results-grid" id="resultsGrid"></div>

  <div id="dailyListSection" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h3 style="color: var(--spotify-white); margin: 0; display: flex; align-items: center;">
        📅 Mis canciones
        <span class="list-badge" id="songCount">0 canciones</span>
      </h3>
    </div>
    <table class="table table-dark table-striped table-hover" id="dailyListTable">
      <thead>
        <tr><th>#</th><th>Canción</th><th>Artista</th><th>Hora</th><th>Día</th></tr>
      </thead>
      <tbody id="dailyListBody"></tbody>
    </table>
  </div>
</div>

<!-- Toast para agregar canciones -->
<div class="toast-container-center">
  <div id="songToast" class="toast toast-spotify align-items-center border-0" role="alert">
    <div class="d-flex justify-content-center w-100">
      <div class="toast-body toast-body-spotify w-100">
         ¡Canción agregada!<br>
        <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Ya está en tu lista de espera</small>
      </div>
    </div>
  </div>
</div>

<!-- Toast para eliminar lista -->
<div class="toast-container-center">
  <div id="deleteToast" class="toast toast-spotify-delete align-items-center border-0" role="alert">
    <div class="d-flex justify-content-center w-100">
      <div class="toast-body toast-body-spotify-delete w-100">
         Lista eliminada<br>
        <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Los datos permanecen en la base de datos</small>
      </div>
    </div>
  </div>
</div>

<!-- Toast para límite de tiempo -->
<div class="toast-container-center">
  <div id="timeLimitToast" class="toast toast-spotify-time-limit align-items-center border-0" role="alert">
    <div class="d-flex justify-content-center w-100">
      <div class="toast-body toast-body-spotify-time-limit w-100">
        Límite de tiempo<br>
        <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">
          <?php echo htmlspecialchars($mensaje_limite); ?>
        </small>
      </div>
    </div>
  </div>
</div>

<!-- Toast para errores -->
<div class="toast-container-center" id="errorToastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const clientId = "a2b747acdbab4083b0a89ded7d546a77";
const clientSecret = "c60af17e44c34c258bb08adf6e37e3b7";
let searchTimeout;
let addedSongs = [];

const minutosLimite = <?php echo $minutos_limite; ?>;
const mensajeLimite = "<?php echo addslashes($mensaje_limite); ?>";
const djActivo = "<?php echo $dj_activo; ?>";

// ============================================
// SINCRONIZAR EMAIL CON EL SERVIDOR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const userEmail = localStorage.getItem('userEmail');
    if (userEmail) {
        fetch('actualizar_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'email=' + encodeURIComponent(userEmail),
            credentials: 'include'
        }).then(res => res.json()).then(console.log).catch(console.error);
    }
});

async function getToken() {
  const result = await fetch("https://accounts.spotify.com/api/token", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded", "Authorization": "Basic " + btoa(clientId + ":" + clientSecret) },
    body: "grant_type=client_credentials"
  });
  return (await result.json()).access_token;
}

async function searchAutocomplete(query) {
  if (query.length < 2) { 
    document.getElementById('autocomplete').style.display = 'none'; 
    return; 
  }
  try {
    const token = await getToken();
    const result = await fetch(`https://api.spotify.com/v1/search?q=${encodeURIComponent(query)}&type=track,artist&limit=5`, { 
      headers: { "Authorization": `Bearer ${token}` } 
    });
    const data = await result.json();
    displayAutocomplete(data, query);
  } catch(error) { 
    console.error('Error en autocomplete:', error);
    document.getElementById('autocomplete').style.display = 'none'; 
  }
}

function displayAutocomplete(data, query) {
  const container = document.getElementById('autocomplete');
  if ((!data.tracks || !data.tracks.items.length) && (!data.artists || !data.artists.items.length)) { 
    container.style.display = 'none'; 
    return; 
  }
  
  let html = '';
  
  if (data.tracks) {
    data.tracks.items.slice(0,3).forEach(track => {
      const img = track.album.images[0]?.url || '';
      const trackName = escapeHtml(track.name);
      const artistName = track.artists.map(a => escapeHtml(a.name)).join(', ');
      
      html += `<div class="autocomplete-item" onclick="selectAutocomplete('${trackName.replace(/'/g, "\\'")}')">
        ${img ? `<img src="${img}" class="autocomplete-image">` : ''}
        <div>
          <div class="autocomplete-name">${trackName}</div>
          <div class="autocomplete-artist">${artistName}</div>
        </div>
        <div class="autocomplete-type">Canción</div>
      </div>`;
    });
  }
  
  if (data.artists) {
    data.artists.items.slice(0,2).forEach(artist => {
      const img = artist.images[0]?.url || '';
      const artistName = escapeHtml(artist.name);
      
      html += `<div class="autocomplete-item" onclick="selectAutocomplete('${artistName.replace(/'/g, "\\'")}')">
        ${img ? `<img src="${img}" class="autocomplete-image">` : ''}
        <div>
          <div class="autocomplete-name">${artistName}</div>
          <div class="autocomplete-artist">Artista</div>
        </div>
        <div class="autocomplete-type">Artista</div>
      </div>`;
    });
  }
  
  container.innerHTML = html;
  container.style.display = 'block';
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function selectAutocomplete(text) {
  document.getElementById('searchInput').value = text;
  document.getElementById('autocomplete').style.display = 'none';
  searchSong();
}

async function searchSong() {
  const query = document.getElementById("searchInput").value;
  if(!query){ 
    document.getElementById('welcomeSection').style.display='block'; 
    document.getElementById('resultsGrid').innerHTML=''; 
    return; 
  }
  
  const welcome = document.getElementById("welcomeSection");
  const grid = document.getElementById("resultsGrid");
  const auto = document.getElementById("autocomplete");
  
  welcome.style.display='none';
  auto.style.display='none';
  grid.innerHTML='<div class="loading">Buscando canciones...</div>';
  
  try {
    const token = await getToken();
    const res = await fetch(`https://api.spotify.com/v1/search?q=${encodeURIComponent(query)}&type=track&limit=12`, { 
      headers: { "Authorization": `Bearer ${token}` } 
    });
    const data = await res.json();
    displayResults(data.tracks.items, query);
  } catch(error) { 
    console.error('Error en búsqueda:', error);
    grid.innerHTML='<div class="no-results">Error al buscar canciones. Inténtalo de nuevo.</div>'; 
  }
}

function displayResults(tracks, query){
  const grid = document.getElementById("resultsGrid");
  if(!tracks || !tracks.length){ 
    grid.innerHTML=`<div class="no-results">No se encontraron resultados para "${query}"</div>`; 
    return; 
  }
  
  grid.innerHTML='';
  
  tracks.forEach(track => {
    const durMin = Math.floor(track.duration_ms/60000);
    const durSec = Math.floor((track.duration_ms%60000)/1000);
    const duration = `${durMin}:${durSec<10?'0':''}${durSec}`;
    const img = track.album.images[0]?.url || 'https://via.placeholder.com/300x300/282828/FFFFFF?text=♪';
    const trackName = escapeHtml(track.name);
    const artistName = escapeHtml(track.artists.map(a => a.name).join(", "));
    const albumName = escapeHtml(track.album.name);
    
    const card = document.createElement("div");
    card.className = "track-card";
    card.innerHTML = `
      <img src="${img}" class="track-image" alt="${trackName}">
      <div class="track-name">${trackName}</div>
      <div class="track-artist">${artistName}</div>
      <div class="track-album">${albumName}</div>
      <div class="track-duration">${duration}</div>
    `;
    
    // ============================================
    // 🎯 VERSIÓN SIN ALERTAS (SOLO NOTIFICACIONES VISUALES)
    // ============================================
    card.addEventListener('click', async () => {
        try {
            const userEmail = localStorage.getItem('userEmail') || '';
            
            card.style.opacity = '0.7';
            card.style.pointerEvents = 'none';
            
            const formData = new URLSearchParams();
            formData.append('trackUri', track.uri);
            formData.append('trackName', track.name);
            formData.append('trackArtist', track.artists.map(a => a.name).join(", "));
            formData.append('userEmail', userEmail);
            
            const response = await fetch('addToPlaylist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString(),
                credentials: 'include'
            });
            
            const text = await response.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                throw new Error('No es JSON');
            }
            
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            
            if(data.success){ 
                if (data.message === 'dj_mode') {
                    showNotification('🎵 Canción agregada a la cola del DJ', 'success');
                } else {
                    showNotification('¡Canción agregada!', 'success');
                }
                addSongToDailyList(track);
            } else if (data.message === 'time_limit') {
                showNotification(data.error_message || 'Límite de tiempo alcanzado', 'time_limit');
            } else {
                showNotification(data.message || '❌ Error al agregar la canción', 'error');
            }
        } catch(err) { 
            console.error('❌ Error:', err); 
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            showNotification('❌ Error al conectar con el servidor', 'error');
        }
    });
    
    grid.appendChild(card);
  });
}

function addSongToDailyList(track){
  const now = new Date();
  const hora = now.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
  const dia = now.toLocaleDateString();
  
  addedSongs.push({
    name: track.name,
    artist: track.artists.map(a => a.name).join(", "),
    time: hora,
    date: dia,
    uri: track.uri,
    id: Date.now()
  });
  
  document.getElementById("dailyListSection").style.display = "block";
  renderDailyList();
  toggleClearButton();
  setTimeout(() => scrollToDailyList(), 500);
}

function renderDailyList(){
  const tbody = document.getElementById("dailyListBody");
  tbody.innerHTML = "";
  
  if(!addedSongs.length){ 
    document.getElementById("dailyListSection").style.display = "none"; 
    return; 
  }
  
  document.getElementById("songCount").textContent = `${addedSongs.length} ${addedSongs.length===1?'canción':'canciones'}`;
  
  const sortedSongs = [...addedSongs].sort((a, b) => b.id - a.id);
  
  sortedSongs.forEach((song, i) => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${i+1}</td>
      <td>${escapeHtml(song.name)}</td>
      <td>${escapeHtml(song.artist)}</td>
      <td>${song.time}</td>
      <td>${song.date}</td>
    `;
    tbody.appendChild(row);
  });
}

function toggleClearButton() {
  const clearBtn = document.getElementById('clearListBtn');
  if (clearBtn) {
    clearBtn.style.display = addedSongs.length > 0 ? 'block' : 'none';
  }
}

function clearSongList() {
  if (!addedSongs.length) {
    showNotification('La lista ya está vacía', 'info');
    return;
  }
  
  const listSection = document.getElementById("dailyListSection");
  listSection.classList.add('fade-out-up');
  
  setTimeout(() => {
    addedSongs = [];
    listSection.style.display = "none";
    listSection.classList.remove('fade-out-up');
    document.getElementById("dailyListBody").innerHTML = "";
    document.getElementById("songCount").textContent = "0 canciones";
    
    showNotification('Lista eliminada correctamente', 'delete');
    toggleClearButton();
    document.getElementById('welcomeSection').style.display = 'block';
  }, 500);
}

function scrollToDailyList(){
  const sec = document.getElementById("dailyListSection");
  sec.scrollIntoView({behavior:'smooth',block:'start'});
  sec.style.transition = 'all 0.5s ease';
  sec.style.boxShadow = '0 0 0 3px rgba(29,185,84,0.5)';
  setTimeout(() => {sec.style.boxShadow='none';}, 1000);
}

function createErrorToast() {
    const toastContainer = document.getElementById('errorToastContainer');
    const errorToast = document.createElement('div');
    errorToast.id = 'errorToast';
    errorToast.className = 'toast toast-spotify-error align-items-center border-0';
    errorToast.innerHTML = `
        <div class="d-flex justify-content-center w-100">
            <div class="toast-body toast-body-spotify-error w-100">
                Error<br>
                <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Inténtalo de nuevo</small>
            </div>
        </div>
    `;
    toastContainer.appendChild(errorToast);
    return errorToast;
}

function showNotification(message = '¡Canción agregada!', type = 'success'){
    let toastEl, toastBody;
    
    if (type === 'delete') {
        toastEl = document.getElementById('deleteToast');
        toastBody = toastEl.querySelector('.toast-body-spotify-delete');
        toastBody.innerHTML = `
            ${message}<br>
            <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Los datos permanecen en la base de datos</small>
        `;
    } else if (type === 'info') {
        toastEl = document.getElementById('songToast');
        toastBody = toastEl.querySelector('.toast-body-spotify');
        toastBody.innerHTML = `
            ${message}<br>
            <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Ya está en tu lista</small>
        `;
    } else if (type === 'time_limit') {
        toastEl = document.getElementById('timeLimitToast');
        toastBody = toastEl.querySelector('.toast-body-spotify-time-limit');
        toastBody.innerHTML = `
            ${message}<br>
            <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">
                ${mensajeLimite}
            </small>
        `;
    } else if (type === 'error') {
        toastEl = createErrorToast();
        toastBody = toastEl.querySelector('.toast-body-spotify-error');
        toastBody.innerHTML = `
            ${message}<br>
            <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Inténtalo de nuevo</small>
        `;
    } else {
        toastEl = document.getElementById('songToast');
        toastBody = toastEl.querySelector('.toast-body-spotify');
        toastBody.innerHTML = `
            ${message}<br>
            <small style="font-weight: 400; font-size: 14px; color: #b3b3b3;">Ya está en tu lista de espera</small>
        `;
    }
    
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    
    if (type === 'error') {
        setTimeout(() => { toastEl.remove(); }, 4000);
    }
}

document.getElementById("searchInput").addEventListener("input", e => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => searchAutocomplete(e.target.value), 300);
});

document.getElementById("searchInput").addEventListener("keypress", e => {
  if(e.key === "Enter") searchSong();
});
</script>
</body>
</html>