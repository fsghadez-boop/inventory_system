-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Feb 06, 2026 at 07:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `action` enum('login','logout','edit','delete','update','add','condemn') NOT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `log_details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `category_type` enum('supply','asset') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `category_type`) VALUES
(7, 'Keyboard', '2025-09-08 15:00:40', 'supply'),
(16, 'CPU', '2025-09-09 16:01:26', 'asset'),
(17, 'Laptop', '2025-09-09 16:01:31', 'asset'),
(18, 'Monitor', '2025-09-09 16:01:36', 'asset'),
(19, 'Printer', '2025-09-09 16:01:40', 'asset'),
(20, 'UPS', '2025-09-09 16:01:43', 'asset'),
(21, 'Scanner', '2025-09-09 16:01:47', 'asset'),
(22, 'Speaker', '2025-09-09 16:01:50', 'asset'),
(23, 'Portable Devices', '2025-09-09 16:01:55', 'asset'),
(24, 'Network Equipment', '2025-09-09 16:01:59', 'asset'),
(25, 'Server', '2025-09-09 16:02:02', 'asset'),
(26, 'SMART TV', '2025-09-09 16:02:06', 'asset'),
(27, 'Removable Devices', '2025-09-09 16:02:10', 'asset'),
(32, 'Mouse', '2025-09-16 09:29:27', 'supply');

-- --------------------------------------------------------

--
-- Table structure for table `condemnations`
--

CREATE TABLE `condemnations` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `memorandum_receipt` varchar(255) DEFAULT NULL,
  `reason` text NOT NULL,
  `condemned_by` int(11) NOT NULL,
  `condemned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_item_tracker`
--

CREATE TABLE `inventory_item_tracker` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `from_status` enum('brand_new','for_replacement','for_condemn','condemned') DEFAULT NULL,
  `to_status` enum('brand_new','for_replacement','for_condemn','condemned') NOT NULL,
  `moved_by` int(11) NOT NULL,
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `property_number` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `processor` varchar(100) DEFAULT NULL,
  `ram` varchar(50) DEFAULT NULL,
  `graphics1` varchar(100) DEFAULT NULL,
  `graphics2` varchar(100) DEFAULT NULL,
  `computer_name` varchar(100) DEFAULT NULL,
  `workgroup` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `office_app` varchar(50) DEFAULT NULL,
  `ms_account` varchar(100) DEFAULT NULL,
  `endpoint_protection` varchar(100) DEFAULT NULL,
  `endpoint_updated` tinyint(1) DEFAULT 0,
  `anydesk_id` varchar(100) DEFAULT NULL,
  `belarc_installed` tinyint(1) DEFAULT 0,
  `accounts_updated` tinyint(1) DEFAULT 0,
  `ultravnc_installed` tinyint(1) DEFAULT 0,
  `snmp_installed` tinyint(1) DEFAULT 0,
  `connection_type` varchar(50) DEFAULT NULL,
  `dhcp_type` varchar(50) DEFAULT NULL,
  `static_app` varchar(100) DEFAULT NULL,
  `ip_address1` varchar(50) DEFAULT NULL,
  `ip_address2` varchar(50) DEFAULT NULL,
  `lan_mac` varchar(50) DEFAULT NULL,
  `wlan_mac1` varchar(50) DEFAULT NULL,
  `wlan_mac2` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `office_id` int(11) DEFAULT NULL,
  `USERids` varchar(255) DEFAULT NULL,
  `miaa_property` varchar(50) DEFAULT NULL,
  `memorandum_receipt` varchar(100) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `display_size` varchar(50) DEFAULT NULL,
  `printer_type` varchar(50) DEFAULT NULL,
  `capacity_va` varchar(50) DEFAULT NULL,
  `other_details` text DEFAULT NULL,
  `network_equipment_type` varchar(100) DEFAULT NULL,
  `network_equipment_other` varchar(100) DEFAULT NULL,
  `area_of_deployment` varchar(100) DEFAULT NULL,
  `storage_type` varchar(50) DEFAULT NULL,
  `storage_capacity` varchar(50) DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` enum('boxes','pieces') DEFAULT 'pieces',
  `status` enum('brand_new','for_replacement','for_condemn','condemned') DEFAULT 'brand_new',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) DEFAULT 0,
  `acquisition_date` varchar(255) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_contact` varchar(255) DEFAULT NULL,
  `supplier_email` varchar(255) DEFAULT NULL,
  `supplier_address` text DEFAULT NULL,
  `supplier_contact_name` varchar(255) DEFAULT NULL,
  `warranty` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_status_logs`
