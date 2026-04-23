-- Migration: Add columns for scheduled subscription changes
-- These columns are used for:
-- - Scheduling plan downgrades (takes effect at end of billing period)
-- - Scheduling seat reductions (takes effect at end of billing period)
-- - Tracking cancellations (cancelled_at timestamp for when user requested cancellation)

-- Add scheduled columns to subscriptions table
ALTER TABLE `subscriptions`
ADD COLUMN `plan_scheduled` ENUM('free', 'creator', 'pro', 'team', 'agency') NULL DEFAULT NULL AFTER `seats`,
ADD COLUMN `seats_scheduled` INT UNSIGNED NULL DEFAULT NULL AFTER `plan_scheduled`,
ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `cancel_at_period_end`;

-- Add index for processing scheduled changes (run daily via cron)
CREATE INDEX `idx_subscriptions_scheduled` ON `subscriptions` (`status`, `current_period_end`, `cancel_at_period_end`);
CREATE INDEX `idx_subscriptions_plan_scheduled` ON `subscriptions` (`status`, `current_period_end`, `plan_scheduled`);
CREATE INDEX `idx_subscriptions_seats_scheduled` ON `subscriptions` (`status`, `current_period_end`, `seats_scheduled`);
