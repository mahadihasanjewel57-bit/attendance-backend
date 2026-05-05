<?php
$host = "YOUR_RAILWAY_HOST";
$user = "YOUR_DB_USER";
$pass = "BfOvPrkVNErxGLKPpjIRrWKrCQVNhPHo";
$db   = "YOUR_DB_NAME";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status"=>"error","message"=>"DB Connection failed"]));
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
?>
