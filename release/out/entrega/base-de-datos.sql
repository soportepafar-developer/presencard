-- Presencard · base de datos inicial (estructura final + administrador). Importar en phpMyAdmin.
-- Compatible con MariaDB 10.2+ y MySQL 5.7+. Importar sobre una base de datos VACÍA.
SET NAMES utf8mb4;

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `presencards` (
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
CREATE TABLE `users` (
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

-- Versiones de migración ya aplicadas (para que futuras migraciones no repitan estas).
INSERT INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20261027152712','2026-11-02 23:51:11',53),('DoctrineMigrations\\Version20261027190000','2026-11-02 23:51:11',121),('DoctrineMigrations\\Version20261028120000','2026-11-02 23:51:11',22),('DoctrineMigrations\\Version20261030120000','2026-11-02 23:51:11',21),('DoctrineMigrations\\Version20261102120000','2026-11-02 23:51:11',23);

-- Administrador inicial. La contraseña temporal se entrega aparte; hay que cambiarla al entrar.
INSERT INTO `users` (`email`, `full_name`, `password_hash`, `role`, `active`, `created_at`, `updated_at`) VALUES ('admin@pafar.net', 'Administrador', '$2y$12$fGX4I2ixa5ERqaF9LKoU7eYChHeTcX21haeaZoXfp.ju32FLi6lg2', 'ROLE_ADMIN', 1, NOW(), NOW());
