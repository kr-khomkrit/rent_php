-- สร้างฐานข้อมูลระบบเช่าห้องพัก
-- Rental Room Management System Database

CREATE DATABASE IF NOT EXISTS rental_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_system;

-- ตารางโซน
CREATE TABLE zones (
    zone_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ตารางห้อง
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    water_rate DECIMAL(10,2) DEFAULT 0.00,
    electricity_rate DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id),
    UNIQUE KEY unique_room (zone_id, room_number)
);

-- ตารางผู้ใช้
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
);

-- ตารางสัญญา
CREATE TABLE contracts (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    rental_price DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    contract_terms TEXT,
    status ENUM('active', 'expired', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
);

-- เพิ่มข้อมูลเริ่มต้น
-- โซนต่างๆ
INSERT INTO zones (zone_name) VALUES
('A'), ('B'), ('C'), ('D'), ('E'), ('VIP');

-- สร้างห้องตัวอย่างสำหรับแต่ละโซน
-- Zone A (10 ห้อง)
INSERT INTO rooms (zone_id, room_number, status) VALUES
(1, 'A-01', 'available'), (1, 'A-02', 'available'), (1, 'A-03', 'available'),
(1, 'A-04', 'available'), (1, 'A-05', 'available'), (1, 'A-06', 'available'),
(1, 'A-07', 'available'), (1, 'A-08', 'available'), (1, 'A-09', 'available'),
(1, 'A-10', 'available');

-- Zone B (10 ห้อง)
INSERT INTO rooms (zone_id, room_number, status) VALUES
(2, 'B-01', 'available'), (2, 'B-02', 'available'), (2, 'B-03', 'available'),
(2, 'B-04', 'available'), (2, 'B-05', 'available'), (2, 'B-06', 'available'),
(2, 'B-07', 'available'), (2, 'B-08', 'available'), (2, 'B-09', 'available'),
(2, 'B-10', 'available');

-- Zone C (8 ห้อง)
INSERT INTO rooms (zone_id, room_number, status) VALUES
(3, 'C-01', 'available'), (3, 'C-02', 'available'), (3, 'C-03', 'available'),
(3, 'C-04', 'available'), (3, 'C-05', 'available'), (3, 'C-06', 'available'),
(3, 'C-07', 'available'), (3, 'C-08', 'available');

-- Zone D (8 ห้อง)
INSERT INTO rooms (zone_id, room_number, status) VALUES
(4, 'D-01', 'available'), (4, 'D-02', 'available'), (4, 'D-03', 'available'),
(4, 'D-04', 'available'), (4, 'D-05', 'available'), (4, 'D-06', 'available'),
(4, 'D-07', 'available'), (4, 'D-08', 'available');

-- Zone E (6 ห้อง)
INSERT INTO rooms (zone_id, room_number, status) VALUES
(5, 'E-01', 'available'), (5, 'E-02', 'available'), (5, 'E-03', 'available'),
(5, 'E-04', 'available'), (5, 'E-05', 'available'), (5, 'E-06', 'available');

-- Zone VIP (4 ห้อง)
INSERT INTO rooms (zone_id, room_number, status) VALUES
(6, 'VIP-01', 'available'), (6, 'VIP-02', 'available'),
(6, 'VIP-03', 'available'), (6, 'VIP-04', 'available');

-- สร้าง Admin ตัวอย่าง (password: admin123)
INSERT INTO users (username, password, role, first_name, last_name, phone, emergency_contact) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator', 'System', '0812345678', '0898765432');

-- สร้าง User ตัวอย่าง (password: user123)
INSERT INTO users (username, password, role, first_name, last_name, phone, emergency_contact, has_room) VALUES
('john_doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'John', 'Doe', '0823456789', '0887654321', FALSE),
('jane_smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Jane', 'Smith', '0834567890', '0876543210', FALSE);