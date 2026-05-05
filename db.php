<?php
$host = "mysql.railway.internal";
$user = "root";
$pass = "jDUfIAhncLvVDCmdhIHNRVRStkeygPVs";
$db   = "railway";
$port = 3306;
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "DB Connection failed"
    ]));
}
?>
