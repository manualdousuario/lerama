<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLastErrorAndFixIndexes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('feeds')
            ->addColumn('last_error', 'text', [
                'null' => true,
                'default' => null,
                'after' => 'status',
            ])
            ->update();

        $this->table('feed_items')
            ->addIndex(['guid'], ['name' => 'idx_guid'])
            ->update();

        $this->table('categories')
            ->removeIndexByName('idx_slug')
            ->update();

        $this->table('tags')
            ->removeIndexByName('idx_slug')
            ->update();
    }

    public function down(): void
    {
        $this->table('feed_items')
            ->removeIndexByName('idx_guid')
            ->update();

        $this->table('categories')
            ->addIndex(['slug'], ['name' => 'idx_slug'])
            ->update();

        $this->table('tags')
            ->addIndex(['slug'], ['name' => 'idx_slug'])
            ->update();

        $this->table('feeds')
            ->removeColumn('last_error')
            ->update();
    }
}
