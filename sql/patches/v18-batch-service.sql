-- V17 -> V18 in-place upgrade. Wipes demo batch/scan data (approved),
-- swaps aid_type for the services reference table, removes test reference
-- rows, and resets the developer login to developer/developer123.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE aid_distribution;
TRUNCATE TABLE distribution_batch;

ALTER TABLE aid_distribution
  DROP INDEX idx_ad_type,
  CHANGE COLUMN aid_type_id service_id INT(11) NOT NULL,
  ADD KEY idx_ad_service (service_id);

ALTER TABLE distribution_batch
  DROP INDEX idx_db_aidtype,
  CHANGE COLUMN aid_type_id service_id INT(11) NOT NULL,
  ADD KEY idx_db_service (service_id);

DROP TABLE IF EXISTS aid_type;

DELETE FROM services WHERE serviceID IN (47, 48);
DELETE FROM category WHERE categoryID = 8;
DELETE FROM sector   WHERE sectorID = 11;

-- Hash from: php -r "echo password_hash('developer123', PASSWORD_ARGON2ID);"
UPDATE users SET password = '$argon2id$v=19$m=65536,t=4,p=1$UHVBVzJEMFV2VDNhaU5xTg$hzjRbNAe6Pw4DFwVP9VApkJtRhRfnuSHsv7laHnXHiQ'
WHERE username = 'developer';

SET FOREIGN_KEY_CHECKS = 1;
