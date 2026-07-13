-- V17 -> V18 in-place upgrade. The batch/scan schema is unchanged from V17
-- (batches bind aid_type_id). This patch only removes the test reference
-- rows and resets the developer login to developer/developer123.

TRUNCATE TABLE aid_distribution;
TRUNCATE TABLE distribution_batch;

DELETE FROM services WHERE serviceID IN (47, 48);
DELETE FROM category WHERE categoryID = 8;
DELETE FROM sector   WHERE sectorID = 11;

-- Databases imported before the developer-enforcement PR lack the
-- 'developer' enum value; align with the V18 dump.
ALTER TABLE users
  MODIFY account_level ENUM('viewer','scanner','administrator','developer','encoder') NOT NULL DEFAULT 'encoder';

-- Hash from: php -r "echo password_hash('developer123', PASSWORD_ARGON2ID);"
INSERT INTO users (username, password, account_level, isactive)
SELECT 'developer',
       '$argon2id$v=19$m=65536,t=4,p=1$UHVBVzJEMFV2VDNhaU5xTg$hzjRbNAe6Pw4DFwVP9VApkJtRhRfnuSHsv7laHnXHiQ',
       'developer', 'Enable'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'developer');

UPDATE users SET
  password = '$argon2id$v=19$m=65536,t=4,p=1$UHVBVzJEMFV2VDNhaU5xTg$hzjRbNAe6Pw4DFwVP9VApkJtRhRfnuSHsv7laHnXHiQ',
  account_level = 'developer',
  isactive = 'Enable'
WHERE username = 'developer';
