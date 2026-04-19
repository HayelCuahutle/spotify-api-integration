<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 DEBUG BUSCADOR.PHP</h2>";

// Test 1: Conexión a la base de datos
echo "<h3>1. Probando conexión a la base de datos...</h3>";
try {
    $host = 'localhost';
    $dbname = 'tlaxcalitabeach';
    $username = 'hayel';
    $password = 'Terminus10***';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ CONEXIÓN EXITOSA<br>";
    
    // Test 2: Verificar tabla sistema_config
    echo "<h3>2. Verificando tabla sistema_config...</h3>";
    $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'canciones_activas'");
    $config = $stmt->fetch();
    
    if ($config) {
        echo "✅ Tabla encontrada - Valor: " . $config['config_value'] . "<br>";
        $canciones_activas = $config['config_value'];
    } else {
        echo "❌ No se encontró la configuración<br>";
        $canciones_activas = '1';
    }
    
    // Test 3: Verificar estado
    echo "<h3>3. Estado del sistema de canciones...</h3>";
    if ($canciones_activas == '0') {
        echo "❌ SISTEMA DESACTIVADO - Debería mostrar página de mantenimiento<br>";
    } else {
        echo "✅ SISTEMA ACTIVADO - Debería mostrar buscador normal<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ ERROR DE CONEXIÓN: " . $e->getMessage() . "<br>";
}

// Test 4: Verificar si el código de verificación funciona
echo "<h3>4. Probando código de verificación...</h3>";
ob_start();
try {
    $host = 'localhost';
    $dbname = 'tlaxcalitabeach';
    $username = 'hayel';
    $password = 'Terminus10***';
    
    $pdo_check = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo_check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    
    $stmt = $pdo_check->query("SELECT config_value FROM sistema_config WHERE config_key = 'canciones_activas'");
    $config = $stmt->fetch();
    $canciones_activas = $config['config_value'] ?? '1';
    
    echo "✅ Código ejecutado correctamente<br>";
    echo "Valor obtenido: " . $canciones_activas . "<br>";
    
    if ($canciones_activas == '0') {
        echo "🔴 Mostraría página de mantenimiento<br>";
    } else {
        echo "🟢 Mostraría buscador normal<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error en código: " . $e->getMessage() . "<br>";
}
$output = ob_get_clean();
echo $output;

echo "<hr><h3>🎯 RESULTADO FINAL:</h3>";
echo "Si ves errores arriba, ahí está el problema.<br>";
echo "Si todo está en verde, el problema está en otra parte del código.";

echo "<hr><h3>🔗 Enlaces de prueba:</h3>";
echo "<a href='buscador.php' target='_blank'>Probar buscador.php</a><br>";
echo "<a href='sistema/dashboard.php' target='_blank'>Ir al Dashboard</a>";
?>