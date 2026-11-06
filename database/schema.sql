-- Campus Lost & Found System: schema (14 InnoDB tables, utf8mb4)
-- Order matters: parent tables come before the tables that reference them.

SET NAMES utf8mb4;

-- 1. USERS -------------------------------------------------------------
CREATE TABLE users (
    user_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id          VARCHAR(20)  NULL,
    full_name           VARCHAR(100) NOT NULL,
    university_email    VARCHAR(150) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    phone               VARCHAR(20)  NULL,
    role                ENUM('student','custodian','admin') NOT NULL DEFAULT 'student',
    verification_status ENUM('pending','verified') NOT NULL DEFAULT 'pending',
    account_status      ENUM('active','suspended','deactivated') NOT NULL DEFAULT 'active',
    suspended_until     DATETIME     NULL,
    suspension_reason   VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_student_id (student_id),
    UNIQUE KEY uq_users_email (university_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. EMAIL_VERIFICATIONS -----------------------------------------------
CREATE TABLE email_verifications (
    verification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      CHAR(64)     NOT NULL,
    purpose         ENUM('verify_email','reset_password') NOT NULL,
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (verification_id),
    UNIQUE KEY uq_email_verifications_token (token_hash),
    KEY idx_email_verifications_user (user_id),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CATEGORIES --------------------------------------------------------
CREATE TABLE categories (
    category_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CAMPUS_LOCATIONS --------------------------------------------------
CREATE TABLE campus_locations (
    location_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_name VARCHAR(100) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (location_id),
    UNIQUE KEY uq_locations_name (location_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ITEMS -------------------------------------------------------------
CREATE TABLE items (
    item_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id   INT UNSIGNED NOT NULL,
    report_type   ENUM('lost','found') NOT NULL,
    title         VARCHAR(120) NOT NULL,
    category_id   INT UNSIGNED NOT NULL,
    location_id   INT UNSIGNED NOT NULL,
    description   TEXT         NOT NULL,
    incident_date DATE         NOT NULL,
    item_status   ENUM('open','claim_pending','ready_for_handover','recovered','closed','removed')
                  NOT NULL DEFAULT 'open',
    is_high_value TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY idx_items_status_type_date (item_status, report_type, incident_date),
    KEY idx_items_category_location (category_id, location_id),
    KEY idx_items_reporter_created (reporter_id, created_at),
    FULLTEXT KEY ft_items_title_desc (title, description),
    CONSTRAINT fk_items_reporter FOREIGN KEY (reporter_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE RESTRICT,
    CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES campus_locations (location_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ITEM_IMAGES -------------------------------------------------------
CREATE TABLE item_images (
    image_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id     INT UNSIGNED NOT NULL,
    image_path  VARCHAR(255) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (image_id),
    KEY idx_item_images_item (item_id),
    CONSTRAINT fk_images_item FOREIGN KEY (item_id) REFERENCES items (item_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CLAIMS ------------------------------------------------------------
CREATE TABLE claims (
    claim_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id           INT UNSIGNED NOT NULL,
    claimant_id       INT UNSIGNED NOT NULL,
    ownership_details TEXT         NOT NULL,
    claim_status      ENUM('pending','under_review','approved','rejected','withdrawn')
                      NOT NULL DEFAULT 'pending',
    reviewed_by       INT UNSIGNED NULL,
    review_reason     TEXT         NULL,
    reviewed_at       DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (claim_id),
    UNIQUE KEY uq_claims_item_claimant (item_id, claimant_id),
    KEY idx_claims_item_status (item_id, claim_status),
    KEY idx_claims_claimant (claimant_id),
    KEY idx_claims_reviewer (reviewed_by),
    CONSTRAINT fk_claims_item     FOREIGN KEY (item_id)     REFERENCES items (item_id) ON DELETE RESTRICT,
    CONSTRAINT fk_claims_claimant FOREIGN KEY (claimant_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_claims_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT chk_claims_reviewer_not_claimant CHECK (reviewed_by IS NULL OR reviewed_by <> claimant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. PRIVATE_ITEM_EVIDENCE ---------------------------------------------
CREATE TABLE private_item_evidence (
    evidence_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id             INT UNSIGNED NOT NULL,
    claim_id            INT UNSIGNED NULL,
    evidence_type       ENUM('item_detail','claim_proof') NOT NULL,
    submitted_by        INT UNSIGNED NOT NULL,
    private_description TEXT         NOT NULL,
    file_reference      VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evidence_id),
    KEY idx_evidence_item (item_id),
    KEY idx_evidence_claim (claim_id),
    KEY idx_evidence_submitter (submitted_by),
    CONSTRAINT fk_evidence_item      FOREIGN KEY (item_id)      REFERENCES items (item_id)   ON DELETE RESTRICT,
    CONSTRAINT fk_evidence_claim     FOREIGN KEY (claim_id)     REFERENCES claims (claim_id) ON DELETE RESTRICT,
    CONSTRAINT fk_evidence_submitter FOREIGN KEY (submitted_by) REFERENCES users (user_id)   ON DELETE RESTRICT,
    CONSTRAINT chk_evidence_type_claim CHECK (
        (evidence_type = 'item_detail' AND claim_id IS NULL)
        OR (evidence_type = 'claim_proof' AND claim_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. HANDOVERS ---------------------------------------------------------
CREATE TABLE handovers (
    handover_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    claim_id        INT UNSIGNED NOT NULL,
    custodian_id    INT UNSIGNED NOT NULL,
    handover_status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    scheduled_at    DATETIME     NOT NULL,
    completed_at    DATETIME     NULL,
    notes           VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (handover_id),
    UNIQUE KEY uq_handovers_claim (claim_id),
    KEY idx_handovers_custodian (custodian_id),
    CONSTRAINT fk_handovers_claim     FOREIGN KEY (claim_id)      REFERENCES claims (claim_id) ON DELETE RESTRICT,
    CONSTRAINT fk_handovers_custodian FOREIGN KEY (custodian_id)  REFERENCES users (user_id)   ON DELETE RESTRICT,
    CONSTRAINT chk_handovers_completed CHECK ((handover_status = 'completed') = (completed_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. MESSAGES ---------------------------------------------------------
CREATE TABLE messages (
    message_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id        INT UNSIGNED NOT NULL,
    sender_id      INT UNSIGNED NOT NULL,
    recipient_id   INT UNSIGNED NOT NULL,
    message_text   TEXT         NOT NULL,
    message_status ENUM('sent','read','removed') NOT NULL DEFAULT 'sent',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    KEY idx_messages_item_created (item_id, created_at),
    KEY idx_messages_recipient_status (recipient_id, message_status),
    KEY idx_messages_sender (sender_id),
    CONSTRAINT fk_messages_item      FOREIGN KEY (item_id)      REFERENCES items (item_id) ON DELETE RESTRICT,
    CONSTRAINT fk_messages_sender    FOREIGN KEY (sender_id)    REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT chk_messages_not_self CHECK (sender_id <> recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. NOTIFICATIONS ----------------------------------------------------
CREATE TABLE notifications (
    notification_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    related_item_id   INT UNSIGNED NULL,
    notification_type ENUM('claim_submitted','claim_approved','claim_rejected','new_message',
                           'handover_scheduled','item_recovered','report_update') NOT NULL,
    notification_text VARCHAR(255) NOT NULL,
    is_read           TINYINT(1)   NOT NULL DEFAULT 0,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notifications_user_read_created (user_id, is_read, created_at),
    KEY idx_notifications_item (related_item_id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)         REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_notifications_item FOREIGN KEY (related_item_id) REFERENCES items (item_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. REPORTS ----------------------------------------------------------
CREATE TABLE reports (
    report_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id      INT UNSIGNED NOT NULL,
    reported_user_id INT UNSIGNED NULL,
    item_id          INT UNSIGNED NULL,
    message_id       INT UNSIGNED NULL,
    reason           ENUM('spam','fake_listing','harassment','inappropriate','other') NOT NULL,
    details          TEXT         NULL,
    report_status    ENUM('open','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
    moderator_id     INT UNSIGNED NULL,
    resolution_notes TEXT         NULL,
    resolved_at      DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    KEY idx_reports_status_created (report_status, created_at),
    KEY idx_reports_reporter (reporter_id),
    KEY idx_reports_reported_user (reported_user_id),
    KEY idx_reports_item (item_id),
    KEY idx_reports_message (message_id),
    KEY idx_reports_moderator (moderator_id),
    CONSTRAINT fk_reports_reporter  FOREIGN KEY (reporter_id)      REFERENCES users (user_id)       ON DELETE RESTRICT,
    CONSTRAINT fk_reports_reported  FOREIGN KEY (reported_user_id) REFERENCES users (user_id)       ON DELETE RESTRICT,
    CONSTRAINT fk_reports_item      FOREIGN KEY (item_id)          REFERENCES items (item_id)       ON DELETE RESTRICT,
    CONSTRAINT fk_reports_message   FOREIGN KEY (message_id)       REFERENCES messages (message_id) ON DELETE RESTRICT,
    CONSTRAINT fk_reports_moderator FOREIGN KEY (moderator_id)     REFERENCES users (user_id)       ON DELETE RESTRICT,
    CONSTRAINT chk_reports_has_target CHECK (
        reported_user_id IS NOT NULL OR item_id IS NOT NULL OR message_id IS NOT NULL
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. AUDIT_LOGS -------------------------------------------------------
-- target is a type + id with NO foreign key, so a log row never depends on its target.
CREATE TABLE audit_logs (
    log_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id    INT UNSIGNED NULL,
    action      VARCHAR(50)  NOT NULL,
    target_type ENUM('user','item','claim','handover','report','message','evidence') NOT NULL,
    target_id   INT UNSIGNED NOT NULL,
    details     TEXT         NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_audit_target (target_type, target_id),
    KEY idx_audit_actor_created (actor_id, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. RATE_LIMITS ------------------------------------------------------
-- Standalone: no relationships, so guests and unknown emails can be counted.
CREATE TABLE rate_limits (
    limit_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    limit_key    VARCHAR(100) NOT NULL,
    window_start DATETIME     NOT NULL,
    hit_count    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (limit_id),
    UNIQUE KEY uq_rate_limits_key_window (limit_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;