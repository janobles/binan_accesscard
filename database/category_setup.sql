-- =====================================================================
-- category_setup.sql
-- ---------------------------------------------------------------------
-- Formalizes sector categories into a real `category` table and links
-- the existing `sector` rows to it via a new `sector.categoryID` column.
--
-- Run ONCE against the `accesscard` database (e.g. phpMyAdmin > Import,
-- or:  mysql -uroot accesscard < category_setup.sql).
--
-- Safe to re-run: every INSERT is idempotent (INSERT IGNORE / ON DUPLICATE
-- KEY UPDATE) and the ALTER is guarded by hand (drop the column first if
-- you need to re-run step 5).
--
-- NOTES
--  * Step 4 seeds the custom-category display names that previously lived
--    in writable/sector_categories.json. It is HAND-GENERATED from that
--    file's current contents. If the JSON changes before you import this
--    script, regenerate the VALUES in step 4 to match.
--  * Uses REGEXP_REPLACE (MariaDB 10.0.5+ / MySQL 8+). The target XAMPP is
--    MariaDB 10.4, which supports it. On legacy MySQL 5.x, replace the
--    prefix expression with a LEFT()/locate-based equivalent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. CREATE the category table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `category` (
  `categoryID`  INT(11)       NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)   NOT NULL,
  `name`        VARCHAR(150)  NOT NULL,
  `is_official` TINYINT(1)    NOT NULL DEFAULT 0,
  `dt_created`  TIMESTAMP     NOT NULL DEFAULT current_timestamp(),
  `dt_updated`  TIMESTAMP     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted`  DATETIME      DEFAULT NULL,
  PRIMARY KEY (`categoryID`),
  UNIQUE KEY `uq_category_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 2. SEED the official categories (mirrors
--    App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES, minus 'OTHER').
--    is_official = 1  -> protected: can be renamed, never archived/deleted.
-- ---------------------------------------------------------------------
INSERT INTO `category` (`code`, `name`, `is_official`) VALUES
  ('SC',   'Senior Citizen',              1),
  ('PWD',  'Person with Disability',      1),
  ('SP',   'Solo Parent',                 1),
  ('B',    'Bata (Children)',             1),
  ('LGBT', 'LGBTQIA+',                    1),
  ('OFW',  'Overseas Filipino Worker',    1),
  ('IP',   'Indigenous People',           1),
  ('IDP',  'Internally Displaced Person', 1),
  ('PDL',  'Persons Deprived of Liberty', 1)
ON DUPLICATE KEY UPDATE `is_official` = 1;

-- ---------------------------------------------------------------------
-- 3. DERIVE custom categories from existing non-official sector prefixes.
--    Prefix = leading letters of the shortcode (trailing digits stripped),
--    folding OSCA/OSWA into SC. Name defaults to the prefix; display names
--    are applied in step 4.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `category` (`code`, `name`, `is_official`)
SELECT prefix, prefix, 0
FROM (
  SELECT DISTINCT
    CASE
      WHEN REGEXP_REPLACE(UPPER(`shortcode`), '[0-9]+$', '') IN ('OSCA', 'OSWA') THEN 'SC'
      ELSE REGEXP_REPLACE(UPPER(`shortcode`), '[0-9]+$', '')
    END AS prefix
  FROM `sector`
) AS derived
WHERE prefix <> ''
  AND prefix NOT IN ('SC', 'PWD', 'SP', 'B', 'LGBT', 'OFW', 'IP', 'IDP', 'PDL');

-- ---------------------------------------------------------------------
-- 4. APPLY custom display names (hand-generated from
--    writable/sector_categories.json). Current snapshot:
--      {"SBS":"Sabotage","SS":"SS - Super Special"}
--    SBS has no sectors yet -> still seeded so the named category survives.
-- ---------------------------------------------------------------------
INSERT INTO `category` (`code`, `name`, `is_official`) VALUES
  ('SS',  'SS - Super Special', 0),
  ('SBS', 'Sabotage',           0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ---------------------------------------------------------------------
-- 5. ADD the categoryID FK column to `sector` (nullable, soft link).
--    If re-running, first:  ALTER TABLE `sector` DROP COLUMN `categoryID`;
-- ---------------------------------------------------------------------
ALTER TABLE `sector`
  ADD COLUMN `categoryID` INT(11) DEFAULT NULL AFTER `shortcode`,
  ADD KEY `idx_sector_categoryID` (`categoryID`);

-- Optional hard FK (kept commented so backfill never fails on an
-- unresolved prefix; categoryID stays nullable and the app guards it):
-- ALTER TABLE `sector`
--   ADD CONSTRAINT `fk_sector_category` FOREIGN KEY (`categoryID`)
--   REFERENCES `category` (`categoryID`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- 6. BACKFILL sector.categoryID by matching each shortcode's prefix to a
--    category code (same OSCA/OSWA -> SC folding as step 3).
-- ---------------------------------------------------------------------
UPDATE `sector` s
JOIN `category` c
  ON c.`code` =
     CASE
       WHEN REGEXP_REPLACE(UPPER(s.`shortcode`), '[0-9]+$', '') IN ('OSCA', 'OSWA') THEN 'SC'
       ELSE REGEXP_REPLACE(UPPER(s.`shortcode`), '[0-9]+$', '')
     END
SET s.`categoryID` = c.`categoryID`;
