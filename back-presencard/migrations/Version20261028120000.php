<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cargo, correo y teléfono pasan a ser opcionales: no siempre se conocen al crear una tarjeta.
 * Solo relaja las columnas (NOT NULL -> NULL): no modifica ni borra ningún dato existente.
 */
final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hace opcionales job_title, email y phone en presencards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presencards MODIFY job_title VARCHAR(150) DEFAULT NULL, MODIFY email VARCHAR(180) DEFAULT NULL, MODIFY phone VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Las tarjetas sin esos datos quedan con texto vacío para poder volver a exigirlos.
        $this->addSql('UPDATE presencards SET job_title = \'\' WHERE job_title IS NULL');
        $this->addSql('UPDATE presencards SET email = \'\' WHERE email IS NULL');
        $this->addSql('UPDATE presencards SET phone = \'\' WHERE phone IS NULL');
        $this->addSql('ALTER TABLE presencards MODIFY job_title VARCHAR(150) NOT NULL, MODIFY email VARCHAR(180) NOT NULL, MODIFY phone VARCHAR(30) NOT NULL');
    }
}
