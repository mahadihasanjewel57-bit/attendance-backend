<?php
header("Content-Type: application/json");
include "db.php";
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
$data = json_decode(file_get_contents("php://input"), true);

$emp_id = $data['pyempcde'] ?? '';
$device = $data['pydevice'] ?? '';

if (!$emp_id || !$device) {
    echo json_encode(["status"=>"error","message"=>"Missing parameters"]);
    exit;
}

// Check employee
$stmt = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["status"=>"error","message"=>"Employee not found"]);
    exit;
}

$emp = $res->fetch_assoc();

// Check device match
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["status"=>"error","message"=>"Device not registered"]);
    exit;
}

$row = $res->fetch_assoc();

if ($row['pydevice'] !== $device) {
    echo json_encode([
        "status"=>"error",
        "message"=>"Device is not register for this ID, Please contact HRD"
    ]);
    exit;
}

// success
echo json_encode([
    "status"=>"success",
    "employee"=>$emp
]);
