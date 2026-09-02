-- MwalimuPlus schema
-- MySQL 8.x on Laragon (utf8mb4)

CREATE DATABASE IF NOT EXISTS mwalimu_plus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mwalimu_plus;

-- Teachers (auth is included in this build)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KICD curriculum catalogue (demo corpus)
CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(10) NOT NULL,
  name VARCHAR(100) NOT NULL,
  strand VARCHAR(190) NOT NULL,
  grade_level VARCHAR(20) NOT NULL DEFAULT 'Grade 10',
  PRIMARY KEY (id),
  UNIQUE KEY uq_subjects_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  strand VARCHAR(190) NOT NULL DEFAULT '',
  source_file VARCHAR(255) NOT NULL DEFAULT '',
  source_text MEDIUMTEXT NULL,
  PRIMARY KEY (id),
  KEY idx_topics_subject (subject_id),
  CONSTRAINT fk_topics_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher-generated lessons
CREATE TABLE IF NOT EXISTS lessons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  subject_id INT UNSIGNED NOT NULL,
  topic_id INT UNSIGNED NOT NULL,
  scheme_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  status ENUM('SUPPORTED','NEEDS_VERIFICATION','UNKNOWN') NOT NULL DEFAULT 'SUPPORTED',
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lessons_user (user_id),
  KEY idx_lessons_topic (topic_id),
  KEY idx_lessons_scheme (scheme_id),
  CONSTRAINT fk_lessons_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_lessons_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- fk_lessons_scheme is added below, after `schemes` exists (lessons is created first).

-- Teacher-generated schemes of work (rows live in payload JSON)
CREATE TABLE IF NOT EXISTS schemes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lessons ADD CONSTRAINT fk_lessons_scheme
  FOREIGN KEY (scheme_id) REFERENCES schemes (id) ON DELETE SET NULL;

-- Teacher-added learning materials attached to a scheme (scheme-level or per row)
CREATE TABLE IF NOT EXISTS scheme_resources (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher-added media/resources attached to a lesson (lesson-level or per section)
CREATE TABLE IF NOT EXISTS lesson_resources (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-use password reset tokens (only the hash is stored)
CREATE TABLE IF NOT EXISTS password_resets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
