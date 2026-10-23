CREATE DATABASE IF NOT EXISTS goferbet
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE goferbet;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(254) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    telegram_user_id BIGINT UNSIGNED NULL,
    telegram_username VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_phone (phone),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_telegram_user_id (telegram_user_id),
    KEY idx_users_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dating_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('woman', 'man', 'non_binary', 'prefer_not_to_say') NOT NULL,
    city VARCHAR(80) NULL,
    bio VARCHAR(500) NULL,
    visibility ENUM('private', 'discoverable') NOT NULL DEFAULT 'private',
    telegram_username VARCHAR(32) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dating_profiles_user (user_id),
    CONSTRAINT fk_dating_profiles_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dating_profile_preferences (
    profile_id BIGINT UNSIGNED NOT NULL,
    preference ENUM('women', 'men', 'everyone') NOT NULL,
    PRIMARY KEY (profile_id, preference),
    CONSTRAINT fk_dating_preferences_profile FOREIGN KEY (profile_id) REFERENCES dating_profiles (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dating_profile_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    profile_id BIGINT UNSIGNED NOT NULL,
    media_type ENUM('photo', 'video') NOT NULL,
    storage_name VARCHAR(41) NOT NULL,
    mime_type VARCHAR(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dating_media_storage (storage_name),
    KEY idx_dating_media_profile_type_created (profile_id, media_type, created_at),
    CONSTRAINT fk_dating_media_profile FOREIGN KEY (profile_id) REFERENCES dating_profiles (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username),
    KEY idx_admins_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_presence (
    admin_id BIGINT UNSIGNED NOT NULL,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id),
    KEY idx_admin_presence_last_seen (last_seen_at),
    CONSTRAINT fk_admin_presence_admin FOREIGN KEY (admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    currency VARCHAR(16) NOT NULL DEFAULT 'ETB',
    balance DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    locked_balance DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wallets_user_currency (user_id, currency),
    KEY idx_wallets_currency (currency),
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_wallets_balance_nonnegative CHECK (balance >= 0),
    CONSTRAINT chk_wallets_locked_balance CHECK (locked_balance >= 0 AND locked_balance <= balance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    wallet_id BIGINT UNSIGNED NOT NULL,
    type ENUM('DEPOSIT', 'WITHDRAW', 'TRANSFER_IN', 'TRANSFER_OUT', 'ADMIN_ADJUSTMENT', 'BET', 'WIN', 'REFUND') NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    balance_before DECIMAL(20,8) NOT NULL,
    balance_after DECIMAL(20,8) NOT NULL,
    reference VARCHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('pending', 'completed', 'failed', 'reversed') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transactions_reference (reference),
    KEY idx_transactions_user_created (user_id, created_at),
    KEY idx_transactions_wallet_created (wallet_id, created_at),
    KEY idx_transactions_type_status (type, status),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_wallet FOREIGN KEY (wallet_id) REFERENCES wallets (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_transactions_amount_nonzero CHECK (amount <> 0),
    CONSTRAINT chk_transactions_balances_nonnegative CHECK (balance_before >= 0 AND balance_after >= 0),
    CONSTRAINT chk_transactions_balance_matches CHECK (balance_after = balance_before + amount),
    CONSTRAINT chk_transactions_amount_direction CHECK (
        (type IN ('DEPOSIT', 'TRANSFER_IN', 'WIN', 'REFUND') AND amount > 0)
        OR (type IN ('WITHDRAW', 'TRANSFER_OUT', 'BET') AND amount < 0)
        OR (type = 'ADMIN_ADJUSTMENT' AND amount <> 0)
    )
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

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    admin_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_logs_user_created (user_id, created_at),
    KEY idx_activity_logs_admin_created (admin_id, created_at),
    KEY idx_activity_logs_action_created (action, created_at),
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activity_logs_admin FOREIGN KEY (admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
