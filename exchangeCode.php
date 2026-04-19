<?php
$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
$redirect_uri = "https://tlaxcalitabeach.com"; // Debe coincidir con tu app
$code = $_GET['code'] ?? 'AQDkopd7OkhL9_A4E63EsWvj5j_2Zrc-MucXzARuSbMvpmwlL6Jlrm5cz9U4j58EGDMAfFbPyZfQXrd5avJQHR7rGFBRySAHgH8_9Ha5f-5Zg4WnTLNulInejSaGTjrNqktQ2yLJVsC0jKp5eYkyVks6KcKui8WGov35lDTkg1iCx8Tvqa69rdEr8G9HAOwVbrSW0KWpq3kjj_afX6ustZf0vMhOGB7Fej_CaGpbX9s'; // El code que Spotify te manda

if (!$code) {
    echo "No se recibió el code.";
    exit;
}

// Inicializar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://accounts.spotify.com/api/token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode("$client_id:$client_secret"),
    "Content-Type: application/x-www-form-urlencoded"
]);

// Body
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'client_id' => $client_id,
    'client_secret' => $client_secret
]));

$response = curl_exec($ch);
curl_close($ch);

// Mostrar la respuesta JSON
$data = json_decode($response, true);
echo "<pre>";
print_r($data);
echo "</pre>";
?>
