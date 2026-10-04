-- Creates the Northbridge University database schema.

USE northbridge_university;

CREATE TABLE User (
    user_ID INT PRIMARY KEY,
    first_Name VARCHAR(50) NOT NULL,
    middle_Name VARCHAR(50) DEFAULT NULL,
    last_Name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    DOB DATE NOT NULL,
    street VARCHAR(100) NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    zip_Code VARCHAR(10) NOT NULL,
    user_Type ENUM('Student', 'Faculty', 'Admin', 'StatStaff') NOT NULL,
    UNIQUE KEY uq_User_ID_user_Type (user_ID, user_Type)
);

CREATE TABLE Login (
    user_ID INT PRIMARY KEY,
    user_Email VARCHAR(255) NOT NULL UNIQUE,
    user_Password VARCHAR(255) NOT NULL,
    no_Of_Tries INT NOT NULL DEFAULT 0,
    lock_var BOOLEAN NOT NULL DEFAULT FALSE,
    user_Type ENUM('Student', 'Faculty', 'Admin', 'StatStaff') NOT NULL,
    FOREIGN KEY (user_ID, user_Type) REFERENCES User(user_ID, user_Type)
);

CREATE TABLE Admin (
    user_ID INT PRIMARY KEY,
    user_Type ENUM('Student', 'Faculty', 'Admin', 'StatStaff') NOT NULL DEFAULT 'Admin',
    security_Level ENUM('Read-Only', 'Full-Access') NOT NULL,
    FOREIGN KEY (user_ID, user_Type) REFERENCES User(user_ID, user_Type),
    CHECK (user_Type = 'Admin')
);

CREATE TABLE StatStaff (
    user_ID INT PRIMARY KEY,
    user_Type ENUM('Student', 'Faculty', 'Admin', 'StatStaff') NOT NULL DEFAULT 'StatStaff',
    employment_Type ENUM('Full-Time', 'Part-Time') NOT NULL,
    access_Level ENUM('Read-Only', 'Read-Write') NOT NULL,
    FOREIGN KEY (user_ID, user_Type) REFERENCES User(user_ID, user_Type),
    CHECK (user_Type = 'StatStaff'),
    CHECK (employment_Type = 'Full-Time' AND access_Level = 'Read-Only')
);

CREATE TABLE User_Update_Log (
    update_Log_ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_ID INT NOT NULL,
    changed_By_User_ID INT NOT NULL,
    changed_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_Field VARCHAR(50) NOT NULL,
    old_Value TEXT,
    new_Value TEXT,
    user_Request_Reference VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_ID) REFERENCES User(user_ID),
    FOREIGN KEY (changed_By_User_ID) REFERENCES User(user_ID)
);

CREATE TABLE Building (
    Bldg_ID VARCHAR(8) PRIMARY KEY,
    Bldg_Name VARCHAR(255) NOT NULL,
    building_Usage VARCHAR(255) NOT NULL
);

CREATE TABLE Room (
    room_ID INT PRIMARY KEY,
    Bldg_ID VARCHAR(8) NOT NULL,
    room_Number VARCHAR(20) NOT NULL,
    room_Type ENUM('Lecture', 'Office', 'Lab') NOT NULL,
    FOREIGN KEY (Bldg_ID) REFERENCES Building(Bldg_ID),
    UNIQUE KEY uq_Room_ID_type (room_ID, room_Type),
    UNIQUE KEY uq_Room_building_room (Bldg_ID, room_Number)
);

CREATE TABLE Lecture (
    lecture_ID INT PRIMARY KEY,
    room_Type ENUM('Lecture', 'Office', 'Lab') NOT NULL DEFAULT 'Lecture',
    avail_Seats INT NOT NULL,
    FOREIGN KEY (lecture_ID, room_Type) REFERENCES Room(room_ID, room_Type),
    CHECK (room_Type = 'Lecture' AND avail_Seats > 0)
);

CREATE TABLE Office (
    office_ID INT PRIMARY KEY,
    room_Type ENUM('Lecture', 'Office', 'Lab') NOT NULL DEFAULT 'Office',
    No_Of_Desks INT NOT NULL,
    FOREIGN KEY (office_ID, room_Type) REFERENCES Room(room_ID, room_Type),
    CHECK (room_Type = 'Office' AND No_Of_Desks > 0)
);

