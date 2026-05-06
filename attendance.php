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
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat    = $data['lat'] ?? '';
$lng    = $data['lng'] ?? '';

if ($emp_id == '' || $device == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing employee or device"
    ]);
    exit;
}

// ================= TIME =================
$time = date("Y-m-d H:i:s");

// ================= DEVICE HASH (FIXED) =================
$deviceHash = hash('sha256', $device);

// ================= DEVICE VALIDATION =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Device not registered"
    ]);
    exit;
}

$row = $res->fetch_assoc();

if ($row['pydevice'] !== $deviceHash) {
    echo json_encode([
        "status" => "error",
        "message" => "Employee already registered in another device"
    ]);
    exit;
}

// ================= 30 MIN DUPLICATE CHECK =================
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

if ($last = $res->fetch_assoc()) {
    if ((strtotime($time) - strtotime($last['LOGDTIME'])) < 1800) {
        echo json_encode([
            "status" => "error",
            "message" => "Already punched within 30 minutes"
        ]);
        exit;
    }
}

// ================= IN / OUT LOGIC =================
$hour = date("H");
$type = ($hour < 12) ? "IN" : "OUT";

$remark = "$type | LAT:$lat LNG:$lng";

// ================= DEVICE INT FOR TABLE =================
$deviceInt = abs(crc32($device)) % 2147483647;

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

$stmt->bind_param(
    "iissss",
    $deviceInt,
    $deviceInt,
    $time,
    $emp_id,
    $time,
    $remark
);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
    exit;
}

// ================= TODAY LAST PUNCH =================
$log = $conn->prepare("
SELECT LOGDTIME, REMARKSS
FROM pyacslog
WHERE EMPLCODE=?
AND DATE(LOGDTIME)=CURDATE()
ORDER BY LOGDTIME ASC
");

$log->bind_param("s", $emp_id);
$log->execute();
$res = $log->get_result();

$checkIn = "";
$checkOut = "";

while ($row = $res->fetch_assoc()) {
    if (strpos($row['REMARKSS'], 'IN') !== false && $checkIn == "") {
        $checkIn = date("h:i A", strtotime($row['LOGDTIME']));
    }

    if (strpos($row['REMARKSS'], 'OUT') !== false) {
        $checkOut = date("h:i A", strtotime($row['LOGDTIME']));
    }
}

// ================= RESPONSE =================
echo json_encode([
    "status" => "success",
    "message" => "Attendance saved",
    "check_in" => $checkIn ?: "--:--",
    "check_out" => $checkOut ?: "--:--",
    "type" => $type,
    "time" => date("h:i A", strtotime($time))
]);
?>
