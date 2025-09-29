<?php
require_once 'includes/config.php';

// ถ้ายังไม่ได้ login ให้ redirect ไปหน้า login
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// ถ้า login แล้วให้ redirect ไปหน้าที่เหมาะสม
if (isAdmin()) {
    header('Location: pages/admin/dashboard.php');
} else {
    header('Location: pages/user/dashboard.php');
}
exit();
?>