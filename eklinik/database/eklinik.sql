-- Database Schema for i-Klinik Politeknik Besut
-- Database Name: eklinik

CREATE DATABASE IF NOT EXISTS `eklinik` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eklinik`;

-- Users Table (stores all users: students, doctors, staff, OKU)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE,
    `matrix_no` VARCHAR(50) UNIQUE,
    `ic_number` VARCHAR(20),
    `phone` VARCHAR(20),
    `parent_phone` VARCHAR(20),
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'doctor', 'staff', 'oku') NOT NULL,
    `department` VARCHAR(100),
    `course` VARCHAR(100),
    `blood_type` VARCHAR(5),
    `is_oku` TINYINT(1) DEFAULT 0,
    `oku_disability` VARCHAR(255),
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments Table
CREATE TABLE `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `doctor_id` INT,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `queue_number` INT,
    `status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    `symptoms` TEXT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Categories (First Aid)
CREATE TABLE `inventory_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Items (First Aid Stock)
CREATE TABLE `inventory_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `stock_quantity` INT DEFAULT 0,
    `minimum_stock` INT DEFAULT 10,
    `unit` VARCHAR(50) DEFAULT 'unit',
    `status` ENUM('available', 'low_stock', 'out_of_stock') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `inventory_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock Borrowing/Loans (First Aid)
CREATE TABLE `stock_loans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `borrow_date` DATE NOT NULL,
    `return_date` DATE,
    `status` ENUM('pending', 'approved', 'rejected', 'returned') DEFAULT 'pending',
    `approved_by` INT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OKU Equipment
CREATE TABLE `oku_equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(100),
    `quantity` INT DEFAULT 0,
    `available_quantity` INT DEFAULT 0,
    `status` ENUM('available', 'borrowed', 'maintenance') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OKU Equipment Loans
CREATE TABLE `oku_equipment_loans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `equipment_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `borrow_date` DATE NOT NULL,
    `return_date` DATE,
    `status` ENUM('pending', 'approved', 'rejected', 'returned') DEFAULT 'pending',
    `approved_by` INT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`equipment_id`) REFERENCES `oku_equipment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pharmacy/Medicines
CREATE TABLE `medicines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `stock_quantity` INT DEFAULT 0,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `expiry_date` DATE,
    `category` VARCHAR(100),
    `status` ENUM('available', 'low_stock', 'expired', 'out_of_stock') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medicine Dispensing (Prescriptions)
CREATE TABLE `medicine_dispensing` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appointment_id` INT,
    `medicine_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `dosage` VARCHAR(255),
    `instructions` TEXT,
    `dispensed_by` INT,
    `dispensed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`dispensed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency Unit
CREATE TABLE `emergency_drivers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `vehicle_number` VARCHAR(20),
    `phone` VARCHAR(20),
    `status` ENUM('available', 'on_duty', 'off_duty') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency Reports
CREATE TABLE `emergency_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_name` VARCHAR(255) NOT NULL,
    `matrix_no` VARCHAR(50),
    `phone` VARCHAR(20),
    `department` VARCHAR(100),
    `emergency_description` TEXT NOT NULL,
    `driver_id` INT,
    `status` ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    `reported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY (`driver_id`) REFERENCES `emergency_drivers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Driver Trip Logs
CREATE TABLE `driver_trip_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `driver_id` INT NOT NULL,
    `emergency_report_id` INT,
    `departure_time` TIMESTAMP,
    `arrival_time` TIMESTAMP,
    `destination` VARCHAR(255),
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`driver_id`) REFERENCES `emergency_drivers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`emergency_report_id`) REFERENCES `emergency_reports`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT,
    `type` ENUM('appointment', 'inventory', 'oku', 'emergency', 'general') DEFAULT 'general',
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patient Medical Records
CREATE TABLE `medical_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `doctor_id` INT,
    `appointment_id` INT,
    `diagnosis` TEXT,
    `treatment` TEXT,
    `prescription` TEXT,
    `notes` TEXT,
    `is_critical` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `activity` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Data

-- Insert Inventory Categories
INSERT INTO `inventory_categories` (`name`, `description`) VALUES
('Ubatan Asas', 'Ubat-ubatan asas untuk rawatan awal'),
('Rawatan Luka', 'Peralatan untuk rawatan luka'),
('Pembalut', 'Pembalut dan peralatan berkaitan');

-- Insert Sample Inventory Items
INSERT INTO `inventory_items` (`category_id`, `name`, `description`, `stock_quantity`, `minimum_stock`, `unit`) VALUES
(1, 'Paracetamol 500mg', 'Ubat tahan sakit dan demam', 100, 20, 'tablet'),
(1, 'Ibuprofen 400mg', 'Ubat tahan sakit dan radang', 50, 15, 'tablet'),
(1, 'Antihistamine', 'Ubat alergi', 75, 20, 'tablet'),
(2, 'Betadine Solution', 'Cecair antiseptik untuk luka', 30, 10, 'bottle'),
(2, 'Hydrogen Peroxide', 'Cecair untuk membersihkan luka', 25, 10, 'bottle'),
(2, 'Gauze Swabs', 'Kasa steril untuk luka', 200, 50, 'pack'),
(3, 'Elastic Bandage', 'Pembalut elastik', 80, 20, 'roll'),
(3, 'Crepe Bandage', 'Pembalut krep', 60, 15, 'roll'),
(3, 'Adhesive Plaster', 'Plaster pelekat', 150, 30, 'box');

