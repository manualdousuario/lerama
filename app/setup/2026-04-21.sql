SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `feeds`
ADD COLUMN IF NOT EXISTS `item_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `shuffle`;

ALTER TABLE `feeds`
ADD INDEX IF NOT EXISTS `idx_status_shuffle`(`status`, `shuffle`) USING BTREE;

UPDATE `feeds` f
SET f.item_count = (
    SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id
);

DROP TRIGGER IF EXISTS `update_feed_item_count_on_insert`;
DROP TRIGGER IF EXISTS `update_feed_item_count_on_delete`;

CREATE TRIGGER `update_feed_item_count_on_insert`
AFTER INSERT ON `feed_items`
FOR EACH ROW
BEGIN
    UPDATE `feeds` SET `item_count` = `item_count` + 1 WHERE `id` = NEW.feed_id;
END;

CREATE TRIGGER `update_feed_item_count_on_delete`
AFTER DELETE ON `feed_items`
FOR EACH ROW
BEGIN
    UPDATE `feeds` SET `item_count` = GREATEST(0, `item_count` - 1) WHERE `id` = OLD.feed_id;
END;

SET FOREIGN_KEY_CHECKS = 1;
