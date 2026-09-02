<?php
/**
 * Idempotent schema bootstrap.
 *
 * ensure_schema(PDO) creates the database and every table the app needs,
 * using CREATE TABLE IF NOT EXISTS so it is safe to run on every connect.
 * This is what lets setup.php bring a fresh install (or an empty database)
 * up to date without a manual mysql import.
 */

declare(strict_types=1);

/** Creates the configured database if it does not exist yet. */
function ensure_database(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $host = getenv('MWALIMU_DB_HOST') ?: '127.0.0.1';
    $port = getenv('MWALIMU_DB_PORT') ?: '3306';
    $name = getenv('MWALIMU_DB_NAME') ?: 'mwalimu_plus';
    $user = getenv('MWALIMU_DB_USER') ?: 'root';
    $pass = getenv('MWALIMU_DB_PASS') ?: '';

    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        // Let the main connection attempt surface the real error below.
    }
}

/** Ensures every table exists. Safe to call on every request. */
function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS subjects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(10) NOT NULL,
            name VARCHAR(100) NOT NULL,
            strand VARCHAR(190) NOT NULL,
            grade_level VARCHAR(20) NOT NULL DEFAULT 'Grade 10',
            PRIMARY KEY (id),
            UNIQUE KEY uq_subjects_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS topics (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            strand VARCHAR(190) NOT NULL DEFAULT '',
            source_file VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_topics_subject (subject_id),
            CONSTRAINT fk_topics_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lessons (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            subject_id INT UNSIGNED NOT NULL,
            topic_id INT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            status ENUM('SUPPORTED','NEEDS_VERIFICATION','UNKNOWN') NOT NULL DEFAULT 'SUPPORTED',
            duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 40,
            payload JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lessons_user (user_id),
            KEY idx_lessons_topic (topic_id),
            CONSTRAINT fk_lessons_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_lessons_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schemes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            subject_id INT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            term TINYINT UNSIGNED NOT NULL DEFAULT 1,
            lessons_per_week TINYINT UNSIGNED NOT NULL DEFAULT 4,
            start_week TINYINT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('SUPPORTED','NEEDS_VERIFICATION','UNKNOWN') NOT NULL DEFAULT 'SUPPORTED',
            payload JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_schemes_user (user_id),
            CONSTRAINT fk_schemes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_schemes_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_resets_user (user_id),
            KEY idx_resets_token (token_hash),
            CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
