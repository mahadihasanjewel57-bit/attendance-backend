<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";

$employees = $conn->query("
    SELECT m.pyempcde, m.pyempnam,
        d.pydevice,
        l.latitude, l.longitude
    FROM pyempmas m
    LEFT JOIN emdevice d ON m.pyempcde = d.pyempcde
    LEFT JOIN pyemploc l ON m.pyempcde = l.pyempcde
    ORDER BY m.pyempnam ASC
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
        .nav-links { display: flex; gap: 8px; }
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 13px;
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
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        .badge-red { background: #fdecea; color: #c0392b; }
        .badge-blue { background: #e3f2fd; color: #1565c0; }
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

    <div class="section-title">👥 Employee List</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Device</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while ($row = $employees->fetch_assoc()):
            $hasDevice = !empty($row['pydevice']);
            $hasLocation = !empty($row['latitude']);
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['pyempcde']) ?></td>
                <td><?= htmlspecialchars($row['pyempnam']) ?></td>
                <td>
                    <?php if ($hasDevice): ?>
                        <span class="badge badge-green">✔ Registered</span>
                    <?php else: ?>
                        <span class="badge badge-red">✘ Not Registered</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($hasLocation): ?>
                        <span class="badge badge-blue">
                            <?= $row['latitude'] ?>, <?= $row['longitude'] ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-orange">✘ Not Assigned</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php if ($i === 1): ?>
            <tr>
                <td colspan="5" style="text-align:center; color:#888; padding:24px;">
                    No employees found
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>