CREATE TABLE Lab (
    lab_ID INT PRIMARY KEY,
    room_Type ENUM('Lecture', 'Office', 'Lab') NOT NULL DEFAULT 'Lab',
    no_Of_Workstations INT NOT NULL,
    FOREIGN KEY (lab_ID, room_Type) REFERENCES Room(room_ID, room_Type),
    CHECK (room_Type = 'Lab' AND no_Of_Workstations > 0)
);

CREATE TABLE Faculty (
    faculty_ID INT PRIMARY KEY,
    user_Type ENUM('Student', 'Faculty', 'Admin', 'StatStaff') NOT NULL DEFAULT 'Faculty',
    office_ID INT NOT NULL,
    Specialty VARCHAR(100) NOT NULL,
    faculty_Rank VARCHAR(50) NOT NULL,
    faculty_Type ENUM('Full-Time', 'Part-Time') NOT NULL,
    no_of_Classes INT NOT NULL DEFAULT 0,
    FOREIGN KEY (faculty_ID, user_Type) REFERENCES User(user_ID, user_Type),
    FOREIGN KEY (office_ID) REFERENCES Office(office_ID),
    UNIQUE KEY uq_Faculty_ID_type (faculty_ID, faculty_Type),
    UNIQUE KEY uq_Faculty_office (office_ID),
    CHECK (user_Type = 'Faculty')
);

CREATE TABLE Full_Time_Faculty (
    faculty_ID INT PRIMARY KEY,
    faculty_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Full-Time',
    no_of_Classes INT NOT NULL DEFAULT 0,
    FOREIGN KEY (faculty_ID, faculty_Type) REFERENCES Faculty(faculty_ID, faculty_Type),
    CHECK (faculty_Type = 'Full-Time')
);

CREATE TABLE Part_Time_Faculty (
    faculty_ID INT PRIMARY KEY,
    faculty_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Part-Time',
    no_of_Classes INT NOT NULL DEFAULT 0,
    DOA DATE NOT NULL,
    FOREIGN KEY (faculty_ID, faculty_Type) REFERENCES Faculty(faculty_ID, faculty_Type),
    CHECK (faculty_Type = 'Part-Time')
);

CREATE TABLE Department (
    dept_ID INT PRIMARY KEY,
    dept_Name VARCHAR(100) NOT NULL,
    office_ID INT NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    Phone_No VARCHAR(20) NOT NULL,
    chair_ID INT NOT NULL,
    dept_Manager VARCHAR(100) NOT NULL,
    FOREIGN KEY (office_ID) REFERENCES Office(office_ID),
    UNIQUE KEY uq_Department_office (office_ID),
    FOREIGN KEY (chair_ID) REFERENCES Faculty(faculty_ID)
);

CREATE TABLE Major (
    major_ID INT PRIMARY KEY,
    major_Name VARCHAR(100) NOT NULL,
    dept_ID INT NOT NULL,
    FOREIGN KEY (dept_ID) REFERENCES Department(dept_ID)
);

CREATE TABLE Minor (
    minor_ID INT PRIMARY KEY,
    minor_Name VARCHAR(100) NOT NULL,
    dept_ID INT NOT NULL,
    FOREIGN KEY (dept_ID) REFERENCES Department(dept_ID)
);

CREATE TABLE Student (
    student_ID INT PRIMARY KEY,
    user_Type ENUM('Student', 'Faculty', 'Admin', 'StatStaff') NOT NULL DEFAULT 'Student',
    major_ID INT NOT NULL,
    student_Year INT NOT NULL,
    student_Type ENUM('Undergraduate', 'Graduate') NOT NULL,
    FOREIGN KEY (student_ID, user_Type) REFERENCES User(user_ID, user_Type),
    FOREIGN KEY (major_ID) REFERENCES Major(major_ID),
    UNIQUE KEY uq_Student_ID_type (student_ID, student_Type),
    CHECK (user_Type = 'Student')
);

