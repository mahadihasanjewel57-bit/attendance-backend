<?php
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$emp = $data["pyempcde"];

$q = $conn->prepare("
SELECT LOGDTIME FROM pyacslog 
WHERE EMPLCODE=? AND DATE(LOGDTIME)=CURDATE()
ORDER BY LOGDTIME ASC
");

$q->bind_param("s", $emp);
$q->execute();
$r = $q->get_result();

$times = [];
while ($row = $r->fetch_assoc()) {
    $times[] = date("h:i A", strtotime($row["LOGDTIME"]));
}

echo json_encode([
    "check_in" => $times[0] ?? "--:--",
    "check_out" => count($times) > 1 ? end($times) : "--:--"
]);
