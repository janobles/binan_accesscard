-- One-time data patch: brings existing member rows in line with the uppercase
-- normalization in App\Support\MemberFieldNormalizer. Run once against an
-- existing accesscard DB:
--   mysql -u root accesscard < sql/patches/v18-uppercase-names.sql
--
-- Scope is the worker-typed columns only. Values picked from a dropdown keep
-- their stored casing, so civilstatus, education, job, religion and relationship
-- are deliberately left alone. suffix and sex are ENUM columns ('Jr','Sr',...
-- and 'Male','Female') -- uppercasing those would write an empty string.
--
-- dt_updated is assigned to itself so its ON UPDATE current_timestamp() does not
-- fire. Without this every member row would look like it was just modified.
--
-- Idempotent: UPPER() on already-uppercase text is a no-op, so re-running is
-- safe. NOT reversible -- the original capitalization is recorded nowhere. Take
-- a dump before running this.
--
-- Who needs this: only a database that already holds member rows. A fresh
-- `php spark db:seed DummyDataSeeder` already writes uppercase, because the
-- seeder now goes through MemberFieldNormalizer. accesscardV18.sql seeds no
-- member rows at all, so importing the dump needs nothing here either.

-- Count the rows this will actually change. The member table collates
-- utf8mb4_general_ci (case-insensitive), so BINARY is required to compare case.
-- Run this first and keep the number.
SELECT COUNT(*) AS rows_needing_change
FROM `member`
WHERE BINARY `firstname`  <> UPPER(`firstname`)
   OR BINARY `middlename` <> UPPER(`middlename`)
   OR BINARY `lastname`   <> UPPER(`lastname`)
   OR BINARY `address`    <> UPPER(COALESCE(`address`, ''));

UPDATE `member`
SET `firstname`  = UPPER(`firstname`),
    `middlename` = UPPER(`middlename`),
    `lastname`   = UPPER(`lastname`),
    `address`    = UPPER(`address`),
    `dt_updated` = `dt_updated`;
