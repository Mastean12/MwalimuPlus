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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
