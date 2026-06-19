<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RestoreFeedItemsVisibleIndexes extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("DROP INDEX IF EXISTS `idx_is_visible` ON `feed_items`");
        $this->execute("DROP INDEX IF EXISTS `idx_feed_visible` ON `feed_items`");
        $this->execute("DROP INDEX IF EXISTS `idx_visible_published` ON `feed_items`");

        $this->table('feed_items')
            ->addIndex(['is_visible'], ['name' => 'idx_is_visible'])
            ->addIndex(['feed_id', 'is_visible'], ['name' => 'idx_feed_visible'])
            ->addIndex(['is_visible', 'published_at'], ['name' => 'idx_visible_published'])
            ->update();
    }

    public function down(): void
    {
        $this->execute("DROP INDEX IF EXISTS `idx_is_visible` ON `feed_items`");
        $this->execute("DROP INDEX IF EXISTS `idx_feed_visible` ON `feed_items`");
        $this->execute("DROP INDEX IF EXISTS `idx_visible_published` ON `feed_items`");
    }
}
