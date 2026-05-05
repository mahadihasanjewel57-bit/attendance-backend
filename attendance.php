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

if (!is_array($data)) {
    $data = $_POST;
}

// ================= SAFE INPUT =================
$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat    = $data['lat'] ?? null;
$lng    = $data['lng'] ?? null;

// ================= VALIDATION =================
if ($emp_id === '' || $device === '' || $lat === null || $lng === null) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing parameters"
    ]);
    exit;
}

// ================= DEVICE CHECK =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");
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

// ================= LOCATION CHECK =================
$stmt = $conn->prepare("SELECT latitude, longitude FROM pyemploc WHERE pyempcde=? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Location not set"
    ]);
    exit;
}

$loc = $res->fetch_assoc();

// ================= DISTANCE FUNCTION =================
function distance($lat1, $lon1, $lat2, $lon2) {
    $earth = 6371000;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

$dist = distance($lat, $lng, $loc['latitude'], $loc['longitude']);

if ($dist > 100) {
    echo json_encode([
        "status" => "error",
        "message" => "Outside allowed area"
    ]);
    exit;
}

// ================= INSERT ATTENDANCE =================
$time = date("Y-m-d H:i:s");
$deviceInt = abs(crc32($device));

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

$stmt->bind_param(
    "iisss",
    $deviceInt,
    $deviceInt,
    $time,
    $emp_id,
    $time
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Attendance marked successfully",
        "time" => $time
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database insert failed"
    ]);
}
?>
