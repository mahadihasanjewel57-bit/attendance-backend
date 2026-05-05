<?php
include "db.php";

echo json_encode([
    "status" => "success",
    "message" => "API working",
    "db" => $conn ? "connected" : "failed"
]);
?>
