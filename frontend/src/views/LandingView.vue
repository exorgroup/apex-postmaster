<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const router = useRouter()
const mobileMenuOpen = ref(false)
const openFaqIndex = ref(null)
const planData = ref(null)
const billingPeriod = ref('monthly') // 'monthly' or 'annual'
const showVat = ref(false) // false = excl. VAT, true = incl. VAT

// Fetch plan limits from API
onMounted(async () => {
  try {
    const response = await fetch('/api/config/plans')
    const data = await response.json()
    if (data.success) {
      planData.value = data.data
    }
  } catch (error) {
    console.error('Failed to fetch plan limits:', error)
  }
})

// VAT rate from API
const vatRate = computed(() => {
  return planData.value?.vat?.rate ?? 18
})

// Helper to format price (cents to euros), optionally with VAT
const formatPrice = (cents, applyVat = false) => {
  let price = cents / 100
  if (applyVat) {
    price = price * (1 + vatRate.value / 100)
  }
  // Show decimal only if needed
  return price % 1 === 0 ? price.toFixed(0) : price.toFixed(2)
}

// Helper to format monthly price from annual (for display)
const formatMonthlyFromAnnual = (annualCents, applyVat = false) => {
  let price = annualCents / 100 / 12
  if (applyVat) {
    price = price * (1 + vatRate.value / 100)
  }
  return price % 1 === 0 ? price.toFixed(0) : price.toFixed(2)
}

// Helper to format limit values
const formatLimit = (value, suffix = '') => {
  if (value === -1) return 'Unlimited'
  if (value === 0) return null
  return value + suffix
}

// Helper to format analytics days
const formatAnalyticsDays = (days) => {
  if (days === -1) return 'Unlimited analytics history'
  if (days >= 365) return '1-year analytics history'
  if (days >= 30) return `${Math.round(days / 30)}-month analytics history`
  return `${days}-day analytics history`
}

const toggleFaq = (index) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}

const features = [
  {
    icon: 'pi-calendar',
    title: 'Smart Scheduling',
    description: 'Plan and schedule posts across all platforms with our intuitive calendar interface.'
  },
  {
    icon: 'pi-sparkles',
    title: 'AI-Powered Content',
    description: 'Generate engaging posts, optimize content, and discover trending hashtags with AI.'
  },
  {
    icon: 'pi-chart-line',
    title: 'Deep Analytics',
    description: 'Track performance, measure engagement, and understand your audience growth.'
  },
  {
    icon: 'pi-users',
    title: 'Team Collaboration',
    description: 'Work together with approval workflows, shared calendars, and role-based access.'
  },
  {
    icon: 'pi-bolt',
    title: 'Bulk Publishing',
    description: 'Import and schedule hundreds of posts at once with CSV bulk upload.'
  },
  {
    icon: 'pi-eye',
    title: 'Social Listening',
    description: 'Monitor mentions, track hashtags, and stay on top of industry conversations.'
  }
]

const platforms = [
  { name: 'YouTube', icon: 'pi-youtube' },
  { name: 'Facebook', icon: 'pi-facebook' },
  { name: 'Instagram', icon: 'pi-instagram' }
]

const stats = [
  { value: '10M+', label: 'Posts Scheduled' },
  { value: '50K+', label: 'Active Users' },
  { value: '99.9%', label: 'Uptime' },
  { value: '4.8★', label: 'User Rating' }
]

const testimonials = [
  {
    quote: "APEX PostMaster transformed how we manage social media. The AI tools alone save us 10+ hours per week.",
    author: "Sarah Chen",
    role: "Marketing Director",
    company: "TechFlow Inc"
  },
  {
    quote: "Finally, a social media tool that doesn't require a PhD to use. Clean, fast, and incredibly powerful.",
    author: "Marcus Johnson",
    role: "Social Media Manager",
    company: "Bloom Agency"
  },
  {
    quote: "The campaign planner is genius. It's like having a social media strategist on demand.",
    author: "Emily Rodriguez",
    role: "Content Creator",
    company: "Lifestyle Daily"
  }
]

// Check if annual billing is enabled
const annualBillingEnabled = computed(() => {
  return planData.value?.annual_billing_enabled ?? true
})

// Helper to get price based on billing period and VAT
const getPrice = (plan) => {
  if (!plan) return '0'
  if (plan.price_monthly === 0) return '0'
  if (billingPeriod.value === 'annual' && plan.price_annual > 0) {
    return formatMonthlyFromAnnual(plan.price_annual, showVat.value)
  }
  return formatPrice(plan.price_monthly, showVat.value)
}

// Helper to get seat price
const getSeatPrice = (plan) => {
  if (!plan || !plan.seat_price) return null
  if (billingPeriod.value === 'annual') {
    // Apply ~20% discount for annual seat pricing
    const annualSeatPrice = plan.seat_price * 0.8
    return formatPrice(annualSeatPrice, showVat.value)
  }
  return formatPrice(plan.seat_price, showVat.value)
}

