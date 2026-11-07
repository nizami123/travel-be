-- =====================================================================
-- MULTI TRAVEL MARKETPLACE - DATABASE SCHEMA
-- MySQL 8.x | InnoDB | utf8mb4
-- Import via phpMyAdmin (buat database dulu, mis. `multi_travel`)
-- Urutan import: database.sql  ->  seeders.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `multi_travel`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `multi_travel`;

-- ---------------------------------------------------------------------
-- 1. USER, ROLE, PERMISSION
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type`         ENUM('super_admin','admin_platform','admin_travel','staff_travel','customer') NOT NULL DEFAULT 'customer',
  `full_name`         VARCHAR(150) NOT NULL,
  `email`             VARCHAR(150) NOT NULL,
  `phone`             VARCHAR(20)  NOT NULL COMMENT 'Format 62xxxxxxxxxx (WhatsApp)',
  `password_hash`     VARCHAR(255) NOT NULL COMMENT 'bcrypt via password_hash()',
  `status`            ENUM('pending','active','inactive','suspended') NOT NULL DEFAULT 'pending',
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `last_login_at`     DATETIME NULL,
  `failed_login_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`      DATETIME NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_type_status` (`user_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_profiles` (
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `avatar_path`   VARCHAR(255) NULL,
  `gender`        ENUM('male','female') NULL,
  `birth_date`    DATE NULL,
  `birth_place`   VARCHAR(100) NULL,
  `address`       TEXT NULL,
  `city`          VARCHAR(100) NULL,
  `province`      VARCHAR(100) NULL,
  `postal_code`   VARCHAR(10) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(50) NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `scope`       ENUM('platform','partner','customer') NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(80) NOT NULL COMMENT 'format modul.aksi',
  `module`      VARCHAR(50) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `scope`       ENUM('platform','partner','both') NOT NULL DEFAULT 'both',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_code` (`code`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_tokens` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `token_hash`    CHAR(64) NOT NULL COMMENT 'SHA-256 dari token; token asli tidak disimpan',
  `device_name`   VARCHAR(120) NULL,
  `ip_address`    VARCHAR(45) NULL,
  `last_used_at`  DATETIME NULL,
  `expires_at`    DATETIME NOT NULL,
  `revoked_at`    DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_tokens_user` (`user_id`,`expires_at`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `otp_codes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL,
  `target`      VARCHAR(150) NOT NULL COMMENT 'email atau nomor WhatsApp',
  `purpose`     ENUM('register','login','reset_password','verify_phone','verify_email') NOT NULL,
  `code_hash`   VARCHAR(255) NOT NULL,
  `attempts`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`  DATETIME NOT NULL,
  `used_at`     DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_target` (`target`,`purpose`,`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_limits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `limit_key`   VARCHAR(190) NOT NULL COMMENT 'mis. login:IP, otp:target',
  `hits`        INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_key` (`limit_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TRAVEL PARTNER
-- ---------------------------------------------------------------------
CREATE TABLE `travel_partners` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(30) NOT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `slug`            VARCHAR(170) NOT NULL,
  `logo_path`       VARCHAR(255) NULL,
  `cover_path`      VARCHAR(255) NULL,
  `description`     TEXT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(20) NOT NULL,
  `whatsapp`        VARCHAR(20) NULL,
  `website`         VARCHAR(190) NULL,
  `address`         TEXT NULL,
  `city`            VARCHAR(100) NULL,
  `province`        VARCHAR(100) NULL,
  `latitude`        DECIMAL(10,7) NULL,
  `longitude`       DECIMAL(10,7) NULL,
  `business_license_no` VARCHAR(100) NULL COMMENT 'NIB / SIUP',
  `ppiu_number`     VARCHAR(100) NULL COMMENT 'Izin PPIU (Umroh)',
  `pihk_number`     VARCHAR(100) NULL COMMENT 'Izin PIHK (Haji Khusus)',
  `verification_status` ENUM('pending','under_review','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by`     BIGINT UNSIGNED NULL,
  `verified_at`     DATETIME NULL,
  `rejection_note`  VARCHAR(255) NULL,
  `status`          ENUM('active','inactive','suspended') NOT NULL DEFAULT 'inactive',
  `is_featured`     TINYINT(1) NOT NULL DEFAULT 0,
  `rating_avg`      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `rating_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partner_code` (`code`),
  UNIQUE KEY `uq_partner_slug` (`slug`),
  KEY `idx_partner_status` (`status`,`verification_status`),
  KEY `idx_partner_featured` (`is_featured`,`status`),
  CONSTRAINT `fk_partner_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `travel_partner_users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `user_id`           BIGINT UNSIGNED NOT NULL,
  `position`          VARCHAR(100) NULL,
  `is_owner`          TINYINT(1) NOT NULL DEFAULT 0,
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpu_user` (`user_id`) COMMENT 'satu akun hanya milik satu travel',
  KEY `idx_tpu_partner` (`travel_partner_id`,`status`),
  CONSTRAINT `fk_tpu_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tpu_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission staff yang diberikan oleh Admin Travel (di dalam batas permission role staff)
CREATE TABLE `partner_staff_permissions` (
  `travel_partner_user_id` BIGINT UNSIGNED NOT NULL,
  `permission_id`          INT UNSIGNED NOT NULL,
  `granted_by`             BIGINT UNSIGNED NULL,
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`travel_partner_user_id`,`permission_id`),
  CONSTRAINT `fk_psp_tpu` FOREIGN KEY (`travel_partner_user_id`) REFERENCES `travel_partner_users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psp_granter` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `travel_partner_documents` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `doc_type`          ENUM('nib','siup','npwp','akta','ppiu','pihk','iata','other') NOT NULL,
  `doc_number`        VARCHAR(100) NULL,
  `file_path`         VARCHAR(255) NOT NULL COMMENT 'disimpan di uploads/private',
  `original_name`     VARCHAR(190) NULL,
  `mime_type`         VARCHAR(100) NULL,
  `file_size`         INT UNSIGNED NULL,
  `expires_on`        DATE NULL,
  `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`       BIGINT UNSIGNED NULL,
  `reviewed_at`       DATETIME NULL,
  `review_note`       VARCHAR(255) NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tpd_partner` (`travel_partner_id`,`doc_type`),
  CONSTRAINT `fk_tpd_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpd_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `partner_bank_accounts` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `bank_code`         VARCHAR(20) NOT NULL,
  `bank_name`         VARCHAR(100) NOT NULL,
  `account_number`    VARCHAR(40) NOT NULL,
  `account_holder`    VARCHAR(150) NOT NULL,
  `purpose`           ENUM('payout','manual_transfer','both') NOT NULL DEFAULT 'both' COMMENT 'payout=pencairan, manual_transfer=rekening tujuan transfer customer',
  `is_primary`        TINYINT(1) NOT NULL DEFAULT 0,
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pba_partner` (`travel_partner_id`,`status`),
  CONSTRAINT `fk_pba_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. DESTINASI
-- ---------------------------------------------------------------------
CREATE TABLE `destination_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `icon`        VARCHAR(100) NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_destcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `destinations` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`         INT UNSIGNED NULL,
  `created_by_partner_id` BIGINT UNSIGNED NULL COMMENT 'NULL = dibuat platform',
  `scope`               ENUM('local','international','hajj_umrah') NOT NULL DEFAULT 'local',
  `name`                VARCHAR(150) NOT NULL,
  `slug`                VARCHAR(170) NOT NULL,
  `description`         TEXT NULL,
  `country`             VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
  `province`            VARCHAR(100) NULL,
  `city`                VARCHAR(100) NULL,
  `address`             TEXT NULL,
  `latitude`            DECIMAL(10,7) NULL,
  `longitude`           DECIMAL(10,7) NULL,
  `google_maps_url`     VARCHAR(255) NULL,
  `facilities`          JSON NULL,
  `additional_info`     TEXT NULL,
  `cover_path`          VARCHAR(255) NULL,
  `view_count`          INT UNSIGNED NOT NULL DEFAULT 0,
  `rating_avg`          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `rating_count`        INT UNSIGNED NOT NULL DEFAULT 0,
  `is_popular`          TINYINT(1) NOT NULL DEFAULT 0,
  `status`              ENUM('draft','pending','published','archived') NOT NULL DEFAULT 'draft',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dest_slug` (`slug`),
  KEY `idx_dest_filter` (`status`,`scope`,`country`,`province`,`city`),
  KEY `idx_dest_popular` (`is_popular`,`view_count`),
  FULLTEXT KEY `ft_dest_search` (`name`,`city`,`province`,`country`),
  CONSTRAINT `fk_dest_cat` FOREIGN KEY (`category_id`) REFERENCES `destination_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dest_partner` FOREIGN KEY (`created_by_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `destination_media` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `destination_id`  BIGINT UNSIGNED NOT NULL,
  `media_type`      ENUM('image','video') NOT NULL DEFAULT 'image',
  `file_path`       VARCHAR(255) NOT NULL,
  `caption`         VARCHAR(190) NULL,
  `sort_order`      INT NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dm_dest` (`destination_id`,`sort_order`),
  CONSTRAINT `fk_dm_dest` FOREIGN KEY (`destination_id`) REFERENCES `destinations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. PAKET PERJALANAN
-- ---------------------------------------------------------------------
CREATE TABLE `package_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`        ENUM('local','international','hajj','umrah') NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `icon`        VARCHAR(100) NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkgcat_slug` (`slug`),
  KEY `idx_pkgcat_type` (`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `travel_packages` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `travel_partner_id`   BIGINT UNSIGNED NOT NULL,
  `category_id`         INT UNSIGNED NOT NULL,
  `code`                VARCHAR(40) NOT NULL,
  `name`                VARCHAR(190) NOT NULL,
  `slug`                VARCHAR(210) NOT NULL,
  `summary`             VARCHAR(300) NULL,
  `description`         MEDIUMTEXT NULL,
  `duration_days`       SMALLINT UNSIGNED NOT NULL,
  `duration_nights`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `start_city`          VARCHAR(100) NULL COMMENT 'Kota keberangkatan / titik kumpul',
  `min_participants`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `max_participants_per_booking` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `transportation`      TEXT NULL,
  `accommodation`       TEXT NULL,
  `terms_conditions`    MEDIUMTEXT NULL,
  `cancellation_policy` TEXT NULL,
  `required_documents`  JSON NULL COMMENT 'daftar dokumen wajib peserta, mis. ["ktp","paspor"]',
  `dp_percent`          DECIMAL(5,2) NOT NULL DEFAULT 30.00 COMMENT 'DP minimum (%) dari total',
  `full_payment_days_before` SMALLINT UNSIGNED NOT NULL DEFAULT 14 COMMENT 'Pelunasan paling lambat H-n keberangkatan',
  `payment_expiry_hours` SMALLINT UNSIGNED NULL COMMENT 'NULL = pakai setting global',
  `latitude`            DECIMAL(10,7) NULL,
  `longitude`           DECIMAL(10,7) NULL,
  `cover_path`          VARCHAR(255) NULL,
  `whatsapp_number`     VARCHAR(20) NULL,
  `base_price`          DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'cache harga terendah untuk listing; sumber harga tetap schedule_prices',
  `view_count`          INT UNSIGNED NOT NULL DEFAULT 0,
  `booking_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  `rating_avg`          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `rating_count`        INT UNSIGNED NOT NULL DEFAULT 0,
  `approval_status`     ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
  `approval_note`       VARCHAR(255) NULL,
  `approved_by`         BIGINT UNSIGNED NULL,
  `approved_at`         DATETIME NULL,
  `status`              ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  `created_by`          BIGINT UNSIGNED NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkg_code` (`code`),
  UNIQUE KEY `uq_pkg_slug` (`slug`),
  KEY `idx_pkg_partner` (`travel_partner_id`,`status`,`approval_status`),
  KEY `idx_pkg_listing` (`approval_status`,`status`,`category_id`,`base_price`),
  KEY `idx_pkg_popular` (`booking_count`,`rating_avg`),
  FULLTEXT KEY `ft_pkg_search` (`name`,`summary`),
  CONSTRAINT `fk_pkg_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pkg_cat` FOREIGN KEY (`category_id`) REFERENCES `package_categories`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pkg_approver` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pkg_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_pkg_dp` CHECK (`dp_percent` > 0 AND `dp_percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_destinations` (
  `package_id`     BIGINT UNSIGNED NOT NULL,
  `destination_id` BIGINT UNSIGNED NOT NULL,
  `sort_order`     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`package_id`,`destination_id`),
  KEY `idx_pd_dest` (`destination_id`),
  CONSTRAINT `fk_pd_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_dest` FOREIGN KEY (`destination_id`) REFERENCES `destinations`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_media` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`  BIGINT UNSIGNED NOT NULL,
  `media_type`  ENUM('image','video') NOT NULL DEFAULT 'image',
  `file_path`   VARCHAR(255) NOT NULL,
  `caption`     VARCHAR(190) NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pm_pkg` (`package_id`,`sort_order`),
  CONSTRAINT `fk_pm_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_itineraries` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`  BIGINT UNSIGNED NOT NULL,
  `day_number`  SMALLINT UNSIGNED NOT NULL,
  `title`       VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `meals`       VARCHAR(60) NULL COMMENT 'mis. B,L,D',
  `accommodation` VARCHAR(190) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_itin_day` (`package_id`,`day_number`),
  CONSTRAINT `fk_itin_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_facilities` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`  BIGINT UNSIGNED NOT NULL,
  `facility_type` ENUM('included','excluded') NOT NULL DEFAULT 'included',
  `name`        VARCHAR(150) NOT NULL,
  `icon`        VARCHAR(60) NULL COMMENT 'transport, hotel, meal, guide, dll.',
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pf_pkg` (`package_id`,`facility_type`),
  CONSTRAINT `fk_pf_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_hajj_umrah_details` (
  `package_id`        BIGINT UNSIGNED NOT NULL,
  `program_type`      ENUM('umrah_reguler','umrah_plus','umrah_vip','haji_khusus','haji_furoda','lainnya') NOT NULL,
  `ppiu_pihk_number`  VARCHAR(100) NULL,
  `ppiu_pihk_name`    VARCHAR(150) NULL,
  `airline`           VARCHAR(100) NULL,
  `departure_airport` VARCHAR(100) NULL,
  `hotel_makkah`      VARCHAR(150) NULL,
  `hotel_makkah_star` TINYINT UNSIGNED NULL,
  `hotel_makkah_distance_m` INT UNSIGNED NULL,
  `hotel_madinah`     VARCHAR(150) NULL,
  `hotel_madinah_star` TINYINT UNSIGNED NULL,
  `hotel_madinah_distance_m` INT UNSIGNED NULL,
  `mutawwif_name`     VARCHAR(150) NULL,
  `manasik_info`      TEXT NULL,
  `visa_info`         TEXT NULL,
  `room_types`        JSON NULL COMMENT 'mis. ["quad","triple","double"]',
  `extra_facilities`  TEXT NULL,
  PRIMARY KEY (`package_id`),
  CONSTRAINT `fk_hu_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_schedules` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`        BIGINT UNSIGNED NOT NULL,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL COMMENT 'denormalisasi untuk isolasi data & index',
  `departure_date`    DATE NOT NULL,
  `return_date`       DATE NOT NULL,
  `departure_note`    VARCHAR(190) NULL,
  `booking_open_at`   DATETIME NULL,
  `booking_close_at`  DATETIME NULL,
  `status`            ENUM('draft','open','closed','departed','completed','cancelled') NOT NULL DEFAULT 'draft',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sched_pkg_date` (`package_id`,`departure_date`,`status`),
  KEY `idx_sched_partner` (`travel_partner_id`,`departure_date`),
  KEY `idx_sched_upcoming` (`status`,`departure_date`),
  CONSTRAINT `fk_sched_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sched_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_sched_dates` CHECK (`return_date` >= `departure_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schedule_prices` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id`   BIGINT UNSIGNED NOT NULL,
  `price_code`    VARCHAR(30) NOT NULL COMMENT 'adult, child, infant, quad, triple, double, single',
  `label`         VARCHAR(100) NOT NULL,
  `price`         DECIMAL(15,2) NOT NULL,
  `original_price` DECIMAL(15,2) NULL COMMENT 'harga coret (opsional, tampilan)',
  `is_default`    TINYINT(1) NOT NULL DEFAULT 0,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_code` (`schedule_id`,`price_code`),
  CONSTRAINT `fk_price_sched` FOREIGN KEY (`schedule_id`) REFERENCES `package_schedules`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_price_pos` CHECK (`price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_quotas` (
  `schedule_id`   BIGINT UNSIGNED NOT NULL,
  `quota_total`   INT UNSIGNED NOT NULL,
  `quota_held`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ditahan oleh booking pending (belum bayar)',
  `quota_booked`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'terkonfirmasi (DP/lunas)',
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  CONSTRAINT `fk_quota_sched` FOREIGN KEY (`schedule_id`) REFERENCES `package_schedules`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_quota_limit` CHECK (`quota_held` + `quota_booked` <= `quota_total`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. PROMO & KOMISI
-- ---------------------------------------------------------------------
CREATE TABLE `promotions` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by_partner_id` BIGINT UNSIGNED NULL COMMENT 'NULL = promo platform',
  `code`              VARCHAR(40) NULL COMMENT 'kode voucher; NULL = promo otomatis',
  `title`             VARCHAR(150) NOT NULL,
  `description`       TEXT NULL,
  `banner_path`       VARCHAR(255) NULL,
  `discount_type`     ENUM('percent','fixed') NOT NULL,
  `discount_value`    DECIMAL(15,2) NOT NULL,
  `max_discount`      DECIMAL(15,2) NULL,
  `min_purchase`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `quota`             INT UNSIGNED NULL COMMENT 'NULL = tak terbatas',
  `used_count`        INT UNSIGNED NOT NULL DEFAULT 0,
  `per_user_limit`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `funded_by`         ENUM('platform','partner') NOT NULL DEFAULT 'partner' COMMENT 'siapa menanggung diskon',
  `applies_to_all`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = semua paket milik pembuat',
  `starts_at`         DATETIME NOT NULL,
  `ends_at`           DATETIME NOT NULL,
  `status`            ENUM('draft','active','inactive','expired') NOT NULL DEFAULT 'draft',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promo_code` (`code`),
  KEY `idx_promo_active` (`status`,`starts_at`,`ends_at`),
  CONSTRAINT `fk_promo_partner` FOREIGN KEY (`created_by_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_promo_dates` CHECK (`ends_at` > `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `promotion_packages` (
  `promotion_id` BIGINT UNSIGNED NOT NULL,
  `package_id`   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`promotion_id`,`package_id`),
  KEY `idx_pp_pkg` (`package_id`),
  CONSTRAINT `fk_pp_promo` FOREIGN KEY (`promotion_id`) REFERENCES `promotions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `commission_rules` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope`             ENUM('global','partner','package') NOT NULL,
  `travel_partner_id` BIGINT UNSIGNED NULL,
  `package_id`        BIGINT UNSIGNED NULL,
  `model`             ENUM('percentage','fixed','percentage_fixed') NOT NULL,
  `percentage`        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `fixed_amount`      DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'nominal per booking',
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `effective_from`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `effective_until`   DATETIME NULL,
  `note`              VARCHAR(255) NULL,
  `created_by`        BIGINT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_lookup` (`scope`,`is_active`,`travel_partner_id`,`package_id`,`effective_from`),
  CONSTRAINT `fk_cr_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_cr_values` CHECK (`percentage` >= 0 AND `percentage` <= 100 AND `fixed_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. BOOKING
-- ---------------------------------------------------------------------
CREATE TABLE `bookings` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_code`        VARCHAR(30) NOT NULL COMMENT 'mis. MT-20261103-A1B2C3',
  `user_id`             BIGINT UNSIGNED NOT NULL,
  `travel_partner_id`   BIGINT UNSIGNED NOT NULL,
  `package_id`          BIGINT UNSIGNED NOT NULL,
  `schedule_id`         BIGINT UNSIGNED NOT NULL,
  `status`              ENUM('pending','partially_paid','paid','completed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
  `payment_option`      ENUM('full','dp') NOT NULL DEFAULT 'full',
  `participant_count`   SMALLINT UNSIGNED NOT NULL,
  `currency`            CHAR(3) NOT NULL DEFAULT 'IDR',
  -- snapshot paket & jadwal
  `package_name_snapshot` VARCHAR(190) NOT NULL,
  `partner_name_snapshot` VARCHAR(150) NOT NULL,
  `departure_date`      DATE NOT NULL,
  `return_date`         DATE NOT NULL,
  -- snapshot harga
  `subtotal`            DECIMAL(15,2) NOT NULL,
  `promotion_id`        BIGINT UNSIGNED NULL,
  `promo_code_snapshot` VARCHAR(40) NULL,
  `discount_amount`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_funded_by`  ENUM('platform','partner') NULL,
  `admin_fee`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount`        DECIMAL(15,2) NOT NULL,
  -- skema DP & pelunasan (snapshot)
  `dp_percent_snapshot` DECIMAL(5,2) NULL,
  `dp_amount`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount`    DECIMAL(15,2) NOT NULL,
  `payment_expires_at`  DATETIME NOT NULL COMMENT 'batas bayar pembayaran pertama; lewat = expired & kuota dibebaskan',
  `full_payment_due_date` DATE NULL COMMENT 'batas pelunasan jika memilih DP',
  -- kontak & catatan
  `contact_name`        VARCHAR(150) NOT NULL,
  `contact_email`       VARCHAR(150) NOT NULL,
  `contact_phone`       VARCHAR(20) NOT NULL,
  `customer_note`       TEXT NULL,
  `internal_note`       TEXT NULL,
  `confirmed_by`        BIGINT UNSIGNED NULL,
  `confirmed_at`        DATETIME NULL,
  `cancelled_at`        DATETIME NULL,
  `cancel_reason`       VARCHAR(255) NULL,
  `cancelled_by`        BIGINT UNSIGNED NULL,
  `completed_at`        DATETIME NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_code` (`booking_code`),
  KEY `idx_bk_user` (`user_id`,`status`,`created_at`),
  KEY `idx_bk_partner` (`travel_partner_id`,`status`,`created_at`),
  KEY `idx_bk_schedule` (`schedule_id`,`status`),
  KEY `idx_bk_package` (`package_id`),
  KEY `idx_bk_expiry` (`status`,`payment_expires_at`),
  KEY `idx_bk_fullpay` (`status`,`full_payment_due_date`),
  CONSTRAINT `fk_bk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bk_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bk_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bk_sched` FOREIGN KEY (`schedule_id`) REFERENCES `package_schedules`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bk_promo` FOREIGN KEY (`promotion_id`) REFERENCES `promotions`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bk_confirmer` FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bk_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_bk_amounts` CHECK (`total_amount` >= 0 AND `paid_amount` >= 0 AND `remaining_amount` >= 0 AND `participant_count` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_participants` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`      BIGINT UNSIGNED NOT NULL,
  `full_name`       VARCHAR(150) NOT NULL COMMENT 'sesuai identitas/paspor',
  `gender`          ENUM('male','female') NULL,
  `birth_date`      DATE NULL,
  `birth_place`     VARCHAR(100) NULL,
  `nationality`     VARCHAR(60) NULL DEFAULT 'Indonesia',
  `id_number`       VARCHAR(40) NULL COMMENT 'NIK',
  `passport_number` VARCHAR(40) NULL,
  `passport_expiry` DATE NULL,
  `phone`           VARCHAR(20) NULL,
  `email`           VARCHAR(150) NULL,
  `price_code`      VARCHAR(30) NOT NULL,
  `price_label_snapshot` VARCHAR(100) NOT NULL,
  `unit_price`      DECIMAL(15,2) NOT NULL COMMENT 'snapshot harga per peserta',
  `room_type`       VARCHAR(30) NULL,
  `mahram_name`     VARCHAR(150) NULL,
  `special_request` VARCHAR(255) NULL,
  `is_main_contact` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bp_booking` (`booking_id`),
  CONSTRAINT `fk_bp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_documents` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`      BIGINT UNSIGNED NOT NULL,
  `participant_id`  BIGINT UNSIGNED NULL,
  `uploaded_by`     BIGINT UNSIGNED NULL,
  `direction`       ENUM('customer_upload','partner_upload') NOT NULL DEFAULT 'customer_upload' COMMENT 'partner_upload = e-ticket, visa, voucher, dll.',
  `doc_type`        ENUM('ktp','passport','kk','visa','vaccine','photo','mahram_letter','eticket','voucher','itinerary','other') NOT NULL,
  `file_path`       VARCHAR(255) NOT NULL COMMENT 'uploads/private, diakses lewat controller + cek kepemilikan',
  `original_name`   VARCHAR(190) NULL,
  `mime_type`       VARCHAR(100) NULL,
  `file_size`       INT UNSIGNED NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `review_note`     VARCHAR(255) NULL,
  `reviewed_by`     BIGINT UNSIGNED NULL,
  `reviewed_at`     DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bd_booking` (`booking_id`,`doc_type`),
  KEY `idx_bd_participant` (`participant_id`),
  CONSTRAINT `fk_bd_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bd_participant` FOREIGN KEY (`participant_id`) REFERENCES `booking_participants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bd_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bd_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. PEMBAYARAN
-- ---------------------------------------------------------------------
CREATE TABLE `payment_methods` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(40) NOT NULL COMMENT 'mis. VA_BCA, QRIS, EWALLET_OVO, MANUAL_TRANSFER',
  `name`          VARCHAR(100) NOT NULL,
  `group_type`    ENUM('virtual_account','qris','ewallet','bank_transfer') NOT NULL,
  `provider`      ENUM('xendit','manual') NOT NULL DEFAULT 'xendit',
  `provider_code` VARCHAR(30) NULL COMMENT 'kode bank/channel di Xendit, mis. BCA, OVO',
  `fee_fixed`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `fee_percent`   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `icon`          VARCHAR(100) NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_code`    VARCHAR(40) NOT NULL COMMENT 'dipakai juga sebagai external_id Xendit',
  `booking_id`      BIGINT UNSIGNED NOT NULL,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `payment_method_id` INT UNSIGNED NOT NULL,
  `kind`            ENUM('full','dp','settlement') NOT NULL COMMENT 'full=lunas langsung, dp=uang muka, settlement=pelunasan setelah DP',
  `amount`          DECIMAL(15,2) NOT NULL COMMENT 'nilai yang masuk ke booking',
  `fee_amount`      DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'biaya channel yang dibebankan ke customer',
  `total_charge`    DECIMAL(15,2) NOT NULL COMMENT 'amount + fee_amount (yang ditagihkan)',
  `status`          ENUM('pending','waiting_verification','paid','failed','expired','rejected','refunded') NOT NULL DEFAULT 'pending',
  `provider`        ENUM('xendit','manual') NOT NULL,
  `provider_ref_id` VARCHAR(100) NULL COMMENT 'ID transaksi di Xendit',
  `payment_url`     VARCHAR(500) NULL,
  `va_number`       VARCHAR(40) NULL,
  `qr_string`       TEXT NULL,
  `manual_bank_account_id` BIGINT UNSIGNED NULL COMMENT 'rekening tujuan transfer manual',
  `expires_at`      DATETIME NULL,
  `paid_at`         DATETIME NULL,
  `verified_by`     BIGINT UNSIGNED NULL,
  `verified_at`     DATETIME NULL,
  `reject_reason`   VARCHAR(255) NULL,
  `raw_response`    JSON NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_code` (`payment_code`),
  UNIQUE KEY `uq_payment_provider_ref` (`provider`,`provider_ref_id`),
  KEY `idx_pay_booking` (`booking_id`,`status`),
  KEY `idx_pay_partner` (`travel_partner_id`,`status`,`created_at`),
  KEY `idx_pay_user` (`user_id`,`created_at`),
  KEY `idx_pay_expiry` (`status`,`expires_at`),
  CONSTRAINT `fk_pay_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_bank` FOREIGN KEY (`manual_bank_account_id`) REFERENCES `partner_bank_accounts`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_pay_amount` CHECK (`amount` > 0 AND `total_charge` >= `amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_status_history` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id`  BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(30) NULL,
  `to_status`   VARCHAR(30) NOT NULL,
  `source`      ENUM('system','webhook','customer','partner','admin','cron') NOT NULL,
  `actor_id`    BIGINT UNSIGNED NULL,
  `note`        VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_psh_payment` (`payment_id`,`created_at`),
  CONSTRAINT `fk_psh_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_proofs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id`    BIGINT UNSIGNED NOT NULL,
  `file_path`     VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(190) NULL,
  `mime_type`     VARCHAR(100) NULL,
  `file_size`     INT UNSIGNED NULL,
  `sender_bank`   VARCHAR(100) NULL,
  `sender_name`   VARCHAR(150) NULL,
  `transfer_date` DATE NULL,
  `transfer_amount` DECIMAL(15,2) NULL,
  `note`          VARCHAR(255) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proof_payment` (`payment_id`),
  CONSTRAINT `fk_proof_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhook_logs` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`        VARCHAR(30) NOT NULL DEFAULT 'xendit',
  `event_key`       VARCHAR(150) NOT NULL COMMENT 'ID event/transaksi dari provider -> idempotensi',
  `event_type`      VARCHAR(80) NULL,
  `signature_valid` TINYINT(1) NOT NULL DEFAULT 0,
  `payload`         JSON NULL,
  `process_status`  ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  `error_message`   VARCHAR(255) NULL,
  `ip_address`      VARCHAR(45) NULL,
  `received_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_event` (`provider`,`event_key`,`event_type`),
  KEY `idx_webhook_status` (`process_status`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refunds` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `refund_code`     VARCHAR(40) NOT NULL,
  `booking_id`      BIGINT UNSIGNED NOT NULL,
  `payment_id`      BIGINT UNSIGNED NULL,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `requested_by`    BIGINT UNSIGNED NULL,
  `reason`          VARCHAR(255) NOT NULL,
  `paid_amount_snapshot` DECIMAL(15,2) NOT NULL,
  `cancellation_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `refund_amount`   DECIMAL(15,2) NOT NULL,
  `refund_to_bank`  VARCHAR(100) NULL,
  `refund_to_account` VARCHAR(40) NULL,
  `refund_to_name`  VARCHAR(150) NULL,
  `status`          ENUM('requested','approved','rejected','processing','completed','failed') NOT NULL DEFAULT 'requested',
  `reviewed_by`     BIGINT UNSIGNED NULL,
  `reviewed_at`     DATETIME NULL,
  `review_note`     VARCHAR(255) NULL,
  `processed_at`    DATETIME NULL,
  `transfer_proof_path` VARCHAR(255) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refund_code` (`refund_code`),
  KEY `idx_refund_booking` (`booking_id`),
  KEY `idx_refund_partner` (`travel_partner_id`,`status`),
  CONSTRAINT `fk_refund_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_refund_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refund_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_refund_requester` FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refund_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_refund_amount` CHECK (`refund_amount` >= 0 AND `cancellation_fee` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. KOMISI & PENCAIRAN
-- ---------------------------------------------------------------------
CREATE TABLE `commissions` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`        BIGINT UNSIGNED NOT NULL,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `commission_rule_id` BIGINT UNSIGNED NULL COMMENT 'rujukan saja; nilai sebenarnya ada di snapshot di bawah',
  `rule_scope_snapshot` ENUM('global','partner','package') NOT NULL,
  `model_snapshot`    ENUM('percentage','fixed','percentage_fixed') NOT NULL,
  `percentage_snapshot` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `fixed_amount_snapshot` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `base_amount`       DECIMAL(15,2) NOT NULL COMMENT 'dasar perhitungan (harga setelah diskon yang ditanggung partner)',
  `commission_amount` DECIMAL(15,2) NOT NULL COMMENT 'pendapatan platform',
  `partner_amount`    DECIMAL(15,2) NOT NULL COMMENT 'pendapatan travel sebelum potongan lain',
  `status`            ENUM('pending','earned','available','paid_out','reversed') NOT NULL DEFAULT 'pending',
  `earned_at`         DATETIME NULL COMMENT 'saat booking lunas',
  `available_at`      DATETIME NULL COMMENT 'saat dana dapat dicairkan (setelah hold period)',
  `reversed_at`       DATETIME NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comm_booking` (`booking_id`),
  KEY `idx_comm_partner` (`travel_partner_id`,`status`),
  CONSTRAINT `fk_comm_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_comm_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_comm_rule` FOREIGN KEY (`commission_rule_id`) REFERENCES `commission_rules`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_comm_amount` CHECK (`commission_amount` >= 0 AND `partner_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `partner_payouts` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payout_code`       VARCHAR(40) NOT NULL,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `bank_account_id`   BIGINT UNSIGNED NULL,
  `bank_name_snapshot` VARCHAR(100) NOT NULL,
  `account_number_snapshot` VARCHAR(40) NOT NULL,
  `account_holder_snapshot` VARCHAR(150) NOT NULL,
  `gross_amount`      DECIMAL(15,2) NOT NULL,
  `fee_amount`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `net_amount`        DECIMAL(15,2) NOT NULL,
  `status`            ENUM('requested','approved','rejected','processing','paid','failed') NOT NULL DEFAULT 'requested',
  `requested_by`      BIGINT UNSIGNED NULL,
  `reviewed_by`       BIGINT UNSIGNED NULL,
  `reviewed_at`       DATETIME NULL,
  `review_note`       VARCHAR(255) NULL,
  `paid_at`           DATETIME NULL,
  `transfer_proof_path` VARCHAR(255) NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_code` (`payout_code`),
  KEY `idx_payout_partner` (`travel_partner_id`,`status`,`created_at`),
  CONSTRAINT `fk_payout_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payout_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `partner_bank_accounts`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payout_requester` FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payout_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_payout_amount` CHECK (`gross_amount` > 0 AND `net_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payout_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payout_id`     BIGINT UNSIGNED NOT NULL,
  `commission_id` BIGINT UNSIGNED NOT NULL,
  `amount`        DECIMAL(15,2) NOT NULL COMMENT 'partner_amount yang dicairkan',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_commission` (`commission_id`) COMMENT 'satu komisi hanya bisa dicairkan sekali',
  KEY `idx_pi_payout` (`payout_id`),
  CONSTRAINT `fk_pi_payout` FOREIGN KEY (`payout_id`) REFERENCES `partner_payouts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_comm` FOREIGN KEY (`commission_id`) REFERENCES `commissions`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. REVIEW & WISHLIST
-- ---------------------------------------------------------------------
CREATE TABLE `reviews` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`    BIGINT UNSIGNED NOT NULL,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `package_id`    BIGINT UNSIGNED NOT NULL,
  `travel_partner_id` BIGINT UNSIGNED NOT NULL,
  `rating`        TINYINT UNSIGNED NOT NULL,
  `title`         VARCHAR(150) NULL,
  `comment`       TEXT NULL,
  `partner_reply` TEXT NULL,
  `replied_at`    DATETIME NULL,
  `status`        ENUM('pending','published','hidden') NOT NULL DEFAULT 'published',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_booking` (`booking_id`) COMMENT 'satu review per booking selesai',
  KEY `idx_review_pkg` (`package_id`,`status`,`created_at`),
  KEY `idx_review_partner` (`travel_partner_id`),
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_partner` FOREIGN KEY (`travel_partner_id`) REFERENCES `travel_partners`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_review_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wishlists` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `item_type`   ENUM('package','destination') NOT NULL DEFAULT 'package',
  `package_id`  BIGINT UNSIGNED NULL,
  `destination_id` BIGINT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wish_pkg` (`user_id`,`package_id`),
  UNIQUE KEY `uq_wish_dest` (`user_id`,`destination_id`),
  CONSTRAINT `fk_wish_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wish_pkg` FOREIGN KEY (`package_id`) REFERENCES `travel_packages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wish_dest` FOREIGN KEY (`destination_id`) REFERENCES `destinations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. KONTEN, NOTIFIKASI, SISTEM
-- ---------------------------------------------------------------------
CREATE TABLE `banners` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(150) NOT NULL,
  `subtitle`    VARCHAR(190) NULL,
  `image_path`  VARCHAR(255) NOT NULL,
  `action_type` ENUM('none','package','destination','promotion','article','url') NOT NULL DEFAULT 'none',
  `action_value` VARCHAR(255) NULL,
  `cta_label`   VARCHAR(50) NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `starts_at`   DATETIME NULL,
  `ends_at`     DATETIME NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banner_active` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `articles` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_id`     BIGINT UNSIGNED NULL,
  `title`         VARCHAR(190) NOT NULL,
  `slug`          VARCHAR(210) NOT NULL,
  `excerpt`       VARCHAR(300) NULL,
  `content`       MEDIUMTEXT NOT NULL,
  `cover_path`    VARCHAR(255) NULL,
  `tags`          VARCHAR(255) NULL,
  `view_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `status`        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at`  DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_slug` (`slug`),
  KEY `idx_article_pub` (`status`,`published_at`),
  CONSTRAINT `fk_article_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faqs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`    VARCHAR(80) NOT NULL DEFAULT 'umum',
  `question`    VARCHAR(255) NOT NULL,
  `answer`      TEXT NOT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_faq_active` (`status`,`category`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `type`        VARCHAR(40) NOT NULL COMMENT 'booking, payment, promo, system, ...',
  `title`       VARCHAR(150) NOT NULL,
  `body`        VARCHAR(500) NOT NULL,
  `data`        JSON NULL COMMENT 'payload deep link, mis. {"booking_id":1}',
  `read_at`     DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_tokens` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `fcm_token`   VARCHAR(255) NOT NULL,
  `platform`    ENUM('android','ios','web') NOT NULL DEFAULT 'android',
  `last_seen_at` DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fcm_token` (`fcm_token`),
  KEY `idx_dt_user` (`user_id`),
  CONSTRAINT `fk_dt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `setting_key`  VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `value_type`   ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
  `is_public`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = boleh dikirim ke aplikasi Android',
  `description`  VARCHAR(255) NULL,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL,
  `travel_partner_id` BIGINT UNSIGNED NULL,
  `action`      VARCHAR(80) NOT NULL COMMENT 'mis. booking.confirm, package.approve, user.login',
  `entity_type` VARCHAR(60) NULL,
  `entity_id`   BIGINT UNSIGNED NULL,
  `old_values`  JSON NULL,
  `new_values`  JSON NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_partner` (`travel_partner_id`,`created_at`),
  KEY `idx_audit_action` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. VIEW PEMBANTU (laporan & listing)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_schedule_availability` AS
SELECT s.id AS schedule_id, s.package_id, s.travel_partner_id, s.departure_date, s.status,
       q.quota_total, q.quota_held, q.quota_booked,
       CAST(q.quota_total AS SIGNED) - CAST(q.quota_held AS SIGNED) - CAST(q.quota_booked AS SIGNED) AS quota_available
FROM package_schedules s
JOIN package_quotas q ON q.schedule_id = s.id;

SET FOREIGN_KEY_CHECKS = 1;
