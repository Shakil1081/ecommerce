<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

defineProps<{
  title?: string;
}>();

const page = usePage<SharedProps>();
const sidebarCollapsed = ref(false);
const isLoggingOut = ref(false);

interface SubMenuItem {
  title: string;
  href: string;
  icon?: string;
  highlight?: boolean;
}

interface MenuGroup {
  id: string;
  title: string;
  icon: string;
  items: SubMenuItem[];
}

const menuGroups: MenuGroup[] = [
  {
    id: 'procurement',
    title: 'Procurement & Sourcing',
    icon: '🏢',
    items: [
      { title: 'Suppliers Directory', href: '/admin/procurement/suppliers', icon: '📋' },
      { title: 'Global Import Orders', href: '/admin/procurement/import-orders', icon: '🚢' },
      { title: 'Local Purchase Orders', href: '/admin/procurement/local-pos', icon: '🧾' },
      { title: 'Inward GRN Receiving', href: '/admin/procurement/grn', icon: '📥' },
    ],
  },
  {
    id: 'catalog',
    title: 'Operations & Catalog',
    icon: '📦',
    items: [
      { title: 'Categories', href: '/admin/categories', icon: '📁' },
      { title: 'Add Category', href: '/admin/categories?action=create', icon: '➕' },
      { title: 'Product Catalog', href: '/admin/products', icon: '🏷️' },
      { title: 'Add New Product', href: '/admin/products/create', icon: '➕', highlight: true },
      { title: 'Orders & Anti-Fraud', href: '/admin/orders', icon: '🛍️' },
      { title: 'Fraud Directory', href: '/admin/fraud-directory', icon: '🛡️' },
    ],
  },
  {
    id: 'logistics',
    title: 'Couriers & Logistics',
    icon: '🚚',
    items: [
      { title: 'Delivery & COD Overview', href: '/admin/logistics/delivery-overview', icon: '📊', highlight: true },
      { title: 'Courier Gateways', href: '/admin/settings/couriers', icon: '⚙️' },
      { title: 'Scan & Handover Parcels', href: '/admin/orders/scan-handover', icon: '📦' },
      { title: 'Scan Returned Parcels', href: '/admin/orders/scan-returns', icon: '📷' },
      { title: 'Delivery Zones & Rates', href: '/admin/logistics/zones', icon: '🗺️' },
      { title: 'COD Reconciliation', href: '/admin/logistics/cod-reconciliation', icon: '💰' },
    ],
  },
  {
    id: 'payments',
    title: 'Payments & Security',
    icon: '💳',
    items: [
      { title: 'Payment Gateways', href: '/admin/settings/payment-gateways', icon: '💳' },
      { title: 'Bot Security & Captcha', href: '/admin/settings/bot-security', icon: '🛡️' },
      { title: 'Roles & Permissions', href: '/admin/roles', icon: '👥' },
      { title: 'Staff Management', href: '/admin/staff', icon: '👤' },
    ],
  },
  {
    id: 'communication',
    title: 'Communication & Marketing',
    icon: '💬',
    items: [
      { title: 'SMTP Mail Settings', href: '/admin/communication/mail-settings', icon: '📧' },
      { title: 'SMS Gateways & OTP', href: '/admin/communication/sms-settings', icon: '📱' },
      { title: 'AI Voice Verification', href: '/admin/communication/voice-settings', icon: '📞' },
      { title: 'Cart Recovery Sequencer', href: '/admin/marketing/cart-recovery', icon: '🛒' },
    ],
  },
  {
    id: 'tax',
    title: 'Tax & NBR Compliance',
    icon: '🏛️',
    items: [
      { title: 'Tax Overview', href: '/admin/tax-compliance', icon: '📊' },
      { title: 'Mushak 6.3 (Invoices)', href: '/admin/tax-compliance/mushak-6-3', icon: '🧾' },
      { title: 'Mushak 9.1 (Returns)', href: '/admin/tax-compliance/mushak-9-1', icon: '📈' },
      { title: 'Mushak 6.6 (VDS)', href: '/admin/tax-compliance/mushak-6-6', icon: '📑' },
      { title: 'RMA & Credit Notes', href: '/admin/tax-compliance/rma', icon: '🔄' },
      { title: 'Mushak 6.1 / 6.2', href: '/admin/tax-compliance/registers', icon: '📖' },
    ],
  },
  {
    id: 'analytics',
    title: 'Analytics & Audit',
    icon: '📈',
    items: [
      { title: 'Trading P&L', href: '/admin/analytics/trading-pnl', icon: '💹' },
      { title: 'Audit Trail', href: '/admin/audit-logs', icon: '📋' },
    ],
  },
  {
    id: 'system',
    title: 'System & AI Settings',
    icon: '⚙️',
    items: [
      { title: 'AI Engine Settings', href: '/admin/ai-settings', icon: '🤖' },
      { title: 'Multi-Cloud Storage', href: '/admin/settings/media-storage', icon: '☁️' },
      { title: 'System Info & Updates', href: '/admin/settings/system', icon: 'ℹ️' },
    ],
  },
];

