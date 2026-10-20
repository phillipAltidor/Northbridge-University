-- Supplemental account-recovery storage; existing Schema 2.3 records are unchanged.
CREATE TABLE IF NOT EXISTS Password_Reset (
    request_ID CHAR(64) PRIMARY KEY,
    user_ID INT NULL,
    email_Hash CHAR(64) NOT NULL,
    ip_Hash CHAR(64) NOT NULL,
    credential_Hash CHAR(64) NULL,
    code_Hash VARCHAR(255) NULL,
    grant_Hash CHAR(64) NULL,
    attempts INT NOT NULL DEFAULT 0,
    created_At DATETIME NOT NULL,
    expires_At DATETIME NOT NULL,
    verified_At DATETIME NULL,
    consumed_At DATETIME NULL,
    INDEX reset_email_created (email_Hash, created_At),
    INDEX reset_ip_created (ip_Hash, created_At),
    INDEX reset_user (user_ID),
    FOREIGN KEY (user_ID) REFERENCES Login(user_ID) ON DELETE CASCADE
) ENGINE=InnoDB;
