<?php
$client_id = "a2b747acdbab4083b0a89ded7d546a77";
$client_secret = "c60af17e44c34c258bb08adf6e37e3b7";
$redirect_uri = "https://tlaxcalitabeach.com";

if(!isset($_GET['code'])){
    $scopes = urlencode("playlist-modify-public playlist-modify-private");
    $auth_url = "https://accounts.spotify.com/authorize?response_type=code&client_id=$client_id&scope=$scopes&redirect_uri=$redirect_uri";
    echo "<a href='$auth_url'>Haz clic aquí para autorizar tu app</a>";
    exit;
}

$code = $_GET['code'];
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,"https://accounts.spotify.com/api/token");
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_HTTPHEADER,[
    "Authorization: Basic ".base64_encode("$client_id:$client_secret"),
    "Content-Type: application/x-www-form-urlencoded"
]);
curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query([
    'grant_type'=>'authorization_code',
    'code'=>$code,
    'redirect_uri'=>$redirect_uri
]));
$response = curl_exec($ch);
$data = json_decode($response,true);
curl_close($ch);

echo "<h3>Tokens obtenidos:</h3>";
echo "<strong>Access Token:</strong> ".$data['access_token']."<br>";
echo "<strong>Refresh Token:</strong> ".$data['refresh_token']."<br>";
?>

