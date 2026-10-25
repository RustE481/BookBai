CREATE DATABASE IF NOT EXISTS bbai
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bbai;

-- Creates the current schema and upgrades the older BookBai user table without deleting accounts.
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    course VARCHAR(100) NOT NULL DEFAULT '',
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

SET @school_id_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_school_id'
);
SET @drop_school_id_index_sql = IF(
    @school_id_index_exists > 0,
    'ALTER TABLE users DROP INDEX uq_users_school_id',
    'SELECT 1'
);
PREPARE drop_school_id_index_statement FROM @drop_school_id_index_sql;
EXECUTE drop_school_id_index_statement;
DEALLOCATE PREPARE drop_school_id_index_statement;

SET @school_id_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'school_id'
);
SET @drop_school_id_column_sql = IF(
    @school_id_column_exists > 0,
    'ALTER TABLE users DROP COLUMN school_id',
    'SELECT 1'
);
PREPARE drop_school_id_column_statement FROM @drop_school_id_column_sql;
EXECUTE drop_school_id_column_statement;
DEALLOCATE PREPARE drop_school_id_column_statement;

SET @email_unique_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_email'
);
SET @add_email_unique_index_sql = IF(
    @email_unique_index_exists = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)',
    'SELECT 1'
);
PREPARE add_email_unique_index_statement FROM @add_email_unique_index_sql;
EXECUTE add_email_unique_index_statement;
DEALLOCATE PREPARE add_email_unique_index_statement;

ALTER TABLE users
    MODIFY role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student';

CREATE TABLE IF NOT EXISTS books (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    author VARCHAR(80) NOT NULL,
    subject VARCHAR(80) NOT NULL,
    category ENUM('Textbook', 'Fiction', 'Nonfiction', 'Reference') NOT NULL,
    `condition` ENUM('Like new', 'Very good', 'Good', 'Fair') NOT NULL,
    price DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
    type ENUM('For sale', 'Donation', 'Exchange') NOT NULL,
    description VARCHAR(500) NOT NULL,
    image VARCHAR(255) NULL,
    status ENUM('Available', 'Sold', 'Donated', 'Exchanged', 'Unavailable') NOT NULL DEFAULT 'Available',
    approved TINYINT(1) NOT NULL DEFAULT 0,
    removed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_books_browse (approved, removed, status, created_at),
    KEY idx_books_owner (user_id, created_at),
    CONSTRAINT fk_books_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_books (
    user_id BIGINT UNSIGNED NOT NULL,
    book_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, book_id),
    CONSTRAINT fk_saved_books_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_books_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(500) NOT NULL,
    offered_book VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_recipient (recipient_id, created_at),
    KEY idx_messages_sender (sender_id, created_at),
    CONSTRAINT fk_messages_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id BIGINT UNSIGNED NOT NULL,
    reported_user_id BIGINT UNSIGNED NULL,
    book_id BIGINT UNSIGNED NULL,
    message_id BIGINT UNSIGNED NULL,
    reason ENUM('scam', 'offensive', 'harassment', 'inappropriate', 'other') NOT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('open', 'reviewed', 'dismissed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reports_reporter_message (reporter_id, message_id),
    KEY idx_reports_status (status, created_at),
    KEY idx_reports_book (book_id),
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_reports_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE SET NULL,
    CONSTRAINT fk_reports_message FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Existing accounts continue to log in with their email and current password.
-- Resolve duplicate email addresses before upgrading an existing users table.
-- New accounts are self-registered with the student role. Promote trusted administrators manually.
