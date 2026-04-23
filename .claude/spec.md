# APEX PostMaster — Project Specification v2

## Social Media Management Platform

**Version:** 1.0 MVP
**Stack:** Vue 3 + PrimeVue 4 + Bootstrap 5 (grid/responsive) + PHP 8+ REST API + MySQL 8 + Hostinger Shared Hosting
**AI Engine:** Anthropic Claude API (Sonnet)
**Design:** KISS — clean, lean, responsive. Desktop-first but fully adaptive to tablets and phones.

---

## 1. Architecture Overview

```
hostinger-server/
├── public_html/
│   ├── app/                  ← Vue 3 SPA (Vite build output)
│   │   ├── index.html
│   │   └── assets/
│   ├── api/                  ← PHP REST API
│   │   ├── index.php         ← Front controller (router)
│   │   ├── .htaccess         ← URL rewriting
│   │   ├── config/
│   │   │   ├── database.php
│   │   │   ├── platforms.php
│   │   │   └── constants.php
│   │   ├── middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── CorsMiddleware.php
│   │   │   ├── RateLimitMiddleware.php
│   │   │   └── PlanLimitMiddleware.php
│   │   ├── controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── PostController.php
│   │   │   ├── PlatformController.php
│   │   │   ├── AnalyticsController.php
│   │   │   ├── AIController.php
│   │   │   ├── CampaignController.php
│   │   │   ├── OnboardingController.php
│   │   │   ├── BillingController.php
│   │   │   ├── CouponController.php
│   │   │   ├── CreditController.php
│   │   │   └── TeamController.php
│   │   ├── models/
│   │   │   ├── User.php
│   │   │   ├── Post.php
│   │   │   ├── SocialAccount.php
│   │   │   ├── Campaign.php
│   │   │   ├── CreditWallet.php
│   │   │   ├── Coupon.php
│   │   │   ├── Analytics.php
│   │   │   └── Subscription.php
│   │   ├── services/
│   │   │   ├── JWTService.php
│   │   │   ├── EncryptionService.php
│   │   │   ├── TokenEstimator.php
│   │   │   ├── platforms/
│   │   │   │   ├── PlatformFactory.php
│   │   │   │   ├── TwitterService.php
│   │   │   │   ├── LinkedInService.php
│   │   │   │   ├── InstagramService.php
│   │   │   │   ├── YouTubeService.php
│   │   │   │   ├── FacebookService.php
│   │   │   │   ├── ThreadsService.php
│   │   │   │   └── TikTokService.php
│   │   │   ├── ClaudeService.php
│   │   │   └── StripeService.php
│   │   └── cron/
│   │       ├── publisher.php      ← */5 * * * *
│   │       ├── analytics.php      ← 0 */6 * * *
│   │       └── cleanup.php        ← 0 3 * * *
│   └── uploads/                   ← User media files
│       └── media/
└── config/
    └── env.php                    ← OUTSIDE public_html
```

### Hostinger Cron Jobs

| Schedule | Script | Purpose |
|----------|--------|---------|
| `*/5 * * * *` | `publisher.php` | Publish scheduled posts |
| `0 */6 * * *` | `analytics.php` | Pull engagement metrics |
| `0 3 * * *` | `cleanup.php` | Expire tokens, purge old logs |

---

## 2. Database Schema (MySQL)

### Users & Auth

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    password_hash VARCHAR(255) NULL,
    auth_provider ENUM('email', 'google', 'facebook') DEFAULT 'email',
    auth_provider_id VARCHAR(255) NULL,
    plan ENUM('free', 'pro', 'team') DEFAULT 'free',
    onboarding_completed BOOLEAN DEFAULT FALSE,
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(5) DEFAULT 'en',
    industry VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_provider (auth_provider, auth_provider_id)
);

CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    owner_id INT NOT NULL,
    plan ENUM('free', 'pro', 'team') DEFAULT 'free',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'editor', 'viewer') DEFAULT 'editor',
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_team_user (team_id, user_id)
);

