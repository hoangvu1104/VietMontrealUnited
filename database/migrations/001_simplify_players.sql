-- Run once on the original schema. Back up first; MySQL DDL auto-commits.
-- IDs, users, availability and match statistics are unchanged.
ALTER TABLE players
 MODIFY jersey_number VARCHAR(20) NOT NULL,
 ADD birth_year SMALLINT UNSIGNED NULL AFTER jersey_number;
UPDATE players SET birth_year = YEAR(birth_date) WHERE birth_date IS NOT NULL;
ALTER TABLE players
 DROP COLUMN birth_date,
 DROP COLUMN phone,
 DROP COLUMN email,
 DROP COLUMN position,
 DROP COLUMN joined_date,
 DROP COLUMN photo;
