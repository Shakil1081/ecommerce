<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import type { SharedProps, NavCategoryItem } from '@/types';
import CartDrawer from '@/Components/Cart/CartDrawer.vue';
import { useCartStore } from '@/Stores/cartStore';

defineProps<{
  title?: string;
  metaDescription?: string;
}>();

const page = usePage<SharedProps>();
const cartStore = useCartStore();
const currentLocale = ref(page.props.app?.locale || 'en');
const headerSearchQuery = ref('');

// Category Menu Dropdown State
const isCatMenuOpen = ref(false);
const mobileCatDrawerOpen = ref(false);
let catMenuTimer: ReturnType<typeof setTimeout> | null = null;

const handleCatMouseEnter = () => {
  if (catMenuTimer) {
    clearTimeout(catMenuTimer);
    catMenuTimer = null;
  }
  isCatMenuOpen.value = true;
};

const handleCatMouseLeave = () => {
  catMenuTimer = setTimeout(() => {
    isCatMenuOpen.value = false;
  }, 160);
};

const toggleCatMenu = () => {
  isCatMenuOpen.value = !isCatMenuOpen.value;
};

const toggleLocale = () => {
  currentLocale.value = currentLocale.value === 'en' ? 'bn' : 'en';
};

const handleHeaderSearch = () => {
  const q = headerSearchQuery.value.trim();
  if (!q) return;
  router.visit(`/shop?search=${encodeURIComponent(q)}`);
};

// Categories list from shared props or default fallback
const categories = computed<NavCategoryItem[]>(() => {
  const shared = (page.props as any).categories as NavCategoryItem[] | undefined;
  if (shared && shared.length > 0) {
    return shared;
  }

  return [
    { id: '1', name: 'Smartphones & Accessories', slug: 'smartphones-mobile-accessories', is_featured: true, products_count: 8, children: [] },
    { id: '2', name: 'Consumer Electronics', slug: 'consumer-electronics', is_featured: true, products_count: 14, children: [] },
    { id: '3', name: 'Computers & Office IT', slug: 'computers-office-it', is_featured: true, products_count: 6, children: [] },
    { id: '4', name: 'Home & Kitchen Appliances', slug: 'home-kitchen-appliances', is_featured: true, products_count: 11, children: [] },
    { id: '5', name: 'Fashion & Lifestyle', slug: 'fashion-lifestyle', is_featured: true, products_count: 19, children: [] },
    { id: '6', name: 'Ladies Bags & Handbags', slug: 'ladies-bags', is_featured: true, products_count: 12, children: [] },
  ];
});

// Category helper: App-style icon
const getCategoryIcon = (slug: string): string => {
  const s = slug.toLowerCase();
  if (s.includes('phone') || s.includes('mobile')) return '📱';
  if (s.includes('electronic') || s.includes('audio') || s.includes('sound') || s.includes('headphone')) return '🎧';
  if (s.includes('computer') || s.includes('laptop') || s.includes('it')) return '💻';
  if (s.includes('kitchen') || s.includes('appliance') || s.includes('home')) return '🍳';
  if (s.includes('bag') || s.includes('handbag') || s.includes('purse')) return '👜';
  if (s.includes('fashion') || s.includes('cloth') || s.includes('wear') || s.includes('shoe')) return '👗';
  if (s.includes('beauty') || s.includes('cosmetic')) return '💄';
  if (s.includes('watch') || s.includes('jewelry')) return '⌚';
  return '🛍️';
};

// Category helper: Bangla title
const getCategoryBn = (name: string, slug: string): string => {
  const s = slug.toLowerCase();
  if (s.includes('phone') || s.includes('mobile')) return 'স্মার্টফোন ও গ্যাজেট';
  if (s.includes('electronic')) return 'কনজিউমার ইলেকট্রনিক্স';
  if (s.includes('computer') || s.includes('it')) return 'কম্পিউটার ও আইটি';
  if (s.includes('kitchen') || s.includes('home')) return 'হোম ও কিচেন অ্যাপ্লায়েন্স';
  if (s.includes('bag')) return 'লেডিস ব্যাগ ও হ্যান্ডব্যাগ';
  if (s.includes('fashion')) return 'ফ্যাশন ও লাইফস্টাইল';
  return name;
};

