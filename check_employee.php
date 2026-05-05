<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

include "db.php";

// ✅ Handle preflight request (important for some devices)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ✅ READ INPUT (JSON + fallback)
$data = [];

// Try JSON first
$raw = file_get_contents("php://input");
$json = json_decode($raw, true);

if (is_array($json)) {
    $data = $json;
}

// Fallback to POST
if (empty($data)) {
    $data = $_POST;
}

// Final safe extraction
$emp_id = trim($data['pyempcde'] ?? '');

echo json_encode([
    "raw" => $raw,
    "post" => $_POST,
    "final_emp_id" => $emp_id
]);
exit;

// ✅ GET VALUE SAFELY
$emp_id = trim($data['pyempcde'] ?? '');


echo json_encode([
    "raw_input" => file_get_contents("php://input"),
    "parsed" => $data
]);
exit;

// ✅ VALIDATION
if (!$emp_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Employee ID required"
    ]);
    exit;
}

// ================= CHECK DEVICE TABLE =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status" => "registered"
    ]);
    exit;
}

// ================= CHECK EMPLOYEE MASTER =================
$stmt = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid Employee ID"
    ]);
    exit;
}

$row = $res->fetch_assoc();

// ================= NEW USER =================
echo json_encode([
    "status" => "new",
    "emp_name" => $row['pyempnam']
]);
?>