-- V21: distribution batches become scheduled events.
--
-- scheduled_start/scheduled_end are the plan, started_at/closed_at stay the
-- actuals. Dates and daily hours are separate because a three day batch runs
-- 8:00 to 17:00 on each of its days, not for 72 unbroken hours.
--
-- Apply to a V20 database, then regenerate accesscardV21.sql from the result.

ALTER TABLE `distribution_batch`
  ADD `venue`            varchar(150) NOT NULL DEFAULT '' AFTER `name`,
  ADD `scheduled_start`  date NOT NULL DEFAULT '1970-01-01' AFTER `subsidy_type_id`,
  ADD `scheduled_end`    date NOT NULL DEFAULT '1970-01-01' AFTER `scheduled_start`,
  ADD `daily_start_time` time NOT NULL DEFAULT '08:00:00' AFTER `scheduled_end`,
  ADD `daily_end_time`   time NOT NULL DEFAULT '17:00:00' AFTER `daily_start_time`,
  ADD `color`            varchar(16) NOT NULL DEFAULT 'green' AFTER `daily_end_time`,
  MODIFY `started_at`    timestamp NULL DEFAULT NULL,
  ADD KEY `idx_db_sched` (`scheduled_start`, `scheduled_end`);

-- Existing batches predate scheduling. Their actuals are the best available
-- plan, so history keeps rendering on the calendar instead of collapsing onto
-- the epoch default above.
UPDATE `distribution_batch`
   SET `scheduled_start`  = DATE(`started_at`),
       `scheduled_end`    = DATE(COALESCE(`closed_at`, `started_at`)),
       `daily_start_time` = TIME(`started_at`),
       `daily_end_time`   = TIME(COALESCE(`closed_at`, `started_at`))
 WHERE `started_at` IS NOT NULL;
