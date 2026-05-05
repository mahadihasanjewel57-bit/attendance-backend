<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

if ($emp_id === '' || $device === '' || !$lat || !$lng) {
    echo json_encode(["status"=>"error","message"=>"Missing parameters"]);
    exit;
}

echo json_encode([
    "status"=>"success",
    "message"=>"API working",
    "emp"=>$emp_id
]);
?>
