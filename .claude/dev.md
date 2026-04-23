# APEX PostMaster — Development Guide for Claude Code

## Execute this entire document as a single build. Read ALL sections before writing any code.

---

## PROJECT OVERVIEW

APEX PostMaster is a social media management platform (Hootsuite competitor). Build the complete Vue 3 SPA frontend and PHP REST API backend. The app runs on Hostinger shared hosting with MySQL.

**Design philosophy:** KISS. Clean, lean, modern 2026 UI. No visual clutter. Generous whitespace. Responsive across desktop, laptop, tablet, and phone. Marketing-grade polish — this sells a paid service.

---

## TECH STACK — DO NOT DEVIATE

### Frontend
- **Vue 3** (Composition API with `<script setup>`) 
- **Vite** for build
- **PrimeVue 4** with **Aura** theme (dark mode default, light mode toggle)
- **Bootstrap 5** for grid system and responsive utilities ONLY (container, row, col, d-none, d-md-block, etc). Do NOT use Bootstrap components — use PrimeVue for all UI components.
- **Pinia** for state management
- **Vue Router 4** for routing
- **Axios** for HTTP
- **Chart.js** via PrimeVue Chart component
- **PrimeIcons** for icons

### Backend
- **PHP 8.1+** — vanilla, no framework. Simple router.
- **MySQL 8** via PDO (prepared statements ONLY)
- **JWT** via `firebase/pap-jwt` (install via composer)
- **Stripe PHP SDK**
- **Anthropic API** via direct HTTP (curl)

### Responsive Breakpoints
- Phone: < 576px (single column, bottom nav)
- Tablet: 576px – 991px (collapsible sidebar, 2-col grids)
- Desktop: ≥ 992px (full sidebar, multi-col layouts)

---

## STEP 1: PROJECT SCAFFOLD

### 1.1 Create Vue Project

```bash
npm create vue@latest APEXPostMaster -- --typescript=no --jsx=no --router --pinia
cd APEXPostMaster
npm install primevue @primevue/themes primeicons
npm install bootstrap
npm install axios
npm install @vueuse/core
```

### 1.2 Configure main.js

```javascript
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import router from './router'
import App from './App.vue'

import 'primeicons/primeicons.css'
import 'bootstrap/dist/css/bootstrap-grid.min.css'
import 'bootstrap/dist/css/bootstrap-utilities.min.css'
import './assets/main.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.app-dark',
    }
  }
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)
app.mount('#app')
```

### 1.3 Global CSS (`src/assets/main.css`)

Design tokens for the entire app. 2026 aesthetic: dark by default, subtle depth, no harsh borders, fluid typography.

```css
:root {
  --ap-bg: #0a0b0f;
  --ap-surface: #13141a;
  --ap-surface-hover: #1a1b23;
  --ap-border: #1e2028;
  --ap-border-hover: #2d2f3a;
  --ap-text: #e4e6eb;
  --ap-text-muted: #7a7f8e;
  --ap-text-dim: #4b4f5e;
  --ap-accent: #6366f1;
  --ap-accent-hover: #818cf8;
  --ap-accent-subtle: rgba(99, 102, 241, 0.12);
  --ap-success: #22c55e;
  --ap-warning: #f59e0b;
  --ap-danger: #ef4444;
  --ap-radius: 12px;
  --ap-radius-sm: 8px;
  --ap-radius-lg: 16px;
  --ap-shadow: 0 1px 3px rgba(0,0,0,0.3);
  --ap-shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
  --ap-transition: 0.2s ease;
  --ap-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --ap-font-mono: 'JetBrains Mono', 'Fira Code', monospace;
}

/* Import fonts */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

body {
  font-family: var(--ap-font);
  background: var(--ap-bg);
  color: var(--ap-text);
  -webkit-font-smoothing: antialiased;
}

/* Override PrimeVue surface colours to match our dark theme */
.app-dark {
  --p-surface-0: #0a0b0f;
  --p-surface-50: #0e0f14;
  --p-surface-100: #13141a;
  --p-surface-200: #1a1b23;
  --p-surface-300: #24262f;
  --p-surface-400: #2d2f3a;
  --p-surface-500: #4b4f5e;
  --p-surface-600: #7a7f8e;
  --p-surface-700: #9ca0ae;
  --p-surface-800: #c4c7d0;
  --p-surface-900: #e4e6eb;
  --p-primary-color: var(--ap-accent);
}

/* Utility classes */
.hpCard {
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  padding: 1.5rem;
  transition: border-color var(--ap-transition);
}
.hpCard:hover { border-color: var(--ap-border-hover); }

.hpMetric {
  font-family: var(--ap-font-mono);
  font-weight: 600;
  font-size: 2rem;
  line-height: 1;
}

.hpLabel {
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--ap-text-muted);
}

.hpMono { font-family: var(--ap-font-mono); }

.hpFadeIn { animation: hpFadeIn 0.3s ease; }
@keyframes hpFadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* Platform colours — use as CSS classes */
.platform-twitter { --platform-color: #1d9bf0; }
.platform-linkedin { --platform-color: #0a66c2; }
.platform-instagram { --platform-color: #e1306c; }
.platform-youtube { --platform-color: #ff0000; }
.platform-facebook { --platform-color: #1877f2; }
.platform-threads { --platform-color: #999999; }
.platform-tiktok { --platform-color: #00f2ea; }

/* Responsive sidebar */
@media (max-width: 991px) {
  .hpSidebar { display: none; }
  .hpSidebar.open { display: flex; position: fixed; z-index: 100; inset: 0; background: var(--ap-bg); }
  .hpBottomNav { display: flex !important; }
}
@media (min-width: 992px) {
  .hpSidebar { display: flex !important; }
  .hpBottomNav { display: none !important; }
}

/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-thumb { background: var(--ap-border); border-radius: 3px; }
::-webkit-scrollbar-track { background: transparent; }
```

