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

// ── Search query ──────────────────────────────────────────────────
$search    = trim($_GET['search'] ?? '');
$whereClause = '';
if ($search !== '') {
    $safesearch  = $conn->real_escape_string($search);
    $whereClause = "WHERE d.pyempcde LIKE '%$safesearch%' OR m.pyempnam LIKE '%$safesearch%'";
}

// ── Fetch devices ─────────────────────────────────────────────────
$result = $conn->query("
    SELECT d.pyempcde, MAX(m.pyempnam) as pyempnam, MAX(d.pydevice) as pydevice
    FROM emdevice d
    LEFT JOIN pyempmas m ON d.pyempcde = m.pyempcde
    $whereClause
    GROUP BY d.pyempcde
    ORDER BY d.pyempcde ASC
");

$total_devices = $conn->query("SELECT COUNT(DISTINCT pyempcde) as cnt FROM emdevice")->fetch_assoc()['cnt'];
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

        /* ── Navbar ── */
        .navbar {
            background: #644BA4;
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { font-size: 18px; }
        .nav-links { display: flex; gap: 8px; flex-wrap: wrap; }
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 13px;
            padding: 6px 12px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 6px;
            transition: background 0.2s;
        }
        .navbar a:hover, .navbar a.active { background: rgba(255,255,255,0.2); }

        .container { padding: 24px; max-width: 1100px; margin: auto; }

        /* ── Alert ── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
        .alert-error   { background: #fdecea; border: 1px solid #f5c6cb; color: #c0392b; }

        /* ── Toolbar: title + search ── */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title { font-size: 16px; font-weight: bold; color: #333; }
        .device-count {
            background: #644BA4;
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ── Search form ── */
        .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-wrap .search-icon {
            position: absolute;
            left: 11px;
            color: #aaa;
            font-size: 15px;
            pointer-events: none;
        }
        .search-input {
            padding: 9px 14px 9px 34px;
            border: 1.5px solid #d0c5f0;
            border-radius: 8px;
            font-size: 13px;
            color: #333;
            background: white;
            width: 260px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-input:focus {
            border-color: #644BA4;
            box-shadow: 0 0 0 3px rgba(100,75,164,0.12);
        }
        .btn-search {
            padding: 9px 18px;
            background: #644BA4;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-search:hover { background: #5D0476; }
        .btn-clear {
            padding: 9px 14px;
            background: white;
            color: #888;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-clear:hover { background: #f5f5f5; color: #555; }

        /* ── Search result note ── */
        .search-note {
            font-size: 13px;
            color: #644BA4;
            background: #f0ebff;
            border: 1px solid #d0c5f0;
            border-radius: 8px;
            padding: 9px 14px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Table ── */
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

        .device-text {
            font-size: 11px;
            color: #888;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .emp-id   { font-weight: 600; color: #644BA4; }
        .emp-name { font-weight: 500; }

        /* ── Highlight search match ── */
        mark {
            background: #ede7ff;
            color: #4a148c;
            border-radius: 3px;
            padding: 0 2px;
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
            transition: background 0.2s;
        }
        .btn-reset:hover { background: #b71c1c; }

        .empty-state {
            text-align: center;
            color: #888;
            padding: 40px 24px;
        }
        .empty-state .empty-icon { font-size: 36px; margin-bottom: 10px; }
        .empty-state p { font-size: 14px; }
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
        <a href="admin_device.php?token=<?= $t ?>" class="active">Devices</a>
        <a href="admin_import.php?token=<?= $t ?>">Import</a>
        <a href="admin_login.php">Logout</a>
    </div>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <?= $msg_type === 'success' ? '✅' : '❌' ?>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ── Toolbar ── -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="section-title">📱 Device Management</div>
            <span class="device-count"><?= $total_devices ?> Registered</span>
        </div>

        <form method="GET" action="admin_device.php" class="search-form">
            <input type="hidden" name="token" value="<?= $t ?>">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search by ID or name…"
                    value="<?= htmlspecialchars($search) ?>"
                    autofocus
                >
            </div>
            <button type="submit" class="btn-search">Search</button>
            <?php if ($search !== ''): ?>
                <a href="admin_device.php?token=<?= $t ?>" class="btn-clear">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Search result note ── -->
    <?php if ($search !== ''): ?>
        <?php $found = $result->num_rows; ?>
        <div class="search-note">
            🔎 Showing <strong><?= $found ?></strong> result<?= $found !== 1 ? 's' : '' ?> for
            "<strong><?= htmlspecialchars($search) ?></strong>"
        </div>
    <?php endif; ?>

    <!-- ── Table ── -->
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

        // Helper to highlight search term
        function highlight($text, $search) {
            if ($search === '') return htmlspecialchars($text);
            $escaped = htmlspecialchars($text);
            $pattern = '/' . preg_quote(htmlspecialchars($search), '/') . '/i';
            return preg_replace($pattern, '<mark>$0</mark>', $escaped);
        }

        while ($row = $result->fetch_assoc()):
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td class="emp-id"><?= highlight($row['pyempcde'], $search) ?></td>
                <td class="emp-name"><?= highlight($row['pyempnam'] ?? 'N/A', $search) ?></td>
                <td>
                    <div class="device-text" title="<?= htmlspecialchars($row['pydevice']) ?>">
                        <?= htmlspecialchars($row['pydevice']) ?>
                    </div>
                </td>
                <td>
                    <form method="POST" action="admin_device.php?token=<?= $t ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                          onsubmit="return confirm('Reset device for <?= htmlspecialchars(addslashes($row['pyempnam'] ?? $row['pyempcde'])) ?>?\nThey will need to register again.')">
                        <input type="hidden" name="reset_emp" value="<?= htmlspecialchars($row['pyempcde']) ?>">
                        <button type="submit" class="btn-reset">🔄 Reset Device</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>

        <?php if ($i === 1): ?>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <div class="empty-icon"><?= $search ? '🔍' : '📱' ?></div>
                        <p><?= $search ? "No results found for \"" . htmlspecialchars($search) . "\"" : "No registered devices found" ?></p>
                    </div>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>

<script>
    // Allow pressing Enter in search to submit
    document.querySelector('.search-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') this.closest('form').submit();
    });
</script>

</body>
</html>
