-- Lost and Found Search System Database Schema

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    school_id VARCHAR(50) DEFAULT NULL,
    role ENUM('Student', 'Staff', 'Admin') DEFAULT 'Student',
    password_hash VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id VARCHAR(50) NOT NULL UNIQUE,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    date_event DATE DEFAULT NULL,
    location VARCHAR(150) DEFAULT NULL,
    photo_url TEXT DEFAULT NULL,
    status ENUM('pending', 'confirmed', 'rejected', 'claimed', 'returned') DEFAULT 'pending',
    report_type ENUM('lost', 'found') DEFAULT 'lost',
    user_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_id VARCHAR(50) NOT NULL UNIQUE,
    report_id INT DEFAULT NULL,
    report_code VARCHAR(50) DEFAULT NULL,
    item_name VARCHAR(150) DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    school_id VARCHAR(50) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    staff_feedback TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'resolved') DEFAULT 'unread',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data
INSERT INTO users (full_name, email, school_id, role, password_hash)
VALUES ('Sample User', 'user@school.edu', '000000', 'Student', '$2y$10$abcdefghijklmnopqrstuvwxyz12345678901234567890')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

INSERT INTO reports (report_id, item_name, category, description, date_event, location, photo_url, status, report_type)
VALUES 
('R-10102026-#001', 'Black Leather Wallet', 'personal-belongings', 'Leather wallet with school ID and cards.', '2026-10-10', 'Main Library 2nd Floor', '/assets/category/personal-belongings.png', 'confirmed', 'lost'),
('R-10082026-#002', 'Blue Grid Notebook', 'school-supplies', 'Math notes inside with name tag.', '2026-10-08', 'Cafeteria Table 4', '/assets/category/school-supplies.png', 'confirmed', 'found'),
('R-10052026-#003', 'Wireless Earbuds', 'electronics', 'White charging case, brand sound core.', '2026-10-05', 'Gymnasium Bleachers', '/assets/category/electronics.png', 'returned', 'found')
ON DUPLICATE KEY UPDATE item_name=VALUES(item_name);

INSERT INTO claims (claim_id, report_code, item_name, full_name, email, school_id, status, staff_feedback)
VALUES 
('CLM-001', 'R-10102026-#001', 'Sample Wallet', 'Sample User', 'user@school.edu', '000000', 'pending', 'Waiting for verification.'),
('CLM-002', 'R-10082026-#002', 'Sample Notebook', 'Sample User', 'user@school.edu', '000000', 'approved', 'Visit the Lost and Found Office to collect the item.')
ON DUPLICATE KEY UPDATE claim_id=VALUES(claim_id);
