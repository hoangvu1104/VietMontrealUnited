-- Apply once, with application writes paused. DDL commits independently in MySQL.
-- Existing blank/duplicate jerseys cause failure; no records are deleted.
ALTER TABLE players
 ADD COLUMN jersey_key VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
 GENERATED ALWAYS AS (LOWER(TRIM(jersey_number))) STORED,
 ADD CONSTRAINT players_jersey_nonempty CHECK (CHAR_LENGTH(TRIM(jersey_number)) > 0),
 ADD UNIQUE KEY players_jersey_unique (jersey_key);