// Helper to build features list
const buildFeatures = (plan, planKey) => {
  const features = []

  // Posts
  const posts = formatLimit(plan.posts_per_month, ' posts/month')
  if (posts) features.push(posts)

  // Social accounts
  if (plan.social_accounts > 0) {
    features.push(`${plan.social_accounts} social accounts`)
  }

  // AI generations
  if (plan.ai_generations > 0) {
    features.push(`${plan.ai_generations} AI generations/month`)
  }

  // AI credits
  if (plan.ai_credits_monthly > 0) {
    features.push(`${plan.ai_credits_monthly} AI credits/month`)
  }

  // Analytics
  features.push(formatAnalyticsDays(plan.analytics_days))

  // Streams
  if (plan.streams > 0) {
    features.push(`${plan.streams} social streams`)
  }

  // Included seats
  if (plan.included_seats > 1) {
    features.push(`${plan.included_seats} team seats included`)
  } else if (plan.included_seats === 1 && plan.max_seats > 1) {
    features.push(`1 seat included`)
  }

  // Boolean features
  if (plan.bulk_import) features.push('Bulk import')
  if (plan.recycling) features.push('Content recycling')
  if (plan.approval) features.push('Approval workflows')

  return features
}

// Computed plans based on API response
const plans = computed(() => {
  const data = planData.value

  // Default values if API hasn't loaded yet
  if (!data?.plans) {
    return [
      { name: 'Free', price: '0', period: 'forever', description: 'Perfect for getting started', features: ['Loading...'], cta: 'Get Started', highlighted: false },
      { name: 'Creator', price: '20', period: 'month', description: 'For individual creators', features: ['Loading...'], cta: 'Start Free Trial', highlighted: false },
      { name: 'Pro', price: '39', period: 'month', description: 'For growing businesses', features: ['Loading...'], cta: 'Start Free Trial', highlighted: true },
      { name: 'Team', price: '98', period: 'month', description: 'For teams & small agencies', features: ['Loading...'], cta: 'Start Free Trial', highlighted: false },
      { name: 'Agency', price: '193', period: 'month', description: 'For large agencies', features: ['Loading...'], cta: 'Start Free Trial', highlighted: false }
    ]
  }

  const p = data.plans
  const isAnnual = billingPeriod.value === 'annual'

  return [
    {
      name: 'Free',
      price: '0',
      period: 'forever',
      description: 'Perfect for getting started',
      features: buildFeatures(p.free, 'free'),
      cta: 'Get Started',
      highlighted: false,
      seatPrice: null,
      maxSeats: 1,
      includedSeats: 1
    },
    {
      name: 'Creator',
      price: getPrice(p.creator),
      period: 'month',
      description: 'For individual creators',
      features: buildFeatures(p.creator, 'creator'),
      cta: 'Start Free Trial',
      highlighted: false,
      annualSavings: isAnnual ? Math.round((1 - p.creator.price_annual / (p.creator.price_monthly * 12)) * 100) : 0,
      seatPrice: getSeatPrice(p.creator),
      maxSeats: p.creator.max_seats,
      includedSeats: p.creator.included_seats
    },
    {
      name: 'Pro',
      price: getPrice(p.pro),
      period: 'month',
      description: 'For growing businesses',
      features: buildFeatures(p.pro, 'pro'),
      cta: 'Start Free Trial',
      highlighted: true,
      annualSavings: isAnnual ? Math.round((1 - p.pro.price_annual / (p.pro.price_monthly * 12)) * 100) : 0,
      seatPrice: getSeatPrice(p.pro),
      maxSeats: p.pro.max_seats,
      includedSeats: p.pro.included_seats
    },
    {
      name: 'Team',
      price: getPrice(p.team),
      period: 'month',
      description: 'For teams & small agencies',
      features: buildFeatures(p.team, 'team'),
      cta: 'Start Free Trial',
      highlighted: false,
      annualSavings: isAnnual ? Math.round((1 - p.team.price_annual / (p.team.price_monthly * 12)) * 100) : 0,
      seatPrice: getSeatPrice(p.team),
      maxSeats: p.team.max_seats,
      includedSeats: p.team.included_seats
    },
    {
      name: 'Agency',
      price: getPrice(p.agency),
      period: 'month',
      description: 'For large agencies',
      features: buildFeatures(p.agency, 'agency'),
      cta: 'Start Free Trial',
      highlighted: false,
      annualSavings: isAnnual ? Math.round((1 - p.agency.price_annual / (p.agency.price_monthly * 12)) * 100) : 0,
      seatPrice: getSeatPrice(p.agency),
      maxSeats: p.agency.max_seats,
      includedSeats: p.agency.included_seats
    }
  ]
})

