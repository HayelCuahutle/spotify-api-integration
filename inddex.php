<?php
// CONFIGURACIONES
$clientId = "a2b747acdbab4083b0a89ded7d546a77";
$clientSecret = "c60af17e44c34c258bb08adf6e37e3b7";
$redirectUri = "https://tlaxcalitabeach.com/rating";

// Spotify te manda ?code=XXXX
if (!isset($_GET['code'])) {
    die("No se recibió el parámetro 'code' de Spotify");
}

$code = $_GET['code'];

// Petición para obtener el Access Token
$url = "https://accounts.spotify.com/api/token";
$data = [
    "grant_type" => "authorization_code",
    "code" => $code,
    "redirect_uri" => $redirectUri,
    "client_id" => $clientId,
    "client_secret" => $clientSecret
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$json = json_decode($response, true);

// Si hay error
if (isset($json["error"])) {
    echo "Error al obtener token:<br>";
    print_r($json);
    exit;
}

// Si se obtuvo el token correctamente
$accessToken = $json["access_token"];
$refreshToken = $json["refresh_token"];

// Muestra el token
echo "<h2>ACCESS TOKEN:</h2>";
echo "<textarea style='width:400px; height:120px;'>$accessToken</textarea>";

echo "<h2>REFRESH TOKEN:</h2>";
echo "<textarea style='width:400px; height:120px;'>$refreshToken</textarea>";
?>
