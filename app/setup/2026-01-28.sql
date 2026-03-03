SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `feeds`
ADD COLUMN IF NOT EXISTS `last_feed_item_id` int(10) UNSIGNED DEFAULT NULL AFTER `proxy_only`;

ALTER TABLE `feeds`
ADD INDEX IF NOT EXISTS `idx_last_feed_item_id`(`last_feed_item_id`) USING BTREE;

-- Initialize the last_feed_item_id for existing feeds
UPDATE `feeds` SET `last_feed_item_id` = (SELECT `feed_items`.`id` FROM `feed_items` WHERE `feed_items`.`feed_id` = `feeds`.`id` AND `feed_items`.`is_visible` = 1  AND `feed_items`.`guid` = `feeds`.`last_post_id` ORDER BY `feed_items`.`id` DESC LIMIT 1);

SET FOREIGN_KEY_CHECKS = 1;