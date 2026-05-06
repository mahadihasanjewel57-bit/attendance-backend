<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

include "db.php";

// ================= INPUT =================
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');

if ($emp_id == '' || $device == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing employee or device"
    ]);
    exit;
}

// ================= DEVICE HASH =================
$deviceHash = hash('sha256', $device);

// ================= GET STORED DEVICE =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Device not registered"
    ]);
    exit;
}

$row = $res->fetch_assoc();
$storedDevice = $row['pydevice'];

// ================= DEVICE MATCH =================
if ($storedDevice === $deviceHash) {

    // ================= GET EMPLOYEE DATA =================
    $emp = $conn->prepare("SELECT pyempcde, pyempnam FROM pyemp WHERE pyempcde=? LIMIT 1");
    $emp->bind_param("s", $emp_id);
    $emp->execute();
    $empRes = $emp->get_result();
    $empData = $empRes->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "employee" => $empData
    ]);
    exit;
}

// ================= DEVICE MISMATCH =================
// Instead of hard blocking, return controlled response
echo json_encode([
    "status" => "device_mismatch",
    "message" => "This employee is registered on another device",
    "allow_rebind" => true
]);
exit;
?>
