<?php

// Returned statements are installed after existing enrollment/seat counts are audited.
return [
    'DROP TRIGGER IF EXISTS northbridge_enrollment_insert',
    'DROP TRIGGER IF EXISTS northbridge_enrollment_delete',
    'DROP TRIGGER IF EXISTS northbridge_enrollment_update',
    <<<'SQL'
CREATE TRIGGER northbridge_enrollment_insert BEFORE INSERT ON Enrollment FOR EACH ROW
BEGIN
    DECLARE seats INT DEFAULT NULL;
    DECLARE term_id INT DEFAULT NULL;
    SELECT available_Seats, semester_ID INTO seats, term_id FROM Course_Section WHERE CRN=NEW.CRN FOR UPDATE;
    IF term_id IS NULL OR term_id<>NEW.semester_ID THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Enrollment must match the course section semester.';
    END IF;
    IF seats<=0 OR seats>10 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='This section is full or its seat count is invalid.';
    END IF;
    UPDATE Course_Section SET available_Seats=available_Seats-1 WHERE CRN=NEW.CRN;
END
SQL,
    <<<'SQL'
CREATE TRIGGER northbridge_enrollment_delete BEFORE DELETE ON Enrollment FOR EACH ROW
BEGIN
    DECLARE seats INT DEFAULT NULL;
    SELECT available_Seats INTO seats FROM Course_Section WHERE CRN=OLD.CRN FOR UPDATE;
    IF seats IS NULL OR seats<0 OR seats>=10 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='The section seat count is invalid.';
    END IF;
    UPDATE Course_Section SET available_Seats=available_Seats+1 WHERE CRN=OLD.CRN;
END
SQL,
    <<<'SQL'
CREATE TRIGGER northbridge_enrollment_update BEFORE UPDATE ON Enrollment FOR EACH ROW
BEGIN
    DECLARE seats INT DEFAULT NULL;
    DECLARE term_id INT DEFAULT NULL;
    DECLARE old_seats INT DEFAULT NULL;
    SELECT available_Seats, semester_ID INTO seats, term_id FROM Course_Section WHERE CRN=NEW.CRN FOR UPDATE;
    IF term_id IS NULL OR term_id<>NEW.semester_ID THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Enrollment must match the course section semester.';
    END IF;
    IF NEW.CRN<>OLD.CRN THEN
        SELECT available_Seats INTO old_seats FROM Course_Section WHERE CRN=OLD.CRN FOR UPDATE;
        IF seats<=0 OR seats>10 OR old_seats IS NULL OR old_seats<0 OR old_seats>=10 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='The target section is full or a seat count is invalid.';
        END IF;
        UPDATE Course_Section SET available_Seats=available_Seats+1 WHERE CRN=OLD.CRN;
        UPDATE Course_Section SET available_Seats=available_Seats-1 WHERE CRN=NEW.CRN;
    END IF;
END
SQL,
];
