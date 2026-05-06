<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat    = $data['lat'] ?? null;
$lng    = $data['lng'] ?? null;

// ================= VALIDATION =================
if ($emp_id == '' || $device == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Employee ID or Device missing"
    ]);
    exit;
}

// ================= FIXED VALUES =================
$COMPCODE = 200;
$NODECODE = 200;
$AUTHTYPE = 128;
$AUTHRSLT = 0;
$OPENRSLT = 0;
$FUNCNUMB = 0;
$CHECKFLG = 0;
$TERMNAME = "152";
$LGSTATUS = "N";
$PYACSENF = "N";

// ================= TIME =================
$time = date("Y-m-d H:i:s");
$today = date("Y-m-d");

// ================= DEVICE CHECK =================
$stmt = $conn->prepare("SELECT pydevice FROM emdevice WHERE pyempcde=? LIMIT 1");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();

    if ($row['pydevice'] !== $device) {
        echo json_encode([
            "status" => "error",
            "message" => "Device registered to another employee"
        ]);
        exit;
    }
}

// ================= INSERT ATTENDANCE =================
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

$deviceInt = abs(crc32($device));

$stmt->bind_param(
    "iisss",
    $deviceInt,
    $deviceInt,
    $time,
    $emp_id,
    $time
);

$stmt->execute();

// ================= FETCH TODAY RECORDS =================
$checkIn = "--:--";
$checkOut = "--:--";

$stmt2 = $conn->prepare("
SELECT LOGDTIME
FROM pyacslog
WHERE EMPLCODE = ?
AND DATE(LOGDTIME) = ?
ORDER BY LOGDTIME ASC
");

$stmt2->bind_param("ss", $emp_id, $today);
$stmt2->execute();
$res2 = $stmt2->get_result();

$rows = [];

while ($r = $res2->fetch_assoc()) {
    $rows[] = $r['LOGDTIME'];
}

$count = count($rows);

if ($count >= 1) {
    $checkIn = date("h:i A", strtotime($rows[0]));
}

if ($count >= 2) {
    $checkOut = date("h:i A", strtotime($rows[$count - 1]));
}

// ================= RESPONSE =================
echo json_encode([
    "status" => "success",
    "message" => "Attendance saved",
    "check_in" => $checkIn,
    "check_out" => $checkOut
]);
exit;
?>
