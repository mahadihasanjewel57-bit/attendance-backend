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
$filter_emp  = trim($_GET['emp']  ?? '');
$filter_post = trim($_GET['post'] ?? '');
$filter_flag = trim($_GET['flag'] ?? '');

// ── Load attendance settings ──────────────────────────────────────
$settings = [];
$sRes = $conn->query("SHOW TABLES LIKE 'attendance_settings'");
if ($sRes && $sRes->num_rows > 0) {
    $sRes2 = $conn->query("SELECT setting_key, setting_val FROM attendance_settings");
    if ($sRes2) {
        while ($r = $sRes2->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_val'];
    }
}
$entry_time  = $settings['entry_time']          ?? '09:00';
$exit_time   = $settings['exit_time']           ?? '17:00';
$late_grace  = (int)($settings['late_grace_minutes']  ?? 10);
$early_grace = (int)($settings['early_grace_minutes'] ?? 10);
$lateThreshold      = DateTime::createFromFormat('H:i', $entry_time)->modify("+{$late_grace} minutes");
$earlyExitThreshold = DateTime::createFromFormat('H:i', $exit_time)->modify("-{$early_grace} minutes");

// ── Build query ───────────────────────────────────────────────────
$where  = "WHERE DATE(p.LOGDTIME) = ?";
$params = [$filter_date];
$types  = "s";
if ($filter_emp !== '') { $where .= " AND p.EMPLCODE = ?"; $params[] = $filter_emp; $types .= "s"; }
if ($filter_post !== '') {
    if ($filter_post === 'Head Office') { $where .= " AND m.pyempost LIKE ?"; $params[] = "%Head Office%"; $types .= "s"; }
    else { $where .= " AND m.pyempost = ?"; $params[] = $filter_post; $types .= "s"; }
}
$stmt = $conn->prepare("
    SELECT p.EMPLCODE, m.pyempnam, m.pyempost,
        MIN(p.LOGDTIME) as check_in,
        MAX(p.LOGDTIME) as check_out,
        COUNT(*) as punches
    FROM pyacslog p
    LEFT JOIN pyempmas m ON p.EMPLCODE = m.pyempcde
    $where
    GROUP BY p.EMPLCODE, m.pyempnam, m.pyempost
    ORDER BY check_in ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ── Pre-fetch + compute flags ─────────────────────────────────────
$all_rows = [];
while ($row = $result->fetch_assoc()) {
    $co_raw = $row['punches'] > 1 ? $row['check_out'] : null;
    $flags  = [];
    $ciTime = DateTime::createFromFormat('H:i', date('H:i', strtotime($row['check_in'])));
    if ($ciTime && $ciTime > $lateThreshold) $flags[] = 'late';
    if ($co_raw) {
        $coTime = DateTime::createFromFormat('H:i', date('H:i', strtotime($co_raw)));
        if ($coTime && $coTime < $earlyExitThreshold) $flags[] = 'early_exit';
    }
    $row['flags'] = $flags;
    $all_rows[] = $row;
}

// ── Apply flag filter ─────────────────────────────────────────────
$rows = [];
foreach ($all_rows as $row) {
    $f = $row['flags'];
    if ($filter_flag === 'late')       { if (in_array('late', $f) && !in_array('early_exit', $f)) $rows[] = $row; }
    elseif ($filter_flag === 'early_exit') { if (in_array('early_exit', $f) && !in_array('late', $f)) $rows[] = $row; }
    elseif ($filter_flag === 'both')   { if (in_array('late', $f) && in_array('early_exit', $f)) $rows[] = $row; }
    elseif ($filter_flag === 'ok')     { if (empty($f)) $rows[] = $row; }
    else                               { $rows[] = $row; }
}

// ── File name reflects active filter ─────────────────────────────
$flag_suffix = $filter_flag ? "_{$filter_flag}" : '';
$filename    = "attendance_{$filter_date}{$flag_suffix}.xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// ── Report title label ────────────────────────────────────────────
$flag_label = [
    'late'       => 'Late Arrivals',
    'early_exit' => 'Early Exits',
    'both'       => 'Late + Early Exit',
    'ok'         => 'On Time',
    ''           => 'All Employees',
][$filter_flag] ?? 'All Employees';
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
<table border="1" cellpadding="5" cellspacing="0" style="width:auto">
    <thead>
        <tr>
            <th colspan="8" style="text-align:center; padding:15px;">
                <div style="font-size:22px; font-weight:bold;">UNION BANK PLC.</div>
                <div style="font-size:16px; margin-top:5px;">Daily Attendance Information</div>
                <?php if ($filter_flag): ?>
                <div style="font-size:13px; margin-top:4px; color:#c62828;">Status: <?= htmlspecialchars($flag_label) ?></div>
                <?php endif; ?>
            </th>
        </tr>
        <tr>
            <th>No</th>
            <th>Employee ID</th>
            <th>Employee Name</th>
            <th>Place of Posting</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Remarks</th>
            <th>Date</th>
        </tr>
    </thead>
<?php
$i = 1;
foreach ($rows as $row):
    $ci     = date("h:i A", strtotime($row['check_in']));
    $co     = $row['punches'] > 1 ? date("h:i A", strtotime($row['check_out'])) : "--:--";
    $status = $row['punches'] > 1 ? "Complete" : "Checked In";
    $flags  = $row['flags'];
    $parts  = [];
    if (in_array('late', $flags))       $parts[] = 'Late';
    if (in_array('early_exit', $flags)) $parts[] = 'Early Exit';
    $remarks = empty($parts) ? 'On Time' : implode(' + ', $parts);
?>
<tr>
    <td class="num"><?= $i++ ?></td>
    <td><?= htmlspecialchars($row['EMPLCODE']) ?></td>
    <td><?= htmlspecialchars($row['pyempnam'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['pyempost'] ?? '') ?></td>
    <td><?= $ci ?></td>
    <td><?= $co ?></td>
    <td><?= $remarks ?></td>
    <td><?= $filter_date ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
