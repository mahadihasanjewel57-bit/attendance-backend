<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

include "db.php";

// ✅ Handle preflight request (important for hosting)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ✅ READ JSON INPUT
$data = json_decode(file_get_contents("php://input"), true);

// Fallback for non-JSON requests
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
        "message" => "Missing parameters"
    ]);
    exit;
}

// ================= CHECK EMPLOYEE =================
$stmt = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde=?");
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

// ================= CHECK DEVICE =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
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

// ================= DEVICE MATCH =================
if ($row['pydevice'] !== $device) {
    echo json_encode([
        "status" => "error",
        "message" => "Device is not registered for this ID. Please contact HRD"
    ]);
    exit;
}

// ================= SUCCESS =================
echo json_encode([
    "status" => "success",
    "employee" => $emp
]);
?>