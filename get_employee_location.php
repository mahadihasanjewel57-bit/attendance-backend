<?php

header("Content-Type: application/json");

$conn = new mysqli(
    $_ENV['MYSQLHOST'],
    $_ENV['MYSQLUSER'],
    $_ENV['MYSQLPASSWORD'],
    $_ENV['MYSQLDATABASE']
);

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "DB Connection Failed"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$empId = $data['pyempcde'] ?? '';

if ($empId == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Employee ID missing"
    ]);
    exit;
}

$sql = "
SELECT latitude, longitude
FROM pyemploc
WHERE pyempcde = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $empId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    echo json_encode([
        "status" => "success",
        "latitude" => $row['latitude'],
        "longitude" => $row['longitude']
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Location not assigned"
    ]);
}
?>
