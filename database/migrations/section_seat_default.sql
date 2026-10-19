-- New sections start with ten available seats when no value is supplied.
-- Existing enrolled sections retain their current remaining-seat counts.
ALTER TABLE Course_Section ALTER COLUMN available_Seats SET DEFAULT 10;
