-- Additive, inactive source schedule staging. Legacy sections/rosters are untouched.
CREATE TABLE IF NOT EXISTS NB_SectionImport (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 catalog_id BIGINT UNSIGNED NOT NULL,
 source_sha256 CHAR(64) NOT NULL,
 source_url VARCHAR(255) NOT NULL,
 term_code VARCHAR(16) NOT NULL,
 status VARCHAR(64) NOT NULL DEFAULT 'source-import-inactive',
 source_manifest JSON NOT NULL,
 UNIQUE KEY (catalog_id,source_sha256),
 FOREIGN KEY (catalog_id) REFERENCES NB_CatalogVersion(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_OfficialSection (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 import_id BIGINT UNSIGNED NOT NULL,
 course_version_id BIGINT UNSIGNED NULL,
 term_code VARCHAR(16) NOT NULL,
 official_crn VARCHAR(24) NOT NULL,
 section_label VARCHAR(24) NOT NULL,
 official_course_code VARCHAR(32) NOT NULL,
 course_title VARCHAR(255) NOT NULL,
 part_of_term VARCHAR(16) NULL,
 project_capacity SMALLINT UNSIGNED NOT NULL DEFAULT 10,
 registration_active TINYINT NOT NULL DEFAULT 0,
 fictional_faculty_id INT NULL,
 fictional_room_id VARCHAR(20) NULL,
 review_status VARCHAR(96) NOT NULL,
 source_json JSON NOT NULL,
 UNIQUE KEY (import_id,term_code,official_crn),
 FOREIGN KEY (import_id) REFERENCES NB_SectionImport(id),
 FOREIGN KEY (course_version_id) REFERENCES NB_CourseVersion(id),
 FOREIGN KEY (fictional_faculty_id) REFERENCES Faculty(faculty_ID),
 FOREIGN KEY (fictional_room_id) REFERENCES Room(room_ID)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_OfficialMeeting (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 section_id BIGINT UNSIGNED NOT NULL,
 ordinal INT UNSIGNED NOT NULL,
 begin_time VARCHAR(8) NULL,
 end_time VARCHAR(8) NULL,
 start_date DATE NULL,
 end_date DATE NULL,
 days_json JSON NOT NULL,
 source_json JSON NOT NULL,
 UNIQUE KEY (section_id,ordinal),
 FOREIGN KEY (section_id) REFERENCES NB_OfficialSection(id)
) ENGINE=InnoDB;
