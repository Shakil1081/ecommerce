<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

defineProps<{
  title?: string;
}>();

const page = usePage<SharedProps>();
const sidebarCollapsed = ref(false);
const isLoggingOut = ref(false);

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
        <button class="sidebar-toggle-btn" @click="toggleSidebar">
          {{ sidebarCollapsed ? '→' : '←' }}
        </button>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section-title" v-if="!sidebarCollapsed">PROCUREMENT & SOURCING</div>
        <Link href="/admin/procurement/suppliers" class="nav-item">
          <span class="nav-icon">🏢</span>
          <span v-if="!sidebarCollapsed">Suppliers Directory</span>
        </Link>
        <Link href="/admin/procurement/import-orders" class="nav-item">
          <span class="nav-icon">🚢</span>
          <span v-if="!sidebarCollapsed">Global Import Orders</span>
        </Link>
        <Link href="/admin/procurement/local-pos" class="nav-item">
          <span class="nav-icon">🧾</span>
          <span v-if="!sidebarCollapsed">Local Purchase Orders</span>
        </Link>
        <Link href="/admin/procurement/grn" class="nav-item">
          <span class="nav-icon">📥</span>
          <span v-if="!sidebarCollapsed">Inward GRN Receiving</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">OPERATIONS & CATALOG</div>
        <Link href="/admin/products" class="nav-item">
          <span class="nav-icon">📦</span>
          <span v-if="!sidebarCollapsed">Product Catalog</span>
        </Link>
        <Link href="/admin/products/create" class="nav-item nav-item-highlight">
          <span class="nav-icon">➕</span>
          <span v-if="!sidebarCollapsed">Add New Product</span>
        </Link>
        <Link href="/admin/orders" class="nav-item">
          <span class="nav-icon">🛍️</span>
          <span v-if="!sidebarCollapsed">Orders & Anti-Fraud</span>
        </Link>
        <Link href="/admin/settings/couriers" class="nav-item">
          <span class="nav-icon">🚚</span>
          <span v-if="!sidebarCollapsed">Couriers & Logistics</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">PAYMENTS & SETTINGS</div>
        <Link href="/admin/settings/payment-gateways" class="nav-item">
          <span class="nav-icon">💳</span>
          <span v-if="!sidebarCollapsed">Payment Gateways</span>
        </Link>
        <Link href="/admin/settings/bot-security" class="nav-item">
          <span class="nav-icon">🛡️</span>
          <span v-if="!sidebarCollapsed">Bot Security & Captcha</span>
        </Link>
        <Link href="/admin/roles" class="nav-item">
          <span class="nav-icon">👥</span>
          <span v-if="!sidebarCollapsed">Roles & Permissions</span>
        </Link>
        <Link href="/admin/staff" class="nav-item">
          <span class="nav-icon">👤</span>
          <span v-if="!sidebarCollapsed">Staff Management</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">COMMUNICATION & MARKETING</div>
        <Link href="/admin/communication/mail-settings" class="nav-item">
          <span class="nav-icon">📧</span>
          <span v-if="!sidebarCollapsed">SMTP Mail Settings</span>
        </Link>
        <Link href="/admin/communication/sms-settings" class="nav-item">
          <span class="nav-icon">📱</span>
          <span v-if="!sidebarCollapsed">SMS Gateways & OTP</span>
        </Link>
        <Link href="/admin/communication/voice-settings" class="nav-item">
          <span class="nav-icon">📞</span>
          <span v-if="!sidebarCollapsed">AI Voice Verification</span>
        </Link>
        <Link href="/admin/marketing/cart-recovery" class="nav-item">
          <span class="nav-icon">🛒</span>
          <span v-if="!sidebarCollapsed">Cart Recovery Sequencer</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">TAX & NBR COMPLIANCE</div>
        <Link href="/admin/tax-compliance" class="nav-item">
          <span class="nav-icon">🏛️</span>
          <span v-if="!sidebarCollapsed">Tax Overview</span>
        </Link>
        <Link href="/admin/tax-compliance/mushak-6-3" class="nav-item">
          <span class="nav-icon">🧾</span>
          <span v-if="!sidebarCollapsed">Mushak 6.3 (Invoices)</span>
        </Link>
        <Link href="/admin/tax-compliance/mushak-9-1" class="nav-item">
          <span class="nav-icon">📊</span>
          <span v-if="!sidebarCollapsed">Mushak 9.1 (Returns)</span>
        </Link>
        <Link href="/admin/tax-compliance/mushak-6-6" class="nav-item">
          <span class="nav-icon">📑</span>
          <span v-if="!sidebarCollapsed">Mushak 6.6 (VDS)</span>
        </Link>
        <Link href="/admin/tax-compliance/rma" class="nav-item">
          <span class="nav-icon">🔄</span>
          <span v-if="!sidebarCollapsed">RMA & Credit Notes</span>
        </Link>
        <Link href="/admin/tax-compliance/registers" class="nav-item">
          <span class="nav-icon">📖</span>
          <span v-if="!sidebarCollapsed">Mushak 6.1 / 6.2</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">AI INTELLIGENCE</div>
        <Link href="/admin/ai-settings" class="nav-item">
          <span class="nav-icon">🤖</span>
          <span v-if="!sidebarCollapsed">AI Engine Settings</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">ANALYTICS & AUDIT</div>
        <Link href="/admin/analytics/trading-pnl" class="nav-item">
          <span class="nav-icon">📈</span>
          <span v-if="!sidebarCollapsed">Trading P&L</span>
        </Link>
        <Link href="/admin/audit-logs" class="nav-item">
          <span class="nav-icon">📋</span>
          <span v-if="!sidebarCollapsed">Audit Trail</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">ADMINISTRATION</div>
        <Link href="/admin/roles" class="nav-item">
          <span class="nav-icon">🛡️</span>
          <span v-if="!sidebarCollapsed">Roles & Staff RBAC</span>
        </Link>
        <Link href="/admin/settings/media-storage" class="nav-item">
          <span class="nav-icon">☁️</span>
          <span v-if="!sidebarCollapsed">Multi-Cloud Storage</span>
        </Link>
        <Link href="/admin/settings/system" class="nav-item">
          <span class="nav-icon">⚙️</span>
          <span v-if="!sidebarCollapsed">System Info & Updates</span>
        </Link>
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
  background-color: $color-neutral-100;
}

