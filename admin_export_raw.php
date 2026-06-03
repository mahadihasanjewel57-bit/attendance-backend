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

$where  = "WHERE DATE(LOGDTIME) = ?";
$params = [$filter_date];
$types  = "s";

if ($filter_emp !== '') {
    $where   .= " AND EMPLCODE = ?";
    $params[] = $filter_emp;
    $types   .= "s";
}

$sql  = "SELECT * FROM pyacslog $where ORDER BY LOGDTIME ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = "pyacslog_raw_" . $filter_date . ".csv";
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$out = fopen("php://output", "w");

// ── Header row ────────────────────────────────────────────────────
fputcsv($out, [
    'COMPCODE', 'LOGINDEX', 'NODINDEX', 'LOGDTIME',
    'EMPLCODE', 'NODECODE', 'AUTHTYPE', 'AUTHRSLT',
    'OPENRSLT', 'FUNCNUMB', 'SLOGTIME', 'CHECKFLG',
    'TERMNAME', 'BRANCODE', 'LGSTATUS', 'REMARKSS',
    'AUTHCODE', 'PYACSENF'
]);

// ── Data rows ─────────────────────────────────────────────────────
while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['COMPCODE'],
        $row['LOGINDEX'],
        $row['NODINDEX'],
        $row['LOGDTIME'],
    "=" . '"' . $row['EMPLCODE'] . '"',
        $row['NODECODE'],
        $row['AUTHTYPE'],
        $row['AUTHRSLT'],
        $row['OPENRSLT'],
        $row['FUNCNUMB'],
        $row['SLOGTIME'],
        $row['CHECKFLG'],
        $row['TERMNAME'],
        $row['BRANCODE'],
        $row['LGSTATUS'],
        $row['REMARKSS'],
        $row['AUTHCODE'],
        $row['PYACSENF']
    ]);
}

fclose($out);
?>
