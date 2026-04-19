<?php
// CONFIGURACIÓN
$clientId = "a2b747acdbab4083b0a89ded7d546a77";
$redirectUri = "https://tlaxcalitabeach.com/rating";
$scope = "user-modify-playback-state user-read-playback-state";

$params = http_build_query([
    "client_id" => $clientId,
    "response_type" => "code",
    "redirect_uri" => $redirectUri,
    "scope" => $scope
]);

// Redirige al usuario a Spotify
header("Location: https://accounts.spotify.com/authorize?$params");
exit;
?>