CREATE TABLE refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(500) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token(255))
);
```

### Social Platform Connections

```sql
CREATE TABLE social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_id INT NULL,
    platform ENUM('twitter', 'linkedin', 'instagram', 'youtube', 'threads', 'tiktok', 'facebook') NOT NULL,
    platform_user_id VARCHAR(255) NOT NULL,
    platform_username VARCHAR(255) NULL,
    platform_display_name VARCHAR(255) NULL,
    platform_avatar_url VARCHAR(500) NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    token_expires_at TIMESTAMP NULL,
    scopes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    INDEX idx_user_platform (user_id, platform),
    INDEX idx_token_expiry (token_expires_at)
);
```

### Posts & Scheduling

```sql
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_id INT NULL,
    campaign_id INT NULL,
    content TEXT NOT NULL,
    content_variants JSON NULL,
    media_urls JSON NULL,
    media_types JSON NULL,
    hashtags JSON NULL,
    link_url VARCHAR(1000) NULL,
    link_utm_params JSON NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'scheduled', 'publishing', 'published', 'failed', 'cancelled') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurrence_rule JSON NULL,
    ai_generated BOOLEAN DEFAULT FALSE,
    ai_score DECIMAL(3,2) NULL,
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_status_schedule (status, scheduled_at),
    INDEX idx_user_status (user_id, status),
    INDEX idx_campaign (campaign_id)
);

CREATE TABLE post_platforms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    social_account_id INT NOT NULL,
    platform ENUM('twitter', 'linkedin', 'instagram', 'youtube', 'threads', 'tiktok', 'facebook') NOT NULL,
    platform_post_id VARCHAR(255) NULL,
    platform_post_url VARCHAR(1000) NULL,
    status ENUM('pending', 'publishing', 'published', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    published_at TIMESTAMP NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (social_account_id) REFERENCES social_accounts(id),
    INDEX idx_post_platform (post_id, platform)
);

CREATE TABLE bulk_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    total_rows INT DEFAULT 0,
    processed_rows INT DEFAULT 0,
    failed_rows INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_log JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE evergreen_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    rotation_interval_days INT DEFAULT 30,
    last_posted_at TIMESTAMP NULL,
    next_post_at TIMESTAMP NULL,
    times_posted INT DEFAULT 0,
    max_posts INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_next_post (is_active, next_post_at)
);
```

### Campaign Planner

```sql
CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_id INT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    goal VARCHAR(100) NULL,
    status ENUM('planning', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'planning',
    start_date DATE NULL,
    end_date DATE NULL,
    total_budget_cents INT DEFAULT 0,
    organic_budget_cents INT DEFAULT 0,
    paid_budget_cents INT DEFAULT 0,
    total_posts_planned INT DEFAULT 0,
    total_posts_published INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    INDEX idx_user_status (user_id, status)
);

CREATE TABLE campaign_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    campaign_id INT NULL,
    title VARCHAR(255) DEFAULT 'New Campaign',
    status ENUM('active', 'completed', 'abandoned') DEFAULT 'active',
    total_input_tokens INT DEFAULT 0,
    total_output_tokens INT DEFAULT 0,
    total_cost_cents INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    INDEX idx_user_active (user_id, status)
);

CREATE TABLE campaign_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,
    cost_cents INT DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES campaign_chats(id) ON DELETE CASCADE,
    INDEX idx_chat_order (chat_id, created_at)
);

CREATE TABLE campaign_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL,
    budget_type ENUM('organic', 'boost', 'ad') NOT NULL,
    amount_cents INT DEFAULT 0,
    spent_cents INT DEFAULT 0,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);
