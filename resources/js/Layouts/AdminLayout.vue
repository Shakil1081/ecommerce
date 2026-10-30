<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

defineProps<{
  title?: string;
}>();

const page = usePage<SharedProps>();
const sidebarCollapsed = ref(false);

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value;
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

        <div class="nav-section-title" v-if="!sidebarCollapsed">OPERATIONS</div>
        <Link href="/admin/dashboard" class="nav-item">
          <span class="nav-icon">📊</span>
          <span v-if="!sidebarCollapsed">Dashboard</span>
        </Link>
        <Link href="/admin/catalog/products" class="nav-item">
          <span class="nav-icon">📦</span>
          <span v-if="!sidebarCollapsed">Catalog & Matrix</span>
        </Link>
        <Link href="/admin/inventory/stocks" class="nav-item">
          <span class="nav-icon">🏭</span>
          <span v-if="!sidebarCollapsed">Warehouse Inventory</span>
        </Link>
        <Link href="/admin/orders" class="nav-item">
          <span class="nav-icon">🛍️</span>
          <span v-if="!sidebarCollapsed">Orders & Anti-Fraud</span>
        </Link>
        <Link href="/admin/logistics/couriers" class="nav-item">
          <span class="nav-icon">🚚</span>
          <span v-if="!sidebarCollapsed">Couriers & Logistics</span>
        </Link>

        <div class="nav-section-title" v-if="!sidebarCollapsed">FINANCE & TAX</div>
        <Link href="/admin/tax/mushak-6-3" class="nav-item">
          <span class="nav-icon">🧾</span>
          <span v-if="!sidebarCollapsed">NBR VAT Mushak 6.3</span>
        </Link>
        <Link href="/admin/payment/gateways" class="nav-item">
          <span class="nav-icon">💳</span>
          <span v-if="!sidebarCollapsed">Payment Gateways</span>
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
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main-wrap">
      <!-- Topbar Header -->
      <header class="admin-topbar">
        <div class="topbar-left">
          <h2 class="page-title">{{ title || 'Dashboard' }}</h2>
        </div>
        <div class="topbar-right">
          <span class="version-tag badge badge-primary">v0.1.0 Alpha</span>
          <div class="user-profile">
            <span class="user-avatar">👤</span>
            <span class="user-name">{{ page.props.auth?.user?.name || 'Administrator' }}</span>
          </div>
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
