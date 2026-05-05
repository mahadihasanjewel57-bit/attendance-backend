<?php
include "db.php";
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) $data = $_POST;

$emp_id = trim($data["pyempcde"] ?? "");
$device = trim($data["pydevice"] ?? "");

if ($emp_id == "" || $device == "") {
    echo json_encode(["status"=>"error","message"=>"Missing data"]);
    exit;
}

/*
🚨 NEW RULE:
- Device can belong to ONLY ONE employee
*/

$stmt = $conn->prepare("SELECT pyempcde FROM emdevice WHERE pydevice=?");
$stmt->bind_param("s", $device);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status"=>"error",
        "message"=>"This device is already registered to another employee"
    ]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO emdevice (pyempcde, pydevice) VALUES (?, ?)");
$stmt->bind_param("ss", $emp_id, $device);

echo $stmt->execute()
    ? json_encode(["status"=>"success","message"=>"Device locked to employee"])
    : json_encode(["status"=>"error","message"=>"Insert failed"]);
?>
