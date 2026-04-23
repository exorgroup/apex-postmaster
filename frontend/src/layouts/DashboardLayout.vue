<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'
import Badge from 'primevue/badge'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const sidebarOpen = ref(false)
const userMenu = ref()
const userMenuItems = ref([
  { label: 'Profile', icon: 'pi pi-user', command: () => router.push('/settings') },
  { label: 'Billing', icon: 'pi pi-credit-card', command: () => router.push('/settings/billing') },
  { separator: true },
  { label: 'Logout', icon: 'pi pi-sign-out', command: () => auth.logout() }
])

const navItems = [
  { section: 'Core' },
  { name: 'Dashboard', icon: 'pi pi-home', route: '/dashboard' },
  { name: 'Compose', icon: 'pi pi-pencil', route: '/compose', highlight: true },
  { name: 'Calendar', icon: 'pi pi-calendar', route: '/calendar' },
  { name: 'Queue', icon: 'pi pi-list', route: '/queue', badge: 'queueCount' },
  { section: 'Intelligence' },
  { name: 'Analytics', icon: 'pi pi-chart-bar', route: '/analytics' },
  { name: 'AI Tools', icon: 'pi pi-sparkles', route: '/ai' },
  { name: 'Campaigns', icon: 'pi pi-megaphone', route: '/campaigns' },
  { section: 'Monitor' },
  { name: 'Streams', icon: 'pi pi-comments', route: '/streams', badge: 'streamCount', plan: 'pro' },
  { section: 'Settings' },
  { name: 'Profile', icon: 'pi pi-user', route: '/settings' },
  { name: 'Platforms', icon: 'pi pi-share-alt', route: '/settings/platforms' },
  { name: 'Billing', icon: 'pi pi-credit-card', route: '/settings/billing' },
  { name: 'Credits', icon: 'pi pi-wallet', route: '/settings/credits' },
  { name: 'Team', icon: 'pi pi-users', route: '/settings/team', plan: 'team' }
]

const bottomNavItems = [
  { name: 'Home', icon: 'pi pi-home', route: '/dashboard' },
  { name: 'Compose', icon: 'pi pi-plus-circle', route: '/compose' },
  { name: 'Calendar', icon: 'pi pi-calendar', route: '/calendar' },
  { name: 'Queue', icon: 'pi pi-list', route: '/queue' },
  { name: 'More', icon: 'pi pi-ellipsis-h', action: 'toggleSidebar' }
]

const isActive = (itemRoute) => {
  if (itemRoute === '/dashboard' && route.path === '/dashboard') return true
  if (itemRoute !== '/dashboard' && route.path.startsWith(itemRoute)) return true
  return false
}

const navigateTo = (item) => {
  if (item.action === 'toggleSidebar') {
    sidebarOpen.value = !sidebarOpen.value
  } else if (item.route) {
    router.push(item.route)
    sidebarOpen.value = false
  }
}

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
}

const closeSidebar = () => {
  sidebarOpen.value = false
}

const queueCount = computed(() => 5)
const streamCount = computed(() => 3)

const getBadgeValue = (badgeKey) => {
  if (badgeKey === 'queueCount') return queueCount.value
  if (badgeKey === 'streamCount') return streamCount.value
  return 0
}
</script>