// Category helper: App theme gradient
const getCategoryThemeClass = (slug: string): string => {
  const s = slug.toLowerCase();
  if (s.includes('phone') || s.includes('mobile')) return 'theme-coral';
  if (s.includes('electronic')) return 'theme-purple';
  if (s.includes('computer') || s.includes('it')) return 'theme-blue';
  if (s.includes('kitchen') || s.includes('home')) return 'theme-amber';
  if (s.includes('bag')) return 'theme-rose';
  if (s.includes('fashion')) return 'theme-emerald';
  return 'theme-indigo';
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

        <!-- Search Bar -->
        <div class="search-container">
          <form @submit.prevent="handleHeaderSearch" class="header-search-form">
            <span class="search-icon">🔍</span>
            <input
              v-model="headerSearchQuery"
              type="search"
              :placeholder="currentLocale === 'bn' ? 'পণ্য, ব্র্যান্ড বা এসকেইউ খুঁজুন...' : 'Search products, brands or SKU...'"
              class="header-search-input"
            >
          </form>
        </div>

        <!-- Main Storefront Nav Links -->
        <nav class="header-nav-links">
          <!-- App-Style Category Menu Trigger with Hover Dropdown -->
          <div
            class="cat-menu-wrapper"
            @mouseenter="handleCatMouseEnter"
            @mouseleave="handleCatMouseLeave"
          >
            <button
              type="button"
              class="header-nav-link cat-trigger-btn"
              :class="{ 'is-open': isCatMenuOpen }"
              @click="toggleCatMenu"
              aria-haspopup="true"
              :aria-expanded="isCatMenuOpen"
            >
              <span class="cat-grid-icon">
                <span class="grid-square"></span>
                <span class="grid-square"></span>
                <span class="grid-square"></span>
                <span class="grid-square"></span>
              </span>
              <span class="cat-btn-label">{{ currentLocale === 'bn' ? 'ক্যাটাগরি' : 'Categories' }}</span>
              <span class="cat-chevron" :class="{ 'rotated': isCatMenuOpen }">▾</span>
            </button>

            <!-- Desktop App-Style Mega Dropdown -->
            <transition name="dropdown-fade">
              <div
                v-if="isCatMenuOpen"
                class="app-mega-dropdown"
                @mouseenter="handleCatMouseEnter"
                @mouseleave="handleCatMouseLeave"
              >
                <!-- Mega Dropdown Header -->
                <div class="mega-header">
                  <div class="header-left">
                    <span class="app-badge-icon">🗂️</span>
                    <div>
                      <h4 class="mega-heading">{{ currentLocale === 'bn' ? 'সকল ক্যাটাগরি' : 'All Categories' }}</h4>
                      <p class="mega-sub">{{ currentLocale === 'bn' ? 'মোবাইল অ্যাপ স্টাইল পণ্য ব্রাউজিং' : 'Browse genuine imported & local products' }}</p>
                    </div>
                  </div>
                  <Link href="/shop" class="view-all-shop-link" @click="isCatMenuOpen = false">
                    <span>{{ currentLocale === 'bn' ? 'সকল পণ্য দেখুন' : 'Explore All Products' }}</span>
                    <span class="arrow-sym">→</span>
                  </Link>
                </div>

                <!-- 3-Column App-Style Category Grid -->
                <div class="mega-cards-grid">
                  <Link
                    v-for="cat in categories"
                    :key="cat.id"
                    :href="`/shop?category=${cat.slug}`"
                    class="app-category-card"
                    @click="isCatMenuOpen = false"
                  >
                    <div class="card-icon-container" :class="getCategoryThemeClass(cat.slug)">
                      <span class="card-emoji">{{ getCategoryIcon(cat.slug) }}</span>
                    </div>
                    <div class="card-text-container">
                      <div class="card-title-row">
                        <span class="card-title">{{ cat.name }}</span>
                        <span v-if="cat.is_featured" class="hot-badge">HOT</span>
                      </div>
                      <span class="card-subtitle-bn">{{ getCategoryBn(cat.name, cat.slug) }}</span>
                      <div class="card-meta">
                        <span class="card-count">{{ cat.products_count ? `${cat.products_count}+ Products` : 'In Stock' }}</span>
                        <span class="card-arrow-link">Explore →</span>
                      </div>
                    </div>
                  </Link>
                </div>

                <!-- Mega Dropdown Footer Bar -->
                <div class="mega-footer-banner">
                  <div class="footer-perk">
                    <span class="perk-sym">🚚</span>
                    <span>{{ currentLocale === 'bn' ? 'সারা বাংলাদেশে ফ্রি ডেলিভারি (৳২,০০০+ অর্ডারে)' : 'Free delivery nationwide on orders ৳2,000+' }}</span>
                  </div>
                  <div class="footer-perk">
                    <span class="perk-sym">💵</span>
                    <span>{{ currentLocale === 'bn' ? 'ক্যাশ অন ডেলিভারি (পণ্য হাতে পেয়ে টাকা)' : 'Cash on Delivery & Instant Mobile Banking' }}</span>
                  </div>
                </div>
              </div>
            </transition>
          </div>

          <Link href="/shop" class="header-nav-link">
            <span>🛍️</span> {{ currentLocale === 'bn' ? 'শপ' : 'Shop Catalog' }}
          </Link>
          <Link href="/track-order" class="header-nav-link">
            <span>🚚</span> {{ currentLocale === 'bn' ? 'অর্ডার ট্র্যাকিং' : 'Track Order' }}
          </Link>
          <Link href="/admin" class="header-nav-link admin-pill-link">
            <span>⚡</span> {{ currentLocale === 'bn' ? 'অ্যাডমিন হাব' : 'Admin Hub' }}
          </Link>
        </nav>

        <!-- Navigation Actions -->
        <nav class="header-nav-actions">
          <!-- Mobile App Category Trigger Button (Visible on screens < 900px) -->
          <button
            type="button"
            class="header-action-btn mobile-cat-trigger-btn"
            @click="mobileCatDrawerOpen = true"
            :title="currentLocale === 'bn' ? 'ক্যাটাগরি' : 'Categories'"
          >
            <span class="action-icon">☰</span>
            <span class="action-label">{{ currentLocale === 'bn' ? 'ক্যাটাগরি' : 'Categories' }}</span>
          </button>

          <button type="button" class="header-action-btn cart-btn" @click="cartStore.openDrawer">
            <span class="action-icon">🛒</span>
            <span class="action-label">{{ currentLocale === 'bn' ? 'কার্ট' : 'Cart' }}</span>
            <span v-if="cartStore.totalItemsCount > 0" class="cart-badge-count">{{ cartStore.totalItemsCount }}</span>
          </button>
        </nav>
      </div>
    </header>

    <!-- Mobile App Category Drawer / Bottom Sheet -->
    <teleport to="body">
      <transition name="drawer-fade">
        <div
          v-if="mobileCatDrawerOpen"
          class="mobile-cat-overlay"
          @click="mobileCatDrawerOpen = false"
        >
          <div class="mobile-cat-sheet" @click.stop>
            <!-- Sheet Header -->
            <div class="sheet-header">
              <div class="sheet-title-group">
                <span class="sheet-logo-accent">E</span>
                <div>
                  <h3 class="sheet-heading">{{ currentLocale === 'bn' ? 'সকল ক্যাটাগরি' : 'All Categories' }}</h3>
                  <p class="sheet-sub">{{ currentLocale === 'bn' ? 'মোবাইল অ্যাপ শপিং মেনু' : 'App-Style Category Explorer' }}</p>
                </div>
              </div>
              <button
                type="button"
                class="sheet-close-btn"
                @click="mobileCatDrawerOpen = false"
                aria-label="Close"
              >
                ✕
              </button>
            </div>

            <!-- Mobile Quick Nav Chips -->
            <div class="sheet-quick-nav">
              <Link href="/shop" class="quick-chip" @click="mobileCatDrawerOpen = false">
                <span>🛍️</span> {{ currentLocale === 'bn' ? 'শপ ক্যাটালগ' : 'Shop' }}
              </Link>
              <Link href="/track-order" class="quick-chip" @click="mobileCatDrawerOpen = false">
                <span>🚚</span> {{ currentLocale === 'bn' ? 'অর্ডার ট্র্যাকিং' : 'Track Order' }}
              </Link>
              <Link href="/admin" class="quick-chip admin-chip" @click="mobileCatDrawerOpen = false">
                <span>⚡</span> {{ currentLocale === 'bn' ? 'অ্যাডমিন হাব' : 'Admin Hub' }}
              </Link>
            </div>

            <!-- Mobile App Category Grid -->
            <div class="sheet-grid-container">
              <h4 class="sheet-section-title">
                {{ currentLocale === 'bn' ? 'ক্যাটাগরি বেছে নিন' : 'Select Category' }}
              </h4>
              <div class="sheet-app-grid">
                <Link
                  v-for="cat in categories"
                  :key="cat.id"
                  :href="`/shop?category=${cat.slug}`"
                  class="mobile-app-card"
                  @click="mobileCatDrawerOpen = false"
                >
                  <div class="mobile-icon-box" :class="getCategoryThemeClass(cat.slug)">
                    <span class="mobile-emoji">{{ getCategoryIcon(cat.slug) }}</span>
                  </div>
                  <div class="mobile-card-details">
                    <span class="m-title">{{ cat.name }}</span>
                    <span class="m-bn">{{ getCategoryBn(cat.name, cat.slug) }}</span>
                    <span class="m-count">{{ cat.products_count ? `${cat.products_count}+ items` : 'In Stock' }}</span>
                  </div>
                </Link>
              </div>
            </div>

            <!-- Sheet Bottom CTA -->
            <div class="sheet-footer">
              <Link href="/shop" class="sheet-btn-view-all" @click="mobileCatDrawerOpen = false">
                🛍️ {{ currentLocale === 'bn' ? 'সকল পণ্য দেখুন (Browse All)' : 'Explore All Products' }}
              </Link>
            </div>
          </div>
        </div>
      </transition>
    </teleport>

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
            <li><Link href="/shop">Shop Catalog (শপ ক্যাটালগ)</Link></li>
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

    <!-- Interactive Slide-Out Cart Drawer -->
    <CartDrawer />
  </div>
</template>

<style lang="scss" scoped>
.top-announcement-bar {
  background: linear-gradient(90deg, $color-primary-100 0%, $color-primary-50 50%, $color-primary-100 100%);
  color: $color-primary-900;
  border-bottom: none;
  font-size: $fs-xs;
  font-weight: $fw-semibold;
  padding: $space-2 0;

  .announcement-content {
    @include flex-between;
  }

  .locale-toggle-btn {
    color: $color-primary-700;
    font-weight: $fw-bold;
    text-decoration: underline;
    cursor: pointer;
    background: transparent;
    border: none;
    font-size: $fs-xs;

    &:hover {
      color: $color-primary-900;
    }
  }
}

.storefront-header {
  background-color: $color-neutral-0;
  border-bottom: none;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  position: sticky;
  top: 0;
  z-index: $z-sticky;

  .header-container {
    @include flex-between;
    height: 72px;
    gap: $space-4;

    @media (max-width: 900px) {
      height: 64px;
      gap: $space-2;
    }
  }

  .brand-logo {
    font-family: $font-heading;
    font-size: $fs-2xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    letter-spacing: -0.03em;
    flex-shrink: 0;
    text-decoration: none;

    @media (max-width: 640px) {
      font-size: $fs-xl;
    }

    .logo-accent {
      color: $color-primary-600;
    }
  }

  .search-container {
    flex: 1;
    max-width: 360px;

    @media (max-width: 640px) {
      max-width: 180px;
    }

    .header-search-form {
      position: relative;
      width: 100%;
      display: flex;
      align-items: center;

      .search-icon {
        position: absolute;
        left: 12px;
        font-size: 14px;
        color: $color-neutral-400;
        pointer-events: none;
      }

      .header-search-input {
        width: 100%;
        height: 42px;
        padding-left: 36px;
        padding-right: $space-4;
        border: 1px solid $color-neutral-300;
        border-radius: $radius-full;
        background-color: $color-neutral-50;
        font-size: $fs-sm;
        transition: all $transition-fast;

        &:focus {
          outline: none;
          background-color: #ffffff;
          border-color: $color-primary-500;
          box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
        }

        @media (max-width: 640px) {
          height: 38px;
          font-size: $fs-xs;
          padding-left: 30px;
          padding-right: $space-2;
        }
      }
    }
  }

  .header-nav-links {
    display: flex;
    align-items: center;
    gap: $space-3;

    @media (max-width: 960px) {
      display: none;
    }

    .header-nav-link {
      display: inline-flex;
      align-items: center;
      gap: $space-2;
      font-size: $fs-sm;
      font-weight: $fw-bold;
      color: $color-neutral-700;
      text-decoration: none;
      padding: $space-2 $space-3;
      border-radius: $radius-lg;
      transition: all $transition-fast;

      &:hover {
        color: $color-primary-600;
        background-color: $color-primary-50;
      }

      &.admin-pill-link {
        padding: 5px $space-3;
        border-radius: $radius-full;
        background: $color-neutral-100;
        color: $color-primary-700;
        border: 1px solid $color-neutral-300;

        &:hover {
          background: $color-primary-50;
          border-color: $color-primary-300;
        }
      }
    }
  }

  // --- Category Menu Hover & Dropdown Styles ---
  .cat-menu-wrapper {
    position: relative;

    .cat-trigger-btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      font-size: $fs-sm;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      background: hsl(342, 60%, 96%);
      border: 1.5px solid hsl(342, 60%, 86%);
      padding: 7px 14px;
      border-radius: $radius-full;
      cursor: pointer;
      transition: all $transition-fast;

      .cat-grid-icon {
        display: grid;
        grid-template-columns: repeat(2, 6px);
        grid-gap: 2.5px;
        align-items: center;
        justify-content: center;

        .grid-square {
          width: 6px;
          height: 6px;
          background-color: $color-primary-600;
          border-radius: 1.5px;
          transition: transform $transition-fast, background-color $transition-fast;
        }
      }

      .cat-chevron {
        font-size: 11px;
        color: $color-neutral-500;
        transition: transform 0.25s ease;

        &.rotated {
          transform: rotate(180deg);
        }
      }

      &:hover,
      &.is-open {
        background-color: $color-primary-500;
        border-color: $color-primary-600;
        color: #ffffff;
        box-shadow: 0 3px 12px rgba(235, 40, 108, 0.25);

        .cat-grid-icon .grid-square {
          background-color: #ffffff;
        }

        .cat-chevron {
          color: #ffffff;
        }
      }
    }

    // --- App-Style Desktop Mega Dropdown ---
    .app-mega-dropdown {
      position: absolute;
      top: calc(100% + 10px);
      left: -10px;
      width: 740px;
      max-width: calc(100vw - 32px);
      background: #ffffff;
      border-radius: 20px;
      border: 1px solid $color-neutral-200;
      box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.16), 0 0 1px 1px rgba(0, 0, 0, 0.04);
      z-index: $z-popover;
      overflow: hidden;

      .mega-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        background: linear-gradient(135deg, hsl(342, 60%, 98%) 0%, #ffffff 100%);
        border-bottom: 1px solid $color-neutral-200;

        .header-left {
          display: flex;
          align-items: center;
          gap: $space-3;

          .app-badge-icon {
            font-size: 24px;
          }

          .mega-heading {
            font-size: $fs-base;
            font-weight: $fw-bold;
            color: $color-neutral-900;
            margin: 0;
            line-height: 1.2;
          }

          .mega-sub {
            font-size: $fs-xs;
            color: $color-neutral-500;
            margin: 0;
          }
        }

        .view-all-shop-link {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          font-size: $fs-xs;
          font-weight: $fw-bold;
          color: $color-primary-600;
          text-decoration: none;
          padding: 6px 12px;
          border-radius: $radius-full;
          background: $color-primary-50;
          border: 1px solid $color-primary-200;
          transition: all $transition-fast;

          &:hover {
            background: $color-primary-600;
            color: #ffffff;
            border-color: $color-primary-600;
          }
        }
      }

      .mega-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding: 18px 20px;
        background-color: #ffffff;

        .app-category-card {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 10px 12px;
          border-radius: 14px;
          border: 1.5px solid $color-neutral-100;
          background-color: $color-neutral-50;
          text-decoration: none;
          transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);

          .card-icon-container {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);

            .card-emoji {
              font-size: 22px;
            }
          }

          .card-text-container {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;

            .card-title-row {
              display: flex;
              align-items: center;
              justify-content: space-between;
              gap: 4px;

              .card-title {
                font-size: 13.5px;
                font-weight: $fw-bold;
                color: $color-neutral-800;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                transition: color $transition-fast;
              }

              .hot-badge {
                font-size: 9px;
                font-weight: 800;
                background: linear-gradient(135deg, #ff4757, #ff6b81);
                color: #ffffff;
                padding: 1px 5px;
                border-radius: 4px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
              }
            }

            .card-subtitle-bn {
              font-size: 11.5px;
              color: $color-neutral-500;
              margin-top: 1px;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            }

            .card-meta {
              display: flex;
              align-items: center;
              justify-content: space-between;
              margin-top: 3px;

              .card-count {
                font-size: 10.5px;
                font-weight: $fw-semibold;
                color: $color-primary-600;
              }

              .card-arrow-link {
                font-size: 11px;
                color: $color-neutral-400;
                font-weight: $fw-bold;
                transition: color $transition-fast, transform $transition-fast;
              }
            }
          }

          &:hover {
            background-color: #ffffff;
            border-color: $color-primary-400;
            box-shadow: 0 8px 20px rgba(235, 40, 108, 0.12);
            transform: translateY(-2px);

            .card-title {
              color: $color-primary-600;
            }

            .card-arrow-link {
              color: $color-primary-600;
              transform: translateX(3px);
            }
          }
        }
      }

      .mega-footer-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 20px;
        background-color: hsl(342, 60%, 98%);
        border-top: 1px solid $color-neutral-200;
        font-size: 12px;
        color: $color-neutral-600;

        .footer-perk {
          display: flex;
          align-items: center;
          gap: 6px;

          .perk-sym {
            font-size: 14px;
          }
        }
      }
    }
  }

  .header-nav-actions {
    display: flex;
    align-items: center;
    gap: $space-3;

    .header-action-btn {
      @include flex-center;
      gap: $space-2;
      font-size: $fs-sm;
      font-weight: $fw-medium;
      color: $color-neutral-700;
      padding: $space-2 $space-3;
      border-radius: $radius-lg;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover {
        background-color: $color-neutral-100;
        color: $color-neutral-900;
      }

      .cart-badge-count {
        background: $color-primary;
        color: #ffffff;
        font-size: $fs-2xs;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: $radius-full;
      }
    }

    .mobile-cat-trigger-btn {
      display: none;
      background: $color-primary-50;
      border: 1px solid $color-primary-200;
      color: $color-primary-700;
      font-weight: $fw-bold;

      @media (max-width: 960px) {
        display: inline-flex;
      }
    }
  }
}

