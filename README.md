# APEX PostMaster

Professional Social Media Management Platform - A Hootsuite alternative built with Vue 3 and PHP.

## Tech Stack

### Frontend
- Vue 3 (Composition API with `<script setup>`)
- PrimeVue 4 (Aura theme)
- Bootstrap 5 (grid utilities only)
- Pinia (state management)
- Vue Router

### Backend
- PHP 8.0+ (no framework, vanilla PHP)
- MySQL 8.0+
- JWT authentication
- Stripe payments

### APIs
- Claude AI (via Anthropic API)
- YouTube Data API v3
- Facebook Graph API v18.0
- Instagram Graph API

## Project Structure

```
ApexPostMaster/
├── frontend/                 # Vue 3 frontend
│   ├── src/
│   │   ├── assets/          # CSS, images
│   │   ├── components/      # Reusable components
│   │   ├── composables/     # Vue composables
│   │   ├── layouts/         # Layout components
│   │   ├── stores/          # Pinia stores
│   │   └── views/           # Page components
│   └── vite.config.js
├── public_html/              # Web root (Hostinger)
│   ├── api/                 # PHP API
│   │   ├── config/          # Configuration
│   │   ├── controllers/     # Route handlers
│   │   ├── cron/           # Cron scripts
│   │   ├── helpers/        # Utilities
│   │   ├── middleware/     # Auth middleware
│   │   ├── services/       # Business logic
│   │   └── index.php       # API router
│   └── app/                 # Built Vue app (after build)
├── database/
│   └── schema.sql           # Database schema
└── .env.example             # Environment template
```

## Setup

### 1. Database
```bash
mysql -u root -p < database/schema.sql
```

### 2. Environment
```bash
cp public_html/.env.example public_html/.env
# Edit .env with your credentials
```

### 3. Frontend Development
```bash
cd frontend
npm install
npm run dev
```

### 4. Build for Production
```bash
cd frontend
npm run build
# Output goes to public_html/app/
```

### 5. Cron Jobs
Add to crontab:
```
* * * * * php /path/to/api/cron/publisher.php
0 * * * * php /path/to/api/cron/analytics.php
*/5 * * * * php /path/to/api/cron/streams.php
0 3 * * * php /path/to/api/cron/cleanup.php
```

## MVP Platforms
- YouTube (community posts)
- Facebook (pages)
- Instagram (business accounts)

## Plans

| Feature | Free | Pro (€15/mo) | Team (€39/mo) |
|---------|------|--------------|---------------|
| Posts/month | 15 | Unlimited | Unlimited |
| Social accounts | 2 | 5 | 15 |
| AI generations | 10 | 100 | 500 |
| Analytics | 7 days | 90 days | 1 year |
| Team members | 1 | 1 | 10 |
| Streams | - | 5 | 20 |
| Bulk import | - | ✓ | ✓ |

## API Endpoints

### Auth
- `POST /api/auth/register` - Register
- `POST /api/auth/login` - Login
- `POST /api/auth/refresh` - Refresh token
- `GET /api/auth/me` - Current user
- `PUT /api/auth/profile` - Update profile

### Posts
- `GET /api/posts` - List posts
- `POST /api/posts` - Create post
- `PUT /api/posts/:id` - Update post
- `DELETE /api/posts/:id` - Delete post
- `POST /api/posts/:id/schedule` - Schedule post
- `POST /api/posts/:id/publish` - Publish now

### Platforms
- `GET /api/platforms` - List connected accounts
- `POST /api/platforms/connect` - Connect account
- `DELETE /api/platforms/:id` - Disconnect

### AI
- `POST /api/ai/create` - Generate post
- `POST /api/ai/optimize` - Optimize post
- `POST /api/ai/hashtags` - Generate hashtags
- `POST /api/ai/score` - Score post

### Campaigns (AI Chat)
- `GET /api/campaigns/chats` - List chats
- `POST /api/campaigns/chats` - Create chat
- `POST /api/campaigns/chats/:id/messages` - Send message

## License

Proprietary - All rights reserved
