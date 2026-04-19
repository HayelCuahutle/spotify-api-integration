<?php
// index.php - VERIFICACIÓN DE UBICACIÓN (PANTALLA BLANCA)
session_start();

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Incluir debug
include 'debug.php';

debug_log("=== INICIO INDEX.PHP ===");
debug_log("URL: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
debug_log("Método: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

// Conexión a BD
$host = 'localhost';
$dbname = 'tlaxcalitabeach';
$username = 'hayel';
$password = 'Terminus10***';

debug_log("Intentando conectar a BD");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    debug_log("✅ Conexión BD exitosa");
} catch(PDOException $e) {
    debug_log("❌ Error de conexión BD: " . $e->getMessage());
    die("Error de conexión");
}

// Obtener configuración
$lat_calita = 19.2062478;
$lon_calita = -98.2332175;
$distancia_maxima = 20000;

debug_log("Configuración por defecto - Lat: $lat_calita, Lon: $lon_calita, Dist: $distancia_maxima");

try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM sistema_config WHERE config_key IN ('lat_calita', 'lon_calita', 'distancia_maxima')");
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    debug_log("Configuraciones cargadas de BD", $configs);
    
    if (isset($configs['lat_calita'])) $lat_calita = (float)$configs['lat_calita'];
    if (isset($configs['lon_calita'])) $lon_calita = (float)$configs['lon_calita'];
    if (isset($configs['distancia_maxima'])) $distancia_maxima = (int)$configs['distancia_maxima'];
} catch (Exception $e) {
    debug_log("Error cargando configuración: " . $e->getMessage());
}

// ============================================
// VERIFICAR SI LA UBICACIÓN ESTÁ ACTIVA
// ============================================
$ubicacion_activa = true;
debug_log("Verificando si ubicación está activa...");