// --- App Category Themes (Icon container gradients) ---
.theme-coral {
  background: linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%);
}
.theme-purple {
  background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
}
.theme-blue {
  background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
}
.theme-amber {
  background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
}
.theme-rose {
  background: linear-gradient(135deg, #e84393 0%, #fd79a8 100%);
}
.theme-emerald {
  background: linear-gradient(135deg, #00b894 0%, #55efc4 100%);
}
.theme-indigo {
  background: linear-gradient(135deg, #4834d4 0%, #686de0 100%);
}

// --- Mobile App Category Sheet / Drawer ---
.mobile-cat-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  z-index: $z-modal;
  display: flex;
  align-items: flex-end;
  justify-content: center;

  .mobile-cat-sheet {
    width: 100%;
    max-width: 540px;
    max-height: 85vh;
    overflow-y: auto;
    background: #ffffff;
    border-radius: 24px 24px 0 0;
    padding: $space-5;
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    animation: sheet-slide-up 0.3s cubic-bezier(0.16, 1, 0.3, 1);

    .sheet-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: $space-4;
      border-bottom: 1px solid $color-neutral-200;

      .sheet-title-group {
        display: flex;
        align-items: center;
        gap: $space-3;

        .sheet-logo-accent {
          font-family: $font-heading;
          font-weight: $fw-extrabold;
          font-size: 20px;
          color: #ffffff;
          background: $color-primary-600;
          width: 36px;
          height: 36px;
          border-radius: $radius-lg;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .sheet-heading {
          font-size: $fs-lg;
          font-weight: $fw-bold;
          color: $color-neutral-900;
          margin: 0;
        }

        .sheet-sub {
          font-size: $fs-xs;
          color: $color-neutral-500;
          margin: 0;
        }
      }

      .sheet-close-btn {
        width: 36px;
        height: 36px;
        border-radius: $radius-full;
        background: $color-neutral-100;
        border: none;
        font-size: 16px;
        color: $color-neutral-600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all $transition-fast;

        &:hover {
          background: $color-neutral-200;
          color: $color-neutral-900;
        }
      }
    }

    .sheet-quick-nav {
      display: flex;
      gap: $space-2;
      padding: $space-3 0;
      overflow-x: auto;

      .quick-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: $fs-xs;
        font-weight: $fw-bold;
        color: $color-neutral-700;
        background: $color-neutral-100;
        padding: 6px 12px;
        border-radius: $radius-full;
        text-decoration: none;
        white-space: nowrap;

        &.admin-chip {
          background: $color-primary-50;
          color: $color-primary-700;
        }
      }
    }

    .sheet-grid-container {
      margin-top: $space-2;

      .sheet-section-title {
        font-size: $fs-xs;
        font-weight: $fw-bold;
        color: $color-neutral-500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: $space-3;
      }

      .sheet-app-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;

        .mobile-app-card {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 10px 12px;
          background-color: $color-neutral-50;
          border: 1px solid $color-neutral-200;
          border-radius: $radius-xl;
          text-decoration: none;
          transition: all $transition-fast;

          .mobile-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;

            .mobile-emoji {
              font-size: 20px;
            }
          }

          .mobile-card-details {
            min-width: 0;
            display: flex;
            flex-direction: column;

            .m-title {
              font-size: 12.5px;
              font-weight: $fw-bold;
              color: $color-neutral-900;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            }

            .m-bn {
              font-size: 10.5px;
              color: $color-neutral-500;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            }

            .m-count {
              font-size: 10px;
              font-weight: $fw-semibold;
              color: $color-primary-600;
              margin-top: 2px;
            }
          }

          &:hover,
          &:active {
            background-color: #ffffff;
            border-color: $color-primary-400;
            box-shadow: 0 4px 12px rgba(235, 40, 108, 0.12);
          }
        }
      }
    }

    .sheet-footer {
      margin-top: $space-5;
      padding-top: $space-3;
      border-top: 1px solid $color-neutral-200;

      .sheet-btn-view-all {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: $space-2;
        padding: 12px;
        background: $color-primary-600;
        color: #ffffff;
        font-weight: $fw-bold;
        font-size: $fs-sm;
        border-radius: $radius-lg;
        text-decoration: none;
        transition: background $transition-fast;

        &:hover {
          background: $color-primary-700;
        }
      }
    }
  }
}