const normalizeUrl = (url: string) => {
  if (!url) return '';
  return url.split('?')[0].replace(/\/+$/, '');
};

const isPathActive = (href: string) => {
  const current = normalizeUrl(page.url);
  const target = normalizeUrl(href);
  if (target === '/admin' || target === '/admin/dashboard') {
    return current === '/admin' || current === '/admin/dashboard';
  }
  return current === target || current.startsWith(target + '/');
};

const isGroupActive = (group: MenuGroup) => {
  return group.items.some((item) => isPathActive(item.href));
};

// Open accordion groups dictionary
const openGroups = ref<Record<string, boolean>>({});

const syncActiveGroup = () => {
  menuGroups.forEach((group) => {
    if (isGroupActive(group)) {
      openGroups.value[group.id] = true;
    }
  });
};

onMounted(() => {
  syncActiveGroup();
});

watch(
  () => page.url,
  () => {
    syncActiveGroup();
  }
);

const toggleGroup = (groupId: string) => {
  if (sidebarCollapsed.value) {
    sidebarCollapsed.value = false;
    openGroups.value[groupId] = true;
    return;
  }
  openGroups.value[groupId] = !openGroups.value[groupId];
};

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value;
};

const handleLogout = () => {
  if (isLoggingOut.value) return;
  isLoggingOut.value = true;
  router.post('/logout', {}, {
    onFinish: () => {
      isLoggingOut.value = false;
    },
  });
};
</script>

<template>
  <div class="admin-shell" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
    <Head :title="title ? `${title} - Admin Hub` : 'Admin Hub'" />

    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
      <div class="sidebar-header">
        <Link href="/admin/dashboard" class="sidebar-brand">
          <span class="brand-badge">⚡</span>
          <span v-if="!sidebarCollapsed" class="brand-name">TradingHub</span>
        </Link>
        <button
          type="button"
          class="sidebar-toggle-btn"
          :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          @click="toggleSidebar"
        >
          {{ sidebarCollapsed ? '→' : '←' }}
        </button>
      </div>

      <nav class="sidebar-nav">
        <!-- Direct Dashboard Link -->
        <Link
          href="/admin/dashboard"
          class="nav-dashboard-link"
          :class="{ 'is-active': isPathActive('/admin/dashboard') || isPathActive('/admin') }"
          :title="sidebarCollapsed ? 'Dashboard & P&L' : undefined"
        >
          <span class="nav-icon">📊</span>
          <span v-if="!sidebarCollapsed" class="nav-label">Dashboard</span>
        </Link>

        <!-- Collapsible Accordion Groups -->
        <div
          v-for="group in menuGroups"
          :key="group.id"
          class="nav-group"
          :class="{ 'is-expanded': openGroups[group.id] && !sidebarCollapsed }"
        >
          <!-- Parent Group Header Button -->
          <button
            type="button"
            class="nav-group-header"
            :class="{
              'is-active': isGroupActive(group),
              'is-open': openGroups[group.id] && !sidebarCollapsed,
            }"
            :title="sidebarCollapsed ? group.title : undefined"
            @click="toggleGroup(group.id)"
          >
            <div class="header-main">
              <span class="nav-icon">{{ group.icon }}</span>
              <span v-if="!sidebarCollapsed" class="nav-group-title">{{ group.title }}</span>
            </div>

            <div v-if="!sidebarCollapsed" class="header-meta">
              <span
                v-if="isGroupActive(group) && !openGroups[group.id]"
                class="active-dot"
                title="Active section"
              />
              <svg
                class="chevron-icon"
                :class="{ 'is-rotated': openGroups[group.id] }"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
          </button>

          <!-- Collapsible Sub-menu -->
          <transition name="menu-expand">
            <div
              v-show="openGroups[group.id] && !sidebarCollapsed"
              class="nav-sub-items"
            >
              <Link
                v-for="item in group.items"
                :key="item.href"
                :href="item.href"
                class="nav-sub-item"
                :class="{
                  'is-active': isPathActive(item.href),
                  'is-highlight': item.highlight,
                }"
              >
                <span class="sub-item-icon">{{ item.icon || '•' }}</span>
                <span class="sub-item-title">{{ item.title }}</span>
                <span v-if="item.highlight" class="sub-item-badge">New</span>
              </Link>
            </div>
          </transition>
        </div>
      </nav>

      <!-- Sidebar User Profile & Logout -->
      <div class="sidebar-footer" :class="{ 'collapsed': sidebarCollapsed }">
        <div v-if="!sidebarCollapsed" class="sidebar-user-card">
          <span class="user-avatar-badge">👤</span>
          <div class="sidebar-user-details">
            <span class="sidebar-user-name">{{ page.props.auth?.user?.name || 'Administrator' }}</span>
            <span class="sidebar-user-email">{{ page.props.auth?.user?.email || 'admin@ecommerce.test' }}</span>
          </div>
        </div>
        <button
          type="button"
          class="sidebar-logout-btn"
          :title="sidebarCollapsed ? 'Sign Out / Logout' : undefined"
          :disabled="isLoggingOut"
          @click="handleLogout"
        >
          <span class="logout-icon">🚪</span>
          <span v-if="!sidebarCollapsed" class="logout-label">
            {{ isLoggingOut ? 'Signing out...' : 'Sign Out' }}
          </span>
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main-wrap">
      <!-- Topbar Header -->
      <header class="admin-topbar">
        <div class="topbar-left">
          <h2 class="page-title">{{ title || 'Dashboard' }}</h2>
        </div>
        <div class="topbar-right">
          <Link href="/shop" class="topbar-storefront-link" title="Open Storefront">
            <span>🛍️</span> Storefront
          </Link>
          <span class="version-tag badge badge-primary">v0.1.0 Alpha</span>
          <div class="user-profile">
            <span class="user-avatar">👤</span>
            <span class="user-name">{{ page.props.auth?.user?.name || 'Administrator' }}</span>
          </div>
          <button
            type="button"
            class="topbar-logout-btn"
            title="Sign Out / Logout"
            :disabled="isLoggingOut"
            @click="handleLogout"
          >
            <span class="logout-icon">🚪</span>
            <span class="logout-text">{{ isLoggingOut ? 'Signing out...' : 'Logout' }}</span>
          </button>
        </div>
      </header>

      <!-- Global Flash Alerts -->
      <div v-if="page.props.flash?.success || page.props.flash?.error" class="admin-flash-container">
        <div v-if="page.props.flash?.success" class="alert alert-success">
          {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="alert alert-danger">
          {{ page.props.flash.error }}
        </div>
      </div>

      <!-- Main Slot -->
      <main class="admin-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.admin-shell {
  display: flex;
  min-height: 100vh;
  background-color: hsl(342, 60%, 98%);
}

