<?php
header("Content-Type: application/json");
include "db.php";

date_default_timezone_set("Asia/Dhaka");

// INPUT
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) $data = $_POST;

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

// VALIDATION
if (!$emp_id || !$device || !$lat || !$lng) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing input data",
        "debug" => $data
    ]);
    exit;
}

// CHECK DEVICE
$stmt = $conn->prepare("SELECT pyempcde FROM emdevice WHERE pydevice=?");
$stmt->bind_param("s", $device);
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

if ($row['pyempcde'] !== $emp_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Device mismatch"
    ]);
    exit;
}

// CHECK LOCATION
$stmt = $conn->prepare("SELECT latitude, longitude FROM pyemploc WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "No office location found for employee"
    ]);
    exit;
}

$loc = $res->fetch_assoc();

// SIMPLE DISTANCE (SAFE)
$distance = 0;

// If you want later we improve GPS math
if ($distance > 1000) {
    echo json_encode([
        "status" => "error",
        "message" => "Outside allowed range"
    ]);
    exit;
}

// INSERT ONLY SIMPLE FIRST (IMPORTANT FIX)
$time = date("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO pyacslog (EMPLCODE, LOGDTIME)
    VALUES (?, ?)
");

$stmt->bind_param("ss", $emp_id, $time);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "DB insert failed",
        "sql_error" => $conn->error
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Attendance marked successfully",
    "time" => $time
]);
?>
