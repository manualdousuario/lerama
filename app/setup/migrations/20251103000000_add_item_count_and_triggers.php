<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddItemCountAndTriggers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('categories')
            ->addColumn('item_count', 'integer', ['signed' => false, 'default' => 0, 'after' => 'slug'])
            ->update();

        $this->table('tags')
            ->addColumn('item_count', 'integer', ['signed' => false, 'default' => 0, 'after' => 'slug'])
            ->update();

        $this->execute(
            "UPDATE `categories` c SET c.item_count = ("
            . "SELECT COUNT(DISTINCT fi.id) FROM feed_items fi "
            . "JOIN feeds f ON fi.feed_id = f.id "
            . "JOIN feed_categories fc ON f.id = fc.feed_id "
            . "WHERE fc.category_id = c.id AND fi.is_visible = 1)"
        );

        $this->execute(
            "UPDATE `tags` t SET t.item_count = ("
            . "SELECT COUNT(DISTINCT fi.id) FROM feed_items fi "
            . "JOIN feeds f ON fi.feed_id = f.id "
            . "JOIN feed_tags ft ON f.id = ft.feed_id "
            . "WHERE ft.tag_id = t.id AND fi.is_visible = 1)"
        );

        foreach ([
            'update_category_count_on_insert',
            'update_category_count_on_delete',
            'update_category_count_on_update',
            'update_tag_count_on_insert',
            'update_tag_count_on_delete',
            'update_tag_count_on_update',
        ] as $trigger) {
            $this->execute("DROP TRIGGER IF EXISTS `{$trigger}`");
        }

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_category_count_on_insert`
AFTER INSERT ON `feed_items`
FOR EACH ROW
BEGIN
    IF NEW.is_visible = 1 THEN
        UPDATE `categories` c
        INNER JOIN `feed_categories` fc ON c.id = fc.category_id
        SET c.item_count = c.item_count + 1
        WHERE fc.feed_id = NEW.feed_id;
    END IF;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_category_count_on_delete`
AFTER DELETE ON `feed_items`
FOR EACH ROW
BEGIN
    IF OLD.is_visible = 1 THEN
        UPDATE `categories` c
        INNER JOIN `feed_categories` fc ON c.id = fc.category_id
        SET c.item_count = GREATEST(0, c.item_count - 1)
        WHERE fc.feed_id = OLD.feed_id;
    END IF;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_category_count_on_update`
AFTER UPDATE ON `feed_items`
FOR EACH ROW
BEGIN
    IF OLD.is_visible != NEW.is_visible THEN
        IF NEW.is_visible = 1 THEN
            UPDATE `categories` c
            INNER JOIN `feed_categories` fc ON c.id = fc.category_id
            SET c.item_count = c.item_count + 1
            WHERE fc.feed_id = NEW.feed_id;
        ELSE
            UPDATE `categories` c
            INNER JOIN `feed_categories` fc ON c.id = fc.category_id
            SET c.item_count = GREATEST(0, c.item_count - 1)
            WHERE fc.feed_id = NEW.feed_id;
        END IF;
    END IF;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_tag_count_on_insert`
AFTER INSERT ON `feed_items`
FOR EACH ROW
BEGIN
    IF NEW.is_visible = 1 THEN
        UPDATE `tags` t
        INNER JOIN `feed_tags` ft ON t.id = ft.tag_id
        SET t.item_count = t.item_count + 1
        WHERE ft.feed_id = NEW.feed_id;
    END IF;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_tag_count_on_delete`
AFTER DELETE ON `feed_items`
FOR EACH ROW
BEGIN
    IF OLD.is_visible = 1 THEN
        UPDATE `tags` t
        INNER JOIN `feed_tags` ft ON t.id = ft.tag_id
        SET t.item_count = GREATEST(0, t.item_count - 1)
        WHERE ft.feed_id = OLD.feed_id;
    END IF;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_tag_count_on_update`
AFTER UPDATE ON `feed_items`
FOR EACH ROW
BEGIN
    IF OLD.is_visible != NEW.is_visible THEN
        IF NEW.is_visible = 1 THEN
            UPDATE `tags` t
            INNER JOIN `feed_tags` ft ON t.id = ft.tag_id
            SET t.item_count = t.item_count + 1
            WHERE ft.feed_id = NEW.feed_id;
        ELSE
            UPDATE `tags` t
            INNER JOIN `feed_tags` ft ON t.id = ft.tag_id
            SET t.item_count = GREATEST(0, t.item_count - 1)
            WHERE ft.feed_id = NEW.feed_id;
        END IF;
    END IF;
END
SQL);
    }

    public function down(): void
    {
        foreach ([
            'update_category_count_on_insert',
            'update_category_count_on_delete',
            'update_category_count_on_update',
            'update_tag_count_on_insert',
            'update_tag_count_on_delete',
            'update_tag_count_on_update',
        ] as $trigger) {
            $this->execute("DROP TRIGGER IF EXISTS `{$trigger}`");
        }

        $this->table('categories')->removeColumn('item_count')->update();
        $this->table('tags')->removeColumn('item_count')->update();
    }
}
