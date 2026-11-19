-- Additive storage only. Apply to an explicitly selected local/test database.
CREATE TABLE IF NOT EXISTS Admin_Feature_Lock (
 lock_ID INT PRIMARY KEY
);
INSERT IGNORE INTO Admin_Feature_Lock(lock_ID) VALUES (1);
CREATE TABLE IF NOT EXISTS Admin_Action_Log (
 log_ID BIGINT AUTO_INCREMENT PRIMARY KEY,
 admin_ID INT NOT NULL,
 action_Name VARCHAR(64) NOT NULL,
 before_Data JSON NOT NULL,
 after_Data JSON NOT NULL,
 reason VARCHAR(500) NULL,
 created_At TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (admin_ID) REFERENCES Admin(admin_ID)
);