CREATE TABLE Undergraduate (
    student_ID INT PRIMARY KEY,
    student_Type ENUM('Undergraduate', 'Graduate') NOT NULL DEFAULT 'Undergraduate',
    dept_ID INT NOT NULL,
    undergraduate_student_Type ENUM('Full-Time', 'Part-Time') NOT NULL,
    FOREIGN KEY (student_ID, student_Type) REFERENCES Student(student_ID, student_Type),
    FOREIGN KEY (dept_ID) REFERENCES Department(dept_ID),
    UNIQUE KEY uq_Undergraduate_student_status (student_ID, undergraduate_student_Type),
    CHECK (student_Type = 'Undergraduate')
);

CREATE TABLE Full_Time_Undergraduate (
    student_ID INT PRIMARY KEY,
    undergraduate_student_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Full-Time',
    Status VARCHAR(50) NOT NULL,
    min_Credits INT NOT NULL,
    max_Credits INT NOT NULL,
    credits_Earned INT NOT NULL DEFAULT 0,
    FOREIGN KEY (student_ID, undergraduate_student_Type)
        REFERENCES Undergraduate(student_ID, undergraduate_student_Type),
    CHECK (undergraduate_student_Type = 'Full-Time'),
    CHECK (min_Credits <= max_Credits)
);

CREATE TABLE Part_Time_Undergraduate (
    student_ID INT PRIMARY KEY,
    undergraduate_student_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Part-Time',
    Status VARCHAR(50) NOT NULL,
    min_Credits INT NOT NULL,
    max_Credits INT NOT NULL,
    credits_Earned INT NOT NULL DEFAULT 0,
    FOREIGN KEY (student_ID, undergraduate_student_Type)
        REFERENCES Undergraduate(student_ID, undergraduate_student_Type),
    CHECK (undergraduate_student_Type = 'Part-Time'),
    CHECK (min_Credits <= max_Credits)
);

CREATE TABLE Graduate_Student (
    student_ID INT PRIMARY KEY,
    student_Type ENUM('Undergraduate', 'Graduate') NOT NULL DEFAULT 'Graduate',
    dept_ID INT NOT NULL,
    Program VARCHAR(100) NOT NULL,
    graduate_Student_Type ENUM('Full-Time', 'Part-Time') NOT NULL,
    FOREIGN KEY (student_ID, student_Type) REFERENCES Student(student_ID, student_Type),
    FOREIGN KEY (dept_ID) REFERENCES Department(dept_ID),
    UNIQUE KEY uq_Graduate_Student_status (student_ID, graduate_Student_Type),
    CHECK (student_Type = 'Graduate')
);

CREATE TABLE Full_Time_Graduate (
    student_ID INT PRIMARY KEY,
    graduate_Student_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Full-Time',
    year INT NOT NULL,
    credits_Earned INT NOT NULL DEFAULT 0,
    Thesis VARCHAR(255),
    FOREIGN KEY (student_ID, graduate_Student_Type)
        REFERENCES Graduate_Student(student_ID, graduate_Student_Type),
    CHECK (graduate_Student_Type = 'Full-Time')
);

CREATE TABLE Part_Time_Graduate (
    student_ID INT PRIMARY KEY,
    graduate_Student_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Part-Time',
    FOREIGN KEY (student_ID, graduate_Student_Type)
        REFERENCES Graduate_Student(student_ID, graduate_Student_Type),
    CHECK (graduate_Student_Type = 'Part-Time')
);

CREATE TABLE Hold (
    hold_ID INT PRIMARY KEY,
    hold_Type VARCHAR(100) NOT NULL
);

CREATE TABLE Course (
    course_ID INT PRIMARY KEY,
    dept_ID INT NOT NULL,
    course_Name VARCHAR(255) NOT NULL,
    course_Credits INT NOT NULL,
    course_DSC VARCHAR(500) NOT NULL,
    course_Type ENUM('Undergraduate', 'Graduate') NOT NULL,
    FOREIGN KEY (dept_ID) REFERENCES Department(dept_ID),
    CHECK (course_Credits > 0)
);

CREATE TABLE Major_Requirement (
    major_ID INT NOT NULL,
    course_ID INT NOT NULL,
    min_Grade_Req VARCHAR(5) NOT NULL,
    requirement_Status ENUM('Provisional', 'Approved') NOT NULL DEFAULT 'Provisional',
    PRIMARY KEY (major_ID, course_ID),
    FOREIGN KEY (major_ID) REFERENCES Major(major_ID),
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID)
);

