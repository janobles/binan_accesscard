-- Binan Access Card dump V18.
-- V18 = V17 + test reference rows removed (sector 11 TS, category 8 TSC,
-- services 47-48 TS1/TSC1) + developer login (developer/developer123).
-- Batches bind subsidy_type_id: subsidy is its own reference table,
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
(1, 'developer', NULL, '$argon2id$v=19$m=65536,t=4,p=1$SDJHU0p3NHF0L2hRQkhiZA$WQqhzKPvG3nBUNLwH4naRBjjwBoUunH8soeNEgeyvzk', 'developer', 'Enable', '2026-07-12 22:05:26', '2026-07-12 22:05:26'),
(2, 'Administrator1', NULL, '$argon2id$v=19$m=65536,t=4,p=1$QS4zaTQ5bWFNVC9GaG8zbA$LnM1Ll2YUyUk6tZeNjin0EEiEuZNU9dP4WK+cXyRUmw', 'administrator', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(3, 'Administrator2', NULL, '$argon2id$v=19$m=65536,t=4,p=1$bFNKQ1h1V1JHQUl4czFpSg$xKfnElVSeJ/HV5bFQIE1wSQwqIfU2/81ofZdRgN4nlU', 'administrator', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(4, 'Administrator3', NULL, '$argon2id$v=19$m=65536,t=4,p=1$OEJFTC9QOVduYzdEZ3V2WQ$s5Hqrnkqz+c2/kWo77JaQdkUEo9dpsXGw6vyUyiRkqU', 'administrator', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(5, 'Administrator4', NULL, '$argon2id$v=19$m=65536,t=4,p=1$aUxIM2ExLjBYeDBSSEovNQ$DLEa3hnlKAI9Dw/V8gUw+YeV4Ih+q7v92NEt2gr5KQM', 'administrator', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(6, 'Administrator5', NULL, '$argon2id$v=19$m=65536,t=4,p=1$MFNrVVgubFgxbnR0aUwwLw$QiiRhkG+GvNdtRn12BudXnQFWWgKsovkw40IJhzV6xA', 'administrator', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(7, 'Scanner1', NULL, '$argon2id$v=19$m=65536,t=4,p=1$TVlWZTBxNWdncEdLMVRoTg$iiju6JG9/opGYjHKHpnEW10czPH7/9FdF241s2IUeIk', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(8, 'Scanner2', NULL, '$argon2id$v=19$m=65536,t=4,p=1$UGQ4U2drU1pjbjJPbGNyLw$IChkuTvgwyoLRe37CZpT9STnN9HksmA1k1skxbIFCA0', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(9, 'Scanner3', NULL, '$argon2id$v=19$m=65536,t=4,p=1$cjRiV0pFdGwwMFBqUi9XYw$bOukipVUow2Jgh4QRjPPR7P/kZKjvifwozbaqor/ne4', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(10, 'Scanner4', NULL, '$argon2id$v=19$m=65536,t=4,p=1$dVVocXM3elo5TnNUQXlDbw$9Yn9YntDSW6MbKjWab8n7N+oYy/HhzgAoZZaaSJIubk', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(11, 'Scanner5', NULL, '$argon2id$v=19$m=65536,t=4,p=1$U0pac1RTL0ZkMzg4LlUyeg$hZLcNgYwMjOYJx/Rj/mmuW0+Y4ECAef8+PCopMCiQUY', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(12, 'Scanner6', NULL, '$argon2id$v=19$m=65536,t=4,p=1$ZDNLUURHZlNjYkFpREFGcQ$JLajJ3zSOEHRJ0WNpFaOVEjAe/PhNtkNKTT2IZon1rw', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(13, 'Scanner7', NULL, '$argon2id$v=19$m=65536,t=4,p=1$LnQ2OU5jOXVSbncwSEdxZw$mXpPnxFyqeq6IuOBMbmiEc4/EURzpkgO5eAW/1Pz3As', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(14, 'Scanner8', NULL, '$argon2id$v=19$m=65536,t=4,p=1$NDhNb0pQLzZidkFSMnZjLw$U3eCOhgLn8GmQmOKzVKtYPRIZoyI/YPVUaoGYcY80yo', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(15, 'Scanner9', NULL, '$argon2id$v=19$m=65536,t=4,p=1$MjZLYlRFVXVxeXdkTGVkdg$LwODBfqzPCGSEa+JtFogQqyQsBIK+cAq9Lio3Okv81A', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(16, 'Scanner10', NULL, '$argon2id$v=19$m=65536,t=4,p=1$QnVjNXhhMUtIeE9OcFpXOA$a4RlD3uI/JOQv1k0vLgJBXd5rw2A4VDpK77/PjdCxiQ', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(17, 'Scanner11', NULL, '$argon2id$v=19$m=65536,t=4,p=1$TEVrZUp5MndiVlRPL0ZqdA$DSbL8UhJ8miHL6uf5wOSmaOZonXBGGNwX7OJ8GZRn7c', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(18, 'Scanner12', NULL, '$argon2id$v=19$m=65536,t=4,p=1$c0x3dE91SmtCMnBPcTNxMw$JnxbibKd/ndqK90rNyrGD5gHKZVX9/zODSNjxBbkmJA', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(19, 'Scanner13', NULL, '$argon2id$v=19$m=65536,t=4,p=1$aElTMjVvZDM5SzFjTGE3SQ$iB95AVggJynM4sPyNQUKdU9zrIBaUE4NrN/Xebt+F3I', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(20, 'Scanner14', NULL, '$argon2id$v=19$m=65536,t=4,p=1$ZGpxWnU4djc1ZWVqR0J4Lg$UlAtr11gOG9e7SnzjUn3mWKus3yT9K7S5DSsJnphdnY', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(21, 'Scanner15', NULL, '$argon2id$v=19$m=65536,t=4,p=1$a2VBcldHTVVxWDcxblRtNg$F+h17y+tdd3lMYtC1/vRmQ9OfrDUrEnFmeOLdNtB7UU', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(22, 'Scanner16', NULL, '$argon2id$v=19$m=65536,t=4,p=1$VHdyOGVCSFpMdUQzRTJsNA$8bz2Ddng/SNH4rfkwADDRx9U50+9dWyPsHYxa/feDZE', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(23, 'Scanner17', NULL, '$argon2id$v=19$m=65536,t=4,p=1$eWRoTml4VERHdVpvd3JMSw$09UiSpO60brLAbpqWuUKhsuI1YcvyNXJymhPC6kLdwc', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(24, 'Scanner18', NULL, '$argon2id$v=19$m=65536,t=4,p=1$S042YzQ0Rkp3L0M1NWZmRQ$ynei6uhXgaTBX6kPGtVgq8YAMFPl7G6oEy1tOU1mk9E', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(25, 'Scanner19', NULL, '$argon2id$v=19$m=65536,t=4,p=1$Zkd5MWV6OUpPY1FvUVJvRQ$rhEO0zdTyrsAMEp5G4CU92TSeUPZU/IZ7UDzdCSuttg', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(26, 'Scanner20', NULL, '$argon2id$v=19$m=65536,t=4,p=1$Vnp1R2hrenFtOWVrM2Y0WA$TSlKLu6Tuy6gfN5luJm6hbJ5PjWe5L/8oJKB0Hc82RM', 'scanner', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(27, 'Encoder1', NULL, '$argon2id$v=19$m=65536,t=4,p=1$OFFqSHgwNHgwZUNJenpvUA$4vhxCpaHeFQebY1Zly/JnaW3CXrZPnvmQQyWUGguKbA', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(28, 'Encoder2', NULL, '$argon2id$v=19$m=65536,t=4,p=1$eFZXWURJdTBKR01RR2twbg$TqYcUQqtD2EWnF8VdpqJj+jX6bLXf/Sr8QKpMoRC2C0', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(29, 'Encoder3', NULL, '$argon2id$v=19$m=65536,t=4,p=1$N0k1VndEamc5emNOQlVqMg$9rh4yOLfXnU16y92UtqZl9RfD/k1D2loxj8gzKrrRGA', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(30, 'Encoder4', NULL, '$argon2id$v=19$m=65536,t=4,p=1$WFRpcFk1bE1iTUFoVlN3NA$xcDJWSAyZ3US+EY75JSUgPvWZco1BCzQjKnKy9Ve9to', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(31, 'Encoder5', NULL, '$argon2id$v=19$m=65536,t=4,p=1$MGJvTGIxLlJoR01zcjdDZg$zNemg3DGydMFatmOPG7uYzoM18GVuCxDUrNoisG74P4', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(32, 'Encoder6', NULL, '$argon2id$v=19$m=65536,t=4,p=1$dTZGbTc4eWtIY1hxamR6Yg$JTPScDmxK64WeEwSGJfJmJZUnRwjXO67Ka2v1iusym0', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(33, 'Encoder7', NULL, '$argon2id$v=19$m=65536,t=4,p=1$RHRwaHdKMWVISWF3U3lRNQ$+3w2mYGW5XreuFUNFmamyszVtISJYd4FCVDIgioCfjw', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(34, 'Encoder8', NULL, '$argon2id$v=19$m=65536,t=4,p=1$d3pQdXVabjUvQlBpWHpZRg$1Ng/5Pq4Ww/+gfyiYImmS9JN7/Pm9zkw8Qrug34acpo', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(35, 'Encoder9', NULL, '$argon2id$v=19$m=65536,t=4,p=1$MWgyVmY4ZnN5VWhzWjhTNA$eS/3iZ4E5bH6o1sc+mbX8AIpOHaJhG+FH1XQlLahcDg', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(36, 'Encoder10', NULL, '$argon2id$v=19$m=65536,t=4,p=1$N09JZ0tVcm9Ra3VvZkNSbw$yCVStMtZuhvnWxu4XgutXUUBuMEk5qxqh/nyx2CCDDE', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(37, 'Encoder11', NULL, '$argon2id$v=19$m=65536,t=4,p=1$dXlId0drUjNRLjFTczl0dg$Ii5eEzFDjRkNYQwYWpCeRInlvA9SlA0duiRuK7dKEN4', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(38, 'Encoder12', NULL, '$argon2id$v=19$m=65536,t=4,p=1$Q1I3bFR0eC5veFBIZlFhdw$6PW6sWEY+dHPbVtEEn+8iAGpyTg34NWjuOtPVrSkYCU', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(39, 'Encoder13', NULL, '$argon2id$v=19$m=65536,t=4,p=1$emk1SlVISXpDRnNWNjlhcA$ETwazbO0OcpFebkpaqtVfGnLx5oZg73Loih4R53Indw', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(40, 'Encoder14', NULL, '$argon2id$v=19$m=65536,t=4,p=1$eXdrVHlMbFlhWkVuTVJHQQ$D7Ki2tNAW+U/oO3yzul/DNwxGzVxXtI1S9otfJkZFzc', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(41, 'Encoder15', NULL, '$argon2id$v=19$m=65536,t=4,p=1$RlN0Q2FNb2hwcjk4STBGZQ$HZ3bQzMpJwF3aLD1Mu7vEQYRLclgrh5E4d+Fn4bWlqc', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(42, 'Encoder16', NULL, '$argon2id$v=19$m=65536,t=4,p=1$NVczdktsbVBUVG01QklWQw$0Tm5vBJFHPoLrhnPwph91d00XPWT4sBGgLh7nAhGUeo', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(43, 'Encoder17', NULL, '$argon2id$v=19$m=65536,t=4,p=1$bS9HdENKVUVrZzFzT2duVg$I40RoJVs9Yap8Mdz8+mD+3UTX72t5x/MszgWPMQZkHE', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(44, 'Encoder18', NULL, '$argon2id$v=19$m=65536,t=4,p=1$RjN5eXZmRFlZd0VvYlFtbA$qPMznnOD+29HgOzXiId0Kt7NaXiPgwvmsGg6BHvsobQ', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(45, 'Encoder19', NULL, '$argon2id$v=19$m=65536,t=4,p=1$Tkoxek51eS9OZndTTkxQTQ$gsY9DH2wk3c1DY1YfPXjBQO6AcSe6V/NunZ61NYMB20', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00'),
(46, 'Encoder20', NULL, '$argon2id$v=19$m=65536,t=4,p=1$b2FhU0sxaGo5M0dsWER2Lw$XRmbQLt4cDf966G6k1qoRIe2q8LwLzBZOSFez8ZHDfM', 'encoder', 'Enable', '2026-07-20 00:00:00', '2026-07-20 00:00:00');

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

DROP TABLE IF EXISTS `subsidy`;
CREATE TABLE `subsidy` (
  `subsidy_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`subsidy_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `subsidy` (`name`) VALUES ('Financial'), ('Rice'), ('Grocery');

DROP TABLE IF EXISTS `aid_distribution`;
CREATE TABLE `aid_distribution` (
  `aidID` int(11) NOT NULL AUTO_INCREMENT,
  `control_no` int(11) NOT NULL,
  `memberID` int(11) NOT NULL,
  `subsidy_type_id` int(11) NOT NULL,
  `claim_date` date NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aidID`),
  KEY `idx_ad_control` (`control_no`),
  KEY `idx_ad_type` (`subsidy_type_id`),
  KEY `idx_ad_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Distribution batches: one row per giving event; closed_at NULL = the single
-- open batch. Closing a batch is the manual statistics reset.
--
DROP TABLE IF EXISTS `distribution_batch`;
CREATE TABLE `distribution_batch` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subsidy_type_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `idx_db_aidtype` (`subsidy_type_id`)
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
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

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
