-- V21 -> V22 (1 of 3): one canonical case for stored text.
-- Run once against an existing accesscard DB:
--   mysql -u root accesscard < sql/patches/v22-uppercase.sql
--
-- Names and addresses were already stored uppercase; everything else was not,
-- so each read site had to decide its own casing. This makes uppercase the
-- stored form everywhere, which is why the two enums have to change: with
-- enum('Jr','Sr',...) MySQL resolves an inserted 'JR' back to the member 'Jr'
-- and hands 'Jr' back on SELECT, so uppercase can never survive a round trip.
--
-- The collation is utf8mb4_general_ci, so every existing WHERE/JOIN/UNIQUE
-- comparison keeps matching across the case change. Nothing to re-point.

-- Enums: widen to varchar, uppercase the data, then narrow back onto the
-- uppercase members. Converting the enum in place would leave MySQL to map old
-- members to new ones by collation; this way the mapping is explicit.
ALTER TABLE `member` MODIFY `suffix` varchar(10) DEFAULT NULL;
UPDATE `member` SET `suffix` = UPPER(`suffix`) WHERE `suffix` IS NOT NULL;
ALTER TABLE `member` MODIFY `suffix` enum('JR','SR','I','II','III','IV','V') DEFAULT NULL;

ALTER TABLE `member` MODIFY `sex` varchar(10) DEFAULT NULL;
UPDATE `member` SET `sex` = UPPER(`sex`) WHERE `sex` IS NOT NULL;
ALTER TABLE `member` MODIFY `sex` enum('MALE','FEMALE') DEFAULT NULL;

-- Free-text member columns. lastname/firstname/middlename/address are already
-- uppercase (MemberFieldNormalizer::cleanName/cleanAddress); they are included
-- so a row written before that normalizer landed is caught too.
UPDATE `member` SET
  `lastname`      = UPPER(`lastname`),
  `firstname`     = UPPER(`firstname`),
  `middlename`    = UPPER(`middlename`),
  `address`       = UPPER(`address`),
  `civilstatus`   = UPPER(`civilstatus`),
  `religion`      = UPPER(`religion`),
  `education`     = UPPER(`education`),
  `job`           = UPPER(`job`),
  `relationship`  = UPPER(`relationship`);

-- Reference data. Barangay names are spelled out rather than run through
-- UPPER() because two of them carry an n-tilde, and the uppercase form of that
-- letter is the one thing a collation-driven UPPER() can get wrong.
UPDATE `barangay` SET `name` = 'BIÑAN'        WHERE `barangayID` = 1;
UPDATE `barangay` SET `name` = 'BUNGAHAN'     WHERE `barangayID` = 2;
UPDATE `barangay` SET `name` = 'CANLALAY'     WHERE `barangayID` = 3;
UPDATE `barangay` SET `name` = 'CASILE'       WHERE `barangayID` = 4;
UPDATE `barangay` SET `name` = 'DE LA PAZ'    WHERE `barangayID` = 5;
UPDATE `barangay` SET `name` = 'GANADO'       WHERE `barangayID` = 6;
UPDATE `barangay` SET `name` = 'LANGKIWA'     WHERE `barangayID` = 7;
UPDATE `barangay` SET `name` = 'LOMA'         WHERE `barangayID` = 8;
UPDATE `barangay` SET `name` = 'MALABAN'      WHERE `barangayID` = 9;
UPDATE `barangay` SET `name` = 'MALAMIG'      WHERE `barangayID` = 10;
UPDATE `barangay` SET `name` = 'MAMPLASAN'    WHERE `barangayID` = 11;
UPDATE `barangay` SET `name` = 'PLATERO'      WHERE `barangayID` = 12;
UPDATE `barangay` SET `name` = 'POBLACION'    WHERE `barangayID` = 13;
UPDATE `barangay` SET `name` = 'SAN ANTONIO'  WHERE `barangayID` = 14;
UPDATE `barangay` SET `name` = 'SAN FRANCISCO' WHERE `barangayID` = 15;
UPDATE `barangay` SET `name` = 'SAN JOSE'     WHERE `barangayID` = 16;
UPDATE `barangay` SET `name` = 'SAN VICENTE'  WHERE `barangayID` = 17;
UPDATE `barangay` SET `name` = 'SANTO DOMINGO' WHERE `barangayID` = 18;
UPDATE `barangay` SET `name` = 'SANTO NIÑO'   WHERE `barangayID` = 19;
UPDATE `barangay` SET `name` = 'SANTO TOMAS'  WHERE `barangayID` = 20;
UPDATE `barangay` SET `name` = 'SORO-SORO'    WHERE `barangayID` = 21;
UPDATE `barangay` SET `name` = 'TIMBAO'       WHERE `barangayID` = 22;
UPDATE `barangay` SET `name` = 'TUBIGAN'      WHERE `barangayID` = 23;
UPDATE `barangay` SET `name` = 'ZAPOTE'       WHERE `barangayID` = 24;

-- The lookup tables an admin can add rows to. Shortcodes were already stored
-- uppercase by the lookup CRUD; the display names were not.
UPDATE `sector`   SET `shortcode` = UPPER(`shortcode`), `name` = UPPER(`name`);
UPDATE `services` SET `shortcode` = UPPER(`shortcode`), `name` = UPPER(`name`), `category` = UPPER(`category`);
UPDATE `category` SET `code` = UPPER(`code`), `name` = UPPER(`name`);
UPDATE `subsidy`  SET `name` = UPPER(`name`);

-- users.account_level is deliberately left alone: those are role keys the code
-- matches on ('encoder', 'developer'), not labels anyone reads.