<template>
  <div class="dashboardLayout">
    <!-- Sidebar -->
    <aside class="apSidebar" :class="{ open: sidebarOpen }">
      <div class="sidebarHeader">
        <div class="apLogo">
          <span>APEX</span> PostMaster
        </div>
        <Button
          v-if="sidebarOpen"
          icon="pi pi-times"
          text
          rounded
          class="closeSidebarBtn"
          @click="closeSidebar"
        />
      </div>
      <nav class="apSidebarNav">
        <template v-for="item in navItems" :key="item.name || item.section">
          <div v-if="item.section" class="apNavSection">
            {{ item.section }}
          </div>
          <button
            v-else
            class="apNavItem"
            :class="{ active: isActive(item.route), highlight: item.highlight }"
            @click="navigateTo(item)"
          >
            <i :class="item.icon"></i>
            <span>{{ item.name }}</span>
            <Badge
              v-if="item.badge && getBadgeValue(item.badge) > 0"
              :value="getBadgeValue(item.badge)"
              class="navBadge"
            />
          </button>
        </template>
      </nav>
      <div class="sidebarFooter">
        <div class="userInfo" @click="toggleUserMenu">
          <Avatar
            :image="auth.user?.avatar"
            :label="auth.user?.avatar ? undefined : (auth.user?.name?.[0] || 'U')"
            shape="circle"
            class="userAvatar"
          />
          <div class="userDetails">
            <span class="userName">{{ auth.user?.name || 'User' }}</span>
            <span class="userPlan">{{ auth.currentPlan }} plan</span>
          </div>
          <i class="pi pi-chevron-up"></i>
        </div>
        <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
      </div>
    </aside>

    <!-- Overlay for mobile sidebar -->
    <div
      v-if="sidebarOpen"
      class="sidebarOverlay"
      @click="closeSidebar"
    ></div>

    <!-- Header -->
    <header class="apHeader">
      <div class="headerLeft">
        <Button
          icon="pi pi-bars"
          text
          rounded
          class="menuToggle d-lg-none"
          @click="sidebarOpen = !sidebarOpen"
        />
        <div class="apLogo d-lg-none">
          <span>APEX</span> PostMaster
        </div>
      </div>
      <div class="headerRight">
        <Button
          icon="pi pi-plus"
          label="New Post"
          class="d-none d-md-inline-flex"
          @click="router.push('/compose')"
        />
        <Button
          icon="pi pi-plus"
          class="d-md-none"
          rounded
          @click="router.push('/compose')"
        />
        <Button
          icon="pi pi-bell"
          text
          rounded
          v-tooltip.bottom="'Notifications'"
        />
        <Avatar
          :image="auth.user?.avatar"
          :label="auth.user?.avatar ? undefined : (auth.user?.name?.[0] || 'U')"
          shape="circle"
          class="headerAvatar d-none d-lg-flex"
          @click="toggleUserMenu"
        />
      </div>
    </header>

    <!-- Main content -->
    <main class="apMain">
      <slot />
    </main>

    <!-- Bottom nav (mobile) -->
    <nav class="apBottomNav">
      <button
        v-for="item in bottomNavItems"
        :key="item.name"
        class="apBottomNavItem"
        :class="{ active: isActive(item.route) }"
        @click="navigateTo(item)"
      >
        <i :class="item.icon"></i>
        <span>{{ item.name }}</span>
      </button>
    </nav>
  </div>
</template>

<style scoped>
.dashboardLayout {
  min-height: 100vh;
  background: var(--ap-bg);
}

.sidebarHeader {
  padding: 1.25rem;
  border-bottom: 1px solid var(--ap-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.closeSidebarBtn {
  color: var(--ap-text-muted);
}

.sidebarFooter {
  padding: 1rem;
  border-top: 1px solid var(--ap-border);
}

.userInfo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border-radius: var(--ap-radius-sm);
  cursor: pointer;
  transition: background var(--ap-transition);
}

.userInfo:hover {
  background: var(--ap-surface-hover);
}

.userAvatar {
  background: var(--ap-accent);
  color: white;
}

.userDetails {
  flex: 1;
  min-width: 0;
}

.userName {
  display: block;
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--ap-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.userPlan {
  display: block;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  text-transform: capitalize;
}

.userInfo i {
  color: var(--ap-text-muted);
  font-size: 0.75rem;
}

.apNavItem.highlight {
  color: var(--ap-accent);
}

.apNavItem.highlight:hover {
  background: var(--ap-accent-subtle);
}

.navBadge {
  margin-left: auto;
}

.headerLeft,
.headerRight {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.menuToggle {
  color: var(--ap-text);
}

.headerAvatar {
  cursor: pointer;
  background: var(--ap-accent);
  color: white;
}

.sidebarOverlay {
  display: none;
}

@media (max-width: 991px) {
  .sidebarOverlay {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99;
  }

  .apSidebar.open {
    width: 280px;
  }
}
</style>
