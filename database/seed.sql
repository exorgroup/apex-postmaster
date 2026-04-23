-- APEX PostMaster Database Seeder
-- Run this after migrations to add test data for development

-- Insert test admin user (password: password123)
INSERT INTO `users` (`email`, `password`, `name`, `plan`, `role`, `timezone`, `onboarding_completed`, `credit_balance_cents`, `created_at`)
VALUES (
    'admin@apexpostmaster.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin User',
    'team',
    'admin',
    'UTC',
    1,
    10000,
    NOW()
);

-- Insert test regular user (password: password123)
INSERT INTO `users` (`email`, `password`, `name`, `plan`, `role`, `timezone`, `onboarding_completed`, `credit_balance_cents`, `created_at`)
VALUES (
    'user@apexpostmaster.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Test User',
    'free',
    'user',
    'UTC',
    1,
    500,
    NOW()
);

-- Insert sample coupons
INSERT INTO `coupons` (`code`, `type`, `value_cents`, `max_redemptions`, `expires_at`, `is_active`, `created_at`)
VALUES
    ('WELCOME50', 'credits', 500, 100, DATE_ADD(NOW(), INTERVAL 1 YEAR), 1, NOW()),
    ('LAUNCH20', 'discount_percent', 20, 50, DATE_ADD(NOW(), INTERVAL 6 MONTH), 1, NOW()),
    ('FREETRIAL', 'trial_days', 30, NULL, NULL, 1, NOW());

-- Insert sample posts for test user
INSERT INTO `posts` (`user_id`, `content`, `status`, `scheduled_at`, `created_at`)
VALUES
    (2, 'Just discovered the power of consistent posting! 🚀 #SocialMediaTips #Growth', 'draft', NULL, NOW()),
    (2, 'Our new product launch is coming soon! Stay tuned for something amazing. ✨ #ComingSoon #Excited', 'scheduled', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW()),
    (2, 'Thank you to all our amazing followers! You make this journey worthwhile. ❤️ #ThankYou #Community', 'scheduled', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW());

-- Link posts to platforms
INSERT INTO `post_platforms` (`post_id`, `platform`, `status`)
VALUES
    (1, 'instagram', 'pending'),
    (1, 'facebook', 'pending'),
    (2, 'youtube', 'pending'),
    (2, 'instagram', 'pending'),
    (3, 'facebook', 'pending');

SELECT 'Seed data inserted successfully!' AS status;
