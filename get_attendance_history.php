<?php
// get_attendance_history.php
// Returns last 30 days attendance with late/early-exit detection

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// ── DB connection ─────────────────────────────────────────────────────
$host = getenv("MYSQLHOST")     ?: "mysql.railway.internal";
$db   = getenv("MYSQLDATABASE") ?: "railway";
$user = getenv("MYSQLUSER")     ?: "root";
$pass = getenv("MYSQLPASSWORD") ?: "";
$port = (int)(getenv("MYSQLPORT") ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed: " . $conn->connect_error]);
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

// ── Load attendance settings ──────────────────────────────────────────
$settings     = [];
$settingsExist = false;
$sRes = $conn->query("SHOW TABLES LIKE 'attendance_settings'");
if ($sRes && $sRes->num_rows > 0) {
    $sRes2 = $conn->query("SELECT setting_key, setting_val FROM attendance_settings");
    if ($sRes2) {
        while ($row = $sRes2->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_val'];
        }
        $settingsExist = true;
    }
}

// Defaults if table doesn't exist yet
$entry_time  = $settings['entry_time']          ?? '09:00';
$exit_time   = $settings['exit_time']           ?? '17:00';
$late_grace  = (int)($settings['late_grace_minutes']  ?? 10);
$early_grace = (int)($settings['early_grace_minutes'] ?? 10);

// Compute thresholds as DateTime for comparison
$lateThreshold      = DateTime::createFromFormat('H:i', $entry_time)->modify("+{$late_grace} minutes");
$earlyExitThreshold = DateTime::createFromFormat('H:i', $exit_time)->modify("-{$early_grace} minutes");

// ── Date range ────────────────────────────────────────────────────────
$today    = new DateTime();
$fromDate = (clone $today)->modify("-29 days");
$toStr    = $today->format("Y-m-d");
$fromStr  = $fromDate->format("Y-m-d");

// ── Query: first & last punch per day ────────────────────────────────
$sql = "
    SELECT
        DATE(LOGDTIME)                         AS log_date,
        TIME_FORMAT(MIN(LOGDTIME), '%H:%i')    AS check_in_raw,
        TIME_FORMAT(MAX(LOGDTIME), '%H:%i')    AS check_out_raw,
        TIME_FORMAT(MIN(LOGDTIME), '%h:%i %p') AS check_in,
        TIME_FORMAT(MAX(LOGDTIME), '%h:%i %p') AS check_out,
        COUNT(*)                               AS punch_count
    FROM pyacslog
    WHERE EMPLCODE = '$empId'
      AND DATE(LOGDTIME) BETWEEN '$fromStr' AND '$toStr'
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
    $attendanceMap[$row['log_date']] = $row;
}
$conn->close();

// ── Build 30-day list with late/early detection ───────────────────────
$records = [];
$cursor  = new DateTime();

for ($i = 0; $i < 30; $i++) {
    $dateKey   = $cursor->format("Y-m-d");
    $dayOfWeek = (int) $cursor->format("N"); // 1=Mon…7=Sun

    if ($dayOfWeek === 5 || $dayOfWeek === 6) {
        // Friday & Saturday → weekend
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => null,
            "check_out" => null,
            "status"    => "weekend",
            "flags"     => [],
        ];
    } elseif (isset($attendanceMap[$dateKey])) {
        $row         = $attendanceMap[$dateKey];
        $checkIn     = $row['check_in']  ?: null;
        $checkOut    = $row['check_out'] ?: null;
        $checkInRaw  = $row['check_in_raw'];
        $checkOutRaw = $row['check_out_raw'];

        // Single punch → no check-out
        if ($row['punch_count'] == 1) {
            $checkOut    = null;
            $checkOutRaw = null;
        }

        // ── Late detection ──────────────────────────────────────
        $flags  = [];
        $isLate = false;
        $isEarlyExit = false;

        if ($checkInRaw) {
            $ciTime = DateTime::createFromFormat('H:i', $checkInRaw);
            if ($ciTime && $ciTime > $lateThreshold) {
                $isLate  = true;
                $flags[] = "late";
            }
        }

        // ── Early exit detection ────────────────────────────────
        if ($checkOutRaw) {
            $coTime = DateTime::createFromFormat('H:i', $checkOutRaw);
            if ($coTime && $coTime < $earlyExitThreshold) {
                $isEarlyExit = true;
                $flags[]     = "early_exit";
            }
        }

        $records[] = [
            "date"      => $dateKey,
            "check_in"  => $checkIn,
            "check_out" => $checkOut,
            "status"    => "present",
            "flags"     => $flags,    // e.g. ["late"], ["early_exit"], ["late","early_exit"], []
        ];
    } else {
        // Weekday with no record → absent
        $records[] = [
            "date"      => $dateKey,
            "check_in"  => null,
            "check_out" => null,
            "status"    => "absent",
            "flags"     => [],
        ];
    }

    $cursor->modify("-1 day");
}

echo json_encode([
    "status"     => "success",
    "records"    => $records,
    "settings"   => [
        "entry_time"          => $entry_time,
        "exit_time"           => $exit_time,
        "late_grace_minutes"  => $late_grace,
        "early_grace_minutes" => $early_grace,
    ],
]);
