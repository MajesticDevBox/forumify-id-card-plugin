<?php

declare(strict_types=1);

namespace MajesticDevIdCardMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914000000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create fictional ID cards and optional MILHQ unit mappings.'; }

    public function up(Schema $schema): void
    {
        $card = $schema->createTable('majestic_id_card');
        $card->addColumn('id', 'integer', ['autoincrement' => true]); $card->setPrimaryKey(['id']);
        $card->addColumn('source', 'string', ['length' => 10]);
        $card->addColumn('milhq_soldier_id', 'integer', ['notnull' => false]);
        $card->addColumn('display_name', 'string', ['length' => 100]);
        $card->addColumn('member_id', 'string', ['length' => 6]);
        foreach ([1,2,3] as $i) { $card->addColumn('organization_line'.$i, 'string', ['length' => 100]); }
        $card->addColumn('photo', 'string', ['length' => 255, 'notnull' => false]);
        $card->addColumn('photo_source', 'string', ['length' => 24]);
        $card->addColumn('issue_date', 'date_immutable');
        $card->addColumn('expiration_date', 'datetime_immutable');
        $card->addColumn('qr_token', 'string', ['length' => 64]);
        $card->addColumn('status', 'string', ['length' => 10]);
        foreach (['expiration_override', 'auto_sync_milhq', 'sync_name', 'sync_organization', 'sync_photo', 'sync_status'] as $field) { $card->addColumn($field, 'boolean'); }
        $card->addColumn('created_at', 'datetime_immutable');
        $card->addColumn('updated_at', 'datetime_immutable');
        $card->addColumn('revoked_at', 'datetime_immutable', ['notnull' => false]);
        $card->addColumn('revocation_reason', 'string', ['length' => 500, 'notnull' => false]);
        $card->addColumn('notes', 'text', ['notnull' => false]);
        $card->addUniqueIndex(['member_id']); $card->addUniqueIndex(['qr_token']);
        $card->addIndex(['source', 'milhq_soldier_id'], 'idx_id_card_source');
        $card->addIndex(['status', 'expiration_date'], 'idx_id_card_status');
        $unit = $schema->createTable('majestic_id_unit_mapping');
        $unit->addColumn('id', 'integer', ['autoincrement' => true]); $unit->setPrimaryKey(['id']);
        $unit->addColumn('milhq_unit_id', 'integer'); $unit->addUniqueIndex(['milhq_unit_id']);
        $unit->addColumn('milhq_unit_name', 'string', ['length' => 100]);
        foreach ([1,2,3] as $i) { $unit->addColumn('organization_line'.$i, 'string', ['length' => 100]); }
        $unit->addColumn('enabled', 'boolean');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('majestic_id_unit_mapping'); $schema->dropTable('majestic_id_card');
    }
}
