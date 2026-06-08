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

// ── Download as TXT ───────────────────────────────────────────────
$filename = "pyacslog_sql_" . $filter_date . ".txt";
header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$rows = [];
while ($row = $result->fetch_assoc()) {
    $logdtime = date("d/m/Y H:i:s", strtotime($row['LOGDTIME']));
    $slogtime = date("d/m/Y H:i:s", strtotime($row['SLOGTIME']));

    $compcode = $row['COMPCODE'] ?? '200';
    $emplcode = $row['EMPLCODE'];
    $logindex = $row['LOGINDEX'] !== null ? "'" . $row['LOGINDEX'] . "'" : "NULL";
    $nodindex = $row['NODINDEX'] !== null ? "'" . $row['NODINDEX'] . "'" : "NULL";
    $nodecode = $row['NODECODE'] !== null ? "'" . $row['NODECODE'] . "'" : "NULL";
    $authtype = $row['AUTHTYPE'] !== null ? $row['AUTHTYPE'] : "NULL";
    $authrslt = $row['AUTHRSLT'] !== null ? $row['AUTHRSLT'] : "NULL";
    $openrslt = $row['OPENRSLT'] !== null ? $row['OPENRSLT'] : "NULL";
    $funcnumb = $row['FUNCNUMB'] !== null ? $row['FUNCNUMB'] : "NULL";
    $checkflg = $row['CHECKFLG'] !== null ? $row['CHECKFLG'] : "NULL";
    $termname = $row['TERMNAME'] !== null ? "'" . $row['TERMNAME'] . "'" : "NULL";
    $brancode = $row['BRANCODE'] !== null ? "'" . $row['BRANCODE'] . "'" : "NULL";
    $lgstatus = $row['LGSTATUS'] !== null ? "'" . $row['LGSTATUS'] . "'" : "'N'";
    $remarkss = $row['REMARKSS'] !== null ? "'" . $row['REMARKSS'] . "'" : "'APPS'";
    $authcode = $row['AUTHCODE'] !== null ? "'" . $row['AUTHCODE'] . "'" : "NULL";
    $pyacsenf = $row['PYACSENF'] !== null ? "'" . $row['PYACSENF'] . "'" : "'N'";

    $rows[] = "    INTO ORBHRM.PYACSLOG (
        COMPCODE, LOGINDEX, NODINDEX, LOGDTIME, EMPLCODE,
        AUTHTYPE, AUTHRSLT, OPENRSLT, FUNCNUMB, NODECODE,
        SLOGTIME, CHECKFLG, TERMNAME, BRANCODE, LGSTATUS,
        REMARKSS, AUTHCODE, PYACSENF
    )
    VALUES (
        '{$compcode}', {$logindex}, {$nodindex},
        TO_DATE('{$logdtime}', 'DD/MM/YYYY HH24:MI:SS'),
        '{$emplcode}',
        {$authtype}, {$authrslt}, {$openrslt}, {$funcnumb}, {$nodecode},
        TO_DATE('{$slogtime}', 'DD/MM/YYYY HH24:MI:SS'),
        {$checkflg}, {$termname}, {$brancode}, {$lgstatus},
        {$remarkss}, {$authcode}, {$pyacsenf}
    )";
}

if (count($rows) > 0) {
    echo "INSERT ALL\n";
    echo implode("\n", $rows);
    echo "\nSELECT 1 FROM DUAL;";
} else {
    echo "-- No records found for date: $filter_date";
}
?>