```

### Credit Wallet (Prepaid AI Credits)

```sql
CREATE TABLE credit_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance_cents INT DEFAULT 0,
    total_purchased_cents INT DEFAULT 0,
    total_spent_cents INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    type ENUM('topup', 'deduction', 'refund', 'bonus') NOT NULL,
    amount_cents INT NOT NULL,
    balance_after_cents INT NOT NULL,
    description VARCHAR(500) NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    stripe_payment_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES credit_wallets(id) ON DELETE CASCADE,
    INDEX idx_wallet_time (wallet_id, created_at)
);

CREATE TABLE credit_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price_cents INT NOT NULL,
    credit_cents INT NOT NULL,
    bonus_cents INT DEFAULT 0,
    stripe_price_id VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0
);
```

### Analytics

```sql
CREATE TABLE post_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_platform_id INT NOT NULL,
    impressions INT DEFAULT 0,
    reach INT DEFAULT 0,
    engagements INT DEFAULT 0,
    likes INT DEFAULT 0,
    comments INT DEFAULT 0,
    shares INT DEFAULT 0,
    saves INT DEFAULT 0,
    clicks INT DEFAULT 0,
    video_views INT DEFAULT 0,
    engagement_rate DECIMAL(5,2) DEFAULT 0,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_platform_id) REFERENCES post_platforms(id) ON DELETE CASCADE,
    INDEX idx_platform_time (post_platform_id, fetched_at)
);

CREATE TABLE hashtag_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    hashtag VARCHAR(255) NOT NULL,
    platform VARCHAR(50) NOT NULL,
    post_count BIGINT DEFAULT 0,
    avg_engagement DECIMAL(5,2) DEFAULT 0,
    growth_rate DECIMAL(6,2) DEFAULT 0,
    sentiment_score DECIMAL(3,2) DEFAULT 0,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_hashtag (user_id, hashtag)
);

CREATE TABLE stream_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    social_account_id INT NOT NULL,
    stream_type ENUM('mention', 'comment', 'keyword', 'reply') NOT NULL,
    platform VARCHAR(50) NOT NULL,
    author_name VARCHAR(255) NULL,
    author_avatar VARCHAR(500) NULL,
    content TEXT NULL,
    platform_item_id VARCHAR(255) NOT NULL,
    platform_url VARCHAR(1000) NULL,
    parent_post_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_replied BOOLEAN DEFAULT FALSE,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    item_created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, fetched_at)
);
```

### Billing & Coupons

```sql
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    team_id INT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    plan ENUM('free', 'pro', 'team') DEFAULT 'free',
    status ENUM('active', 'past_due', 'cancelled', 'trialing') DEFAULT 'active',
    trial_ends_at TIMESTAMP NULL,
    current_period_start TIMESTAMP NULL,
    current_period_end TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (team_id) REFERENCES teams(id),
    INDEX idx_stripe_customer (stripe_customer_id)
);

CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    stripe_coupon_id VARCHAR(255) NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    duration ENUM('once', 'repeating', 'forever') DEFAULT 'once',
    duration_months INT NULL,
    applies_to_plans JSON NULL,
    max_redemptions INT NULL,
    times_redeemed INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    description VARCHAR(500) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_code (code),
    INDEX idx_active_expiry (is_active, expires_at)
);

CREATE TABLE coupon_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    subscription_id INT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    UNIQUE KEY uk_coupon_user (coupon_id, user_id)
);

