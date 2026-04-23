-- APEX PostMaster Database Schema
-- MySQL 8.0+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `plan` enum('free','pro','team') NOT NULL DEFAULT 'free',
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `timezone` varchar(50) DEFAULT 'UTC',
  `industry` varchar(50) DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT '0',
  `credit_balance_cents` int NOT NULL DEFAULT '0',
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `oauth_provider` varchar(50) DEFAULT NULL,
  `oauth_id` varchar(255) DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `deletion_requested_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_plan_idx` (`plan`),
  KEY `users_stripe_customer_idx` (`stripe_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refresh tokens
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `refresh_tokens_user_idx` (`user_id`),
  KEY `refresh_tokens_token_idx` (`token`),
  KEY `refresh_tokens_expires_idx` (`expires_at`),
  CONSTRAINT `refresh_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `password_resets_user_idx` (`user_id`),
  KEY `password_resets_token_idx` (`token`),
  CONSTRAINT `password_resets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social accounts
CREATE TABLE IF NOT EXISTS `social_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `platform` varchar(50) NOT NULL,
  `platform_user_id` varchar(255) NOT NULL,
  `platform_username` varchar(255) DEFAULT NULL,
  `platform_display_name` varchar(255) DEFAULT NULL,
  `platform_avatar` varchar(500) DEFAULT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_accounts_unique` (`user_id`, `platform`, `platform_user_id`),
  KEY `social_accounts_user_idx` (`user_id`),
  KEY `social_accounts_platform_idx` (`platform`),
  CONSTRAINT `social_accounts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posts
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `content` text NOT NULL,
  `media_urls` json DEFAULT NULL,
  `status` enum('draft','scheduled','publishing','published','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `posts_user_idx` (`user_id`),
  KEY `posts_status_idx` (`status`),
  KEY `posts_scheduled_idx` (`scheduled_at`),
  KEY `posts_user_status_idx` (`user_id`, `status`),
  CONSTRAINT `posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post platforms (many-to-many with status per platform)
CREATE TABLE IF NOT EXISTS `post_platforms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int unsigned NOT NULL,
  `platform` varchar(50) NOT NULL,
  `status` enum('pending','publishing','published','failed') NOT NULL DEFAULT 'pending',
  `platform_post_id` varchar(255) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `error_message` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_platforms_unique` (`post_id`, `platform`),
  KEY `post_platforms_post_idx` (`post_id`),
  KEY `post_platforms_platform_idx` (`platform`),
  CONSTRAINT `post_platforms_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post analytics
CREATE TABLE IF NOT EXISTS `post_analytics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int unsigned NOT NULL,
  `platform` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `likes` int NOT NULL DEFAULT '0',
  `comments` int NOT NULL DEFAULT '0',
  `shares` int NOT NULL DEFAULT '0',
  `impressions` int NOT NULL DEFAULT '0',
  `reach` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_analytics_unique` (`post_id`, `platform`, `date`),
  KEY `post_analytics_post_idx` (`post_id`),
  KEY `post_analytics_date_idx` (`date`),
  CONSTRAINT `post_analytics_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI generations log
CREATE TABLE IF NOT EXISTS `ai_generations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `input` json DEFAULT NULL,
  `output` text,
  `input_tokens` int NOT NULL DEFAULT '0',
  `output_tokens` int NOT NULL DEFAULT '0',
  `model` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_generations_user_idx` (`user_id`),
  KEY `ai_generations_type_idx` (`type`),
  KEY `ai_generations_created_idx` (`created_at`),
  CONSTRAINT `ai_generations_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign chats
CREATE TABLE IF NOT EXISTS `campaign_chats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'New Campaign',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_chats_user_idx` (`user_id`),
  CONSTRAINT `campaign_chats_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign messages
CREATE TABLE IF NOT EXISTS `campaign_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int unsigned NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `cost_cents` int NOT NULL DEFAULT '0',
  `input_tokens` int NOT NULL DEFAULT '0',
  `output_tokens` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_messages_chat_idx` (`chat_id`),
  CONSTRAINT `campaign_messages_chat_fk` FOREIGN KEY (`chat_id`) REFERENCES `campaign_chats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Credit transactions
CREATE TABLE IF NOT EXISTS `credit_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `amount_cents` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `balance_after` int NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `credit_transactions_user_idx` (`user_id`),
  KEY `credit_transactions_type_idx` (`type`),
  CONSTRAINT `credit_transactions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscriptions
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `stripe_subscription_id` varchar(255) NOT NULL,
  `stripe_price_id` varchar(255) DEFAULT NULL,
  `plan` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_stripe_unique` (`stripe_subscription_id`),
  KEY `subscriptions_user_idx` (`user_id`),
  KEY `subscriptions_status_idx` (`status`),
  CONSTRAINT `subscriptions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment failures log
CREATE TABLE IF NOT EXISTS `payment_failures` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `stripe_invoice_id` varchar(255) DEFAULT NULL,
  `amount_cents` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_failures_user_idx` (`user_id`),
  CONSTRAINT `payment_failures_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('credits','discount_percent','discount_fixed','trial_days') NOT NULL,
  `value_cents` int NOT NULL,
  `max_redemptions` int DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupon redemptions
CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_redemptions_unique` (`coupon_id`, `user_id`),
  KEY `coupon_redemptions_user_idx` (`user_id`),
  CONSTRAINT `coupon_redemptions_coupon_fk` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_redemptions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User discounts (for checkout)
CREATE TABLE IF NOT EXISTS `user_discounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `coupon_id` int unsigned NOT NULL,
  `discount_type` varchar(50) NOT NULL,
  `discount_value` int NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_discounts_user_idx` (`user_id`),
  CONSTRAINT `user_discounts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_discounts_coupon_fk` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Streams
CREATE TABLE IF NOT EXISTS `streams` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('mentions','hashtag','keywords','scheduled') NOT NULL,
  `platform` varchar(50) NOT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `streams_user_idx` (`user_id`),
  KEY `streams_platform_idx` (`platform`),
  CONSTRAINT `streams_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stream items
CREATE TABLE IF NOT EXISTS `stream_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `stream_id` int unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `content` text,
  `author_name` varchar(255) DEFAULT NULL,
  `author_avatar` varchar(500) DEFAULT NULL,
  `platform` varchar(50) NOT NULL,
  `platform_post_id` varchar(255) DEFAULT NULL,
  `likes` int NOT NULL DEFAULT '0',
  `comments` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `stream_items_stream_idx` (`stream_id`),
  KEY `stream_items_platform_post_idx` (`platform_post_id`),
  KEY `stream_items_created_idx` (`created_at`),
  CONSTRAINT `stream_items_stream_fk` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
