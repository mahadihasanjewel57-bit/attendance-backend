<?php
define('AUTH_TOKEN', 'ubpladmin2026secure');
$token = $_GET['token'] ?? '';
if ($token !== AUTH_TOKEN) {
    header("Location: admin_login.php");
    exit;
}
include "db.php";
date_default_timezone_set("Asia/Dhaka");
$t = AUTH_TOKEN;

// ── Create settings table if not exists ─────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS attendance_settings (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        setting_key  VARCHAR(100) UNIQUE NOT NULL,
        setting_val  VARCHAR(100) NOT NULL,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// ── Insert defaults if missing ───────────────────────────────────────
$defaults = [
    'entry_time'          => '09:00',
    'exit_time'           => '17:00',
    'late_grace_minutes'  => '10',
    'early_grace_minutes' => '10',
];
foreach ($defaults as $key => $val) {
    $conn->query("INSERT IGNORE INTO attendance_settings (setting_key, setting_val) VALUES ('$key', '$val')");
}

// ── Handle POST save ─────────────────────────────────────────────────
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entry_time  = $conn->real_escape_string($_POST['entry_time']          ?? '09:00');
    $exit_time   = $conn->real_escape_string($_POST['exit_time']           ?? '17:00');
    $late_grace  = (int)($_POST['late_grace_minutes']  ?? 10);
    $early_grace = (int)($_POST['early_grace_minutes'] ?? 10);

    $updates = [
        'entry_time'          => $entry_time,
        'exit_time'           => $exit_time,
        'late_grace_minutes'  => $late_grace,
        'early_grace_minutes' => $early_grace,
    ];

    $ok = true;
    foreach ($updates as $key => $val) {
        $res = $conn->query("UPDATE attendance_settings SET setting_val='$val' WHERE setting_key='$key'");
        if (!$res) $ok = false;
    }

    $success = $ok ? "Settings saved successfully." : "Failed to save some settings.";
}

// ── Load current settings ────────────────────────────────────────────
$settings = [];
$res = $conn->query("SELECT setting_key, setting_val FROM attendance_settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_val'];
}

$entry_time  = $settings['entry_time']          ?? '09:00';
$exit_time   = $settings['exit_time']           ?? '17:00';
$late_grace  = $settings['late_grace_minutes']  ?? '10';
$early_grace = $settings['early_grace_minutes'] ?? '10';

// ── Compute example labels ───────────────────────────────────────────
function addMinutes($time, $mins) {
    $dt = DateTime::createFromFormat('H:i', $time);
    $dt->modify("+{$mins} minutes");
    return $dt->format("h:i A");
}
function subMinutes($time, $mins) {
    $dt = DateTime::createFromFormat('H:i', $time);
    $dt->modify("-{$mins} minutes");
    return $dt->format("h:i A");
}

