<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$today = date("Y-m-d");

$result = $conn->query("
    SELECT m.pyempcde, m.pyempnam,
        d.pydevice,
        l.latitude, l.longitude,
        (SELECT COUNT(*) FROM pyacslog p
            WHERE p.EMPLCODE = m.pyempcde
            AND DATE(p.LOGDTIME) = '$today') as today_punches
    FROM pyempmas m
    LEFT JOIN emdevice d ON m.pyempcde = d.pyempcde
    LEFT JOIN pyemploc l ON m.pyempcde = l.pyempcde
    ORDER BY m.pyempcde ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees — Union Bank</title>
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
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 13px;
            margin-left: 16px;
            padding: 6px 12px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 6px;
        }
        .navbar a:hover { background: rgba(255,255,255,0.2); }

        .container { padding: 24px; max-width: 1100px; margin: auto; }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-collapse: collapse;
            overflow: hidden;
        }
        th {
            background: #644BA4;
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
        }
        td {
            padding: 12px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #faf8ff; }

        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-green  { background: #e8f5e9; color: #2e7d32; }
        .badge-red    { background: #fdecea; color: #c0392b; }
        .badge-orange { background: #fff3e0; color: #e65100; }
        .badge-blue   { background: #e3f2fd; color: #1565c0; }

        .nav-links { display: flex; gap: 8px; }

        .device-text {
            font-size: 11px;
            color: #888;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
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

    <div class="section-title">👥 Employee List</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Device</th>
                <th>GPS Location</th>
                <th>Today</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while ($row = $result->fetch_assoc()):
            $has_device = !empty($row['pydevice']);
            $has_loc    = !empty($row['latitude']);
            $punches    = intval($row['today_punches']);

            if ($punches >= 2) {
                $today_status = "<span class='badge badge-green'>Complete</span>";
            } elseif ($punches === 1) {
                $today_status = "<span class='badge badge-orange'>Checked In</span>";
            } else {
                $today_status = "<span class='badge badge-red'>Absent</span>";
            }

            $overall = ($has_device && $has_loc)
                ? "<span class='badge badge-blue'>Ready</span>"
                : "<span class='badge badge-red'>Incomplete</span>";
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['pyempcde']) ?></td>
                <td><?= htmlspecialchars($row['pyempnam']) ?></td>
                <td>
                    <?php if ($has_device): ?>
                        <span class='badge badge-green'>Registered</span>
                        <div class='device-text'><?= htmlspecialchars(substr($row['pydevice'], 0, 20)) ?>...</div>
                    <?php else: ?>
                        <span class='badge badge-red'>Not Registered</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($has_loc): ?>
                        <span class='badge badge-green'>Assigned</span>
                        <div class='device-text'>
                            <?= $row['latitude'] ?>, <?= $row['longitude'] ?>
                        </div>
                    <?php else: ?>
                        <span class='badge badge-red'>Not Assigned</span>
                    <?php endif; ?>
                </td>
                <td><?= $today_status ?></td>
                <td><?= $overall ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</div>
</body>
</html>
