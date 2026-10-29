CREATE TABLE IF NOT EXISTS user_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_user_id BIGINT UNSIGNED NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    title VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_notifications_recipient_created (recipient_user_id, created_at),
    KEY idx_user_notifications_created (created_at),
    CONSTRAINT fk_user_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_user_notifications_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notification_reads (
    notification_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, user_id),
    KEY idx_user_notification_reads_user_read (user_id, read_at),
    CONSTRAINT fk_user_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES user_notifications (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_user_notification_reads_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
