-- Diozabeth Fitness Gym Management System
-- Database Setup SQL
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS gym_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gym_system;

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('superadmin','admin','staff') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Members table
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Male','Female','Other') DEFAULT 'Male',
    date_of_birth DATE NULL,
    age INT NULL,
    address TEXT NULL,
    phone VARCHAR(20) NULL,
    member_type ENUM('walkin','subscription') NOT NULL,
    status ENUM('active','inactive','frozen','expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','expired','frozen') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Walk-ins table
CREATE TABLE IF NOT EXISTS walkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    visit_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Attendance logs table
CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    member_name VARCHAR(100) NOT NULL,
    member_type ENUM('walkin','subscription') NOT NULL,
    time_in DATETIME NOT NULL,
    access_result ENUM('granted','denied') DEFAULT 'granted',
    status ENUM('active','inactive','frozen') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    member_type ENUM('walkin','subscription') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Freeze records table
CREATE TABLE IF NOT EXISTS freeze_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    freeze_date DATE NOT NULL,
    unfreeze_date DATE NULL,
    reason TEXT NULL,
    status ENUM('active','ended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Price settings table
CREATE TABLE IF NOT EXISTS price_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('walkin','subscription') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    label VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account (password: diozabethgymfitness468)
INSERT IGNORE INTO admins (username, password, full_name, role) VALUES 
('diozabeth', '$2y$12$f0ZJYWs4FWgThCb3AGorve9.Asc6wHuaxmMyxFVp71u5JokDm0Fyu', 'Diozabeth Admin', 'superadmin');

-- Default prices
INSERT IGNORE INTO price_settings (id, type, price, label) VALUES 
(1, 'walkin', 100.00, '₱100 - Standard'),
(2, 'walkin', 150.00, '₱150 - Premium'),
(3, 'subscription', 1200.00, '₱1200 - 1 Month'),
(4, 'subscription', 1500.00, '₱1500 - 1 Month Plus'),
(5, 'subscription', 1800.00, '₱1800 - Premium Month');

-- Supplements table
CREATE TABLE IF NOT EXISTS supplements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NULL,
    category ENUM('Protein','Pre-workout','Creatine','Vitamins','BCAAs','Weight Gainer','Fat Burner','Other') DEFAULT 'Other',
    stock_quantity INT DEFAULT 0,
    price DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Supplement sales table
CREATE TABLE IF NOT EXISTS supplement_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(30) UNIQUE NOT NULL,
    member_name VARCHAR(100) NOT NULL,
    user_id VARCHAR(30) NULL,
    supplement_id INT NULL,
    supplement_name VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NULL,
    category VARCHAR(50) NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','Card','GCash','Maya','Others') DEFAULT 'Cash',
    payment_status ENUM('Paid','Pending') DEFAULT 'Paid',
    staff_name VARCHAR(100) NULL,
    notes TEXT NULL,
    sale_date DATE NOT NULL,
    sale_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Backup System Tables (added v2 — OAuth 2.0 personal Drive)
-- ============================================================

CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(200) NOT NULL,
    file_id VARCHAR(200) NULL COMMENT 'Google Drive file ID',
    folder_id VARCHAR(200) NULL,
    file_size INT NOT NULL DEFAULT 0 COMMENT 'size in bytes',
    status ENUM('success','failed') NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    created_by VARCHAR(100) NULL,
    backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
