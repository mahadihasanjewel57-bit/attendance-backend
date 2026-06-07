<?php
// get_attendance_history.php
// Returns last 30 days attendance for a given employee
// LOGDTIME = combined datetime column, EMPLCODE = employee ID
// First punch of day = Check-In, Last punch = Check-Out

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// ── DB config — Railway's exact MySQL variable names ─────────────────
$host = getenv("MYSQLHOST")     ?: "mysql.railway.internal";
$db   = getenv("MYSQLDATABASE") ?: "railway";
$user = getenv("MYSQLUSER")     ?: "root";
$pass = getenv("MYSQLPASSWORD") ?: "";
$port = getenv("MYSQLPORT")     ?: 3306;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "DB connection failed: " . $e->getMessage()
    ]);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);
$empId = trim($input["pyempcde"] ?? "");

if (empty($empId)) {
    echo json_encode(["status" => "error", "message" => "Employee ID required"]);
    exit;
}

// ── Date range: today back 30 days ────────────────────────────────────
$today    = new DateTime();
$fromDate = (clone $today)->modify("-29 days");

// ── Query: first punch = check-in, last punch = check-out ────────────
$stmt = $pdo->prepare("
    SELECT
        DATE(LOGDTIME)                             AS log_date,
        TIME_FORMAT(MIN(LOGDTIME), '%h:%i %p')     AS check_in,
        TIME_FORMAT(MAX(LOGDTIME), '%h:%i %p')     AS check_out
    FROM pyacslog
    WHERE EMPLCODE = :empId
      AND DATE(LOGDTIME) BETWEEN :fromDate AND :toDate
    GROUP BY DATE(LOGDTIME)
    ORDER BY log_date DESC
");

$stmt->execute([
    ":empId"    => $empId,
    ":fromDate" => $fromDate->format("Y-m-d"),
    ":toDate"   => $today->format("Y-m-d"),
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Index by date for quick lookup
$attendanceMap = [];
foreach ($rows as $row) {
    $attendanceMap[$row["log_date"]] = $row;
}

// ── Build full 30-day list (fills absent/weekend gaps) ────────────────
$records = [];
$cursor  = clone $today;

for ($i = 0; $i < 30; $i++) {
    $dateKey   = $cursor->format("Y-m-d");
    $dayOfWeek = (int) $cursor->format("N"); // 1=Mon … 7=Sun

    if ($dayOfWeek === 5 || $dayOfWeek === 6) {
        // Friday & Saturday = weekend (Bangladesh standard)
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => null,
            "check_out" => null,
            "status"    => "weekend",
        ];
    } elseif (isset($attendanceMap[$dateKey])) {
        $row      = $attendanceMap[$dateKey];
        $checkIn  = $row["check_in"]  ?: null;
        $checkOut = $row["check_out"] ?: null;
        // Single punch: don't repeat same time for check-out
        if ($checkIn === $checkOut) {
            $checkOut = null;
        }
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => $checkIn,
            "check_out" => $checkOut,
            "status"    => "present",
        ];
    } else {
        // Weekday with no record = absent
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => null,
            "check_out" => null,
            "status"    => "absent",
        ];
    }

    $cursor->modify("-1 day");
}

echo json_encode([
    "status"  => "success",
    "records" => $records,
]);
