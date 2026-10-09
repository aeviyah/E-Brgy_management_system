-- ============================================================
-- E-Barangay System — Barangay Bigaan, Calauag, Quezon
-- Regenerated schema (matches live DB)
-- Engine: InnoDB · Charset: utf8mb4 · Collation: utf8mb4_general_ci
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `e_brgy_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `e_brgy_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `resident_status_history`;
DROP TABLE IF EXISTS `treasury_transactions`;
DROP TABLE IF EXISTS `treasury`;
DROP TABLE IF EXISTS `transparency_links`;
DROP TABLE IF EXISTS `transparency_documents`;
DROP TABLE IF EXISTS `transparency_charter`;
DROP TABLE IF EXISTS `transparency_office_hours`;
DROP TABLE IF EXISTS `transparency_treasurer`;
DROP TABLE IF EXISTS `service_requests`;
DROP TABLE IF EXISTS `facility_reservations`;
DROP TABLE IF EXISTS `facilities`;
DROP TABLE IF EXISTS `permit_applications`;
DROP TABLE IF EXISTS `document_requests`;
DROP TABLE IF EXISTS `cases`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `assets`;
DROP TABLE IF EXISTS `residents`;
DROP TABLE IF EXISTS `households`;
DROP TABLE IF EXISTS `puroks`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. users
-- ============================================================
CREATE TABLE `users` (
  `user_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       VARCHAR(50)  NOT NULL DEFAULT 'Resident',
  `position`   VARCHAR(100) DEFAULT NULL,
  `status`     ENUM('Active','Pending','Inactive') DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 2. puroks
-- ============================================================
CREATE TABLE `puroks` (
  `purok_id`     INT(11) NOT NULL AUTO_INCREMENT,
  `purok_name`   VARCHAR(100) NOT NULL,
  `purok_leader` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`purok_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 3. households
-- ============================================================
CREATE TABLE `households` (
  `household_id`      INT(11) NOT NULL AUTO_INCREMENT,
  `household_number`  VARCHAR(50) NOT NULL,
  `household_name`    VARCHAR(120) DEFAULT NULL,
  `household_head_id` INT(11) DEFAULT NULL,
  `purok_id`          INT(11) DEFAULT NULL,
  `address`           TEXT NOT NULL,
  `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`household_id`),
  UNIQUE KEY `household_number` (`household_number`),
  KEY `purok_id` (`purok_id`),
  CONSTRAINT `households_ibfk_1` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 4. residents
-- ============================================================
CREATE TABLE `residents` (
  `resident_id`            INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`                INT(11) DEFAULT NULL,
  `household_id`           INT(11) DEFAULT NULL,
  `purok_id`               INT(11) DEFAULT NULL,
  `first_name`             VARCHAR(50) NOT NULL,
  `middle_name`            VARCHAR(50) DEFAULT NULL,
  `last_name`              VARCHAR(50) NOT NULL,
  `suffix`                 VARCHAR(10) DEFAULT NULL,
  `birthdate`              DATE NOT NULL,
  `gender`                 ENUM('Male','Female','Other') NOT NULL,
  `civil_status`           ENUM('Single','Married','Widowed','Separated') NOT NULL,
  `occupation`             VARCHAR(100) DEFAULT NULL,
  `voter_status`           ENUM('Yes','No') DEFAULT 'No',
  `special_classification` ENUM('None','Senior Citizen','PWD','Solo Parent','Indigent') DEFAULT 'None',
  `contact_number`         VARCHAR(15) DEFAULT NULL,
  `email`                  VARCHAR(100) DEFAULT NULL,
  `address`                TEXT DEFAULT NULL,
  `profile_completed`      TINYINT(1) DEFAULT 0,
  `is_head_of_family`      TINYINT(1) DEFAULT 0,
  `is_senior`              TINYINT(1) DEFAULT 0,
  `is_pwd`                 TINYINT(1) DEFAULT 0,
  `pwd_id_number`          VARCHAR(50) DEFAULT NULL,
  `is_solo_parent`         TINYINT(1) DEFAULT 0,
  `solo_parent_id_number`  VARCHAR(50) DEFAULT NULL,
  `is_youth`               TINYINT(1) DEFAULT 0,
  `relationship_to_head`   VARCHAR(60) DEFAULT NULL,
  `status`                 ENUM('Active','Deceased','Moved Out','Abroad') DEFAULT 'Active',
  `status_changed_at`      DATETIME DEFAULT NULL,
  `status_notes`           TEXT DEFAULT NULL,
  `created_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`resident_id`),
  KEY `user_id` (`user_id`),
  KEY `household_id` (`household_id`),
  KEY `purok_id` (`purok_id`),
  CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`user_id`)      REFERENCES `users` (`user_id`)         ON DELETE SET NULL,
  CONSTRAINT `residents_ibfk_2` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE SET NULL,
  CONSTRAINT `residents_ibfk_3` FOREIGN KEY (`purok_id`)     REFERENCES `puroks` (`purok_id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 5. resident_status_history
-- ============================================================
CREATE TABLE `resident_status_history` (
  `history_id`      INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`     INT(11) NOT NULL,
  `old_status`      VARCHAR(40) DEFAULT NULL,
  `new_status`      VARCHAR(40) DEFAULT NULL,
  `notes`           TEXT DEFAULT NULL,
  `changed_by`      INT(11) DEFAULT NULL,
  `changed_by_name` VARCHAR(100) DEFAULT NULL,
  `changed_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_resident` (`resident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 6. assets
-- ============================================================
CREATE TABLE `assets` (
  `asset_id`        INT(11) NOT NULL AUTO_INCREMENT,
  `asset_code`      VARCHAR(50) NOT NULL,
  `item_name`       VARCHAR(100) NOT NULL,
  `quantity`        INT(11) NOT NULL DEFAULT 1,
  `asset_condition` ENUM('Good','Fair','Needs Repair','Disposed') DEFAULT 'Good',
  `location`        VARCHAR(100) NOT NULL,
  PRIMARY KEY (`asset_id`),
  UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 7. audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
  `log_id`             INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`            INT(11) DEFAULT NULL,
  `action_type`        VARCHAR(50) NOT NULL,
  `affected_table`     VARCHAR(50) NOT NULL,
  `affected_record_id` INT(11) DEFAULT NULL,
  `details`            TEXT DEFAULT NULL,
  `timestamp`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 8. announcements
-- ============================================================
CREATE TABLE `announcements` (
  `announcement_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(150) NOT NULL,
  `content`         TEXT NOT NULL,
  `target_audience` VARCHAR(100) DEFAULT 'All',
  `channel`         ENUM('In-App','SMS','Email','Bulletin') DEFAULT 'In-App',
  `status`          ENUM('Draft','Scheduled','Published','Archived') DEFAULT 'Published',
  `posted_by`       INT(11) NOT NULL,
  `date_posted`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`),
  KEY `posted_by` (`posted_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 9. complaints
-- ============================================================
CREATE TABLE `complaints` (
  `complaint_id`     INT(11) NOT NULL AUTO_INCREMENT,
  `complainant_id`   INT(11) NOT NULL,
  `respondent_name`  VARCHAR(150) NOT NULL,
  `incident_details` TEXT NOT NULL,
  `status`           ENUM('Pending','Ongoing','Resolved','Dismissed') DEFAULT 'Pending',
  `date_filed`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`complaint_id`),
  KEY `complainant_id` (`complainant_id`),
  CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`complainant_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 10. cases
-- ============================================================
CREATE TABLE `cases` (
  `case_id`           INT(11) NOT NULL AUTO_INCREMENT,
  `case_number`       VARCHAR(50) NOT NULL,
  `complainant_id`    INT(11) NOT NULL,
  `respondent_name`   VARCHAR(150) NOT NULL,
  `incident_location` TEXT NOT NULL,
  `incident_details`  TEXT NOT NULL,
  `case_type`         ENUM('Blotter','Complaint','Katarungang Pambarangay','VAWC','BCPC') NOT NULL,
  `is_restricted`     TINYINT(1) DEFAULT 0,
  `status`            ENUM('Pending','Under Investigation','Mediation/Hearing','Resolved','Referred to Police/Court','Dismissed') DEFAULT 'Pending',
  `date_filed`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`case_id`),
  UNIQUE KEY `case_number` (`case_number`),
  KEY `complainant_id` (`complainant_id`),
  CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`complainant_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 11. document_requests
-- ============================================================
CREATE TABLE `document_requests` (
  `request_id`        INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`       INT(11) NOT NULL,
  `document_type`     ENUM('Barangay Clearance','Certificate of Indigency','Certificate of Residency','Good Moral','Low Income','No Property') NOT NULL,
  `purpose`           TEXT NOT NULL,
  `requirements_path` VARCHAR(255) DEFAULT NULL,
  `status`            ENUM('Pending','Under Review','For Payment','For Approval','Processing','Ready for Release','Completed','Rejected','Returned for Correction','Cancelled') DEFAULT 'Pending',
  `qr_code_hash`      VARCHAR(255) DEFAULT NULL,
  `payment_method`    ENUM('Cash','GCash','Maya','MariBank') DEFAULT 'Cash',
  `reference_number`  VARCHAR(100) DEFAULT NULL,
  `amount`            DECIMAL(10,2) DEFAULT 0.00,
  `requested_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by`      INT(11) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `resident_id` (`resident_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 12. events
-- ============================================================
CREATE TABLE `events` (
  `event_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `event_date`  DATETIME NOT NULL,
  `event_time`  TIME DEFAULT NULL,
  `location`    VARCHAR(150) NOT NULL,
  `organizer`   VARCHAR(150) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 13. facilities
-- ============================================================
CREATE TABLE `facilities` (
  `facility_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `facility_name` VARCHAR(100) NOT NULL,
  `description`   TEXT DEFAULT NULL,
  `capacity`      INT(11) DEFAULT 0,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 14. facility_reservations
-- ============================================================
CREATE TABLE `facility_reservations` (
  `reservation_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`      INT(11) DEFAULT NULL,
  `user_id`          INT(11) DEFAULT NULL,
  `facility_id`      INT(11) DEFAULT NULL,
  `facility_name`    VARCHAR(100) NOT NULL,
  `applicant_name`   VARCHAR(150) DEFAULT NULL,
  `purpose`          TEXT DEFAULT NULL,
  `reservation_date` DATE NOT NULL,
  `start_time`       TIME NOT NULL,
  `end_time`         TIME NOT NULL,
  `status`           ENUM('Pending','Approved','Rejected','Cancelled','Completed') DEFAULT 'Pending',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  KEY `resident_id` (`resident_id`),
  KEY `user_id` (`user_id`),
  KEY `facility_id` (`facility_id`),
  CONSTRAINT `facility_reservations_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  CONSTRAINT `facility_reservations_ibfk_2` FOREIGN KEY (`user_id`)     REFERENCES `users` (`user_id`)         ON DELETE SET NULL,
  CONSTRAINT `facility_reservations_ibfk_3` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 15. permit_applications
-- ============================================================
CREATE TABLE `permit_applications` (
  `permit_id`        INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`      INT(11) NOT NULL,
  `business_name`    VARCHAR(150) NOT NULL,
  `business_address` TEXT NOT NULL,
  `assessment_fee`   DECIMAL(10,2) NOT NULL,
  `status`           ENUM('Pending','Under Review','For Payment','For Approval','Completed','Rejected') DEFAULT 'Pending',
  `applied_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permit_id`),
  KEY `resident_id` (`resident_id`),
  CONSTRAINT `permit_applications_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 16. posts
-- ============================================================
CREATE TABLE `posts` (
  `post_id`     INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL,
  `author_name` VARCHAR(150) NOT NULL,
  `author_role` VARCHAR(100) NOT NULL,
  `content`     TEXT DEFAULT NULL,
  `image_path`  VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 17. projects
-- ============================================================
CREATE TABLE `projects` (
  `project_id`      INT(11) NOT NULL AUTO_INCREMENT,
  `project_name`    VARCHAR(150) NOT NULL,
  `budget`          DECIMAL(12,2) NOT NULL,
  `purok_id`        INT(11) DEFAULT NULL,
  `progress_status` ENUM('Proposed','Ongoing','Completed','On Hold') DEFAULT 'Proposed',
  `start_date`      DATE DEFAULT NULL,
  `target_end_date` DATE DEFAULT NULL,
  PRIMARY KEY (`project_id`),
  KEY `purok_id` (`purok_id`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 18. service_requests
-- ============================================================
CREATE TABLE `service_requests` (
  `service_request_id` INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`        INT(11) NOT NULL,
  `service_type`       VARCHAR(100) NOT NULL,
  `details`            TEXT NOT NULL,
  `status`             ENUM('Pending','Under Review','Approved','Processing','Completed','Rejected') DEFAULT 'Pending',
  `filed_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`service_request_id`),
  KEY `resident_id` (`resident_id`),
  CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 19. treasury  (manual-entry payment log)
-- ============================================================
CREATE TABLE `treasury` (
  `treasury_id`      INT(11) NOT NULL AUTO_INCREMENT,
  `or_number`        VARCHAR(50) NOT NULL,
  `payer_name`       VARCHAR(150) NOT NULL,
  `payment_type`     VARCHAR(120) NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method`   ENUM('Cash','GCash','Maya','MariBank') DEFAULT 'Cash',
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`treasury_id`),
  UNIQUE KEY `or_number` (`or_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 20. treasury_transactions  (FK-based ledger)
-- ============================================================
CREATE TABLE `treasury_transactions` (
  `transaction_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `or_number`        VARCHAR(50) NOT NULL,
  `resident_id`      INT(11) NOT NULL,
  `reference_type`   ENUM('Document Request','Permit Application','Facility Rental','Other') NOT NULL,
  `reference_id`     INT(11) NOT NULL,
  `amount_paid`      DECIMAL(10,2) NOT NULL,
  `payment_method`   ENUM('Cash','GCash','Online Transfer') DEFAULT 'Cash',
  `payment_status`   ENUM('Paid','Pending','Cancelled') DEFAULT 'Paid',
  `treasurer_id`     INT(11) NOT NULL,
  `transaction_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `or_number` (`or_number`),
  KEY `resident_id` (`resident_id`),
  KEY `treasurer_id` (`treasurer_id`),
  CONSTRAINT `treasury_transactions_ibfk_1` FOREIGN KEY (`resident_id`)   REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  CONSTRAINT `treasury_transactions_ibfk_2` FOREIGN KEY (`treasurer_id`)  REFERENCES `users` (`user_id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 21-25. transparency_* tables (public disclosure content)
-- ============================================================
CREATE TABLE `transparency_treasurer` (
  `id`      INT(11) NOT NULL AUTO_INCREMENT,
  `name`    VARCHAR(150) NOT NULL,
  `title`   VARCHAR(100) NOT NULL,
  `mobile`  VARCHAR(40)  DEFAULT NULL,
  `email`   VARCHAR(150) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transparency_office_hours` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `label`      VARCHAR(100) NOT NULL,
  `hours`      VARCHAR(150) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transparency_charter` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `service_name`    VARCHAR(150) NOT NULL,
  `fee`             VARCHAR(80)  NOT NULL,
  `processing_time` VARCHAR(100) NOT NULL,
  `sort_order`      INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transparency_documents` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `period`      VARCHAR(60) DEFAULT NULL,
  `report_type` VARCHAR(40) NOT NULL,
  `sort_order`  INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transparency_links` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `url`         VARCHAR(255) NOT NULL,
  `link_label`  VARCHAR(150) DEFAULT NULL,
  `sort_order`  INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- End of schema
-- ============================================================