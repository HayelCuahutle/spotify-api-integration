<?php
session_start();

// 1. Probar inclusión de PHPMailer
echo "Probando PHPMailer...<br>";

$phpmailer_files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
foreach ($phpmailer_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO existe<br>";
        // Probar ruta alternativa
        if (file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/' . $file)) {
            echo "⚠️  Pero existe en vendor/<br>";
        }
    }
}

// 2. Probar conexión SMTP simple
echo "<br>Probando conexión SMTP...<br>";

try {
    require 'PHPMailer.php';
    require 'SMTP.php';
    require 'Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 465;
    $mail->SMTPSecure = 'ssl';
    
    // Solo probar conexión, no enviar
    $mail->SMTPAutoTLS = false;
    $mail->Timeout = 10;
    
    if (@$mail->smtpConnect()) {
        echo "✅ Conexión SMTP exitosa<br>";
        $mail->smtpClose();
    } else {
        echo "❌ Falló conexión SMTP<br>";
    }
} catch (Exception $e) {
    echo "❌ Error PHPMailer: " . $e->getMessage() . "<br>";
}

// 3. Probar inserción en BD
echo "<br>Probando base de datos...<br>";

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=tlaxcalitabeach;charset=utf8", 
        'hayel', 
        'Terminus10***'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Probar consulta simple
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result['test'] == 1) {
        echo "✅ Conexión BD exitosa<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error BD: " . $e->getMessage() . "<br>";
}
?>