<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");

$today       = date("Y-m-d");
$t           = AUTH_TOKEN;
$filter_date = $_GET['date'] ?? $today;
$filter_emp  = trim($_GET['emp'] ?? '');
$filter_post = trim($_GET['post'] ?? '');

// ── Load attendance settings ──────────────────────────────────────
$settings = [];
$sRes = $conn->query("SHOW TABLES LIKE 'attendance_settings'");
if ($sRes && $sRes->num_rows > 0) {
    $sRes2 = $conn->query("SELECT setting_key, setting_val FROM attendance_settings");
    if ($sRes2) {
        while ($row = $sRes2->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_val'];
        }
    }
}
$entry_time  = $settings['entry_time']          ?? '09:00';
$exit_time   = $settings['exit_time']           ?? '17:00';
$late_grace  = (int)($settings['late_grace_minutes']  ?? 10);
$early_grace = (int)($settings['early_grace_minutes'] ?? 10);

// Threshold DateTimes
$lateThreshold      = DateTime::createFromFormat('H:i', $entry_time)->modify("+{$late_grace} minutes");
$earlyExitThreshold = DateTime::createFromFormat('H:i', $exit_time)->modify("-{$early_grace} minutes");

$lateLabel      = $lateThreshold->format("h:i A");
$earlyExitLabel = $earlyExitThreshold->format("h:i A");

// ── Helper: compute flags ─────────────────────────────────────────
function computeFlags($check_in_raw, $check_out_raw, $lateThreshold, $earlyExitThreshold) {
    $flags = [];
    if ($check_in_raw) {
        $ci = DateTime::createFromFormat('Y-m-d H:i:s', $check_in_raw);
        if ($ci) {
            $ciTime = DateTime::createFromFormat('H:i', $ci->format('H:i'));
            if ($ciTime > $lateThreshold) $flags[] = 'late';
        }
    }
    if ($check_out_raw) {
        $co = DateTime::createFromFormat('Y-m-d H:i:s', $check_out_raw);
        if ($co) {
            $coTime = DateTime::createFromFormat('H:i', $co->format('H:i'));
            if ($coTime < $earlyExitThreshold) $flags[] = 'early_exit';
        }
    }
    return $flags;
}

// ── Build query ───────────────────────────────────────────────────
$where  = "WHERE DATE(p.LOGDTIME) = ?";
$params = [$filter_date];
$types  = "s";

if ($filter_emp !== '') {
    $where   .= " AND p.EMPLCODE = ?";
    $params[] = $filter_emp;
    $types   .= "s";
}

if ($filter_post !== '') {
    if ($filter_post === 'Head Office') {
        $where   .= " AND m.pyempost LIKE ?";
        $params[] = "%Head Office%";
        $types   .= "s";
    } else {
        $where   .= " AND m.pyempost = ?";
        $params[] = $filter_post;
        $types   .= "s";
    }
}

$sql = "
    SELECT p.EMPLCODE, m.pyempnam, m.pyempost,
        MIN(p.LOGDTIME) as check_in,
        MAX(p.LOGDTIME) as check_out,
        COUNT(*) as punches
    FROM pyacslog p
    LEFT JOIN pyempmas m ON p.EMPLCODE = m.pyempcde
    $where
    GROUP BY p.EMPLCODE, m.pyempnam, m.pyempost
    ORDER BY m.pyempost ASC, check_in ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ── Pre-fetch all rows so we can count flags ──────────────────────
$rows = [];
$count_late = 0; $count_early = 0; $count_both = 0; $count_ok = 0;
while ($row = $result->fetch_assoc()) {
    $co_raw = $row['punches'] > 1 ? $row['check_out'] : null;
    $flags  = computeFlags($row['check_in'], $co_raw, $lateThreshold, $earlyExitThreshold);
    $row['flags'] = $flags;
    if (in_array('late', $flags) && in_array('early_exit', $flags)) $count_both++;
    elseif (in_array('late', $flags))       $count_late++;
    elseif (in_array('early_exit', $flags)) $count_early++;
    else $count_ok++;
    $rows[] = $row;
}

