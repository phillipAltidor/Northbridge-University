-- Apply only to an isolated cloned database during the migration rehearsal.
-- Local CRNs remain identifiers. The original official CRN is term-scoped.
CREATE TABLE NB_AcademicRebuild (
 singleton TINYINT PRIMARY KEY,
 candidateSHA256 CHAR(64) NOT NULL,
 scheduleSHA256 CHAR(64) NOT NULL,
 integrationStatus VARCHAR(64) NOT NULL,
 auditJSON JSON NOT NULL,
 CHECK (singleton = 1)
) ENGINE=InnoDB;
CREATE TABLE NB_AcademicCourse (
 course_ID VARCHAR(20) PRIMARY KEY,
 version_Key VARCHAR(64) NOT NULL UNIQUE,
 definition_JSON JSON NOT NULL,
 rule_JSON JSON NOT NULL,
 properties_JSON JSON NULL,
 FOREIGN KEY (course_ID) REFERENCES Course(course_ID)
) ENGINE=InnoDB;
CREATE TABLE NB_AcademicSection (
 CRN INT PRIMARY KEY,
 official_CRN VARCHAR(16) NULL,
 source_Section VARCHAR(32) NOT NULL,
 source_Term VARCHAR(8) NOT NULL,
 delivery_Group INT NOT NULL,
 source_JSON JSON NOT NULL,
 FOREIGN KEY (CRN) REFERENCES Course_Section(CRN)
) ENGINE=InnoDB;
CREATE TABLE NB_AcademicMeeting (
 CRN INT NOT NULL,
 ordinal INT NOT NULL,
 day_Of_Week VARCHAR(12) NOT NULL,
 start_Time TIME NOT NULL,
 end_Time TIME NOT NULL,
 start_Date DATE NOT NULL,
 end_Date DATE NOT NULL,
 PRIMARY KEY (CRN, ordinal),
 FOREIGN KEY (CRN) REFERENCES NB_AcademicSection(CRN),
 CHECK (start_Time < end_Time),
 CHECK (start_Date <= end_Date)
) ENGINE=InnoDB;
CREATE TABLE NB_AcademicStudent (
 student_ID INT PRIMARY KEY,
 profile_JSON JSON NOT NULL,
 FOREIGN KEY (student_ID) REFERENCES Student(student_ID)
) ENGINE=InnoDB;
CREATE TABLE NB_AcademicProgram (
 program_Name VARCHAR(255) PRIMARY KEY,
 program_Kind VARCHAR(16) NOT NULL,
 catalog_Edition VARCHAR(32) NOT NULL,
 definition_JSON JSON NOT NULL
) ENGINE=InnoDB;
