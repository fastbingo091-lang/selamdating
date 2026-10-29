CREATE TABLE IF NOT EXISTS marketplace_partner_settings (
    id TINYINT UNSIGNED NOT NULL,
    upgrade_price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_marketplace_partner_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_marketplace_partner_settings_singleton CHECK (id = 1),
    CONSTRAINT chk_marketplace_partner_settings_price CHECK (upgrade_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO marketplace_partner_settings (id, upgrade_price, enabled)
VALUES (1, 0.00, 0);

CREATE TABLE IF NOT EXISTS marketplace_partner_memberships (
    user_id BIGINT UNSIGNED NOT NULL,
    amount_paid DECIMAL(20,2) NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL,
    upgraded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_marketplace_partner_transaction (transaction_id),
    CONSTRAINT fk_marketplace_partner_memberships_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_marketplace_partner_memberships_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_marketplace_partner_membership_amount CHECK (amount_paid > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
