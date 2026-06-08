<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$t       = AUTH_TOKEN;
$message = '';
$msg_type = '';

// ── DELETE employee ───────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = trim($_GET['delete']);
    $conn->prepare("DELETE FROM pyempmas WHERE pyempcde = ?")->bind_param("s", $del_id) && true;
    $stmt = $conn->prepare("DELETE FROM pyempmas WHERE pyempcde = ?");
    $stmt->bind_param("s", $del_id);
    $stmt->execute();

    $stmt2 = $conn->prepare("DELETE FROM pyemploc WHERE pyempcde = ?");
    $stmt2->bind_param("s", $del_id);
    $stmt2->execute();

    $stmt3 = $conn->prepare("DELETE FROM emdevice WHERE pyempcde = ?");
    $stmt3->bind_param("s", $del_id);
    $stmt3->execute();

    $message  = "Employee $del_id deleted successfully.";
    $msg_type = "success";
}

// ── UPDATE employee ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $emp_id      = trim($_POST['pyempcde']);
    $emp_name    = trim($_POST['pyempnam']);
    $emp_post    = trim($_POST['pyempost']);
    $designation = trim($_POST['designation']);
    $latitude    = trim($_POST['latitude']);
    $longitude   = trim($_POST['longitude']);

    // Update pyempmas
    $stmt = $conn->prepare("UPDATE pyempmas SET pyempnam=?, pyempost=?, designation=? WHERE pyempcde=?");
    $stmt->bind_param("ssss", $emp_name, $emp_post, $designation, $emp_id);
    $stmt->execute();

    // Update or insert pyemploc
    $chk = $conn->prepare("SELECT pyempcde FROM pyemploc WHERE pyempcde=?");
    $chk->bind_param("s", $emp_id);
    $chk->execute();
    $chkRes = $chk->get_result();

    if ($chkRes->num_rows > 0) {
        $stmt2 = $conn->prepare("UPDATE pyemploc SET latitude=?, longitude=? WHERE pyempcde=?");
        $stmt2->bind_param("sss", $latitude, $longitude, $emp_id);
        $stmt2->execute();
    } else {
        $stmt2 = $conn->prepare("INSERT INTO pyemploc (pyempcde, latitude, longitude) VALUES (?,?,?)");
        $stmt2->bind_param("sss", $emp_id, $latitude, $longitude);
        $stmt2->execute();
    }

    $message  = "Employee $emp_id updated successfully.";
    $msg_type = "success";
}

// ── SEARCH ────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$where  = '';
$params = [];
$types  = '';

if ($search !== '') {
    $where    = "WHERE m.pyempcde LIKE ? OR m.pyempnam LIKE ?";
    $like     = "%$search%";
    $params   = [$like, $like];
    $types    = "ss";
}

$sql = "
    SELECT m.pyempcde, m.pyempnam, m.pyempost, m.designation,
           l.latitude, l.longitude
    FROM pyempmas m
    LEFT JOIN pyemploc l ON m.pyempcde = l.pyempcde
    $where
    ORDER BY m.pyempcde ASC
    LIMIT 100
