<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$date_from   = $_GET['date_from'] ?? date("Y-m-01");
$date_to     = $_GET['date_to']   ?? date("Y-m-d");
$filter_emp  = trim($_GET['emp']  ?? '');
$filter_post = trim($_GET['post'] ?? '');

// ── Generate date range ───────────────────────────────────────────
$dates   = [];
$current = strtotime($date_from);
$end     = strtotime($date_to);
while ($current <= $end) {
    $dates[] = date("Y-m-d", $current);
    $current = strtotime("+1 day", $current);
}

// ── Fetch employees ───────────────────────────────────────────────
$emp_where  = "WHERE 1=1";
$emp_params = [];
$emp_types  = "";

if ($filter_emp !== '') {
    $emp_where   .= " AND pyempcde = ?";
    $emp_params[] = $filter_emp;
    $emp_types   .= "s";
}
if ($filter_post !== '') {
    $emp_where   .= " AND pyempost = ?";
    $emp_params[] = $filter_post;
    $emp_types   .= "s";
}

$emp_sql = "SELECT pyempcde, pyempnam, pyempost, designation
            FROM pyempmas $emp_where
            ORDER BY pyempost ASC, pyempnam ASC";

if ($emp_types !== '') {
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->bind_param($emp_types, ...$emp_params);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
} else {
    $emp_result = $conn->query($emp_sql);
}

$employees = [];
while ($row = $emp_result->fetch_assoc()) {
    $employees[] = $row;
}

// ── Fetch attendance ──────────────────────────────────────────────
$att_where  = "WHERE DATE(LOGDTIME) BETWEEN ? AND ?";
$att_params = [$date_from, $date_to];
$att_types  = "ss";

if ($filter_emp !== '') {
    $att_where   .= " AND EMPLCODE = ?";
    $att_params[] = $filter_emp;
    $att_types   .= "s";
}

$att_sql = "
    SELECT EMPLCODE,
        DATE(LOGDTIME) as att_date,
        MIN(LOGDTIME) as check_in,
        MAX(LOGDTIME) as check_out,
        COUNT(*) as punches
    FROM pyacslog
    $att_where
    GROUP BY EMPLCODE, DATE(LOGDTIME)
";

$att_stmt = $conn->prepare($att_sql);
$att_stmt->bind_param($att_types, ...$att_params);
$att_stmt->execute();
$att_result = $att_stmt->get_result();

$att_map = [];
while ($row = $att_result->fetch_assoc()) {
    $att_map[$row['EMPLCODE']][$row['att_date']] = $row;
}

// ── Count working days ────────────────────────────────────────────
$working_days = 0;
foreach ($dates as $d) {
    $dow = date("N", strtotime($d));
    if ($dow != 5 && $dow != 6) $working_days++;
}

