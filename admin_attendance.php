<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
if (($_COOKIE['admin_token'] ?? '') !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$today       = date("Y-m-d");
$filter_date = $_GET['date'] ?? $today;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report — Union Bank</title>
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
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-card label { display: block; font-size: 12px; color: #888; margin-bottom: 6px; }
        .filter-card input {
            padding: 9px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        .filter-card input:focus { border-color: #644BA4; }
        .filter-card button {
            padding: 9px 20px;
            background: #644BA4;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            font-weight: bold;
        }
        .filter-card button:hover { background: #5D0476; }
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
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_attendance.php">Attendance</a>
        <a href="admin_employees.php">Employees</a>
        <a href="admin_device.php">Devices</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <form method="GET">
        <div class="filter-card">
            <div>
                <label>Date</label>
                <input type="date" name="date"
                    value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div>
                <label>Employee ID (optional)</label>
                <input type="text" name="emp"
                    placeholder="e.g. EMP001"
                    value="<?= htmlspecialchars($filter_emp) ?>">
            </div>
            <button type="submit">🔍 Search</button>
        </div>
    </form>

    <div class="section-title">
        📋 Attendance Report — <?= date("d M Y", strtotime($filter_date)) ?>
    </div>
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
        while ($row = $result->fetch_assoc()):
            $ci     = date("h:i A", strtotime($row['check_in']));
            $co     = $row['punches'] > 1
                        ? date("h:i A", strtotime($row['check_out']))
                        : "--:--";
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
                <td colspan="7"
                    style="text-align:center; color:#888; padding:24px;">
                    No attendance found for this date
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
