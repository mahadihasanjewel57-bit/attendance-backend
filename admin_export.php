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

$filename = "attendance_" . $filter_date . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
    td { mso-number-format: "\@"; }
    .num { mso-number-format: "0"; }
</style>
</head>
<body>
<table border="1">
<tr>
    <th>No</th>
    <th>Employee ID</th>
    <th>Employee Name</th>
    <th>Check In</th>
    <th>Check Out</th>
    <th>Total Punches</th>
    <th>Status</th>
    <th>Date</th>
</tr>
<?php
$i = 1;
while ($row = $result->fetch_assoc()):
    $ci     = date("h:i A", strtotime($row['check_in']));
    $co     = $row['punches'] > 1
                ? date("h:i A", strtotime($row['check_out']))
                : "--:--";
    $status = $row['punches'] > 1 ? "Complete" : "Checked In";
?>
<tr>
    <td class="num"><?= $i++ ?></td>
    <td><?= $row['EMPLCODE'] ?></td>
    <td><?= $row['pyempnam'] ?></td>
    <td><?= $ci ?></td>
    <td><?= $co ?></td>
    <td class="num"><?= $row['punches'] ?></td>
    <td><?= $status ?></td>
    <td><?= $filter_date ?></td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
