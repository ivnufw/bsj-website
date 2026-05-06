<?php
session_start();
session_unset();
session_destroy();
// ส่งกลับไปหน้า Login
header("Location: login.php"); 
exit;
?>