try {
    $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'ubicacion_activa'");
    $config_ub = $stmt->fetch();
    
    if ($config_ub) {
        $ubicacion_activa = ($config_ub['config_value'] == '1');
        debug_log("✅ Valor encontrado en BD: " . $config_ub['config_value'] . " -> Activa: " . ($ubicacion_activa ? 'SÍ' : 'NO'));
    } else {
        debug_log("⚠️ No se encontró 'ubicacion_activa' en BD, usando valor por defecto: true");
        
        // Intentar crear la configuración
        try {
            $pdo->exec("INSERT INTO sistema_config (config_key, config_value) VALUES ('ubicacion_activa', '1')");
            debug_log("✅ Configuración 'ubicacion_activa' creada con valor '1'");
        } catch (Exception $e) {
            debug_log("❌ No se pudo crear la configuración: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    debug_log("❌ Error verificando ubicación activa: " . $e->getMessage());
}

debug_log("UBICACIÓN ACTIVA = " . ($ubicacion_activa ? 'SÍ' : 'NO'));

// Función de distancia
function calcularDistancia($lat1, $lon1, $lat2, $lon2) {
    $radio_tierra = 6371000;
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $diferencia_lat = $lat2 - $lat1;
    $diferencia_lon = $lon2 - $lon1;
    
    $a = sin($diferencia_lat/2) * sin($diferencia_lat/2) + 
         cos($lat1) * cos($lat2) * 
         sin($diferencia_lon/2) * sin($diferencia_lon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $radio_tierra * $c;
}

// Verificar sesión existente
$ubicacion_validada = false;
debug_log("Verificando sesión existente...");

if (isset($_SESSION['ubicacion_ok']) && $_SESSION['ubicacion_ok'] === true) {
    debug_log("Hay ubicacion_ok en sesión");
    if (isset($_SESSION['ubicacion_timestamp'])) {
        $tiempo_transcurrido = time() - $_SESSION['ubicacion_timestamp'];
        debug_log("Timestamp: " . $_SESSION['ubicacion_timestamp'] . ", Tiempo transcurrido: $tiempo_transcurrido segundos");
        
        if ($tiempo_transcurrido < 3600) {
            $ubicacion_validada = true;
            debug_log("✅ Sesión válida (menos de 1 hora)");
        } else {
            debug_log("⚠️ Sesión expirada (más de 1 hora), eliminando");
            unset($_SESSION['ubicacion_ok'], $_SESSION['ubicacion_timestamp']);
        }
    } else {
        debug_log("⚠️ No hay timestamp en sesión");
    }
} else {
    debug_log("No hay ubicacion_ok en sesión");
}

// ============================================
// SI LA UBICACIÓN ESTÁ DESACTIVADA, SALTAR VERIFICACIÓN
// ============================================
if (!$ubicacion_activa) {
    debug_log("🚫 UBICACIÓN DESACTIVADA - Saltando verificación");
    $_SESSION['ubicacion_ok'] = true;
    $_SESSION['ubicacion_timestamp'] = time();
    $_SESSION['ubicacion_data'] = [
        'lat' => 0,
        'lon' => 0,
        'distancia' => 0,
        'fecha' => date('Y-m-d H:i:s'),
        'ubicacion_desactivada' => true
    ];
    $ubicacion_validada = true;
    debug_log("✅ Sesión marcada como válida automáticamente");
}

// Procesar nueva ubicación POST (solo si ubicación activa)
if ($ubicacion_activa && !$ubicacion_validada && isset($_POST['lat']) && isset($_POST['lon'])) {
    debug_log("📥 Procesando POST de ubicación");
    debug_log("Lat recibida: " . $_POST['lat']);
    debug_log("Lon recibida: " . $_POST['lon']);
    
    $lat_user = floatval($_POST['lat']);
    $lon_user = floatval($_POST['lon']);
    
    $distancia = calcularDistancia($lat_user, $lon_user, $lat_calita, $lon_calita);
    
    debug_log("Distancia calculada: $distancia metros, Máximo permitido: $distancia_maxima metros");
    
    if ($distancia <= $distancia_maxima) {
        debug_log("✅ Ubicación VÁLIDA - Dentro del área");
        $_SESSION['ubicacion_ok'] = true;
        $_SESSION['ubicacion_timestamp'] = time();
        $_SESSION['ubicacion_data'] = [
            'lat' => $lat_user,
            'lon' => $lon_user,
            'distancia' => $distancia,
            'fecha' => date('Y-m-d H:i:s')
        ];
        
        debug_log("Redirigiendo a " . $_SERVER['PHP_SELF']);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        debug_log("❌ Ubicación NO VÁLIDA - Fuera del área");
        $_SESSION['error_ubicacion'] = [
            'mensaje' => 'Ubicación fuera del área permitida',
            'distancia' => $distancia,
            'max_permitido' => $distancia_maxima
        ];
        
        debug_log("Redirigiendo a acceso_denegado.php");
        header("Location: acceso_denegado.php");
        exit();
    }
}

// Si no tiene ubicación válida Y ubicación está activa, mostrar PANTALLA BLANCA
if (!$ubicacion_validada && $ubicacion_activa) {
    debug_log("❌ Ubicación no validada y activa - Mostrando pantalla blanca con JS");
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title></title>
        <style>
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
                background: white;
                overflow: hidden;
            }
            * {
                display: none;
                visibility: hidden;
            }
        </style>
    </head>
    <body>
        <script>
            console.log('📍 Iniciando obtención de ubicación...');
            
            function obtenerUbicacion() {
                <?php if (!$ubicacion_activa): ?>
                    console.log('📍 Ubicación desactivada, redirigiendo...');
                    window.location.href = window.location.href;
                    return;
                <?php endif; ?>
                
                console.log('📍 Verificando soporte de geolocation...');
                
                if (!navigator.geolocation) {
                    console.error('❌ Navegador no soporta geolocation');
                    window.location.href = 'acceso_denegado.php?error=no_soporte';
                    return;
                }
                
                console.log('📍 Solicitando ubicación...');
                
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        console.log('✅ Ubicación obtenida:', pos.coords.latitude, pos.coords.longitude);
                        
                        let form = document.createElement("form");
                        form.method = "POST";
                        form.style.display = "none";
                        
                        let latInput = document.createElement("input");
                        latInput.name = "lat";
                        latInput.value = pos.coords.latitude;
                        
                        let lonInput = document.createElement("input");
                        lonInput.name = "lon";
                        lonInput.value = pos.coords.longitude;
                        
                        form.appendChild(latInput);
                        form.appendChild(lonInput);
                        document.body.appendChild(form);
                        
                        console.log('📍 Enviando formulario...');
                        form.submit();
                    },
                    function(error) {
                        console.error('❌ Error obteniendo ubicación:', error);
                        
                        let errorCodigo = "";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorCodigo = "permiso_denegado";
                                console.error('❌ Permiso denegado por el usuario');
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorCodigo = "ubicacion_no_disponible";
                                console.error('❌ Ubicación no disponible');
                                break;
                            case error.TIMEOUT:
                                errorCodigo = "tiempo_expirado";
                                console.error('❌ Tiempo de espera agotado');
                                break;
                            default:
                                errorCodigo = "error_desconocido";
                                console.error('❌ Error desconocido');
                        }
                        
                        console.log('📍 Redirigiendo a acceso_denegado.php?error=' + errorCodigo);
                        window.location.href = 'acceso_denegado.php?error=' + errorCodigo;
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            }
            
            window.onload = function() {
                console.log('📍 Página cargada, iniciando...');
                obtenerUbicacion();
            };
            
            document.addEventListener('click', function() {
                console.log('📍 Click detectado');
                obtenerUbicacion();
            });
            
            document.addEventListener('keydown', function() {
                console.log('📍 Tecla detectada');
                obtenerUbicacion();
            });
            
            document.addEventListener('touchstart', function() {
                console.log('📍 Touch detectado');
                obtenerUbicacion();
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Verificación periódica (30 minutos)
if (isset($_SESSION['ubicacion_timestamp'])) {
    $tiempo_transcurrido = time() - $_SESSION['ubicacion_timestamp'];
    if ($tiempo_transcurrido > 1800) {
        unset($_SESSION['ubicacion_ok'], $_SESSION['ubicacion_timestamp']);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ============================================
// AQUÍ COMIENZA TU INDEX.PHP ORIGINAL
// La ubicación ya está validada
// ============================================

debug_log("✅ Ubicación validada - Mostrando contenido normal");

// ============================================
// ✅ NUEVO: VERIFICAR SI LAS CALIFICACIONES ESTÁN ACTIVAS
// ============================================

$calificaciones_activas = true; // Por defecto activas

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'calificaciones_activas'");
        $config = $stmt->fetch();
        $calificaciones_activas = ($config && $config['config_value'] == '1');
    } catch (Exception $e) {
        // Si hay error, mantener activas por defecto
    }
}

// ============================================
// FLUJO PRINCIPAL
// ============================================

// OPCIÓN 1: CALIFICACIONES DESACTIVADAS → Ir directo al buscador
if (!$calificaciones_activas) {
    // Si ya hay email en sesión, mantenerlo
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        $_SESSION['user_email'] = $_POST['email'];
    }
    
    // Redirigir al buscador
    header("Location: buscador.php");
    exit();
}

// OPCIÓN 2: CALIFICACIONES ACTIVAS → Flujo normal existente
// ============================================
// NUEVO SISTEMA DE IDENTIFICACIÓN POR DISPOSITIVO
// ============================================

// FUNCIÓN PRINCIPAL MEJORADA: Verificar estado del usuario (SISTEMA NUEVO SIN IP)
function verificarEstadoUsuarioMejorado($pdo) {
    $email = $_SESSION['user_email'] ?? null;
    
    // 1. Obtener huella del dispositivo
    $huella_hash = obtenerHuellaUsuarioPDO($pdo, $email);
    
    // 2. Verificar por huella (NO por IP)
    return verificarPorHuellaPDO($pdo, $huella_hash, $email);
}

// Función para obtener huella del dispositivo (PDO version)
function obtenerHuellaUsuarioPDO($pdo, $email = '') {
    // Si ya tenemos cookie, usarla
    if (isset($_COOKIE['user_fingerprint'])) {
        $huella_hash = $_COOKIE['user_fingerprint'];
        
        $stmt = $pdo->prepare("SELECT id FROM huellas_usuarios WHERE huella_hash = ?");
        $stmt->execute([$huella_hash]);
        
        if ($stmt->rowCount() > 0) {
            // Actualizar última actividad
            $updateStmt = $pdo->prepare("UPDATE huellas_usuarios SET ultima_actividad = NOW() WHERE huella_hash = ?");
            $updateStmt->execute([$huella_hash]);
            
            return $huella_hash;
        }
    }
    
    // Crear NUEVA huella SIN usar IP para identificación
    $components = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'time' => time(),
        'session_id' => session_id(),
        'random' => bin2hex(random_bytes(16))
    ];
    
    $huella_hash = md5(json_encode($components));
    
    // Guardar en cookie (30 días)
    setcookie('user_fingerprint', $huella_hash, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    
    // Guardar en BD
    $stmt = $pdo->prepare("
        INSERT INTO huellas_usuarios (huella_hash, email, ip_cliente, user_agent, dispositivo_id) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            email = COALESCE(VALUES(email), email),
            ultima_actividad = NOW()
    ");
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $dispositivo_id = 'device_' . substr(md5($user_agent . session_id()), 0, 12);
    
    $stmt->execute([
        $huella_hash, 
        !empty($email) ? $email : NULL,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        $user_agent,
        $dispositivo_id
    ]);
    
    return $huella_hash;
}

// Función para verificar por huella (PDO version) - NO USA IP
function verificarPorHuellaPDO($pdo, $huella_hash, $email = '') {
    $minutos_limite = obtenerTiempoLimiteConfiguradoPDO($pdo);
    
    // CASO 1: Si NO hay email (usuario no calificó aún)
    if (empty($email)) {
        // Buscar última canción por esta huella (email asociado)
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
                    return [
                        'status' => 'en_espera',
                        'minutosTranscurridos' => $minutosTranscurridos,
                        'minutosRestantes' => $minutos_limite - $minutosTranscurridos
                    ];
                } else {
                    // Si ya pasó el tiempo límite → Buscador directo
                    return ['status' => 'buscar_cancion'];
                }
            }
        }
        
        // Usuario nuevo o puede agregar → Mostrar formulario de calificación
        return ['status' => 'mostrar_formulario'];
    }
    
    // CASO 2: Si SÍ hay email (usuario ya calificó antes)
    return verificarPorEmail($pdo, $email);
}

// Función para obtener tiempo límite (PDO version)
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
        // Valor por defecto
    }
    
    return 30;
}

