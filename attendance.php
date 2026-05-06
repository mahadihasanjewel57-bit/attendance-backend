<?php
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";
date_default_timezone_set("Asia/Dhaka");

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
    echo json_encode(["status"=>"error","message"=>"Missing data"]);
    exit;
}

$time = date("Y-m-d H:i:s");

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
    echo json_encode(["status"=>"error","message"=>"Device mismatch"]);
    exit;
}

// ================= 30 MIN DUPLICATE BLOCK =================
$check = $conn->prepare("
SELECT LOGDTIME 
FROM pyacslog 
WHERE EMPLCODE=? 
ORDER BY LOGDTIME DESC 
LIMIT 1
");
$check->bind_param("s", $emp_id);
$check->execute();
$res2 = $check->get_result();

if ($last = $res2->fetch_assoc()) {
    $diff = strtotime($time) - strtotime($last['LOGDTIME']);
    if ($diff < 1800) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Already punched within 30 minutes"
        ]);
        exit;
    }
}

// ================= IN / OUT LOGIC =================
$hour = (int)date("H");

$type = ($hour < 13) ? "IN" : "OUT";
$remark = $type;

// ================= INSERT =================
$stmt = $conn->prepare("
INSERT INTO pyacslog (
    COMPCODE, LOGINDEX, NODINDEX, LOGDTIME,
    EMPLCODE, NODECODE, AUTHTYPE, AUTHRSLT,
    OPENRSLT, FUNCNUMB, SLOGTIME, CHECKFLG,
    TERMNAME, LGSTATUS, REMARKSS, PYACSENF
) VALUES (
    200, 1, 1, ?, 
    ?, 200, 128, 0,
    0, 0, ?, 0,
    '152', 'N', ?, 'N'
)
");

$stmt->bind_param("sss", $time, $emp_id, $time, $remark);
$stmt->execute();

// ================= LAST PUNCH =================
$log = $conn->prepare("
SELECT LOGDTIME, REMARKSS 
FROM pyacslog 
WHERE EMPLCODE=? 
ORDER BY LOGDTIME DESC 
LIMIT 10
");

$log->bind_param("s", $emp_id);
$log->execute();
$res3 = $log->get_result();

$checkIn = "";
$checkOut = "";

while ($r = $res3->fetch_assoc()) {
    if ($r['REMARKSS'] == "IN" && $checkIn == "") {
        $checkIn = date("h:i A", strtotime($r['LOGDTIME']));
    }
    if ($r['REMARKSS'] == "OUT" && $checkOut == "") {
        $checkOut = date("h:i A", strtotime($r['LOGDTIME']));
    }
}

// ================= RESPONSE =================
echo json_encode([
    "status"=>"success",
    "message"=>"Attendance saved",
    "type"=>$type,
    "check_in"=>$checkIn ?: "--:--",
    "check_out"=>$checkOut ?: "--:--",
    "time"=>date("h:i A", strtotime($time))
]);
?>
