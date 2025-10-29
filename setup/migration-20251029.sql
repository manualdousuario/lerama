-- Migration for Tags, Categories, and Feed Suggestions
-- Date: 2025-10-29

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create migrations tracking table
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `executed_at` datetime NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `migration`(`migration`) USING BTREE,
    INDEX `idx_executed_at`(`executed_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- Create categories table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NULL DEFAULT current_timestamp(),
    `updated_at` datetime NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `slug`(`slug`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- Create tags table
CREATE TABLE IF NOT EXISTS `tags` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NULL DEFAULT current_timestamp(),
    `updated_at` datetime NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `slug`(`slug`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- Create feed_categories junction table
CREATE TABLE IF NOT EXISTS `feed_categories` (
    `feed_id` int(10) UNSIGNED NOT NULL,
    `category_id` int(10) UNSIGNED NOT NULL,
    `created_at` datetime NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`feed_id`, `category_id`) USING BTREE,
    INDEX `idx_category_id`(`category_id`) USING BTREE,
    CONSTRAINT `feed_categories_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `feed_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- Create feed_tags junction table
CREATE TABLE IF NOT EXISTS `feed_tags` (
    `feed_id` int(10) UNSIGNED NOT NULL,
    `tag_id` int(10) UNSIGNED NOT NULL,
    `created_at` datetime NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`feed_id`, `tag_id`) USING BTREE,
    INDEX `idx_tag_id`(`tag_id`) USING BTREE,
    CONSTRAINT `feed_tags_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `feed_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- Modify status enum to include 'pending' and 'rejected' for feed suggestions
ALTER TABLE `feeds`
MODIFY COLUMN `status` enum('online','offline','paused','pending','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'online',
ADD COLUMN IF NOT EXISTS `submitter_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `language`;

-- Add composite index on feed_categories for better JOIN performance
ALTER TABLE `feed_categories`
ADD INDEX IF NOT EXISTS `idx_feed_category_lookup`(`category_id`, `feed_id`) USING BTREE;

-- Add composite index on feed_tags for better JOIN performance
ALTER TABLE `feed_tags`
ADD INDEX IF NOT EXISTS `idx_feed_tag_lookup`(`tag_id`, `feed_id`) USING BTREE;

-- Add index on categories slug for filtering
ALTER TABLE `categories`
ADD INDEX IF NOT EXISTS `idx_slug`(`slug`) USING BTREE;

-- Add index on tags slug for filtering
ALTER TABLE `tags`
ADD INDEX IF NOT EXISTS `idx_slug`(`slug`) USING BTREE;

SET FOREIGN_KEY_CHECKS = 1;