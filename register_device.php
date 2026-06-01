<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

include "db.php";

$data   = json_decode(file_get_contents("php://input"), true);
$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');

if ($emp_id === '' || $device === '') {
    echo json_encode(["status" => "error", "message" => "Missing data"]);
    exit;
}

// Check if this device is already registered to another employee
$stmt = $conn->prepare("SELECT pyempcde FROM emdevice WHERE pydevice = ? LIMIT 1");
$stmt->bind_param("s", $device);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status"  => "error",
        "message" => "This device is already registered to another employee",
    ]);
    exit;
}

// Check if this employee already has a device
$stmt2 = $conn->prepare("SELECT pyempcde FROM emdevice WHERE pyempcde = ? LIMIT 1");
$stmt2->bind_param("s", $emp_id);
$stmt2->execute();
$res2 = $stmt2->get_result();

if ($res2->num_rows > 0) {
    echo json_encode([
        "status"  => "error",
        "message" => "This employee already has a registered device",
    ]);
    exit;
}

// Insert
$ins = $conn->prepare("INSERT INTO emdevice (pyempcde, pydevice) VALUES (?, ?)");
$ins->bind_param("ss", $emp_id, $device);

if ($ins->execute()) {
    echo json_encode([
        "status"  => "success",
        "message" => "New Device registered, Please login again",
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Registration failed, please try again",
    ]);
}
?>
