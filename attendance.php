<?php
header("Content-Type: application/json");
include "db.php";

date_default_timezone_set("Asia/Dhaka");

// ================= INPUT =================
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) $data = $_POST;

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

// ================= VALIDATION =================
if (!$emp_id || !$device) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing employee or device"
    ]);
    exit;
}

// ================= DEVICE CHECK =================
$stmt = $conn->prepare("SELECT pyempcde FROM emdevice WHERE pydevice=?");
$stmt->bind_param("s", $device);
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

if ($row['pyempcde'] !== $emp_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Device mismatch"
    ]);
    exit;
}

// ================= TIME =================
$time = date("Y-m-d H:i:s");
$deviceInt = abs(crc32($device));

// ================= INSERT (FULL TABLE MATCH) =================
$stmt = $conn->prepare("
INSERT INTO pyacslog (
    COMPCODE,
    LOGINDEX,
    NODINDEX,
    LOGDTIME,
    EMPLCODE,
    NODECODE,
    AUTHTYPE,
    AUTHRSLT,
    OPENRSLT,
    FUNCNUMB,
    SLOGTIME,
    CHECKFLG,
    TERMNAME,
    BRANCODE,
    LGSTATUS,
    REMARKSS,
    AUTHCODE,
    PYACSENF
) VALUES (
    200,
    ?,
    ?,
    ?,
    ?,
    200,
    128,
    0,
    0,
    0,
    ?,
    0,
    '152',
    NULL,
    'N',
    NULL,
    NULL,
    'N'
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

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Insert failed",
        "sql_error" => $conn->error
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Attendance saved successfully",
    "time" => $time
]);
?>
