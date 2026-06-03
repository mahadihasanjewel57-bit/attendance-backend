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

$filename = "pyacslog_raw_" . $filter_date . ".xls";
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
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td class="num"><?= $row['COMPCODE'] ?></td>
    <td class="num"><?= $row['LOGINDEX'] ?></td>
    <td class="num"><?= $row['NODINDEX'] ?></td>
    <td><?= $row['LOGDTIME'] ?></td>
    <td><?= $row['EMPLCODE'] ?></td>
    <td class="num"><?= $row['NODECODE'] ?></td>
    <td class="num"><?= $row['AUTHTYPE'] ?></td>
    <td class="num"><?= $row['AUTHRSLT'] ?></td>
    <td class="num"><?= $row['OPENRSLT'] ?></td>
    <td class="num"><?= $row['FUNCNUMB'] ?></td>
    <td><?= $row['SLOGTIME'] ?></td>
    <td class="num"><?= $row['CHECKFLG'] ?></td>
    <td><?= $row['TERMNAME'] ?></td>
    <td><?= $row['BRANCODE'] ?></td>
    <td><?= $row['LGSTATUS'] ?></td>
    <td><?= $row['REMARKSS'] ?></td>
    <td><?= $row['AUTHCODE'] ?></td>
    <td><?= $row['PYACSENF'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
