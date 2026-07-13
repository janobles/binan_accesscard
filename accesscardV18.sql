-- Binan Access Card dump V18.
-- V18 = V17 + test reference rows removed (sector 11 TS, category 8 TSC,
-- services 47-48 TS1/TSC1) + developer login (developer/developer123).
-- Batches bind aid_type_id: aid_type is its own reference table,
-- unrelated to services/programs.
--
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2026 at 06:04 AM
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
  `full_description` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `userID` int(11) DEFAULT NULL,
  `memberID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trails`
--


-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `categoryID` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`categoryID`, `code`, `name`, `dt_created`, `dt_updated`, `dt_deleted`) VALUES
(5, 'FA', 'Financial Assistance Programs', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(6, 'SWPS', 'Social Welfare Programs and Services', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(7, 'EDA', 'Emergency / Disaster Assistance Programs', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_queue`
--

CREATE TABLE `job_queue` (
  `jobID` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `payload` longtext DEFAULT NULL,
  `status` enum('pending','processing','done','partial','failed') NOT NULL DEFAULT 'pending',
  `progress_total` int(11) NOT NULL DEFAULT 0,
  `progress_done` int(11) NOT NULL DEFAULT 0,
  `checkpoint` int(11) NOT NULL DEFAULT 0,
  `result_json` longtext DEFAULT NULL,
  `message` varchar(500) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 1,
  `available_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by` varchar(64) DEFAULT NULL,
  `dt_created` datetime NOT NULL,
  `dt_started` datetime DEFAULT NULL,
  `dt_finished` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_queue`
--


-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `memberID` int(11) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(50) NOT NULL,
  `suffix` enum('Jr','Sr','I','II','III','IV','V') DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `civilstatus` varchar(100) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `education` text DEFAULT NULL,
  `job` text DEFAULT NULL,
  `Salary` float DEFAULT NULL,
  `contactnumber` varchar(20) DEFAULT NULL,
  `relationship` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` timestamp NULL DEFAULT NULL,
  `headID` int(11) NOT NULL,
  `sectorID` varchar(255) NOT NULL DEFAULT '[]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member`
--


-- --------------------------------------------------------

--
-- Table structure for table `member_services`
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


-- --------------------------------------------------------

--
-- Table structure for table `sector`
--

CREATE TABLE `sector` (
  `sectorID` int(11) NOT NULL,
  `shortcode` varchar(30) NOT NULL,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sector`
--

INSERT INTO `sector` (`sectorID`, `shortcode`, `name`, `description`, `dt_created`, `dt_updated`, `dt_deleted`) VALUES
(1, 'SC', 'Senior Citizen', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(2, 'PWD', 'Person with Disability', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(3, 'SP', 'Solo Parent', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(4, 'B', 'Bata (Children)', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(5, 'LGBT', 'LGBTQIA+', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(6, 'OFW', 'Overseas Filipino Worker', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(7, 'IP', 'Indigenous People', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(8, 'IDP', 'Internally Displaced Person', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(9, 'PDL', 'Persons Deprived of Liberty', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(10, 'OTHER', 'Other Sectors', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `serviceID` int(11) NOT NULL,
  `shortcode` varchar(30) DEFAULT NULL,
  `category` text DEFAULT NULL,
  `name` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`serviceID`, `shortcode`, `category`, `name`, `description`, `dt_created`, `dt_updated`, `dt_deleted`) VALUES
(1, 'EDA1', 'Emergency / Disaster Assistance Programs', 'Cash Assistance', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(2, 'EDA2', 'Emergency / Disaster Assistance Programs', 'Cash for Work', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(3, 'EDA3', 'Emergency / Disaster Assistance Programs', 'Emergency Shelter (Local)', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(4, 'EDA4', 'Emergency / Disaster Assistance Programs', 'Emergency Shelter (National / NHA)', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(5, 'EDA5', 'Emergency / Disaster Assistance Programs', 'Emergency Shelter (Province)', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(6, 'EDA6', 'Emergency / Disaster Assistance Programs', 'Food for Work', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(7, 'EDA7', 'Emergency / Disaster Assistance Programs', 'Non-Food Assistance', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(8, 'EDA8', 'Emergency / Disaster Assistance Programs', 'Relief Food Pack', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(9, 'EDA9', 'Emergency / Disaster Assistance Programs', 'Temporary Shelter', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(10, 'FA1', 'Financial Assistance Programs', 'Balik Probinsya', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(11, 'FA2', 'Financial Assistance Programs', 'Burial Assistance', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(12, 'FA3', 'Financial Assistance Programs', 'Dental Assistance', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(13, 'FA4', 'Financial Assistance Programs', 'Eyeglasses Assistance', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(14, 'FA5', 'Financial Assistance Programs', 'Lingap sa Mahirap', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(15, 'FA6', 'Financial Assistance Programs', 'Medical Assistance', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(16, 'SWPS1', 'Social Welfare Programs and Services', 'Balay Silangan', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(17, 'SWPS2', 'Social Welfare Programs and Services', 'Business Skills Management Training', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(18, 'SWPS3', 'Social Welfare Programs and Services', 'Counseling / Dialogue', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(19, 'SWPS4', 'Social Welfare Programs and Services', 'Family Development Session', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(20, 'SWPS5', 'Social Welfare Programs and Services', 'Gender Sensitivity Training', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(21, 'SWPS6', 'Social Welfare Programs and Services', 'Legal Assistance / Free Notary', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(22, 'SWPS7', 'Social Welfare Programs and Services', 'Licensed Foster Parent', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(23, 'SWPS8', 'Social Welfare Programs and Services', 'Pamaskong Handog', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(24, 'SWPS9', 'Social Welfare Programs and Services', 'Parent Effectiveness Service', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(25, 'SWPS10', 'Social Welfare Programs and Services', 'PMOC (Pre-Marriage Orientation / Counseling)', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(26, 'SWPS11', 'Social Welfare Programs and Services', 'Referral', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(27, '4PS', 'Social Welfare Programs and Services', '4Ps (Pantawid Pamilyang Pilipino Programs)', '', '2026-06-29 07:22:54', '2026-06-29 07:22:54', NULL),
(28, 'SC1', 'Senior Citizen', 'Registered OSCA Biñan', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(29, 'SC2', 'Senior Citizen', 'Local Pensioner', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(30, 'SC3', 'Senior Citizen', 'National Pensioner', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(31, 'SC4', 'Senior Citizen', 'Centenarian Local Awardee', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(32, 'SC5', 'Senior Citizen', 'Centenarian National Awardee', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(33, 'SC6', 'Senior Citizen', 'Centenarian Province Awardee', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(34, 'SC7', 'Senior Citizen', 'Eyeglasses Assistance', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(35, 'SC8', 'Senior Citizen', 'One Time Cash Incentive (85yrs old)', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(36, 'SC9', 'Senior Citizen', 'Wheelchair / Crutches', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(37, 'PWD1', 'Person with Disability', 'Registered PWD in Biñan', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(38, 'PWD2', 'Person with Disability', 'Biñan City Development Center', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(39, 'PWD3', 'Person with Disability', 'Birthday Cash Gift', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(40, 'PWD4', 'Person with Disability', 'Project Aruga', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(41, 'PWD5', 'Person with Disability', 'Subsidy for Unemployable PWD', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(42, 'SP1', 'Solo Parent', 'Registered Solo Parent in Biñan', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(43, 'SP2', 'Solo Parent', 'Monthly Subsidy for Solo Parent', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(44, 'B1', 'Bata (Children)', 'Bahay Pag-Asa', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(45, 'B2', 'Bata (Children)', 'ECCD', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL),
(46, 'B3', 'Bata (Children)', 'Supplementary Feeding Program', '', '2026-07-01 01:13:55', '2026-07-01 01:13:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `full_description` varchar(255) DEFAULT NULL,
  `password` text NOT NULL,
  `account_level` enum('viewer','scanner','administrator','developer','encoder') NOT NULL DEFAULT 'encoder',
  `isactive` enum('Enable','Disabled') DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `username`, `full_description`, `password`, `account_level`, `isactive`, `dt_created`, `dt_updated`) VALUES
(1, 'admin', NULL, '$argon2id$v=19$m=65536,t=4,p=1$LmM5bWgzQnFIOTRPZHJzbg$qwukn0E/F4uV6rXD8UTPEcZR5mS6gKPC69dpV+xW3Js', 'administrator', 'Enable', '2026-05-18 02:32:47', '2026-06-15 07:08:41'),
(3, 'Admin_Mel', NULL, '$argon2id$v=19$m=65536,t=4,p=1$aWVsWUlBVlpmTmFQVXEySQ$G8dtlmtL2U7hY8qZr+nW2SJGsRFmKUO3XJFJEnBgsO0', 'administrator', 'Enable', '2026-05-29 04:11:24', '2026-06-11 07:32:25'),
(4, 'Employee_Mel', NULL, '$argon2id$v=19$m=65536,t=4,p=1$Q1hydHUyczRmZ0pWV1VJLw$ytij+sFXZEPfMtE2Fu8sBy2LF4jnfkeuMdZZL2mtuNE', 'encoder', 'Enable', '2026-05-29 04:11:41', '2026-06-11 07:32:25'),
(5, 'test', NULL, '$argon2id$v=19$m=65536,t=4,p=1$T3pSVkxSOFgzczM5cVlMNA$avLsIKZdvC7huK3D7rRD3d/iMJH2+kqwWy/M8J1b/oA', 'administrator', 'Enable', '2026-06-08 07:39:19', '2026-06-11 07:32:25'),
(6, 'Employee_JC', NULL, '$argon2id$v=19$m=65536,t=4,p=1$d3hPa0t3MC9BcnVnQUxRYw$cVqxUnDI0s3SMhhUgek5FXS08wN8HJHTeupTQVfyZUg', 'encoder', 'Disabled', '2026-06-10 06:23:19', '2026-06-11 07:32:25'),
(7, 'Emp_RomelATibay', 'LN:Tibay; FN:Romel; MN:Sarmiento; ADDR:Santa Rosa Laguna; CN:123456781910; BD:2000-05-31', '$argon2id$v=19$m=65536,t=4,p=1$SHo4MlhhNXRKbU0uckk5TA$JpnxWW5W8QAV+JMb6GYHMPJBexm+CsxRobfaYS6h2z0', 'administrator', 'Enable', '2026-06-11 07:51:28', '2026-06-11 07:51:28'),
(8, 'Administrator_Mel', 'LN:Tibay; FN:Romel; MN:Sarmiento; ADDR:Santa Rosa Laguna; CN:1234567819; BD:2000-05-31', '$argon2id$v=19$m=65536,t=4,p=1$ZDJRR0JYcXNSeHZscGJRMw$BaR00ZtPWvFXnC2C/+eH6YzVtig6z7KV+xYi5axzy/E', 'administrator', 'Enable', '2026-06-15 06:12:40', '2026-06-15 07:18:20'),
(9, 'Encoder_Mel', 'LN:Tibay; FN:Romel Andres; MN:Sarmiento; ADDR:Santa Rosa Laguna; CN:12345678910; BD:2000-05-31', '$argon2id$v=19$m=65536,t=4,p=1$cWIycjlnd1BKUzV0eHhpMw$hMUEtyWR04QbP3TIXi1e6B0OtWSAfLRSV+zpv5ovJb4', 'encoder', 'Enable', '2026-06-15 07:15:55', '2026-06-15 07:15:55'),
(10, 'Viewer_Mel', 'LN:Tibay; FN:Romel Andres; MN:Tibay; ADDR:Santa Rosa Laguna; CN:123456781910; BD:2000-05-31', '$argon2id$v=19$m=65536,t=4,p=1$ZVVMVGZJeG5qU2lHZUltLw$SAwUK4vrjkbuGrgtt3a5FQDYD6J4Zu6OLHbNEihi3Ow', 'viewer', 'Enable', '2026-06-15 07:16:58', '2026-06-16 01:13:48'),
(11, 'adminjade', 'LN:Nobles; FN:Jade; MN:Tasoy; ADDR:Pulo, Cabuyao Laguna; CN:09821078512; BD:2003-07-05', '$argon2id$v=19$m=65536,t=4,p=1$akRyV3NhSlRaNUx6anpvRg$F/u37Ji3CfVmxrSysnOdrA6O5twZm0mk1teJNZ8lv2I', 'administrator', 'Enable', '2026-06-17 05:58:24', '2026-06-17 06:00:17'),
(12, 'developer', NULL, '$argon2id$v=19$m=65536,t=4,p=1$SDJHU0p3NHF0L2hRQkhiZA$WQqhzKPvG3nBUNLwH4naRBjjwBoUunH8soeNEgeyvzk', 'developer', 'Enable', '2026-07-12 22:05:26', '2026-07-12 22:05:26');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_member_dashboard`
-- (See below for the actual view)
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
  ADD KEY `fk_audit_member` (`memberID`),
  ADD KEY `idx_audit_created` (`dt_created`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`categoryID`),
  ADD UNIQUE KEY `uq_category_code` (`code`);

--
-- Indexes for table `job_queue`
--
ALTER TABLE `job_queue`
  ADD PRIMARY KEY (`jobID`),
  ADD KEY `idx_claim` (`status`,`available_at`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`memberID`),
  ADD KEY `fk_head` (`headID`),
  ADD KEY `idx_member_deleted_name` (`dt_deleted`,`lastname`,`firstname`),
  ADD KEY `idx_member_created` (`dt_created`);

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
  MODIFY `auditID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `categoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `job_queue`
--
ALTER TABLE `job_queue`
  MODIFY `jobID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
--
-- Scanner module: QR control mapping, aid types, aid distribution log
--
DROP TABLE IF EXISTS `qr_control`;
CREATE TABLE `qr_control` (
  `control_no` int(11) NOT NULL,
  `headID` int(11) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`control_no`),
  KEY `idx_qr_head` (`headID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `aid_type`;
CREATE TABLE `aid_type` (
  `aid_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`aid_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `aid_type` (`name`) VALUES ('Financial'), ('Rice'), ('Grocery');

DROP TABLE IF EXISTS `aid_distribution`;
CREATE TABLE `aid_distribution` (
  `aidID` int(11) NOT NULL AUTO_INCREMENT,
  `control_no` int(11) NOT NULL,
  `memberID` int(11) NOT NULL,
  `aid_type_id` int(11) NOT NULL,
  `claim_date` date NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aidID`),
  KEY `idx_ad_control` (`control_no`),
  KEY `idx_ad_type` (`aid_type_id`),
  KEY `idx_ad_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Temporary scanner table used until family encoding is complete.
DROP TABLE IF EXISTS `temp_aid_distribution`;
CREATE TABLE `temp_aid_distribution` (
  `temp_aidID` int(11) NOT NULL AUTO_INCREMENT,
  `control_no` int(11) NOT NULL,
  `aid_type_id` int(11) NOT NULL,
  `claim_date` date NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`temp_aidID`),
  UNIQUE KEY `uq_temp_aid_batch_control` (`batch_id`, `control_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Distribution batches: one row per giving event; closed_at NULL = the single
-- open batch. Closing a batch is the manual statistics reset.
--
DROP TABLE IF EXISTS `distribution_batch`;
CREATE TABLE `distribution_batch` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `aid_type_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `idx_db_aidtype` (`aid_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `memberID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `member_services`
--
ALTER TABLE `member_services`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `sector`
--
ALTER TABLE `sector`
  MODIFY `sectorID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
