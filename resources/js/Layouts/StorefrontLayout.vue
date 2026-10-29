<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

defineProps<{
  title?: string;
  metaDescription?: string;
}>();

const page = usePage<SharedProps>();
const mobileMenuOpen = ref(false);
const currentLocale = ref(page.props.app?.locale || 'en');

const toggleLocale = () => {
  currentLocale.value = currentLocale.value === 'en' ? 'bn' : 'en';
};
</script>

<template>
  <div>
    <Head :title="title">
      <meta v-if="metaDescription" name="description" :content="metaDescription">
    </Head>

    <!-- Top Announcement Bar -->
    <div class="top-announcement-bar">
      <div class="container announcement-content">
        <span>🚀 {{ currentLocale === 'bn' ? 'সারা বাংলাদেশে দ্রুততম হোম ডেলিভারি ও ক্যাশ অন ডেলিভারি!' : 'Fast Nationwide Cash on Delivery Across Bangladesh!' }}</span>
        <button class="locale-toggle-btn" @click="toggleLocale">
          {{ currentLocale === 'bn' ? 'English (EN)' : 'বাংলা (BN)' }}
        </button>
      </div>
    </div>

    <!-- Storefront Navigation Header -->
    <header class="storefront-header">
      <div class="container header-container">
        <!-- Logo -->
        <Link href="/" class="brand-logo">
          <span class="logo-accent">E</span>COMMERCE
        </Link>

        <!-- Search Bar Placeholder (Instant Search in Phase 8) -->
        <div class="search-container">
          <input
            type="search"
            :placeholder="currentLocale === 'bn' ? 'পণ্য, ব্র্যান্ড বা এসকেইউ খুঁজুন...' : 'Search products, brands or SKU...'"
            class="header-search-input"
          >
        </div>

        <!-- Navigation Actions -->
        <nav class="header-nav-actions">
          <Link href="/cart" class="header-action-btn cart-btn">
            <span class="action-icon">🛒</span>
            <span class="action-label">{{ currentLocale === 'bn' ? 'কার্ট' : 'Cart' }}</span>
          </Link>
          <Link href="/login" class="header-action-btn">
            <span class="action-icon">👤</span>
            <span class="action-label">{{ currentLocale === 'bn' ? 'লগইন' : 'Account' }}</span>
          </Link>
        </nav>
      </div>
    </header>

    <!-- Global Flash Message Alerts -->
    <div v-if="page.props.flash?.success || page.props.flash?.error" class="container flash-container">
      <div v-if="page.props.flash?.success" class="alert alert-success">
        {{ page.props.flash.success }}
      </div>
      <div v-if="page.props.flash?.error" class="alert alert-danger">
        {{ page.props.flash.error }}
      </div>
    </div>

    <!-- Main Storefront Content -->
    <main class="storefront-main">
      <slot />
    </main>

    <!-- Storefront Footer -->
    <footer class="storefront-footer">
      <div class="container footer-grid">
        <div class="footer-col">
          <h4 class="footer-heading">Enterprise E-Commerce</h4>
          <p class="footer-text">
            {{ currentLocale === 'bn' ? '১০০% আসল আমদানিকৃত এবং স্থানীয় পণ্য। এনবিআর মূসক ৬.৩ চালান ও অফিসিয়াল ওয়ারেন্টি সহ।' : '100% Genuine imported & domestic procurement with statutory NBR VAT compliance and official warranties.' }}
          </p>
        </div>
        <div class="footer-col">
          <h4 class="footer-heading">Quick Links</h4>
          <ul class="footer-links">
            <li><Link href="/track-order">Track Order (অর্ডার ট্র্যাকিং)</Link></li>
            <li><Link href="/returns">Returns & Exchange (ফেরত নীতি)</Link></li>
            <li><Link href="/b2b">Wholesale Trade (পাইকারি একাউন্ট)</Link></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4 class="footer-heading">Accepted Payments & Logistics</h4>
          <div class="partner-badges">
            <span class="badge badge-secondary">bKash</span>
            <span class="badge badge-secondary">Nagad</span>
            <span class="badge badge-secondary">Steadfast</span>
            <span class="badge badge-secondary">Pathao</span>
          </div>
        </div>
      </div>
      <div class="container footer-bottom">
        <p>&copy; 2026 Enterprise E-Commerce Platform. All rights reserved. NBR BIN Compliant.</p>
      </div>
    </footer>
  </div>
