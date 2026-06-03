<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$filter_date = $_GET['date'] ?? date("Y-m-d");
$filter_emp  = trim($_GET['emp'] ?? '');

$where  = "WHERE DATE(p.LOGDTIME) = ?";
$params = [$filter_date];
$types  = "s";

if ($filter_emp !== '') {
    $where   .= " AND p.EMPLCODE = ?";
    $params[] = $filter_emp;
    $types   .= "s";
}

$sql = "
    SELECT p.EMPLCODE, m.pyempnam,
        MIN(p.LOGDTIME) as check_in,
        MAX(p.LOGDTIME) as check_out,
        COUNT(*) as punches
    FROM pyacslog p
    LEFT JOIN pyempmas m ON p.EMPLCODE = m.pyempcde
    $where
    GROUP BY p.EMPLCODE, m.pyempnam
    ORDER BY check_in ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = "attendance_" . $filter_date . ".csv";
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$out = fopen("php://output", "w");

// ── Header row ────────────────────────────────────────────────────
fputcsv($out, [
    'No', 'Employee ID', 'Employee Name',
    'Check In', 'Check Out', 'Total Punches', 'Status', 'Date'
]);

// ── Data rows ─────────────────────────────────────────────────────
$i = 1;
while ($row = $result->fetch_assoc()) {
    $ci     = date("h:i A", strtotime($row['check_in']));
    $co     = $row['punches'] > 1
                ? date("h:i A", strtotime($row['check_out']))
                : "--:--";
    $status = $row['punches'] > 1 ? "Complete" : "Checked In";

    fputcsv($out, [
        $i,
        "'" . $row['EMPLCODE'],
        $row['pyempnam'],
        $ci,
        $co,
        $row['punches'],
        $status,
        $filter_date
    ]);
    $i++;
}

fclose($out);
?>
