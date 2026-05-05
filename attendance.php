<?php
header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$emp_id = $data['pyempcde'] ?? '';
$device = $data['pydevice'] ?? '';

if ($emp_id == '' || $device == '') {
    echo json_encode(["status"=>"error","message"=>"Missing"]);
    exit;
}

echo json_encode([
    "status"=>"success",
    "message"=>"Attendance recorded",
    "emp"=>$emp_id
]);
?>