--

CREATE TABLE `item_status_logs` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `old_status` varchar(255) DEFAULT NULL,
  `new_status` varchar(255) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`id`, `name`) VALUES
(36, 'ACCOUNTING DIVISION'),
(18, 'ADMINISTRATIVE DEPARTMENT'),
(8, 'AGM FOR AIRPORT DEVELOPMENT AND CORPORATE AFFAIRS'),
(6, 'AGM FOR FINANCE AND ADMINISTRATION'),
(61, 'AIRPORT GROUNDS OPERATIONS & SAFETY DIVISION'),
(44, 'AIRPORT GROUNDS OPERATIONS COMPLIANCE MONITORING DIVISION'),
(62, 'AIRPORT GROUNDS OPERATIONS DIVISION'),
(63, 'AIRPORT OPERATIONS DEPARTMENT'),
(21, 'AIRPORT POLICE DEPARTMENT'),
(24, 'AIRPORT SECURITY INSPECTORATE OFFICE'),
(45, 'AIRPORT TERMINAL OPERATIONS COMPLIANCE MONITORING DIVISION'),
(54, 'AIRSIDE POLICE DIVISION'),
(37, 'BUDGET DIVISION'),
(50, 'BUILDINGS DIVISION'),
(82, 'BUSINESS & INVESTMENT DEVELOPMENT DIVISION'),
(28, 'BUSINESS AND REAL ESTATE INVESTMENT DEVELOPMENT DIVISION'),
(38, 'CASHIERING DIVISION'),
(15, 'CIVIL WORKS DEPARTMENT'),
(86, 'COA OFFICE'),
(29, 'COLLECTION DIVISION'),
(19, 'COMMERCIAL SERVICES DEPARTMENT'),
(30, 'CONCESSIONS MANAGEMENT DIVISION'),
(16, 'CORPORATE MANAGEMENT SERVICES DEPARTMENT'),
(85, 'DESIGN & PLANNING DIVISION'),
(33, 'DESIGN AND PLANNING DIVISION'),
(47, 'ELECTRICAL DIVISION'),
(81, 'ELECTRONICS & COMMUNICATIONS DIVISION'),
(48, 'ELECTRONICS AND COMMUNICATIONS DIVISION'),
(84, 'ENGINEERING DEPARTMENT'),
(17, 'FINANCE DEPARTMENT'),
(87, 'GENDER AND DEVELOPMENT OFFICE'),
(43, 'GENERAL SERVICES DIVISION'),
(77, 'HUMAN RESOURCE AND DEVELOPMENT DIVISION'),
(40, 'HUMAN RESOURCE DEVELOPMENT DIVISION'),
(67, 'ID & PASS CONTROL DIVISION'),
(20, 'INTELLIGENCE & ID PASS CONTROL DEPARTMENT'),
(52, 'INTELLIGENCE AND INVESTIGATION DIVISION'),
(13, 'INTERNAL AUDIT SERVICES OFFICE'),
(56, 'LANDSIDE POLICE DIVISION'),
(12, 'LEGAL OFFICE'),
(35, 'MANAGEMENT INFORMATION SYSTEM DIVISION'),
(46, 'MECHANICAL DIVISION'),
(27, 'MEDIA AFFAIRS DIVISION'),
(53, 'MEDICAL DIVISION'),
(79, 'OFFICE OF THE AGM FOR DEVELOPMENT & CORPORATE AFFAIRS'),
(9, 'OFFICE OF THE AGM FOR ENGINEERING'),
(80, 'OFFICE OF THE AGM FOR FINANCE AND ADMINISTRATION'),
(14, 'OFFICE OF THE AGM FOR OPERATIONS'),
(7, 'OFFICE OF THE AGM FOR OPERATIONS AND SAFETY STANDARDS COMPLIANCE'),
(10, 'OFFICE OF THE AGM FOR SECURITY & EMERGENCY SERVICES'),
(78, 'OFFICE OF THE AGM FOR SECURITY AND EMERGENCY SERVICES'),
(26, 'OFFICE OF THE CORPORATE BOARD SECRETARY'),
(3, 'OFFICE OF THE GENERAL MANAGER'),
(5, 'OFFICE OF THE SENIOR ASSISTANT GENERAL MANAGER'),
(49, 'PAVEMENTS AND GROUNDS DIVISION'),
(39, 'PERSONNEL DIVISION'),
(32, 'PLANS AND PROGRAMS DIVISION'),
(58, 'POLICE DETECTION AND REACTION DIVISION'),
(57, 'POLICE INTELLIGENCE AND INVESTIGATION DIVISION'),
(83, 'PREQUALIFICATION BID AND AWARD COMMITTEE'),
(41, 'PROCUREMENT DIVISION'),
(42, 'PROPERTY MANAGEMENT DIVISION'),
(11, 'PUBLIC AFFAIRS AND PROTOCOLS OFFICE'),
(51, 'PUBLIC ASSISTANCE DIVISION'),
(25, 'SCREENING & SURVEILLANCE DEPARTMENT'),
(59, 'SCREENING OPERATIONS DIVISION'),
(31, 'SURVEILLANCE OPERATIONS DIVISION'),
(76, 'SYSTEMS AND PROCEDURE DIVISION'),
(34, 'SYSTEMS AND PROCEDURES IMPROVEMENT DIVISION'),
(55, 'TERMINAL POLICE DIVISION'),
(71, 'TERMINAL POLICE DIVISION CT SECTION'),
(72, 'TERMINAL POLICE DIVISION T1 SECTION'),
(60, 'TERMINAL POLICE DIVISION T2 SECTION'),
(74, 'TERMINAL POLICE DIVISION T3 SECTION'),
(75, 'TERMINAL POLICE DIVISION T4 SECTION');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `requested_quantity` int(11) DEFAULT NULL,
  `office_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `is_return` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$geMw53D2H44P0PhjkbRMbOOcUREXIXfwbmcGYtPnUAfFVNqO3Nrhy', 'admin', '2025-09-04 19:52:21'),
(2, 'test', '$2y$10$KKhJnlhv36LNmiJUv9NRSO2Q6pNrDgVvgzg/5UeMaT5LZUR4arrQ6', 'user', '2025-09-05 12:10:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_holdings`
--

