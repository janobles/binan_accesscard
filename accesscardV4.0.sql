-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2026 at 10:08 AM
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
-- Database: `accesscard`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_trails`
--

CREATE TABLE `audit_trails` (
  `auditID` int(11) NOT NULL,
  `user_action` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `userID` int(11) NOT NULL,
  `memberID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trails`
--

INSERT INTO `audit_trails` (`auditID`, `user_action`, `description`, `ip_address`, `user_agent`, `dt_created`, `userID`, `memberID`) VALUES
(1, 'DB_INITIALIZATION', 'Database clean rebuild successful. Sector tracking migrated to bracketed string arrays.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-05-18 02:32:47', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `member`
--
-- ----------------------------------------------------------------------------
-- ABOUT THE `sectorID` COLUMN (the "JSON array" for a member's sectors)
-- ----------------------------------------------------------------------------
-- `member`.`sectorID` is intentionally NOT a foreign key. It stores a JSON
-- array of sector IDs as a string, e.g. '[1,2,3]', where each number is a
-- `sector`.`sectorID`. This lets one member belong to many sectors without a
-- junction table -- the sectors are referenced by ID inside the bracketed list.
-- (Services are handled separately by the `member_services` junction table.)
--
-- A member's sectors are resolved in code by decoding this array and matching
-- each ID against the `sector` table. Where it connects:
--
--   app/Support/SectorIds.php   -- encodes/decodes the array
--       toStorage()        : [1,2,3]   -> '[1,2,3]'  (what gets saved here)
--       normalize()        : '[1,2,3]' -> [1,2,3]    (read back as ints)
--       toNames()          : IDs       -> sector names (looked up in `sector`)
--       containsCondition(): builds JSON_CONTAINS(...) used for searching
--
--   app/Models/MemberModel.php
--       normalizeSectorIdStorage()      -- beforeInsert/beforeUpdate, writes this column
--       withSectorNames()/sectorNameMap() -- turn the IDs into sector names via `sector`
--       findWithSector()                -- find one member + their sector names
--       familySearchBuilder()           -- search members by sector using
--                                          JSON_CONTAINS(member.sectorID, '<id>')
--
--   app/Models/DashboardModel.php       -- same IDs->names resolve for dashboard lists
--   app/Validation/SectorRules.php      -- 'valid_sector_array' validates the array on save
--   view `view_member_dashboard`        -- exposes this column as `sector_array_string`
-- ----------------------------------------------------------------------------

CREATE TABLE `member` (
  `memberID` int(11) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(50) NOT NULL,
  `suffix` enum('I','II','III','IV') DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `civilstatus` enum('Single','Married','Widowed','Others') DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `education` text DEFAULT NULL,
  `job` text DEFAULT NULL,
  `Salary` float DEFAULT NULL,
  `contactnumber` int(11) DEFAULT NULL,
  `relationship` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` timestamp NULL DEFAULT NULL,
  `headID` int(11) NOT NULL,
  -- JSON array of sector.sectorID, e.g. '[1,2,3]'. Not a FK; resolved in code. See note above the table.
  `sectorID` varchar(255) NOT NULL DEFAULT '[]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member`
--

INSERT INTO `member` (`memberID`, `lastname`, `firstname`, `middlename`, `suffix`, `birthday`, `civilstatus`, `sex`, `education`, `job`, `Salary`, `contactnumber`, `relationship`, `address`, `religion`, `dt_created`, `dt_updated`, `dt_deleted`, `headID`, `sectorID`) VALUES
(1, 'Dela Cruz', 'Juan', 'Ramos', NULL, '1965-05-15', 'Married', 'Male', 'College Graduate', 'Jeepney Driver', 15000, 912345678, 'Head of Family', '123 Rizal Street, Brgy. San Vicente, BiÃ±an, Laguna', 'Roman Catholic', '2026-05-18 02:32:47', '2026-05-18 02:32:47', NULL, 1, '[1,2,3]'),
(2, 'Dela Cruz', 'Maria', 'Santos', NULL, '1970-10-22', 'Married', 'Female', 'High School Graduate', 'Sari-Sari Store Owner', 5000, 987654321, 'Asawa', '123 Rizal Street, Brgy. San Vicente, BiÃ±an, Laguna', 'Roman Catholic', '2026-05-18 02:32:47', '2026-05-18 02:32:47', NULL, 1, '[1,6]');

-- --------------------------------------------------------

--
-- Table structure for table `member_services`
--
-- This IS a junction table: it links one member to many services with real
-- foreign keys (memberID -> member, serviceID -> services). Compare with the
-- `member`.`sectorID` JSON array above, which links members to sectors instead.
-- Resolved in code by app/Models/MemberServiceModel.php (getServiceIdsByMemberIds()).
--

