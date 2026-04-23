-- Migration: Add new plans (creator, agency) and seats column
-- Run this migration to update your database for the new 5-tier pricing

-- Update users table: expand plan enum and add seats
ALTER TABLE `users`
MODIFY COLUMN `plan` ENUM('free', 'creator', 'pro', 'team', 'agency') NOT NULL DEFAULT 'free',
ADD COLUMN `seats` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `plan`;

-- Update subscriptions table: expand plan enum and add seats
ALTER TABLE `subscriptions`
MODIFY COLUMN `plan` ENUM('free', 'creator', 'pro', 'team', 'agency') NOT NULL DEFAULT 'free',
ADD COLUMN `seats` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `plan`;

-- Migrate existing 'pro' users to 'pro' (no change needed)
-- Migrate existing 'team' users to 'team' (no change needed)
