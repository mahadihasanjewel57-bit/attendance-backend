<?php
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

$emp_id = trim($data['pyempcde'] ?? '');
$device  = trim($data['pydevice'] ?? '');

if ($emp_id == '' || $device == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing data"
    ]);
    exit;
}

// check employee
$stmt = $conn->prepare("SELECT pyempcde, pyempnam FROM pyemp WHERE pyempcde=? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Employee not found"
    ]);
    exit;
}

$emp = $res->fetch_assoc();

// device check
$stmt2 = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");
$stmt2->bind_param("s", $emp_id);
$stmt2->execute();
$res2 = $stmt2->get_result();

if ($res2->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Device not registered"
    ]);
    exit;
}

$row = $res2->fetch_assoc();

if ($row['pydevice'] !== $device) {
    echo json_encode([
        "status" => "error",
        "message" => "Device mismatch"
    ]);
    exit;
}

// success
echo json_encode([
    "status" => "success",
    "employee" => $emp
]);
exit;
?>