CREATE TABLE usage_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    period_start DATE NOT NULL,
    posts_scheduled INT DEFAULT 0,
    posts_published INT DEFAULT 0,
    ai_generations INT DEFAULT 0,
    platforms_connected INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY uk_user_period (user_id, period_start)
);
```

### AI Features

```sql
CREATE TABLE ai_generations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    generation_type ENUM('create', 'optimise', 'repurpose', 'hashtag_suggest', 'caption', 'thread', 'score') NOT NULL,
    input_text TEXT NULL,
    output_text TEXT NOT NULL,
    platform VARCHAR(50) NULL,
    model_used VARCHAR(100) DEFAULT 'claude-sonnet-4-5-20250929',
    tokens_used INT DEFAULT 0,
    applied_to_post_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_type (user_id, generation_type)
);
```

---

## 3. Plan Limits

| Feature | Free | Pro (€15/mo) | Team (€39/mo) |
|---------|------|-------------|---------------|
| Scheduled posts/month | 15 | Unlimited | Unlimited |
| Social accounts | 2 | 5 | 15 |
| AI generations/month | 10 | 100 | 500 |
| Team members | 1 | 1 | 10 |
| Bulk CSV import | No | Yes | Yes |
| Content recycling | No | Yes | Yes |
| Streams | No | Yes | Yes |
| Approval workflows | No | No | Yes |
| Analytics history | 7 days | 90 days | 1 year |
| Campaign Planner | Prepaid credits | Prepaid credits | Prepaid credits |

### Credit Packages (Campaign Planner)

| Package | Price | Credits | Bonus | Effective rate |
|---------|-------|---------|-------|----------------|
| Starter | €10 | €10 | — | €1 = €1 |
| Builder | €25 | €27 | €2 | €1 = €1.08 |
| Growth | €50 | €55 | €5 | €1 = €1.10 |
| Scale | €100 | €115 | €15 | €1 = €1.15 |

Estimated cost per campaign chat message: €0.01-0.02 (2-4x markup on Claude API cost).
A €50 credit buys approximately 100-150 full campaign planning sessions.

---

## 4. API Endpoints

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Email registration |
| POST | `/api/auth/login` | Email login → JWT |
| GET | `/api/auth/google` | Google OAuth redirect |
| GET | `/api/auth/google/callback` | Google OAuth callback |
| GET | `/api/auth/facebook` | Facebook OAuth redirect |
| GET | `/api/auth/facebook/callback` | Facebook OAuth callback |
| POST | `/api/auth/refresh` | Refresh JWT |
| POST | `/api/auth/logout` | Invalidate refresh token |
| POST | `/api/auth/forgot-password` | Send reset email |
| POST | `/api/auth/reset-password` | Reset with token |

### Onboarding
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/onboarding/status` | Get progress |
| POST | `/api/onboarding/profile` | Save profile + timezone |
| POST | `/api/onboarding/connect` | Initiate social connection |
| POST | `/api/onboarding/plan` | Select plan |
| POST | `/api/onboarding/complete` | Mark done |

### Posts
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/posts` | List (filterable: status, platform, date range, campaign) |
| GET | `/api/posts/{id}` | Single post with platform statuses |
| POST | `/api/posts` | Create (draft or scheduled) |
| PUT | `/api/posts/{id}` | Update |
| DELETE | `/api/posts/{id}` | Delete |
| POST | `/api/posts/{id}/duplicate` | Clone as draft |
| POST | `/api/posts/{id}/reschedule` | Change time |
| POST | `/api/posts/bulk` | CSV import |
| GET | `/api/posts/calendar` | Grouped by date |

### Platforms
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/platforms` | List connected accounts |
| GET | `/api/platforms/connect/{platform}` | Start OAuth |
| GET | `/api/platforms/callback/{platform}` | OAuth callback |
| DELETE | `/api/platforms/{id}` | Disconnect |
| POST | `/api/platforms/{id}/refresh` | Force token refresh |

### Analytics
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/analytics/overview` | Dashboard summary |
| GET | `/api/analytics/posts` | Per-post performance |
| GET | `/api/analytics/platforms` | Platform comparison |
| GET | `/api/analytics/hashtags` | Hashtag performance |
| GET | `/api/analytics/best-times` | Optimal times |

### AI Tools (Plan-limited)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/ai/create` | Generate post from topic |
| POST | `/api/ai/optimise` | Improve content |
| POST | `/api/ai/repurpose` | Adapt for platform |
| POST | `/api/ai/hashtags` | Suggest hashtags |
| POST | `/api/ai/thread` | Long content → thread |
| POST | `/api/ai/caption` | Image/video caption |
| POST | `/api/ai/score` | Engagement prediction |

