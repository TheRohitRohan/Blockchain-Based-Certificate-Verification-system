-- Certificate Verification System Database Schema

CREATE DATABASE IF NOT EXISTS certificate_db;
USE certificate_db;

-- Universities table 
CREATE TABLE IF NOT EXISTS universities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    address TEXT,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    wallet_address VARCHAR(42),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'university', 'student') NOT NULL,
    full_name VARCHAR(255),
    university_id INT NULL,
    wallet_address VARCHAR(42) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_university (university_id),
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(100) UNIQUE NOT NULL,
    university_id INT NOT NULL,
    date_of_birth DATE,
    enrollment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_university (university_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_id VARCHAR(255) UNIQUE NOT NULL,
    student_id INT NOT NULL,
    university_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    degree_type VARCHAR(100),
    issue_date DATE NOT NULL,
    certificate_hash VARCHAR(255) NOT NULL,
    blockchain_tx_hash VARCHAR(255),
    pdf_path VARCHAR(500),
    qr_code_path VARCHAR(500),
    status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    revoked_at TIMESTAMP NULL,
    revoked_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_course (student_id, course_name),
    INDEX idx_certificate_id (certificate_id),
    INDEX idx_student (student_id),
    INDEX idx_university (university_id),
    INDEX idx_hash (certificate_hash),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verification logs table
CREATE TABLE IF NOT EXISTS verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_ref_id INT NULL,
    certificate_id VARCHAR(255) NOT NULL,
    verifier_ip VARCHAR(45),
    verification_method ENUM('certificate_id', 'hash', 'qr_code', 'upload') NOT NULL,
    verification_result ENUM('valid', 'invalid', 'revoked', 'not_found') NOT NULL,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (certificate_ref_id) REFERENCES certificates(id) ON DELETE SET NULL,
    INDEX idx_certificate_ref (certificate_ref_id),
    INDEX idx_certificate (certificate_id),
    INDEX idx_verified_at (verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES
('admin', 'admin@certificate-system.com', '$2y$10$tCmOdwc0pZQ1HoHwnYh4MOXGE9HBPHmB/gd.luFzNkEahWdsWRQAa', 'admin', 'System Administrator');

