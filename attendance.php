<?php
header("Content-Type: application/json");
include "db.php";
date_default_timezone_set("Asia/Dhaka");
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

$emp_id = $_POST['pyempcde'] ?? '';
$device = $_POST['pydevice'] ?? '';
$lat = $_POST['lat'] ?? '';
$lng = $_POST['lng'] ?? '';

if (!$emp_id || !$device || !$lat || !$lng) {
    echo json_encode(["status"=>"error","message"=>"Missing parameters"]);
    exit;
}

// VERIFY DEVICE
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["status"=>"error","message"=>"Device not registered"]);
    exit;
}

$row = $res->fetch_assoc();

if ($row['pydevice'] !== $device) {
    echo json_encode(["status"=>"error","message"=>"Unauthorized device"]);
    exit;
}

// CHECK LAST ATTENDANCE (15 MIN)
$stmt = $conn->prepare("
SELECT LOGDTIME FROM pyacslog 
WHERE EMPLCODE=? 
ORDER BY LOGDTIME DESC 
LIMIT 1
");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if ((time() - strtotime($row['LOGDTIME'])) < 900) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Already checked within 15 minutes"
        ]);
        exit;
    }
}

// LOCATION CHECK
$stmt = $conn->prepare("SELECT latitude, longitude FROM pyemploc WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();
$loc = $res->fetch_assoc();

function distance($lat1, $lon1, $lat2, $lon2) {
    $earth = 6371000;
    $dLat = deg2rad($lat2-$lat1);
    $dLon = deg2rad($lon2-$lon1);

    $a = sin($dLat/2)*sin($dLat/2) +
         cos(deg2rad($lat1))*cos(deg2rad($lat2))*
         sin($dLon/2)*sin($dLon/2);

    return $earth * (2 * atan2(sqrt($a), sqrt(1-$a)));
}

$dist = distance($lat, $lng, $loc['latitude'], $loc['longitude']);

if ($dist > 100) {
    echo json_encode(["status"=>"error","message"=>"Outside allowed area"]);
    exit;
}

// INSERT
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
200, ?, ?, ?, ?, 200, 128, 0,
0, 0, ?, 0,
'152', NULL, 'N', NULL,
NULL, 'N'
)");

$stmt->bind_param("iisss", $deviceInt, $deviceInt, $time, $emp_id, $time);
$stmt->execute();

echo json_encode([
    "status"=>"success",
    "message"=>"Checked at: $time"
]);
?>