// VERIFICACIÓN POR EMAIL (para cuando el usuario tiene email)
function verificarPorEmail($pdo, $email) {
    // PASO 1: ¿Ya calificó hoy?
    $stmtCalificacion = $pdo->prepare("SELECT COUNT(*) as total FROM calificaciones_servicio WHERE correo = ? AND DATE(fecha_registro) = CURDATE()");
    $stmtCalificacion->execute([$email]);
    $yaCalificoHoy = $stmtCalificacion->fetch()['total'] > 0;
    
    // SI NO HA CALIFICADO HOY → Mostrar formulario
    if (!$yaCalificoHoy) {
        return ['status' => 'mostrar_formulario'];
    }
    
    // SI YA CALIFICÓ HOY → Verificar última canción
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
        
        // [<limite min] → Mensaje espera
        if ($minutosTranscurridos < $minutos_limite) {
            return [
                'status' => 'en_espera',
                'minutosTranscurridos' => $minutosTranscurridos,
                'minutosRestantes' => $minutos_limite - $minutosTranscurridos
            ];
        }
        // [≥limite min] → Buscador directo
        else {
            return ['status' => 'buscar_cancion'];
        }
    }
    
    // Si ya calificó pero NUNCA pidió canción → Buscador directo
    return ['status' => 'buscar_cancion'];
}