.admin-sidebar {
  width: 260px;
  background: #ffffff;
  color: hsl(342, 80%, 25%);
  display: flex;
  flex-direction: column;
  transition: width $transition-base;
  flex-shrink: 0;
  border-right: 1px solid hsl(342, 40%, 90%);
  box-shadow: 2px 0 10px rgba(235, 40, 108, 0.04);

  .sidebar-header {
    height: 64px;
    padding: 0 $space-5;
    @include flex-between;
    border-bottom: 1px solid hsl(342, 40%, 90%);
    background: #ffffff;

    .sidebar-brand {
      @include flex-start;
      gap: $space-3;
      font-family: $font-heading;
      font-weight: $fw-bold;
      font-size: $fs-lg;
      color: hsl(342, 85%, 22%);
      text-decoration: none;

      .brand-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: $radius-lg;
        background: linear-gradient(135deg, $color-primary-500, $color-primary-600);
        box-shadow: 0 4px 12px rgba(235, 40, 108, 0.3);
        font-size: 15px;
        color: #ffffff;
      }

      .brand-name {
        letter-spacing: -0.02em;
        color: hsl(342, 85%, 22%);
      }
    }

    .sidebar-toggle-btn {
      color: hsl(342, 60%, 40%);
      font-size: $fs-sm;
      padding: $space-1 $space-2;
      border-radius: $radius-sm;
      background: transparent;
      border: none;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover {
        background-color: hsl(342, 90%, 95%);
        color: $color-primary-700;
      }
    }
  }

  .sidebar-nav {
    padding: $space-3 $space-2;
    display: flex;
    flex-direction: column;
    gap: 3px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: hsl(342, 50%, 88%) transparent;

    &::-webkit-scrollbar {
      width: 4px;
    }
    &::-webkit-scrollbar-thumb {
      background: hsl(342, 50%, 88%);
      border-radius: 4px;
    }

    .nav-dashboard-link {
      @include flex-start;
      gap: $space-3;
      padding: 10px 12px;
      border-radius: $radius-lg;
      font-size: $fs-sm;
      font-weight: $fw-semibold;
      color: hsl(342, 80%, 25%);
      text-decoration: none;
      transition: all $transition-fast;
      margin-bottom: $space-2;
      border: 1px solid transparent;

      .nav-icon {
        font-size: $fs-base;
        flex-shrink: 0;
      }

      &:hover {
        background-color: hsl(342, 90%, 96%);
        color: $color-primary-700;
      }

      &.is-active {
        background: linear-gradient(135deg, hsl(342, 90%, 95%) 0%, hsl(342, 85%, 92%) 100%);
        color: $color-primary-800;
        border-color: hsl(342, 70%, 84%);
        box-shadow: 0 2px 8px rgba(235, 40, 108, 0.12);
        font-weight: $fw-bold;
      }
    }

    .nav-group {
      display: flex;
      flex-direction: column;

      .nav-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 9px 12px;
        background: transparent;
        border: 1px solid transparent;
        border-radius: $radius-lg;
        color: hsl(342, 80%, 25%);
        font-size: $fs-sm;
        font-weight: $fw-semibold;
        cursor: pointer;
        transition: all $transition-fast;
        text-align: left;

        .header-main {
          display: flex;
          align-items: center;
          gap: $space-3;
          min-width: 0;

          .nav-icon {
            font-size: $fs-base;
            flex-shrink: 0;
          }

          .nav-group-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 13px;
          }
        }

        .header-meta {
          display: flex;
          align-items: center;
          gap: $space-2;
          flex-shrink: 0;

          .active-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: $color-primary-600;
            box-shadow: 0 0 6px rgba(235, 40, 108, 0.6);
          }

          .chevron-icon {
            width: 16px;
            height: 16px;
            color: hsl(342, 40%, 60%);
            transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);

            &.is-rotated {
              transform: rotate(180deg);
              color: $color-primary-600;
            }
          }
        }

        &:hover {
          background-color: hsl(342, 90%, 96%);
          color: $color-primary-800;

          .chevron-icon {
            color: $color-primary-700;
          }
        }

        &.is-active {
          color: $color-primary-800;
          background-color: hsl(342, 85%, 96%);
          border-color: hsl(342, 70%, 88%);

          .nav-group-title {
            font-weight: $fw-bold;
          }
        }

        &.is-open {
          color: $color-primary-900;
        }
      }

      .nav-sub-items {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 4px 0 6px 14px;
        margin: 2px 0 4px 18px;
        border-left: 2px solid hsl(342, 60%, 88%);

        .nav-sub-item {
          display: flex;
          align-items: center;
          gap: $space-2;
          padding: 6px 10px;
          border-radius: $radius-md;
          font-size: 12.5px;
          color: hsl(342, 70%, 30%);
          text-decoration: none;
          transition: all $transition-fast;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          font-weight: $fw-medium;

          .sub-item-icon {
            font-size: 13px;
            flex-shrink: 0;
            opacity: 0.85;
          }

          .sub-item-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
          }

          .sub-item-badge {
            font-size: 9px;
            font-weight: $fw-bold;
            text-transform: uppercase;
            padding: 1px 5px;
            border-radius: $radius-full;
            background: $color-primary-500;
            color: #ffffff;
            letter-spacing: 0.05em;
          }

          &:hover {
            color: $color-primary-900;
            background-color: hsl(342, 90%, 95%);
            font-weight: $fw-semibold;
          }

          &.is-active {
            color: $color-primary-800;
            background-color: hsl(342, 85%, 93%);
            font-weight: $fw-bold;

            .sub-item-icon {
              opacity: 1;
            }
          }

          &.is-highlight {
            color: $color-primary-700;
            font-weight: $fw-bold;
          }
        }
      }
    }
  }

  // Accordion Expand Transition
  .menu-expand-enter-active,
  .menu-expand-leave-active {
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 450px;
    overflow: hidden;
    opacity: 1;
  }

  .menu-expand-enter-from,
  .menu-expand-leave-to {
    max-height: 0;
    opacity: 0;
    margin-top: 0;
    margin-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
    transform: translateY(-4px);
  }

  .sidebar-footer {
    margin-top: auto;
    padding: $space-4 $space-3;
    border-top: 1px solid hsl(342, 40%, 90%);
    background-color: hsl(342, 60%, 98%);
    display: flex;
    flex-direction: column;
    gap: $space-3;

    &.collapsed {
      padding: $space-3 $space-2;
      align-items: center;
    }

    .sidebar-user-card {
      display: flex;
      align-items: center;
      gap: $space-3;
      padding: 0 $space-2;

      .user-avatar-badge {
        font-size: $fs-base;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: hsl(342, 85%, 93%);
        border: 1px solid hsl(342, 60%, 82%);
        color: $color-primary-700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .sidebar-user-details {
        display: flex;
        flex-direction: column;
        overflow: hidden;

        .sidebar-user-name {
          font-size: $fs-xs;
          font-weight: $fw-bold;
          color: hsl(342, 85%, 22%);
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .sidebar-user-email {
          font-size: $fs-2xs;
          color: hsl(342, 45%, 45%);
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
      }
    }

    .sidebar-logout-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: $space-2;
      width: 100%;
      padding: $space-2 $space-3;
      background: hsl(342, 80%, 96%);
      color: hsl(342, 80%, 35%);
      border: 1px solid hsl(342, 60%, 88%);
      border-radius: $radius-lg;
      font-size: $fs-xs;
      font-weight: $fw-bold;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover:not(:disabled) {
        background: hsl(342, 85%, 92%);
        color: $color-primary-900;
        border-color: hsl(342, 60%, 80%);
      }

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }

      .logout-icon {
        font-size: $fs-sm;
      }
    }
  }
}

