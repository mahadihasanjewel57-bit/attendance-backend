<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');

if ($emp_id === '' || $device === '') {
    echo json_encode(["status"=>"error","message"=>"Missing parameters"]);
    exit;
}

$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode(["status"=>"error","message"=>"Already registered"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO emdevice (pyempcde, pydevice) VALUES (?, ?)");
$stmt->bind_param("ss", $emp_id, $device);

if ($stmt->execute()) {
    echo json_encode(["status"=>"success","message"=>"Device registered"]);
} else {
    echo json_encode(["status"=>"error","message"=>"DB error"]);
}
?>
