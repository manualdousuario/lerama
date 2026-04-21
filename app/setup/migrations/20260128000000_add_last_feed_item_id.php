<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLastFeedItemId extends AbstractMigration
{
    public function up(): void
    {
        $this->table('feeds')
            ->addColumn('last_feed_item_id', 'integer', [
                'signed' => false,
                'null' => true,
                'default' => null,
                'after' => 'proxy_only',
            ])
            ->addIndex(['last_feed_item_id'], ['name' => 'idx_last_feed_item_id'])
            ->update();

        $this->execute(
            "UPDATE `feeds` SET `last_feed_item_id` = ("
            . "SELECT `feed_items`.`id` FROM `feed_items` "
            . "WHERE `feed_items`.`feed_id` = `feeds`.`id` "
            . "AND `feed_items`.`is_visible` = 1 "
            . "AND `feed_items`.`guid` = `feeds`.`last_post_id` "
            . "ORDER BY `feed_items`.`id` DESC LIMIT 1)"
        );
    }

    public function down(): void
    {
        $this->table('feeds')
            ->removeIndexByName('idx_last_feed_item_id')
            ->removeColumn('last_feed_item_id')
            ->update();
    }
}
