-- Migration for Category and Tag Item Counts
-- Date: 2025-11-03
-- Adds item_count columns and triggers to automatically maintain counts

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Add item_count column to categories table
ALTER TABLE `categories`
ADD COLUMN IF NOT EXISTS `item_count` INT UNSIGNED DEFAULT 0 AFTER `slug`;

-- Add item_count column to tags table
ALTER TABLE `tags`
ADD COLUMN IF NOT EXISTS `item_count` INT UNSIGNED DEFAULT 0 AFTER `slug`;

-- Initialize counts for existing categories
UPDATE `categories` c
SET c.item_count = (
    SELECT COUNT(DISTINCT fi.id)
    FROM feed_items fi
    JOIN feeds f ON fi.feed_id = f.id
    JOIN feed_categories fc ON f.id = fc.feed_id
    WHERE fc.category_id = c.id
    AND fi.is_visible = 1
);

-- Initialize counts for existing tags
UPDATE `tags` t
SET t.item_count = (
    SELECT COUNT(DISTINCT fi.id)
    FROM feed_items fi
    JOIN feeds f ON fi.feed_id = f.id
    JOIN feed_tags ft ON f.id = ft.feed_id
    WHERE ft.tag_id = t.id
    AND fi.is_visible = 1
);

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS `update_category_count_on_insert`;
DROP TRIGGER IF EXISTS `update_category_count_on_delete`;
DROP TRIGGER IF EXISTS `update_category_count_on_update`;
DROP TRIGGER IF EXISTS `update_tag_count_on_insert`;
DROP TRIGGER IF EXISTS `update_tag_count_on_delete`;
DROP TRIGGER IF EXISTS `update_tag_count_on_update`;

-- Trigger to update category counts when feed_item is inserted
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
END;

-- Trigger to update category counts when feed_item is deleted
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
END;

-- Trigger to update category counts when feed_item visibility changes
CREATE TRIGGER `update_category_count_on_update`
AFTER UPDATE ON `feed_items`
FOR EACH ROW
BEGIN
    IF OLD.is_visible != NEW.is_visible THEN
        IF NEW.is_visible = 1 THEN
            -- Item became visible, increment count
            UPDATE `categories` c
            INNER JOIN `feed_categories` fc ON c.id = fc.category_id
            SET c.item_count = c.item_count + 1
            WHERE fc.feed_id = NEW.feed_id;
        ELSE
            -- Item became invisible, decrement count
            UPDATE `categories` c
            INNER JOIN `feed_categories` fc ON c.id = fc.category_id
            SET c.item_count = GREATEST(0, c.item_count - 1)
            WHERE fc.feed_id = NEW.feed_id;
        END IF;
    END IF;
END;

-- Trigger to update tag counts when feed_item is inserted
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
END;

-- Trigger to update tag counts when feed_item is deleted
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
END;

-- Trigger to update tag counts when feed_item visibility changes
CREATE TRIGGER `update_tag_count_on_update`
AFTER UPDATE ON `feed_items`
FOR EACH ROW
BEGIN
    IF OLD.is_visible != NEW.is_visible THEN
        IF NEW.is_visible = 1 THEN
            -- Item became visible, increment count
            UPDATE `tags` t
            INNER JOIN `feed_tags` ft ON t.id = ft.tag_id
            SET t.item_count = t.item_count + 1
            WHERE ft.feed_id = NEW.feed_id;
        ELSE
            -- Item became invisible, decrement count
            UPDATE `tags` t
            INNER JOIN `feed_tags` ft ON t.id = ft.tag_id
            SET t.item_count = GREATEST(0, t.item_count - 1)
            WHERE ft.feed_id = NEW.feed_id;
        END IF;
    END IF;
END;

SET FOREIGN_KEY_CHECKS = 1;