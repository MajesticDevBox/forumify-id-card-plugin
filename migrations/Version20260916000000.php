<?php

declare(strict_types=1);

namespace MajesticDevIdCardMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a second optional personnel source alongside MILHQ.';
    }

    public function up(Schema $schema): void
    {
        $card = $schema->getTable('majestic_id_card');
        $card->addColumn('command_net_soldier_id', 'integer', ['notnull' => false]);
        $card->addColumn('auto_sync_command_net', 'boolean');
        $card->addIndex(['source', 'command_net_soldier_id'], 'idx_id_card_source_cn');
    }

    public function down(Schema $schema): void
    {
        $card = $schema->getTable('majestic_id_card');
        $card->dropIndex('idx_id_card_source_cn');
        $card->dropColumn('auto_sync_command_net');
        $card->dropColumn('command_net_soldier_id');
    }
}
