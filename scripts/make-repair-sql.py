#!/usr/bin/env python3
"""Genera reparar-actualizar.sql a partir de base-de-datos.sql: versión idempotente que sirve cuando la base ya tiene tablas."""
import re
import sys

src, dst = sys.argv[1], sys.argv[2]
s = open(src).read()
creates = re.findall(r"CREATE TABLE `.*?;\n(?=CREATE|\n--)", s, flags=re.S)
assert len(creates) == 3, len(creates)
creates = [c.replace("CREATE TABLE `", "CREATE TABLE IF NOT EXISTS `", 1) for c in creates]
versions = re.search(r"INSERT INTO `doctrine_migration_versions` VALUES (.*?);\n", s, flags=re.S).group(1)
admin_hash = re.search(r"VALUES \('admin@pafar\.net', 'Administrador', '([^']+)'", s).group(1)


def add_column(name, ddl):
    # information_schema en vez de "ADD COLUMN IF NOT EXISTS": funciona igual en MariaDB y MySQL.
    return (
        "SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `presencards` ADD COLUMN " + ddl + "', 'DO 0') "
        "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presencards' "
        "AND COLUMN_NAME = '" + name + "');\nPREPARE stmt FROM @sql;\nEXECUTE stmt;\nDEALLOCATE PREPARE stmt;\n"
    )


out = """-- Presencard · script de REPARACIÓN / ACTUALIZACIÓN. Se puede ejecutar varias veces sin riesgo.
-- Sirve cuando la base ya tiene tablas (por una importación anterior, completa o a medias):
--   * crea solo las tablas que falten,
--   * agrega las columnas nuevas (Telegram y colores) solo si no existen,
--   * registra las migraciones aplicadas sin duplicarlas,
--   * crea el administrador inicial solo si aún no existe ese correo.
-- No borra ni modifica tarjetas ni usuarios existentes. Compatible con MariaDB y MySQL.
SET NAMES utf8mb4;

""" + "\n".join(creates) + "\n" + "\n".join([
    add_column("telegram", "`telegram` VARCHAR(32) DEFAULT NULL AFTER `instagram`"),
    add_column("color_primary", "`color_primary` VARCHAR(7) DEFAULT NULL AFTER `telegram`"),
    add_column("color_accent", "`color_accent` VARCHAR(7) DEFAULT NULL AFTER `color_primary`"),
]) + """
INSERT IGNORE INTO `doctrine_migration_versions` VALUES """ + versions + """;

INSERT INTO `users` (`email`, `full_name`, `password_hash`, `role`, `active`, `created_at`, `updated_at`)
SELECT 'admin@pafar.net', 'Administrador', '""" + admin_hash + """', 'ROLE_ADMIN', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@pafar.net');
"""
open(dst, "w").write(out)
