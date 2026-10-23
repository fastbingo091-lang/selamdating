CREATE TABLE IF NOT EXISTS dating_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('woman', 'man', 'non_binary', 'prefer_not_to_say') NOT NULL,
    city VARCHAR(80) NULL,
    bio VARCHAR(500) NULL,
    visibility ENUM('private') NOT NULL DEFAULT 'private',
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
