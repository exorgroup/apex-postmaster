import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  // Landing page (public)
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/LandingView.vue'),
    meta: { layout: 'none', public: true }
  },

  // Auth routes (no sidebar)
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { layout: 'auth', guest: true }
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { layout: 'auth', guest: true }
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { layout: 'auth', guest: true }
  },
  {
    path: '/auth/callback/:provider',
    name: 'oauth-callback',
    component: () => import('@/views/auth/OAuthCallbackView.vue'),
    meta: { layout: 'none', public: true }
  },

  // Onboarding
  {
    path: '/onboarding',
    name: 'onboarding',
    component: () => import('@/views/onboarding/OnboardingView.vue'),
    meta: { layout: 'onboarding', requiresAuth: true }
  },

  // Home redirect for authenticated users
  {
    path: '/home',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/dashboard/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/compose',
    name: 'compose',
    component: () => import('@/views/composer/ComposerView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/compose/:id',
    name: 'compose-edit',
    component: () => import('@/views/composer/ComposerView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: () => import('@/views/calendar/CalendarView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/queue',
    name: 'queue',
    component: () => import('@/views/queue/QueueView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/analytics',
    name: 'analytics',
    component: () => import('@/views/analytics/OverviewView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/analytics/hashtags',
    name: 'analytics-hashtags',
    component: () => import('@/views/analytics/HashtagView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/analytics/platforms',
    name: 'analytics-platforms',
    component: () => import('@/views/analytics/PlatformView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/streams',
    name: 'streams',
    component: () => import('@/views/streams/StreamsView.vue'),
    meta: { requiresAuth: true, plan: 'pro' }
  },
  {
    path: '/campaigns',
    name: 'campaigns',
    component: () => import('@/views/campaigns/CampaignListView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/campaigns/planner',
    name: 'campaign-planner',
    component: () => import('@/views/campaigns/CampaignPlannerView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/campaigns/planner/:chatId',
    name: 'campaign-planner-chat',
    component: () => import('@/views/campaigns/CampaignPlannerView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/ai',
    name: 'ai-tools',
    component: () => import('@/views/ai/AIToolsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/bulk-import',
    name: 'bulk-import',
    component: () => import('@/views/bulk/BulkImportView.vue'),
    meta: { requiresAuth: true, plan: 'pro' }
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/settings/ProfileView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/platforms',
    name: 'settings-platforms',
    component: () => import('@/views/settings/PlatformsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/billing',
    name: 'settings-billing',
    component: () => import('@/views/settings/BillingView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/credits',
    name: 'settings-credits',
    component: () => import('@/views/settings/CreditsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/coupons',
    name: 'settings-coupons',
    component: () => import('@/views/settings/CouponsView.vue'),
    meta: { requiresAuth: true, admin: true }
  },
  {
    path: '/settings/team',
    name: 'settings-team',
    component: () => import('@/views/settings/TeamView.vue'),
    meta: { requiresAuth: true, plan: 'team' }
  },

  // Public pages
  {
    path: '/contact',
    name: 'contact',
    component: () => import('@/views/ContactView.vue'),
    meta: { layout: 'none', public: true }
  },
  {
    path: '/integrations',
    name: 'integrations',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'Integrations', description: 'Discover powerful integrations coming soon to APEX PostMaster.' }
  },
  {
    path: '/changelog',
    name: 'changelog',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'Changelog', description: 'Stay updated with our latest features and improvements.' }
  },
  {
    path: '/blog',
    name: 'blog',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'Blog', description: 'Insights, tips, and updates from the APEX PostMaster team.' }
  },
  {
    path: '/help',
    name: 'help',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'Help Center', description: 'Our comprehensive help center is coming soon to assist you.' }
  },
  {
    path: '/api-docs',
    name: 'api-docs',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'API Documentation', description: 'Developer documentation for our API is coming soon.' }
  },
  {
    path: '/status',
    name: 'status',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'System Status', description: 'Real-time system status monitoring coming soon.' }
  },
  {
    path: '/about',
    name: 'about',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'About Us', description: 'Learn more about APEX PostMaster and our mission.' }
  },
  {
    path: '/careers',
    name: 'careers',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'Careers', description: 'Join our team! Career opportunities coming soon.' }
  },
  {
    path: '/press',
    name: 'press',
    component: () => import('@/views/PlaceholderView.vue'),
    meta: { layout: 'none', public: true },
    props: { title: 'Press', description: 'Media resources and press information.' }
  },

  // Legal pages
  {
    path: '/privacy-policy',
    name: 'privacy-policy',
    component: () => import('@/views/legal/PrivacyPolicyView.vue'),
    meta: { layout: 'none', public: true }
  },
  {
    path: '/terms-of-service',
    name: 'terms-of-service',
    component: () => import('@/views/legal/TermsOfServiceView.vue'),
    meta: { layout: 'none', public: true }
  },
  {
    path: '/cookie-policy',
    name: 'cookie-policy',
    component: () => import('@/views/legal/CookiePolicyView.vue'),
    meta: { layout: 'none', public: true }
  },
  {
    path: '/gdpr',
    name: 'gdpr',
    component: () => import('@/views/legal/GDPRView.vue'),
    meta: { layout: 'none', public: true }
  }
]

const router = createRouter({
  history: createWebHistory('/app/'),
  routes
})

let authInitialized = false

router.beforeEach(async (to, from, next) => {
  const { useAuthStore } = await import('@/stores/auth')
  const auth = useAuthStore()

  // Initialize auth on first navigation (restore session from localStorage)
  if (!authInitialized) {
    authInitialized = true
    if (auth.accessToken && !auth.user) {
      await auth.initialize()
    }
  }

  // Public routes are always accessible
  if (to.meta.public) {
    // Redirect authenticated users from landing to dashboard
    if (to.name === 'landing' && auth.isAuthenticated) {
      return next('/dashboard')
    }
    return next()
  }

  // Check if route requires authentication
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next('/login')
  }

  // Redirect authenticated users away from guest-only routes
  if (to.meta.guest && auth.isAuthenticated) {
    return next('/dashboard')
  }

  // Redirect to onboarding if not completed
  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.user?.onboardingCompleted && to.name !== 'onboarding') {
    return next('/onboarding')
  }

  next()
})

export default router
