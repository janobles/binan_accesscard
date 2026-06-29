-- ---------------------------------------------------------------------------
-- sector_service_master_setup.sql
--
-- Canonical CSWD Family Profiling Form v2 master list for the `sector` and
-- `services` lookup tables (codes + names + service categories). Supersedes the
-- earlier services_shortcode_setup.sql.
--
-- Sectors (19): PWD1-5, SP1-2, SC1-9, B1-3.
-- Services (27): EDA1-9, FA1-6, SWPS1-11, 4PS.
--
-- WARNING: this is a REBUILD. It clears member_services and the services table.
-- For a database that already holds family records, run the app migration instead
-- (it also strips removed sector IDs from member.sectorID); this file is the
-- fresh-install / reference seed.
--
-- Apply:  mysql -uroot accesscard < database/sector_service_master_setup.sql
-- ---------------------------------------------------------------------------

ALTER TABLE services ADD COLUMN IF NOT EXISTS shortcode VARCHAR(30) NULL AFTER serviceID;

-- --- Sectors -------------------------------------------------------------------
-- Rename B1, drop the codes not on the form, add the two Bata programs.
UPDATE sector SET name = 'Bahay Pag-Asa' WHERE shortcode = 'B1';
DELETE FROM sector WHERE shortcode IN ('SS', 'NF1', 'SSS1');
INSERT INTO sector (shortcode, categoryID, name, description)
SELECT * FROM (SELECT 'B2' AS shortcode, 4 AS categoryID, 'ECCD' AS name, '' AS description) AS t
WHERE NOT EXISTS (SELECT 1 FROM sector WHERE shortcode = 'B2' AND dt_deleted IS NULL);
INSERT INTO sector (shortcode, categoryID, name, description)
SELECT * FROM (SELECT 'B3' AS shortcode, 4 AS categoryID, 'Supplementary Feeding Program' AS name, '' AS description) AS t
WHERE NOT EXISTS (SELECT 1 FROM sector WHERE shortcode = 'B3' AND dt_deleted IS NULL);

-- --- Services (full rebuild) ---------------------------------------------------
DELETE FROM member_services;
DELETE FROM services;

INSERT INTO services (serviceID, shortcode, category, name, description) VALUES
 (1,  'EDA1', 'Emergency / Disaster Assistance Programs', 'Cash Assistance', ''),
 (2,  'EDA2', 'Emergency / Disaster Assistance Programs', 'Cash for Work', ''),
 (3,  'EDA3', 'Emergency / Disaster Assistance Programs', 'Emergency Shelter (Local)', ''),
 (4,  'EDA4', 'Emergency / Disaster Assistance Programs', 'Emergency Shelter (National / NHA)', ''),
 (5,  'EDA5', 'Emergency / Disaster Assistance Programs', 'Emergency Shelter (Province)', ''),
 (6,  'EDA6', 'Emergency / Disaster Assistance Programs', 'Food for Work', ''),
 (7,  'EDA7', 'Emergency / Disaster Assistance Programs', 'Non-Food Assistance', ''),
 (8,  'EDA8', 'Emergency / Disaster Assistance Programs', 'Relief Food Pack', ''),
 (9,  'EDA9', 'Emergency / Disaster Assistance Programs', 'Temporary Shelter', ''),
 (10, 'FA1',  'Financial Assistance Programs', 'Balik Probinsya', ''),
 (11, 'FA2',  'Financial Assistance Programs', 'Burial Assistance', ''),
 (12, 'FA3',  'Financial Assistance Programs', 'Dental Assistance', ''),
 (13, 'FA4',  'Financial Assistance Programs', 'Eyeglasses Assistance', ''),
 (14, 'FA5',  'Financial Assistance Programs', 'Lingap sa Mahirap', ''),
 (15, 'FA6',  'Financial Assistance Programs', 'Medical Assistance', ''),
 (16, 'SWPS1',  'Social Welfare Programs and Services', 'Balay Silangan', ''),
 (17, 'SWPS2',  'Social Welfare Programs and Services', 'Business Skills Management Training', ''),
 (18, 'SWPS3',  'Social Welfare Programs and Services', 'Counseling / Dialogue', ''),
 (19, 'SWPS4',  'Social Welfare Programs and Services', 'Family Development Session', ''),
 (20, 'SWPS5',  'Social Welfare Programs and Services', 'Gender Sensitivity Training', ''),
 (21, 'SWPS6',  'Social Welfare Programs and Services', 'Legal Assistance / Free Notary', ''),
 (22, 'SWPS7',  'Social Welfare Programs and Services', 'Licensed Foster Parent', ''),
 (23, 'SWPS8',  'Social Welfare Programs and Services', 'Pamaskong Handog', ''),
 (24, 'SWPS9',  'Social Welfare Programs and Services', 'Parent Effectiveness Service', ''),
 (25, 'SWPS10', 'Social Welfare Programs and Services', 'PMOC (Pre-Marriage Orientation / Counseling)', ''),
 (26, 'SWPS11', 'Social Welfare Programs and Services', 'Referral', ''),
 (27, '4PS',    'Social Welfare Programs and Services', '4Ps (Pantawid Pamilyang Pilipino Programs)', '');
