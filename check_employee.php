<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

include "db.php";

$data    = json_decode(file_get_contents("php://input"), true);
$emp_id  = trim($data['pyempcde']  ?? '');
$device  = trim($data['pydevice']  ?? '');

if ($emp_id === '') {
    echo json_encode(["status" => "error", "message" => "Employee ID required"]);
    exit;
}

// ── STEP 1: Is this emp_id already in emdevice? ──────────────────
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde = ? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$devRes = $stmt->get_result();

if ($devRes->num_rows > 0) {
    $devRow = $devRes->fetch_assoc();

    if ($device === '') {
        echo json_encode(["status" => "error", "message" => "Device ID missing"]);
        exit;
    }

    if ($devRow['pydevice'] === $device) {
        // Device matches — fetch employee name
        $stmt2 = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde = ? LIMIT 1");
        $stmt2->bind_param("s", $emp_id);
        $stmt2->execute();
        $empRes = $stmt2->get_result();

        if ($empRes->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Employee not found"]);
            exit;
        }

        $empRow = $empRes->fetch_assoc();
        echo json_encode([
            "status"   => "matched",
            "emp_name" => $empRow['pyempnam'],
        ]);
    } else {
        // Wrong device
        echo json_encode([
            "status"  => "device_mismatch",
            "message" => "Device is not registered for this ID, Please contact HRD",
        ]);
    }
    exit;
}

// ── STEP 2: emp_id NOT in emdevice — check pyempmas ──────────────
$stmt3 = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde = ? LIMIT 1");
$stmt3->bind_param("s", $emp_id);
$stmt3->execute();
$masterRes = $stmt3->get_result();

if ($masterRes->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Employee ID"]);
    exit;
}

$masterRow = $masterRes->fetch_assoc();
echo json_encode([
    "status"   => "new",
    "emp_name" => $masterRow['pyempnam'],
]);
?>
