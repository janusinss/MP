<?php
// admin/logout.php
session_start();
unset($_SESSION['admin_logged_in']);
header("Location: login.php");
exit;
?>