CREATE TABLE Minor_Requirement (
    minor_ID INT NOT NULL,
    course_ID INT NOT NULL,
    min_Grade_Req VARCHAR(5) NOT NULL,
    requirement_Status ENUM('Provisional', 'Approved') NOT NULL DEFAULT 'Provisional',
    PRIMARY KEY (minor_ID, course_ID),
    FOREIGN KEY (minor_ID) REFERENCES Minor(minor_ID),
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID)
);

CREATE TABLE Day (
    day_ID INT PRIMARY KEY,
    day_Of_Week ENUM(
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ) NOT NULL
);

CREATE TABLE Period (
    period_ID INT PRIMARY KEY,
    start_Time TIME NOT NULL,
    end_Time TIME NOT NULL,
    CHECK (start_Time < end_Time)
);

CREATE TABLE Time_Slot (
    time_Slot_ID INT PRIMARY KEY
);

CREATE TABLE Semester (
    semester_ID INT PRIMARY KEY,
    semester_Name VARCHAR(50) NOT NULL
);

CREATE TABLE Course_Section (
    CRN INT PRIMARY KEY,
    course_ID INT NOT NULL,
    section_No INT NOT NULL,
    faculty_ID INT NOT NULL,
    time_Slot_ID INT NOT NULL,
    lecture_ID INT,
    lab_ID INT,
    semester_ID INT NOT NULL,
    available_Seats INT NOT NULL,
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID),
    FOREIGN KEY (faculty_ID) REFERENCES Faculty(faculty_ID),
    FOREIGN KEY (lecture_ID) REFERENCES Lecture(lecture_ID),
    FOREIGN KEY (lab_ID) REFERENCES Lab(lab_ID),
    FOREIGN KEY (time_Slot_ID) REFERENCES Time_Slot(time_Slot_ID),
    FOREIGN KEY (semester_ID) REFERENCES Semester(semester_ID),
    UNIQUE KEY uq_Course_Section_CRN_course_semester (CRN, course_ID, semester_ID),
    UNIQUE KEY uq_Course_Section_CRN_semester (CRN, semester_ID),
    UNIQUE KEY uq_Course_Section_CRN_course (CRN, course_ID),
    UNIQUE KEY uq_Course_Section_CRN_faculty (CRN, faculty_ID),
    UNIQUE KEY uq_Course_Section_course_semester_section (course_ID, semester_ID, section_No),
    CHECK ((lecture_ID IS NOT NULL AND lab_ID IS NULL) OR
           (lecture_ID IS NULL AND lab_ID IS NOT NULL)),
    CHECK (available_Seats BETWEEN 0 AND 10)
);

CREATE TABLE Advisor (
    faculty_ID INT NOT NULL,
    faculty_Type ENUM('Full-Time', 'Part-Time') NOT NULL DEFAULT 'Full-Time',
    student_ID INT NOT NULL,
    date_Of_Appnt DATE NOT NULL,
    PRIMARY KEY (faculty_ID, student_ID),
    FOREIGN KEY (faculty_ID, faculty_Type) REFERENCES Faculty(faculty_ID, faculty_Type),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    CHECK (faculty_Type = 'Full-Time')
);

CREATE TABLE Student_Hold (
    hold_ID INT NOT NULL,
    student_ID INT NOT NULL,
    hold_Date DATE NOT NULL,
    PRIMARY KEY (student_ID, hold_ID),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    FOREIGN KEY (hold_ID) REFERENCES Hold(hold_ID)
);

CREATE TABLE Course_Prerequisite (
    course_ID INT NOT NULL,
    prerequisite_course_ID INT NOT NULL,
    min_Grade_Req VARCHAR(5) NOT NULL,
    PRIMARY KEY (course_ID, prerequisite_course_ID),
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID),
    FOREIGN KEY (prerequisite_course_ID) REFERENCES Course(course_ID),
    CHECK (course_ID <> prerequisite_course_ID)
);

CREATE TABLE Enrollment (
    student_ID INT NOT NULL,
    CRN INT NOT NULL,
    semester_ID INT NOT NULL,
    Grade VARCHAR(5),
    PRIMARY KEY (student_ID, CRN),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    FOREIGN KEY (CRN) REFERENCES Course_Section(CRN),
    FOREIGN KEY (semester_ID) REFERENCES Semester(semester_ID),
    FOREIGN KEY (CRN, semester_ID) REFERENCES Course_Section(CRN, semester_ID)
);

