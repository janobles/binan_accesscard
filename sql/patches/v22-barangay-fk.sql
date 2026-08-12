-- V21 -> V22 (2 of 3): make barangayID the only place a member's barangay lives.
--
-- RUN ORDER. This patch is the LAST step, not the first:
--   1. php spark members:backfill-barangay   fills barangayID from the address text
--   2. php spark members:split-address       strips the barangay off member.address
--   3. mysql -u root accesscard < sql/patches/v22-barangay-fk.sql
-- Reversing 1 and 2 throws away the only copy of the barangay for any row
-- imported before V20, which is the set that still has barangayID NULL.
--
-- V20 added member.barangayID but left combineAddressBarangay() writing the
-- barangay name into member.address as well, so the same fact lived in two
-- places and only the free-text one was ever displayed. Steps 1-2 above end
-- that; this adds the constraint that keeps it ended.

-- Fails if any member still points at a barangay row that no longer exists.
-- That is the point: an orphan barangayID silently drops the row out of every
-- barangay filter and rollup, and until now nothing could detect one.
ALTER TABLE `member`
  ADD CONSTRAINT `fk_member_barangay` FOREIGN KEY (`barangayID`) REFERENCES `barangay` (`barangayID`);
