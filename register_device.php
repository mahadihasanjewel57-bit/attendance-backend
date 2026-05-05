<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

include "db.php";

// ✅ Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ================= READ INPUT =================
$data = json_decode(file_get_contents("php://input"), true);

// fallback for form-data
if (!$data) {
    $data = $_POST;
}

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat = $data['lat'] ?? '';
$lng = $data['lng'] ?? '';
// ================= VALIDATION =================
if (!$emp_id || !$device) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing data"
    ]);
    exit;
}

// ================= CHECK IF ALREADY REGISTERED =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Device already registered for this employee"
    ]);
    exit;
}

// ================= INSERT DEVICE =================
$stmt = $conn->prepare("INSERT INTO emdevice (pyempcde, pydevice) VALUES (?, ?)");
$stmt->bind_param("ss", $emp_id, $device);

// ================= RESULT =================
if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "New Device Registered. Please login again"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Register failed"
    ]);
}
?>