CREATE TABLE `user_holdings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `audit_logs_ibfk_1` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `condemnations`
--
ALTER TABLE `condemnations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `condemned_by` (`condemned_by`);

--
-- Indexes for table `inventory_item_tracker`
--
ALTER TABLE `inventory_item_tracker`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `moved_by` (`moved_by`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `item_status_logs`
--
ALTER TABLE `item_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `fk_requests_office` (`office_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_holdings`
--
ALTER TABLE `user_holdings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `condemnations`
--
ALTER TABLE `condemnations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `inventory_item_tracker`
--
ALTER TABLE `inventory_item_tracker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_status_logs`
--
ALTER TABLE `item_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_holdings`
--
ALTER TABLE `user_holdings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `condemnations`
--
ALTER TABLE `condemnations`
  ADD CONSTRAINT `condemnations_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `condemnations_ibfk_2` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_item_tracker`
--
ALTER TABLE `inventory_item_tracker`
  ADD CONSTRAINT `inventory_item_tracker_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_item_tracker_ibfk_2` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `item_status_logs`
--
ALTER TABLE `item_status_logs`
  ADD CONSTRAINT `item_status_logs_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_requests_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`),
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_holdings`
--
ALTER TABLE `user_holdings`
  ADD CONSTRAINT `user_holdings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_holdings_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
