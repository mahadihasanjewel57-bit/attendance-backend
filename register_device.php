<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

include "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ================= INPUT =================
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');

// ================= VALIDATION =================
if ($emp_id === '' || $device === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing parameters"
    ]);
    exit;
}

// ================= CHECK: DEVICE ALREADY USED =================
$stmt = $conn->prepare("SELECT pyempcde FROM emdevice WHERE pydevice=?");
$stmt->bind_param("s", $device);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();

    echo json_encode([
        "status" => "error",
        "message" => "This device is already registered with Employee ID: " . $row['pyempcde']
    ]);
    exit;
}

// ================= CHECK: EMPLOYEE ALREADY HAS DEVICE =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Employee already has a registered device"
    ]);
    exit;
}

// ================= INSERT =================
$stmt = $conn->prepare("INSERT INTO emdevice (pyempcde, pydevice) VALUES (?, ?)");
$stmt->bind_param("ss", $emp_id, $device);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Device registered successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database insert failed"
    ]);
}
?>
