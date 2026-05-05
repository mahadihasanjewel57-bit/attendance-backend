<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================= HEADERS =================
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

include "db.php";

// ================= READ INPUT =================
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// fallback to POST if JSON fails
if (!is_array($data)) {
    $data = $_POST;
}

// ================= INPUT VALUES =================
$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

// ================= VALIDATION =================
if ($emp_id === '' || $device === '') {
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

if ($res->num_rows === 0) {
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

if ($res->num_rows === 0) {
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
        "message" => "Device not registered for this ID"
    ]);
    exit;
}

// ================= SUCCESS RESPONSE =================
echo json_encode([
    "status" => "success",
    "employee" => $emp
]);
?>
