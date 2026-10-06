-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2026 at 04:19 PM
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
-- Database: `e_brgy_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `target_audience` varchar(100) DEFAULT 'All',
  `channel` enum('In-App','SMS','Email','All') DEFAULT 'In-App',
  `status` enum('Draft','Scheduled','Published','Archived') DEFAULT 'Published',
  `posted_by` int(11) NOT NULL,
  `date_posted` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL,
  `asset_code` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `asset_condition` enum('Good','Fair','Needs Repair','Disposed') DEFAULT 'Good',
  `location` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `affected_table` varchar(50) NOT NULL,
  `affected_record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE `cases` (
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `complainant_id` int(11) NOT NULL,
  `respondent_name` varchar(150) NOT NULL,
  `incident_location` text NOT NULL,
  `incident_details` text NOT NULL,
  `case_type` enum('Blotter','Complaint','Katarungang Pambarangay','VAWC','BCPC') NOT NULL,
  `is_restricted` tinyint(1) DEFAULT 0,
  `status` enum('Pending','Under Investigation','Mediation/Hearing','Resolved','Referred to Police/Court','Dismissed') DEFAULT 'Pending',
  `date_filed` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `document_type` enum('Barangay Clearance','Certificate of Indigency','Certificate of Residency','Good Moral','Low Income','No Property') NOT NULL,
  `purpose` text NOT NULL,
  `requirements_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Under Review','For Payment','For Approval','Processing','Ready for Release','Completed','Rejected','Returned for Correction','Cancelled') DEFAULT 'Pending',
  `qr_code_hash` varchar(255) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `location` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility_reservations`
--

CREATE TABLE `facility_reservations` (
  `reservation_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `facility_name` varchar(100) NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Pending','Approved','Cancelled','Completed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `household_id` int(11) NOT NULL,
  `household_number` varchar(50) NOT NULL,
  `purok_id` int(11) DEFAULT NULL,
  `address` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permit_applications`
--

CREATE TABLE `permit_applications` (
  `permit_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `business_address` text NOT NULL,
  `assessment_fee` decimal(10,2) NOT NULL,
  `status` enum('Pending','Under Review','For Payment','For Approval','Completed','Rejected') DEFAULT 'Pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `project_name` varchar(150) NOT NULL,
  `budget` decimal(12,2) NOT NULL,
  `purok_id` int(11) DEFAULT NULL,
  `progress_status` enum('Proposed','Ongoing','Completed','On Hold') DEFAULT 'Proposed',
  `start_date` date DEFAULT NULL,
  `target_end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `puroks`
--

CREATE TABLE `puroks` (
  `purok_id` int(11) NOT NULL,
  `purok_name` varchar(100) NOT NULL,
  `purok_leader` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `resident_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `household_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') NOT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `voter_status` enum('Yes','No') DEFAULT 'No',
  `special_classification` enum('None','Senior Citizen','PWD','Solo Parent','Indigent') DEFAULT 'None',
  `contact_number` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('Active Resident','Pending Verification','Deceased','Moved Out') DEFAULT 'Pending Verification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `service_request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `status` enum('Pending','Under Review','Approved','Processing','Completed','Rejected') DEFAULT 'Pending',
  `filed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treasury_transactions`
--

CREATE TABLE `treasury_transactions` (
  `transaction_id` int(11) NOT NULL,
  `or_number` varchar(50) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `reference_type` enum('Document Request','Permit Application','Facility Rental','Other') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','GCash','Online Transfer') DEFAULT 'Cash',
  `payment_status` enum('Paid','Pending','Cancelled') DEFAULT 'Paid',
  `treasurer_id` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Resident','Barangay Secretary','Barangay Treasurer','Punong Barangay','Barangay Staff','System Administrator') NOT NULL,
  `status` enum('Active','Pending','Inactive') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cases`
--
ALTER TABLE `cases`
  ADD PRIMARY KEY (`case_id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `complainant_id` (`complainant_id`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `facility_reservations`
--
ALTER TABLE `facility_reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`household_id`),
  ADD UNIQUE KEY `household_number` (`household_number`),
  ADD KEY `purok_id` (`purok_id`);

--
-- Indexes for table `permit_applications`
--
ALTER TABLE `permit_applications`
  ADD PRIMARY KEY (`permit_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `purok_id` (`purok_id`);

--
-- Indexes for table `puroks`
--
ALTER TABLE `puroks`
  ADD PRIMARY KEY (`purok_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`resident_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `household_id` (`household_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`service_request_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `treasury_transactions`
--
ALTER TABLE `treasury_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD UNIQUE KEY `or_number` (`or_number`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `treasurer_id` (`treasurer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cases`
--
ALTER TABLE `cases`
  MODIFY `case_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility_reservations`
--
ALTER TABLE `facility_reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `household_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permit_applications`
--
ALTER TABLE `permit_applications`
  MODIFY `permit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `puroks`
--
ALTER TABLE `puroks`
  MODIFY `purok_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `resident_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `service_request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treasury_transactions`
--
ALTER TABLE `treasury_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `cases`
--
ALTER TABLE `cases`
  ADD CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`complainant_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `facility_reservations`
--
ALTER TABLE `facility_reservations`
  ADD CONSTRAINT `facility_reservations_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `households_ibfk_1` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL;

--
-- Constraints for table `permit_applications`
--
ALTER TABLE `permit_applications`
  ADD CONSTRAINT `permit_applications_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL;

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `residents_ibfk_2` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE SET NULL;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE;

--
-- Constraints for table `treasury_transactions`
--
ALTER TABLE `treasury_transactions`
  ADD CONSTRAINT `treasury_transactions_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treasury_transactions_ibfk_2` FOREIGN KEY (`treasurer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
