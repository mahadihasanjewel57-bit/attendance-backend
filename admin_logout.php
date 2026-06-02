<?php
setcookie('admin_token', '', time() - 3600, '/');
header("Location: admin_login.php");
exit;
?>
