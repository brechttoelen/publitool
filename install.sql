-- ========================================================
-- Parcours Editor — installatie-script voor MySQL
-- Plak dit volledig in phpMyAdmin (SQL-tabblad) op je
-- bestaande database. Alle tabellen krijgen prefix 'pe_'.
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Gebruikers (single-user, maar ondersteunt later meer)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_users`;
CREATE TABLE `pe_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standaard gebruiker. ZET HET PASWOORD via api/install_password.php NA INSTALLATIE.
-- De hash hieronder is een placeholder en werkt mogelijk niet voor inloggen.
INSERT INTO `pe_users` (`username`, `password_hash`, `display_name`) VALUES
  ('admin', 'PLACEHOLDER_RUN_INSTALL_PASSWORD_PHP', 'Beheerder');

-- --------------------------------------------------------
-- Login-tokens (30 dagen "remember me")
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_login_tokens`;
CREATE TABLE `pe_login_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `pe_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seizoenen (cyclocross-stijl: '2025-2026')
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_seasons`;
CREATE TABLE `pe_seasons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(20) NOT NULL UNIQUE,    -- bv. '2025-2026'
  `start_year` SMALLINT UNSIGNED NOT NULL, -- 2025
  `end_year` SMALLINT UNSIGNED NOT NULL,   -- 2026
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pe_seasons` (`label`, `start_year`, `end_year`, `is_current`) VALUES
  ('2025-2026', 2025, 2026, 1),
  ('2026-2027', 2026, 2027, 0);

-- --------------------------------------------------------
-- Partners (zowel koepel-partners als lokale partners)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_partners`;
CREATE TABLE `pe_partners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#888888',
  `logo_path` VARCHAR(255) NULL,         -- relatieve URL naar /uploads/logos/...
  `is_local` TINYINT(1) NOT NULL DEFAULT 0, -- 0 = koepel-partner, 1 = lokale partner
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_local` (`is_local`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Voorbeelden van partners (kan je later in de UI beheren)
INSERT INTO `pe_partners` (`name`, `color`, `is_local`) VALUES
  ('Ethias', '#FF6B00', 0),
  ('Velux', '#C8102E', 0),
  ('Bingoal', '#E10600', 0),
  ('Maes Pils', '#003F7F', 0),
  ('Stihl', '#FF7F00', 0),
  ('Pauwels Sauzen', '#5C2E1A', 0),
  ('Viessmann', '#1C1C1C', 0),
  ('Simac', '#0066CC', 0);

-- --------------------------------------------------------
-- Koepelorganisaties (UCI World Cup, Telenet Superprestige, etc.)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_koepels`;
CREATE TABLE `pe_koepels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL UNIQUE,
  `short_name` VARCHAR(40) NULL,         -- bv. 'UCI WC', 'TSP'
  `color` VARCHAR(20) NOT NULL DEFAULT '#1c1917',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pe_koepels` (`name`, `short_name`, `color`, `sort_order`) VALUES
  ('UCI Cyclo-Cross World Cup', 'UCI WC', '#C8102E', 1),
  ('Telenet Superprestige', 'TSP', '#FF6B00', 2),
  ('X²O Badkamers Trofee', 'X²O', '#0066CC', 3);

-- --------------------------------------------------------
-- Koppeling koepel <-> standaard partners
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_koepel_partners`;
CREATE TABLE `pe_koepel_partners` (
  `koepel_id` INT UNSIGNED NOT NULL,
  `partner_id` INT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`koepel_id`, `partner_id`),
  KEY `partner_id` (`partner_id`),
  CONSTRAINT `fk_kp_koepel` FOREIGN KEY (`koepel_id`) REFERENCES `pe_koepels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kp_partner` FOREIGN KEY (`partner_id`) REFERENCES `pe_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Parcours
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_parcours`;
CREATE TABLE `pe_parcours` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `season_id` INT UNSIGNED NOT NULL,
  `koepel_id` INT UNSIGNED NULL,         -- NULL = standalone parcours zonder koepel
  `location_name` VARCHAR(120) NOT NULL, -- 'Tabor', 'Koksijde', ...
  `race_date` DATE NOT NULL,             -- verplicht
  `bg_image_path` VARCHAR(255) NULL,     -- /uploads/maps/xxx.png (PNG van de KMZ-rendering)
  `bg_width` INT UNSIGNED NULL,
  `bg_height` INT UNSIGNED NULL,
  `data_json` LONGTEXT NULL,             -- alle parcours-data: punten, zones, markers, logo's, layerVisibility, ...
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `season_id` (`season_id`),
  KEY `koepel_id` (`koepel_id`),
  KEY `race_date` (`race_date`),
  CONSTRAINT `fk_par_season` FOREIGN KEY (`season_id`) REFERENCES `pe_seasons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_par_koepel` FOREIGN KEY (`koepel_id`) REFERENCES `pe_koepels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Welke partners horen bij welk parcours (lokale + koepel)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pe_parcours_partners`;
CREATE TABLE `pe_parcours_partners` (
  `parcours_id` INT UNSIGNED NOT NULL,
  `partner_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`parcours_id`, `partner_id`),
  KEY `partner_id` (`partner_id`),
  CONSTRAINT `fk_pp_parcours` FOREIGN KEY (`parcours_id`) REFERENCES `pe_parcours` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_partner` FOREIGN KEY (`partner_id`) REFERENCES `pe_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
