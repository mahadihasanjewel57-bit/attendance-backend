<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$today = date("Y-m-d");
$t     = AUTH_TOKEN;

$total_emp     = $conn->query("SELECT COUNT(*) as cnt FROM pyempmas")->fetch_assoc()['cnt'];
$total_dev     = $conn->query("SELECT COUNT(*) as cnt FROM emdevice")->fetch_assoc()['cnt'];
$total_punch   = $conn->query("SELECT COUNT(*) as cnt FROM pyacslog WHERE DATE(LOGDTIME) = '$today'")->fetch_assoc()['cnt'];
$total_present = $conn->query("SELECT COUNT(DISTINCT EMPLCODE) as cnt FROM pyacslog WHERE DATE(LOGDTIME) = '$today'")->fetch_assoc()['cnt'];

$att = $conn->query("
    SELECT p.EMPLCODE, m.pyempnam,
        MIN(p.LOGDTIME) as check_in,
        MAX(p.LOGDTIME) as check_out,
        COUNT(*) as punches
    FROM pyacslog p
    LEFT JOIN pyempmas m ON p.EMPLCODE = m.pyempcde
    WHERE DATE(p.LOGDTIME) = '$today'
    GROUP BY p.EMPLCODE, m.pyempnam
    ORDER BY check_in ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Union Bank</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f3f0fa; }
        .navbar {
            background: #644BA4;
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { font-size: 18px; }
        .nav-links { display: flex; gap: 8px; }
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 13px;
            margin-left: 8px;
            padding: 6px 12px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 6px;
        }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { padding: 24px; max-width: 1100px; margin: auto; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #644BA4; }
        .stat-card .label  { font-size: 13px; color: #888; margin-top: 4px; }
        .section-title { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 12px; }
        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-collapse: collapse;
            overflow: hidden;
        }
        th { background: #644BA4; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
        td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid #f0f0f0; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #faf8ff; }
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-green  { background: #e8f5e9; color: #2e7d32; }
        .badge-orange { background: #fff3e0; color: #e65100; }
    </style>
</head>
<body>
<div class="navbar">
     <h1>🏦 Union Bank — Admin Panel</h1>
    <div class="nav-links">
        <a href="admin_dashboard.php?token=<?= $t ?>">Dashboard</a>
        <a href="admin_attendance.php?token=<?= $t ?>">Attendance</a>
        <a href="admin_monthly.php?token=<?= $t ?>">Monthly</a>
        <a href="admin_observation.php?token=<?= $t ?>">Observation</a>
        <a href="admin_employees_edit.php?token=<?= $t ?>">Edit Employees</a>
        <a href="admin_device.php?token=<?= $t ?>">Devices</a>
        <a href="admin_import.php?token=<?= $t ?>">Import</a>
         <a href="admin_settings.php?token=<?= $t ?>" class="active">Settings</a>
          <a href=" admin_attendance_delete.php?token=<?= $t ?>" class="active">Delete</a>
       
        <a href="admin_login.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?= $total_emp ?></div>
            <div class="label">Total Employees</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $total_dev ?></div>
            <div class="label">Registered Devices</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $total_present ?></div>
            <div class="label">Present Today</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $total_punch ?></div>
            <div class="label">Total Punches Today</div>
        </div>
    </div>

    <div class="section-title">📋 Today's Attendance — <?= date("d M Y") ?></div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Punches</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while ($row = $att->fetch_assoc()):
            $ci     = date("h:i A", strtotime($row['check_in']));
            $co     = $row['punches'] > 1 ? date("h:i A", strtotime($row['check_out'])) : "--:--";
            $status = $row['punches'] > 1 ? "Complete" : "Checked In";
            $badge  = $row['punches'] > 1 ? "badge-green" : "badge-orange";
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['EMPLCODE']) ?></td>
                <td><?= htmlspecialchars($row['pyempnam'] ?? 'N/A') ?></td>
                <td><?= $ci ?></td>
                <td><?= $co ?></td>
                <td><?= $row['punches'] ?></td>
                <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
            </tr>
        <?php endwhile; ?>
        <?php if ($i === 1): ?>
            <tr>
                <td colspan="7" style="text-align:center; color:#888; padding:24px;">
                    No attendance recorded today
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
