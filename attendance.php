<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

include "db.php";
date_default_timezone_set("Asia/Dhaka");

// ================= PRE-FLIGHT =================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ================= INPUT =================
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// 🔥 fallback handling
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

// 🔥 debug (temporary)
if (empty($data)) {
    echo json_encode([
        "status" => "error",
        "message" => "No input received",
        "raw" => $raw
    ]);
    exit;
}

// ================= USER INPUT =================
$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat    = $data['lat'] ?? null;
$lng    = $data['lng'] ?? null;

// ================= VALIDATION =================
if ($emp_id === '' || $device === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Employee ID or Device missing"
    ]);
    exit;
}

// ================= TIME =================
$time = date("Y-m-d H:i:s");

// ================= DEVICE HASH =================
$deviceInt = abs(crc32($device));

// ================= DEVICE CHECK =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");

if (!$stmt) {
    echo json_encode(["status"=>"error","message"=>$conn->error]);
    exit;
}

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

if ($row['pydevice'] !== $device) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized device"
    ]);
    exit;
}

// ================= INSERT =================
$stmt = $conn->prepare("
INSERT INTO pyacslog (
    COMPCODE, LOGINDEX, NODINDEX, LOGDTIME,
    EMPLCODE, NODECODE, AUTHTYPE, AUTHRSLT,
    OPENRSLT, FUNCNUMB, SLOGTIME, CHECKFLG,
    TERMNAME, BRANCODE, LGSTATUS, REMARKSS,
    AUTHCODE, PYACSENF
) VALUES (
    200, ?, ?, ?, 
    ?, 200, 128, 0,
    0, 0, ?, 0,
    '152', NULL, 'N', NULL,
    NULL, 'N'
)
");

if (!$stmt) {
    echo json_encode(["status"=>"error","message"=>$conn->error]);
    exit;
}

$stmt->bind_param(
    "iisss",
    $deviceInt,  // LOGINDEX
    $deviceInt,  // NODINDEX
    $time,       // LOGDTIME
    $emp_id,     // EMPLCODE
    $time        // SLOGTIME
);

// ✅ ONLY ONE EXECUTION
if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Attendance saved",
        "time" => $time
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Insert failed",
        "debug" => $stmt->error
    ]);
}
?>
