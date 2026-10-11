-- Schema 2.3 migration. Select the target database before running.

-- Existing User and Login records are preserved.

ALTER TABLE `User` MODIFY COLUMN gender VARCHAR(20) NOT NULL;

ALTER TABLE `User` MODIFY COLUMN user_Type VARCHAR(20) NOT NULL;

ALTER TABLE `Login` MODIFY COLUMN user_Type VARCHAR(20) NOT NULL;

CREATE TABLE Admin (
 admin_ID INT PRIMARY KEY,
 FOREIGN KEY (admin_ID) REFERENCES User(user_ID)

);

CREATE TABLE Stat_Dept_Member (
 stat_Member_ID INT PRIMARY KEY,
 FOREIGN KEY (stat_Member_ID) REFERENCES User(user_ID)

);

ALTER TABLE `Room` MODIFY COLUMN room_Number INT NOT NULL;

ALTER TABLE `Room` MODIFY COLUMN room_Type VARCHAR(20) NOT NULL;

ALTER TABLE `Faculty` MODIFY COLUMN faculty_Type VARCHAR(20) NOT NULL;

ALTER TABLE `Faculty` DROP COLUMN `no_of_Classes`;

ALTER TABLE `Student` MODIFY COLUMN student_Type VARCHAR(20) NOT NULL;

ALTER TABLE `Undergraduate` MODIFY COLUMN undergraduate_student_Type VARCHAR(20) NOT NULL;

ALTER TABLE `Graduate_Student` MODIFY COLUMN graduate_Student_Type VARCHAR(20) NOT NULL;

ALTER TABLE `Part_Time_Graduate` ADD COLUMN year INT NOT NULL;

ALTER TABLE `Part_Time_Graduate` ADD COLUMN credits_Earned INT NOT NULL DEFAULT 0;

ALTER TABLE `Part_Time_Graduate` ADD COLUMN Thesis VARCHAR(255);

ALTER TABLE `Course` MODIFY COLUMN course_Type VARCHAR(20) NOT NULL;

CREATE TABLE Major_Requirements (
 major_ID INT NOT NULL,
 course_ID INT NOT NULL,
 min_Grade_Req VARCHAR(5) NOT NULL,
 PRIMARY KEY (major_ID, course_ID),
 FOREIGN KEY (major_ID) REFERENCES Major(major_ID),
 FOREIGN KEY (course_ID) REFERENCES Course(course_ID)

);

CREATE TABLE Minor_Requirements (
 minor_ID INT NOT NULL,
 course_ID INT NOT NULL,
 min_Grade_Req VARCHAR(5) NOT NULL,
 PRIMARY KEY (minor_ID, course_ID),
 FOREIGN KEY (minor_ID) REFERENCES Minor(minor_ID),
 FOREIGN KEY (course_ID) REFERENCES Course(course_ID)

);

ALTER TABLE `Day` MODIFY COLUMN day_Of_Week VARCHAR(12) NOT NULL;

ALTER TABLE `Time_Slot` ADD COLUMN time_Slot_Day VARCHAR(100) NOT NULL;

ALTER TABLE `Time_Slot` ADD COLUMN time_Slot_Period VARCHAR(100) NOT NULL;

ALTER TABLE `Attendance` MODIFY COLUMN Present_Absent VARCHAR(10) NOT NULL;