// ── Fetch posting places for autocomplete ────────────────────────
$post_list = [];
$post_res  = $conn->query("
    SELECT DISTINCT pyempost FROM pyempmas
    WHERE pyempost IS NOT NULL AND pyempost != ''
    ORDER BY pyempost ASC
");
while ($pr = $post_res->fetch_assoc()) $post_list[] = $pr['pyempost'];
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

        .container { padding: 24px; max-width: 1200px; margin: auto; }

        /* ── Filter card ── */
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-row label { display: block; font-size: 12px; color: #888; margin-bottom: 6px; }
        .filter-row input {
            padding: 9px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            min-width: 180px;
        }
        .filter-row input:focus { border-color: #644BA4; }

        /* ── Settings hint bar ── */
        .settings-hint {
            background: #f0ebff;
            border: 1px solid #d0c5f0;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 12px;
            color: #4a148c;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .settings-hint strong { color: #5D0476; }
        .settings-hint a {
            margin-left: auto;
            font-size: 12px;
            color: #644BA4;
            text-decoration: none;
            border: 1px solid #644BA4;
            padding: 3px 10px;
            border-radius: 6px;
        }
        .settings-hint a:hover { background: #644BA4; color: white; }

        /* ── Summary chips ── */
        .summary-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .chip {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .chip-ok     { background: #e8f5e9; color: #2e7d32; }
        .chip-late   { background: #fff3e0; color: #e65100; }
        .chip-early  { background: #fce4ec; color: #c62828; }
        .chip-both   { background: #f3e5f5; color: #6a1b9a; }
        .chip .dot   { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

        /* ── Buttons ── */
        .btn {
            padding: 9px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .btn-search { background: #644BA4; color: white; }
        .btn-search:hover { background: #5D0476; }
        .btn-export { background: #2e7d32; color: white; }
        .btn-export:hover { background: #1b5e20; }
        .btn-raw    { background: #1565c0; color: white; }
        .btn-raw:hover { background: #0d47a1; }
        .btn-sql    { background: #e65100; color: white; }
        .btn-sql:hover { background: #bf360c; }
        .btn-ho     { background: #5D0476; color: white; }
        .btn-ho:hover { background: #644BA4; }
        .btn-clear  { background: #888; color: white; }
        .btn-clear:hover { background: #555; }

        .section-title { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 12px; }
        .post-badge {
            background: #ede7f6; color: #4527a0;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: bold; margin-left: 8px;
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
        th { background: #644BA4; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
        td { padding: 11px 16px; font-size: 13px; border-bottom: 1px solid #f0f0f0; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #faf8ff; }

        /* ── Status badges ── */
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-green  { background: #e8f5e9; color: #2e7d32; }
        .badge-orange { background: #fff3e0; color: #e65100; }

        /* ── Flag badges ── */
        .flags-cell { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .flag {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .flag-late       { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
        .flag-early-exit { background: #fce4ec; color: #c62828; border: 1px solid #f48fb1; }
        .flag-ok         { background: #e8f5e9; color: #2e7d32; font-size: 11px; }

        /* ── Autocomplete ── */
        .autocomplete-wrap { position: relative; }
        .autocomplete-list {
            position: absolute; top: 100%; left: 0; right: 0;
            background: white; border: 1px solid #ddd;
            border-top: none; border-radius: 0 0 8px 8px;
            max-height: 200px; overflow-y: auto;
            z-index: 999; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .autocomplete-list div { padding: 9px 14px; font-size: 13px; cursor: pointer; color: #333; }
        .autocomplete-list div:hover { background: #f3f0fa; color: #644BA4; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>🏦 Union Bank — Admin Panel</h1>
    <div class="nav-links">
        <a href="admin_dashboard.php?token=<?= $t ?>">Dashboard</a>
        <a href="admin_attendance.php?token=<?= $t ?>" class="active">Attendance</a>
        <a href="admin_monthly.php?token=<?= $t ?>">Monthly</a>
        <a href="admin_observation.php?token=<?= $t ?>">Observation</a>
        <a href="admin_employees_edit.php?token=<?= $t ?>">Edit Employees</a>
        <a href="admin_device.php?token=<?= $t ?>">Devices</a>
        <a href="admin_import.php?token=<?= $t ?>">Import</a>
        <a href="admin_login.php">Logout</a>
    </div>
</div>

<div class="container">

    <!-- ── Filter form ── -->
    <form method="GET" id="filterForm">
        <input type="hidden" name="token" value="<?= $t ?>">
        <div class="filter-card">
            <div class="filter-row">
                <div>
                    <label>Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div>
                    <label>Employee ID (optional)</label>
                    <input type="text" name="emp" value="<?= htmlspecialchars($filter_emp) ?>">
                </div>
                <div class="autocomplete-wrap">
                    <label>Posting Place (optional)</label>
                    <input type="text" name="post" id="postInput"
                        placeholder="Type posting place..."
                        value="<?= htmlspecialchars($filter_post) ?>"
                        autocomplete="off">
                    <div class="autocomplete-list" id="autoList"></div>
                </div>
                <button type="submit" class="btn btn-search">🔍 Search</button>
                <?php if ($filter_emp || $filter_post): ?>
                    <a href="admin_attendance.php?token=<?= $t ?>&date=<?= urlencode($filter_date) ?>"
                       class="btn btn-clear">✖ Clear</a>
                <?php endif; ?>
            </div>
            <div class="filter-row">
                <a href="admin_attendance.php?token=<?= $t ?>&date=<?= urlencode($filter_date) ?>&post=Head+Office"
                   class="btn btn-ho">🏦 Head Office Attendance</a>
                <a href="admin_export.php?token=<?= $t ?>&date=<?= urlencode($filter_date) ?>&emp=<?= urlencode($filter_emp) ?>&post=<?= urlencode($filter_post) ?>"
                   class="btn btn-export">📥 Export Excel</a>
                <a href="admin_export_raw.php?token=<?= $t ?>&date=<?= urlencode($filter_date) ?>&emp=<?= urlencode($filter_emp) ?>&post=<?= urlencode($filter_post) ?>"
                   class="btn btn-raw">📊 Export Raw Table</a>
                <a href="admin_export_sql.php?token=<?= $t ?>&date=<?= urlencode($filter_date) ?>&emp=<?= urlencode($filter_emp) ?>&post=<?= urlencode($filter_post) ?>"
                   class="btn btn-sql">🗄️ Export SQL</a>
            </div>
        </div>
    </form>

    <!-- ── Settings hint ── -->
    <div class="settings-hint">
        ⚙️ Late after: <strong><?= $lateLabel ?></strong>
        &nbsp;|&nbsp;
        Early Exit before: <strong><?= $earlyExitLabel ?></strong>
        &nbsp;|&nbsp;
        Grace: <strong><?= $late_grace ?> min</strong> entry &amp; <strong><?= $early_grace ?> min</strong> exit
        <a href="admin_settings.php?token=<?= $t ?>">⚙ Change Settings</a>
    </div>

    <!-- ── Summary chips ── -->
    <?php if (count($rows) > 0): ?>
    <div class="summary-row">
        <div class="chip chip-ok">
            <span class="dot"></span> On Time: <?= $count_ok ?>
        </div>
        <div class="chip chip-late">
            <span class="dot"></span> Late: <?= $count_late ?>
        </div>
        <div class="chip chip-early">
            <span class="dot"></span> Early Exit: <?= $count_early ?>
        </div>
        <div class="chip chip-both">
            <span class="dot"></span> Late + Early Exit: <?= $count_both ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Title ── -->
    <div class="section-title">
        📋 Attendance Report — <?= date("d M Y", strtotime($filter_date)) ?>
        <?php if ($filter_post): ?>
            <span class="post-badge">🏢 <?= htmlspecialchars($filter_post) ?></span>
        <?php endif; ?>
    </div>

    <!-- ── Table ── -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Posting</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Punches</th>
                <th>Status</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="9" style="text-align:center; color:#888; padding:24px;">
                    No attendance found for this date
                </td>
            </tr>
        <?php endif; ?>
        <?php
        $i = 1;
        foreach ($rows as $row):
            $ci     = date("h:i A", strtotime($row['check_in']));
            $co     = $row['punches'] > 1 ? date("h:i A", strtotime($row['check_out'])) : "--:--";
            $status = $row['punches'] > 1 ? "Complete" : "Checked In";
            $badge  = $row['punches'] > 1 ? "badge-green" : "badge-orange";
            $flags  = $row['flags'];

            // Row highlight for late/early
            $rowStyle = '';
            if (in_array('late', $flags) && in_array('early_exit', $flags))
                $rowStyle = 'background:#fdf4ff;';
            elseif (in_array('late', $flags))
                $rowStyle = 'background:#fffdf5;';
            elseif (in_array('early_exit', $flags))
                $rowStyle = 'background:#fff8f8;';
        ?>
            <tr style="<?= $rowStyle ?>">
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['EMPLCODE']) ?></td>
                <td><?= htmlspecialchars($row['pyempnam'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['pyempost'] ?? '') ?></td>
                <td><?= $ci ?></td>
                <td><?= $co ?></td>
                <td><?= $row['punches'] ?></td>
                <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
                <td>
                    <div class="flags-cell">
                        <?php if (empty($flags)): ?>
                            <span class="flag flag-ok">✓ On Time</span>
                        <?php endif; ?>
                        <?php if (in_array('late', $flags)): ?>
                            <span class="flag flag-late">⏰ Late</span>
                        <?php endif; ?>
                        <?php if (in_array('early_exit', $flags)): ?>
                            <span class="flag flag-early-exit">🚪 Early Exit</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// ── Autocomplete ──────────────────────────────────────────────────
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
