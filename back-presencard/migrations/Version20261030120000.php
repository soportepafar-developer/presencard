<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Empresa u organización opcional de la persona de la tarjeta (se exporta como ORG en el contacto .vcf).
 */
final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade la columna opcional company a presencards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presencards ADD company VARCHAR(150) DEFAULT NULL AFTER job_title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presencards DROP company');
    }
}
