<?php
session_start();

// Configuración de BD
$host = 'localhost';
$dbname = 'tlaxcalitabeach';
$username = 'hayel';
$password = 'Terminus10***';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener configuración
try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM sistema_config WHERE config_key IN ('lat_calita', 'lon_calita', 'distancia_maxima')");
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $lat_calita = isset($configs['lat_calita']) ? (float)$configs['lat_calita'] : 19.2062478;
    $lon_calita = isset($configs['lon_calita']) ? (float)$configs['lon_calita'] : -98.2332175;
    $distancia_maxima = isset($configs['distancia_maxima']) ? (int)$configs['distancia_maxima'] : 20000;
    
} catch (Exception $e) {
    die("Error cargando configuración: " . $e->getMessage());
}

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Ubicación</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 10px; }
        .config { background: #e3f2fd; }
        .test { background: #f3e5f5; }
        .result { background: #fff8e1; }
        .error { color: #d32f2f; }
        .success { color: #388e3c; }
        input { padding: 8px; margin: 5px; width: 200px; }
        button { padding: 10px 20px; background: #2196f3; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug de Ubicación</h1>
        
        <div class="section config">
            <h2>📋 Configuración Actual en BD</h2>
            <p><strong>Latitud:</strong> <?php echo $lat_calita; ?></p>
            <p><strong>Longitud:</strong> <?php echo $lon_calita; ?></p>
            <p><strong>Radio permitido:</strong> <?php echo number_format($distancia_maxima); ?> metros (<?php echo round($distancia_maxima/1000, 2); ?> km)</p>
        </div>
        
        <div class="section test">
            <h2>🧪 Probar Ubicación</h2>
            <form method="POST" action="">
                <div>
                    <label>Latitud:</label>
                    <input type="text" name="lat" placeholder="Ej: 19.2062478" required>
                </div>
                <div>
                    <label>Longitud:</label>
                    <input type="text" name="lon" placeholder="Ej: -98.2332175" required>
                </div>
                <button type="submit">Calcular Distancia</button>
            </form>
            
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat']) && isset($_POST['lon'])) {
                $lat_user = floatval($_POST['lat']);
                $lon_user = floatval($_POST['lon']);
                
                $distancia = calcularDistancia($lat_user, $lon_user, $lat_calita, $lon_calita);
                $distancia_km = round($distancia / 1000, 2);
                $permitido = $distancia <= $distancia_maxima;
            ?>
            <div class="section result">
                <h2>📊 Resultado</h2>
                <p><strong>Tus coordenadas:</strong> <?php echo $lat_user; ?>, <?php echo $lon_user; ?></p>
                <p><strong>Distancia calculada:</strong> <?php echo number_format($distancia); ?> metros (<?php echo $distancia_km; ?> km)</p>
                <p><strong>Radio permitido:</strong> <?php echo number_format($distancia_maxima); ?> metros</p>
                <p><strong>Estado:</strong> 
                    <span class="<?php echo $permitido ? 'success' : 'error'; ?>">
                        <?php echo $permitido ? '✅ DENTRO del área permitida' : '❌ FUERA del área permitida'; ?>
                    </span>
                </p>
                <p><strong>Redirigiría a:</strong> 
                    <?php echo $permitido ? 'Continuaría al sitio' : 'acceso_denegado.php'; ?>
                </p>
            </div>
            <?php } ?>
        </div>
        
        <div class="section">
            <h2>🔗 Acciones</h2>
            <p><a href="index.php">← Volver al sitio principal</a></p>
            <p><a href="admin/configuracion.php">⚙️ Ir a configuración (admin)</a></p>
            <p><button onclick="obtenerMiUbicacion()">📍 Obtener mi ubicación actual</button></p>
            <div id="miUbicacion" style="margin-top: 10px;"></div>
        </div>
    </div>
    
    <script>
    function obtenerMiUbicacion() {
        if (!navigator.geolocation) {
            alert('Tu navegador no soporta geolocalización');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                
                document.getElementById('miUbicacion').innerHTML = `
                    <div style="background: #e8f5e9; padding: 10px; border-radius: 5px;">
                        <strong>Tu ubicación actual:</strong><br>
                        Latitud: ${lat}<br>
                        Longitud: ${lon}<br>
                        <button onclick="probarEstaUbicacion(${lat}, ${lon})">Probar estas coordenadas</button>
                    </div>
                `;
            },
            function(error) {
                alert('Error obteniendo ubicación: ' + error.message);
            }
        );
    }
    
    function probarEstaUbicacion(lat, lon) {
        document.querySelector('input[name="lat"]').value = lat;
        document.querySelector('input[name="lon"]').value = lon;
        document.querySelector('form').submit();
    }
    </script>
</body>
</html>