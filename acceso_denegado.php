<?php
// acceso_denegado.php
session_start();

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión a BD
$host = 'localhost';
$dbname = 'tlaxcalitabeach';
$username = 'hayel';
$password = 'Terminus10***';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $pdo = null;
}

// ============================================
// VERIFICAR SI LA UBICACIÓN ESTÁ ACTIVA
// ============================================
$ubicacion_activa = true;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'ubicacion_activa'");
        $config = $stmt->fetch();
        $ubicacion_activa = ($config && $config['config_value'] == '1');
    } catch (Exception $e) {
        $ubicacion_activa = true;
    }
}

// Si la ubicación está desactivada, redirigir al index
if (!$ubicacion_activa) {
    header("Location: index.php");
    exit();
}

// Obtener datos del error si existen
$error_mensaje = '';
$distancia_info = '';

if (isset($_SESSION['error_ubicacion'])) {
    $distancia = round($_SESSION['error_ubicacion']['distancia'] / 1000, 2);
    $max_permitido = $_SESSION['error_ubicacion']['max_permitido'] / 1000;
    $distancia_info = "Tu distancia: $distancia km (máximo: $max_permitido km)";
    unset($_SESSION['error_ubicacion']);
}

if (isset($_GET['error'])) {
    $error_codigo = $_GET['error'];
    switch($error_codigo) {
        case 'permiso_denegado':
            $error_mensaje = 'Permiso de ubicación denegado';
            break;
        case 'ubicacion_no_disponible':
            $error_mensaje = 'No se pudo obtener tu ubicación';
            break;
        case 'tiempo_expirado':
            $error_mensaje = 'Tiempo de espera agotado';
            break;
        case 'no_soporte':
            $error_mensaje = 'Tu navegador no soporta geolocalización';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background: #f2f4f7;
            height: 100vh;
        }

        .card-custom {
            border: none;
            border-radius: 18px;
            padding: 30px;
            max-width: 420px;
        }

        .icon-circle {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: #ffe5e5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .icon-circle i {
            font-size: 42px;
            color: #d9534f;
        }
        
        .error-detail {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 10px;
        }
    </style>
</head>

<body class="d-flex justify-content-center align-items-center">

    <div class="card shadow card-custom bg-white text-center">
        
        <div class="icon-circle">
            <i class="bi bi-x-circle-fill"></i>
        </div>

        <h3 class="text-danger fw-bold">Acceso Denegado</h3>

        <p class="mt-3 mb-4">
            Este página solo puede utilizarse dentro de <strong>Tlaxcalita Beach</strong>.
        </p>
        
        <?php if (!empty($error_mensaje)): ?>
            <div class="alert alert-warning py-2 mb-3">
                <small><i class="bi bi-exclamation-triangle me-1"></i><?php echo $error_mensaje; ?></small>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($distancia_info)): ?>
            <div class="alert alert-info py-2 mb-3">
                <small><i class="bi bi-geo-alt me-1"></i><?php echo $distancia_info; ?></small>
            </div>
        <?php endif; ?>

        <a href="index.php" class="btn btn-danger w-100">Reintentar</a>
    </div>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

</body>
</html>