---

## STEP 2: FILE STRUCTURE

Create ALL these files. Every file must be a real, working implementation — no placeholders, no TODOs.

```
src/
├── main.js
├── App.vue
├── assets/
│   └── main.css
├── router/
│   └── index.js
├── stores/
│   ├── auth.js
│   ├── posts.js
│   ├── platforms.js
│   ├── analytics.js
│   ├── ai.js
│   ├── campaigns.js
│   ├── credits.js
│   └── billing.js
├── composables/
│   ├── useApi.js                ← Axios instance with JWT interceptor + refresh
│   ├── usePlatformLimits.js     ← Character counts, media rules per platform
│   ├── usePermissions.js        ← Plan-based feature gating
│   └── useTheme.js              ← Dark/light toggle
├── layouts/
│   ├── AuthLayout.vue           ← Centered card, brand, no nav
│   ├── OnboardingLayout.vue     ← Full-screen stepper
│   └── DashboardLayout.vue      ← Sidebar + header + content area
├── views/
│   ├── auth/
│   │   ├── LoginView.vue
│   │   ├── RegisterView.vue
│   │   └── ForgotPasswordView.vue
│   ├── onboarding/
│   │   └── OnboardingView.vue
│   ├── dashboard/
│   │   └── DashboardView.vue
│   ├── composer/
│   │   └── ComposerView.vue
│   ├── calendar/
│   │   └── CalendarView.vue
│   ├── queue/
│   │   └── QueueView.vue
│   ├── analytics/
│   │   ├── OverviewView.vue
│   │   ├── HashtagView.vue
│   │   └── PlatformView.vue
│   ├── streams/
│   │   └── StreamsView.vue
│   ├── campaigns/
│   │   ├── CampaignListView.vue
│   │   └── CampaignPlannerView.vue
│   ├── ai/
│   │   └── AIToolsView.vue
│   ├── settings/
│   │   ├── ProfileView.vue
│   │   ├── PlatformsView.vue
│   │   ├── BillingView.vue
│   │   ├── CreditsView.vue
│   │   ├── CouponsView.vue       ← Admin only
│   │   └── TeamView.vue
│   └── bulk/
│       └── BulkImportView.vue
└── components/
    ├── common/
    │   ├── PlatformBadge.vue
    │   ├── StatusBadge.vue
    │   ├── CharCounter.vue
    │   ├── HashtagPill.vue
    │   ├── CreditBalance.vue
    │   └── UpgradePrompt.vue
    ├── composer/
    │   ├── ContentEditor.vue
    │   ├── PlatformSelector.vue
    │   ├── PlatformPreview.vue
    │   ├── MediaUploader.vue
    │   ├── SchedulePicker.vue
    │   ├── UTMBuilder.vue
    │   └── AIAssistPanel.vue
    ├── calendar/
    │   └── CalendarGrid.vue
    ├── queue/
    │   ├── PostCard.vue
    │   └── QueueFilters.vue
    ├── analytics/
    │   ├── MetricCard.vue
    │   ├── EngagementChart.vue
    │   ├── PlatformBreakdown.vue
    │   ├── HashtagTable.vue
    │   └── PostingHeatmap.vue
    ├── campaigns/
    │   ├── ChatMessage.vue
    │   ├── ChatInput.vue
    │   ├── CampaignPlan.vue
    │   └── CostIndicator.vue
    ├── streams/
    │   ├── StreamColumn.vue
    │   └── StreamItem.vue
    └── ai/
        ├── AICreatePost.vue
        ├── AIOptimise.vue
        ├── AIRepurpose.vue
        ├── AIHashtagSuggest.vue
        └── AIThreadMaker.vue
```

---

## STEP 3: ROUTING

