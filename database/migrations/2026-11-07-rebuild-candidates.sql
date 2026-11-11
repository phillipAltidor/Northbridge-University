-- Review storage only. No active-dataset pointer and no replacement of portal tables.
CREATE TABLE IF NOT EXISTS NB_RebuildDataset (
    datasetSHA256 CHAR(64) NOT NULL PRIMARY KEY,
    datasetStatus VARCHAR(32) NOT NULL,
    auditJSON JSON NOT NULL,
    studentCount INT NOT NULL,
    attemptCount INT NOT NULL,
    courseVersionCount INT NOT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT nb_rebuild_review_only CHECK (datasetStatus = 'blocked-from-activation')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS NB_RebuildStudentHistory (
    datasetSHA256 CHAR(64) NOT NULL,
    studentID INT NOT NULL,
    proposedProfileJSON JSON NOT NULL,
    proposedAttemptsJSON JSON NOT NULL,
    PRIMARY KEY (datasetSHA256, studentID),
    CONSTRAINT nb_rebuild_history_dataset FOREIGN KEY (datasetSHA256) REFERENCES NB_RebuildDataset(datasetSHA256)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS NB_RebuildCourseVersion (
    datasetSHA256 CHAR(64) NOT NULL,
    courseVersionKey VARCHAR(80) NOT NULL,
    proposedCourseJSON JSON NOT NULL,
    PRIMARY KEY (datasetSHA256, courseVersionKey),
    CONSTRAINT nb_rebuild_course_dataset FOREIGN KEY (datasetSHA256) REFERENCES NB_RebuildDataset(datasetSHA256)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS NB_RebuildSchedule (
    scheduleSHA256 CHAR(64) NOT NULL PRIMARY KEY,
    datasetSHA256 CHAR(64) NOT NULL,
    scheduleStatus VARCHAR(48) NOT NULL,
    termCount INT NOT NULL,
    sectionCount INT NOT NULL,
    auditJSON JSON NOT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY nb_rebuild_schedule_identity (scheduleSHA256, datasetSHA256),
    CONSTRAINT nb_rebuild_schedule_dataset FOREIGN KEY (datasetSHA256) REFERENCES NB_RebuildDataset(datasetSHA256),
    CONSTRAINT nb_rebuild_schedule_review CHECK (scheduleStatus = 'verified-known-meeting-times-only')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS NB_RebuildSection (
    scheduleSHA256 CHAR(64) NOT NULL,
    datasetSHA256 CHAR(64) NOT NULL,
    semesterCode CHAR(6) NOT NULL,
    courseVersionKey VARCHAR(80) NOT NULL,
    deliveryGroup INT NOT NULL,
    proposedSectionJSON JSON NOT NULL,
    PRIMARY KEY (scheduleSHA256, semesterCode, courseVersionKey, deliveryGroup),
    CONSTRAINT nb_rebuild_section_schedule FOREIGN KEY (scheduleSHA256, datasetSHA256) REFERENCES NB_RebuildSchedule(scheduleSHA256, datasetSHA256),
    CONSTRAINT nb_rebuild_section_course FOREIGN KEY (datasetSHA256, courseVersionKey) REFERENCES NB_RebuildCourseVersion(datasetSHA256, courseVersionKey)
) ENGINE=InnoDB;
