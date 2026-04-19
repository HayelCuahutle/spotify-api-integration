<?php

$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$redirect_uri = "https://tlaxcalitabeach.com/rating/callback_spotify.php";

// Scopes válidos, en una sola línea
$scope = "user-read-playback-state user-modify-playback-state user-read-currently-playing playlist-read-private playlist-modify-private playlist-modify-public streaming";

$url = "https://accounts.spotify.com/authorize?"
     . "client_id=$client_id"
     . "&response_type=code"
     . "&redirect_uri=" . urlencode($redirect_uri)
     . "&scope=" . urlencode($scope);

echo "<a href='$url'>GENERAR NUEVO REFRESH TOKEN</a>";

?>
