-- V21 -> V22, final step: drop the two text columns the normalized tables
-- replace. Run ONLY after both backfills have reported every row copied:
--   php spark members:split-sectors --dry-run
--   php spark services:link-categories --dry-run
-- Each prints what it would write; a clean dry run means nothing is left in
-- the text column that the new table does not already hold.
--
-- Separate from v22-normalize.sql on purpose: that file is additive and safe
-- to run before the data moves, this one destroys the old copy.

-- view_member_dashboard selects member.sectorID and nothing in the app reads
-- the view (the last reference was a default argument on a helper V22 removes),
-- so it goes rather than being rebuilt around a column that no longer exists.
DROP VIEW IF EXISTS `view_member_dashboard`;

ALTER TABLE `member` DROP COLUMN `sectorID`;

ALTER TABLE `services` DROP COLUMN `category`;

-- A service is grouped by a standalone category OR by a sector (a sector is
-- its own service category - see categories:dedupe-sectors). Both keys are
-- nullable so either shape is expressible; the CHECK is what stops a row from
-- claiming both or neither, which the old text column could not.
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_category` FOREIGN KEY IF NOT EXISTS (`categoryID`) REFERENCES `category` (`categoryID`),
  ADD CONSTRAINT `fk_services_sector` FOREIGN KEY IF NOT EXISTS (`sectorID`) REFERENCES `sector` (`sectorID`),
  ADD CONSTRAINT `chk_services_group` CHECK ((`categoryID` IS NULL) <> (`sectorID` IS NULL));
