CREATE DATABASE IF NOT EXISTS drivew_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drivew_db;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS file_versions;
DROP TABLE IF EXISTS file_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS shares;
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS folders;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  display_name  VARCHAR(80)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  storage_quota BIGINT UNSIGNED NOT NULL DEFAULT 1073741824, -- 1 GB in bytes
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE folders (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  parent_id  INT UNSIGNED NULL,
  name       VARCHAR(180) NOT NULL,
  color      CHAR(7) NULL,                 -- hex color for visual tag
  is_starred TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_folders_user    FOREIGN KEY (user_id)    REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_folders_parent   FOREIGN KEY (parent_id)  REFERENCES folders(id) ON DELETE CASCADE,
  INDEX idx_folders_user (user_id),
  INDEX idx_folders_parent (parent_id),
  UNIQUE KEY uq_folder_name (user_id, parent_id, name)
) ENGINE=InnoDB;

CREATE TABLE files (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  parent_folder_id  INT UNSIGNED NULL,
  original_name     VARCHAR(255) NOT NULL,
  stored_path       VARCHAR(255) NOT NULL,
  ext               VARCHAR(20)  NOT NULL,
  mime_type         VARCHAR(150) NOT NULL,
  size              BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_starred        TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted        TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at        DATETIME NULL,
  version_num       INT UNSIGNED NOT NULL DEFAULT 1,
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL,
  last_downloaded_at DATETIME NULL,
  CONSTRAINT fk_files_user FOREIGN KEY (user_id)           REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_files_folder FOREIGN KEY (parent_folder_id) REFERENCES folders(id) ON DELETE SET NULL,
  INDEX idx_files_user (user_id),
  INDEX idx_files_folder (parent_folder_id),
  INDEX idx_files_name (user_id, original_name),
  INDEX idx_files_deleted (is_deleted)
) ENGINE=InnoDB;

CREATE TABLE file_versions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id       INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  version_num   INT UNSIGNED NOT NULL,
  stored_path   VARCHAR(255) NOT NULL,
  size          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL,
  CONSTRAINT fk_fv_file FOREIGN KEY (file_id)   REFERENCES files(id)   ON DELETE CASCADE,
  CONSTRAINT fk_fv_user FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  UNIQUE KEY uq_file_version (file_id, version_num),
  INDEX idx_fv_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE tags (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(60) NOT NULL,
  color      CHAR(7) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_tag (user_id, name),
  INDEX idx_tags_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE file_tags (
  file_id INT UNSIGNED NOT NULL,
  tag_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (file_id, tag_id),
  CONSTRAINT fk_ft_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
  CONSTRAINT fk_ft_tag  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shares (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  resource_type ENUM('file','folder') NOT NULL,
  resource_id   INT UNSIGNED NOT NULL,
  token         CHAR(64) NOT NULL UNIQUE,   -- sha256 of random bytes
  password_hash VARCHAR(255) NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  expires_at    DATETIME NULL,
  view_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL,
  CONSTRAINT fk_shares_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_shares_user (user_id),
  INDEX idx_shares_active (is_active, expires_at)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NULL,
  action        VARCHAR(50)  NOT NULL,
  resource_type VARCHAR(30)  NOT NULL DEFAULT '',
  resource_id   INT UNSIGNED NOT NULL DEFAULT 0,
  description   VARCHAR(255) NOT NULL DEFAULT '',
  ip_address    VARCHAR(45)  NOT NULL DEFAULT '0.0.0.0',
  created_at    DATETIME NOT NULL,
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_logs_user (user_id),
  INDEX idx_logs_action (action, created_at)
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key   VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL
) ENGINE=InnoDB;