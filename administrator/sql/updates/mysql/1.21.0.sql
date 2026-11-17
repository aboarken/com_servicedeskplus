-- 1.21.0: per-user cache in the database (was the session).
CREATE TABLE IF NOT EXISTS `#__sdp_cache` (
  `user_id` INT UNSIGNED NOT NULL,
  `cache_key` VARCHAR(191) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`, `cache_key`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
