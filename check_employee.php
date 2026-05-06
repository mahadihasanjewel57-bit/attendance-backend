<?php
header("Content-Type: application/json");
include "db.php";
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
$data = json_decode(file_get_contents("php://input"), true);
$emp_id = trim($data['pyempcde'] ?? '');

if (!$emp_id) {
    echo json_encode(["status"=>"error","message"=>"Employee ID required"]);
    exit;
}

// Check device table
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode(["status"=>"registered"]);
    exit;
}

// Check employee master
$stmt = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["status"=>"error","message"=>"Invalid Employee ID"]);
    exit;
}

$row = $res->fetch_assoc();

echo json_encode([
    "status"=>"new",
    "emp_name"=>$row['pyempnam']
]);