-- Insert Sample Medicines
INSERT INTO `medicines` (`name`, `description`, `stock_quantity`, `price`, `expiry_date`, `category`, `status`) VALUES
('Paracetamol 500mg', 'Ubat tahan sakit dan demam', 500, 0.10, '2027-12-31', 'Painkiller', 'available'),
('Amoxicillin 500mg', 'Antibiotik', 300, 0.50, '2027-06-30', 'Antibiotic', 'available'),
('Cetirizine 10mg', 'Ubat alergi', 200, 0.30, '2027-09-15', 'Antihistamine', 'available'),
('Omeprazole 20mg', 'Ubat gastrik', 150, 0.40, '2027-08-20', 'Antacid', 'available'),
('Cough Syrup', 'Ubat batuk', 100, 5.00, '2027-05-10', 'Cough Medicine', 'available');

-- Insert Sample OKU Equipment
INSERT INTO `oku_equipment` (`name`, `description`, `category`, `quantity`, `available_quantity`, `status`) VALUES
('Wheelchair', 'Kerusi roda standard', 'Mobility', 10, 8, 'available'),
('Crutches', 'Tongkat ketiak', 'Mobility', 15, 12, 'available'),
('Walking Frame', 'Bingkai berjalan', 'Mobility', 5, 4, 'available'),
('Blind Stick', 'Tongkat putih untuk buta', 'Visual Aid', 8, 6, 'available'),
('Hearing Aid Battery', 'Bateri alat pendengaran', 'Hearing Aid', 50, 45, 'available');

-- Insert Sample Users
-- Password: 123456 (plain text - will be verified directly in login)
INSERT INTO `users` (`name`, `email`, `matrix_no`, `ic_number`, `phone`, `parent_phone`, `password`, `role`, `department`, `course`, `blood_type`, `is_oku`, `status`) VALUES
-- Doctor
('Dr. Ahmad Razak', 'doctor@polibesut.edu.my', NULL, '800101-01-1234', '0123456789', NULL, '123456', 'doctor', 'Klinik', 'Perubatan', 'O+', 0, 'active'),
-- Staff
('Nurse Siti Nurhaliza', 'staff@polibesut.edu.my', NULL, '850202-02-5678', '0134567890', NULL, '123456', 'staff', 'Klinik', 'Kejururawatan', 'A+', 0, 'active'),
-- Students
('Ali bin Abu', 'student1@polibesut.edu.my', '34DIT24F1001', '050101-01-1234', '0145678901', '0156789012', '123456', 'student', 'KEJURUTERAAN ELEKTRONIK', 'DIPLOMA TEKNOLOGI ELEKTRONIK INDUSTRI', 'B+', 0, 'active'),
('Siti Aminah', 'student2@polibesut.edu.my', '34DIT24F1002', '050202-02-5678', '0156789012', '0167890123', '123456', 'student', 'KEJURUTERAAN MEKANIKAL', 'DIPLOMA TEKNOLOGI MEKANIKAL', 'A+', 0, 'active'),
('Ahmad bin Mohamed', 'student3@polibesut.edu.my', '34DIT24F1003', '050303-03-9012', '0167890123', '0178901234', '123456', 'student', 'KEJURUTERAAN AWAM', 'DIPLOMA TEKNOLOGI BINAAN', 'O-', 0, 'active'),
-- OKU Student
('Fatimah binti Ismail', 'oku_student@polibesut.edu.my', '34DIT24F1004', '050404-04-3456', '0178901234', '0189012345', '123456', 'student', 'KEJURUTERAAN ELEKTRONIK', 'DIPLOMA TEKNOLOGI ELEKTRONIK INDUSTRI', 'AB+', 1, 'active'),
-- OKU Staff
('Mohd Azman', 'oku_staff@polibesut.edu.my', NULL, '820505-05-6789', '0189012345', '0190123456', '123456', 'staff', 'PENTADBIRAN', 'PENTADBIRAN AM', 'B+', 1, 'active');

-- Insert Sample Emergency Drivers
INSERT INTO `emergency_drivers` (`user_id`, `vehicle_number`, `phone`, `status`) VALUES
(2, 'PBS 1234', '0134567890', 'available'),
(1, 'PBS 5678', '0123456789', 'on_duty');

-- Insert Sample Activity Logs
INSERT INTO `activity_logs` (`user_id`, `activity`, `description`) VALUES
(1, 'New patient registered', 'AHMAD BIN MOHAMED'),
(1, 'Appointment confirmed', 'SITI AMINAH'),
(2, 'Stock updated', 'Paracetamol stock replenished');
