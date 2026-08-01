-- V19: finish the aid-to-subsidy rename started in V18.
-- V18 renamed the type table to `subsidy` and its key to `subsidy_type_id` but
-- left the distribution log and one index spelled `aid`. Apply against a V18 db.

ALTER TABLE `aid_distribution`
  RENAME TO `subsidy_distribution`;

ALTER TABLE `subsidy_distribution`
  CHANGE `aidID` `distribution_id` int(11) NOT NULL AUTO_INCREMENT;

-- MariaDB < 10.5 has no RENAME INDEX; drop and recreate under the new name.
ALTER TABLE `distribution_batch`
  DROP INDEX `idx_db_aidtype`,
  ADD INDEX `idx_db_subsidy` (`subsidy_type_id`);
