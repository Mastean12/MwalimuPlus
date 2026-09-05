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

/** True if $column exists on $table in the current database (used by migrations below). */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
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
            is_hidden TINYINT UNSIGNED NOT NULL DEFAULT 0,
            payload JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_schemes_user (user_id),
            CONSTRAINT fk_schemes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_schemes_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // schemes.is_hidden: lets a teacher hide a scheme from the public browse.php
    // listing without deleting it. Added after the initial schemes table shipped,
    // so existing databases need the migration below.
    if (!column_exists($pdo, 'schemes', 'is_hidden')) {
        $pdo->exec('ALTER TABLE schemes ADD COLUMN is_hidden TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status');
    }

    // --- Migrations below: run on every connect. MySQL's ALTER TABLE has no
    // portable "IF NOT EXISTS" for ADD COLUMN/KEY, so check first / catch-and-ignore. ---

    // lessons.scheme_id: links a lesson generated from scheme.php's "Generate lesson
    // plan" row action back to the scheme (and row) it came from.
    if (!column_exists($pdo, 'lessons', 'scheme_id')) {
        $pdo->exec('ALTER TABLE lessons ADD COLUMN scheme_id INT UNSIGNED NULL AFTER topic_id');
    }
    try {
        $pdo->exec('ALTER TABLE lessons ADD KEY idx_lessons_scheme (scheme_id)');
    } catch (PDOException $e) {
        // Key already exists.
    }
    try {
        $pdo->exec(
            'ALTER TABLE lessons ADD CONSTRAINT fk_lessons_scheme
                FOREIGN KEY (scheme_id) REFERENCES schemes (id) ON DELETE SET NULL'
        );
    } catch (PDOException $e) {
        // Constraint already exists.
    }

    // topics.source_text: teacher-pasted curriculum source for a custom topic added
    // via the curriculum admin UI (as opposed to source_file, the bundled KICD corpus).
    if (!column_exists($pdo, 'topics', 'source_text')) {
        $pdo->exec('ALTER TABLE topics ADD COLUMN source_text MEDIUMTEXT NULL AFTER source_file');
    }

    // subjects.source_pdf: an uploaded curriculum PDF covering the whole subject
    // (stored filename under uploads/curriculum/), attached natively to the Claude
    // API request as a document — the same role source_file/source_text play, for
    // a subject added via the curriculum admin UI with no bundled corpus of its own.
    if (!column_exists($pdo, 'subjects', 'source_pdf')) {
        $pdo->exec('ALTER TABLE subjects ADD COLUMN source_pdf VARCHAR(255) NULL AFTER grade_level');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scheme_resources (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            scheme_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            row_key VARCHAR(16) NOT NULL DEFAULT '',
            kind ENUM('youtube','link','pdf') NOT NULL,
            label VARCHAR(190) NOT NULL,
            url VARCHAR(600) NOT NULL DEFAULT '',
            file_name VARCHAR(190) NOT NULL DEFAULT '',
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sr_scheme (scheme_id),
            CONSTRAINT fk_sr_scheme FOREIGN KEY (scheme_id) REFERENCES schemes (id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lesson_resources (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            section VARCHAR(40) NOT NULL DEFAULT '',
            kind ENUM('youtube','link','image','pdf') NOT NULL,
            label VARCHAR(190) NOT NULL,
            url VARCHAR(600) NOT NULL DEFAULT '',
            file_name VARCHAR(190) NOT NULL DEFAULT '',
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lr_lesson (lesson_id),
            CONSTRAINT fk_lr_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
            CONSTRAINT fk_lr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lesson_study_sets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            status ENUM('SUPPORTED','NEEDS_VERIFICATION','UNKNOWN') NOT NULL DEFAULT 'SUPPORTED',
            payload JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_study_lesson (lesson_id),
            CONSTRAINT fk_study_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
            CONSTRAINT fk_study_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lesson_presentations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            payload JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pres_lesson (lesson_id),
            CONSTRAINT fk_pres_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
            CONSTRAINT fk_pres_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
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

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(50) NOT NULL,
            setting_value TEXT NULL,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
