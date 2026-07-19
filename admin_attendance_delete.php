<?php
define('AUTH_TOKEN','ubpladmin2026secure');
if(($_GET['token']??'')!==AUTH_TOKEN){ header("Location: admin_login.php"); exit; }
include "db.php";
$msg="";
if(isset($_POST['delete_logindex'])){
 $stmt=$conn->prepare("DELETE FROM pyacslog WHERE LOGINDEX=?");
 $stmt->bind_param("i",$_POST['delete_logindex']);
 $msg=$stmt->execute()?"Attendance deleted successfully.":"Delete failed.";
}
$emp=$_GET['emp']??'';
$date=$_GET['date']??date('Y-m-d');
$data=[];
$name="";
if($emp!=""){
 $s=$conn->prepare("SELECT PYEMPNAM FROM pyempmas WHERE PYEMPCDE=?");
 $s->bind_param("s",$emp); $s->execute();
 $r=$s->get_result()->fetch_assoc(); $name=$r['PYEMPNAM']??"Unknown";
 $q=$conn->prepare("SELECT LOGINDEX,LOGDTIME,TERMNAME,BRANCODE FROM pyacslog WHERE EMPLCODE=? AND DATE(LOGDTIME)=? ORDER BY LOGDTIME");
 $q->bind_param("ss",$emp,$date); $q->execute();
 $data=$q->get_result();
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Attendance Delete</title>
<style>
body{font-family:Segoe UI;background:#f3f0fa;margin:0}
.top{background:#644BA4;color:#fff;padding:15px}
.box{max-width:1000px;margin:20px auto;background:#fff;padding:20px;border-radius:8px}
input,button{padding:8px} table{width:100%;border-collapse:collapse;margin-top:15px}
th{background:#644BA4;color:#fff} th,td{padding:10px;border:1px solid #ddd}
.msg{color:green;margin:10px 0}
.navbar {
    background: #644BA4;
    color: white;
    padding: 14px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar h1 {
    font-size: 18px;
}

.nav-links {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.navbar a {
    color: white;
    text-decoration: none;
    font-size: 13px;
    padding: 6px 12px;
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: 6px;
}

.navbar a:hover,
.navbar a.active {
    background: rgba(255,255,255,0.2);
}
</style></head><body>
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

        <a href="admin_attendance_delete.php?token=<?= $t ?>" class="active">
            Attendance Delete
        </a>

        <a href="admin_settings.php?token=<?= $t ?>">Settings</a>
        <a href="admin_login.php">Logout</a>
    </div>
</div>
<div class="top"><h2>Attendance Delete</h2></div>
<div class="box">
<?php if($msg) echo "<div class='msg'>$msg</div>"; ?>
<form method="get">
<input type="hidden" name="token" value="<?=AUTH_TOKEN?>">
Employee ID <input name="emp" value="<?=htmlspecialchars($emp)?>" required>
Date <input type="date" name="date" value="<?=htmlspecialchars($date)?>" required>
<button>Search</button>
</form>
<?php if($emp!=""): ?>
<p><b>Name:</b> <?=htmlspecialchars($name)?></p>
<table>
<tr><th>LOGINDEX</th><th>Date Time</th><th>Terminal</th><th>Branch</th><th>Action</th></tr>
<?php while($row=$data->fetch_assoc()): ?>
<tr>
<td><?=$row['LOGINDEX']?></td>
<td><?=$row['LOGDTIME']?></td>
<td><?=htmlspecialchars($row['TERMNAME'])?></td>
<td><?=htmlspecialchars($row['BRANCODE'])?></td>
<td>
<form method="post" onsubmit="return confirm('Delete this attendance permanently?');">
<input type="hidden" name="delete_logindex" value="<?=$row['LOGINDEX']?>">
<button type="submit">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>
</div></body></html>