```javascript
// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  // Auth (no sidebar)
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { layout: 'auth', guest: true } },
  { path: '/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { layout: 'auth', guest: true } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { layout: 'auth', guest: true } },

  // Onboarding
  { path: '/onboarding', name: 'onboarding', component: () => import('@/views/onboarding/OnboardingView.vue'), meta: { layout: 'onboarding', requiresAuth: true } },

  // Dashboard (sidebar layout)
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', name: 'dashboard', component: () => import('@/views/dashboard/DashboardView.vue'), meta: { requiresAuth: true } },
  { path: '/compose', name: 'compose', component: () => import('@/views/composer/ComposerView.vue'), meta: { requiresAuth: true } },
  { path: '/compose/:id', name: 'compose-edit', component: () => import('@/views/composer/ComposerView.vue'), meta: { requiresAuth: true } },
  { path: '/calendar', name: 'calendar', component: () => import('@/views/calendar/CalendarView.vue'), meta: { requiresAuth: true } },
  { path: '/queue', name: 'queue', component: () => import('@/views/queue/QueueView.vue'), meta: { requiresAuth: true } },
  { path: '/analytics', name: 'analytics', component: () => import('@/views/analytics/OverviewView.vue'), meta: { requiresAuth: true } },
  { path: '/analytics/hashtags', name: 'analytics-hashtags', component: () => import('@/views/analytics/HashtagView.vue'), meta: { requiresAuth: true } },
  { path: '/analytics/platforms', name: 'analytics-platforms', component: () => import('@/views/analytics/PlatformView.vue'), meta: { requiresAuth: true } },
  { path: '/streams', name: 'streams', component: () => import('@/views/streams/StreamsView.vue'), meta: { requiresAuth: true, plan: 'pro' } },
  { path: '/campaigns', name: 'campaigns', component: () => import('@/views/campaigns/CampaignListView.vue'), meta: { requiresAuth: true } },
  { path: '/campaigns/planner', name: 'campaign-planner', component: () => import('@/views/campaigns/CampaignPlannerView.vue'), meta: { requiresAuth: true } },
  { path: '/campaigns/planner/:chatId', name: 'campaign-planner-chat', component: () => import('@/views/campaigns/CampaignPlannerView.vue'), meta: { requiresAuth: true } },
  { path: '/ai', name: 'ai-tools', component: () => import('@/views/ai/AIToolsView.vue'), meta: { requiresAuth: true } },
  { path: '/bulk-import', name: 'bulk-import', component: () => import('@/views/bulk/BulkImportView.vue'), meta: { requiresAuth: true, plan: 'pro' } },
  { path: '/settings', name: 'settings', component: () => import('@/views/settings/ProfileView.vue'), meta: { requiresAuth: true } },
  { path: '/settings/platforms', name: 'settings-platforms', component: () => import('@/views/settings/PlatformsView.vue'), meta: { requiresAuth: true } },
  { path: '/settings/billing', name: 'settings-billing', component: () => import('@/views/settings/BillingView.vue'), meta: { requiresAuth: true } },
  { path: '/settings/credits', name: 'settings-credits', component: () => import('@/views/settings/CreditsView.vue'), meta: { requiresAuth: true } },
  { path: '/settings/coupons', name: 'settings-coupons', component: () => import('@/views/settings/CouponsView.vue'), meta: { requiresAuth: true, admin: true } },
  { path: '/settings/team', name: 'settings-team', component: () => import('@/views/settings/TeamView.vue'), meta: { requiresAuth: true, plan: 'team' } },
]

const router = createRouter({ history: createWebHistory('/app/'), routes })

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return next('/login')
  if (to.meta.guest && auth.isAuthenticated) return next('/dashboard')
  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.user.onboarding_completed && to.name !== 'onboarding') return next('/onboarding')
  next()
})

export default router
```

---

## STEP 4: KEY VIEWS — IMPLEMENTATION DETAILS

### 4.1 DashboardLayout.vue

This is the main application shell. It wraps ALL dashboard routes.

**Structure:**
- **Top header bar** (60px): Logo left, search centre (optional), user avatar + credit balance right. On mobile: hamburger menu + logo + compose button.
- **Left sidebar** (240px on desktop, hidden on mobile/tablet): Navigation grouped into sections. Use PrimeVue `Menu` component with custom template.
- **Bottom navigation** (mobile only, 56px): 5 icons — Dashboard, Compose, Calendar, Queue, More (opens sheet with remaining items).
- **Content area**: Scrollable, padded. Uses Bootstrap container-fluid and row/col for responsive grids.

**Sidebar sections:**
1. **Core**: Dashboard, Compose (+ New Post), Calendar, Queue
2. **Intelligence**: Analytics, Hashtags, AI Tools, Campaign Planner
3. **Monitor**: Streams
4. **Settings**: Profile, Platforms, Billing, Credits, Team

Each nav item shows an icon (PrimeIcon) and label. Active state uses `var(--ap-accent-subtle)` background. Queue and Streams show badge counts.

### 4.2 LoginView.vue / RegisterView.vue

Centred card on dark background. Logo at top. Two prominent social login buttons (Google, Facebook) using PrimeVue `Button` with custom styling. Divider line with "or". Email/password form using PrimeVue `InputText` and `Password`. Links between login/register at bottom.

Google button: white background, Google icon, "Continue with Google"
Facebook button: Facebook blue background, FB icon, "Continue with Facebook"

Both trigger `GET /api/auth/{provider}` which returns a redirect URL. After OAuth callback, the API returns JWT tokens and redirects to `/app/onboarding` or `/app/dashboard`.

