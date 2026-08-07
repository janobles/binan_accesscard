-- V20: batch eligibility rosters, barangay reference data, soft void.
-- Coverage had no per-batch denominator and barangay was parsed out of the
-- free-text address column. Apply against a V19 db.

CREATE TABLE `barangay` (
  `barangayID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`barangayID`),
  UNIQUE KEY `uq_barangay_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `member`
  ADD `barangayID` int(11) DEFAULT NULL,
  ADD KEY `idx_member_brgy` (`barangayID`);

ALTER TABLE `distribution_batch`
  ADD `eligible_count` int(11) NOT NULL DEFAULT 0;

ALTER TABLE `subsidy_distribution`
  ADD `dt_voided` timestamp NULL DEFAULT NULL,
  ADD KEY `idx_sd_batch_voided` (`batch_id`, `dt_voided`);

CREATE TABLE `batch_barangay` (
  `batch_id` int(11) NOT NULL,
  `barangayID` int(11) NOT NULL,
  PRIMARY KEY (`batch_id`, `barangayID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `batch_sector` (
  `batch_id` int(11) NOT NULL,
  `sectorID` int(11) NOT NULL,
  PRIMARY KEY (`batch_id`, `sectorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The frozen masterlist. One row per eligible family head, written once when
-- the batch opens. This is the coverage denominator.
CREATE TABLE `batch_eligibility` (
  `batch_id` int(11) NOT NULL,
  `headID` int(11) NOT NULL,
  PRIMARY KEY (`batch_id`, `headID`),
  KEY `idx_be_head` (`headID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Barangay roster, verified against Wikipedia and PhilAtlas/PSA.
INSERT INTO `barangay` (`name`) VALUES
('Biñan'),('Bungahan'),('Canlalay'),('Casile'),('De La Paz'),('Ganado'),
('Langkiwa'),('Loma'),('Malaban'),('Malamig'),('Mamplasan'),('Platero'),
('Poblacion'),('San Antonio'),('San Francisco'),('San Jose'),('San Vicente'),
('Santo Domingo'),('Santo Niño'),('Santo Tomas'),('Soro-soro'),('Timbao'),
('Tubigan'),('Zapote');