### Campaign Planner (Credit-funded)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/campaigns` | List campaigns |
| GET | `/api/campaigns/{id}` | Campaign detail |
| POST | `/api/campaigns` | Create from chat plan |
| PUT | `/api/campaigns/{id}` | Update |
| POST | `/api/campaigns/{id}/deploy` | Create all posts in queue |
| DELETE | `/api/campaigns/{id}` | Delete |
| GET | `/api/campaigns/chats` | List planner chats |
| POST | `/api/campaigns/chats` | Start new chat |
| GET | `/api/campaigns/chats/{id}` | Chat history |
| POST | `/api/campaigns/chats/{id}/message` | Send message (with pre-flight cost check) |
| POST | `/api/campaigns/chats/{id}/generate-plan` | Generate structured plan |

### Credits
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/credits/balance` | Current balance |
| GET | `/api/credits/transactions` | Transaction history |
| GET | `/api/credits/packages` | Available packages |
| POST | `/api/credits/purchase` | Create Stripe checkout for credits |
| POST | `/api/credits/estimate` | Pre-flight cost estimate |

### Billing & Coupons
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/billing/status` | Plan + usage |
| POST | `/api/billing/checkout` | Stripe checkout session |
| POST | `/api/billing/portal` | Stripe customer portal |
| POST | `/api/billing/webhook` | Stripe webhook |
| POST | `/api/billing/apply-coupon` | Apply coupon code |
| GET | `/api/billing/validate-coupon/{code}` | Check coupon validity |
| GET | `/api/admin/coupons` | List coupons (admin) |
| POST | `/api/admin/coupons` | Create coupon (admin) |
| PUT | `/api/admin/coupons/{id}` | Update coupon (admin) |
| DELETE | `/api/admin/coupons/{id}` | Deactivate coupon (admin) |
| POST | `/api/admin/coupons/bulk` | Generate bulk codes (admin) |

### Streams
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/streams` | List items |
| POST | `/api/streams/{id}/read` | Mark read |
| POST | `/api/streams/{id}/reply` | Reply |

### Teams
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/teams` | List teams |
| POST | `/api/teams` | Create |
| POST | `/api/teams/{id}/invite` | Invite by email |
| PUT | `/api/teams/{id}/members/{userId}` | Change role |
| DELETE | `/api/teams/{id}/members/{userId}` | Remove |

---

## 5. Platform Integration

| Platform | API | Post Endpoint | Key Limits |
|----------|-----|---------------|-----------|
| X/Twitter | X API v2 | `POST /2/tweets` | 280 chars, Basic $100/mo |
| LinkedIn | Marketing API | `POST /ugcPosts` | 3000 chars |
| Instagram | Graph API | `POST /{id}/media` + `media_publish` | Business only, 25/24hr |
| YouTube | Data API v3 | `POST /youtube/v3/videos` with `publishAt` | 10k quota/day |
| Facebook | Graph API | `POST /{page-id}/feed` | Pages only |
| Threads | Threads API | `POST /{id}/threads` + `threads_publish` | 500 chars |
| TikTok | Content Posting API | Video only | Requires app review |

---

## 6. Security

- OAuth tokens encrypted with AES-256-CBC via `openssl_encrypt`
- JWT: 15-min access + 7-day refresh tokens
- CSRF on all state-changing endpoints
- Rate limiting: auth (5/min), AI (per plan), credits (per transaction)
- PDO prepared statements everywhere
- CORS restricted to app domain
- Stripe webhooks signature-verified
- env.php stored OUTSIDE public_html
- HTTPS via Hostinger SSL
- Credit deductions use atomic SQL: `UPDATE ... SET balance = balance - :cost WHERE balance >= :cost`