// FUNCIÓN PARA REDIRIGIR SEGÚN ESTADO
function manejarEstadoUsuario($estado) {
    switch ($estado['status']) {
        case 'en_espera':
            mostrarPantallaEspera($estado['minutosRestantes'], $estado['minutosTranscurridos']);
            exit();
            
        case 'buscar_cancion':
            header("Location: buscador.php");
            exit();
            
        case 'mostrar_formulario':
            // Continuar mostrando el formulario normal
            break;
    }
}

// FUNCIÓN PARA MOSTRAR PANTALLA DE ESPERA
function mostrarPantallaEspera($minutosRestantes, $minutosTranscurridos) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espera para otra canción - Tlaxcalita Beach</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
        }
        .wait-container {
            background: #000;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            border: 1px solid #333;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .logo-container {
            margin-bottom: 25px;
        }
        .logo-container img {
            max-width: 200px;
        }
        .time-display {
            font-size: 3rem;
            font-weight: bold;
            color: #ff6b35;
            margin: 20px 0;
        }
        .info-text {
            color: #b0b0b0;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="wait-container">
        <div class="logo-container">
            <img src="tlaxcala.png" alt="Logo Tlaxcalita Beach">
        </div>
        <h2>🎵 ¡Ya pediste una canción!</h2>
        <div class="time-display" id="countdown">
            <?php echo $minutosRestantes; ?> min
        </div>
        <p class="info-text">
            Pediste tu última canción hace <strong><?php echo $minutosTranscurridos; ?> minutos</strong>.
        </p>
        <p class="info-text">
            Puedes solicitar otra canción en <strong><?php echo $minutosRestantes; ?> minutos</strong>.
        </p>
        <div class="mt-4">
            <button class="btn btn-primary" onclick="window.location.href='index.php'">
                Actualizar
            </button>
        </div>
    </div>
    <script>
        let minutosRestantes = <?php echo $minutosRestantes; ?>;
        const countdownElement = document.getElementById('countdown');

        function actualizarContador() {
            if (minutosRestantes > 0) {
                minutosRestantes--;
                countdownElement.textContent = minutosRestantes + ' min';
                if (minutosRestantes === 0) {
                    countdownElement.textContent = '¡Ya puedes!';
                    countdownElement.style.color = '#28a745';
                    setTimeout(() => {
                        window.location.href = 'buscador.php';
                    }, 3000);
                }
            }
        }

        setInterval(actualizarContador, 60000);
        
        localStorage.setItem('ultimaVerificacion', Date.now());
        localStorage.setItem('minutosRestantes', minutosRestantes);
        
        if (minutosRestantes <= 0) {
            countdownElement.textContent = '¡Ya puedes!';
            countdownElement.style.color = '#28a745';
            setTimeout(() => {
                window.location.href = 'buscador.php';
            }, 3000);
        }
    </script>
</body>
</html>
<?php
exit();
}

// VERIFICACIÓN INICIAL (al cargar la página) - SISTEMA MEJORADO
if ($pdo) {
    // Usar el NUEVO sistema de identificación por dispositivo
    $estadoUsuario = verificarEstadoUsuarioMejorado($pdo);
    manejarEstadoUsuario($estadoUsuario);
}

// DESACTIVAR MOSTRAR ERRORES EN PANTALLA
error_reporting(0);
ini_set('display_errors', 0);

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Inicializar variables
$message = '';
$messageType = '';
$showThankYou = false;

