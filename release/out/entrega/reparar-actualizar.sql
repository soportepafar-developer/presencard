-- Presencard · script de REPARACIÓN / ACTUALIZACIÓN. Se puede ejecutar varias veces sin riesgo.
-- Sirve cuando la base ya tiene tablas (por una importación anterior, completa o a medias):
--   * crea solo las tablas que falten,
--   * agrega las columnas nuevas (Telegram y colores) solo si no existen,
--   * registra las migraciones aplicadas sin duplicarlas,
--   * crea el administrador inicial solo si aún no existe ese correo.
-- No borra ni modifica tarjetas ni usuarios existentes. Compatible con MariaDB y MySQL.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `presencards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hash_id` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `owner_id` int NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagram` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_primary` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_accent` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_presencards_hash_id` (`hash_id`),
  KEY `idx_presencards_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `updated_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `presencards` ADD COLUMN `telegram` VARCHAR(32) DEFAULT NULL AFTER `instagram`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presencards' AND COLUMN_NAME = 'telegram');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `presencards` ADD COLUMN `color_primary` VARCHAR(7) DEFAULT NULL AFTER `telegram`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presencards' AND COLUMN_NAME = 'color_primary');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `presencards` ADD COLUMN `color_accent` VARCHAR(7) DEFAULT NULL AFTER `color_primary`', 'DO 0') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presencards' AND COLUMN_NAME = 'color_accent');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20261027152712','2026-11-02 23:51:11',53),('DoctrineMigrations\\Version20261027190000','2026-11-02 23:51:11',121),('DoctrineMigrations\\Version20261028120000','2026-11-02 23:51:11',22),('DoctrineMigrations\\Version20261030120000','2026-11-02 23:51:11',21),('DoctrineMigrations\\Version20261102120000','2026-11-02 23:51:11',23);

INSERT INTO `users` (`email`, `full_name`, `password_hash`, `role`, `active`, `created_at`, `updated_at`)
SELECT 'admin@pafar.net', 'Administrador', '$2y$12$fGX4I2ixa5ERqaF9LKoU7eYChHeTcX21haeaZoXfp.ju32FLi6lg2', 'ROLE_ADMIN', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@pafar.net');
