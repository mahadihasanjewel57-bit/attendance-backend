<?php
header("Content-Type: application/json");
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$emp_id = trim($data['pyempcde'] ?? '');
$device = trim($data['pydevice'] ?? '');

$stmt = $conn->prepare("SELECT pyempnam FROM pyempmas WHERE pyempcde=?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["status"=>"error","message"=>"Employee not found"]);
    exit;
}

$emp = $res->fetch_assoc();

echo json_encode([
    "status"=>"success",
    "employee"=>$emp
]);
?>