</template>

<style lang="scss" scoped>
.top-announcement-bar {
  background-color: $color-secondary-900;
  color: $color-neutral-0;
  font-size: $fs-xs;
  padding: $space-2 0;

  .announcement-content {
    @include flex-between;
  }

  .locale-toggle-btn {
    color: $color-accent-400;
    font-weight: $fw-semibold;
    text-decoration: underline;
    cursor: pointer;
  }
}

.storefront-header {
  background-color: $color-neutral-0;
  border-bottom: 1px solid $color-neutral-200;
  position: sticky;
  top: 0;
  z-index: $z-sticky;

  .header-container {
    @include flex-between;
    height: 72px;
    gap: $space-6;
  }

  .brand-logo {
    font-family: $font-heading;
    font-size: $fs-2xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    letter-spacing: -0.03em;

    .logo-accent {
      color: $color-primary-600;
    }
  }

  .search-container {
    flex: 1;
    max-width: 580px;

    .header-search-input {
      width: 100%;
      height: 42px;
      padding: 0 $space-4;
      border: 1px solid $color-neutral-300;
      border-radius: $radius-full;
      background-color: $color-neutral-50;
      font-size: $fs-sm;
      @include focus-ring($color-primary-500);
    }
  }

  .header-nav-actions {
    display: flex;
    align-items: center;
    gap: $space-4;

    .header-action-btn {
      @include flex-center;
      gap: $space-2;
      font-size: $fs-sm;
      font-weight: $fw-medium;
      color: $color-neutral-700;
      padding: $space-2 $space-3;
      border-radius: $radius-lg;
      transition: background-color $transition-fast;

      &:hover {
        background-color: $color-neutral-100;
        color: $color-neutral-900;
      }
    }
  }
}

.flash-container {
  margin-top: $space-4;

  .alert {
    padding: $space-3 $space-5;
    border-radius: $radius-lg;
    font-size: $fs-sm;
    font-weight: $fw-medium;

    &-success {
      background-color: hsl(142, 76%, 94%);
      color: hsl(142, 76%, 24%);
      border: 1px solid hsl(142, 76%, 80%);
    }

    &-danger {
      background-color: hsl(0, 86%, 95%);
      color: hsl(0, 72%, 35%);
      border: 1px solid hsl(0, 86%, 85%);
    }
  }
}

.storefront-main {
  min-height: calc(100vh - 350px);
}

.storefront-footer {
  background-color: $color-neutral-900;
  color: $color-neutral-300;
  padding: $space-12 0 $space-6;
  margin-top: $space-16;

  .footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: $space-8;

    @include media-down(md) {
      grid-template-columns: 1fr;
    }
  }

  .footer-heading {
    color: $color-neutral-0;
    font-size: $fs-base;
    margin-bottom: $space-4;
  }

  .footer-text {
    font-size: $fs-sm;
    line-height: $lh-relaxed;
    max-width: 380px;
  }

  .footer-links {
    @include flex-column;
    gap: $space-2;

    a {
      font-size: $fs-sm;
      transition: color $transition-fast;

      &:hover {
        color: $color-primary-400;
      }
    }
  }

  .partner-badges {
    display: flex;
    gap: $space-2;
    flex-wrap: wrap;
  }

  .footer-bottom {
    margin-top: $space-10;
    padding-top: $space-6;
    border-top: 1px solid $color-neutral-800;
    text-align: center;
    font-size: $fs-xs;
    color: $color-neutral-500;
  }
}
</style>
