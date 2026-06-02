<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

set_exception_handler(function ($e) {
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "file" => basename($e->getFile()),
        "line" => $e->getLine()
    ]);
    exit;
});

header("Content-Type: application/json");
$raw = file_get_contents("php://input");

file_put_contents(
    "debug.log",
    date("Y-m-d H:i:s") . "\n" . $raw . "\n\n",
    FILE_APPEND
);

$data = json_decode($raw, true);

if (!$data) {
    die(json_encode([
        "status" => "error",
        "message" => "JSON decode failed"
    ]));
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

include "db.php";
if (!$conn) {
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]));
}
date_default_timezone_set("Asia/Dhaka");

$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat    = isset($data['lat']) ? floatval($data['lat']) : null;
$lng    = isset($data['lng']) ? floatval($data['lng']) : null;

// ── Validation ────────────────────────────────────────────────────
if ($emp_id === '' || $device === '') {
    echo json_encode(["status" => "error", "message" => "Employee ID or Device missing"]);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(["status" => "error", "message" => "GPS coordinates missing"]);
    exit;
}

// ── Verify device ─────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde = ? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$devRes = $stmt->get_result();

if ($devRes->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Device not registered"]);
    exit;
}
$devRow = $devRes->fetch_assoc();
if ($devRow['pydevice'] !== $device) {
    echo json_encode(["status" => "error", "message" => "Device mismatch"]);
    exit;
}

// ── GPS distance check ────────────────────────────────────────────
$locStmt = $conn->prepare("SELECT latitude, longitude FROM pyemploc WHERE pyempcde = ? LIMIT 1");
$locStmt->bind_param("s", $emp_id);
$locStmt->execute();
$locRes = $locStmt->get_result();

if ($locRes->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Office location not assigned for this employee"]);
    exit;
}
$locRow  = $locRes->fetch_assoc();
$offLat  = floatval($locRow['latitude']);
$offLng  = floatval($locRow['longitude']);

// Haversine formula
$earthR  = 6371000;
$dLat    = deg2rad($lat - $offLat);
$dLng    = deg2rad($lng - $offLng);
$a       = sin($dLat / 2) * sin($dLat / 2)
         + cos(deg2rad($offLat)) * cos(deg2rad($lat))
         * sin($dLng / 2) * sin($dLng / 2);
$distance = $earthR * 2 * atan2(sqrt($a), sqrt(1 - $a));

if ($distance > 10) {
    echo json_encode([
        "status"  => "error",
        "message" => "You are outside the allowed area (" . round($distance) . " m from office)",
    ]);
    exit;
}

// ── 30 minute block ───────────────────────────────────────────────
$time  = date("Y-m-d H:i:s");
$today = date("Y-m-d");

$blockStmt = $conn->prepare("
    SELECT LOGDTIME FROM pyacslog
    WHERE EMPLCODE = ?
    ORDER BY LOGDTIME DESC
    LIMIT 1
");
$blockStmt->bind_param("s", $emp_id);
$blockStmt->execute();
$blockRes = $blockStmt->get_result();

if ($blockRes->num_rows > 0) {
    $lastRow  = $blockRes->fetch_assoc();
    $diffMins = (strtotime($time) - strtotime($lastRow['LOGDTIME'])) / 60;
    if ($diffMins < 30) {
        echo json_encode([
            "status"  => "error",
            "message" => "You must wait 30 minutes between punches",
        ]);
        exit;
    }
}

// ── Insert attendance ─────────────────────────────────────────────
$deviceInt = abs(crc32($device));

$ins = $conn->prepare("
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
$ins->bind_param("iisss", $deviceInt, $deviceInt, $time, $emp_id, $time);

if (!$ins->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to save attendance"]);
    exit;
}

// ── Fetch today records ───────────────────────────────────────────
$todayStmt = $conn->prepare("
    SELECT LOGDTIME FROM pyacslog
    WHERE EMPLCODE = ? AND DATE(LOGDTIME) = ?
    ORDER BY LOGDTIME ASC
");
$todayStmt->bind_param("ss", $emp_id, $today);
$todayStmt->execute();
$todayRes = $todayStmt->get_result();

$times = [];
while ($r = $todayRes->fetch_assoc()) {
    $times[] = $r['LOGDTIME'];
}

$checkIn  = count($times) >= 1 ? date("h:i A", strtotime($times[0]))                 : "--:--";
$checkOut = count($times) >= 2 ? date("h:i A", strtotime($times[count($times) - 1])) : "--:--";

echo json_encode([
    "status"    => "success",
    "message"   => "Attendance saved successfully",
    "check_in"  => $checkIn,
    "check_out" => $checkOut,
]);
?>