// ── Download as XLS ───────────────────────────────────────────────
$filename = "monthly_report_" . $date_from . "_to_" . $date_to . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
    td  { mso-number-format: "\@"; font-size: 11pt; }
    .num { mso-number-format: "0"; }
    .hdr { background: #644BA4; color: white; font-weight: bold; text-align: center; }
    .present  { background: #c8e6c9; text-align: center; font-weight: bold; }
    .half     { background: #ffe0b2; text-align: center; font-weight: bold; }
    .absent   { background: #ffcdd2; text-align: center; }
    .weekend  { background: #f5f5f5; text-align: center; color: #aaa; }
    .center   { text-align: center; }
</style>
</head>
<body>

<!-- ── Sheet Title ─────────────────────────────────────────────── -->
<table border="0">
<tr><td style="font-size:14pt; font-weight:bold; color:#644BA4;">
    Union Bank PLC — Monthly Attendance Report
</td></tr>
<tr><td>Period: <?= date("d M Y", strtotime($date_from)) ?> to <?= date("d M Y", strtotime($date_to)) ?></td></tr>
<?php if ($filter_post): ?>
<tr><td>Posting: <?= htmlspecialchars($filter_post) ?></td></tr>
<?php endif; ?>
<tr><td>&nbsp;</td></tr>
</table>

<!-- ── Day-by-day detail ──────────────────────────────────────── -->
<table border="1">
<tr>
    <td class="hdr">#</td>
    <td class="hdr">Employee ID</td>
    <td class="hdr">Name</td>
    <td class="hdr">Posting</td>
    <?php foreach ($dates as $d): ?>
        <td class="hdr"><?= date("d", strtotime($d)) ?><br><?= date("D", strtotime($d)) ?></td>
    <?php endforeach; ?>
    <td class="hdr">Present</td>
    <td class="hdr">Half Day</td>
    <td class="hdr">Absent</td>
</tr>
<?php
$i = 1;
foreach ($employees as $emp):
    $emp_id   = $emp['pyempcde'];
    $present  = 0;
    $half_day = 0;
?>
<tr>
    <td class="center num"><?= $i++ ?></td>
    <td><?= $emp_id ?></td>
    <td><?= htmlspecialchars($emp['pyempnam']) ?></td>
    <td><?= htmlspecialchars($emp['pyempost'] ?? '') ?></td>
    <?php foreach ($dates as $d):
        $dow = date("N", strtotime($d));
        $rec = $att_map[$emp_id][$d] ?? null;

        if ($dow == 5 || $dow == 6):
            echo "<td class='weekend'>-</td>";
        elseif ($rec):
            if ($rec['punches'] >= 2) {
                $present++;
                $ci = date("h:i", strtotime($rec['check_in']));
                $co = date("h:i", strtotime($rec['check_out']));
                echo "<td class='present'>P</td>";
            } else {
                $half_day++;
                echo "<td class='half'>H</td>";
            }
        else:
            echo "<td class='absent'>A</td>";
        endif;
    endforeach;

    $absent = $working_days - $present - $half_day;
    ?>
    <td class="center num" style="background:#c8e6c9; font-weight:bold"><?= $present ?></td>
    <td class="center num" style="background:#ffe0b2; font-weight:bold"><?= $half_day ?></td>
    <td class="center num" style="background:#ffcdd2; font-weight:bold"><?= $absent ?></td>
</tr>
<?php endforeach; ?>
</table>

<br>

<!-- ── Summary ────────────────────────────────────────────────── -->
<table border="1">
<tr>
    <td class="hdr">#</td>
    <td class="hdr">Employee ID</td>
    <td class="hdr">Name</td>
    <td class="hdr">Posting</td>
    <td class="hdr">Designation</td>
    <td class="hdr">Working Days</td>
    <td class="hdr">Present</td>
    <td class="hdr">Half Day</td>
    <td class="hdr">Absent</td>
    <td class="hdr">Attendance %</td>
</tr>
<?php
$i = 1;
foreach ($employees as $emp):
    $emp_id   = $emp['pyempcde'];
    $present  = 0;
    $half_day = 0;

    foreach ($dates as $d) {
        $dow = date("N", strtotime($d));
        if ($dow == 5 || $dow == 6) continue;
        $rec = $att_map[$emp_id][$d] ?? null;
        if ($rec) {
            if ($rec['punches'] >= 2) $present++;
            else $half_day++;
        }
    }

    $absent = $working_days - $present - $half_day;
    $pct    = $working_days > 0 ? round(($present / $working_days) * 100) : 0;
?>
<tr>
    <td class="center num"><?= $i++ ?></td>
    <td><?= $emp_id ?></td>
    <td><?= htmlspecialchars($emp['pyempnam']) ?></td>
    <td><?= htmlspecialchars($emp['pyempost'] ?? '') ?></td>
    <td><?= htmlspecialchars($emp['designation'] ?? '') ?></td>
    <td class="center num"><?= $working_days ?></td>
    <td class="center num" style="background:#c8e6c9; font-weight:bold"><?= $present ?></td>
    <td class="center num" style="background:#ffe0b2; font-weight:bold"><?= $half_day ?></td>
    <td class="center num" style="background:#ffcdd2; font-weight:bold"><?= $absent ?></td>
    <td class="center num"><?= $pct ?>%</td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
