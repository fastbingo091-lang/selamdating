-- Run once against an existing Stage 1 GoferBet database before deploying phone signup.
-- Back up the database first. Existing users keep their accounts and may have a NULL phone
-- until a future profile flow lets them add one. New registrations require a phone in PHP.
ALTER TABLE users
    MODIFY COLUMN email VARCHAR(254) NULL,
    ADD COLUMN phone VARCHAR(20) NULL AFTER username,
    ADD COLUMN telegram_user_id BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN telegram_username VARCHAR(64) NULL AFTER telegram_user_id,
    ADD UNIQUE KEY uq_users_phone (phone),
    ADD UNIQUE KEY uq_users_telegram_user_id (telegram_user_id);
