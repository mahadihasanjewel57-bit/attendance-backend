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

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) $data = $_POST;

$emp_id = trim($data['pyempcde'] ?? '');

if ($emp_id === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Employee ID required"
    ]);
    exit;
}

// check employee
$stmt = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid Employee ID"
    ]);
    exit;
}

$emp = $res->fetch_assoc();

// check device
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

echo json_encode([
    "status" => "new",
    "emp_name" => $emp['pyempnam']
]);
?>
