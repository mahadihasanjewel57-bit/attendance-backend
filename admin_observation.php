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
$msg_type= '';

// ── Add to observation ────────────────────────────────────────────
if (isset($_GET['add'])) {
    $add_id = trim($_GET['add']);
    $stmt   = $conn->prepare("INSERT IGNORE INTO obs_employees (pyempcde) VALUES (?)");
    $stmt->bind_param("s", $add_id);
    $stmt->execute();
    $message  = "Employee $add_id added to observation list.";
    $msg_type = "success";
}

// ── Remove from observation ───────────────────────────────────────
if (isset($_GET['remove'])) {
    $rem_id = trim($_GET['remove']);
    $stmt   = $conn->prepare("DELETE FROM obs_employees WHERE pyempcde = ?");
    $stmt->bind_param("s", $rem_id);
    $stmt->execute();
    $message  = "Employee $rem_id removed from observation list.";
    $msg_type = "success";
}

// ── Date range: 1st of month to today ────────────────────────────
$today      = date("Y-m-d");
$month_start= date("Y-m-01");

$dates   = [];
$current = strtotime($month_start);
$end     = strtotime($today);
while ($current <= $end) {
    $dates[] = date("Y-m-d", $current);
    $current = strtotime("+1 day", $current);
}

// ── Fetch observation employees ───────────────────────────────────
$obs_result = $conn->query("
    SELECT o.pyempcde, m.pyempnam, m.pyempost, m.designation
    FROM obs_employees o
    LEFT JOIN pyempmas m ON o.pyempcde = m.pyempcde
    ORDER BY m.pyempost ASC, m.pyempnam ASC
");
$obs_employees = [];
while ($row = $obs_result->fetch_assoc()) {
    $obs_employees[] = $row;
}

// ── Fetch attendance for observation employees ────────────────────
$obs_ids = array_column($obs_employees, 'pyempcde');
$att_map = [];

if (count($obs_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($obs_ids), '?'));
    $att_sql = "
        SELECT EMPLCODE,
            DATE(LOGDTIME) as att_date,
            MIN(LOGDTIME) as check_in,
            MAX(LOGDTIME) as check_out,
            COUNT(*) as punches
        FROM pyacslog
        WHERE DATE(LOGDTIME) BETWEEN ? AND ?
        AND EMPLCODE IN ($placeholders)
        GROUP BY EMPLCODE, DATE(LOGDTIME)
    ";

    $att_stmt   = $conn->prepare($att_sql);
    $att_types  = "ss" . str_repeat("s", count($obs_ids));
    $att_params = array_merge([$month_start, $today], $obs_ids);
    $att_stmt->bind_param($att_types, ...$att_params);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();

    while ($row = $att_result->fetch_assoc()) {
        $att_map[$row['EMPLCODE']][$row['att_date']] = $row;
    }
}

