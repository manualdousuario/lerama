SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `feeds`;
CREATE TABLE `feeds` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `feed_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `site_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `feed_type` enum('rss1','rss2','atom','rdf','csv','json','xml') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_post_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `last_checked` datetime NULL DEFAULT NULL,
    `last_updated` datetime NULL DEFAULT NULL,
    `status` enum('online','offline','paused') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'online',
    `created_at` datetime NULL DEFAULT current_timestamp(),
    `updated_at` datetime NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
    `language` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `feed_url`(`feed_url`(255)) USING,
    INDEX `idx_feed_status`(`status`) USING BTREE,
    INDEX `idx_feed_type`(`feed_type`) USING BTREE,
    INDEX `idx_last_checked`(`last_checked`) USING BTREE,
    INDEX `idx_last_updated`(`last_updated`) USING BTREE,
    INDEX `idx_status_checked`(`status`, `last_checked`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `feed_items`;
CREATE TABLE `feed_items` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `feed_id` int(10) UNSIGNED NOT NULL,
    `title` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `image_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `guid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `published_at` datetime NULL DEFAULT NULL,
    `is_visible` tinyint(1) NULL DEFAULT 1,
    `created_at` datetime NULL DEFAULT current_timestamp(),
    `updated_at` datetime NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE INDEX `unique_item`(`feed_id`, `guid`) USING BTREE,
    FULLTEXT INDEX `idx_title_content`(`title`, `content`),
    INDEX `idx_published_at`(`published_at`) USING BTREE,
    INDEX `idx_is_visible`(`is_visible`) USING BTREE,
    INDEX `idx_feed_published`(`feed_id`, `published_at`) USING BTREE,
    INDEX `idx_feed_visible`(`feed_id`, `is_visible`) USING BTREE,
    CONSTRAINT `feed_items_ibfk_1` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;