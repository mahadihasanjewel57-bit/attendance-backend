<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

include "db.php";

// ================= HANDLE PRE-FLIGHT =================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ================= READ INPUT =================
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// fallback for form-data
if (!is_array($data)) {
    $data = $_POST;
}

// ================= SAFE VARIABLES =================
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

// ================= CHECK IF EMPLOYEE ALREADY HAS DEVICE =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

// If employee already has a device → prevent duplicate registration
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

// ================= RESPONSE =================
if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "New device registered successfully. Please login again"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database insert failed"
    ]);
}
?>
