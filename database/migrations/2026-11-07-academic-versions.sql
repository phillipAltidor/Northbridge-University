-- Additive test schema. Never rename or update legacy academic/person tables.
CREATE TABLE IF NOT EXISTS NB_CatalogVersion (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 edition VARCHAR(32) NOT NULL,
 package_sha256 CHAR(64) NOT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'isolated-test',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY (edition, package_sha256)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_CourseVersion (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 catalog_id BIGINT UNSIGNED NOT NULL,
 version_key VARCHAR(64) NOT NULL,
 course_code VARCHAR(24) NOT NULL,
 title VARCHAR(255) NOT NULL,
 credit_min DECIMAL(6,2) NULL,
 credit_max DECIMAL(6,2) NULL,
 definition JSON NOT NULL,
 UNIQUE KEY (catalog_id, version_key),
 FOREIGN KEY (catalog_id) REFERENCES NB_CatalogVersion(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_ProgramVersion (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 catalog_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(255) NOT NULL,
 kind VARCHAR(16) NOT NULL,
 evaluator VARCHAR(40) NOT NULL DEFAULT 'review-required',
 definition JSON NOT NULL,
 UNIQUE KEY (catalog_id, name, kind),
 FOREIGN KEY (catalog_id) REFERENCES NB_CatalogVersion(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_RequirementGroup (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 program_version_id BIGINT UNSIGNED NOT NULL,
 ordinal INT UNSIGNED NOT NULL,
 definition JSON NOT NULL,
 UNIQUE KEY (program_version_id, ordinal),
 FOREIGN KEY (program_version_id) REFERENCES NB_ProgramVersion(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_Prerequisite (
 course_version_id BIGINT UNSIGNED PRIMARY KEY,
 review_status VARCHAR(64) NOT NULL,
 definition JSON NOT NULL,
 FOREIGN KEY (course_version_id) REFERENCES NB_CourseVersion(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_GEApproval (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 catalog_id BIGINT UNSIGNED NOT NULL,
 ordinal INT UNSIGNED NOT NULL,
 definition JSON NOT NULL,
 UNIQUE KEY (catalog_id, ordinal),
 FOREIGN KEY (catalog_id) REFERENCES NB_CatalogVersion(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS NB_AcademicPolicy (
 catalog_id BIGINT UNSIGNED NOT NULL,
 policy_key VARCHAR(64) NOT NULL,
 definition JSON NOT NULL,
 PRIMARY KEY (catalog_id, policy_key),
 FOREIGN KEY (catalog_id) REFERENCES NB_CatalogVersion(id)
) ENGINE=InnoDB;
