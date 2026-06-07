<?php
// get_attendance_history.php
// Returns last 30 days attendance for a given employee

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// ── DB config (update if yours differ) ──────────────────────────────
$host   = getenv("DB_HOST")   ?: "localhost";
$db     = getenv("DB_NAME")   ?: "railway";
$user   = getenv("DB_USER")   ?: "root";
$pass   = getenv("DB_PASS")   ?: "";
$port   = getenv("DB_PORT")   ?: 3306;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// ── Input ────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);
$empId = trim($input["pyempcde"] ?? "");

if (empty($empId)) {
    echo json_encode(["status" => "error", "message" => "Employee ID required"]);
    exit;
}

// ── Date range: today back 30 days ────────────────────────────────────
$today    = new DateTime();
$fromDate = (clone $today)->modify("-29 days"); // 30 days total including today

// ── Fetch attendance records from pyacslog ───────────────────────────
// Adjust column names below if yours differ:
//   pyacsdte  → the date column  (DATE or DATETIME)
//   pyacstme  → time column      (TIME or DATETIME/VARCHAR)
//   pyacstyp  → type column      ('I' = Check-In, 'O' = Check-Out)  ← adjust as needed
$stmt = $pdo->prepare("
    SELECT
        DATE(pyacsdte)                        AS log_date,
        MIN(CASE WHEN pyacstyp = 'I' THEN TIME_FORMAT(pyacstme, '%h:%i %p') END) AS check_in,
        MAX(CASE WHEN pyacstyp = 'O' THEN TIME_FORMAT(pyacstme, '%h:%i %p') END) AS check_out
    FROM pyacslog
    WHERE pyempcde = :empId
      AND DATE(pyacsdte) BETWEEN :fromDate AND :toDate
    GROUP BY DATE(pyacsdte)
    ORDER BY log_date DESC
");

$stmt->execute([
    ":empId"    => $empId,
    ":fromDate" => $fromDate->format("Y-m-d"),
    ":toDate"   => $today->format("Y-m-d"),
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Index rows by date for quick lookup
$attendanceMap = [];
foreach ($rows as $row) {
    $attendanceMap[$row["log_date"]] = $row;
}

// ── Build the 30-day record list ──────────────────────────────────────
$records = [];
$cursor  = clone $today;

for ($i = 0; $i < 30; $i++) {
    $dateKey    = $cursor->format("Y-m-d");
    $dayOfWeek  = (int) $cursor->format("N"); // 1=Mon … 7=Sun

    if ($dayOfWeek === 5 || $dayOfWeek === 6) {
        // Friday & Saturday → weekend (adjust if your weekend differs)
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => null,
            "check_out" => null,
            "status"    => "weekend",
        ];
    } elseif (isset($attendanceMap[$dateKey])) {
        $row = $attendanceMap[$dateKey];
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => $row["check_in"]  ?: null,
            "check_out" => $row["check_out"] ?: null,
            "status"    => "present",
        ];
    } else {
        // Past weekday with no record = absent
        // Future dates = skip (optional: mark as 'future')
        if ($cursor <= $today) {
            $records[] = [
                "date"      => $dateKey,
                "check_in"  => null,
                "check_out" => null,
                "status"    => "absent",
            ];
        }
    }

    $cursor->modify("-1 day");
}

echo json_encode([
    "status"  => "success",
    "records" => $records,
]);
