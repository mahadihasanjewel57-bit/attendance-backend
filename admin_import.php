<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";

$t        = AUTH_TOKEN;
$message  = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file  = $_FILES['excel_file'];
    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $import = $_POST['import_type'] ?? 'employees';

    if ($ext !== 'csv') {
        $message  = "Only CSV files supported.";
        $msg_type = "error";
    } elseif ($file['error'] !== 0) {
        $message  = "File upload error code: " . $file['error'];
        $msg_type = "error";
    } else {
        $handle  = fopen($file['tmp_name'], 'r');
        $header  = fgetcsv($handle);
        $success = 0;
        $failed  = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0])) continue;

            if ($import === 'employees') {
                $emp_id      = trim($row[0] ?? '');
                $emp_name    = trim($row[1] ?? '');
                $emp_post    = trim($row[2] ?? '');
                $designation = trim($row[3] ?? '');

                if ($emp_id === '' || $emp_name === '') {
                    $failed++;
                    continue;
                }

                $stmt = $conn->prepare("
                    INSERT INTO pyempmas (pyempcde, pyempnam, pyempost, designation)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        pyempnam    = VALUES(pyempnam),
                        pyempost    = VALUES(pyempost),
                        designation = VALUES(designation)
                ");
                $stmt->bind_param("ssss", $emp_id, $emp_name, $emp_post, $designation);
                $stmt->execute() ? $success++ : $failed++;

            } else {
                $emp_id    = trim($row[0] ?? '');
                $latitude  = trim($row[1] ?? '');
                $longitude = trim($row[2] ?? '');

                if ($emp_id === '' || $latitude === '' || $longitude === '') {
                    $failed++;
                    continue;
                }

                $stmt = $conn->prepare("
                    INSERT INTO pyemploc (pyempcde, latitude, longitude)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        latitude  = VALUES(latitude),
                        longitude = VALUES(longitude)
                ");
                $stmt->bind_param("sss", $emp_id, $latitude, $longitude);
                $stmt->execute() ? $success++ : $failed++;
            }
        }

        fclose($handle);
        $message  = "Import complete — Success: $success, Failed: $failed";
        $msg_type = $failed > 0 ? "error" : "success";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data — Union Bank</title>
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
        .container { padding: 24px; max-width: 800px; margin: auto; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
        .alert-error   { background: #fdecea; border: 1px solid #f5c6cb; color: #c0392b; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .card h3 { color: #644BA4; margin-bottom: 16px; font-size: 15px; }
        label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 6px;
            margin-top: 14px;
        }
        select, input[type=file] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            background: white;
        }
        .btn {
            margin-top: 20px;
            padding: 11px 24px;
            background: #644BA4;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn:hover { background: #5D0476; }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 14px;
            margin-top: 16px;
            font-size: 12px;
            color: #1565c0;
            line-height: 1.8;
        }
        .info-box strong { display: block; margin-bottom: 6px; font-size: 13px; }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🏦 Union Bank — Admin Panel</h1>
    <div class="nav-links">
        <a href="admin_dashboard.php?token=<?= $t ?>">Dashboard</a>
        <a href="admin_attendance.php?token=<?= $t ?>">Attendance</a>
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

    <div class="card">
        <h3>📤 Import Data from CSV</h3>
        <form method="POST" enctype="multipart/form-data"
            action="admin_import.php?token=<?= $t ?>">
            <label>Import Type</label>
            <select name="import_type">
                <option value="employees">Employees (pyempmas)</option>
                <option value="locations">GPS Locations (pyemploc)</option>
            </select>
            <label>Select CSV File</label>
            <input type="file" name="excel_file" accept=".csv" required>
            <button type="submit" class="btn">📤 Upload & Import</button>
        </form>
        <div class="info-box">
            <strong>📋 CSV Format Guide:</strong>
            <strong>For Employees:</strong>
            Columns: <code>pyempcde, pyempnam, pyempost, designation</code><br>
            Example: <code>0204201700923, Mr. Mahadi Hasan, Dhaka Branch, Officer</code><br><br>
            <strong>For GPS Locations:</strong>
            Columns: <code>pyempcde, latitude, longitude</code><br>
            Example: <code>0204201700923, 23.7830818, 90.4170002</code><br><br>
            <strong>Notes:</strong><br>
            • First row = header (will be skipped)<br>
            • Save Excel as CSV (Comma delimited)<br>
            • Existing records will be updated
        </div>
    </div>
</div>
</body>
</html>
