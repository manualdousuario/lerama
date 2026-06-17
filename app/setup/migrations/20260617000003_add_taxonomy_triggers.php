<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTaxonomyTriggers extends AbstractMigration
{
    public function up(): void
    {
        foreach ([
            'update_category_count_on_feed_category_insert',
            'update_category_count_on_feed_category_delete',
            'update_tag_count_on_feed_tag_insert',
            'update_tag_count_on_feed_tag_delete',
        ] as $trigger) {
            $this->execute("DROP TRIGGER IF EXISTS `{$trigger}`");
        }

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_category_count_on_feed_category_insert`
AFTER INSERT ON `feed_categories`
FOR EACH ROW
BEGIN
    UPDATE `categories` c
    SET c.item_count = (
        SELECT COUNT(DISTINCT fi.id)
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        JOIN feed_categories fc ON f.id = fc.feed_id
        WHERE fc.category_id = NEW.category_id
          AND fi.is_visible = 1
    )
    WHERE c.id = NEW.category_id;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_category_count_on_feed_category_delete`
AFTER DELETE ON `feed_categories`
FOR EACH ROW
BEGIN
    UPDATE `categories` c
    SET c.item_count = (
        SELECT COUNT(DISTINCT fi.id)
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        JOIN feed_categories fc ON f.id = fc.feed_id
        WHERE fc.category_id = OLD.category_id
          AND fi.is_visible = 1
    )
    WHERE c.id = OLD.category_id;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_tag_count_on_feed_tag_insert`
AFTER INSERT ON `feed_tags`
FOR EACH ROW
BEGIN
    UPDATE `tags` t
    SET t.item_count = (
        SELECT COUNT(DISTINCT fi.id)
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        JOIN feed_tags ft ON f.id = ft.feed_id
        WHERE ft.tag_id = NEW.tag_id
          AND fi.is_visible = 1
    )
    WHERE t.id = NEW.tag_id;
END
SQL);

        $this->execute(<<<'SQL'
CREATE TRIGGER `update_tag_count_on_feed_tag_delete`
AFTER DELETE ON `feed_tags`
FOR EACH ROW
BEGIN
    UPDATE `tags` t
    SET t.item_count = (
        SELECT COUNT(DISTINCT fi.id)
        FROM feed_items fi
        JOIN feeds f ON fi.feed_id = f.id
        JOIN feed_tags ft ON f.id = ft.feed_id
        WHERE ft.tag_id = OLD.tag_id
          AND fi.is_visible = 1
    )
    WHERE t.id = OLD.tag_id;
END
SQL);

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
    }

    public function down(): void
    {
        foreach ([
            'update_category_count_on_feed_category_insert',
            'update_category_count_on_feed_category_delete',
            'update_tag_count_on_feed_tag_insert',
            'update_tag_count_on_feed_tag_delete',
        ] as $trigger) {
            $this->execute("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }
}
