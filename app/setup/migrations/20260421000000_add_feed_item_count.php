<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFeedItemCount extends AbstractMigration
{
    public function up(): void
    {
        $this->table('feeds')
            ->addColumn('item_count', 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 0,
                'after' => 'shuffle',
            ])
            ->addIndex(['status', 'shuffle'], ['name' => 'idx_status_shuffle'])
            ->update();

        $this->execute(
            "UPDATE `feeds` f SET f.item_count = ("
            . "SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id)"
        );

        $this->execute("DROP TRIGGER IF EXISTS `update_feed_item_count_on_insert`");
        $this->execute("DROP TRIGGER IF EXISTS `update_feed_item_count_on_delete`");

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_feed_item_count_on_insert`
AFTER INSERT ON `feed_items`
FOR EACH ROW
BEGIN
    UPDATE `feeds` SET `item_count` = `item_count` + 1 WHERE `id` = NEW.feed_id;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_feed_item_count_on_delete`
AFTER DELETE ON `feed_items`
FOR EACH ROW
BEGIN
    UPDATE `feeds` SET `item_count` = GREATEST(0, `item_count` - 1) WHERE `id` = OLD.feed_id;
END
SQL);
    }

    public function down(): void
    {
        $this->execute("DROP TRIGGER IF EXISTS `update_feed_item_count_on_insert`");
        $this->execute("DROP TRIGGER IF EXISTS `update_feed_item_count_on_delete`");

        $this->table('feeds')
            ->removeIndexByName('idx_status_shuffle')
            ->removeColumn('item_count')
            ->update();
    }
}