$entry_display     = DateTime::createFromFormat('H:i', $entry_time)->format("h:i A");
$exit_display      = DateTime::createFromFormat('H:i', $exit_time)->format("h:i A");
$late_deadline     = addMinutes($entry_time, $late_grace);
$early_exit_limit  = subMinutes($exit_time, $early_grace);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Settings — Union Bank</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f3f0fa; min-height: 100vh; }

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

        /* ── Container ── */
        .container { padding: 28px 24px; max-width: 860px; margin: auto; }

        .page-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .page-header .icon {
            width: 46px; height: 46px;
            background: #644BA4;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .page-header h2 { font-size: 20px; color: #333; font-weight: 700; }
        .page-header p  { font-size: 13px; color: #888; margin-top: 2px; }

        /* ── Alert ── */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error   { background: #fdecea; color: #c62828; border: 1px solid #ef9a9a; }

        /* ── Card ── */
        .card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #5D0476, #644BA4);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header span { font-size: 18px; }
        .card-header h3  { font-size: 15px; font-weight: 600; }
        .card-header p   { font-size: 12px; opacity: 0.8; margin-top: 2px; }
        .card-body { padding: 24px; }

        /* ── Form grid ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .form-group input[type="time"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e0d9f5;
            border-radius: 9px;
            font-size: 15px;
            color: #333;
            background: #faf8ff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
            border-color: #644BA4;
            box-shadow: 0 0 0 3px rgba(100,75,164,0.12);
            background: white;
        }
        .form-group .hint {
            font-size: 11px;
            color: #999;
            margin-top: 6px;
        }

        /* ── Preview panel ── */
        .preview {
            background: #faf8ff;
            border: 1.5px dashed #d0c5f0;
            border-radius: 12px;
            padding: 18px 20px;
            margin-top: 20px;
        }
        .preview h4 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #644BA4;
            margin-bottom: 14px;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .preview-item {
            background: white;
            border-radius: 10px;
            padding: 12px 14px;
            border-left: 4px solid #644BA4;
        }
        .preview-item.green  { border-left-color: #43a047; }
        .preview-item.orange { border-left-color: #fb8c00; }
        .preview-item.red    { border-left-color: #e53935; }
        .preview-item .pi-label { font-size: 11px; color: #888; margin-bottom: 4px; }
        .preview-item .pi-time  { font-size: 17px; font-weight: 700; color: #333; }
        .preview-item .pi-desc  { font-size: 11px; color: #aaa; margin-top: 2px; }

        /* ── Save button ── */
        .btn-save {
            margin-top: 24px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #5D0476, #644BA4);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: opacity 0.2s, transform 0.1s;
        }
        .btn-save:hover  { opacity: 0.92; }
        .btn-save:active { transform: scale(0.99); }

        /* ── Info box ── */
        .info-box {
            background: #fffde7;
            border: 1px solid #ffe082;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            color: #5d4037;
            line-height: 1.7;
        }
        .info-box strong { color: #e65100; }
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
        <a href="admin_settings.php?token=<?= $t ?>" class="active">Settings</a>
        <a href="admin_login.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-header">
        <div class="icon">⚙️</div>
        <div>
            <h2>Attendance Time Settings</h2>
            <p>Configure official entry/exit times and grace periods for late & early exit detection</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="admin_settings.php?token=<?= $t ?>">

        <!-- ── Official Times ── -->
        <div class="card">
            <div class="card-header">
                <span>🕐</span>
                <div>
                    <h3>Official Work Hours</h3>
                    <p>Set the standard entry and exit times for all employees</p>
                </div>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Official Entry Time</label>
                        <input type="time" name="entry_time" value="<?= htmlspecialchars($entry_time) ?>" required>
                        <div class="hint">Employees must check in by this time</div>
                    </div>
                    <div class="form-group">
                        <label>Official Exit Time</label>
                        <input type="time" name="exit_time" value="<?= htmlspecialchars($exit_time) ?>" required>
                        <div class="hint">Employees must check out after this time</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Grace Periods ── -->
        <div class="card">
            <div class="card-header">
                <span>⏱️</span>
                <div>
                    <h3>Grace Periods</h3>
                    <p>Tolerance window before marking an employee as late or early exit</p>
                </div>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Late Grace Period (minutes)</label>
                        <input type="number" name="late_grace_minutes" value="<?= htmlspecialchars($late_grace) ?>" min="0" max="120" required>
                        <div class="hint">Check-in allowed up to this many minutes after entry time</div>
                    </div>
                    <div class="form-group">
                        <label>Early Exit Grace Period (minutes)</label>
                        <input type="number" name="early_grace_minutes" value="<?= htmlspecialchars($early_grace) ?>" min="0" max="120" required>
                        <div class="hint">Check-out allowed up to this many minutes before exit time</div>
                    </div>
                </div>

                <!-- ── Live preview ── -->
                <div class="preview">
                    <h4>📊 Current Rule Preview</h4>
                    <div class="preview-grid">
                        <div class="preview-item green">
                            <div class="pi-label">Official Entry</div>
                            <div class="pi-time"><?= $entry_display ?></div>
                            <div class="pi-desc">On-time if checked in by this</div>
                        </div>
                        <div class="preview-item orange">
                            <div class="pi-label">Late After</div>
                            <div class="pi-time"><?= $late_deadline ?></div>
                            <div class="pi-desc">Grace: <?= $late_grace ?> min after entry</div>
                        </div>
                        <div class="preview-item green">
                            <div class="pi-label">Official Exit</div>
                            <div class="pi-time"><?= $exit_display ?></div>
                            <div class="pi-desc">On-time if checked out after this</div>
                        </div>
                        <div class="preview-item red">
                            <div class="pi-label">Early Exit Before</div>
                            <div class="pi-time"><?= $early_exit_limit ?></div>
                            <div class="pi-desc">Grace: <?= $early_grace ?> min before exit</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Save Settings</button>
            </div>
        </div>

    </form>

    <!-- ── Info ── -->
    <div class="info-box">
        ℹ️ <strong>How this works:</strong> These settings are used across the attendance system.
        In the <strong>Attendance History</strong> (app) and <strong>Observation</strong> (admin) pages,
        each employee's check-in/out time is compared against these values.
        If check-in is after <strong><?= $late_deadline ?></strong>, they are marked <strong>Late</strong>.
        If check-out is before <strong><?= $early_exit_limit ?></strong>, they are marked <strong>Early Exit</strong>.
    </div>

</div>

</body>
</html>
