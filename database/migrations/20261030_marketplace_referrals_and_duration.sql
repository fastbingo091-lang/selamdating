ALTER TABLE products
    ADD COLUMN image_path VARCHAR(255) NULL AFTER description,
    ADD COLUMN profit_duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER profit;

ALTER TABLE marketplace_purchases
    ADD COLUMN image_path VARCHAR(255) NULL AFTER product_name,
    ADD COLUMN duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER daily_profit;

ALTER TABLE users
    ADD COLUMN referral_code VARCHAR(24) NULL AFTER telegram_username,
    ADD COLUMN referred_by_user_id BIGINT UNSIGNED NULL AFTER referral_code,
    ADD UNIQUE KEY uq_users_referral_code (referral_code),
    ADD KEY idx_users_referred_by (referred_by_user_id),
    ADD CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE SET NULL;

UPDATE users
SET referral_code = CONCAT('SELAM', LPAD(id, 12, '0'))
WHERE referral_code IS NULL;

ALTER TABLE users
    MODIFY COLUMN referral_code VARCHAR(24) NOT NULL;

CREATE TABLE IF NOT EXISTS product_commissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    claim_id BIGINT UNSIGNED NOT NULL,
    beneficiary_user_id BIGINT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    rate_percent DECIMAL(5,2) NOT NULL,
    amount DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_commissions_claim_level (claim_id, level),
    KEY idx_product_commissions_beneficiary_created (beneficiary_user_id, created_at),
    CONSTRAINT fk_product_commissions_claim FOREIGN KEY (claim_id) REFERENCES product_claims (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_product_commissions_beneficiary FOREIGN KEY (beneficiary_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
