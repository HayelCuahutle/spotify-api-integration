<?php

// Coordenadas permitidas de Calita Beach
$lat_permitida = 19.2062478;
$lon_permitida = -98.2332175;

// Radio permitido en metros
$radio = 20000; // puedes cambiarlo a 100, 150 o 300 si quieres

if (!isset($_POST['lat']) || !isset($_POST['lon'])) {
    echo "ERROR";
    exit;
}

$lat = floatval($_POST['lat']);
$lon = floatval($_POST['lon']);

// Función para calcular distancia entre dos puntos (Haversine)
function distancia($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

$distancia = distancia($lat, $lon, $lat_permitida, $lon_permitida);

// Validar distancia
if ($distancia <= $radio) {
    echo "OK";
} else {
    echo "NO";
}
