<?php
// config_tiempo.php - Sistema centralizado de configuración de tiempo
// Ubicación: En la raíz del proyecto público

function obtenerMinutosLimite() {
    static $minutos_limite = null;
    
    if ($minutos_limite === null) {
        // Valor por defecto
        $minutos_limite = 30;
        
        // Intentar obtener de la base de datos
        try {
            $host = 'localhost';
            $dbname = 'tlaxcalitabeach';
            $username = 'hayel';
            $password = 'Terminus10*';
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            
            $stmt = $pdo->query("SELECT config_value FROM sistema_config WHERE config_key = 'minutos_limite'");
            $config = $stmt->fetch();
            
            if ($config && $config['config_value'] !== null) {
                $valor = (int)$config['config_value'];
                // Validar que esté entre 0 y 60
                if ($valor >= 0 && $valor <= 60) {
                    $minutos_limite = $valor;
                }
            }
            
        } catch (Exception $e) {
            // Silenciar error, usar valor por defecto
            error_log("Error obteniendo minutos límite: " . $e->getMessage());
        }
    }
    
    return $minutos_limite;
}

function obtenerMensajeLimite($minutos_limite = null) {
    if ($minutos_limite === null) {
        $minutos_limite = obtenerMinutosLimite();
    }
    
    if ($minutos_limite === 0) {
        return "Sin límite de tiempo";
    } elseif ($minutos_limite === 1) {
        return "Límite: 1 canción por minuto";
    } elseif ($minutos_limite === 60) {
        return "Límite: 1 canción por hora";
    } else {
        return "Límite: 1 canción cada {$minutos_limite} minutos";
    }
}

function puedeSolicitarCancion($ultima_cancion_timestamp) {
    $minutos_limite = obtenerMinutosLimite();
    
    // Si el límite es 0, siempre puede solicitar
    if ($minutos_limite === 0) {
        return [
            'puede' => true,
            'minutos_restantes' => 0,
            'mensaje' => ''
        ];
    }
    
    $ahora = time();
    $diferencia_minutos = ($ahora - $ultima_cancion_timestamp) / 60;
    
    if ($diferencia_minutos < $minutos_limite) {
        $minutos_restantes = ceil($minutos_limite - $diferencia_minutos);
        return [
            'puede' => false,
            'minutos_restantes' => $minutos_restantes,
            'minutos_transcurridos' => floor($diferencia_minutos),
            'mensaje' => "Espera {$minutos_restantes} minuto" . ($minutos_restantes > 1 ? 's' : '')
        ];
    }
    
    return [
        'puede' => true,
        'minutos_restantes' => 0,
        'mensaje' => ''
    ];
}
?>