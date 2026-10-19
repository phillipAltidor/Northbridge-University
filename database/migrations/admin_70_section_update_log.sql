-- Supplemental section-change history. Course_Section keeps the Schema 2.3 definition.
CREATE TABLE IF NOT EXISTS Course_Section_Update_Log (
    log_ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    CRN INT NOT NULL,
    admin_ID INT NOT NULL,
    updated_At TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    before_Data JSON NOT NULL,
    after_Data JSON NOT NULL,
    INDEX section_update_history (CRN, log_ID),
    FOREIGN KEY (CRN) REFERENCES Course_Section(CRN),
    FOREIGN KEY (admin_ID) REFERENCES Admin(admin_ID)
);
