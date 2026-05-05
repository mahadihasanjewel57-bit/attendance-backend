<?php
$host = "trolley.proxy.rlwy.net";
$user = "root";
$pass = "BRDAYvnBiDnOSHsPePMHYXJQHzHUpByv";
$db   = "railway";
$port = 29801;
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status"=>"error","message"=>"DB Connection failed"]));
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
?>
