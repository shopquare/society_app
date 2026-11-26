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
    visitor_tag VARCHAR(50) NOT NULL DEFAULT 'Guest',
    phone_number VARCHAR(20) NULL,
    vehicle_number VARCHAR(30) NULL,
    purpose VARCHAR(100) NOT NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM('Pending', 'Entered', 'Denied', 'Exited') DEFAULT 'Pending',
    entry_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    exit_time DATETIME NULL,
    INDEX idx_visitors_society_entry (society_id, entry_time),
    INDEX idx_visitors_resident_status (resident_id, status),
    INDEX idx_visitors_tag (society_id, visitor_tag),
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
    valid_until DATETIME NULL,
    status ENUM('Active', 'Used', 'Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_passes_lookup (society_id, pin_code, status, valid_until),
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
    alert_type VARCHAR(60) NOT NULL DEFAULT 'General SOS',
    status ENUM('Active', 'Acknowledged', 'Responding', 'Resolved', 'Cancelled') DEFAULT 'Active',
    description TEXT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    notes TEXT NULL,
    assigned_to BIGINT NULL,
    resolved_by BIGINT NULL,
    resolved_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason VARCHAR(255) NULL,
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

-- 18. Society Facilities & Amenities
CREATE TABLE IF NOT EXISTS sms_society_facilities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    facility_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    location VARCHAR(100) NULL,
    capacity INT DEFAULT 20,
    open_time TIME DEFAULT '06:00:00',
    close_time TIME DEFAULT '22:00:00',
    rules TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_facilities_society (society_id, is_active),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_society_facilities (id, society_id, facility_name, description, location, capacity, open_time, close_time) VALUES
(1, 1, 'Clubhouse & Banquet', 'Multi-purpose hall for community gatherings, birthday celebrations and events', 'Ground Floor, Block A', 100, '08:00:00', '23:00:00'),
(2, 1, 'Fitness Gymnasium', 'Fully equipped gym with cardio, weights and strength equipment', '1st Floor, Clubhouse', 30, '06:00:00', '22:00:00'),
(3, 1, 'Swimming Pool', 'Olympic standard pool with baby splash pool and certified lifeguard', 'Central Garden Area', 40, '06:30:00', '20:30:00'),
(4, 1, 'Badminton Court', 'Indoor wooden flooring court with night lighting', 'Sports Complex', 8, '06:00:00', '22:00:00');

CREATE TABLE IF NOT EXISTS sms_facility_bookings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    facility_id BIGINT NOT NULL,
    resident_id BIGINT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('Confirmed', 'Cancelled') DEFAULT 'Confirmed',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bookings_lookup (society_id, facility_id, booking_date),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (facility_id) REFERENCES sms_society_facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Resident Family Members
CREATE TABLE IF NOT EXISTS sms_family_members (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    resident_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    relation VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_family_resident (society_id, resident_id),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Domestic Daily Staff & Association
CREATE TABLE IF NOT EXISTS sms_domestic_staff (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    staff_name VARCHAR(100) NOT NULL,
    staff_type VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NULL,
    photo_path VARCHAR(255) NULL,
    pass_code VARCHAR(20) NULL,
    rating DECIMAL(2, 1) DEFAULT 4.5,
    is_verified TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_society (society_id, staff_type),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_domestic_staff (id, society_id, staff_name, staff_type, phone_number, is_verified) VALUES
(1, 1, 'Sunita Devi', 'Maid', '9871122334', 1),
(2, 1, 'Rajesh Kumar', 'Driver', '9872233445', 1),
(3, 1, 'Meena Sharma', 'Cook', '9873344556', 1),
(4, 1, 'Kailash Nath', 'Cleaner', '9874455667', 1);

CREATE TABLE IF NOT EXISTS sms_resident_staff_mapping (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    resident_id BIGINT NOT NULL,
    staff_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resident_staff (resident_id, staff_id),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES sms_domestic_staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Society Marketplace (Buy & Sell)
CREATE TABLE IF NOT EXISTS sms_marketplace_listings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    seller_id BIGINT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    item_condition ENUM('New', 'Like New', 'Good', 'Used') NOT NULL DEFAULT 'Good',
    contact_preference ENUM('Phone', 'WhatsApp', 'In-Person') NOT NULL DEFAULT 'Phone',
    contact_number VARCHAR(20) NULL,
    status ENUM('Active', 'Sold', 'Archived') DEFAULT 'Active',
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_mkt_society_status (society_id, status, category),
    INDEX idx_mkt_seller (seller_id, created_at),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_marketplace_images (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mkt_images_listing (listing_id),
    FOREIGN KEY (listing_id) REFERENCES sms_marketplace_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_marketplace_saved (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    listing_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_saved (user_id, listing_id),
    FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES sms_marketplace_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_marketplace_reports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    listing_id BIGINT NOT NULL,
    reporter_id BIGINT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Dismissed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reports_lookup (society_id, status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES sms_marketplace_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Monetization & Advertisements
CREATE TABLE IF NOT EXISTS sms_ad_settings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    ads_enabled TINYINT(1) DEFAULT 1,
    adsense_publisher_id VARCHAR(100) NULL,
    adsense_slot_id VARCHAR(100) NULL,
    allow_resident_ads TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ad_settings_society (society_id),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_ad_settings (id, society_id, ads_enabled, allow_resident_ads) VALUES
(1, 1, 1, 1);

CREATE TABLE IF NOT EXISTS sms_advertisement_packages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(100) NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_advertisement_packages (id, package_name, duration_days, price, description, is_active) VALUES
(1, 'Weekly Spotlight Banner', 7, 499.00, 'Display your business ad banner on the Resident Dashboard for 7 days', 1),
(2, 'Monthly Premium Campaign', 30, 1499.00, 'Featured banner across Resident Dashboard, Marketplace & Notice Board for 30 days', 1),
(3, 'Quarterly Partner Sponsorship', 90, 3999.00, 'Maximum exposure across all community pages for 3 months with priority placement', 1);

CREATE TABLE IF NOT EXISTS sms_advertisements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NULL,
    advertiser_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(100) NULL,
    company_logo_path VARCHAR(255) NULL,
    user_id BIGINT NULL,
    ad_type ENUM('Google_AdSense', 'Custom_Banner', 'Resident_Paid', 'Company_Sponsored') NOT NULL DEFAULT 'Company_Sponsored',
    headline VARCHAR(150) NOT NULL,
    body_text TEXT NULL,
    banner_image_path VARCHAR(255) NULL,
    target_url VARCHAR(500) NULL,
    contact_phone VARCHAR(25) NULL,
    category VARCHAR(50) DEFAULT 'Food',
    offer_badge VARCHAR(50) NULL,
    cta_text VARCHAR(50) DEFAULT 'View Offer',
    display_locations VARCHAR(255) DEFAULT 'dashboard_banner,carousel,offers_deals',
    target_audience ENUM('all', 'owners', 'tenants') DEFAULT 'all',
    priority INT DEFAULT 5,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Draft', 'Pending_Approval', 'Active', 'Paused', 'Expired', 'Rejected') DEFAULT 'Active',
    impressions_count INT DEFAULT 0,
    clicks_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ads_lookup (society_id, status, start_date, end_date),
    INDEX idx_ads_category (society_id, category, status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_advertisement_analytics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    advertisement_id BIGINT NOT NULL,
    society_id INT NOT NULL,
    event_type ENUM('impression', 'click') NOT NULL,
    placement VARCHAR(50) NULL,
    user_id BIGINT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ad_analytics_event (advertisement_id, event_type, created_at),
    INDEX idx_ad_analytics_society (society_id, created_at),
    FOREIGN KEY (advertisement_id) REFERENCES sms_advertisements(id) ON DELETE CASCADE,
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_advertisement_payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    ad_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    package_id BIGINT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('UPI_Transfer', 'Bank_Transfer', 'Cash', 'Cheque') NOT NULL,
    transaction_ref VARCHAR(100) NOT NULL,
    payment_status ENUM('Pending_Approval', 'Approved', 'Rejected') DEFAULT 'Pending_Approval',
    approved_by BIGINT NULL,
    approved_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ad_payments_lookup (society_id, payment_status),
    FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_id) REFERENCES sms_advertisements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sms_advertisements (id, society_id, advertiser_name, company_name, company_logo_path, ad_type, headline, body_text, banner_image_path, target_url, contact_phone, category, offer_badge, cta_text, display_locations, target_audience, priority, start_date, end_date, status, impressions_count, clicks_count) VALUES
(1, 1, 'Zomato', 'Zomato', 'logos/zomato.svg', 'Company_Sponsored', 'Craving Delicious Food? Flat 50% OFF!', 'Order your favorite biryani, pizza, North Indian delicacies & desserts from top-rated restaurants near you. Use code SECURE50 at checkout.', 'banners/zomato_banner.svg', 'https://www.zomato.com', NULL, 'Food', 'FLAT 50% OFF', 'Order Now', 'dashboard_banner,carousel,offers_deals', 'all', 9, '2026-01-01', '2027-12-31', 'Active', 142, 28),
(2, 1, 'Blinkit', 'Blinkit', 'logos/blinkit.svg', 'Company_Sponsored', 'Groceries at your Gate in 10 Minutes', 'Fresh milk, farm veggies, bread, eggs, baby care and household staples delivered right to your doorstep in 10 minutes.', 'banners/blinkit_banner.svg', 'https://blinkit.com', NULL, 'Grocery', '10-MIN DELIVERY', 'Order Groceries', 'dashboard_banner,carousel,offers_deals', 'all', 9, '2026-01-01', '2027-12-31', 'Active', 189, 34),
(3, 1, 'Swiggy', 'Swiggy', 'logos/swiggy.svg', 'Company_Sponsored', 'Unlimited Free Delivery with Swiggy One', 'Enjoy delicious meals delivered hot and fast in under 30 mins from 1000+ handpicked neighborhood kitchens.', 'banners/swiggy_banner.svg', 'https://www.swiggy.com', NULL, 'Food', 'FREE DELIVERY', 'View Swiggy Deals', 'carousel,offers_deals,community', 'all', 8, '2026-01-01', '2027-12-31', 'Active', 95, 17),
(4, 1, 'Amazon India', 'Amazon', 'logos/amazon.svg', 'Company_Sponsored', 'Great Indian Home & Kitchen Carnival – Up to 60% OFF', 'Upgrade your home with smart appliances, cookware, electronics, and daily home needs. Fast next-day Prime delivery for society residents.', 'banners/amazon_banner.svg', 'https://www.amazon.in', NULL, 'Shopping', 'UP TO 60% OFF', 'Shop Now', 'dashboard_cards,offers_deals,marketplace', 'all', 8, '2026-01-01', '2027-12-31', 'Active', 110, 22),
(5, 1, 'Zepto', 'Zepto', 'logos/zepto.svg', 'Company_Sponsored', 'Superfast 10-Min Grocery Delivery – Flat ₹150 OFF', 'Get fresh fruits, groceries, snacks & cold drinks in 10 minutes. First 3 orders enjoy zero delivery fee and flat instant cashback.', 'banners/blinkit_banner.svg', 'https://www.zeptonow.com', NULL, 'Grocery', 'FLAT ₹150 OFF', 'Shop on Zepto', 'carousel,offers_deals', 'all', 7, '2026-01-01', '2027-12-31', 'Active', 78, 14),
(6, 1, 'The Royal Spice Restaurant', 'The Royal Spice', 'logos/restaurant.svg', 'Company_Sponsored', 'Exclusive 20% Society Resident Dining & Home Delivery', 'Authentic Mughlai, Tandoor & Chinese cuisine right across Sector 14. Show your SecureGate resident badge or call directly for quick door delivery.', 'banners/restaurant_banner.svg', 'https://maps.google.com', '+91 9876543210', 'Food', '20% RESIDENT DISCOUNT', 'Call Restaurant', 'offers_deals,notice_board,community', 'all', 6, '2026-01-01', '2027-12-31', 'Active', 64, 11);
