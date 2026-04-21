<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLastDailyRun extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('feeds');

        if (!$table->hasColumn('next_fetch_at')) {
            $table->addColumn('next_fetch_at', 'biginteger', [
                'signed' => false,
                'null' => false,
                'default' => 0,
                'after' => 'last_updated',
            ]);
        }

        if (!$table->hasColumn('etag')) {
            $table->addColumn('etag', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'next_fetch_at',
            ]);
        }

        if (!$table->hasColumn('last_modified')) {
            $table->addColumn('last_modified', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'after' => 'etag',
            ]);
        }

        $table->update();

        if (!$this->hasIndexByName('feeds', 'idx_status_next_fetch')) {
            $this->table('feeds')
                ->addIndex(['status', 'next_fetch_at'], ['name' => 'idx_status_next_fetch'])
                ->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('feeds');

        if ($this->hasIndexByName('feeds', 'idx_status_next_fetch')) {
            $table->removeIndexByName('idx_status_next_fetch')->update();
        }

        foreach (['last_modified', 'etag', 'next_fetch_at'] as $col) {
            if ($this->table('feeds')->hasColumn($col)) {
                $this->table('feeds')->removeColumn($col)->update();
            }
        }
    }

    private function hasIndexByName(string $table, string $indexName): bool
    {
        $row = $this->fetchRow(
            "SELECT 1 FROM information_schema.statistics "
            . "WHERE table_schema = DATABASE() "
            . "AND table_name = '" . $table . "' "
            . "AND index_name = '" . $indexName . "' LIMIT 1"
        );

        return !empty($row);
    }
}
