-- Supplemental saved-schedule table for use case 70. Select the target database first.
CREATE TABLE IF NOT EXISTS Master_Schedule (
    semester_ID INT PRIMARY KEY,
    admin_ID INT NOT NULL,
    saved_At TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    schedule_Data JSON NOT NULL,
    FOREIGN KEY (semester_ID) REFERENCES Semester(semester_ID),
    FOREIGN KEY (admin_ID) REFERENCES Admin(admin_ID)
);
