<?php
// ver_logs.php - Para ver los logs fácilmente
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ver Logs</title>
    <style>
        body { background: #1a1a1a; color: #fff; font-family: monospace; padding: 20px; }
        pre { background: #333; padding: 10px; border-radius: 5px; overflow: auto; }
        .log-entry { border-bottom: 1px solid #444; padding: 5px; }
        button { background: #1DB954; color: #000; padding: 10px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>📋 VISOR DE LOGS</h1>
    
    <button onclick="location.href='?file=debug_completo.log'">Ver debug_completo.log</button>
    <button onclick="location.href='?file=spotify_debug.log'">Ver spotify_debug.log</button>
    <button onclick="location.href='?file=error_log'">Ver error_log</button>
    <button onclick="location.href='?clear=1'">🗑️ Limpiar logs</button>
    
    <?php
    if (isset($_GET['clear'])) {
        file_put_contents('debug_completo.log', '');
        file_put_contents('spotify_debug.log', '');
        echo "<p class='success'>✅ Logs limpiados</p>";
    }
    
    $file = $_GET['file'] ?? 'debug_completo.log';
    $allowed = ['debug_completo.log', 'spotify_debug.log', 'error_log'];
    
    if (in_array($file, $allowed) && file_exists($file)) {
        echo "<h2>Contenido de $file:</h2>";
        echo "<pre>" . htmlspecialchars(file_get_contents($file)) . "</pre>";
    } else {
        echo "<p>Selecciona un archivo para ver</p>";
    }
    ?>
</body>
</html>