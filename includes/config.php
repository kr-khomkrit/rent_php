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

// สร้างบัญชี admin เริ่มต้นถ้าไม่มี
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute(['admin']);

    if ($stmt->fetchColumn() == 0) {
        // สร้างบัญชี admin ใหม่
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, role, first_name, last_name, phone, emergency_contact, status, has_room)
            VALUES (?, ?, 'admin', 'Administrator', 'System', '0812345678', '0898765432', 'active', 0)
        ");
        $stmt->execute(['admin', $admin_password]);
    }

    // อัปเดต password ของบัญชี admin หากมีอยู่แล้วแต่ password ไม่ถูกต้อง
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin' AND role = 'admin'");
    $stmt->execute();
    $current_hash = $stmt->fetchColumn();

    if ($current_hash && !password_verify('admin123', $current_hash)) {
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin' AND role = 'admin'");
        $stmt->execute([$new_hash]);
    }

    // สร้างบัญชี user ตัวอย่างถ้าไม่มี
    $test_users = [
        ['john_doe', 'user123', 'John', 'Doe', '0823456789', '0887654321'],
        ['jane_smith', 'user123', 'Jane', 'Smith', '0834567890', '0876543210']
    ];

    foreach ($test_users as $user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$user[0]]);

        if ($stmt->fetchColumn() == 0) {
            $user_password = password_hash($user[1], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, role, first_name, last_name, phone, emergency_contact, status, has_room)
                VALUES (?, ?, 'user', ?, ?, ?, ?, 'active', 0)
            ");
            $stmt->execute([$user[0], $user_password, $user[2], $user[3], $user[4], $user[5]]);
        }
    }

} catch(PDOException $e) {
    // ไม่ต้องแสดงข้อผิดพลาดในการสร้างผู้ใช้เริ่มต้น
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
    return number_format($amount, 2) . '';
}
?>