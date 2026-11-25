-- SocietyHub Complete Production Schema & Seed Data (MySQL / MariaDB)

-- 1. Roles Table
CREATE TABLE IF NOT EXISTS sms_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_roles (id, role_name, description) VALUES
    (1, 'super_admin', 'Super Administrator'),
    (2, 'committee', 'Society Committee Member'),
    (3, 'resident', 'Resident (Owner / Tenant)'),
    (4, 'guard', 'Gate Security Guard');

-- 2. Societies Table (Multi-Tenant Root)
CREATE TABLE IF NOT EXISTS sms_societies (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_name VARCHAR(150) NOT NULL,
    society_code VARCHAR(32) NOT NULL UNIQUE,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NULL,
    zip_code VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_societies (id, society_name, society_code, address, city, state, zip_code) VALUES
(1, 'Sunshine Residency', '9305C6F2', '100 Palm Avenue', 'Pune', 'Maharashtra', '411001');

-- 3. Core Users Table
CREATE TABLE IF NOT EXISTS sms_users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('pending', 'active', 'rejected', 'suspended', 'deactivated') DEFAULT 'pending',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_society_status_role (society_id, status, role_id),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES sms_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo Users (Password for all: 'password')
INSERT IGNORE INTO sms_users (id, society_id, email, password_hash, role_id, status) VALUES
(1, 1, 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active'),
(2, 1, 'committee@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'active'),
(3, 1, 'resident@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'active'),
(4, 1, 'guard@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'active'),
(5, 1, 'yarun520@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'active'),
(6, 1, 'yarun037@gmail.com', '$2y$10$fCzPYbrySVLnHHFSHj7QgOCXAcv0Oh0Iw6crtklGFVEhf/TAb6OWu', 3, 'active'),
(7, 1, 'bosdikee@gmail.com', '$2y$10$iAiYKIlLPNt4UoXY2b6Xu.fpfo/MDWU09WutnR0/DVXfO8Zut4QE2', 3, 'active'),
(8, 1, 'laundarirani@gmail.com', '$2y$10$Z2uyhQTQLDEzyt6BzIm7t.Ly16ON0D39uav04mM9AxhMIPrpq7gZK', 3, 'active'),
(9, 1, 'ohhyehhbaby@ohh.com', '$2y$10$Z0fvr8Zg7ox58t7EVAnDVe2PIaXp7xOSREooyfNRxC97nnxiQAW3.', 3, 'active');

-- 4. User Profiles Table
CREATE TABLE IF NOT EXISTS sms_user_profiles (
    user_id BIGINT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) UNIQUE,
    emergency_contact VARCHAR(20),
    government_id_type VARCHAR(50),
    government_id_hash VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_user_profiles (user_id, first_name, last_name, phone_number) VALUES
(1, 'Super', 'Admin', '9876543210'),
(2, 'Sarah', 'Committee', '9876543211'),
(3, 'John', 'Resident', '9876543212'),
(4, 'Ramesh', 'Guard', '9876543213'),
(5, 'Arun', 'Yadav', '9026521138'),
(6, 'Fateh', 'Singh', '+91 9119810565'),
(7, 'Bosdiee', 'Kee', '982536'),
(8, 'Laundri', 'Rani', '2580'),
(9, 'BABY', '', '258');

-- 5. Unit / Flat Mapping Table
CREATE TABLE IF NOT EXISTS sms_user_units (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    occupancy_type ENUM('owner', 'tenant') NOT NULL,
    move_in_date DATE NOT NULL,
    move_out_date DATE NULL,
    is_current_occupant BOOLEAN DEFAULT TRUE,
    INDEX idx_units_user_current (user_id, is_current_occupant),
    INDEX idx_units_society_unit (society_id, unit_number),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_user_units (id, society_id, user_id, unit_number, occupancy_type, move_in_date, is_current_occupant) VALUES
(1, 1, 3, '101', 'owner', '2024-01-01', 1),
(13, 1, 5, 'A-101', 'owner', '2026-08-06', 1),
(14, 1, 6, 'B-801', 'tenant', '2026-08-06', 1),
(15, 1, 7, 'A-32', 'owner', '2026-08-06', 1),
(16, 1, 8, 'C-H', 'owner', '2026-08-06', 1),
(17, 1, 9, 'B-Ty', 'owner', '2026-08-06', 1);

-- 6. Society Notices
CREATE TABLE IF NOT EXISTS sms_notices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    created_by BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notices_society_created (society_id, created_at),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_notices (id, society_id, created_by, title, content) VALUES
(1, 1, 1, 'Welcome to SocietyHub', 'Welcome to Sunshine Residency! Please explore the resident portal to manage visitors, view maintenance invoices, and submit helpdesk complaints.');

-- 7. Maintenance Invoices
CREATE TABLE IF NOT EXISTS sms_invoices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    resident_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Unpaid', 'Paid', 'Overdue') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_invoices_society_due (society_id, due_date),
    INDEX idx_invoices_resident_status (resident_id, status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_invoices (id, society_id, resident_id, title, amount, due_date, status) VALUES
(1, 1, 3, 'Maintenance Charges - November 2026', 2500.00, '2026-11-30', 'Unpaid');

-- 8. Helpdesk Complaints
CREATE TABLE IF NOT EXISTS sms_complaints (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    resident_id BIGINT NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('Open', 'In Progress', 'Resolved') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_complaints_society_created (society_id, created_at),
    INDEX idx_complaints_resident_created (resident_id, created_at),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Gate Visitors Log
CREATE TABLE IF NOT EXISTS sms_visitors (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    resident_id BIGINT NOT NULL,
    guard_id BIGINT NOT NULL,
    visitor_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NULL,
    vehicle_number VARCHAR(30) NULL,
    purpose VARCHAR(100) NOT NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM('Pending', 'Entered', 'Denied', 'Exited') DEFAULT 'Pending',
    entry_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    exit_time DATETIME NULL,
    INDEX idx_visitors_society_entry (society_id, entry_time),
    INDEX idx_visitors_resident_status (resident_id, status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (guard_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Pre-Approved Guest Pass PINs
CREATE TABLE IF NOT EXISTS sms_visitor_passes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id BIGINT NOT NULL,
    resident_id BIGINT NOT NULL,
    visitor_name VARCHAR(100) NOT NULL,
    pin_code CHAR(6) NOT NULL,
    valid_date DATE NOT NULL,
    status ENUM('Active', 'Used', 'Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_passes_lookup (society_id, pin_code, status, valid_date),
    INDEX idx_passes_resident (resident_id, created_at),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Browser Web Push Subscriptions
CREATE TABLE IF NOT EXISTS sms_user_push_subscriptions (
    user_id BIGINT PRIMARY KEY,
    subscription JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Resident Emergency Security SOS Alerts
CREATE TABLE IF NOT EXISTS sms_emergency_alerts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    resident_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    alert_type ENUM('Security Threat', 'Medical Emergency', 'Fire Alert', 'General SOS') NOT NULL,
    status ENUM('Active', 'Acknowledged', 'Resolved') DEFAULT 'Active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by BIGINT NULL,
    acknowledged_at DATETIME NULL,
    INDEX idx_emergency_society_status (society_id, status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Resident Delivery Pre-Approvals & Instructions
CREATE TABLE IF NOT EXISTS sms_delivery_preapprovals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    resident_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    company VARCHAR(50) NOT NULL,
    instruction ENUM('Allow Entry', 'Leave at Gate / Guard', 'Leave at Doorstep') NOT NULL DEFAULT 'Leave at Gate / Guard',
    valid_until DATETIME NOT NULL,
    status ENUM('Active', 'Fulfilled', 'Cancelled') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_delivery_lookup (society_id, unit_number, status, valid_until),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Persistent Session Tokens (Biometric / Remember-Me Authentication)
CREATE TABLE IF NOT EXISTS sms_persistent_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    user_agent VARCHAR(255) NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_lookup (token_hash, expires_at),
    FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Resident Vehicles & Parking Slots
CREATE TABLE IF NOT EXISTS sms_vehicles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    resident_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    vehicle_type ENUM('2_wheeler', '4_wheeler') NOT NULL,
    vehicle_number VARCHAR(30) NOT NULL,
    parking_slot VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicles_lookup (society_id, vehicle_number),
    INDEX idx_vehicles_unit (society_id, unit_number),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_vehicles (id, society_id, resident_id, unit_number, vehicle_type, vehicle_number, parking_slot) VALUES
(1, 1, 3, '101', '4_wheeler', 'MH12AB1234', 'P-101');

-- 16. Gate Security Parcel Locker
CREATE TABLE IF NOT EXISTS sms_gate_parcels (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    resident_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    courier_company VARCHAR(50) NOT NULL,
    recipient_name VARCHAR(100) NULL,
    received_by_guard_id BIGINT NOT NULL,
    status ENUM('At Gate', 'Collected') DEFAULT 'At Gate',
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    collected_at DATETIME NULL,
    INDEX idx_parcels_unit_status (society_id, unit_number, status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by_guard_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Security & Administration Audit Trail
CREATE TABLE IF NOT EXISTS sms_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    user_id BIGINT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_lookup (society_id, created_at),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
