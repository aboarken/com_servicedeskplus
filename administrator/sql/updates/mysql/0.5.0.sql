CREATE TABLE IF NOT EXISTS `#__sdp_user_keys` (
  `joomla_user_id` INT UNSIGNED NOT NULL,
  `sdp_user_id` VARCHAR(20) NOT NULL,
  `encrypted_key` TEXT NOT NULL,
  `created` DATETIME NOT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `validated_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`joomla_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
