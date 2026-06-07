<?php
// get_attendance_history.php  — mysqli version (fallback if PDO unavailable)

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// ── DB config — Railway's exact MySQL variable names ─────────────────
$host = getenv("MYSQLHOST")     ?: "mysql.railway.internal";
$db   = getenv("MYSQLDATABASE") ?: "railway";
$user = getenv("MYSQLUSER")     ?: "root";
$pass = getenv("MYSQLPASSWORD") ?: "";
$port = (int)(getenv("MYSQLPORT") ?: 3306);

// ── Connect via mysqli ────────────────────────────────────────────────
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo json_encode([
        "status"  => "error",
        "message" => "DB connection failed: " . $conn->connect_error,
    ]);
    exit;
}
$conn->set_charset("utf8mb4");

// ── Input ─────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);
$empId = $conn->real_escape_string(trim($input["pyempcde"] ?? ""));

if (empty($empId)) {
    echo json_encode(["status" => "error", "message" => "Employee ID required"]);
    exit;
}

// ── Date range ────────────────────────────────────────────────────────
$today    = new DateTime();
$fromDate = (clone $today)->modify("-29 days");
$toDate   = $today->format("Y-m-d");
$fromStr  = $fromDate->format("Y-m-d");

// ── Query ─────────────────────────────────────────────────────────────
$sql = "
    SELECT
        DATE(LOGDTIME)                             AS log_date,
        TIME_FORMAT(MIN(LOGDTIME), '%h:%i %p')     AS check_in,
        TIME_FORMAT(MAX(LOGDTIME), '%h:%i %p')     AS check_out
    FROM pyacslog
    WHERE EMPLCODE = '$empId'
      AND DATE(LOGDTIME) BETWEEN '$fromStr' AND '$toDate'
    GROUP BY DATE(LOGDTIME)
    ORDER BY log_date DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => "Query failed: " . $conn->error]);
    exit;
}

$attendanceMap = [];
while ($row = $result->fetch_assoc()) {
    $attendanceMap[$row["log_date"]] = $row;
}
$conn->close();

// ── Build 30-day list ─────────────────────────────────────────────────
$records = [];
$cursor  = new DateTime();

for ($i = 0; $i < 30; $i++) {
    $dateKey   = $cursor->format("Y-m-d");
    $dayOfWeek = (int) $cursor->format("N");

    if ($dayOfWeek === 5 || $dayOfWeek === 6) {
        $records[] = ["date" => $dateKey, "check_in" => null, "check_out" => null, "status" => "weekend"];
    } elseif (isset($attendanceMap[$dateKey])) {
        $row      = $attendanceMap[$dateKey];
        $checkIn  = $row["check_in"]  ?: null;
        $checkOut = $row["check_out"] ?: null;
        if ($checkIn === $checkOut) $checkOut = null;
        $records[] = ["date" => $dateKey, "check_in" => $checkIn, "check_out" => $checkOut, "status" => "present"];
    } else {
        $records[] = ["date" => $dateKey, "check_in" => null, "check_out" => null, "status" => "absent"];
    }

    $cursor->modify("-1 day");
}

echo json_encode(["status" => "success", "records" => $records]);
