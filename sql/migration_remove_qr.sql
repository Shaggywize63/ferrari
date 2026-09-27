-- Migration: Replace QR codes with access codes
-- Run this on existing databases that were set up with the original schema.sql

ALTER TABLE participants
    ADD COLUMN access_code CHAR(6) NULL AFTER team_name;

-- Backfill existing rows with a random 6-char code
UPDATE participants
SET access_code = UPPER(SUBSTRING(MD5(CONCAT(id, email, RAND())), 1, 6))
WHERE access_code IS NULL;

-- Make it NOT NULL and unique
ALTER TABLE participants
    MODIFY COLUMN access_code CHAR(6) NOT NULL,
    ADD UNIQUE KEY uq_participants_access_code (access_code);

-- Make phone required (set empty string for any NULLs first)
UPDATE participants SET phone = '' WHERE phone IS NULL;
ALTER TABLE participants MODIFY COLUMN phone VARCHAR(25) NOT NULL DEFAULT '';

-- Drop old QR columns if they exist
ALTER TABLE participants DROP COLUMN IF EXISTS qr_token;
ALTER TABLE participants DROP COLUMN IF EXISTS qr_image;