### 4.3 OnboardingView.vue

Full-screen. Uses PrimeVue `Stepper` with 4 steps:

1. **Welcome** — Name, timezone (PrimeVue `Dropdown` with timezone list, auto-detected), industry (Dropdown: Technology, Marketing, E-commerce, Lifestyle, Education, Health, Finance, Creative, Other). Avatar from OAuth.
2. **Connect Platforms** — Grid of platform cards. Each shows icon, name, "Connect" button. On connect: OAuth popup → callback → card shows green tick + avatar. Minimum 1 required. Skip option with nudge.
3. **Choose Plan** — 3 cards (Free/Pro/Team) using PrimeVue `Card`. Feature comparison. "Start free" prominent. Pro/Team → Stripe checkout. 14-day trial toggle.
4. **First Post** — Mini composer. AI pre-generates a post based on industry. User can edit, pick platform, schedule. "Launch your first post" CTA. Skip option. Confetti on completion.

### 4.4 ComposerView.vue

**Desktop layout** (2 columns):
- Left (8 cols): Content editor, platform previews below
- Right (4 cols): Platform selector, schedule picker, AI assist panel

**Mobile layout** (stacked):
- Content editor full width
- Platform selector (horizontal scroll chips)
- Schedule (collapsed accordion)
- AI panel (floating button → bottom sheet)

**Content Editor** — PrimeVue `Textarea` (not rich Editor — social posts are plain text). Auto-detects hashtags and highlights them in accent colour. Shows extracted hashtags as PrimeVue `Chip` components below.

**Character Counter** — Circular SVG ring showing chars used / limit. Adapts to strictest selected platform. Green → yellow at 70% → red at 90%. Shown beside textarea.

**Platform Selector** — Each platform is a toggleable card showing icon, name, and character limit. Selected state has coloured border (platform colour). Shows warning icon if content exceeds platform limit.

**Platform Previews** — Horizontal scrollable cards (PrimeVue `Carousel` or flex scroll) showing how the post will appear on each selected platform. Platform-specific formatting: Twitter shows thread indicator if >280 chars, LinkedIn shows "...see more" truncation, Instagram shows image placeholder.

**Schedule Picker** — PrimeVue `Calendar` with time picker. "Schedule" vs "Save as Draft" toggle. If no time set, saves as draft.

**AI Assist Panel** — Collapsible panel on right sidebar. Buttons: "Generate", "Optimise", "Suggest Hashtags", "Score Post". Each makes API call to `/api/ai/*`. Results appear inline with "Apply" button to replace/append content. Shows remaining AI generations for the month.

**Media Upload** — PrimeVue `FileUpload` with drag-and-drop. Image preview grid. Max file size based on plan.

### 4.5 CalendarView.vue

