<?php
header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');

if ($emp_id == '' || $device == '') {
    echo json_encode(["status"=>"error","message"=>"Missing"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO emdevice (pyempcde, pydevice) VALUES (?,?)");
$stmt->bind_param("ss", $emp_id, $device);

if ($stmt->execute()) {
    echo json_encode(["status"=>"success","message"=>"Device registered"]);
} else {
    echo json_encode(["status"=>"error","message"=>"Failed"]);
}
?>
