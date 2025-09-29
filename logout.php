<?php
require_once 'includes/config.php';

// ล้าง Session
session_unset();
session_destroy();

// Redirect ไปหน้า Login
header('Location: login.php');
exit();
?>