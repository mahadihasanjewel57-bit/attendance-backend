<?php
session_start();

$admin_id = "0204201700923";
$admin_pass = "Mahadi_007";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_id   = trim($_POST['admin_id'] ?? '');
    $input_pass = trim($_POST['admin_pass'] ?? '');

    if ($input_id === $admin_id && $input_pass === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $input_id;
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "Invalid ID or Password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Union Bank</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f3f0fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo h2 {
            color: #644BA4;
            font-size: 22px;
        }
        .logo p {
            color: #888;
            font-size: 13px;
        }
        label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 6px;
            margin-top: 16px;
        }
        input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        input:focus {
            border-color: #644BA4;
        }
        button {
            margin-top: 24px;
            width: 100%;
            padding: 12px;
            background: #644BA4;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background: #5D0476; }
        .error {
            margin-top: 16px;
            padding: 10px;
            background: #fdecea;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            color: #c0392b;
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h2>🏦 UNION BANK PLC</h2>
        <p>Admin Panel</p>
    </div>

    <form method="POST">
        <label>Admin ID</label>
        <input type="text" name="admin_id" placeholder="Enter Admin ID" required>

        <label>Password</label>
        <input type="password" name="admin_pass" placeholder="Enter Password" required>

        <button type="submit">LOGIN</button>
    </form>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</div>
</body>
</html>
