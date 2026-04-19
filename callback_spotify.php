<?php

$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
$redirect_uri = "https://tlaxcalitabeach.com/rating/callback_spotify.php";

if (!isset($_GET['code'])) {
    die("No se recibió code.");
}

$code = $_GET['code'];

$ch = curl_init("https://accounts.spotify.com/api/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    "grant_type" => "authorization_code",
    "code" => $code,
    "redirect_uri" => $redirect_uri,
    "client_id" => $client_id,
    "client_secret" => $client_secret
]));

$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
