<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

include "db.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
$raw = file_get_contents("php://input");

echo json_encode([
    "raw" => $raw,
    "post" => $_POST
]);
exit;
// ================= READ INPUT SAFELY =================

// Try JSON input
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Fallback to POST if JSON empty
if (!is_array($data)) {
    $data = $_POST;
}

// Final employee ID
$emp_id = trim($data['pyempcde'] ?? '');

// ================= DEBUG (REMOVE AFTER TEST) =================
/*
echo json_encode([
    "raw" => $raw,
    "post" => $_POST,
    "final_emp_id" => $emp_id
]);
exit;
*/

// ================= VALIDATION =================
if ($emp_id === '') {
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
