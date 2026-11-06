<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Telegram y paleta de colores (principal y de acento) opcionales por tarjeta.
 * Sin color guardado, la tarjeta usa la paleta por defecto (azul/verde).
 */
final class Version20261102120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade telegram, color_primary y color_accent opcionales a presencards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presencards ADD telegram VARCHAR(32) DEFAULT NULL AFTER instagram, ADD color_primary VARCHAR(7) DEFAULT NULL AFTER telegram, ADD color_accent VARCHAR(7) DEFAULT NULL AFTER color_primary');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presencards DROP telegram, DROP color_primary, DROP color_accent');
    }
}
