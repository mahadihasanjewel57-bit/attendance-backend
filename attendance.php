<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$data = $_POST;

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat    = $data['lat'] ?? '';
$lng    = $data['lng'] ?? '';

if ($emp_id == '' || $device == '') {
    echo json_encode(["status"=>"error","message"=>"Missing data"]);
    exit;
}

$time = date("Y-m-d H:i:s");
$deviceInt = abs(crc32($device));

// ================= DEVICE CHECK =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");
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

// ================= PREVENT DUPLICATE (30 MIN) =================
$check = $conn->prepare("
SELECT LOGDTIME 
FROM pyacslog 
WHERE EMPLCODE=? 
ORDER BY LOGDTIME DESC 
LIMIT 1
");

$check->bind_param("s", $emp_id);
$check->execute();
$res = $check->get_result();

if ($r = $res->fetch_assoc()) {
    if ((strtotime($time) - strtotime($r['LOGDTIME'])) < 1800) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Already marked within 30 minutes"
        ]);
        exit;
    }
}

// ================= IN / OUT =================
$hour = date("H");
$type = ($hour < 12) ? "IN" : "OUT";

$remark = "$type | LAT:$lat LNG:$lng";

// ================= INSERT =================
$stmt = $conn->prepare("
INSERT INTO pyacslog (
    COMPCODE, LOGINDEX, NODINDEX, LOGDTIME,
    EMPLCODE, NODECODE, AUTHTYPE, AUTHRSLT,
    OPENRSLT, FUNCNUMB, SLOGTIME, CHECKFLG,
    TERMNAME, BRANCODE, LGSTATUS, REMARKSS,
    AUTHCODE, PYACSENF
) VALUES (
    200, ?, ?, ?, ?, 
    200, 128, 0, 0, 0,
    ?, 0, '152', NULL, 'N', ?, NULL, 'N'
)
");

$stmt->bind_param("iissss",
    $deviceInt,
    $deviceInt,
    $time,
    $emp_id,
    $time,
    $remark
);

if (!$stmt->execute()) {
    echo json_encode(["status"=>"error","message"=>$stmt->error]);
    exit;
}

// ================= LAST PUNCH =================
$last = $conn->prepare("
SELECT REMARKSS, LOGDTIME 
FROM pyacslog 
WHERE EMPLCODE=? 
AND DATE(LOGDTIME)=CURDATE()
ORDER BY LOGDTIME ASC
");

$last->bind_param("s", $emp_id);
$last->execute();
$result = $last->get_result();

$checkIn = "";
$checkOut = "";

while ($row = $result->fetch_assoc()) {
    if (strpos($row['REMARKSS'], 'IN') !== false) {
        $checkIn = date("h:i A", strtotime($row['LOGDTIME']));
    }
    if (strpos($row['REMARKSS'], 'OUT') !== false) {
        $checkOut = date("h:i A", strtotime($row['LOGDTIME']));
    }
}

echo json_encode([
    "status"=>"success",
    "message"=>"Attendance saved",
    "check_in"=>$checkIn,
    "check_out"=>$checkOut
]);
?>
