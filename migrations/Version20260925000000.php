<?php

declare(strict_types=1);

namespace MajesticDevIdCardMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track rank, specialty, callsign, qualifications and awards for the public verification page.';
    }

    public function up(Schema $schema): void
    {
        $card = $schema->getTable('majestic_id_card');
        $card->addColumn('rank', 'string', ['length' => 100, 'notnull' => false]);
        $card->addColumn('specialty', 'string', ['length' => 100, 'notnull' => false]);
        $card->addColumn('callsign', 'string', ['length' => 50, 'notnull' => false]);
        // Existing rows need a real default, not just the PHP-side property initializer -
        // ADD COLUMN ... NOT NULL with no DEFAULT fails outright against a populated table.
        $card->addColumn('qualifications', 'json', ['default' => '[]']);
        $card->addColumn('awards', 'json', ['default' => '[]']);
        $card->addColumn('sync_qualifications', 'boolean', ['default' => true]);
    }

    public function down(Schema $schema): void
    {
        $card = $schema->getTable('majestic_id_card');
        $card->dropColumn('sync_qualifications');
        $card->dropColumn('awards');
        $card->dropColumn('qualifications');
        $card->dropColumn('callsign');
        $card->dropColumn('specialty');
        $card->dropColumn('rank');
    }
}
