-- Supplemental audit table for use case 53. Select the target database first.
CREATE TABLE IF NOT EXISTS User_Update_Log (
    log_ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_ID INT NOT NULL,
    admin_ID INT NOT NULL,
    updated_At TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    request_Note VARCHAR(1000) NOT NULL,
    before_Data JSON NOT NULL,
    after_Data JSON NOT NULL,
    INDEX user_update_history (user_ID, log_ID),
    FOREIGN KEY (user_ID) REFERENCES User(user_ID),
    FOREIGN KEY (admin_ID) REFERENCES Admin(admin_ID)
);
