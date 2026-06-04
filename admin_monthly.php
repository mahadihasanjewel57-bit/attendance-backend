<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$t          = AUTH_TOKEN;
$date_from  = $_GET['date_from'] ?? date("Y-m-01");
$date_to    = $_GET['date_to']   ?? date("Y-m-d");
$filter_emp = trim($_GET['emp']  ?? '');
$filter_post= trim($_GET['post'] ?? '');

// ── Fetch all posting places for autocomplete ─────────────────────
$post_list = [];
$post_res  = $conn->query("
    SELECT DISTINCT pyempost FROM pyempmas
    WHERE pyempost IS NOT NULL AND pyempost != ''
    ORDER BY pyempost ASC
");
while ($pr = $post_res->fetch_assoc()) {
    $post_list[] = $pr['pyempost'];
}

// ── Generate date range array ─────────────────────────────────────
$dates   = [];
$current = strtotime($date_from);
$end     = strtotime($date_to);
while ($current <= $end) {
    $dates[] = date("Y-m-d", $current);
    $current = strtotime("+1 day", $current);
}

// ── Fetch employees ───────────────────────────────────────────────
$emp_where  = "WHERE 1=1";
$emp_params = [];
$emp_types  = "";

if ($filter_emp !== '') {
    $emp_where   .= " AND pyempcde = ?";
    $emp_params[] = $filter_emp;
    $emp_types   .= "s";
}
if ($filter_post !== '') {
    $emp_where   .= " AND pyempost = ?";
    $emp_params[] = $filter_post;
    $emp_types   .= "s";
}

$emp_sql = "SELECT pyempcde, pyempnam, pyempost, designation
            FROM pyempmas $emp_where
            ORDER BY pyempost ASC, pyempnam ASC";

if ($emp_types !== '') {
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->bind_param($emp_types, ...$emp_params);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
} else {
    $emp_result = $conn->query($emp_sql);
}

$employees = [];
while ($row = $emp_result->fetch_assoc()) {
    $employees[] = $row;
}

// ── Fetch all attendance in date range ────────────────────────────
$att_where  = "WHERE DATE(LOGDTIME) BETWEEN ? AND ?";
$att_params = [$date_from, $date_to];
$att_types  = "ss";

if ($filter_emp !== '') {
    $att_where   .= " AND EMPLCODE = ?";
    $att_params[] = $filter_emp;
    $att_types   .= "s";
}

$att_sql = "
    SELECT EMPLCODE,
        DATE(LOGDTIME) as att_date,
        MIN(LOGDTIME) as check_in,
        MAX(LOGDTIME) as check_out,
        COUNT(*) as punches
    FROM pyacslog
    $att_where
    GROUP BY EMPLCODE, DATE(LOGDTIME)
    ORDER BY EMPLCODE, att_date
";

$att_stmt = $conn->prepare($att_sql);
$att_stmt->bind_param($att_types, ...$att_params);
$att_stmt->execute();
$att_result = $att_stmt->get_result();

// ── Build attendance map [emplcode][date] ─────────────────────────
$att_map = [];
while ($row = $att_result->fetch_assoc()) {
    $att_map[$row['EMPLCODE']][$row['att_date']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report — Union Bank</title>
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
        .container { padding: 20px; max-width: 100%; margin: auto; overflow-x: auto; }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .filter-row label { display: block; font-size: 12px; color: #888; margin-bottom: 5px; }
        .filter-row input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }
        .filter-row input:focus { border-color: #644BA4; }
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
        .btn-search { background: #644BA4; color: white; }
        .btn-search:hover { background: #5D0476; }
        .btn-export { background: #2e7d32; color: white; }
        .btn-export:hover { background: #1b5e20; }
        .btn-clear  { background: #888; color: white; }
        .btn-clear:hover  { background: #555; }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            margin-top: 20px;
        }

        /* Day-by-day table */
        /* Day-by-day table */
        .scroll-wrap { overflow-x: auto; margin-bottom: 30px; }
        .detail-table {
            border-collapse: collapse;
            white-space: nowrap;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            width: auto;
            table-layout: auto;
        }
        .detail-table th {
            background: #644BA4;
            color: white;
            padding: 8px 6px;
            text-align: center;
            font-size: 11px;
            border: 1px solid #7c5cbf;
            white-space: nowrap;
        }
        .detail-table th.emp-col {
            text-align: left;
            min-width: 40px;
            max-width: 160px;
            padding: 8px 10px;
        }
        .detail-table td {
            padding: 6px 6px;
            border: 1px solid #f0f0f0;
            text-align: center;
            font-size: 11px;
            color: #333;
            white-space: nowrap;
        }
        .detail-table td.emp-col {
            text-align: left;
            font-weight: 500;
            padding: 6px 10px;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .detail-table td:first-child,
        .detail-table th:first-child {
            width: 30px;
            min-width: 30px;
            max-width: 30px;
            padding: 6px 4px;
            text-align: center;
        }
        .detail-table tr:hover td { background: #faf8ff; }
        .detail-table td.emp-col { text-align: left; font-weight: 500; }
        .detail-table tr:hover td { background: #faf8ff; }
      .present    { background: #1565c0; color: white; font-weight: bold; }
        .half       { background: #c8e6c9; color: #2e7d32; font-weight: bold; }
        .absent     { background: #fdecea; color: #c0392b; }
        .weekend    { background: #f5f5f5; color: #aaa; }

        /* Summary table */
        .summary-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-collapse: collapse;
            overflow: hidden;
        }
        .summary-table th {
            background: #5D0476;
            color: white;
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
        }
        .summary-table td {
            padding: 10px 14px;
            font-size: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }
        .summary-table tr:last-child td { border-bottom: none; }
        .summary-table tr:hover td { background: #faf8ff; }
        .badge { padding: 3px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-green  { background: #e8f5e9; color: #2e7d32; }
        .badge-orange { background: #fff3e0; color: #e65100; }
        .badge-red    { background: #fdecea; color: #c0392b; }

        /* Autocomplete */
        .autocomplete-wrap { position: relative; }
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 180px;
            overflow-y: auto;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .autocomplete-list div {
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            color: #333;
        }
        .autocomplete-list div:hover { background: #f3f0fa; color: #644BA4; }
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
        <a href="admin_login.php">Logout</a>
    </div>
</div>
<div class="container">

    <!-- ── Filter ─────────────────────────────────────────────── -->
    <form method="GET" id="filterForm">
        <input type="hidden" name="token" value="<?= $t ?>">
        <div class="filter-card">
            <div class="filter-row">
                <div>
                    <label>Date From</label>
                    <input type="date" name="date_from"
                        value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div>
                    <label>Date To</label>
                    <input type="date" name="date_to"
                        value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div>
                    <label>Employee ID (optional)</label>
                    <input type="text" name="emp"
                        placeholder="e.g. 0204201700923"
                        value="<?= htmlspecialchars($filter_emp) ?>">
                </div>
                <div class="autocomplete-wrap">
                    <label>Posting Place (optional)</label>
                    <input type="text" name="post" id="postInput"
                        placeholder="Type posting place..."
                        value="<?= htmlspecialchars($filter_post) ?>"
                        autocomplete="off">
                    <div class="autocomplete-list" id="autoList"></div>
                </div>
                <button type="submit" class="btn btn-search">🔍 Generate Report</button>
                <a href="admin_monthly.php?token=<?= $t ?>"
                   class="btn btn-clear">✖ Clear</a>
                <a href="admin_monthly_export.php?token=<?= $t ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&emp=<?= urlencode($filter_emp) ?>&post=<?= urlencode($filter_post) ?>"
                   class="btn btn-export">📥 Export Excel</a>
            </div>
        </div>
    </form>

    <?php if (count($employees) > 0): ?>

    <!-- ── Day-by-day detail ───────────────────────────────────── -->
    <div class="section-title">
        📅 Day-by-Day Attendance
        (<?= date("d M Y", strtotime($date_from)) ?> — <?= date("d M Y", strtotime($date_to)) ?>)
        <?php if ($filter_post): ?>
            — <span style="color:#644BA4"><?= htmlspecialchars($filter_post) ?></span>
        <?php endif; ?>
    </div>

    <div class="scroll-wrap">
        <table class="detail-table">
            <thead>
                <tr>
                    <th class="emp-col">#</th>
                    <th class="emp-col">Employee ID</th>
                    <th class="emp-col">Name</th>
                    <th class="emp-col">Posting</th>
                    <?php foreach ($dates as $d): ?>
                        <th><?= date("d", strtotime($d)) ?><br><?= date("D", strtotime($d)) ?></th>
                    <?php endforeach; ?>
                    <th>Present</th>
                    <th>Absent</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $i = 1;
            foreach ($employees as $emp):
                $emp_id      = $emp['pyempcde'];
                $total_days  = count($dates);
                $present     = 0;
                $half        = 0;
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($emp_id) ?></td>
                    <td><?= htmlspecialchars($emp['pyempnam']) ?></td>
                    <td><?= htmlspecialchars($emp['pyempost'] ?? '') ?></td>
                    <?php foreach ($dates as $d):
                        $dow = date("N", strtotime($d)); // 5=Fri, 6=Sat
                        $rec = $att_map[$emp_id][$d] ?? null;

                        if ($dow == 5 || $dow == 6):
                            echo "<td class='weekend'>-</td>";
                        elseif ($rec):
                            $punches = $rec['punches'];
                            if ($punches >= 2) {
                                $present++;
                                $ci = date("h:i", strtotime($rec['check_in']));
                                $co = date("h:i", strtotime($rec['check_out']));
                                echo "<td class='present' title='In: $ci Out: $co'>✓</td>";
                            } else {
                                $half++;
                                $ci = date("h:i", strtotime($rec['check_in']));
                                echo "<td class='half' title='In: $ci'>½</td>";
                            }
                        else:
                            echo "<td class='absent'>✗</td>";
                        endif;
                    endforeach; ?>
                    <td><strong style="color:#2e7d32"><?= $present ?></strong></td>
                    <td><strong style="color:#c0392b"><?= ($total_days - $present - $half - substr_count(implode('', array_map(function($d){ return (date("N",strtotime($d))==5||date("N",strtotime($d))==6)?'W':''; }, $dates)), 'W')) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Summary ────────────────────────────────────────────── -->
    <div class="section-title">📊 Summary</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Posting</th>
                <th>Designation</th>
                <th>Working Days</th>
                <th>Present</th>
                <th>Half Day</th>
                <th>Absent</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        // Count working days (exclude Fri & Sat)
        $working_days = 0;
        foreach ($dates as $d) {
            $dow = date("N", strtotime($d));
            if ($dow != 5 && $dow != 6) $working_days++;
        }

        foreach ($employees as $emp):
            $emp_id   = $emp['pyempcde'];
            $present  = 0;
            $half_day = 0;

            foreach ($dates as $d) {
                $dow = date("N", strtotime($d));
                if ($dow == 5 || $dow == 6) continue;
                $rec = $att_map[$emp_id][$d] ?? null;
                if ($rec) {
                    if ($rec['punches'] >= 2) $present++;
                    else $half_day++;
                }
            }

            $absent = $working_days - $present - $half_day;
            $pct    = $working_days > 0 ? round(($present / $working_days) * 100) : 0;

            if ($pct >= 90) {
                $status = "<span class='badge badge-green'>Excellent</span>";
            } elseif ($pct >= 70) {
                $status = "<span class='badge badge-orange'>Good</span>";
            } else {
                $status = "<span class='badge badge-red'>Poor</span>";
            }
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($emp_id) ?></td>
                <td><?= htmlspecialchars($emp['pyempnam']) ?></td>
                <td><?= htmlspecialchars($emp['pyempost'] ?? '') ?></td>
                <td><?= htmlspecialchars($emp['designation'] ?? '') ?></td>
                <td><?= $working_days ?></td>
                <td><strong style="color:#2e7d32"><?= $present ?></strong></td>
                <td><strong style="color:#e65100"><?= $half_day ?></strong></td>
                <td><strong style="color:#c0392b"><?= $absent ?></strong></td>
                <td><?= $status ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php else: ?>
        <div style="text-align:center; padding:40px; color:#888;">
            No employees found. Please adjust your filters.
        </div>
    <?php endif; ?>

</div>

<script>
const postData  = <?= json_encode($post_list) ?>;
const postInput = document.getElementById('postInput');
const autoList  = document.getElementById('autoList');

postInput.addEventListener('input', function () {
    const val = this.value.toLowerCase().trim();
    autoList.innerHTML = '';
    if (val === '') { autoList.style.display = 'none'; return; }

    const matches = postData.filter(p => p.toLowerCase().includes(val));
    if (matches.length === 0) { autoList.style.display = 'none'; return; }

    matches.slice(0, 10).forEach(function (item) {
        const div = document.createElement('div');
        div.textContent = item;
        div.addEventListener('click', function () {
            postInput.value = item;
            autoList.style.display = 'none';
            document.getElementById('filterForm').submit();
        });
        autoList.appendChild(div);
    });
    autoList.style.display = 'block';
});

document.addEventListener('click', function (e) {
    if (!postInput.contains(e.target)) autoList.style.display = 'none';
});
</script>
</body>
</html>
