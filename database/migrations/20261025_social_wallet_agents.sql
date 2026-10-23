CREATE TABLE IF NOT EXISTS dating_profile_likes (
    profile_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (profile_id, user_id),
    KEY idx_dating_profile_likes_user (user_id, created_at),
    CONSTRAINT fk_dating_profile_likes_profile FOREIGN KEY (profile_id) REFERENCES dating_profiles (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_dating_profile_likes_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dating_profile_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    profile_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dating_profile_comments_profile_created (profile_id, created_at, id),
    KEY idx_dating_profile_comments_user (user_id, created_at),
    CONSTRAINT fk_dating_profile_comments_profile FOREIGN KEY (profile_id) REFERENCES dating_profiles (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_dating_profile_comments_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    wallet_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    currency VARCHAR(16) NOT NULL DEFAULT 'ETB',
    destination_provider ENUM('Bank', 'Telebirr') NOT NULL,
    destination_name VARCHAR(120) NOT NULL,
    destination_account VARCHAR(100) NOT NULL,
    status ENUM('pending', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
    payout_reference VARCHAR(150) NULL,
    rejection_reason VARCHAR(500) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_withdrawal_requests_status_created (status, created_at),
    KEY idx_withdrawal_requests_user_created (user_id, created_at),
    CONSTRAINT fk_withdrawal_requests_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_withdrawal_requests_wallet FOREIGN KEY (wallet_id) REFERENCES wallets (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_withdrawal_requests_admin FOREIGN KEY (reviewed_by) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_withdrawal_requests_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_presence (
    admin_id BIGINT UNSIGNED NOT NULL,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id),
    KEY idx_admin_presence_last_seen (last_seen_at),
    CONSTRAINT fk_admin_presence_admin FOREIGN KEY (admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
