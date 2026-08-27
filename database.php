<?php
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'localhost');
define('DB_USER', 'diozabeth');
define('DB_PASS', '000070');
define('DB_NAME', 'gym_system');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // Try to create the database if it doesn't exist
    $tempConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if (!$tempConn->connect_error) {
        $tempConn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tempConn->close();
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        // Initialize tables
        initDatabase($conn);
    } else {
        die("Connection failed: " . $tempConn->connect_error);
    }
} else {
    // Check if tables exist, if not initialize
    $result = $conn->query("SHOW TABLES LIKE 'admins'");
    if ($result->num_rows === 0) {
        initDatabase($conn);
    }
}

function initDatabase($conn) {
    $sql = "
    CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('superadmin','admin','staff') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

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

    CREATE TABLE IF NOT EXISTS walkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id VARCHAR(20) NOT NULL,
        payment_amount DECIMAL(10,2) NOT NULL,
        visit_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
    ) ENGINE=InnoDB;

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

    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id VARCHAR(20) NOT NULL,
        member_type ENUM('walkin','subscription') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

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

    CREATE TABLE IF NOT EXISTS price_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('walkin','subscription') NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        label VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE IF NOT EXISTS supplements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

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
    ";

    $conn->multi_query($sql);
    while ($conn->more_results()) { $conn->next_result(); }

    // Insert default admin - must set variables BEFORE bind_param
    $defaultPassword = password_hash('diozabethgymfitness468', PASSWORD_DEFAULT);
    $u = 'diozabeth';
    $fullName = 'Diozabeth Admin';
    $role = 'superadmin';
    $stmt = $conn->prepare("INSERT IGNORE INTO admins (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $u, $defaultPassword, $fullName, $role);
    $stmt->execute();
    $stmt->close();

    // Insert default prices
    $conn->query("INSERT IGNORE INTO price_settings (id, type, price, label) VALUES 
        (1, 'walkin', 100.00, '₱100 - Standard'),
        (2, 'walkin', 150.00, '₱150 - Premium'),
        (3, 'subscription', 1200.00, '₱1200 - 1 Month'),
        (4, 'subscription', 1500.00, '₱1500 - 1 Month Plus'),
        (5, 'subscription', 1800.00, '₱1800 - Premium Month')
    ");
}

// Ensure default superadmin exists (only inserts if missing — no password_verify on every load)
$checkAdmin = $conn->query("SELECT id FROM admins WHERE username = 'diozabeth' LIMIT 1");
if ($checkAdmin && $checkAdmin->num_rows === 0) {
    $fixHash = password_hash('diozabethgymfitness468', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password, full_name, role) VALUES ('diozabeth', '$fixHash', 'Diozabeth Admin', 'superadmin')");
}

// Ensure staff_salaries table exists
$conn->query("CREATE TABLE IF NOT EXISTS staff_salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    staff_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    month DATE NOT NULL COMMENT 'First day of the salary month e.g. 2026-03-01',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// Ensure Google Drive backup tables exist
$conn->query("CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(200) NOT NULL,
    file_id VARCHAR(200) NULL COMMENT 'Google Drive file ID',
    folder_id VARCHAR(200) NULL,
    file_size INT NOT NULL DEFAULT 0 COMMENT 'bytes',
    status ENUM('success','failed') NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    created_by VARCHAR(100) NULL,
    backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS backup_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Safely migrate backup_logs — add any missing columns (no AFTER clause to avoid errors)
$_bkCols = [];
$_bkCr = $conn->query("SHOW COLUMNS FROM backup_logs");
if ($_bkCr) while ($_c = $_bkCr->fetch_assoc()) $_bkCols[] = $_c['Field'];
if (!in_array('file_id',      $_bkCols)) $conn->query("ALTER TABLE backup_logs ADD COLUMN file_id VARCHAR(200) NULL");
if (!in_array('folder_id',    $_bkCols)) $conn->query("ALTER TABLE backup_logs ADD COLUMN folder_id VARCHAR(200) NULL");
if (!in_array('file_size',    $_bkCols)) $conn->query("ALTER TABLE backup_logs ADD COLUMN file_size INT NOT NULL DEFAULT 0");
if (!in_array('error_message',$_bkCols)) $conn->query("ALTER TABLE backup_logs ADD COLUMN error_message TEXT NULL");
if (!in_array('created_by',   $_bkCols)) $conn->query("ALTER TABLE backup_logs ADD COLUMN created_by VARCHAR(100) NULL");
if (!in_array('backup_type',  $_bkCols)) $conn->query("ALTER TABLE backup_logs ADD COLUMN backup_type ENUM('drive','local') NOT NULL DEFAULT 'drive'");
unset($_bkCols, $_bkCr, $_c);

// Safely migrate supplements table columns (compatible with older MySQL versions)
$suppCols = [];
$suppColRes = $conn->query("SHOW COLUMNS FROM supplements");
if ($suppColRes) { while($c = $suppColRes->fetch_assoc()) $suppCols[] = $c['Field']; }
if (!in_array('brand', $suppCols))
    $conn->query("ALTER TABLE supplements ADD COLUMN brand VARCHAR(100) NULL AFTER name");
if (!in_array('category', $suppCols))
    $conn->query("ALTER TABLE supplements ADD COLUMN category ENUM('Protein','Pre-workout','Creatine','Vitamins','BCAAs','Weight Gainer','Fat Burner','Other') DEFAULT 'Other' AFTER brand");
if (!in_array('stock_quantity', $suppCols))
    $conn->query("ALTER TABLE supplements ADD COLUMN stock_quantity INT DEFAULT 0 AFTER category");
?>