CREATE TABLE `member_services` (
  `ID` int(11) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `serviceID` int(11) NOT NULL,
  `memberID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_services`
--

INSERT INTO `member_services` (`ID`, `dt_created`, `dt_updated`, `serviceID`, `memberID`) VALUES
(1, '2026-05-18 02:32:47', '2026-05-18 02:32:47', 0, 1),
(2, '2026-05-18 02:32:47', '2026-05-18 02:32:47', 5, 1),
(3, '2026-05-18 02:32:47', '2026-05-18 02:32:47', 11, 2);

-- --------------------------------------------------------

--
-- Table structure for table `sector`
--
-- Lookup table for sectors. `sectorID` is the value referenced (by number)
-- inside each member's `member`.`sectorID` JSON array, e.g. '[1,2,3]'.
--

CREATE TABLE `sector` (
  `sectorID` int(11) NOT NULL,
  `shortcode` enum('PWD1','PWD2','PWD3','PWD4','PWD5','SP1','SP2','OSCA1','OSCA2','OSCA3','OSCA4','OSCA5','OSCA6','OSCA7') NOT NULL,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sector`
--

INSERT INTO `sector` (`sectorID`, `shortcode`, `name`, `description`, `dt_created`, `dt_updated`) VALUES
(1, 'PWD1', 'Registered PWD in BiÃ±an', 'Official city registration for PWD', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(2, 'PWD2', 'BiÃ±an City Development Center', 'Member of the local development center', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(3, 'PWD3', 'Birthday Cash Gift', 'Annual cash gift benefit', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(4, 'PWD4', 'Project Aruga', 'Local social welfare project', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(5, 'PWD5', 'Subsidy for Unemployable PWD', 'Financial aid for those unable to work', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(6, 'SP1', 'Registered Solo Parent in BiÃ±an', 'Official city registration for Solo Parents', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(7, 'SP2', 'Monthly Subsidy for Solo Parent', 'Regular monthly financial support', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(8, 'OSCA1', 'Registered OSCA BiÃ±an', 'Official city registration for Seniors', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(9, 'OSCA2', 'Local Pensioner', 'Receiving local government pension', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(10, 'OSCA3', 'National Pensioner', 'Receiving national government pension', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(11, 'OSCA4', 'One Time Cash Incentive (85yrs old)', 'Special 85th birthday incentive', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(12, 'OSCA5', 'Centenarian Local Awardee', 'Local recognition for reaching 100', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(13, 'OSCA6', 'Centenarian Province Awardee', 'Provincial recognition for reaching 100', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(14, 'OSCA7', 'Centenarian National Awardee', 'National recognition for reaching 100', '2026-05-18 02:32:47', '2026-05-18 02:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `serviceID` int(11) NOT NULL,
  `category` text DEFAULT NULL,
  `name` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`serviceID`, `category`, `name`, `description`, `dt_created`, `dt_updated`) VALUES
(0, 'FA(OSCA)', 'Philhealth Member', 'Financial Assistance for OSCA', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(1, 'FA(OSCA)', 'Medical Assistance', 'Assistance for medical costs', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(2, 'FA(OSCA)', 'Dental Assistance', 'Assistance for dental work', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(3, 'FA(OSCA)', 'Eyeglasses Assistance', 'Assistance for vision care', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(4, 'FA(OSCA)', 'Wheelchair / Crutches', 'Provision of mobility aids', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(5, 'FA(OSCA)', 'Burial Assistance', 'Assistance for funeral expenses', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(6, 'FA(OSCA)', 'Lingap sa Mahirap', 'Local welfare assistance', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(7, 'FA(OSCA)', 'Balik Probinsya', 'Transportation assistance', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(8, 'Emergency', 'Emergency Shelter (Local)', 'Local disaster shelter aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(9, 'Emergency', 'Emergency Shelter (Province)', 'Provincial disaster shelter aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(10, 'Emergency', 'Emergency Shelter (National)', 'National disaster shelter aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(11, 'Emergency', 'Relief Food Pack', 'Emergency food supply', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(12, 'Emergency', 'Cash Assistance', 'Immediate disaster cash aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(13, 'Emergency', 'Food for Work', 'Labor for food program', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(14, 'Emergency', 'Cash for Work', 'Labor for cash program', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(15, 'Emergency', 'Non-Food Assistance', 'Disaster hygiene/living kits', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(16, 'Children', 'ECCD', 'Early Childhood Care and Development', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(17, 'Children', 'Bahay Pag-Asa', 'Rehabilitation and care for minors', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(18, 'Children', 'Supplementary Feeding Program', 'Nutritional support for children', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(19, 'Social Welfare', '4Ps', 'Pantawid Pamilyang Pilipino Program', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(20, 'Social Welfare', 'Family Development Session', 'Educational family training', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(21, 'Social Welfare', 'Parent Effectiveness Service', 'Parenting skill building', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(22, 'Social Welfare', 'Gender Sensitivity Training', 'Inclusivity and awareness training', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(23, 'Social Welfare', 'Counseling / Dialogue', 'Personalized social counseling', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(24, 'Social Welfare', 'Business Skills Management Training', 'Livelihood skills development', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(25, 'Social Welfare', 'PMOC', 'Pre-Marriage Orientation and Counseling', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(26, 'Social Welfare', 'Licensed Foster Parent', 'Foster care certification program', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(27, 'FA(NS)', 'Philhealth Member', 'Financial Assistance for non-seniors', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(28, 'FA(NS)', 'Medical Assistance', 'Non-senior medical financial aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(29, 'FA(NS)', 'Dental Assistance', 'Non-senior dental financial aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(30, 'FA(NS)', 'Eyeglasses Assistance', 'Non-senior vision financial aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(31, 'FA(NS)', 'Wheelchair / Crutches', 'Non-senior mobility device aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(32, 'FA(NS)', 'Burial Assistance', 'Non-senior funeral financial aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(33, 'FA(NS)', 'Lingap sa Mahirap', 'Non-senior local welfare aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(34, 'FA(NS)', 'Balik Probinsya', 'Non-senior transport aid', '2026-05-18 02:32:47', '2026-05-18 02:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `role` enum('User','Admin','Developer') DEFAULT NULL,
  `isactive` enum('Enable','Disabled') DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `username`, `password`, `role`, `isactive`, `dt_created`, `dt_updated`) VALUES
(1, 'admin', '$argon2id$v=19$m=65536,t=3,p=4$bXlzZWNyZXRzYWx0$SampleArgonHashStringForAna', 'Admin', 'Enable', '2026-05-18 02:32:47', '2026-05-18 02:32:47'),
(2, 'developer', '$argon2id$v=19$m=65536,t=4,p=1$WU1LLmdnSEZZL2gxMG1yaw$VSxlNo1Nj1JxEf+P/mcwcSWonEi7hQfi8iZGGBiJ8eU', 'Developer', 'Enable', '2026-05-18 02:32:47', '2026-05-18 02:32:47');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_member_dashboard`
-- (See below for the actual view)
--
-- NOTE: `sector_array_string` below is simply `member`.`sectorID` (the JSON
-- array '[1,2,3]') passed straight through the view. The app reads it the same
-- way -- decode the array, look the IDs up in `sector`. See the note on `member`.
--
CREATE TABLE `view_member_dashboard` (
`memberID` int(11)
,`firstname` varchar(100)
,`lastname` varchar(100)
,`relationship` text
,`headID` int(11)
,`head_firstname` varchar(100)
,`head_lastname` varchar(100)
,`sector_array_string` varchar(255)
,`dt_deleted` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `view_member_dashboard`
--
DROP TABLE IF EXISTS `view_member_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_member_dashboard`  AS SELECT `m`.`memberID` AS `memberID`, `m`.`firstname` AS `firstname`, `m`.`lastname` AS `lastname`, `m`.`relationship` AS `relationship`, `m`.`headID` AS `headID`, `h`.`firstname` AS `head_firstname`, `h`.`lastname` AS `head_lastname`, `m`.`sectorID` AS `sector_array_string`, `m`.`dt_deleted` AS `dt_deleted` FROM (`member` `m` left join `member` `h` on(`m`.`headID` = `h`.`memberID`)) WHERE `m`.`dt_deleted` is null ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_trails`
--
ALTER TABLE `audit_trails`
  ADD PRIMARY KEY (`auditID`),
  ADD KEY `fk_audit_user` (`userID`),
  ADD KEY `fk_audit_member` (`memberID`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`memberID`),
  ADD KEY `fk_head` (`headID`);

--
-- Indexes for table `member_services`
--
ALTER TABLE `member_services`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_service` (`serviceID`),
  ADD KEY `fk_member` (`memberID`);

--
-- Indexes for table `sector`
--
ALTER TABLE `sector`
  ADD PRIMARY KEY (`sectorID`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`serviceID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_trails`
--
ALTER TABLE `audit_trails`
  MODIFY `auditID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `memberID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `member_services`
--
ALTER TABLE `member_services`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sector`
--
ALTER TABLE `sector`
  MODIFY `sectorID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_trails`
--
ALTER TABLE `audit_trails`
  ADD CONSTRAINT `fk_audit_member` FOREIGN KEY (`memberID`) REFERENCES `member` (`memberID`),
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `member`
--
ALTER TABLE `member`
  ADD CONSTRAINT `fk_head` FOREIGN KEY (`headID`) REFERENCES `member` (`memberID`);

--
-- Constraints for table `member_services`
--
ALTER TABLE `member_services`
  ADD CONSTRAINT `fk_member` FOREIGN KEY (`memberID`) REFERENCES `member` (`memberID`),
  ADD CONSTRAINT `fk_service` FOREIGN KEY (`serviceID`) REFERENCES `services` (`serviceID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
