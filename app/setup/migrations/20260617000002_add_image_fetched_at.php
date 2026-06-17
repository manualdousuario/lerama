<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddImageFetchedAt extends AbstractMigration
{
    public function up(): void
    {
        $this->table('feed_items')
            ->addColumn('image_fetched_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'image_url',
            ])
            ->addIndex(['image_fetched_at'], ['name' => 'idx_image_fetched_at'])
            ->update();
    }

    public function down(): void
    {
        $this->table('feed_items')
            ->removeIndexByName('idx_image_fetched_at')
            ->removeColumn('image_fetched_at')
            ->update();
    }
}
