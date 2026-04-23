-- Migration: Add team_members table
-- This table tracks team members for multi-seat subscriptions

CREATE TABLE IF NOT EXISTS `team_members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `team_owner_id` int unsigned NOT NULL COMMENT 'The user who owns the subscription',
  `user_id` int unsigned NOT NULL COMMENT 'The team member user',
  `role` enum('admin','editor','viewer') NOT NULL DEFAULT 'editor',
  `status` enum('pending','active','suspended','removed') NOT NULL DEFAULT 'pending',
  `invited_email` varchar(255) DEFAULT NULL COMMENT 'Email used for invite (before user accepts)',
  `invite_token` varchar(64) DEFAULT NULL,
  `invite_expires_at` datetime DEFAULT NULL,
  `joined_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_members_owner_user_unique` (`team_owner_id`, `user_id`),
  KEY `team_members_owner_idx` (`team_owner_id`),
  KEY `team_members_user_idx` (`user_id`),
  KEY `team_members_status_idx` (`status`),
  KEY `team_members_invite_token_idx` (`invite_token`),
  CONSTRAINT `team_members_owner_fk` FOREIGN KEY (`team_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_members_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record this migration
INSERT INTO `migrations` (`migration`, `executed_at`) VALUES ('004_add_team_members_table', NOW())
ON DUPLICATE KEY UPDATE `executed_at` = NOW();
