<?php
// การตั้งค่าฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'rental_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// การตั้งค่าเว็บไซต์
define('SITE_NAME', 'ระบบจัดการห้องเช่า');
define('SITE_URL', 'http://localhost/rent/');

// การตั้งค่า Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage());
}

// ฟังก์ชันตรวจสอบการเข้าสู่ระบบ
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ฟังก์ชันตรวจสอบสิทธิ์ Admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// ฟังก์ชันป้องกันการเข้าถึงไม่ได้รับอนุญาต
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . 'login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
}

// ฟังก์ชันป้องกัน XSS
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ฟังก์ชันจัดรูปแบบวันที่
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

// ฟังก์ชันจัดรูปแบบเงิน
function formatMoney($amount) {
    return number_format($amount, 2) . ' บาท';
}
?>