// ── Search for adding employees ───────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$search_result = [];
if ($search !== '') {
    $like  = "%$search%";
    $stmt  = $conn->prepare("
        SELECT pyempcde, pyempnam, pyempost
        FROM pyempmas
        WHERE pyempcde LIKE ? OR pyempnam LIKE ?
        LIMIT 20
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $search_result[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observation — Union Bank</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f3f0fa; font-size: 13px; }
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
            font-size: 12px;
            margin-left: 6px;
            padding: 5px 10px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 6px;
        }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { padding: 20px; max-width: 100%; overflow-x: auto; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
        .alert-error   { background: #fdecea; border: 1px solid #f5c6cb; color: #c0392b; }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #333;
            margin-bottom: 12px;
            margin-top: 20px;
        }
        .search-card {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-card input {
            flex: 1;
            padding: 9px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            min-width: 250px;
        }
        .search-card input:focus { border-color: #644BA4; }
        .btn {
            padding: 8px 16px;
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

        /* Search results */
        .search-results {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .search-results table {
            width: 100%;
            border-collapse: collapse;
        }
        .search-results th {
            background: #644BA4;
            color: white;
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
        }
        .search-results td {
            padding: 9px 14px;
            font-size: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .search-results tr:last-child td { border-bottom: none; }
        .search-results tr:hover td { background: #faf8ff; }

        /* Observation table */
        .scroll-wrap { overflow-x: auto; margin-bottom: 24px; }
        .obs-table {
            border-collapse: collapse;
            white-space: nowrap;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            min-width: 100%;
        }
        .obs-table th {
            background: #5D0476;
            color: white;
            padding: 9px 10px;
            text-align: center;
            font-size: 11px;
            border: 1px solid #7c5cbf;
        }
        .obs-table th.emp-col { text-align: left; min-width: 150px; }
        .obs-table th:first-child,
        .obs-table td:first-child { width: 30px; min-width: 30px; padding: 7px 6px; }
        .obs-table td {
            padding: 7px 8px;
            border: 1px solid #f0f0f0;
            text-align: center;
            font-size: 11px;
        }
        .obs-table td.emp-col { text-align: left; }
        .obs-table tr:hover td { background: #faf8ff; }

        /* Status cells */
        .status-P {
            background: #1565c0;
            color: white;
            font-weight: bold;
            border-radius: 4px;
            cursor: default;
        }
        .status-L {
            background: #c8e6c9;
            color: #2e7d32;
            font-weight: bold;
            border-radius: 4px;
            cursor: default;
        }
        .status-A {
            background: #ffcdd2;
            color: #c0392b;
            border-radius: 4px;
        }
        .status-W {
            background: #f5f5f5;
            color: #aaa;
        }
        .status-today {
            border: 2px solid #e65100 !important;
        }

        /* Tooltip */
        [data-tip] { position: relative; }
        [data-tip]:hover::after {
            content: attr(data-tip);
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 999;
            pointer-events: none;
        }
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
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- ── Add Employee Search ──────────────────────────────── -->
    <div class="section-title">➕ Add Employee to Observation</div>
    <form method="GET">
        <input type="hidden" name="token" value="<?= $t ?>">
        <div class="search-card">
            <input type="text" name="search"
                placeholder="Search by Employee ID or Name..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-purple">🔍 Search</button>
        </div>
    </form>

    <?php if (count($search_result) > 0): ?>
    <div class="search-results">
        <table>
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Posting</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($search_result as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['pyempcde']) ?></td>
                    <td><?= htmlspecialchars($row['pyempnam']) ?></td>
                    <td><?= htmlspecialchars($row['pyempost'] ?? '') ?></td>
                    <td>
                        <?php $in_obs = in_array($row['pyempcde'], $obs_ids); ?>
                        <?php if ($in_obs): ?>
                            <span style="color:#888; font-size:12px">Already in list</span>
                        <?php else: ?>
                            <a href="admin_observation.php?token=<?= $t ?>&add=<?= urlencode($row['pyempcde']) ?>"
                               class="btn btn-green">➕ Add</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ── Observation Table ────────────────────────────────── -->
    <div class="section-title">
        👁️ Employees Under Observation —
        <?= date("M Y") ?>
        (<?= date("d M", strtotime($month_start)) ?> – <?= date("d M", strtotime($today)) ?>)
    </div>

    <?php if (count($obs_employees) === 0): ?>
        <div style="text-align:center; padding:40px; color:#888; background:white; border-radius:12px;">
            No employees in observation list. Search and add employees above.
        </div>
    <?php else: ?>
    <div class="scroll-wrap">
        <table class="obs-table">
            <thead>
                <tr>
                    <th class="emp-col">#</th>
                    <th class="emp-col">ID</th>
                    <th class="emp-col">Name</th>
                    <th class="emp-col">Posting</th>
                    <?php foreach ($dates as $d): ?>
                        <th class="<?= $d === $today ? 'status-today' : '' ?>">
                            <?= date("d", strtotime($d)) ?><br>
                            <?= date("D", strtotime($d)) ?>
                        </th>
                    <?php endforeach; ?>
                    <th>P</th>
                    <th>L</th>
                    <th>A</th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $i = 1;
            foreach ($obs_employees as $emp):
                $emp_id  = $emp['pyempcde'];
                $p_count = 0;
                $l_count = 0;
                $a_count = 0;
            ?>
                <tr>
                    <td class="emp-col"><?= $i++ ?></td>
                    <td class="emp-col"><?= htmlspecialchars($emp_id) ?></td>
                    <td class="emp-col"><?= htmlspecialchars($emp['pyempnam'] ?? 'N/A') ?></td>
                    <td class="emp-col"><?= htmlspecialchars($emp['pyempost'] ?? '') ?></td>

                    <?php foreach ($dates as $d):
                        $dow = date("N", strtotime($d));
                        $rec = $att_map[$emp_id][$d] ?? null;
                        $is_today = ($d === $today);

                        if ($dow == 5 || $dow == 6):
                            echo "<td class='status-W " . ($is_today ? 'status-today' : '') . "'>-</td>";
                        elseif ($rec):
                            $ci_time  = date("h:i A", strtotime($rec['check_in']));
                            $co_time  = $rec['punches'] > 1
                                        ? date("h:i A", strtotime($rec['check_out']))
                                        : '--';
                            $ci_hour  = (int)date("H", strtotime($rec['check_in']));
                            $ci_min   = (int)date("i", strtotime($rec['check_in']));
                            $is_late  = ($ci_hour > 10) || ($ci_hour == 10 && $ci_min > 0);
                            $tip      = "In: $ci_time | Out: $co_time";

                            if ($is_late) {
                                $l_count++;
                                echo "<td class='status-L " . ($is_today ? 'status-today' : '') . "' data-tip='$tip'>L</td>";
                            } else {
                                $p_count++;
                                echo "<td class='status-P " . ($is_today ? 'status-today' : '') . "' data-tip='$tip'>P</td>";
                            }
                        else:
                            $a_count++;
                            echo "<td class='status-A " . ($is_today ? 'status-today' : '') . "'>A</td>";
                        endif;
                    endforeach; ?>

                    <td style="font-weight:bold; color:#1565c0"><?= $p_count ?></td>
                    <td style="font-weight:bold; color:#2e7d32"><?= $l_count ?></td>
                    <td style="font-weight:bold; color:#c0392b"><?= $a_count ?></td>
                    <td>
                        <a href="admin_observation.php?token=<?= $t ?>&remove=<?= urlencode($emp_id) ?>"
                           class="btn btn-red"
                           onclick="return confirm('Remove <?= htmlspecialchars($emp['pyempnam'] ?? $emp_id) ?> from observation?')">
                           ✖
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Legend ──────────────────────────────────────────── -->
    <div style="margin-top:12px; display:flex; gap:16px; flex-wrap:wrap;">
        <span style="background:#1565c0; color:white; padding:4px 12px; border-radius:6px; font-size:12px; font-weight:bold;">P — Present (before 10:00 AM)</span>
        <span style="background:#c8e6c9; color:#2e7d32; padding:4px 12px; border-radius:6px; font-size:12px; font-weight:bold;">L — Late (after 10:00 AM)</span>
        <span style="background:#ffcdd2; color:#c0392b; padding:4px 12px; border-radius:6px; font-size:12px;">A — Absent</span>
        <span style="background:#f5f5f5; color:#aaa; padding:4px 12px; border-radius:6px; font-size:12px;">- — Weekend</span>
        <span style="border: 2px solid #e65100; padding:4px 12px; border-radius:6px; font-size:12px;">Today</span>
    </div>

    <?php endif; ?>

</div>
</body>
</html>
