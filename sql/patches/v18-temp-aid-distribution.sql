-- Temporary scanner table used until family encoding is complete.
CREATE TABLE IF NOT EXISTS `temp_aid_distribution` (
  `temp_aidID` int(11) NOT NULL AUTO_INCREMENT,
  `control_no` int(11) NOT NULL,
  `aid_type_id` int(11) NOT NULL,
  `claim_date` date NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`temp_aidID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `temp_aid_distribution`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_temp_aid_batch_control` (`batch_id`, `control_no`);
