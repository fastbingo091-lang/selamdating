CREATE TABLE IF NOT EXISTS marketplace_coupons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    discount_amount DECIMAL(20,2) NOT NULL,
    max_redemptions INT UNSIGNED NULL,
    expires_at DATETIME NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_coupons_code (code),
    CONSTRAINT fk_marketplace_coupons_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_marketplace_coupons_discount_positive CHECK (discount_amount > 0),
    CONSTRAINT chk_marketplace_coupons_max_redemptions_positive CHECK (max_redemptions IS NULL OR max_redemptions > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_coupon_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    purchase_id BIGINT UNSIGNED NOT NULL,
    discount_amount DECIMAL(20,2) NOT NULL,
    redeemed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_coupon_user (coupon_id, user_id),
    UNIQUE KEY uq_marketplace_coupon_purchase (purchase_id),
    KEY idx_marketplace_coupon_redemptions_user (user_id, redeemed_at),
    CONSTRAINT fk_marketplace_coupon_redemptions_coupon FOREIGN KEY (coupon_id) REFERENCES marketplace_coupons (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_marketplace_coupon_redemptions_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_marketplace_coupon_redemptions_purchase FOREIGN KEY (purchase_id) REFERENCES marketplace_purchases (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_marketplace_coupon_redemptions_discount_positive CHECK (discount_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_daily_streak_settings (
    id TINYINT UNSIGNED NOT NULL,
    reward_amount DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_marketplace_streak_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_marketplace_streak_settings_singleton CHECK (id = 1),
    CONSTRAINT chk_marketplace_streak_settings_reward CHECK (reward_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO marketplace_daily_streak_settings (id, reward_amount, enabled)
VALUES (1, 0.00, 0);

CREATE TABLE IF NOT EXISTS marketplace_daily_streaks (
    user_id BIGINT UNSIGNED NOT NULL,
    current_streak INT UNSIGNED NOT NULL DEFAULT 0,
    last_claim_date DATE NULL,
    total_claims INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_marketplace_daily_streaks_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_daily_streak_claims (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    claim_date DATE NOT NULL,
    streak_day INT UNSIGNED NOT NULL,
    amount DECIMAL(20,2) NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_streak_user_day (user_id, claim_date),
    UNIQUE KEY uq_marketplace_streak_transaction (transaction_id),
    KEY idx_marketplace_streak_claims_date (claim_date),
    CONSTRAINT fk_marketplace_streak_claims_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_marketplace_streak_claims_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_marketplace_streak_claims_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
