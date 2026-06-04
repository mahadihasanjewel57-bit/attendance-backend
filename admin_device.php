<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
$t = AUTH_TOKEN;

$message  = '';
$msg_type = '';

// ── Reset device ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_emp'])) {
    $emp_id = trim($_POST['reset_emp']);

    $stmt = $conn->prepare("DELETE FROM emdevice WHERE pyempcde = ?");
    $stmt->bind_param("s", $emp_id);

    if ($stmt->execute()) {
        $message  = "Device reset successfully for Employee: $emp_id. They can now register a new device.";
        $msg_type = "success";
    } else {
        $message  = "Failed to reset device. Please try again.";
        $msg_type = "error";
    }
}

// ── Fetch all devices ─────────────────────────────────────────────
$result = $conn->query("
    SELECT d.pyempcde, MAX(m.pyempnam) as pyempnam, MAX(d.pydevice) as pydevice
    FROM emdevice d
    LEFT JOIN pyempmas m ON d.pyempcde = m.pyempcde
    GROUP BY d.pyempcde
    ORDER BY d.pyempcde ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management — Union Bank</title>
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
        .section-title { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 12px; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .alert-success {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #2e7d32;
        }
        .alert-error {
            background: #fdecea;
            border: 1px solid #f5c6cb;
            color: #c0392b;
        }
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
        .device-text {
            font-size: 11px;
            color: #888;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .btn-reset {
            padding: 6px 14px;
            background: #e53935;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-reset:hover { background: #b71c1c; }
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
        <a href="admin_employees.php?token=<?= $t ?>">Employees</a>
        <a href="admin_employees_edit.php?token=<?= $t ?>">Edit Employees</a>
        <a href="admin_device.php?token=<?= $t ?>">Devices</a>
        <a href="admin_import.php?token=<?= $t ?>">Import</a>
        <a href="admin_login.php">Logout</a>
    </div>
</div>
<div class="container">

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="section-title">📱 Device Management</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Registered Device</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while ($row = $result->fetch_assoc()):
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['pyempcde']) ?></td>
                <td><?= htmlspecialchars($row['pyempnam'] ?? 'N/A') ?></td>
                <td>
                    <div class="device-text">
                        <?= htmlspecialchars($row['pydevice']) ?>
                    </div>
                </td>
                <td>
                    <form method="POST"
                        onsubmit="return confirm('Reset device for <?= htmlspecialchars($row['pyempnam'] ?? $row['pyempcde']) ?>? They will need to register again.')">
                        <input type="hidden" name="reset_emp"
                            value="<?= htmlspecialchars($row['pyempcde']) ?>">
                        <button type="submit" class="btn-reset">
                            🔄 Reset Device
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php if ($i === 1): ?>
            <tr>
                <td colspan="5"
                    style="text-align:center; color:#888; padding:24px;">
                    No registered devices found
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