// Verificar si el formulario fue enviado Y es el formulario de calificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating']) && !empty($_POST['name'])) {
    $nombre = $_POST['name'] ?? '';
    $correo = $_POST['email'] ?? '';
    $mensaje_form = $_POST['message'] ?? '';
    $rating = $_POST['rating'] ?? '';
    $telefono = $_POST['phone'] ?? '';
    $desea_llamada = isset($_POST['phone']) && !empty($_POST['phone']) ? 1 : 0;

    // Guardar email en sesión para verificación posterior
    $_SESSION['user_email'] = $correo;

    // Limpiar el formato del teléfono antes de guardar/enviar
    $telefono = preg_replace('/\s+/', '', $telefono);

    // Obtener fecha y hora actual
    date_default_timezone_set('America/Mexico_City');
    $fecha = date('d/m/Y');
    $hora = date('H:i:s');
    $fecha_registro = date('Y-m-d H:i:s');

    // PRIMERO: Intentar guardar en la base de datos SILENCIOSAMENTE
    if ($pdo) {
        try {
            $sql1 = "INSERT INTO calificaciones_servicio (nombre, correo, telefono, calificacion, comentarios, desea_llamada, ip_cliente, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([
                $nombre,
                $correo,
                $telefono,
                $rating,
                $mensaje_form,
                $desea_llamada,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            // SEGUNDO: Guardar en la TABLA usuarios_registrados
            $sql2 = "INSERT INTO usuarios_registrados (nombre, correo, telefono, fecha_registro, ip_cliente, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([
                $nombre,
                $correo,
                $telefono,
                $fecha_registro,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (Exception $e) {
            // Error silencioso - continuar
        }
    }

    // LUEGO: Enviar correos
    try {
        require 'Exception.php';
        require 'PHPMailer.php';
        require 'SMTP.php';

        // Configuración del servidor
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tlaxcalitabeach@gmail.com';
        $mail->Password = 'tvupwyjtvodzcoov';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // Destinatarios - Correo para el administrador
        $mail->setFrom('tlaxcalitabeach@gmail.com', 'Tlaxcalita Beach');
        $mail->addAddress('tlaxcalitabeach@gmail.com');
        $mail->addReplyTo($correo, $nombre);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'Nueva calificación y comentario desde tu formulario';

        // Crear el cuerpo del correo con mejor diseño
        $stars = str_repeat('⭐', $rating) . str_repeat('☆', 5 - $rating);
        $body = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Calificación</title>
            <style>
                body {
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f9fafc;
                    margin: 0;
                    padding: 20px;
                    color: #333;
                }
                .container {
                    max-width: 650px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 25px;
                    padding: 30px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
                }
                .header {
                    text-align: center;
                    margin-bottom: 25px;
                }
                .header img {
                    max-width: 250px;
                    height: auto;
                }
                .rating-stars {
                    font-size: 24px;
                    color: #ffc107;
                    text-align: center;
                    margin: 15px 0;
                }
                .info-box {
                    background-color: #f8f9fa;
                    border-radius: 15px;
                    padding: 20px;
                    margin: 15px 0;
                }
                .label {
                    font-weight: 600;
                    color: #0056b3;
                }
                .message-box {
                    background-color: #e3ebf6;
                    border-left: 4px solid #007bff;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                }
                .date-info {
                    background-color: #e9ecef;
                    border-radius: 10px;
                    padding: 12px 15px;
                    text-align: center;
                    margin: 15px 0;
                    font-weight: 500;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    color: #666;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="https://tlaxcalitabeach.com/tlaxcala.png" alt="Logo Tlaxcalita Beach">
                    <h2>Nueva Calificación de Servicio</h2>
                </div>
                <div class="rating-stars">' . $stars . '</div>
                <div class="date-info">
                    📅 Fecha: ' . $fecha . ' &nbsp; | &nbsp; 🕒 Hora: ' . $hora . '
                </div>
                <div class="info-box">
                    <p><span class="label">Calificación:</span> ' . $rating . ' estrellas</p>
                    <p><span class="label">Nombre:</span> ' . htmlspecialchars($nombre) . '</p>
                    <p><span class="label">Correo:</span> ' . htmlspecialchars($correo) . '</p>';
        
        if (!empty($telefono)) {
            $body .= '<p><span class="label">Teléfono:</span> ' . htmlspecialchars($telefono) . '</p>';
        }
        
        $body .= '
                </div>
                <div class="message-box">
                    <p><span class="label">Mensaje:</span><br>' . nl2br(htmlspecialchars($mensaje_form)) . '</p>
                </div>
                <div class="footer">
                    <p>Este mensaje fue enviado desde el formulario de calificación de Tlaxcalita Beach</p>
                    <p>© ' . date('Y') . ' Tlaxcalita Beach. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->Body = $body;

        // Versión de texto plano para clientes de email que no soportan HTML
        $altBody = "Nueva Calificación de Servicio\n\n";
        $altBody .= "Fecha: " . $fecha . " | Hora: " . $hora . "\n";
        $altBody .= "Calificación: " . $rating . " estrellas\n";
        $altBody .= "Nombre: " . $nombre . "\n";
        $altBody .= "Correo: " . $correo . "\n";
        if (!empty($telefono)) {
            $altBody .= "Teléfono: " . $telefono . "\n";
        }
        $altBody .= "Mensaje: " . $mensaje_form . "\n\n";
        $altBody .= "Este mensaje fue enviado desde el formulario de calificación de Tlaxcalita Beach";
        $mail->AltBody = $altBody;

        // Intentar enviar el correo
        if ($mail->send()) {
            // Enviar correo de agradecimiento al usuario
            try {
                $userMail = new PHPMailer(true);
                $userMail->isSMTP();
                $userMail->Host = 'smtp.gmail.com';
                $userMail->SMTPAuth = true;
                $userMail->Username = 'tlaxcalitabeach@gmail.com';
                $userMail->Password = 'tvupwyjtvodzcoov';
                $userMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $userMail->Port = 465;
                $userMail->CharSet = 'UTF-8';

                $userMail->setFrom('tlaxcalitabeach@gmail.com', 'Tlaxcalita Beach');
                $userMail->addAddress($correo, $nombre);
                $userMail->isHTML(true);
                $userMail->Subject = '¡Gracias por tu calificación!';
                $userMail->Body = "
                    <h2>¡Gracias por calificar nuestro servicio!</h2>
                    <p>Hola <strong>{$nombre}</strong>,</p>
                    <p>Agradecemos mucho que hayas tomado el tiempo para calificar nuestro servicio con <strong>{$rating} estrellas</strong>.</p>
                    <p>Tu opinión es muy importante para nosotros y nos ayuda a mejorar continuamente.</p>
                    <p>Hemos recibido tu comentario: <em>\"{$mensaje_form}\"</em></p>
                    <br>
                    <p>Atentamente,<br>El equipo de Tlaxcalita Beach</p>
                ";
                $userMail->AltBody = "¡Gracias por calificar nuestro servicio!\n\nHola {$nombre},\n\nAgradecemos mucho que hayas tomado el tiempo para calificar nuestro servicio con {$rating} estrellas.\nTu opinión es muy importante para nosotros y nos ayuda a mejorar continuamente.\n\nHemos recibido tu comentario: \"{$mensaje_form}\"\n\nAtentamente,\nEl equipo de Tlaxcalita Beach";
                $userMail->send();
            } catch (Exception $e) {
                // Si falla el correo al usuario, continuar de todos modos
            }
            $showThankYou = true;
        }
    } catch (Exception $e) {
        // Si hay cualquier error, igual mostrar agradecimiento
        $showThankYou = true;
    }

    // Limpiar POST después de procesar
    $_POST = array();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Calificación de servicio</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f9fafc, #e3ebf6);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .rating-container {
            background: #000000;
            border-radius: 25px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            max-width: 650px;
            width: 100%;
            text-align: center;
            animation: fadeIn 0.8s ease-in-out;
            color: #ffffff;
        }
        .logo-container {
            margin-bottom: 25px;
        }
        .logo-container img {
            max-width: 250px;
            height: auto;
            width: 100%;
        }
        .rating-container h2 {
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 15px;
        }
        .opinion-text {
            font-size: 1.1rem;
            color: #ffffff !important;
            margin-bottom: 20px;
            font-weight: 400;
        }
        .star-rating {
            direction: rtl;
            display: inline-flex;
            font-size: 3rem;
            justify-content: center;
            margin: 25px 0;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            color: #666;
            cursor: pointer;
            transition: transform 0.25s, color 0.25s;
            padding: 0 5px;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107;
            transform: scale(1.2);
        }
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        #feedbackForm {
            display: none;
            margin-top: 25px;
            text-align: left;
            animation: slideDown 0.6s ease;
        }
        #feedbackForm .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            background-color: #1a1a1a;
            color: #ffffff;
        }
        #feedbackForm h5 {
            font-weight: 600;
            color: #ffffff;
        }
        #feedbackForm .form-label {
            color: #e0e0e0;
        }
        #feedbackForm .form-control {
            background-color: #2a2a2a;
            border: 1px solid #444;
            color: #ffffff;
        }
        #feedbackForm .form-control::placeholder {
            color: #888;
        }
        #feedbackForm .form-control:focus {
            background-color: #2a2a2a;
            border-color: #007bff;
            color: #ffffff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        #feedbackForm button {
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            padding: 10px 20px;
        }
        #feedbackForm button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .alert {
            border-radius: 12px;
            margin-top: 20px;
            font-weight: 500;
        }
        .thank-you {
            text-align: center;
            padding: 30px;
            animation: fadeIn 0.8s ease-in-out;
            background: #000;
            color: #fff;
            border-radius: 25px;
        }
        .thank-you h3 {
            color: #28a745;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .thank-you p {
            color: #e0e0e0;
            margin-bottom: 10px;
        }
        .hidden {
            display: none;
        }
        .visible {
            display: block;
        }
        .phone-field {
            display: none;
            margin-top: 10px;
        }
        .music-section {
            margin-top: 35px;
            padding: 30px 25px;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-radius: 20px;
            border: 1px solid #444;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .music-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #00c6ff, #007bff);
            border-radius: 20px 20px 0 0;
        }
        .music-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: inline-block;
            background: linear-gradient(135deg, #007bff, #00c6ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 10px rgba(0, 198, 255, 0.3));
        }
        .music-question {
            font-size: 1.4rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 25px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .music-subtitle {
            font-size: 1rem;
            color: #b0b0b0;
            margin-bottom: 25px;
            font-weight: 400;
        }
        .music-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .btn-music {
            border-radius: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 14px 35px;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            min-width: 140px;
            position: relative;
            overflow: hidden;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-yes {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-yes:hover {
            background: linear-gradient(135deg, #218838, #1e9e8a);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.4);
        }
        .btn-no {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.3);
        }
        .btn-no:hover {
            background: linear-gradient(135deg, #5a6268, #3d4348);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.4);
        }
        .btn-music::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .btn-music:hover::before {
            left: 100%;
        }
        .btn-icon {
            font-size: 1.3rem;
            transition: transform 0.3s ease;
        }
        .btn-music:hover .btn-icon {
            transform: scale(1.2);
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .rating-container p {
            color: #ffffff !important;
        }
        .form-check-input:checked {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        /* NUEVO: Estilo para el botón de regreso al sitio principal */
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            color: white;
        }

        .btn-primary i {
            margin-right: 8px;
        }
        
        @media (max-width: 576px) {
            .music-buttons {
                flex-direction: column;
                align-items: center;
            }
            .btn-music {
                width: 100%;
                max-width: 250px;
            }
            .music-question {
                font-size: 1.2rem;
            }
        }

        /* Debug info (solo para admin) */
        .debug-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: #0f0;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 11px;
            max-width: 300px;
            max-height: 200px;
            overflow: auto;
            z-index: 9999;
            display: none;
        }
        
        .debug-info.visible {
            display: block;
        }
        
        .debug-toggle {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 11px;
            cursor: pointer;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
    <button class="debug-toggle" onclick="toggleDebug()">🐛 Ver Debug</button>
    <div class="debug-info" id="debugInfo">
        <strong>🔍 DEBUG INFO</strong><br>
        Ubicación activa: <?php echo $ubicacion_activa ? 'SÍ' : 'NO'; ?><br>
        Ubicación validada: <?php echo $ubicacion_validada ? 'SÍ' : 'NO'; ?><br>
        Sesión ID: <?php echo session_id(); ?><br>
        Timestamp: <?php echo $_SESSION['ubicacion_timestamp'] ?? 'none'; ?><br>
        <small>Revisa debug_log.txt para más detalles</small>
    </div>
    <script>
        function toggleDebug() {
            document.getElementById('debugInfo').classList.toggle('visible');
        }
    </script>
    <?php endif; ?>

    <div class="rating-container">
        <!-- Sección de calificación (se muestra inicialmente) -->
        <div id="ratingSection" class="<?php if($showThankYou) echo 'hidden'; ?>">
            <!-- Logo en la parte superior -->
            <div class="logo-container">
                <img src="tlaxcala.png" alt="Logo Tlaxcalita Beach" class="logo">
            </div>
            <h2 class="mb-3">⭐ Califica nuestro servicio</h2>
            <p class="opinion-text">Tu opinión nos ayuda a mejorar</p>

            <!-- Estrellas -->
            <div class="star-rating">
                <input type="radio" id="star5" name="rating" value="5">
                <label for="star5">&#9733;</label>
                <input type="radio" id="star4" name="rating" value="4">
                <label for="star4">&#9733;</label>
                <input type="radio" id="star3" name="rating" value="3">
                <label for="star3">&#9733;</label>
                <input type="radio" id="star2" name="rating" value="2">
                <label for="star2">&#9733;</label>
                <input type="radio" id="star1" name="rating" value="1">
                <label for="star1">&#9733;</label>
            </div>

            <!-- Formulario -->
            <div id="feedbackForm">
                <div class="card p-4">
                    <h5 class="mb-3">📝 Ayúdanos a mejorar</h5>
                    <form id="formFeedback" method="POST" action="">
                        <input type="hidden" name="rating" id="selectedRating" value="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="name" id="name" placeholder="Tu nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" name="email" id="email" placeholder="tucorreo@ejemplo.com" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Comentarios</label>
                            <textarea class="form-control" name="message" id="message" rows="3" placeholder="Escribe tu opinión aquí..." required></textarea>
                        </div>
                        <!-- Checkbox y campo de teléfono con el texto modificado -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="addPhoneCheckbox" name="desea_llamada" value="1">
                                <label class="form-check-label" for="addPhoneCheckbox">
                                    Deseo recibir una llamada telefónica para un trato personalizado
                                </label>
                            </div>
                            <div id="phoneField" class="phone-field">
                                <label for="phone" class="form-label mt-2">Número de teléfono</label>
                                <input type="tel" class="form-control" name="phone" id="phone" placeholder="Ingresa tu número de teléfono">
                                <div class="form-text">El número se formateará automáticamente mientras escribes</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enviar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sección de agradecimiento CORREGIDA -->
        <div id="thankYou" class="thank-you <?php if(!$showThankYou) echo 'hidden'; ?>">
            <h3>¡Gracias por tu calificación! 🎉</h3>
            <p>Hemos recibido tus comentarios y te hemos enviado un correo de confirmación.</p>
            
            <?php
            // ✅ NUEVO: Verificar calificaciones y canciones activas
            try {
                $host = 'localhost';
                $dbname = 'tlaxcalitabeach';
                $username = 'hayel';
                $password = 'Terminus10***';
                $pdo_check = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
                $pdo_check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
                
                // Verificar canciones activas
                $stmt = $pdo_check->query("SELECT config_value FROM sistema_config WHERE config_key = 'canciones_activas'");
                $config = $stmt->fetch();
                $canciones_activas = $config['config_value'] ?? '1';
                
                // ✅ NUEVO: Verificar calificaciones activas
                $stmt_calif = $pdo_check->query("SELECT config_value FROM sistema_config WHERE config_key = 'calificaciones_activas'");
                $config_calif = $stmt_calif->fetch();
                $calificaciones_activas = $config_calif['config_value'] ?? '1';
                
            } catch (Exception $e) {
                $canciones_activas = '1';
                $calificaciones_activas = '1';
            }
            
            if ($canciones_activas == '0'): 
            // CANCIONES DESACTIVADAS: Solo mostrar mensaje de agradecimiento y botón para regresar
            ?>
                <p>Tu opinión es muy importante para nosotros y nos ayuda a mejorar continuamente.</p>
                
                <!-- Botón para regresar al sitio principal (SOLO cuando canciones desactivadas) -->
                <div class="mt-4">
                    <button class="btn btn-primary" onclick="window.location.href='https://tlaxcalitabeach.com/rating/'">
                        <i class="fas fa-home me-2"></i> Volver al sitio principal
                    </button>
                </div>
            
            <?php else: 
            // CANCIONES ACTIVAS: Decidir qué mostrar según calificaciones
            if ($calificaciones_activas == '1'):
                // ✅ CALIFICACIONES ACTIVAS: Mostrar sección musical normal
            ?>
                <!-- Sección musical -->
                <div class="music-section pulse-animation">
                    <div class="music-icon">🎵</div>
                    <h4 class="music-question">¿Deseas escuchar una canción?</h4>
                    <p class="music-subtitle">Descubre nuestra selección musical especial</p>
                    <div class="music-buttons">
                        <!-- Botón Sí con nuevo diseño -->
                        <button class="btn-music btn-yes" onclick="window.location.href='buscador.php'">
                            <span class="btn-icon">✓</span> Sí, me encantaría
                        </button>
                        
                        <!-- Botón No con nuevo diseño -->
                        <button class="btn-music btn-no" onclick="window.location.href='https://tlaxcalitabeach.com/rating/'">
                            <span class="btn-icon">✕</span> No, gracias
                        </button>
                    </div>
                </div>
            <?php else: 
                // ✅ CALIFICACIONES DESACTIVADAS: Mostrar botón directo al buscador
            ?>
                <!-- Sección directa al buscador -->
                <div class="music-section pulse-animation">
                    <div class="music-icon">🎵</div>
                    <h4 class="music-question">Listo para buscar música</h4>
                    <p class="music-subtitle">Ahora puedes buscar y solicitar canciones directamente</p>
                    <div class="music-buttons">
                        <!-- Botón directo al buscador -->
                        <button class="btn-music btn-yes" onclick="window.location.href='buscador.php'">
                            <span class="btn-icon">🔍</span> Ir al Buscador de Canciones
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // URL de redirección directa para escribir reseña en Google usando tu Place ID
        const googleReviewURL = "https://search.google.com/local/writereview?placeid=ChIJD0ZfNeLbz4URxF7wPo3scjI";

        // Manejar la selección de estrellas
        document.querySelectorAll('input[name="rating"]').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.value);
                document.getElementById('selectedRating').value = rating;

                // Verificar si ya calificó antes (usando localStorage)
                const yaCalifico = localStorage.getItem('yaCalificoGoogle');

                if (rating <= 3) {
                    // Para calificaciones bajas (1-3 estrellas), siempre mostrar formulario
                    document.getElementById('feedbackForm').style.display = 'block';
                } else {
                    // Para calificaciones altas (4-5 estrellas)
                    if (yaCalifico) {
                        // Si YA calificó antes, mostrar formulario
                        document.getElementById('feedbackForm').style.display = 'block';
                    } else {
                        // Si es la PRIMERA vez, redirigir a Google y marcar que ya calificó
                        localStorage.setItem('yaCalificoGoogle', 'true');
                        window.location.href = googleReviewURL;
                    }
                }
            });
        });

        // Manejar el checkbox de teléfono
        document.getElementById('addPhoneCheckbox').addEventListener('change', function() {
            const phoneField = document.getElementById('phoneField');
            if (this.checked) {
                phoneField.style.display = 'block';
            } else {
                phoneField.style.display = 'none';
                document.getElementById('phone').value = ''; // Limpiar el campo si se desmarca
            }
        });

        // Formatear automáticamente el número de teléfono
        document.getElementById('phone').addEventListener('input', function(e) {
            // Obtener el valor actual y eliminar todos los caracteres que no sean dígitos
            let input = e.target.value.replace(/\D/g, '');
            
            // Limitar a 10 dígitos
            input = input.substring(0, 10);
            
            // Aplicar formato: XXX XXX XXXX
            let formattedInput = '';
            for (let i = 0; i < input.length; i++) {
                if (i === 3 || i === 6) {
                    formattedInput += ' ';
                }
                formattedInput += input[i];
            }
            
            // Actualizar el valor del campo
            e.target.value = formattedInput;
        });

        // Si hay un mensaje de éxito, asegurarse de que se muestre la sección correcta
        <?php if($showThankYou): ?>
            document.getElementById("ratingSection").classList.add("hidden");
            document.getElementById("thankYou").classList.remove("hidden");
        <?php endif; ?>

        // Guardar el email del usuario cuando envíe el formulario
        document.getElementById('formFeedback').addEventListener('submit', function(e) {
            const userEmail = document.getElementById('email').value;
            localStorage.setItem('userEmail', userEmail);
        });
    </script>
    
    <!-- NUEVO: Script para identificación por dispositivo -->
    <script>
    // Recoger datos del navegador para huella digital única por dispositivo
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const datosNavegador = {
                screen_res: window.screen.width + 'x' + window.screen.height + 'x' + (window.screen.colorDepth || 24),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                cookies: navigator.cookieEnabled ? 'true' : 'false',
                languages: navigator.languages ? navigator.languages.join(',') : navigator.language,
                platform: navigator.platform || 'unknown',
                do_not_track: navigator.doNotTrack || 'unknown',
                hardware_concurrency: navigator.hardwareConcurrency || 'unknown'
            };
            
            // Enviar datos al servidor para generar huella única
            fetch('guardar_huella.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(datosNavegador)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ Huella digital única generada para este dispositivo');
                    console.log('🔑 Hash:', data.huella);
                }
            })
            .catch(err => {
                console.log('⚠️ Error generando huella:', err);
            });
        } catch (e) {
            console.log('⚠️ Error recopilando datos del navegador:', e);
        }
    });

    // También enviar datos cuando se envía el formulario
    document.getElementById('formFeedback')?.addEventListener('submit', function() {
        const datosNavegador = {
            screen_res: window.screen.width + 'x' + window.screen.height,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
        
        fetch('guardar_huella.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(datosNavegador)
        });
    });
    </script>
</body>
</html>