CREATE DATABASE IF NOT EXISTS `lab_app_db`;
USE `lab_app_db`;

-- Table for lab branches
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `branches` (`id`, `name`, `address`) VALUES
(1, 'Main Branch - Bengaluru', '123 MG Road, Bengaluru'),
(2, 'Satellite Branch - Mysuru', '456 KRS Road, Mysuru');

-- Table for admin and superadmin users
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','superadmin') NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Super Admin: user: superadmin / pass: password123
INSERT INTO `users` (`full_name`, `username`, `password`, `role`, `branch_id`) VALUES
('Super Admin', 'superadmin', '$2y$10$tZ.f7oZt5bJ2r5o4i3g.Uu1tYvX8w/jZ7nL6k9.m7g.t5bJ2r5o4i', 'superadmin', NULL);
-- Default Branch Admin: user: admin_bengaluru / pass: password123
INSERT INTO `users` (`full_name`, `username`, `password`, `role`, `branch_id`) VALUES
('Admin Bengaluru', 'admin_bengaluru', '$2y$10$tZ.f7oZt5bJ2r5o4i3g.Uu1tYvX8w/jZ7nL6k9.m7g.t5bJ2r5o4i', 'admin', 1);

-- Table for patients
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `dob` date NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for lab tests
CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_code` varchar(50) NOT NULL UNIQUE,
  `test_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) DEFAULT 'assets/img/test_placeholder.png',
  `branch_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `lab_tests` (`test_code`, `test_name`, `price`, `branch_id`) VALUES
('CBC', 'Complete Blood Count', 350.00, 1),
('LFT', 'Liver Function Test', 800.00, 1),
('KFT', 'Kidney Function Test', 750.00, 1),
('THY', 'Thyroid Profile', 900.00, 2);

-- Table for invoices
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL UNIQUE,
  `patient_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','UPI') NOT NULL,
  `cash_received` decimal(10,2) DEFAULT NULL,
  `balance_returned` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for items within an invoice
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `test_name_snapshot` varchar(255) NOT NULL,
  `price_snapshot` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`test_id`) REFERENCES `lab_tests`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for global application settings
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL UNIQUE,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('lab_name', 'Purity Diagnostics'),
('lab_logo_path', 'assets/img/logo.png'),
('lab_address', '123 Health St, Wellness City, India'),
('lab_phone', '+91 98765 43210'),
('invoice_footer_note', 'Thank you for choosing us! Wishing you good health.');