.admin-sidebar {
  width: 260px;
  background-color: $color-neutral-900;
  color: $color-neutral-300;
  display: flex;
  flex-direction: column;
  transition: width $transition-base;
  flex-shrink: 0;

  .sidebar-header {
    height: 64px;
    padding: 0 $space-5;
    @include flex-between;
    border-bottom: 1px solid $color-neutral-800;

    .sidebar-brand {
      @include flex-start;
      gap: $space-3;
      font-family: $font-heading;
      font-weight: $fw-bold;
      font-size: $fs-lg;
      color: $color-neutral-0;

      .brand-badge {
        font-size: $fs-xl;
      }
    }

    .sidebar-toggle-btn {
      color: $color-neutral-400;
      font-size: $fs-sm;
      padding: $space-1 $space-2;
      border-radius: $radius-sm;

      &:hover {
        background-color: $color-neutral-800;
        color: $color-neutral-0;
      }

      &.nav-item-highlight {
        color: $color-primary-400;
        background-color: rgba($color-primary-500, 0.12);
        font-weight: $fw-semibold;

        &:hover {
          background-color: rgba($color-primary-500, 0.22);
          color: $color-primary-300;
        }
      }
    }
  }

  .sidebar-nav {
    padding: $space-4 $space-3;
    display: flex;
    flex-direction: column;
    gap: $space-1;
    overflow-y: auto;

    .nav-section-title {
      font-size: $fs-2xs;
      font-weight: $fw-bold;
      color: $color-neutral-500;
      letter-spacing: 0.08em;
      padding: $space-3 $space-3 $space-1;
    }

    .nav-item {
      @include flex-start;
      gap: $space-3;
      padding: $space-2 $space-3;
      border-radius: $radius-lg;
      font-size: $fs-sm;
      font-weight: $fw-medium;
      color: $color-neutral-300;
      transition: all $transition-fast;

      .nav-icon {
        font-size: $fs-base;
      }

      &:hover {
        background-color: $color-neutral-800;
        color: $color-neutral-0;
      }

      &.nav-item-highlight {
        color: $color-primary-400;
        background-color: rgba($color-primary-500, 0.12);
        font-weight: $fw-semibold;

        &:hover {
          background-color: rgba($color-primary-500, 0.22);
          color: $color-primary-300;
        }
      }
    }
  }

  .sidebar-footer {
    margin-top: auto;
    padding: $space-4 $space-3;
    border-top: 1px solid $color-neutral-800;
    background-color: rgba($color-neutral-950, 0.4);
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
        background: $color-neutral-800;
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
          color: $color-neutral-100;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .sidebar-user-email {
          font-size: $fs-2xs;
          color: $color-neutral-400;
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
      background: rgba($color-danger, 0.12);
      color: #fca5a5;
      border: 1px solid rgba($color-danger, 0.25);
      border-radius: $radius-lg;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover:not(:disabled) {
        background: rgba($color-danger, 0.25);
        color: #ffffff;
        border-color: $color-danger;
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
}

.admin-main-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.admin-topbar {
  height: 64px;
  background-color: $color-neutral-0;
  border-bottom: 1px solid $color-neutral-200;
  padding: 0 $space-6;
  @include flex-between;

  .page-title {
    font-size: $fs-xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }

  .topbar-right {
    @include flex-start;
    gap: $space-4;

    .version-tag {
      font-size: $fs-xs;
    }

    .user-profile {
      @include flex-start;
      gap: $space-2;
      font-size: $fs-sm;
      font-weight: $fw-medium;
      color: $color-neutral-700;
    }

    .topbar-storefront-link {
      display: inline-flex;
      align-items: center;
      gap: $space-1;
      font-size: $fs-xs;
      font-weight: $fw-medium;
      color: $color-neutral-600;
      padding: 4px $space-3;
      border-radius: $radius-full;
      background: $color-neutral-100;
      text-decoration: none;
      border: 1px solid $color-neutral-200;
      transition: all $transition-fast;

      &:hover {
        background: $color-primary-50;
        color: $color-primary-700;
        border-color: $color-primary-300;
      }
    }

    .topbar-logout-btn {
      display: inline-flex;
      align-items: center;
      gap: $space-1;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: $color-neutral-700;
      padding: 6px $space-3;
      border-radius: $radius-lg;
      background: $color-neutral-100;
      border: 1px solid $color-neutral-300;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover:not(:disabled) {
        background: rgba($color-danger, 0.1);
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
      background-color: hsl(142, 76%, 94%);
      color: hsl(142, 76%, 24%);
    }

    &-danger {
      background-color: hsl(0, 86%, 95%);
      color: hsl(0, 72%, 35%);
    }
  }
}

.admin-content {
  padding: $space-6;
  flex: 1;
}
</style>
