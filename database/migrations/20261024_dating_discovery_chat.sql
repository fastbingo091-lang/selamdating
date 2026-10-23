ALTER TABLE dating_profiles
    MODIFY visibility ENUM('private', 'discoverable') NOT NULL DEFAULT 'private',
    ADD COLUMN telegram_username VARCHAR(32) NULL AFTER visibility;

CREATE TABLE IF NOT EXISTS dating_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_low_id BIGINT UNSIGNED NOT NULL,
    user_high_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dating_conversation_pair (user_low_id, user_high_id),
    CONSTRAINT chk_dating_conversation_order CHECK (user_low_id < user_high_id),
    CONSTRAINT fk_dating_conversation_low FOREIGN KEY (user_low_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_dating_conversation_high FOREIGN KEY (user_high_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dating_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dating_messages_conversation_created (conversation_id, created_at, id),
    KEY idx_dating_messages_sender (conversation_id, sender_id),
    CONSTRAINT fk_dating_messages_conversation FOREIGN KEY (conversation_id) REFERENCES dating_conversations (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_dating_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