.sidebar-collapsed .admin-sidebar {
  width: 72px;

  .sidebar-header {
    justify-content: center;
  }

  .sidebar-nav {
    padding: $space-3 $space-1;

    .nav-dashboard-link {
      justify-content: center;
      padding: 10px 0;
    }

    .nav-group {
      .nav-group-header {
        justify-content: center;
        padding: 10px 0;

        .header-main {
          justify-content: center;
        }
      }
    }
  }
}

.admin-main-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.admin-topbar {
  height: 64px;
  background-color: #ffffff;
  border-bottom: 1px solid hsl(342, 60%, 92%);
  box-shadow: 0 2px 10px rgba(235, 40, 108, 0.04);
  padding: 0 $space-6;
  @include flex-between;

  .page-title {
    font-size: $fs-xl;
    font-weight: $fw-bold;
    color: hsl(342, 45%, 15%);
    font-family: $font-heading;
  }

  .topbar-right {
    @include flex-start;
    gap: $space-4;

    .version-tag {
      font-size: $fs-xs;
      background: linear-gradient(135deg, $color-primary-500, $color-primary-600);
      color: #ffffff;
      border: none;
      padding: 3px 10px;
      font-weight: 600;
      box-shadow: 0 2px 6px rgba(235, 40, 108, 0.3);
    }

    .user-profile {
      @include flex-start;
      gap: $space-2;
      font-size: $fs-sm;
      font-weight: $fw-medium;
      color: hsl(342, 35%, 25%);

      .user-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: $color-primary-100;
        border: 1px solid $color-primary-200;
        font-size: 13px;
      }
    }

    .topbar-storefront-link {
      display: inline-flex;
      align-items: center;
      gap: $space-1;
      font-size: $fs-xs;
      font-weight: 600;
      color: $color-primary-700;
      padding: 5px $space-3;
      border-radius: $radius-full;
      background: $color-primary-50;
      text-decoration: none;
      border: 1px solid $color-primary-200;
      transition: all $transition-fast;

      &:hover {
        background: $color-primary-100;
        color: $color-primary-800;
        border-color: $color-primary-400;
        box-shadow: 0 2px 8px rgba(235, 40, 108, 0.2);
      }
    }

    .topbar-logout-btn {
      display: inline-flex;
      align-items: center;
      gap: $space-1;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: hsl(342, 25%, 40%);
      padding: 6px $space-3;
      border-radius: $radius-lg;
      background: hsl(342, 60%, 98%);
      border: 1px solid hsl(342, 40%, 88%);
      cursor: pointer;
      transition: all $transition-fast;

      &:hover:not(:disabled) {
        background: rgba($color-danger, 0.12);
        color: $color-danger;
        border-color: rgba($color-danger, 0.4);
      }

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }

      .logout-icon {
        font-size: $fs-sm;
      }
    }
  }
}

.admin-flash-container {
  padding: $space-4 $space-6 0;

  .alert {
    padding: $space-3 $space-5;
    border-radius: $radius-lg;
    font-size: $fs-sm;
    font-weight: $fw-medium;

    &-success {
      background-color: hsl(342, 85%, 96%);
      color: $color-primary-800;
      border: 1px solid hsl(342, 70%, 88%);
    }

    &-danger {
      background-color: hsl(0, 86%, 95%);
      color: hsl(0, 72%, 35%);
      border: 1px solid hsl(0, 70%, 88%);
    }
  }
}

.admin-content {
  padding: $space-6;
  flex: 1;
}
</style>
