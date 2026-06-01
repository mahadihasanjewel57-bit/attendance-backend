<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

include "db.php";
date_default_timezone_set("Asia/Dhaka");

$data   = json_decode(file_get_contents("php://input"), true);
$emp_id = trim($data['pyempcde'] ?? '');
$today  = date("Y-m-d");

if ($emp_id === '') {
    echo json_encode(["status" => "error", "message" => "Employee ID required"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT LOGDTIME FROM pyacslog
    WHERE EMPLCODE = ? AND DATE(LOGDTIME) = ?
    ORDER BY LOGDTIME ASC
");
$stmt->bind_param("ss", $emp_id, $today);
$stmt->execute();
$res = $stmt->get_result();

$times = [];
while ($row = $res->fetch_assoc()) {
    $times[] = $row['LOGDTIME'];
}

echo json_encode([
    "status"    => "success",
    "check_in"  => count($times) >= 1 ? date("h:i A", strtotime($times[0]))                 : "--:--",
    "check_out" => count($times) >= 2 ? date("h:i A", strtotime($times[count($times) - 1])) : "--:--",
]);
?>