CREATE TABLE Attendance (
    student_ID INT NOT NULL,
    CRN INT NOT NULL,
    course_ID INT NOT NULL,
    attendance_Date DATE NOT NULL,
    Present_Absent ENUM('Present', 'Absent') NOT NULL,
    PRIMARY KEY (student_ID, CRN, attendance_Date),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    FOREIGN KEY (CRN) REFERENCES Course_Section(CRN),
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID),
    FOREIGN KEY (CRN, course_ID) REFERENCES Course_Section(CRN, course_ID),
    FOREIGN KEY (student_ID, CRN) REFERENCES Enrollment(student_ID, CRN)
);

CREATE TABLE Student_History (
    student_ID INT NOT NULL,
    CRN INT NOT NULL,
    course_ID INT NOT NULL,
    semester_ID INT NOT NULL,
    Grade VARCHAR(5),
    PRIMARY KEY (student_ID, CRN),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    FOREIGN KEY (CRN) REFERENCES Course_Section(CRN),
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID),
    FOREIGN KEY (semester_ID) REFERENCES Semester(semester_ID),
    FOREIGN KEY (CRN, course_ID, semester_ID)
        REFERENCES Course_Section(CRN, course_ID, semester_ID),
    UNIQUE KEY uq_Student_History_student_course_semester
        (student_ID, course_ID, semester_ID)
);

CREATE TABLE Faculty_History (
    faculty_ID INT NOT NULL,
    CRN INT NOT NULL,
    course_ID INT NOT NULL,
    semester_ID INT NOT NULL,
    PRIMARY KEY (faculty_ID, CRN),
    FOREIGN KEY (faculty_ID) REFERENCES Faculty(faculty_ID),
    FOREIGN KEY (CRN) REFERENCES Course_Section(CRN),
    FOREIGN KEY (course_ID) REFERENCES Course(course_ID),
    FOREIGN KEY (semester_ID) REFERENCES Semester(semester_ID),
    FOREIGN KEY (CRN, course_ID, semester_ID)
        REFERENCES Course_Section(CRN, course_ID, semester_ID),
    FOREIGN KEY (CRN, faculty_ID)
        REFERENCES Course_Section(CRN, faculty_ID)
);

CREATE TABLE Faculty_Department (
    faculty_ID INT NOT NULL,
    dept_ID INT NOT NULL,
    percent_Time DECIMAL(5,2) NOT NULL,
    date_Of_Appointment DATE NOT NULL,
    PRIMARY KEY (faculty_ID, dept_ID),
    FOREIGN KEY (faculty_ID) REFERENCES Faculty(faculty_ID),
    FOREIGN KEY (dept_ID) REFERENCES Department(dept_ID),
    CHECK (percent_Time > 0 AND percent_Time <= 100)
);

CREATE TABLE Student_Major (
    student_ID INT NOT NULL,
    major_ID INT NOT NULL,
    date_Of_Choice DATE NOT NULL,
    PRIMARY KEY (student_ID, major_ID),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    FOREIGN KEY (major_ID) REFERENCES Major(major_ID)
);

CREATE TABLE Student_Minor (
    student_ID INT NOT NULL,
    minor_ID INT NOT NULL,
    date_Of_Choice DATE NOT NULL,
    PRIMARY KEY (student_ID, minor_ID),
    FOREIGN KEY (student_ID) REFERENCES Student(student_ID),
    FOREIGN KEY (minor_ID) REFERENCES Minor(minor_ID)
);

CREATE TABLE Time_Slot_Day (
    time_Slot_ID INT NOT NULL,
    day_ID INT NOT NULL,
    PRIMARY KEY (time_Slot_ID, day_ID),
    FOREIGN KEY (time_Slot_ID) REFERENCES Time_Slot(time_Slot_ID),
    FOREIGN KEY (day_ID) REFERENCES Day(day_ID)
);

CREATE TABLE Time_Slot_Period (
    time_Slot_ID INT NOT NULL,
    period_ID INT NOT NULL,
    PRIMARY KEY (time_Slot_ID, period_ID),
    FOREIGN KEY (time_Slot_ID) REFERENCES Time_Slot(time_Slot_ID),
    FOREIGN KEY (period_ID) REFERENCES Period(period_ID)
);
