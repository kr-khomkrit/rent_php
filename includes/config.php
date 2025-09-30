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

// ฟังก์ชันจัดรูปแบบเดือน/ปี
function formatBillingMonth($date) {
    if (empty($date)) return '-';
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $month = (int)date('n', strtotime($date));
    $year = (int)date('Y', strtotime($date)) + 543; // แปลงเป็น พ.ศ.
    return $thai_months[$month] . ' ' . $year;
}

// ฟังก์ชันคำนวณค่าน้ำ
function calculateWaterBill($previous, $current, $rate) {
    $unit = $current - $previous;
    return $unit * $rate;
}

// ฟังก์ชันคำนวณค่าไฟ
function calculateElectricityBill($previous, $current, $rate) {
    $unit = $current - $previous;
    return $unit * $rate;
}

// ฟังก์ชันดึงเลขมิเตอร์เดือนก่อนหน้า
function getPreviousMeterReading($pdo, $contract_id) {
    $stmt = $pdo->prepare("
        SELECT water_current, electricity_current
        FROM utility_bills
        WHERE contract_id = ?
        ORDER BY billing_month DESC
        LIMIT 1
    ");
    $stmt->execute([$contract_id]);
    $result = $stmt->fetch();

    if ($result) {
        return [
            'water_previous' => $result['water_current'],
            'electricity_previous' => $result['electricity_current']
        ];
    }

    return [
        'water_previous' => 0,
        'electricity_previous' => 0
    ];
}

// ฟังก์ชันตรวจสอบสถานะบิล (เกินกำหนดหรือไม่)
function getBillStatus($status, $billing_month, $paid_date = null) {
    if ($status === 'paid') {
        return 'paid';
    }

    // ถ้ายังไม่จ่าย ตรวจสอบว่าเกินกำหนดหรือไม่ (เกิน 7 วันหลังสิ้นเดือน)
    $due_date = date('Y-m-d', strtotime($billing_month . ' +1 month +7 days'));
    if (date('Y-m-d') > $due_date) {
        return 'overdue';
    }

    return 'pending';
}

// ฟังก์ชันสร้างรายการเดือนย้อนหลัง
function getMonthOptions($months = 12) {
    $options = [];
    for ($i = 0; $i < $months; $i++) {
        $date = date('Y-m-01', strtotime("-$i months"));
        $options[] = [
            'value' => $date,
            'text' => formatBillingMonth($date)
        ];
    }
    return $options;
}

// ===================================
// ฟังก์ชันสำหรับระบบสัญญา (Contracts)
// ===================================

// ฟังก์ชันสร้างเลขที่สัญญาอัตโนมัติ
function generateContractNumber($pdo) {
    try {
        $stmt = $pdo->query("SELECT generate_contract_number() as contract_number");
        $result = $stmt->fetch();
        return $result['contract_number'];
    } catch (PDOException $e) {
        // ถ้า function ไม่มี ให้สร้างเองด้วย PHP
        $year = date('Y');
        $stmt = $pdo->prepare("
            SELECT contract_number
            FROM contracts
            WHERE contract_number LIKE ?
            ORDER BY contract_number DESC
            LIMIT 1
        ");
        $stmt->execute(["CT-{$year}-%"]);
        $last = $stmt->fetch();

        if ($last) {
            $parts = explode('-', $last['contract_number']);
            $seq = intval($parts[2]) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('CT-%d-%03d', $year, $seq);
    }
}

// ฟังก์ชันดึงข้อมูลสัญญาพร้อมข้อมูลเต็ม
function getContractData($pdo, $contract_id) {
    $stmt = $pdo->prepare("
        SELECT c.*,
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               u.phone, u.emergency_contact,
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
               r.water_rate, r.electricity_rate
        FROM contracts c
        JOIN users u ON c.user_id = u.user_id
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.contract_id = ?
    ");
    $stmt->execute([$contract_id]);
    return $stmt->fetch();
}

// ฟังก์ชันแทนที่ placeholders ใน template
function replaceContractPlaceholders($template, $data) {
    $placeholders = [
        '{{contract_number}}' => $data['contract_number'] ?? '',
        '{{tenant_name}}' => $data['tenant_name'] ?? '',
        '{{phone}}' => $data['phone'] ?? '',
        '{{emergency_contact}}' => $data['emergency_contact'] ?? '',
        '{{room_name}}' => $data['room_name'] ?? '',
        '{{rental_price}}' => number_format($data['rental_price'] ?? 0, 2),
        '{{water_rate}}' => number_format($data['water_rate'] ?? 0, 2),
        '{{electricity_rate}}' => number_format($data['electricity_rate'] ?? 0, 2),
        '{{start_date}}' => formatThaiDate($data['start_date'] ?? ''),
        '{{end_date}}' => formatThaiDate($data['end_date'] ?? ''),
        '{{contract_terms}}' => nl2br(htmlspecialchars($data['contract_terms'] ?? '')),
    ];

    // แทนที่ placeholders
    $html = $template;
    foreach ($placeholders as $key => $value) {
        $html = str_replace($key, $value, $html);
    }

    // จัดการกับ conditional {{#if contract_terms}}
    if (empty($data['contract_terms'])) {
        $html = preg_replace('/\{\{#if contract_terms\}\}.*?\{\{\/if\}\}/s', '', $html);
    } else {
        $html = str_replace(['{{#if contract_terms}}', '{{/if}}'], '', $html);
    }

    return $html;
}

// ฟังก์ชันแปลงวันที่เป็นรูปแบบไทย
function formatThaiDate($date) {
    if (empty($date)) return '';

    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp) + 543;

    return "{$day} {$thai_months[$month]} {$year}";
}
?>