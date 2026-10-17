-- Apply once to existing databases; included in the schema for new installations.
ALTER TABLE players ADD COLUMN photo_path VARCHAR(190) NULL AFTER birth_year;