// --- Transitions & Keyframes ---
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.drawer-fade-enter-active,
.drawer-fade-leave-active {
  transition: opacity 0.25s ease;

  .mobile-cat-sheet {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  }
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
  opacity: 0;

  .mobile-cat-sheet {
    transform: translateY(100%);
  }
}

@keyframes sheet-slide-up {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
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
  background: linear-gradient(180deg, hsl(342, 70%, 97.5%) 0%, hsl(342, 55%, 92.5%) 100%);
  color: hsl(342, 35%, 32%);
  border-top: none;
  padding: $space-12 0 $space-6;
  margin-top: $space-16;
  box-shadow: inset 0 2px 6px rgba(235, 40, 108, 0.04);

  .footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: $space-8;

    @include media-down(md) {
      grid-template-columns: 1fr;
    }
  }

  .footer-heading {
    color: hsl(342, 70%, 25%);
    font-family: $font-heading;
    font-size: $fs-base;
    font-weight: $fw-extrabold;
    letter-spacing: -0.01em;
    margin-bottom: $space-4;
  }

  .footer-text {
    font-size: $fs-sm;
    line-height: 1.7;
    max-width: 380px;
    color: hsl(342, 30%, 34%);
  }

  .footer-links {
    @include flex-column;
    gap: $space-2;

    a {
      font-size: $fs-sm;
      font-weight: $fw-semibold;
      color: hsl(342, 55%, 30%);
      text-decoration: none;
      transition: all $transition-fast;
      display: inline-flex;
      align-items: center;

      &:hover {
        color: $color-primary-600;
        transform: translateX(4px);
      }
    }
  }

  .partner-badges {
    display: flex;
    gap: $space-2;
    flex-wrap: wrap;

    .badge {
      background: #ffffff;
      border: 1.5px solid hsl(342, 60%, 82%);
      color: hsl(342, 75%, 30%);
      font-weight: $fw-bold;
      font-size: $fs-xs;
      padding: 6px 14px;
      border-radius: $radius-full;
      box-shadow: 0 2px 6px rgba(235, 40, 108, 0.08);
      transition: all $transition-fast;

      &:hover {
        background-color: $color-primary-50;
        border-color: $color-primary-400;
        color: $color-primary-700;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(235, 40, 108, 0.16);
      }
    }
  }

  .footer-bottom {
    margin-top: $space-10;
    padding-top: $space-6;
    border-top: 1px solid hsl(342, 45%, 85%);
    text-align: center;
    font-size: $fs-xs;
    font-weight: $fw-medium;
    color: hsl(342, 30%, 45%);
  }
}
</style>
