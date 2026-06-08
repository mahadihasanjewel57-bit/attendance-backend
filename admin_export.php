<?php
// admin_export.php — Export attendance as Excel CSV with Late/Early Exit column
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
    ORDER BY m.pyempost ASC, check_in ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ── Output CSV ────────────────────────────────────────────────────
$filename = "attendance_{$filter_date}.xls";
header('Content-Type: text/xls; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

// Header row
fputcsv($out, [
    '#', 'Employee ID', 'Name', 'Posting',
    'Check In', 'Check Out', 'Punches', 'Status', 'Remarks',
    'Entry Time Setting', 'Exit Time Setting'
]);

$i = 1;
while ($row = $result->fetch_assoc()) {
    $ci     = date("h:i A", strtotime($row['check_in']));
    $co     = $row['punches'] > 1 ? date("h:i A", strtotime($row['check_out'])) : "--:--";
    $status = $row['punches'] > 1 ? "Complete" : "Checked In";

    // Flags
    $flags = [];
    $ciTime = DateTime::createFromFormat('H:i', date('H:i', strtotime($row['check_in'])));
    if ($ciTime && $ciTime > $lateThreshold) $flags[] = 'Late';

    if ($row['punches'] > 1) {
        $coTime = DateTime::createFromFormat('H:i', date('H:i', strtotime($row['check_out'])));
        if ($coTime && $coTime < $earlyExitThreshold) $flags[] = 'Early Exit';
    }

    $remarks = empty($flags) ? 'On Time' : implode(' + ', $flags);

    fputcsv($out, [
        $i++,
        $row['EMPLCODE'],
        $row['pyempnam'] ?? 'N/A',
        $row['pyempost'] ?? '',
        $ci, $co,
        $row['punches'],
        $status,
        $remarks,
        $entry_time,
        $exit_time,
    ]);
}

fclose($out);
exit;