Monthly grid using custom component (NOT PrimeVue Calendar — that's a date picker). 

7-column grid. Each cell shows the date number, and posts scheduled for that day as small coloured pills (platform colour dots + truncated content). Click a day → PrimeVue `Dialog` with list of posts for that day, each clickable to edit.

Navigation: month/year with prev/next arrows. "Today" button. PrimeVue `Dropdown` for month jump.

**Responsive:** Desktop = full grid. Tablet = full grid, smaller cells. Phone = list view by day (scroll through dates, each showing posts).

### 4.6 QueueView.vue

**PrimeVue `DataTable`** with:
- Columns: Status (badge), Content (truncated), Platforms (icons), Scheduled (date + relative time), Actions
- Filters: Status dropdown, platform multi-select, date range
- Sorting: by scheduled date (default), status, created date
- Pagination: 20 per page
- Row expansion: click row to see full content, platform-specific statuses, analytics (if published)
- Actions: Edit, Duplicate, Delete, Reschedule

**Filter bar** above table: PrimeVue `SelectButton` for status (All / Scheduled / Published / Drafts / Failed). `MultiSelect` for platforms. `Calendar` range picker for dates.

**Responsive:** Desktop = full table. Tablet = hide some columns. Phone = card layout (each post as a card, stacked vertically).

### 4.7 CampaignPlannerView.vue (Chat Interface)

This is the prepaid AI campaign planner.

**Layout:**
- Left panel (3 cols on desktop): Chat history list. New chat button at top. Each chat shows title + date + cost.
- Right panel (9 cols on desktop): Active chat.

**Chat area:**
- Messages displayed as bubbles. User messages right-aligned (subtle accent background). Assistant messages left-aligned (surface background). Messages support markdown rendering (use a simple markdown parser or v-html with sanitisation).
- **Cost indicator** on each assistant message: tiny chip showing "€0.02" in muted text.
- **Running total** in chat header: "Session cost: €0.34 · Balance: €12.66"

**Chat input:**
- PrimeVue `Textarea` (auto-resize) with send button.
- Before send: pre-flight cost check. If balance too low, show inline warning: "Insufficient credits. Top up to continue." with link to credits page.
- While waiting for response: typing indicator (3 dots animation).
- Disable input while request in flight.

**Campaign plan output:**
When Claude generates a structured plan, render it as a special card within the chat:
- Campaign name, date range, total budget breakdown
- Posts listed by date with platform icons
- "Deploy Campaign" button → creates all posts in queue
- "Edit Plan" → re-enter chat to adjust

**Responsive:** Phone = chat list hidden behind hamburger, full-screen chat view. Same pattern as most messaging apps.

### 4.8 CreditsView.vue

**Balance card** at top: Large number showing current balance. "Top up" button.

**Package grid** (4 cards): Each credit package showing price, credit amount, bonus (if any), effective rate. PrimeVue `Card` with "Buy" button → Stripe checkout.

**Transaction history**: PrimeVue `DataTable` with type (topup/deduction), amount, description, balance after, date. Green for topups, red for deductions.

### 4.9 CouponsView.vue (Admin Only)

Only accessible to admin users.

**Create coupon** form: Code (auto-generate or custom), type (percentage/fixed), value, duration (once/repeating/forever), duration months (if repeating), max redemptions, expiry date, applies to plans.

**Coupon list**: PrimeVue `DataTable` with code, type, value, usage (X/Y), expiry, status (active/expired/depleted), actions (deactivate, copy code).

**Bulk generate**: Dialog with prefix, quantity, same discount params. Generates unique codes. CSV export.

### 4.10 BillingView.vue

**Current plan card**: Plan name, status, renewal date, usage bars (posts used/limit, AI generations used/limit, platforms connected/limit).

**Upgrade cards**: If on free, show Pro + Team. If on Pro, show Team.

**Coupon input**: PrimeVue `InputText` with "Apply" button. Validates via API. Shows discount preview.

**Manage subscription** button → Stripe customer portal (opens in new tab).

**Invoice history**: PrimeVue `DataTable` from Stripe data.

### 4.11 StreamsView.vue

**Column layout** using PrimeVue `Splitter` or CSS grid:
- Desktop: 3 columns (Mentions, Comments, Keywords) — each independently scrollable
- Tablet: 2 columns
- Phone: Single column with PrimeVue `TabView` to switch

Each column has a header (title + unread count) and a list of stream items. Each item shows: author avatar, author name, content, platform badge, timestamp, read/unread indicator, "Reply" button.

### 4.12 AIToolsView.vue

Tab-based layout using PrimeVue `TabView`:

1. **Create** — Topic input + platform select + tone select → Generate button → Output with "Use in Composer" button
2. **Optimise** — Paste content input + platform → Improve → Before/after comparison → Apply
3. **Repurpose** — Content + source platform → target platform checkboxes → Generate per-platform versions
4. **Hashtags** — Content input → grouped suggestions (primary/secondary/niche) as Chips → click to copy
5. **Thread Maker** — Long content paste → numbered tweet outputs → "Schedule as Thread" button
6. **Scorer** — Paste content → radar chart (hook, clarity, engagement, hashtags, CTA) + tips

Each tab shows "X/Y AI generations remaining this month" at the top.

---

## STEP 5: PINIA STORES

### 5.1 auth.js
```
State: user, accessToken, refreshToken, isAuthenticated
Actions: login(email, password), loginWithProvider(provider), register(email, password, name), logout(), refreshAccessToken(), fetchProfile(), updateProfile(data)
Getters: isAuthenticated, isPro, isTeam, isAdmin, currentPlan
```

### 5.2 posts.js
```
State: posts[], currentPost, loading, filters, pagination
Actions: fetchPosts(filters), fetchPost(id), createPost(data), updatePost(id, data), deletePost(id), duplicatePost(id), reschedulePost(id, datetime), bulkImport(file), fetchCalendar(year, month)
Getters: scheduledPosts, publishedPosts, draftPosts, failedPosts, postsForDate(date)
```

### 5.3 platforms.js
```
State: accounts[], loading
Actions: fetchAccounts(), connectPlatform(platform), disconnectPlatform(id), refreshToken(id)
Getters: connectedPlatforms, accountForPlatform(platform), isConnected(platform)
```

### 5.4 analytics.js
```
State: overview, postAnalytics[], hashtagData[], bestTimes, loading
Actions: fetchOverview(dateRange), fetchPostAnalytics(postId), fetchHashtags(), fetchBestTimes(), fetchPlatformComparison()
```

### 5.5 ai.js
```
State: lastGeneration, loading, remainingGenerations
Actions: createPost(topic, platform, tone), optimise(content, platform), repurpose(content, from, to), suggestHashtags(content, platform), generateThread(content), scorePost(content, platform)
```

### 5.6 campaigns.js
```
State: campaigns[], chats[], activeChat, activeChatMessages[], loading, isSending
Actions: fetchCampaigns(), createCampaign(data), deployCampaign(id), fetchChats(), createChat(), fetchChatMessages(chatId), sendMessage(chatId, content), generatePlan(chatId)
```

### 5.7 credits.js
```
State: balance, transactions[], packages[], loading
Actions: fetchBalance(), fetchTransactions(), fetchPackages(), purchasePackage(packageId), estimateCost(messageContent)
Getters: balanceFormatted, hasCredits
```

### 5.8 billing.js
```
State: subscription, usage, invoices[], loading
Actions: fetchStatus(), createCheckout(plan), openPortal(), applyCoupon(code), validateCoupon(code), fetchCoupons(), createCoupon(data), deactivateCoupon(id), bulkGenerateCoupons(data)
Getters: currentPlan, isTrialing, daysRemaining, usagePercentage(metric)
```

---

## STEP 6: COMPOSABLES

### 6.1 useApi.js

Create an Axios instance that:
- Sets `baseURL` to `/api`
- Adds `Authorization: Bearer {token}` header from auth store
- On 401 response: attempts token refresh via `/api/auth/refresh`, retries original request
- On refresh failure: logs out and redirects to login
- Returns `{ data, error, loading }` reactive refs

### 6.2 usePlatformLimits.js

Export a map of platform constraints:
```javascript
export const PLATFORMS = {
  twitter: { name: 'X/Twitter', icon: 'pi pi-twitter', charLimit: 280, mediaTypes: ['image', 'video', 'gif'], maxMedia: 4, color: '#1d9bf0' },
  linkedin: { name: 'LinkedIn', icon: 'pi pi-linkedin', charLimit: 3000, mediaTypes: ['image', 'video', 'document'], maxMedia: 9, color: '#0a66c2' },
  instagram: { name: 'Instagram', icon: 'pi pi-instagram', charLimit: 2200, mediaTypes: ['image', 'video'], maxMedia: 10, color: '#e1306c' },
  youtube: { name: 'YouTube', icon: 'pi pi-youtube', charLimit: 5000, mediaTypes: ['video'], maxMedia: 1, color: '#ff0000' },
  facebook: { name: 'Facebook', icon: 'pi pi-facebook', charLimit: 63206, mediaTypes: ['image', 'video'], maxMedia: 10, color: '#1877f2' },
  threads: { name: 'Threads', icon: 'pi pi-at', charLimit: 500, mediaTypes: ['image', 'video'], maxMedia: 10, color: '#999999' },
  tiktok: { name: 'TikTok', icon: 'pi pi-video', charLimit: 4000, mediaTypes: ['video'], maxMedia: 1, color: '#00f2ea' },
}
```

### 6.3 usePermissions.js

Checks user plan against feature requirements:
```javascript
export function usePermissions() {
  const auth = useAuthStore()
  const canUse = (feature) => { /* check plan limits */ }
  const remainingPosts = computed(() => { /* 15 - used for free, Infinity for pro/team */ })
  const remainingAI = computed(() => { /* 10/100/500 - used */ })
  return { canUse, remainingPosts, remainingAI }
}
```

---

## STEP 7: PHP API

### 7.1 Router (api/index.php)

Simple PHP router. Parse `REQUEST_METHOD` and `REQUEST_URI`. Match against route table. Extract path parameters. Call controller method. Return JSON.

```php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/middleware/CorsMiddleware.php';
CorsMiddleware::handle();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);

// Route table
$routes = [
    'POST /auth/login' => ['AuthController', 'login'],
    'POST /auth/register' => ['AuthController', 'register'],
    'GET /auth/google' => ['AuthController', 'googleRedirect'],
    'GET /auth/google/callback' => ['AuthController', 'googleCallback'],
    'GET /auth/facebook' => ['AuthController', 'facebookRedirect'],
    'GET /auth/facebook/callback' => ['AuthController', 'facebookCallback'],
    'POST /auth/refresh' => ['AuthController', 'refresh'],
    // ... all routes from spec
    'GET /posts' => ['PostController', 'index'],
    'POST /posts' => ['PostController', 'store'],
    'GET /posts/{id}' => ['PostController', 'show'],
    'PUT /posts/{id}' => ['PostController', 'update'],
    'DELETE /posts/{id}' => ['PostController', 'destroy'],
    'POST /campaigns/chats/{id}/message' => ['CampaignController', 'sendMessage'],
    'GET /credits/balance' => ['CreditController', 'balance'],
    'POST /credits/purchase' => ['CreditController', 'purchase'],
    // ... etc
];

// Match and dispatch
foreach ($routes as $route => $handler) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);
    if ($method !== $routeMethod) continue;
    $params = matchPath($routePath, $path);
    if ($params !== false) {
        $controller = new $handler[0]($pdo);
        echo json_encode($controller->{$handler[1]}($params));
        exit;
    }
}
http_response_code(404);
echo json_encode(['error' => 'Not found']);
```

### 7.2 Auth Flow

JWT implementation using `firebase/pap-jwt`. On login/register/OAuth callback:
1. Create/find user in MySQL
2. Generate access token (15 min expiry) with user id, email, plan in payload
3. Generate refresh token (7 day expiry), store hash in refresh_tokens table
4. Return both tokens to frontend

OAuth flow (Google/Facebook):
1. Generate state token, store in session
2. Redirect to provider's auth URL with scopes
3. On callback: verify state, exchange code for tokens
4. Fetch user profile from provider
5. Create/find user by provider + provider_id
6. Issue JWT pair
7. Redirect to `{app_url}/auth/callback?token={access_token}&refresh={refresh_token}`

### 7.3 Campaign Chat Message Handler

```php
// CampaignController::sendMessage
public function sendMessage($params) {
    $chatId = $params['id'];
    $userId = $this->auth->userId();
    $body = json_decode(file_get_contents('php://input'), true);
    $content = $body['content'];

    // 1. Get chat history
    $messages = $this->getChatMessages($chatId);

    // 2. Build Claude messages array
    $claudeMessages = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $messages);
    $claudeMessages[] = ['role' => 'user', 'content' => $content];

    // 3. Estimate cost
    $estimator = new TokenEstimator();
    $estInput = $estimator->countTokens($claudeMessages);
    $maxOutput = 2000;
    $estCostCents = $estimator->calculateCost($estInput, $maxOutput);
    $bufferedCost = (int) ceil($estCostCents * 1.2); // 20% buffer

    // 4. Check wallet
    $wallet = CreditWallet::getByUserId($this->pdo, $userId);
    if ($wallet['balance_cents'] < $bufferedCost) {
        return ['error' => 'insufficient_credits', 'required' => $bufferedCost, 'balance' => $wallet['balance_cents']];
    }

    // 5. Save user message
    $this->saveMessage($chatId, 'user', $content, 0, 0, 0);

    // 6. Call Claude API
    $claude = new ClaudeService();
    $systemPrompt = $this->buildCampaignSystemPrompt($userId);
    $response = $claude->chat($claudeMessages, $systemPrompt, $maxOutput);

    // 7. Calculate actual cost
    $actualInput = $response['usage']['input_tokens'];
    $actualOutput = $response['usage']['output_tokens'];
    $actualCostCents = $estimator->calculateCost($actualInput, $actualOutput);

    // 8. Atomic deduction
    $deducted = $this->deductCredits($wallet['id'], $actualCostCents);
    if (!$deducted) {
        return ['error' => 'deduction_failed'];
    }

    // 9. Save assistant message
    $this->saveMessage($chatId, 'assistant', $response['content'], $actualInput, $actualOutput, $actualCostCents);

    // 10. Update chat totals
    $this->updateChatTotals($chatId, $actualInput, $actualOutput, $actualCostCents);

    // 11. Log transaction
    $this->logTransaction($wallet['id'], 'deduction', $actualCostCents, 'Campaign chat message');

    return [
        'message' => $response['content'],
        'cost_cents' => $actualCostCents,
        'balance_cents' => $wallet['balance_cents'] - $actualCostCents,
        'input_tokens' => $actualInput,
        'output_tokens' => $actualOutput
    ];
}
```

### 7.4 TokenEstimator.php

```php
class TokenEstimator {
    // Sonnet pricing (per million tokens)
    const INPUT_RATE = 3.00;   // $3/M input
    const OUTPUT_RATE = 15.00; // $15/M output
    const MARKUP = 3.0;        // 3x markup

    public function countTokens($messages): int {
        $text = '';
        foreach ($messages as $m) { $text .= $m['content']; }
        // Rough estimate: 1 token ≈ 4 chars for English
        return (int) ceil(strlen($text) / 4);
    }

    public function calculateCost(int $inputTokens, int $outputTokens): int {
        $inputCost = ($inputTokens / 1_000_000) * self::INPUT_RATE;
        $outputCost = ($outputTokens / 1_000_000) * self::OUTPUT_RATE;
        $totalUSD = ($inputCost + $outputCost) * self::MARKUP;
        // Convert to EUR cents (rough 1:1 for simplicity, adjust in config)
        return max(1, (int) ceil($totalUSD * 100));
    }
}
```

### 7.5 Cron Publisher (cron/publisher.php)

See spec document for full logic. Key points:
- Query posts WHERE status='scheduled' AND scheduled_at <= NOW()
- Lock with status='publishing' to prevent duplicates
- Refresh OAuth tokens before posting
- Use platform-specific content variants if available
- Update post_platforms status on success/failure
- 4-minute max execution time
- Process evergreen post recycling

### 7.6 Stripe Integration

- Subscription checkout: create Stripe Checkout Session with plan price, redirect user
- Credit purchase: create Stripe Checkout Session with credit package price, on success webhook, add credits to wallet
- Coupons: create via Stripe API, apply via Stripe Checkout Session `discounts` parameter
- Webhook: handle `checkout.session.completed`, `invoice.paid`, `customer.subscription.updated`, `customer.subscription.deleted`

---

## STEP 8: DESIGN RULES

### Typography
- Body: Inter 400, 14px/1.6
- Headings: Inter 600, use rem (h1: 1.875rem, h2: 1.5rem, h3: 1.25rem)
- Mono (metrics, code, hashtags): JetBrains Mono 500
- Labels: Inter 500, 0.75rem, uppercase, letter-spacing 0.06em

### Colours
- NEVER use pure white text on dark — use #e4e6eb maximum
- NEVER use pure black backgrounds — use #0a0b0f
- Accent colour is indigo #6366f1, use sparingly (buttons, active states, links)
- Platform colours for platform-specific UI only, never for general decoration
- Success/warning/danger only for status indicators, never decorative

### Spacing
- Use 4px base grid: 4, 8, 12, 16, 24, 32, 48, 64
- Cards: 24px padding (16px on mobile)
- Section gaps: 24px (16px on mobile)
- Generous whitespace. When in doubt, add more space.

### Borders & Shadows
- Borders: 1px solid var(--ap-border), never more than 1px
- Border radius: 12px for cards, 8px for inputs/buttons, 16px for modals
- Shadows: minimal. Only on elevated elements (modals, dropdowns, toasts)
- NO gradient borders, NO glow effects, NO neon

### Motion
- Transitions: 0.2s ease for all interactive states
- Page transitions: simple fade (0.3s)
- Loading: PrimeVue Skeleton components, never spinners except inline buttons
- Toast notifications: slide in from top-right

### Responsive
- Use Bootstrap grid (container-fluid, row, col-*) for layout
- Use Bootstrap display utilities (d-none, d-md-block) for showing/hiding
- Mobile: single column, bottom nav, full-width cards
- Tablet: 2 columns, collapsible sidebar
- Desktop: sidebar + multi-column content
- Touch targets: minimum 44px on mobile
- Test: 320px (small phone), 768px (tablet), 1024px (small laptop), 1440px (desktop)

### KISS Principles
- One action per view. The composer composes. The queue queues.
- No feature should require more than 2 clicks to reach
- Empty states: always show a helpful message + primary action
- Loading states: always show skeleton placeholders, never blank screens
- Error states: always show what went wrong + how to fix it
- No tooltips for essential information — if it needs explaining, redesign it

---

## STEP 9: BUILD ORDER

Execute in this exact order:

1. Vue scaffold + PrimeVue + Bootstrap + CSS variables + fonts
2. Router + layouts (Auth, Onboarding, Dashboard)
3. Auth store + useApi composable
4. Login + Register views (functional with API stubs)
5. DashboardLayout with sidebar + header + responsive nav
6. DashboardView with metric cards (mock data)
7. ComposerView with editor + platform selector + char counter + previews
8. QueueView with DataTable
9. CalendarView with monthly grid
10. Posts store + full CRUD wiring
11. Platforms store + PlatformsView
12. AI store + AIToolsView (all 6 tabs)
13. AIAssistPanel in composer
14. Campaign store + CampaignPlannerView (chat UI)
15. Credits store + CreditsView + CostIndicator
16. Billing store + BillingView
17. CouponsView (admin)
18. OnboardingView (4-step stepper)
19. Analytics views (overview, hashtags, platforms)
20. StreamsView
21. BulkImportView
22. Settings views (profile, team)
23. PHP API: router + auth endpoints
24. PHP API: post CRUD + platforms
25. PHP API: AI endpoints + Claude service
26. PHP API: campaign chat + credit system
27. PHP API: billing + coupons + Stripe
28. PHP API: cron scripts
29. Responsive polish pass
30. Final integration test

---

## CRITICAL RULES

1. **Every view MUST work.** No placeholder components. No "TODO" comments. Real UI with mock data where APIs aren't ready.
2. **PrimeVue for ALL UI components.** Bootstrap for grid ONLY. Never mix Bootstrap buttons/forms with PrimeVue.
3. **Composition API with `<script setup>` everywhere.** No Options API.
4. **Dark theme by default.** Light theme via toggle (class swap).
5. **Mobile-first responsive.** Every view must work at 320px width.
6. **No unused imports, no console.logs in final code.**
7. **All money in integer cents.** Never use float for money.
8. **PDO prepared statements only.** Never concatenate SQL.
9. **JWT in memory (Pinia), refresh token in httpOnly cookie ideally, or localStorage as fallback.**
10. **Error boundaries everywhere.** Every API call has loading + error state.
11. **Database name is `apex-postmaster`.** Use this for all local and Hostinger MySQL connections. Configure in `config/database.php`.
12. **Ask when uncertain.** If you are unsure about a design decision, implementation detail, business rule, or anything else — stop and ask the user. Do not guess or assume. Ask clearly, get confirmation, then proceed.
13. **Complexity check.** If a feature or solution requires a large amount of text to explain, it is probably too complex. Step back and find a simpler alternative. Simpler code is better code. If you cannot explain the approach in 2-3 sentences, simplify it.
14. **camelCase everywhere.** Use camelCase for all naming: JavaScript/Vue variables, functions, props, emits, store actions, composable names, CSS class names (e.g. `.hpCard`, `.metricCard`), PHP variables, PHP method names, JSON API response keys, and MySQL column aliases in API responses. The ONLY exceptions are: MySQL table and column names (use snake_case as defined in the schema), PHP class names (use PascalCase), and Vue component file names (use PascalCase e.g. `PostCard.vue`). When returning MySQL data to the frontend via the API, convert snake_case column names to camelCase in the JSON response.