const faqs = [
  {
    question: 'What platforms does APEX PostMaster support?',
    answer: 'Currently, we support YouTube, Facebook, and Instagram. We are actively working on adding more platforms including Twitter/X, LinkedIn, TikTok, and Pinterest.'
  },
  {
    question: 'How does the AI content generation work?',
    answer: 'Our AI is powered by Claude, one of the most advanced language models. It can generate post ideas, optimize existing content for each platform, suggest trending hashtags, and even score your posts for engagement potential.'
  },
  {
    question: 'Can I try APEX PostMaster before paying?',
    answer: 'Absolutely! Our Free plan lets you explore the platform with 15 posts per month and 10 AI generations. Pro and Team plans also come with a 14-day free trial.'
  },
  {
    question: 'How does the Campaign Planner work?',
    answer: 'The Campaign Planner is an AI-powered chat interface where you describe your marketing goals, and our AI helps you create a complete content strategy with posts, scheduling recommendations, and hashtag strategies.'
  },
  {
    question: 'Is my data secure?',
    answer: 'Yes. We use industry-standard encryption, never store your social media passwords, and use official OAuth integrations. Your content and analytics data are encrypted at rest and in transit.'
  }
]

const goToRegister = () => router.push('/register')
const goToLogin = () => router.push('/login')
const goToContact = () => router.push('/contact')
</script>

