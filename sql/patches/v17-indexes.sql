-- V16 -> V17: adds the indexes behind the Manage Records list, dashboard
-- counts, and audit list. Run once against an existing accesscard DB:
--   mysql -u root accesscard < sql/patches/v17-indexes.sql
-- IF NOT EXISTS makes each statement safe to re-run (supported by MariaDB,
-- which is what XAMPP ships).

-- The records list always filters on dt_deleted and sorts by lastname,
-- firstname. This composite lets the DB filter and return rows already in
-- sort order, so no filesort over the whole table. It also covers the
-- dashboard family/member counts, which filter on dt_deleted alone.
ALTER TABLE `member`
  ADD INDEX IF NOT EXISTS `idx_member_deleted_name` (`dt_deleted`, `lastname`, `firstname`);

-- The Manage Records date-range filter queries on dt_created.
ALTER TABLE `member`
  ADD INDEX IF NOT EXISTS `idx_member_created` (`dt_created`);

-- Audit list pages order and filter by timestamp, and this table grows the
-- fastest (one row per mutation).
ALTER TABLE `audit_trails`
  ADD INDEX IF NOT EXISTS `idx_audit_created` (`dt_created`);