";

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// ── FETCH single for edit ─────────────────────────────────────────
$edit_row = null;
if (isset($_GET['edit'])) {
    $edit_id = trim($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT m.pyempcde, m.pyempnam, m.pyempost, m.designation,
               l.latitude, l.longitude
        FROM pyempmas m
        LEFT JOIN pyemploc l ON m.pyempcde = l.pyempcde
        WHERE m.pyempcde = ?
    ");
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $edit_row = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employees — Union Bank</title>
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
        .container { padding: 24px; max-width: 1200px; margin: auto; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
        .alert-error   { background: #fdecea; border: 1px solid #f5c6cb; color: #c0392b; }
        .section-title { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 12px; }
        .search-bar {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .search-bar input {
            flex: 1;
            padding: 9px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        .search-bar input:focus { border-color: #644BA4; }
        .btn {
            padding: 9px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .btn-purple { background: #644BA4; color: white; }
        .btn-purple:hover { background: #5D0476; }
        .btn-green  { background: #2e7d32; color: white; }
        .btn-green:hover  { background: #1b5e20; }
        .btn-red    { background: #e53935; color: white; }
        .btn-red:hover    { background: #b71c1c; }
        .btn-blue   { background: #1565c0; color: white; }
        .btn-blue:hover   { background: #0d47a1; }
        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-collapse: collapse;
            overflow: hidden;
            margin-bottom: 24px;
        }
        th { background: #644BA4; color: white; padding: 11px 14px; text-align: left; font-size: 12px; }
        td { padding: 10px 14px; font-size: 12px; border-bottom: 1px solid #f0f0f0; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #faf8ff; }
        .edit-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .edit-card h3 { color: #644BA4; margin-bottom: 16px; font-size: 15px; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            color: #888;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }
        .form-group input:focus { border-color: #644BA4; }
        .form-group input[readonly] { background: #f5f5f5; color: #999; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🏦 Union Bank — Admin Panel</h1>
    <div class="nav-links">
        <a href="admin_dashboard.php?token=<?= $t ?>">Dashboard</a>
        <a href="admin_attendance.php?token=<?= $t ?>">Attendance</a>
             <a href="admin_observation.php?token=<?= $t ?>">Observation</a>
          <a href="admin_monthly.php?token=<?= $t ?>">Monthly</a>
        <a href="admin_employees_edit.php?token=<?= $t ?>">Edit Employees</a>
        <a href="admin_device.php?token=<?= $t ?>">Devices</a>
        <a href="admin_import.php?token=<?= $t ?>">Import</a>
        <a href="admin_login.php">Logout</a>
    </div>
</div>
   
<div class="container">

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- ── Edit Form ─────────────────────────────────────────── -->
    <?php if ($edit_row): ?>
    <div class="edit-card">
        <h3>✏️ Edit Employee — <?= htmlspecialchars($edit_row['pyempcde']) ?></h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="pyempcde" value="<?= htmlspecialchars($edit_row['pyempcde']) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" value="<?= htmlspecialchars($edit_row['pyempcde']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Employee Name</label>
                    <input type="text" name="pyempnam"
                        value="<?= htmlspecialchars($edit_row['pyempnam']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Place of Posting</label>
                    <input type="text" name="pyempost"
                        value="<?= htmlspecialchars($edit_row['pyempost'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="designation"
                        value="<?= htmlspecialchars($edit_row['designation'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Latitude</label>
                    <input type="text" name="latitude"
                        value="<?= htmlspecialchars($edit_row['latitude'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="text" name="longitude"
                        value="<?= htmlspecialchars($edit_row['longitude'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-green">💾 Save Changes</button>
            <a href="admin_employees_edit.php?token=<?= $t ?>" class="btn btn-purple">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Search ────────────────────────────────────────────── -->
    <form method="GET">
        <input type="hidden" name="token" value="<?= $t ?>">
        <div class="search-bar">
            <input type="text" name="search"
                placeholder="Search by Employee ID or Name..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-purple">🔍 Search</button>
            <a href="admin_import.php?token=<?= $t ?>" class="btn btn-blue">📤 Import Excel</a>
        </div>
    </form>

    <div class="section-title">👥 Employee List (showing max 100)</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Posting</th>
                <th>Designation</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Actions</th>
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
                <td><?= htmlspecialchars($row['pyempnam']) ?></td>
                <td><?= htmlspecialchars($row['pyempost'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['designation'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['latitude'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['longitude'] ?? '') ?></td>
                <td> <a href="admin_employees_edit.php?token=<?= $t ?>&edit=<?= urlencode($row['pyempcde']) ?>"
                       class="btn btn-blue">✏️ Edit </a> </td>
                <td> <a href="admin_employees_edit.php?token=<?= $t ?>&delete=<?= urlencode($row['pyempcde']) ?>"
                       class="btn btn-red"
                       onclick="return confirm('Delete <?= htmlspecialchars($row['pyempnam']) ?>? This will also remove their device and location.')">
                       🗑️ Delete </a></td>      
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
