<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

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
    echo json_encode([
        "status" => "error",
        "message" => "Missing employee or device"
    ]);
    exit;
}

$time = date("Y-m-d H:i:s");

// ================= DEVICE INT =================
$deviceInt = abs(crc32($device));

// ================= DEVICE CHECK =================
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

if ($row['pydevice'] !== $device) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized device"
    ]);
    exit;
}

// ================= INSERT INTO PYACSLOG =================
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
    ?, 0, '152', NULL, 'N', NULL, NULL, 'N'
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
        "message" => "Attendance saved"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
}
?>