<template>
  <div class="landingPage">
    <!-- Navigation -->
    <nav class="navbar">
      <div class="navContainer">
        <div class="navBrand">
          <span class="brandIcon">▲</span>
          <span class="brandText">APEX PostMaster</span>
        </div>

        <div class="navLinks" :class="{ open: mobileMenuOpen }">
          <a href="#features">Features</a>
          <a href="#platforms">Platforms</a>
          <a href="#pricing">Pricing</a>
          <a href="#faq">FAQ</a>
        </div>

        <div class="navActions">
          <Button label="Log in" text @click="goToLogin" class="loginBtn" />
          <Button label="Start Free Trial" @click="goToRegister" class="trialBtn" />
        </div>

        <button class="mobileMenuBtn" @click="mobileMenuOpen = !mobileMenuOpen">
          <i :class="mobileMenuOpen ? 'pi pi-times' : 'pi pi-bars'"></i>
        </button>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
      <div class="heroContent">
        <h1>Manage All Your Social Media in One Place</h1>
        <p class="heroSubtitle">
          Schedule posts, generate AI content, track analytics, and grow your audience across YouTube, Facebook, and Instagram.
        </p>
        <div class="heroCtas">
          <Button label="Start Your Free Trial" size="large" @click="goToRegister" class="primaryCta" />
          <Button label="See How It Works" size="large" outlined class="secondaryCta" />
        </div>
        <p class="heroNote">No credit card required. Free plan available.</p>
      </div>
      <div class="heroImage">
        <div class="dashboardPreview">
          <div class="previewHeader">
            <div class="previewDots">
              <span></span><span></span><span></span>
            </div>
            <span class="previewTitle">APEX PostMaster Dashboard</span>
          </div>
          <div class="previewContent">
            <div class="previewSidebar">
              <div class="previewNavItem active"><i class="pi pi-home"></i></div>
              <div class="previewNavItem"><i class="pi pi-pencil"></i></div>
              <div class="previewNavItem"><i class="pi pi-list"></i></div>
              <div class="previewNavItem"><i class="pi pi-calendar"></i></div>
              <div class="previewNavItem"><i class="pi pi-chart-bar"></i></div>
            </div>
            <div class="previewMain">
              <div class="previewMetrics">
                <div class="previewMetric">
                  <span class="metricValue">2,847</span>
                  <span class="metricLabel">Total Posts</span>
                </div>
                <div class="previewMetric">
                  <span class="metricValue">156K</span>
                  <span class="metricLabel">Engagements</span>
                </div>
                <div class="previewMetric">
                  <span class="metricValue">+24%</span>
                  <span class="metricLabel">Growth</span>
                </div>
              </div>
              <div class="previewChart"></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Platforms Section -->
    <section id="platforms" class="platforms">
      <div class="sectionContainer">
        <h2 class="sectionTitle">Connect Your Favorite Platforms</h2>
        <p class="sectionSubtitle">Manage all your social accounts from a single dashboard</p>
        <div class="platformGrid">
          <div v-for="platform in platforms" :key="platform.name" class="platformCard">
            <i :class="'pi ' + platform.icon"></i>
            <span>{{ platform.name }}</span>
          </div>
        </div>
        <p class="platformNote">More platforms coming soon: Twitter/X, LinkedIn, TikTok, Pinterest</p>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
      <div class="sectionContainer">
        <h2 class="sectionTitle">Everything You Need to Succeed</h2>
        <p class="sectionSubtitle">Powerful tools to streamline your social media workflow</p>
        <div class="featureGrid">
          <div v-for="feature in features" :key="feature.title" class="featureCard">
            <div class="featureIcon">
              <i :class="'pi ' + feature.icon"></i>
            </div>
            <h3>{{ feature.title }}</h3>
            <p>{{ feature.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- AI Section -->
    <section class="aiSection">
      <div class="sectionContainer">
        <div class="aiContent">
          <div class="aiText">
            <span class="aiLabel">AI-Powered</span>
            <h2>Your Personal Social Media Assistant</h2>
            <p>
              Let AI handle the heavy lifting. Generate engaging posts, optimize content for each platform,
              discover trending hashtags, and plan entire campaigns with our intelligent assistant.
            </p>
            <ul class="aiFeatures">
              <li><i class="pi pi-check"></i> Generate platform-specific content</li>
              <li><i class="pi pi-check"></i> Optimize posts for maximum engagement</li>
              <li><i class="pi pi-check"></i> Discover trending hashtags</li>
              <li><i class="pi pi-check"></i> AI-powered campaign planning</li>
              <li><i class="pi pi-check"></i> Content scoring and suggestions</li>
            </ul>
            <Button label="Try AI Features Free" @click="goToRegister" />
          </div>
          <div class="aiDemo">
            <div class="aiChat">
              <div class="aiMessage user">
                <p>Create a post about our summer sale for Instagram</p>
              </div>
              <div class="aiMessage assistant">
                <p>Here's an engaging Instagram post for your summer sale:</p>
                <div class="aiOutput">
                  ☀️ SUMMER SALE IS HERE! ☀️<br><br>
                  Get ready to sizzle with up to 50% OFF on all items! 🔥<br><br>
                  Limited time only - don't miss out on these hot deals! 🛍️<br><br>
                  #SummerSale #HotDeals #ShopNow #SummerVibes
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
      <div class="sectionContainer">
        <div class="statsGrid">
          <div v-for="stat in stats" :key="stat.label" class="statCard">
            <span class="statValue">{{ stat.value }}</span>
            <span class="statLabel">{{ stat.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
      <div class="sectionContainer">
        <h2 class="sectionTitle">Loved by Marketers Worldwide</h2>
        <div class="testimonialGrid">
          <div v-for="testimonial in testimonials" :key="testimonial.author" class="testimonialCard">
            <div class="stars">
              <i class="pi pi-star-fill" v-for="n in 5" :key="n"></i>
            </div>
            <p class="quote">"{{ testimonial.quote }}"</p>
            <div class="author">
              <div class="authorAvatar">{{ testimonial.author[0] }}</div>
              <div class="authorInfo">
                <strong>{{ testimonial.author }}</strong>
                <span>{{ testimonial.role }}, {{ testimonial.company }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
      <div class="sectionContainer">
        <h2 class="sectionTitle">Simple, Transparent Pricing</h2>
        <p class="sectionSubtitle">Start free, upgrade when you're ready</p>

        <!-- Pricing Toggles -->
        <div class="pricingToggles">
          <!-- Billing Period Toggle -->
          <div class="billingToggle">
            <span :class="{ active: billingPeriod === 'monthly' }">Monthly</span>
            <button
              type="button"
              class="toggleSwitch"
              :class="{ annual: billingPeriod === 'annual' }"
              @click="billingPeriod = billingPeriod === 'monthly' ? 'annual' : 'monthly'"
            >
              <span class="toggleKnob"></span>
            </button>
            <span :class="{ active: billingPeriod === 'annual' }">
              Annual
            </span>
          </div>

          <!-- VAT Toggle -->
          <div class="vatToggle">
            <span :class="{ active: !showVat }">Excl. VAT</span>
            <button
              type="button"
              class="toggleSwitch toggleSmall"
              :class="{ active: showVat }"
              @click="showVat = !showVat"
            >
              <span class="toggleKnob"></span>
            </button>
            <span :class="{ active: showVat }">Incl. VAT</span>
          </div>
        </div>

        <div class="pricingGrid pricingGrid5">
          <div
            v-for="plan in plans"
            :key="plan.name"
            class="pricingCard"
            :class="{ highlighted: plan.highlighted }"
          >
            <div v-if="plan.highlighted" class="popularBadge">Most Popular</div>
            <h3>{{ plan.name }}</h3>
            <div class="price">
              <span class="currency">€</span>
              <span class="amount">{{ plan.price }}</span>
              <span class="period" v-if="plan.price !== '0'">/{{ plan.period }}</span>
              <span class="period" v-else>/{{ plan.period }}</span>
            </div>
            <p v-if="billingPeriod === 'annual' && plan.annualSavings > 0" class="annualNote">
              Billed annually (save {{ plan.annualSavings }}%)
            </p>
            <p v-if="plan.seatPrice && plan.maxSeats > 1" class="seatNote">
              +€{{ plan.seatPrice }}/seat/mo (up to {{ plan.maxSeats }} seats)
            </p>
            <p class="planDescription">{{ plan.description }}</p>
            <ul class="planFeatures">
              <li v-for="feature in plan.features" :key="feature">
                <i class="pi pi-check"></i>
                {{ feature }}
              </li>
            </ul>
            <Button
              :label="plan.cta"
              :outlined="!plan.highlighted"
              class="planCta"
              @click="goToRegister()"
            />
          </div>
        </div>

        <p class="vatNote">
          {{ showVat ? `Prices include ${vatRate}% VAT` : 'Prices exclude VAT. VAT will be added at checkout where applicable.' }}
        </p>
      </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq">
      <div class="sectionContainer">
        <h2 class="sectionTitle">Frequently Asked Questions</h2>
        <div class="faqContainer">
          <div
            v-for="(faq, index) in faqs"
            :key="faq.question"
            class="faqItem"
          >
            <div
              class="faqQuestion"
              @click="toggleFaq(index)"
              :class="{ active: openFaqIndex === index }"
            >
              <span>{{ faq.question }}</span>
              <i class="pi" :class="openFaqIndex === index ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
            </div>
            <div
              v-if="openFaqIndex === index"
              class="faqAnswer"
            >
              <p>{{ faq.answer }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="ctaSection">
      <div class="sectionContainer">
        <h2>Ready to Transform Your Social Media?</h2>
        <p>Join thousands of marketers who save time and grow their audience with APEX PostMaster.</p>
        <div class="ctaButtons">
          <Button label="Start Your Free Trial" size="large" @click="goToRegister" />
          <Button label="Contact Sales" size="large" outlined @click="goToContact" />
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <div class="footerContainer">
        <div class="footerBrand">
          <div class="brandLogo">
            <span class="brandIcon">▲</span>
            <span class="brandText">APEX PostMaster</span>
          </div>
          <p>Professional social media management for modern marketers.</p>
          <div class="socialLinks">
            <a href="#"><i class="pi pi-twitter"></i></a>
            <a href="#"><i class="pi pi-linkedin"></i></a>
            <a href="#"><i class="pi pi-instagram"></i></a>
            <a href="#"><i class="pi pi-youtube"></i></a>
          </div>
        </div>
        <div class="footerLinks">
          <div class="footerColumn">
            <h4>Product</h4>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a @click="router.push('/integrations')">Integrations</a>
            <a @click="router.push('/changelog')">Changelog</a>
          </div>
          <div class="footerColumn">
            <h4>Resources</h4>
            <a @click="router.push('/blog')">Blog</a>
            <a @click="router.push('/help')">Help Center</a>
            <a @click="router.push('/api-docs')">API Docs</a>
            <a @click="router.push('/status')">Status</a>
          </div>
          <div class="footerColumn">
            <h4>Company</h4>
            <a @click="router.push('/about')">About</a>
            <a @click="router.push('/careers')">Careers</a>
            <a @click="router.push('/contact')">Contact</a>
            <a @click="router.push('/press')">Press</a>
          </div>
          <div class="footerColumn">
            <h4>Legal</h4>
            <a @click="router.push('/privacy-policy')">Privacy Policy</a>
            <a @click="router.push('/terms-of-service')">Terms of Service</a>
            <a @click="router.push('/cookie-policy')">Cookie Policy</a>
            <a @click="router.push('/gdpr')">GDPR</a>
          </div>
        </div>
      </div>
      <div class="footerBottom">
        <p>&copy; {{ new Date().getFullYear() }} APEX PostMaster. All rights reserved.</p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.landingPage {
  --landing-bg: #0a0b0f;
  --landing-surface: #13141a;
  --landing-surface-alt: #1a1b23;
  --landing-border: #2a2b35;
  --landing-text: #f0f0f5;
  --landing-text-muted: #9ca3af;
  --landing-accent: #6366f1;
  --landing-accent-hover: #818cf8;
  --landing-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);

  background: var(--landing-bg);
  color: var(--landing-text);
  min-height: 100vh;
}

/* Navbar */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  background: rgba(10, 11, 15, 0.9);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--landing-border);
}

.navContainer {
  max-width: 1280px;
  margin: 0 auto;
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.navBrand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 700;
  font-size: 1.25rem;
}

.brandIcon {
  color: var(--landing-accent);
  font-size: 1.5rem;
}

.navLinks {
  display: flex;
  gap: 2rem;
}

.navLinks a {
  color: var(--landing-text-muted);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.navLinks a:hover {
  color: var(--landing-text);
}

.navActions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.loginBtn {
  color: var(--landing-text-muted) !important;
}

.trialBtn {
  background: var(--landing-gradient) !important;
  border: none !important;
}

.mobileMenuBtn {
  display: none;
  background: none;
  border: none;
  color: var(--landing-text);
  font-size: 1.5rem;
  cursor: pointer;
}

/* Hero */
.hero {
  padding: 8rem 2rem 4rem;
  max-width: 1280px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  min-height: 100vh;
}

.heroContent h1 {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 1.5rem;
  background: linear-gradient(135deg, #fff 0%, #c7c7c7 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.heroSubtitle {
  font-size: 1.25rem;
  color: var(--landing-text-muted);
  line-height: 1.6;
  margin-bottom: 2rem;
}

.heroCtas {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
}

.primaryCta {
  background: var(--landing-gradient) !important;
  border: none !important;
  padding: 0.875rem 2rem !important;
}

.secondaryCta {
  border-color: var(--landing-border) !important;
  color: var(--landing-text) !important;
}

.heroNote {
  font-size: 0.875rem;
  color: var(--landing-text-muted);
}

.heroImage {
  display: flex;
  justify-content: center;
}

.dashboardPreview {
  background: var(--landing-surface);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
  overflow: hidden;
  width: 100%;
  max-width: 560px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.previewHeader {
  background: var(--landing-surface-alt);
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  border-bottom: 1px solid var(--landing-border);
}

.previewDots {
  display: flex;
  gap: 6px;
}

.previewDots span {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--landing-border);
}

.previewDots span:first-child { background: #ef4444; }
.previewDots span:nth-child(2) { background: #eab308; }
.previewDots span:last-child { background: #22c55e; }

.previewTitle {
  font-size: 0.875rem;
  color: var(--landing-text-muted);
}

.previewContent {
  display: flex;
  min-height: 300px;
}

.previewSidebar {
  width: 60px;
  background: var(--landing-bg);
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.previewNavItem {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  color: var(--landing-text-muted);
}

.previewNavItem.active {
  background: var(--landing-accent);
  color: white;
}

.previewMain {
  flex: 1;
  padding: 1.5rem;
}

.previewMetrics {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.previewMetric {
  flex: 1;
  background: var(--landing-surface-alt);
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
}

.metricValue {
  display: block;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--landing-accent);
}

.metricLabel {
  font-size: 0.75rem;
  color: var(--landing-text-muted);
}

.previewChart {
  height: 120px;
  background: linear-gradient(180deg, rgba(99, 102, 241, 0.2) 0%, transparent 100%);
  border-radius: 8px;
  position: relative;
}

.previewChart::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: var(--landing-accent);
}

/* Sections Common */
.sectionContainer {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 2rem;
}

.sectionTitle {
  font-size: 2.5rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 0.75rem;
}

.sectionSubtitle {
  font-size: 1.125rem;
  color: var(--landing-text-muted);
  text-align: center;
  margin-bottom: 3rem;
}

/* Platforms */
.platforms {
  padding: 6rem 0;
  background: var(--landing-surface);
}

.platformGrid {
  display: flex;
  justify-content: center;
  gap: 2rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.platformCard {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 2rem 3rem;
  background: var(--landing-bg);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
  transition: all 0.3s;
}

.platformCard:hover {
  border-color: var(--landing-accent);
  transform: translateY(-4px);
}

.platformCard i {
  font-size: 3rem;
  color: var(--landing-accent);
}

.platformCard span {
  font-weight: 600;
}

.platformNote {
  text-align: center;
  color: var(--landing-text-muted);
  font-size: 0.875rem;
}

/* Features */
.features {
  padding: 6rem 0;
}

.featureGrid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.featureCard {
  padding: 2rem;
  background: var(--landing-surface);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
  transition: all 0.3s;
}

.featureCard:hover {
  border-color: var(--landing-accent);
  transform: translateY(-4px);
}

.featureIcon {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(99, 102, 241, 0.1);
  border-radius: 12px;
  margin-bottom: 1.5rem;
}

.featureIcon i {
  font-size: 1.5rem;
  color: var(--landing-accent);
}

.featureCard h3 {
  font-size: 1.25rem;
  margin-bottom: 0.75rem;
}

.featureCard p {
  color: var(--landing-text-muted);
  line-height: 1.6;
}

/* AI Section */
.aiSection {
  padding: 6rem 0;
  background: var(--landing-surface);
}

.aiContent {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
}

.aiLabel {
  display: inline-block;
  padding: 0.375rem 1rem;
  background: rgba(99, 102, 241, 0.1);
  color: var(--landing-accent);
  border-radius: 50px;
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.aiText h2 {
  font-size: 2.25rem;
  margin-bottom: 1rem;
}

.aiText > p {
  color: var(--landing-text-muted);
  line-height: 1.6;
  margin-bottom: 1.5rem;
}

.aiFeatures {
  list-style: none;
  padding: 0;
  margin: 0 0 2rem;
}

.aiFeatures li {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.aiFeatures i {
  color: var(--landing-accent);
}

.aiDemo {
  background: var(--landing-bg);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
  padding: 1.5rem;
}

.aiChat {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.aiMessage {
  padding: 1rem;
  border-radius: 12px;
  max-width: 90%;
}

.aiMessage.user {
  background: var(--landing-accent);
  align-self: flex-end;
}

.aiMessage.assistant {
  background: var(--landing-surface);
  border: 1px solid var(--landing-border);
}

.aiMessage p {
  margin: 0;
}

.aiOutput {
  margin-top: 1rem;
  padding: 1rem;
  background: var(--landing-bg);
  border-radius: 8px;
  font-family: var(--ap-font-mono, monospace);
  font-size: 0.875rem;
  line-height: 1.6;
}

/* Stats */
.stats {
  padding: 4rem 0;
  background: var(--landing-gradient);
}

.statsGrid {
  display: flex;
  justify-content: space-around;
  flex-wrap: wrap;
  gap: 2rem;
}

.statCard {
  text-align: center;
}

.statValue {
  display: block;
  font-size: 3rem;
  font-weight: 800;
}

.statLabel {
  font-size: 1rem;
  opacity: 0.9;
}

/* Testimonials */
.testimonials {
  padding: 6rem 0;
}

.testimonialGrid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.testimonialCard {
  padding: 2rem;
  background: var(--landing-surface);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
}

.stars {
  margin-bottom: 1rem;
}

.stars i {
  color: #fbbf24;
  font-size: 1rem;
}

.quote {
  font-size: 1rem;
  line-height: 1.6;
  margin-bottom: 1.5rem;
  color: var(--landing-text-muted);
}

.author {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.authorAvatar {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--landing-accent);
  border-radius: 50%;
  font-weight: 700;
}

.authorInfo {
  display: flex;
  flex-direction: column;
}

.authorInfo span {
  font-size: 0.875rem;
  color: var(--landing-text-muted);
}

/* Pricing */
.pricing {
  padding: 6rem 0;
  background: var(--landing-surface);
}

/* Pricing Toggles Container */
.pricingToggles {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  margin-bottom: 3rem;
}

/* Billing Toggle */
.billingToggle,
.vatToggle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.billingToggle > span,
.vatToggle > span {
  font-size: 1rem;
  color: var(--landing-text-muted);
  transition: color 0.2s;
}

.vatToggle > span {
  font-size: 0.875rem;
}

.billingToggle > span.active,
.vatToggle > span.active {
  color: var(--landing-text);
  font-weight: 600;
}

.saveBadge {
  display: inline-block;
  background: rgba(34, 197, 94, 0.2);
  color: #22c55e;
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  margin-left: 0.5rem;
  font-weight: 600;
}

.seatNote {
  font-size: 0.75rem;
  color: var(--landing-accent);
  margin: 0.25rem 0 0.75rem;
  font-weight: 500;
}

.vatNote {
  text-align: center;
  font-size: 0.875rem;
  color: var(--landing-text-muted);
  margin-top: 2rem;
}

.toggleSwitch {
  width: 56px;
  height: 28px;
  background: var(--landing-border);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  position: relative;
  transition: background 0.2s;
}

.toggleSwitch.toggleSmall {
  width: 40px;
  height: 22px;
  border-radius: 11px;
}

.toggleSwitch.annual,
.toggleSwitch.active {
  background: var(--landing-accent);
}

.toggleKnob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 24px;
  height: 24px;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s;
}

.toggleSwitch.toggleSmall .toggleKnob {
  width: 18px;
  height: 18px;
}

.toggleSwitch.annual .toggleKnob {
  transform: translateX(28px);
}

.toggleSwitch.toggleSmall.active .toggleKnob {
  transform: translateX(18px);
}

.pricingGrid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  align-items: start;
}

.pricingGrid5 {
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
}

.pricingCard {
  padding: 1.5rem;
  background: var(--landing-bg);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
  position: relative;
  transition: all 0.3s;
}

.pricingCard.highlighted {
  border-color: var(--landing-accent);
  transform: scale(1.03);
  z-index: 1;
}

.popularBadge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--landing-gradient);
  padding: 0.375rem 1rem;
  border-radius: 50px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.pricingCard h3 {
  font-size: 1.25rem;
  margin-bottom: 0.75rem;
}

.price {
  margin-bottom: 0.25rem;
}

.currency {
  font-size: 1.25rem;
  vertical-align: top;
}

.amount {
  font-size: 2.5rem;
  font-weight: 800;
}

.period {
  color: var(--landing-text-muted);
  font-size: 0.875rem;
}

.annualNote {
  font-size: 0.75rem;
  color: #22c55e;
  margin: 0 0 0.75rem;
}

.planDescription {
  color: var(--landing-text-muted);
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.planFeatures {
  list-style: none;
  padding: 0;
  margin: 0 0 1.5rem;
  min-height: 200px;
}

.planFeatures li {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  font-size: 0.8125rem;
  line-height: 1.4;
}

.planFeatures i {
  color: var(--landing-accent);
  font-size: 0.75rem;
  margin-top: 0.2rem;
  flex-shrink: 0;
}

.planFeatures i {
  color: var(--landing-accent);
}

.planCta {
  width: 100%;
}

.pricingCard.highlighted .planCta {
  background: var(--landing-gradient) !important;
  border: none !important;
}

/* FAQ */
.faq {
  padding: 6rem 0;
}

.faqContainer {
  max-width: 800px;
  margin: 0 auto;
}

.faqItem {
  margin-bottom: 1rem;
  border: 1px solid var(--landing-border);
  border-radius: 8px;
  overflow: hidden;
}

.faqQuestion {
  background: var(--landing-surface);
  color: var(--landing-text);
  padding: 1.25rem;
  font-size: 12pt;
  font-weight: 600;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s;
}

.faqQuestion:hover {
  background: var(--landing-surface-alt);
}

.faqQuestion.active {
  border-bottom: 1px solid var(--landing-border);
}

.faqQuestion i {
  color: var(--landing-accent);
  font-size: 1rem;
}

.faqAnswer {
  background: var(--landing-surface);
  color: var(--landing-text);
  padding: 1.25rem;
  font-size: 12pt;
  border-top: 1px solid var(--landing-border);
}

.faqAnswer p {
  margin: 0;
  line-height: 1.6;
  color: var(--landing-text);
}

/* CTA Section */
.ctaSection {
  padding: 6rem 0;
  background: var(--landing-surface);
  text-align: center;
}

.ctaSection h2 {
  font-size: 2.5rem;
  margin-bottom: 1rem;
}

.ctaSection > .sectionContainer > p {
  color: var(--landing-text-muted);
  font-size: 1.125rem;
  margin-bottom: 2rem;
}

.ctaButtons {
  display: flex;
  justify-content: center;
  gap: 1rem;
}

.ctaButtons .p-button:first-child {
  background: var(--landing-gradient) !important;
  border: none !important;
}

/* Footer */
.footer {
  background: var(--landing-bg);
  border-top: 1px solid var(--landing-border);
  padding: 4rem 0 0;
}

.footerContainer {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 2rem;
  display: grid;
  grid-template-columns: 1.5fr 2fr;
  gap: 4rem;
}

.footerBrand p {
  color: var(--landing-text-muted);
  margin: 1rem 0;
  max-width: 300px;
}

.brandLogo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 700;
  font-size: 1.25rem;
}

.socialLinks {
  display: flex;
  gap: 1rem;
}

.socialLinks a {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--landing-surface);
  border-radius: 8px;
  color: var(--landing-text-muted);
  transition: all 0.2s;
}

.socialLinks a:hover {
  background: var(--landing-accent);
  color: white;
}

.footerLinks {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2rem;
}

.footerColumn h4 {
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 1rem;
  color: var(--landing-text);
}

.footerColumn a {
  display: block;
  color: var(--landing-text-muted);
  text-decoration: none;
  margin-bottom: 0.75rem;
  font-size: 0.9375rem;
  transition: color 0.2s;
  cursor: pointer;
}

.footerColumn a:hover {
  color: var(--landing-text);
}

.footerBottom {
  max-width: 1280px;
  margin: 0 auto;
  padding: 2rem;
  border-top: 1px solid var(--landing-border);
  margin-top: 4rem;
}

.footerBottom p {
  color: var(--landing-text-muted);
  font-size: 0.875rem;
  text-align: center;
}

/* Responsive */
@media (max-width: 1400px) {
  .pricingGrid5 {
    grid-template-columns: repeat(3, 1fr);
    max-width: 900px;
    margin: 0 auto;
  }
}

@media (max-width: 1024px) {
  .hero {
    grid-template-columns: 1fr;
    text-align: center;
    padding-top: 6rem;
    min-height: auto;
  }

  .heroContent h1 {
    font-size: 2.75rem;
  }

  .heroCtas {
    justify-content: center;
  }

  .heroImage {
    order: -1;
  }

  .dashboardPreview {
    max-width: 100%;
  }

  .featureGrid,
  .testimonialGrid {
    grid-template-columns: 1fr;
    max-width: 500px;
    margin: 0 auto;
  }

  .pricingGrid,
  .pricingGrid5 {
    grid-template-columns: repeat(2, 1fr);
    max-width: 700px;
    margin: 0 auto;
  }

  .pricingCard.highlighted {
    transform: none;
  }

  .aiContent {
    grid-template-columns: 1fr;
  }

  .footerContainer {
    grid-template-columns: 1fr;
  }

  .footerLinks {
    grid-template-columns: repeat(2, 1fr);
  }

  .billingToggle {
    flex-wrap: wrap;
  }
}

@media (max-width: 768px) {
  .navLinks {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--landing-bg);
    border-bottom: 1px solid var(--landing-border);
    flex-direction: column;
    padding: 1rem 2rem;
    gap: 1rem;
  }

  .navLinks.open {
    display: flex;
  }

  .navActions {
    display: none;
  }

  .mobileMenuBtn {
    display: block;
  }

  .heroContent h1 {
    font-size: 2rem;
  }

  .sectionTitle {
    font-size: 1.75rem;
  }

  .statsGrid {
    gap: 1rem;
  }

  .statValue {
    font-size: 2rem;
  }

  .platformGrid {
    gap: 1rem;
  }

  .platformCard {
    padding: 1.5rem 2rem;
  }

  .platformCard i {
    font-size: 2rem;
  }

  .pricingGrid,
  .pricingGrid5 {
    grid-template-columns: 1fr;
    max-width: 400px;
  }

  .planFeatures {
    min-height: auto;
  }
}
</style>
