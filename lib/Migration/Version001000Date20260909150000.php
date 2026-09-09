<?php

declare(strict_types=1);

namespace OCA\DocSort\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** What each file was recognised as (never its text). */
final class Version001000Date20260909150000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('docsort_files')) {
            $table = $schema->createTable('docsort_files');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
            $table->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('fileid', Types::BIGINT, ['notnull' => true, 'length' => 20]);
            $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('kind', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('score', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('engine', Types::STRING, ['notnull' => false, 'length' => 32]);
            $table->addColumn('moved_to', Types::STRING, ['notnull' => false, 'length' => 1024]);
            $table->addColumn('mtime', Types::BIGINT, ['notnull' => true, 'length' => 20, 'default' => 0]);
            $table->addColumn('processed', Types::BIGINT, ['notnull' => true, 'length' => 20, 'default' => 0]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uid', 'fileid'], 'docsort_uid_file');
            $table->addIndex(['uid', 'processed'], 'docsort_uid_time');
        }

        return $schema;
    }
}
