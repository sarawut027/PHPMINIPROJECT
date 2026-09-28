-- Dispensary POS Database Schema
-- ออกแบบสำหรับระบบ Dispensary POS รองรับ Class Diagram และความต้องการของโปรเจกต์

CREATE DATABASE IF NOT EXISTS `dispensary_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dispensary_db`;

-- 1. ตาราง users (สำหรับ Admin และ Staff)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตาราง products (รองรับ Abstract Product, CannabisFlower, CannabisOil, CannabisPlant)
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('flower', 'oil', 'plant') NOT NULL,
    `base_price` DECIMAL(10, 2) NOT NULL,
    
    -- คุณสมบัติเฉพาะของ CannabisFlower
    `strain_type` VARCHAR(50) NULL, -- Sativa, Indica, Hybrid
    `stock_grams` DECIMAL(10, 2) NULL DEFAULT 0.00, -- สต็อกน้ำหนักเป็นกรัม (รองรับทศนิยม เช่น 3.5g)
    
    -- คุณสมบัติเฉพาะของ CannabisOil
    `extraction_method` VARCHAR(100) NULL, -- e.g. Supercritical CO2, Ethanol Extract
    `stock_bottles` INT NULL DEFAULT 0, -- สต็อกเป็นขวด
    
    -- คุณสมบัติเฉพาะของ CannabisPlant
    `age_weeks` INT NULL DEFAULT 0, -- อายุต้นพันธุ์ (สัปดาห์)
    `stock_pieces` INT NULL DEFAULT 0, -- สต็อกเป็นต้น
    
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตาราง orders (บันทึกออเดอร์และการตรวจอายุ)
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `customer_birthdate` DATE NOT NULL,
    `customer_age` INT NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `discount_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(10, 2) NOT NULL,
    `cash_received` DECIMAL(10, 2) NOT NULL,
    `change_returned` DECIMAL(10, 2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ตาราง order_items (รายการสินค้าในแต่ละออเดอร์)
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `product_type` VARCHAR(50) NOT NULL,
    `quantity` DECIMAL(10, 2) NOT NULL,
    `unit_label` VARCHAR(20) NOT NULL, -- กรัม, ขวด, ต้น
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    `discount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `final_price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลตัวอย่างเริ่มต้น (Seed Data)
-- รหัสผ่านเริ่มต้นคือ admin123 และ staff123 (ใช้ password_hash BCRYPT หรือ fallback plain text)
INSERT INTO `users` (`username`, `password`, `full_name`, `role`) VALUES
('admin', '$2y$10$3c8tSg0iP5ZkJ3wY1d6c8e3k8d4l9m0n1o2p3q4r5s6t7u8v9w0xy', 'ผู้จัดการระบบ (Admin)', 'admin'),
('staff', '$2y$10$3c8tSg0iP5ZkJ3wY1d6c8e3k8d4l9m0n1o2p3q4r5s6t7u8v9w0xy', 'พนักงานขาย (Staff)', 'staff')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- ข้อมูลตัวอย่างสินค้า 3 ประเภท
INSERT INTO `products` (`name`, `type`, `base_price`, `strain_type`, `stock_grams`, `extraction_method`, `stock_bottles`, `age_weeks`, `stock_pieces`, `description`) VALUES
('OG Kush Flower Premium', 'flower', 450.00, 'Hybrid', 125.50, NULL, NULL, NULL, NULL, 'ช่อดอกสายพันธุ์คลาสสิก กลิ่นสนและซิตรัส อบแห้งและบ่มอย่างดี'),
('Thai Sticky Sativa', 'flower', 350.00, 'Sativa', 18.00, NULL, NULL, NULL, NULL, 'หางกระรอกไทยแท้ สายพันธุ์ซาติว่า กลิ่นหอมสดชื่น'),
('Granddaddy Purple', 'flower', 500.00, 'Indica', 5.20, NULL, NULL, NULL, NULL, 'อินดิก้าสีม่วงเอกลักษณ์ สต็อกใกล้หมด กลิ่นเบอร์รี่และองุ่น'),
('Full Spectrum CBD Oil 1000mg', 'oil', 1200.00, NULL, NULL, 'Supercritical CO2 Extraction', 15, NULL, NULL, 'น้ำมันสกัดสูตรเข้มข้น สกัดเย็นบริสุทธิ์ ไร้สารเคมีตกค้าง'),
('Pure THC Distillate Drops', 'oil', 1500.00, NULL, NULL, 'Ethanol Multi-stage Distillation', 8, NULL, NULL, 'สารสกัดหยดใต้ลิ้น ความบริสุทธิ์สูงมาตรฐานห้องปฏิบัติการ'),
('White Widow Clone (ต้นแม่พันธุ์)', 'plant', 850.00, NULL, NULL, NULL, NULL, 4, 12, 'ต้นกล้าตัดกิ่งพร้อมลงปลูก ระบบรากแข็งแรง สมบูรณ์'),
('Amnesia Haze Young Plant', 'plant', 950.00, NULL, NULL, NULL, NULL, 6, 2, 'ต้นพันธุ์รุ่นเจริญเติบโต พร้อมออกดอกใน 4 สัปดาห์');
