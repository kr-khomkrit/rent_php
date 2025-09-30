-- สร้างฐานข้อมูลระบบเช่าห้องพัก (เวอร์ชัน 2.0)
-- Rental Room Management System Database
-- เพิ่มระบบแจ้งค่าน้ำค่าไฟรายเดือน

CREATE DATABASE IF NOT EXISTS rental_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_system;

-- ============================================
-- ตารางโซน
-- ============================================
CREATE TABLE zones (
    zone_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ตารางห้อง
-- ============================================
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    water_rate DECIMAL(10,2) DEFAULT 18.00 COMMENT 'อัตราค่าน้ำต่อหน่วย (ค่าเริ่มต้น)',
    electricity_rate DECIMAL(10,2) DEFAULT 5.00 COMMENT 'อัตราค่าไฟต่อหน่วย (ค่าเริ่มต้น)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id) ON DELETE CASCADE,
    UNIQUE KEY unique_room (zone_id, room_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ตารางผู้ใช้
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    emergency_contact VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    has_room BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ตารางสัญญา
-- ============================================
CREATE TABLE contracts (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    rental_price DECIMAL(10,2) NOT NULL COMMENT 'ค่าเช่าต่อเดือน',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    contract_terms TEXT,
    status ENUM('active', 'expired', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ตารางบิลค่าน้ำค่าไฟรายเดือน (ใหม่)
-- ============================================
CREATE TABLE utility_bills (
    bill_id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    billing_month DATE NOT NULL COMMENT 'เดือนที่แจ้ง (เก็บวันที่ 1 ของเดือน เช่น 2024-01-01)',

    -- มิเตอร์น้ำ
    water_previous INT DEFAULT 0 COMMENT 'เลขมิเตอร์น้ำเดือนก่อน',
    water_current INT NOT NULL COMMENT 'เลขมิเตอร์น้ำเดือนนี้',
    water_unit INT GENERATED ALWAYS AS (water_current - water_previous) STORED COMMENT 'หน่วยน้ำที่ใช้',
    water_rate DECIMAL(10,2) NOT NULL COMMENT 'อัตราค่าน้ำต่อหน่วย',
    water_total DECIMAL(10,2) GENERATED ALWAYS AS (water_unit * water_rate) STORED COMMENT 'ยอดรวมค่าน้ำ',

    -- มิเตอร์ไฟ
    electricity_previous INT DEFAULT 0 COMMENT 'เลขมิเตอร์ไฟเดือนก่อน',
    electricity_current INT NOT NULL COMMENT 'เลขมิเตอร์ไฟเดือนนี้',
    electricity_unit INT GENERATED ALWAYS AS (electricity_current - electricity_previous) STORED COMMENT 'หน่วยไฟที่ใช้',
    electricity_rate DECIMAL(10,2) NOT NULL COMMENT 'อัตราค่าไฟต่อหน่วย',
    electricity_total DECIMAL(10,2) GENERATED ALWAYS AS (electricity_unit * electricity_rate) STORED COMMENT 'ยอดรวมค่าไฟ',

    -- รวมและสถานะ
    rental_price DECIMAL(10,2) NOT NULL COMMENT 'ค่าเช่าประจำเดือน',
    other_fees DECIMAL(10,2) DEFAULT 0.00 COMMENT 'ค่าใช้จ่ายอื่นๆ (ถ้ามี)',
    total_amount DECIMAL(10,2) GENERATED ALWAYS AS (water_total + electricity_total + rental_price + other_fees) STORED COMMENT 'ยอดรวมทั้งหมด',

    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending' COMMENT 'สถานะการชำระเงิน',
    paid_date DATE NULL COMMENT 'วันที่ชำระเงิน',
    payment_method ENUM('cash', 'transfer', 'qr', 'other') NULL COMMENT 'วิธีการชำระเงิน',
    payment_note TEXT NULL COMMENT 'หมายเหตุการชำระเงิน',

    note TEXT NULL COMMENT 'หมายเหตุเพิ่มเติม',
    created_by INT NULL COMMENT 'ผู้สร้างบิล (admin user_id)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_bill (contract_id, billing_month) COMMENT 'ห้ามแจ้งบิลซ้ำในเดือนเดียวกัน',
    INDEX idx_billing_month (billing_month),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางบิลค่าน้ำค่าไฟรายเดือน';

-- ============================================
-- ตารางประวัติการชำระเงิน (ใหม่)
-- ============================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'จำนวนเงินที่ชำระ',
    payment_method ENUM('cash', 'transfer', 'qr', 'other') DEFAULT 'cash',
    payment_date DATE NOT NULL COMMENT 'วันที่ชำระเงิน',
    reference_number VARCHAR(100) NULL COMMENT 'เลขที่อ้างอิง (เช่น เลขที่โอน)',
    note TEXT NULL,
    created_by INT NULL COMMENT 'ผู้บันทึกการชำระ (admin user_id)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (bill_id) REFERENCES utility_bills(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางประวัติการชำระเงิน';

-- ============================================
-- สร้าง View สำหรับดูข้อมูลบิลแบบละเอียด
-- ============================================
CREATE OR REPLACE VIEW v_utility_bills_detail AS
SELECT
    ub.bill_id,
    ub.billing_month,
    DATE_FORMAT(ub.billing_month, '%M %Y') as billing_month_text,
    DATE_FORMAT(ub.billing_month, '%m/%Y') as billing_month_short,
    YEAR(ub.billing_month) as billing_year,
    MONTH(ub.billing_month) as billing_month_num,

    -- ข้อมูลผู้เช่า
    c.contract_id,
    u.user_id,
    CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
    u.phone as tenant_phone,

    -- ข้อมูลห้อง
    r.room_id,
    z.zone_name,
    r.room_number,
    CONCAT(z.zone_name, '-', r.room_number) as room_name,

    -- มิเตอร์น้ำ
    ub.water_previous,
    ub.water_current,
    ub.water_unit,
    ub.water_rate,
    ub.water_total,

    -- มิเตอร์ไฟ
    ub.electricity_previous,
    ub.electricity_current,
    ub.electricity_unit,
    ub.electricity_rate,
    ub.electricity_total,

    -- ยอดรวม
    ub.rental_price,
    ub.other_fees,
    ub.total_amount,

    -- สถานะ
    ub.status,
    ub.paid_date,
    ub.payment_method,
    ub.payment_note,
    ub.note,

    ub.created_by,
    ub.created_at,
    ub.updated_at
FROM utility_bills ub
JOIN contracts c ON ub.contract_id = c.contract_id
JOIN users u ON c.user_id = u.user_id
JOIN rooms r ON c.room_id = r.room_id
JOIN zones z ON r.zone_id = z.zone_id
ORDER BY ub.billing_month DESC, room_name ASC;

-- ============================================
-- เพิ่มข้อมูลเริ่มต้น
-- ============================================

-- โซนต่างๆ
INSERT INTO zones (zone_name) VALUES
('A'), ('B'), ('C'), ('D'), ('E'), ('VIP');

-- สร้างห้องตัวอย่างสำหรับแต่ละโซน
-- Zone A (10 ห้อง)
INSERT INTO rooms (zone_id, room_number, status, water_rate, electricity_rate) VALUES
(1, 'A-01', 'available', 18.00, 5.00), (1, 'A-02', 'available', 18.00, 5.00),
(1, 'A-03', 'available', 18.00, 5.00), (1, 'A-04', 'available', 18.00, 5.00),
(1, 'A-05', 'available', 18.00, 5.00), (1, 'A-06', 'available', 18.00, 5.00),
(1, 'A-07', 'available', 18.00, 5.00), (1, 'A-08', 'available', 18.00, 5.00),
(1, 'A-09', 'available', 18.00, 5.00), (1, 'A-10', 'available', 18.00, 5.00);

-- Zone B (10 ห้อง)
INSERT INTO rooms (zone_id, room_number, status, water_rate, electricity_rate) VALUES
(2, 'B-01', 'available', 18.00, 5.00), (2, 'B-02', 'available', 18.00, 5.00),
(2, 'B-03', 'available', 18.00, 5.00), (2, 'B-04', 'available', 18.00, 5.00),
(2, 'B-05', 'available', 18.00, 5.00), (2, 'B-06', 'available', 18.00, 5.00),
(2, 'B-07', 'available', 18.00, 5.00), (2, 'B-08', 'available', 18.00, 5.00),
(2, 'B-09', 'available', 18.00, 5.00), (2, 'B-10', 'available', 18.00, 5.00);

-- Zone C (8 ห้อง)
INSERT INTO rooms (zone_id, room_number, status, water_rate, electricity_rate) VALUES
(3, 'C-01', 'available', 18.00, 5.00), (3, 'C-02', 'available', 18.00, 5.00),
(3, 'C-03', 'available', 18.00, 5.00), (3, 'C-04', 'available', 18.00, 5.00),
(3, 'C-05', 'available', 18.00, 5.00), (3, 'C-06', 'available', 18.00, 5.00),
(3, 'C-07', 'available', 18.00, 5.00), (3, 'C-08', 'available', 18.00, 5.00);

-- Zone D (8 ห้อง)
INSERT INTO rooms (zone_id, room_number, status, water_rate, electricity_rate) VALUES
(4, 'D-01', 'available', 18.00, 5.00), (4, 'D-02', 'available', 18.00, 5.00),
(4, 'D-03', 'available', 18.00, 5.00), (4, 'D-04', 'available', 18.00, 5.00),
(4, 'D-05', 'available', 18.00, 5.00), (4, 'D-06', 'available', 18.00, 5.00),
(4, 'D-07', 'available', 18.00, 5.00), (4, 'D-08', 'available', 18.00, 5.00);

-- Zone E (6 ห้อง)
INSERT INTO rooms (zone_id, room_number, status, water_rate, electricity_rate) VALUES
(5, 'E-01', 'available', 18.00, 5.00), (5, 'E-02', 'available', 18.00, 5.00),
(5, 'E-03', 'available', 18.00, 5.00), (5, 'E-04', 'available', 18.00, 5.00),
(5, 'E-05', 'available', 18.00, 5.00), (5, 'E-06', 'available', 18.00, 5.00);

-- Zone VIP (4 ห้อง)
INSERT INTO rooms (zone_id, room_number, status, water_rate, electricity_rate) VALUES
(6, 'VIP-01', 'available', 20.00, 6.00), (6, 'VIP-02', 'available', 20.00, 6.00),
(6, 'VIP-03', 'available', 20.00, 6.00), (6, 'VIP-04', 'available', 20.00, 6.00);

-- สร้าง Admin ตัวอย่าง (password: admin123)
INSERT INTO users (username, password, role, first_name, last_name, phone, emergency_contact) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator', 'System', '0812345678', '0898765432');

-- สร้าง User ตัวอย่าง (password: user123)
INSERT INTO users (username, password, role, first_name, last_name, phone, emergency_contact, has_room) VALUES
('john_doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'John', 'Doe', '0823456789', '0887654321', FALSE),
('jane_smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Jane', 'Smith', '0834567890', '0876543210', FALSE);

-- ============================================
-- สรุปโครงสร้างฐานข้อมูล
-- ============================================
-- ตารางทั้งหมด 6 ตาราง:
-- 1. zones - โซนห้อง
-- 2. rooms - ห้องพัก (มี water_rate, electricity_rate เป็นค่าเริ่มต้น)
-- 3. users - ผู้ใช้งาน
-- 4. contracts - สัญญาเช่า
-- 5. utility_bills - บิลค่าน้ำค่าไฟรายเดือน (ใหม่)
-- 6. payments - ประวัติการชำระเงิน (ใหม่)

-- View: v_utility_bills_detail - รวมข้อมูลบิลแบบละเอียด

SELECT 'Database created successfully!' as status,
       'Total tables: 6' as info1,
       'New tables: utility_bills, payments' as info2,
       'View created: v_utility_bills_detail' as info3;