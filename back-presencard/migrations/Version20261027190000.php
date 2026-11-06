<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Módulo de usuarios: "admins" pasa a "users" (con nombre, rol y estado) y cada tarjeta
 * queda asociada a un responsable.
 *
 * Escrita a mano: el diff automático borraría "admins" y se perderían las cuentas existentes.
 * Los administradores actuales se conservan como administradores activos y las tarjetas
 * existentes se asignan al primer usuario (el administrador más antiguo).
 */
final class Version20261027190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea el módulo de usuarios (admins -> users con rol y estado) y añade owner_id a presencards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE admins TO users');
        $this->addSql('DROP INDEX uniq_admins_email ON users');

        // Columnas nuevas con valor provisional para las filas existentes; luego se quita el DEFAULT
        // para que el esquema coincida exactamente con el mapeo de Doctrine.
        $this->addSql('ALTER TABLE users ADD full_name VARCHAR(150) NOT NULL DEFAULT \'\' AFTER email, ADD role VARCHAR(20) NOT NULL DEFAULT \'ROLE_ADMIN\' AFTER password_hash, ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER role, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE users SET full_name = SUBSTRING_INDEX(email, \'@\', 1), updated_at = created_at');
        $this->addSql('ALTER TABLE users MODIFY updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ALTER full_name DROP DEFAULT, ALTER role DROP DEFAULT, ALTER active DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');

        $this->addSql('ALTER TABLE presencards ADD owner_id INT DEFAULT NULL AFTER hash_id');
        $this->addSql('UPDATE presencards SET owner_id = COALESCE((SELECT MIN(id) FROM users), 0)');
        $this->addSql('ALTER TABLE presencards MODIFY owner_id INT NOT NULL');
        $this->addSql('CREATE INDEX idx_presencards_owner_id ON presencards (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_presencards_owner_id ON presencards');
        $this->addSql('ALTER TABLE presencards DROP owner_id');

        // Los usuarios que no eran administradores quedarían como administradores en la tabla "admins".
        $this->addSql('DROP INDEX uniq_users_email ON users');
        $this->addSql('ALTER TABLE users DROP full_name, DROP role, DROP active, DROP updated_at');
        $this->addSql('CREATE UNIQUE INDEX uniq_admins_email ON users (email)');
        $this->addSql('RENAME TABLE users TO admins');
    }
}
