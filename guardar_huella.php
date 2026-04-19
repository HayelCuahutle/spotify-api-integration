<?php
session_start();

$host = 'localhost';
$dbname = 'tlaxcalitabeach';
$username = 'hayel';
$password = 'Terminus10***';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
} catch(PDOException $e) {
    $pdo = null;
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generar huella SIN usar IP
    $components = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'screen_res' => $_POST['screen_res'] ?? '',
        'timezone' => $_POST['timezone'] ?? '',
        'languages' => $_POST['languages'] ?? '',
        'platform' => $_POST['platform'] ?? '',
        'session_id' => session_id(),
        'time' => time(),
        'random' => bin2hex(random_bytes(8))
    ];
    
    $huella_hash = md5(json_encode($components));
    
    // Guardar en cookie (30 días)
    setcookie('user_fingerprint', $huella_hash, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    
    // Guardar en BD
    $stmt = $pdo->prepare("
        INSERT INTO huellas_usuarios (huella_hash, ip_cliente, user_agent, dispositivo_id) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE ultima_actividad = NOW()
    ");
    
    $dispositivo_id = 'web_' . substr(md5($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 10);
    $stmt->execute([
        $huella_hash, 
        $_SERVER['REMOTE_ADDR'], 
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $dispositivo_id
    ]);
    
    echo json_encode(['success' => true, 'huella' => $huella_hash]);
}
?>
