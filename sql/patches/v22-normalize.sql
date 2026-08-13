-- V21 -> V22 (3 of 3): normalize the two relations that were stored as text,
-- and constrain the keys that were never constrained.
--
-- RUN ORDER:
--   1. mysql -u root accesscard < sql/patches/v22-normalize.sql   (this file, part A below)
--   2. php spark members:split-sectors      copies member.sectorID JSON into member_sectors
--   3. php spark services:link-categories   resolves services.category text to categoryID
--   4. mysql -u root accesscard < sql/patches/v22-normalize-drop.sql
-- Parts 2-3 are PHP because MariaDB 10.4 (what XAMPP ships) has no JSON_TABLE,
-- and because the category match reuses the same fold the app uses.

-- ---------------------------------------------------------------------------
-- member sectors: a many-to-many stored as a JSON string in a varchar
-- ---------------------------------------------------------------------------
-- member.sectorID held '[]' / '[3,7]'. Nothing could join, index, or constrain
-- it, so every sector filter matched inside the string (MemberModel::
-- applySectorFilter, EligibilityBuilder) and a deleted sector left dangling
-- ids behind. member_services next to it already had the right shape; this is
-- the same shape for sectors.
CREATE TABLE IF NOT EXISTS `member_sectors` (
  `memberID` int(11) NOT NULL,
  `sectorID` int(11) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`memberID`,`sectorID`),
  KEY `idx_ms_sector` (`sectorID`),
  CONSTRAINT `fk_ms_member` FOREIGN KEY (`memberID`) REFERENCES `member` (`memberID`),
  CONSTRAINT `fk_ms_sector` FOREIGN KEY (`sectorID`) REFERENCES `sector` (`sectorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- services.category: the category NAME copied as free text
-- ---------------------------------------------------------------------------
-- A service is grouped EITHER by a standalone category row (FA/SWPS/EDA) OR by
-- a sector, because a sector acts as its own service category - that is what
-- `php spark categories:dedupe-sectors` established when it deleted the
-- category rows duplicating a sector. The text column was pointing at two
-- different tables, which is why it stayed text and why a rename on either
-- side silently orphaned the services filed under it.
--
-- Two nullable keys with a CHECK that exactly one is set says the same thing
-- and can be enforced. The CHECK also rules out the third state the text
-- column allowed: a service grouped under nothing at all.
ALTER TABLE `services`
  ADD COLUMN IF NOT EXISTS `categoryID` int(11) DEFAULT NULL AFTER `shortcode`,
  ADD COLUMN IF NOT EXISTS `sectorID` int(11) DEFAULT NULL AFTER `categoryID`,
  ADD INDEX IF NOT EXISTS `idx_services_category` (`categoryID`),
  ADD INDEX IF NOT EXISTS `idx_services_sector` (`sectorID`);

-- ---------------------------------------------------------------------------
-- keys that were never keys
-- ---------------------------------------------------------------------------
-- Only audit_trails and member_services carried foreign keys. Everything below
-- could hold an id pointing at a row that no longer exists, with no way to
-- detect it short of a manual join. Each ALTER fails loudly if such a row is
-- already there, which is the intended outcome: fix the data, then re-run.
ALTER TABLE `qr_control`
  ADD CONSTRAINT `fk_qr_head` FOREIGN KEY IF NOT EXISTS (`headID`) REFERENCES `member` (`memberID`);

ALTER TABLE `subsidy_distribution`
  ADD CONSTRAINT `fk_sd_control` FOREIGN KEY IF NOT EXISTS (`control_no`) REFERENCES `qr_control` (`control_no`),
  ADD CONSTRAINT `fk_sd_member` FOREIGN KEY IF NOT EXISTS (`memberID`) REFERENCES `member` (`memberID`),
  ADD CONSTRAINT `fk_sd_type` FOREIGN KEY IF NOT EXISTS (`subsidy_type_id`) REFERENCES `subsidy` (`subsidy_type_id`),
  ADD CONSTRAINT `fk_sd_user` FOREIGN KEY IF NOT EXISTS (`userID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `fk_sd_batch` FOREIGN KEY IF NOT EXISTS (`batch_id`) REFERENCES `distribution_batch` (`batch_id`);

ALTER TABLE `batch_barangay`
  ADD CONSTRAINT `fk_bb_batch` FOREIGN KEY IF NOT EXISTS (`batch_id`) REFERENCES `distribution_batch` (`batch_id`),
  ADD CONSTRAINT `fk_bb_barangay` FOREIGN KEY IF NOT EXISTS (`barangayID`) REFERENCES `barangay` (`barangayID`);

ALTER TABLE `batch_sector`
  ADD CONSTRAINT `fk_bs_batch` FOREIGN KEY IF NOT EXISTS (`batch_id`) REFERENCES `distribution_batch` (`batch_id`),
  ADD CONSTRAINT `fk_bs_sector` FOREIGN KEY IF NOT EXISTS (`sectorID`) REFERENCES `sector` (`sectorID`);

ALTER TABLE `batch_eligibility`
  ADD CONSTRAINT `fk_be_batch` FOREIGN KEY IF NOT EXISTS (`batch_id`) REFERENCES `distribution_batch` (`batch_id`),
  ADD CONSTRAINT `fk_be_head` FOREIGN KEY IF NOT EXISTS (`headID`) REFERENCES `member` (`memberID`);

-- ---------------------------------------------------------------------------
-- lookup-table integrity
-- ---------------------------------------------------------------------------
-- category.code has a UNIQUE key; the other two lookups did not, so two rows
-- could share a shortcode and the code that resolves a shortcode to an id
-- would pick whichever came first.
ALTER TABLE `sector`
  ADD UNIQUE KEY IF NOT EXISTS `uq_sector_shortcode` (`shortcode`);
ALTER TABLE `services`
  ADD UNIQUE KEY IF NOT EXISTS `uq_services_shortcode` (`shortcode`);

-- services.serviceID was the only lookup primary key without AUTO_INCREMENT,
-- so every insert had to compute its own id (ServiceModel did it by hand, with
-- the race that implies). member_services references it, and MySQL refuses to
-- alter a column under a live foreign key, so the constraint comes off and
-- goes straight back on around the change.
ALTER TABLE `member_services` DROP FOREIGN KEY IF EXISTS `fk_service`;
ALTER TABLE `services` MODIFY `serviceID` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `member_services`
  ADD CONSTRAINT `fk_service` FOREIGN KEY IF NOT EXISTS (`serviceID`) REFERENCES `services` (`serviceID`);

-- ---------------------------------------------------------------------------
-- money and naming
-- ---------------------------------------------------------------------------
-- `Salary` was the one capitalized column in the schema (the family form
-- carries a documented workaround for it), and float is the wrong type for a
-- peso amount: it cannot represent every value exactly, so a stored figure can
-- read back a centavo off.
ALTER TABLE `member`
  CHANGE `Salary` `salary` decimal(12,2) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- card issuance
-- ---------------------------------------------------------------------------
-- The dashboard counted a qr_control row as an issued card, but that row is
-- written when a worker types the control number during profiling, so the
-- count went up for families whose card had never been generated. This records
-- the generation itself; the stat reads this column instead.
ALTER TABLE `qr_control`
  ADD COLUMN IF NOT EXISTS `card_generated_at` timestamp NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `card_generated_by` int(11) DEFAULT NULL,
  ADD INDEX IF NOT EXISTS `idx_qr_generated` (`card_generated_at`),
  ADD CONSTRAINT `fk_qr_generated_by` FOREIGN KEY IF NOT EXISTS (`card_generated_by`) REFERENCES `users` (`userID`);
