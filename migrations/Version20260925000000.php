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
        // hasColumn()-guarded: an earlier release of this migration combined every ADD
        // COLUMN into one ALTER TABLE statement and failed outright on MySQL (error 1101 -
        // JSON columns can't have a DEFAULT clause at all, not even DEFAULT NULL), which
        // takes the whole statement down with it. Safe to retry from a clean slate either way.
        if (!$card->hasColumn('rank')) { $card->addColumn('rank', 'string', ['length' => 100, 'notnull' => false]); }
        if (!$card->hasColumn('specialty')) { $card->addColumn('specialty', 'string', ['length' => 100, 'notnull' => false]); }
        if (!$card->hasColumn('callsign')) { $card->addColumn('callsign', 'string', ['length' => 50, 'notnull' => false]); }
        if (!$card->hasColumn('sync_qualifications')) { $card->addColumn('sync_qualifications', 'boolean', ['default' => true]); }
        // Add these nullable with no DEFAULT at all - postUp() backfills existing rows and
        // tightens them to NOT NULL once the column physically exists, since that's the only
        // way to land a JSON column that's both populated for existing rows and NOT NULL
        // without ever writing a DEFAULT clause against it.
        if (!$card->hasColumn('qualifications')) { $card->addColumn('qualifications', 'json', ['notnull' => false]); }
        if (!$card->hasColumn('awards')) { $card->addColumn('awards', 'json', ['notnull' => false]); }
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement("UPDATE majestic_id_card SET qualifications = '[]' WHERE qualifications IS NULL");
        $this->connection->executeStatement("UPDATE majestic_id_card SET awards = '[]' WHERE awards IS NULL");

        $toSchema = clone $schema;
        $card = $toSchema->getTable('majestic_id_card');
        $card->modifyColumn('qualifications', ['notnull' => true]);
        $card->modifyColumn('awards', ['notnull' => true]);
        $diff = $this->sm->createComparator()->compareSchemas($schema, $toSchema);
        foreach ($this->platform->getAlterSchemaSQL($diff) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $card = $schema->getTable('majestic_id_card');
        if ($card->hasColumn('sync_qualifications')) { $card->dropColumn('sync_qualifications'); }
        if ($card->hasColumn('awards')) { $card->dropColumn('awards'); }
        if ($card->hasColumn('qualifications')) { $card->dropColumn('qualifications'); }
        if ($card->hasColumn('callsign')) { $card->dropColumn('callsign'); }
        if ($card->hasColumn('specialty')) { $card->dropColumn('specialty'); }
        if ($card->hasColumn('rank')) { $card->dropColumn('rank'); }
    }
}
