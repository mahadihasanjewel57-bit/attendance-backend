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

// ── Download as Excel ─────────────────────────────────────────────
$filename = "pyacslog_raw_" . $filter_date . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";

// ── Header row — all columns ──────────────────────────────────────
echo "<tr>
    <th>COMPCODE</th>
    <th>LOGINDEX</th>
    <th>NODINDEX</th>
    <th>LOGDTIME</th>
    <th>EMPLCODE</th>
    <th>NODECODE</th>
    <th>AUTHTYPE</th>
    <th>AUTHRSLT</th>
    <th>OPENRSLT</th>
    <th>FUNCNUMB</th>
    <th>SLOGTIME</th>
    <th>CHECKFLG</th>
    <th>TERMNAME</th>
    <th>BRANCODE</th>
    <th>LGSTATUS</th>
    <th>REMARKSS</th>
    <th>AUTHCODE</th>
    <th>PYACSENF</th>
</tr>";

// ── Data rows ─────────────────────────────────────────────────────
while ($row = $result->fetch_assoc()) {
    echo "<tr>
        <td>{$row['COMPCODE']}</td>
        <td>{$row['LOGINDEX']}</td>
        <td>{$row['NODINDEX']}</td>
        <td>{$row['LOGDTIME']}</td>
        <td>{$row['EMPLCODE']}</td>
        <td>{$row['NODECODE']}</td>
        <td>{$row['AUTHTYPE']}</td>
        <td>{$row['AUTHRSLT']}</td>
        <td>{$row['OPENRSLT']}</td>
        <td>{$row['FUNCNUMB']}</td>
        <td>{$row['SLOGTIME']}</td>
        <td>{$row['CHECKFLG']}</td>
        <td>{$row['TERMNAME']}</td>
        <td>{$row['BRANCODE']}</td>
        <td>{$row['LGSTATUS']}</td>
        <td>{$row['REMARKSS']}</td>
        <td>{$row['AUTHCODE']}</td>
        <td>{$row['PYACSENF']}</td>
    </tr>";
}

echo "</table>";
?>
