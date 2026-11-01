<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/cartStore';

interface ProductCard {
  id: string;
  name: string;
  slug: string;
  category_name: string;
  category_slug: string;
  brand_name: string;
  origin_country: string;
  warranty_policy: string | null;
  min_price_bdt: number;
  max_price_bdt: number;
  compare_at_price_bdt: number | null;
  image_url: string;
  variants_count: number;
  available_colors: string[];
  available_sizes: string[];
  in_stock: boolean;
  total_stock: number;
  default_variant_id: string | null;
  is_featured: boolean;
}

interface CategoryItem {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  products_count: number;
}

interface BrandItem {
  id: number;
  name: string;
  slug: string;
  origin_country: string;
}

const props = defineProps<{
  bestSellingProducts: ProductCard[];
  newArrivals: ProductCard[];
  allProducts: ProductCard[];
  categories: CategoryItem[];
  brands: BrandItem[];
}>();

const cartStore = useCartStore();
const activeCategoryFilter = ref<string>('all');
const addingVariantId = ref<string | null>(null);

const filteredAllProducts = computed(() => {
  if (activeCategoryFilter.value === 'all') {
    return props.allProducts;
  }
  return props.allProducts.filter(p => p.category_slug === activeCategoryFilter.value);
});

const addToCart = async (product: ProductCard) => {
  if (!product.default_variant_id) return;
  addingVariantId.value = product.default_variant_id;
  try {
    await cartStore.addItem(product.default_variant_id, 1);
  } finally {
    addingVariantId.value = null;
  }
};

const formatPrice = (amount: number): string => {
  return new Intl.NumberFormat('en-BD', {
    style: 'currency',
    currency: 'BDT',
    maximumFractionDigits: 0,
  }).format(amount).replace('BDT', '৳');
};
</script>

<template>
  <StorefrontLayout
    title="Premium Commercial Trading Platform - Direct Imports & Genuine Local Brands"
    meta-description="Shop 100% genuine imported electronics, lifestyle, smartphones and appliances in Bangladesh. Statutory NBR Mushak 6.3 invoices & Nationwide Cash on Delivery."
  >
    <div class="home-page-container">
      <!-- 1. Hero Banner Section -->
      <section class="home-hero">
        <div class="container hero-inner">
          <div class="hero-text-col">
            <div class="hero-pill-badge">
              <span>🌟</span> Direct Global Imports & Genuine Domestic Brands
            </div>
            <h1 class="hero-title">
              Authentic Products at <span class="highlight-pink">Fair Direct Prices</span> in Bangladesh
            </h1>
            <p class="hero-description">
              Finished commercial goods sourced straight from global and domestic manufacturers. Complete with customs Bill of Entry (BOE), statutory NBR Mushak 6.3 VAT tax invoices, and Cash on Delivery nationwide.
            </p>

            <div class="hero-actions-row">
              <Link href="/shop" class="btn btn-hero-primary">
                <span>🛍️</span> Explore All Products
              </Link>
              <a href="#bestsellers" class="btn btn-hero-secondary">
                <span>🔥</span> View Best Sellers
              </a>
            </div>

            <!-- Trust Pills -->
            <div class="hero-trust-bar">
              <div class="trust-pill">
                <span class="trust-icon">🚚</span>
                <span>24-48h Fast Dispatch</span>
              </div>
              <div class="trust-pill">
                <span class="trust-icon">🛡️</span>
                <span>100% Genuine Import</span>
              </div>
              <div class="trust-pill">
                <span class="trust-icon">💵</span>
                <span>Cash on Delivery (COD)</span>
              </div>
              <div class="trust-pill">
                <span class="trust-icon">🧾</span>
                <span>NBR Mushak 6.3 Invoices</span>
              </div>
            </div>
          </div>

          <div class="hero-visual-col">
            <div class="hero-card-featured">
              <div class="featured-badge">Featured Top Seller</div>
              <img
                src="https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&auto=format&fit=crop&q=80"
                alt="Flagship Smartphone"
                class="featured-hero-img"
              >
              <div class="featured-card-overlay">
                <span class="deal-brand">SAMSUNG & APPLE FLAGSHIPS</span>
                <h3 class="deal-name">Latest 5G Smartphones</h3>
                <span class="deal-price">From ৳138,000</span>
                <Link href="/shop?category=smartphones-mobile-accessories" class="deal-link-btn">
                  Shop Smartphones →
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Category Explorer Bar -->
      <section class="section-categories">
        <div class="container">
          <div class="categories-scroller">
            <Link
              v-for="cat in categories"
              :key="cat.id"
              :href="`/shop?category=${cat.slug}`"
              class="cat-tile"
            >
              <span class="cat-icon">
                {{ cat.slug.includes('smartphones') ? '📱' : cat.slug.includes('consumer') ? '🎧' : cat.slug.includes('computer') ? '💻' : cat.slug.includes('home') ? '☕' : '✨' }}
              </span>
              <div class="cat-info">
                <span class="cat-name">{{ cat.name }}</span>
                <span class="cat-count">{{ cat.products_count }} items</span>
              </div>
            </Link>
          </div>
        </div>
      </section>

      <!-- 3. 🔥 Best Selling Products Section -->
      <section id="bestsellers" class="section-bestsellers">
        <div class="container">
          <div class="section-header">
            <div class="header-left">
              <span class="section-subtitle-tag">CUSTOMER FAVORITES</span>
              <h2 class="section-title">🔥 Best Selling Products</h2>
              <p class="section-desc">Top-rated items loved by shoppers across all 64 districts of Bangladesh.</p>
            </div>
            <Link href="/shop?sort=featured" class="section-view-all-link">
              View All Best Sellers →
            </Link>
          </div>

          <div class="products-grid">
            <div
              v-for="product in bestSellingProducts"
              :key="product.id"
              class="product-card"
            >
              <div class="card-badges">
                <span class="badge badge-bestseller">Best Seller</span>
                <span v-if="product.warranty_policy" class="badge badge-warranty">Official Warranty</span>
              </div>

              <Link :href="`/products/${product.slug}`" class="card-image-wrap">
                <img :src="product.image_url" :alt="product.name" class="card-image" loading="lazy">
              </Link>

              <div class="card-body">
                <div class="card-category-brand">
                  <span class="brand-text">{{ product.brand_name }}</span>
                  <span class="cat-sep">•</span>
                  <span>{{ product.category_name }}</span>
                </div>

                <h3 class="product-title">
                  <Link :href="`/products/${product.slug}`">{{ product.name }}</Link>
                </h3>

                <div v-if="product.available_colors.length > 0 || product.available_sizes.length > 0" class="variant-tags-wrap">
                  <span v-for="c in product.available_colors.slice(0, 3)" :key="c" class="color-dot-tag">
                    {{ c }}
                  </span>
                  <span v-for="s in product.available_sizes.slice(0, 2)" :key="s" class="size-pill-tag">
                    {{ s }}
                  </span>
                </div>

                <div class="price-row">
                  <div>
                    <span class="current-price">{{ formatPrice(product.min_price_bdt) }}</span>
                    <span v-if="product.compare_at_price_bdt" class="compare-price">
                      {{ formatPrice(product.compare_at_price_bdt) }}
                    </span>
                  </div>
                  <div class="stock-indicator" :class="product.in_stock ? 'in-stock' : 'out-of-stock'">
                    <span class="stock-dot" />
                    <span>{{ product.in_stock ? 'In Stock' : 'Out of Stock' }}</span>
                  </div>
                </div>

                <div class="card-actions">
                  <Link :href="`/products/${product.slug}`" class="btn details-btn">
                    Details
                  </Link>
                  <button
                    type="button"
                    class="btn add-cart-btn"
                    :disabled="!product.in_stock || addingVariantId === product.default_variant_id"
                    @click="addToCart(product)"
                  >
                    <span v-if="addingVariantId === product.default_variant_id">Adding...</span>
                    <span v-else>🛒 Add to Cart</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 4. Promo Banner Ribbon -->
      <section class="section-promo-ribbon">
        <div class="container">
          <div class="promo-box">
            <div class="promo-content">
              <span class="promo-badge">🎉 FIRST ORDER DISCOUNT</span>
              <h3 class="promo-heading">Get ৳200 OFF on your first purchase!</h3>
              <p class="promo-sub">Use coupon code <strong class="code-pill">WELCOME200</strong> at checkout for orders over ৳3,000.</p>
            </div>
            <Link href="/shop" class="btn btn-promo-cta">
              Claim Discount Now →
            </Link>
          </div>
        </div>
      </section>

      <!-- 5. ✨ New Arrivals Section -->
      <section id="new-arrivals" class="section-new-arrivals">
        <div class="container">
          <div class="section-header">
            <div class="header-left">
              <span class="section-subtitle-tag">JUST LANDED</span>
              <h2 class="section-title">✨ New Arrivals</h2>
              <p class="section-desc">Freshly received shipments cleared through customs and inward to our central distribution hub.</p>
            </div>
            <Link href="/shop?sort=newest" class="section-view-all-link">
              View All New Arrivals →
            </Link>
          </div>

          <div class="products-grid">
            <div
              v-for="product in newArrivals"
              :key="product.id"
              class="product-card"
            >
              <div class="card-badges">
                <span class="badge badge-new">New Arrival</span>
                <span class="badge badge-origin">{{ product.origin_country }}</span>
              </div>

              <Link :href="`/products/${product.slug}`" class="card-image-wrap">
                <img :src="product.image_url" :alt="product.name" class="card-image" loading="lazy">
              </Link>

              <div class="card-body">
                <div class="card-category-brand">
                  <span class="brand-text">{{ product.brand_name }}</span>
                  <span class="cat-sep">•</span>
                  <span>{{ product.category_name }}</span>
                </div>

                <h3 class="product-title">
                  <Link :href="`/products/${product.slug}`">{{ product.name }}</Link>
                </h3>

                <div v-if="product.available_colors.length > 0 || product.available_sizes.length > 0" class="variant-tags-wrap">
                  <span v-for="c in product.available_colors.slice(0, 3)" :key="c" class="color-dot-tag">
                    {{ c }}
                  </span>
                  <span v-for="s in product.available_sizes.slice(0, 2)" :key="s" class="size-pill-tag">
                    {{ s }}
                  </span>
                </div>

                <div class="price-row">
                  <div>
                    <span class="current-price">{{ formatPrice(product.min_price_bdt) }}</span>
                    <span v-if="product.compare_at_price_bdt" class="compare-price">
                      {{ formatPrice(product.compare_at_price_bdt) }}
                    </span>
                  </div>
                  <div class="stock-indicator" :class="product.in_stock ? 'in-stock' : 'out-of-stock'">
                    <span class="stock-dot" />
                    <span>{{ product.in_stock ? 'In Stock' : 'Out of Stock' }}</span>
                  </div>
                </div>

                <div class="card-actions">
                  <Link :href="`/products/${product.slug}`" class="btn details-btn">
                    Details
                  </Link>
                  <button
                    type="button"
                    class="btn add-cart-btn"
                    :disabled="!product.in_stock || addingVariantId === product.default_variant_id"
                    @click="addToCart(product)"
                  >
                    <span v-if="addingVariantId === product.default_variant_id">Adding...</span>
                    <span v-else>🛒 Add to Cart</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 6. 🛍️ All Products / Featured Collection Section -->
      <section id="all-products" class="section-all-products">
        <div class="container">
          <div class="section-header">
            <div class="header-left">
              <span class="section-subtitle-tag">FULL COLLECTION</span>
              <h2 class="section-title">🛍️ Explore All Products</h2>
              <p class="section-desc">Browse our complete warehouse stock catalog of genuine products.</p>
            </div>

            <!-- Category Switcher Tabs -->
            <div class="category-tabs-bar">
              <button
                type="button"
                class="cat-tab-btn"
                :class="{ 'active': activeCategoryFilter === 'all' }"
                @click="activeCategoryFilter = 'all'"
              >
                All ({{ allProducts.length }})
              </button>
              <button
                v-for="cat in categories"
                :key="cat.id"
                type="button"
                class="cat-tab-btn"
                :class="{ 'active': activeCategoryFilter === cat.slug }"
                @click="activeCategoryFilter = cat.slug"
              >
                {{ cat.name }} ({{ cat.products_count }})
              </button>
            </div>
          </div>

          <div class="products-grid">
            <div
              v-for="product in filteredAllProducts"
              :key="product.id"
              class="product-card"
            >
              <div class="card-badges">
                <span class="badge badge-origin">{{ product.origin_country }}</span>
                <span v-if="product.warranty_policy" class="badge badge-warranty">Official Warranty</span>
              </div>

              <Link :href="`/products/${product.slug}`" class="card-image-wrap">
                <img :src="product.image_url" :alt="product.name" class="card-image" loading="lazy">
              </Link>

              <div class="card-body">
                <div class="card-category-brand">
                  <span class="brand-text">{{ product.brand_name }}</span>
                  <span class="cat-sep">•</span>
                  <span>{{ product.category_name }}</span>
                </div>

                <h3 class="product-title">
                  <Link :href="`/products/${product.slug}`">{{ product.name }}</Link>
                </h3>

                <div v-if="product.available_colors.length > 0 || product.available_sizes.length > 0" class="variant-tags-wrap">
                  <span v-for="c in product.available_colors.slice(0, 3)" :key="c" class="color-dot-tag">
                    {{ c }}
                  </span>
                  <span v-for="s in product.available_sizes.slice(0, 2)" :key="s" class="size-pill-tag">
                    {{ s }}
                  </span>
                </div>

                <div class="price-row">
                  <div>
                    <span class="current-price">{{ formatPrice(product.min_price_bdt) }}</span>
                    <span v-if="product.compare_at_price_bdt" class="compare-price">
                      {{ formatPrice(product.compare_at_price_bdt) }}
                    </span>
                  </div>
                  <div class="stock-indicator" :class="product.in_stock ? 'in-stock' : 'out-of-stock'">
                    <span class="stock-dot" />
                    <span>{{ product.in_stock ? 'In Stock' : 'Out of Stock' }}</span>
                  </div>
                </div>

                <div class="card-actions">
                  <Link :href="`/products/${product.slug}`" class="btn details-btn">
                    Details
                  </Link>
                  <button
                    type="button"
                    class="btn add-cart-btn"
                    :disabled="!product.in_stock || addingVariantId === product.default_variant_id"
                    @click="addToCart(product)"
                  >
                    <span v-if="addingVariantId === product.default_variant_id">Adding...</span>
                    <span v-else>🛒 Add to Cart</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="all-products-footer-cta">
            <Link href="/shop" class="btn btn-explore-more">
              <span>🔍</span> Search & Filter Full Catalog with Brands →
            </Link>
          </div>
        </div>
      </section>

      <!-- 7. 💎 Why Choose Us Section ("Why Us") -->
      <section id="why-us" class="section-why-us">
        <div class="container">
          <div class="why-us-header text-center">
            <span class="section-subtitle-tag">PEACE OF MIND</span>
            <h2 class="section-title">💎 Why Choose Us?</h2>
            <p class="section-desc max-w-2xl mx-auto">
              We eliminate fake goods and counterfeit risks through transparent hybrid trading, statutory NBR tax compliance, and trusted courier logistics.
            </p>
          </div>

          <div class="why-us-grid">
            <!-- Pillar 1 -->
            <div class="why-card">
              <div class="why-icon-circle">
                <span>🛡️</span>
              </div>
              <h3 class="why-title">100% Genuine Direct Import</h3>
              <p class="why-desc">
                Finished products procured directly from verified international manufacturers with authentic customs Bill of Entry (বিল অফ এন্ট্রি) clearance. Zero counterfeit goods.
              </p>
            </div>

            <!-- Pillar 2 -->
            <div class="why-card">
              <div class="why-icon-circle">
                <span>💵</span>
              </div>
              <h3 class="why-title">Nationwide Cash on Delivery</h3>
              <p class="why-desc">
                Inspect your package before payment. Seamless home delivery across all 64 districts in Bangladesh with complete convenience and zero upfront risk.
              </p>
            </div>

            <!-- Pillar 3 -->
            <div class="why-card">
              <div class="why-icon-circle">
                <span>🧾</span>
              </div>
              <h3 class="why-title">Statutory NBR Mushak 6.3</h3>
              <p class="why-desc">
                Every sale is backed by an automated legal NBR VAT invoice (মূসক ৬.৩), ensuring transparency and full eligibility for trade input tax credits.
              </p>
            </div>

            <!-- Pillar 4 -->
            <div class="why-card">
              <div class="why-icon-circle">
                <span>⚡</span>
              </div>
              <h3 class="why-title">Rapid 24-48h Dispatch</h3>
              <p class="why-desc">
                Automated 1-click booking with Steadfast, Pathao & RedX from our central Tejgaon warehouse with real-time SMS tracking at every milestone.
              </p>
            </div>

            <!-- Pillar 5 -->
            <div class="why-card">
              <div class="why-icon-circle">
                <span>🔄</span>
              </div>
              <h3 class="why-title">7-Day Easy Return & Warranty</h3>
              <p class="why-desc">
                Official manufacturer warranty support with hassle-free 7-day replacements for any manufacturing defects or transit damages.
              </p>
            </div>

            <!-- Pillar 6 -->
            <div class="why-card">
              <div class="why-icon-circle">
                <span>🔒</span>
              </div>
              <h3 class="why-title">Encrypted & Safe Payments</h3>
              <p class="why-desc">
                Direct merchant gateway integration for bKash, Nagad, and SSLCommerz with cryptographic webhook security and anti-carding defenses.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- 8. Customer Satisfaction Trust Ribbon -->
      <section class="section-satisfaction-bar">
        <div class="container">
          <div class="satisfaction-metrics-row">
            <div class="metric-item">
              <span class="metric-number">50,000+</span>
              <span class="metric-label">Satisfied Shoppers</span>
            </div>
            <div class="metric-divider" />
            <div class="metric-item">
              <span class="metric-number">4.9 / 5.0</span>
              <span class="metric-label">Average Customer Rating ⭐</span>
            </div>
            <div class="metric-divider" />
            <div class="metric-item">
              <span class="metric-number">99.4%</span>
              <span class="metric-label">Courier Delivery Success</span>
            </div>
            <div class="metric-divider" />
            <div class="metric-item">
              <span class="metric-number">100%</span>
              <span class="metric-label">Authentic Product Guarantee</span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.home-page-container {
  display: flex;
  flex-direction: column;
}

// 1. Hero Section
.home-hero {
  background: linear-gradient(135deg, #fff5f8 0%, #fde4ed 50%, #fbd4e2 100%);
  border-bottom: 1px solid $color-primary-200;
  padding: $space-16 0 $space-12;

  @media (max-width: 900px) {
    padding: $space-10 0 $space-8;
  }

  .hero-inner {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: $space-12;
    align-items: center;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      gap: $space-8;
    }
  }

  .hero-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: $space-2;
    padding: $space-1 $space-4;
    border-radius: $radius-full;
    background: $color-neutral-0;
    border: 1px solid $color-primary-300;
    color: $color-primary-700;
    font-size: $fs-xs;
    font-weight: $fw-bold;
    margin-bottom: $space-4;
    box-shadow: $shadow-xs;
  }

  .hero-title {
    font-size: $fs-4xl;
    font-weight: $fw-extrabold;
    line-height: 1.2;
    color: #2b0b16;
    margin-bottom: $space-4;
    letter-spacing: -0.02em;

    @media (max-width: 768px) {
      font-size: $fs-2xl;
    }

    .highlight-pink {
      color: $color-primary-600;
    }
  }

  .hero-description {
    font-size: $fs-base;
    color: #6c2f42;
    line-height: 1.6;
    margin-bottom: $space-6;
    max-width: 580px;
  }

  .hero-actions-row {
    display: flex;
    gap: $space-3;
    flex-wrap: wrap;
    margin-bottom: $space-8;

    .btn {
      padding: $space-3 $space-6;
      border-radius: $radius-xl;
      font-size: $fs-sm;
      font-weight: $fw-bold;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: $space-2;
      transition: all $transition-fast;
    }

    .btn-hero-primary {
      background: $color-primary-600;
      color: #ffffff;
      border: 1px solid $color-primary-600;
      box-shadow: 0 4px 14px rgba(235, 40, 108, 0.3);

      &:hover {
        background: $color-primary-700;
        transform: translateY(-2px);
      }
    }

    .btn-hero-secondary {
      background: $color-neutral-0;
      color: $color-primary-700;
      border: 1px solid $color-primary-300;
      box-shadow: $shadow-xs;

      &:hover {
        background: $color-primary-50;
        border-color: $color-primary-400;
      }
    }
  }

  .hero-trust-bar {
    display: flex;
    gap: $space-4;
    flex-wrap: wrap;

    .trust-pill {
      display: inline-flex;
      align-items: center;
      gap: $space-1;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: #4a1928;
      background: rgba($color-neutral-0, 0.7);
      padding: 4px $space-3;
      border-radius: $radius-full;
      border: 1px solid rgba($color-primary-300, 0.4);
    }
  }

  .hero-visual-col {
    position: relative;

    .hero-card-featured {
      background: $color-neutral-0;
      border-radius: $radius-2xl;
      overflow: hidden;
      box-shadow: 0 20px 40px -10px rgba(235, 40, 108, 0.2);
      border: 1px solid $color-primary-200;
      position: relative;

      .featured-badge {
        position: absolute;
        top: $space-3;
        left: $space-3;
        background: $color-primary-600;
        color: #ffffff;
        font-size: $fs-2xs;
        font-weight: $fw-bold;
        padding: 3px $space-3;
        border-radius: $radius-full;
        z-index: 2;
      }

      .featured-hero-img {
        width: 100%;
        height: 320px;
        object-fit: cover;
        display: block;
      }

      .featured-card-overlay {
        padding: $space-5;
        display: flex;
        flex-direction: column;
        gap: $space-1;

        .deal-brand {
          font-size: $fs-2xs;
          font-weight: $fw-bold;
          color: $color-primary-600;
          letter-spacing: 0.05em;
        }

        .deal-name {
          font-size: $fs-lg;
          font-weight: $fw-bold;
          color: $color-neutral-900;
        }

        .deal-price {
          font-size: $fs-base;
          font-weight: $fw-extrabold;
          color: $color-primary-700;
          margin-bottom: $space-2;
        }

        .deal-link-btn {
          font-size: $fs-xs;
          font-weight: $fw-bold;
          color: $color-primary-600;
          text-decoration: none;

          &:hover {
            text-decoration: underline;
          }
        }
      }
    }
  }
}

// 2. Categories Scroller
.section-categories {
  padding: $space-6 0;
  background: $color-neutral-0;
  border-bottom: 1px solid $color-neutral-200;

  .categories-scroller {
    display: flex;
    gap: $space-3;
    overflow-x: auto;
    padding-bottom: $space-2;
    scrollbar-width: thin;
  }

  .cat-tile {
    flex: 1;
    min-width: 180px;
    display: flex;
    align-items: center;
    gap: $space-3;
    padding: $space-3 $space-4;
    border-radius: $radius-xl;
    background: $color-primary-50;
    border: 1px solid $color-primary-200;
    text-decoration: none;
    transition: all $transition-fast;

    &:hover {
      background: $color-primary-100;
      border-color: $color-primary-300;
      transform: translateY(-2px);
    }

    .cat-icon {
      font-size: $fs-2xl;
    }

    .cat-info {
      display: flex;
      flex-direction: column;

      .cat-name {
        font-size: $fs-xs;
        font-weight: $fw-bold;
        color: $color-neutral-900;
        line-height: 1.25;
      }

      .cat-count {
        font-size: $fs-2xs;
        color: $color-primary-700;
        font-weight: $fw-medium;
      }
    }
  }
}

// Common Section Styles
.section-bestsellers,
.section-new-arrivals,
.section-all-products,
.section-why-us {
  padding: $space-16 0;
}

.section-header {
  @include flex-between;
  margin-bottom: $space-8;
  flex-wrap: wrap;
  gap: $space-4;

  .section-subtitle-tag {
    font-size: $fs-2xs;
    font-weight: $fw-bold;
    letter-spacing: 0.08em;
    color: $color-primary-600;
    display: block;
    margin-bottom: $space-1;
  }

  .section-title {
    font-size: $fs-2xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    margin-bottom: $space-1;
  }

  .section-desc {
    font-size: $fs-sm;
    color: $color-neutral-500;
  }

  .section-view-all-link {
    font-size: $fs-sm;
    font-weight: $fw-bold;
    color: $color-primary-600;
    text-decoration: none;

    &:hover {
      color: $color-primary-700;
      text-decoration: underline;
    }
  }
}

// Category Tabs Bar
.category-tabs-bar {
  display: flex;
  gap: $space-2;
  flex-wrap: wrap;

  .cat-tab-btn {
    padding: $space-2 $space-4;
    border-radius: $radius-full;
    border: 1px solid $color-neutral-300;
    background: $color-neutral-0;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    color: $color-neutral-700;
    cursor: pointer;
    transition: all $transition-fast;

    &:hover {
      background: $color-primary-50;
      border-color: $color-primary-300;
    }

    &.active {
      background: $color-primary-600;
      color: #ffffff;
      border-color: $color-primary-600;
      box-shadow: 0 4px 10px rgba(235, 40, 108, 0.25);
    }
  }
}

// Products Grid
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: $space-6;
}

.product-card {
  background: $color-neutral-0;
  border-radius: $radius-xl;
  border: 1px solid $color-neutral-200;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform $transition-fast, box-shadow $transition-fast, border-color $transition-fast;
  position: relative;

  &:hover {
    transform: translateY(-4px);
    border-color: $color-primary-300;
    box-shadow: 0 12px 28px -6px rgba(235, 40, 108, 0.18);
  }

  .card-badges {
    position: absolute;
    top: $space-3;
    left: $space-3;
    right: $space-3;
    display: flex;
    flex-wrap: wrap;
    gap: $space-1;
    z-index: 2;
    pointer-events: none;

    .badge {
      font-size: $fs-2xs;
      font-weight: $fw-bold;
      padding: 2px $space-2;
      border-radius: $radius-sm;

      &-bestseller {
        background: #ef4444;
        color: #ffffff;
      }

      &-new {
        background: $color-primary-600;
        color: #ffffff;
      }

      &-origin {
        background: rgba($color-neutral-900, 0.8);
        color: #ffffff;
      }

      &-warranty {
        background: rgba($color-primary-600, 0.9);
        color: #ffffff;
      }
    }
  }

  .card-image-wrap {
    display: block;
    aspect-ratio: 1 / 1;
    background: $color-neutral-50;
    overflow: hidden;

    .card-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform $transition-base;

      &:hover {
        transform: scale(1.05);
      }
    }
  }

  .card-body {
    padding: $space-4;
    display: flex;
    flex-direction: column;
    flex: 1;
  }

  .card-category-brand {
    font-size: $fs-xs;
    color: $color-neutral-500;
    margin-bottom: $space-1;
    display: flex;
    align-items: center;
    gap: $space-1;

    .brand-text {
      font-weight: $fw-bold;
      color: $color-primary-600;
    }

    .cat-sep {
      opacity: 0.5;
    }
  }

  .product-title {
    font-size: $fs-base;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    line-height: 1.35;
    margin-bottom: $space-3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.7em;

    a {
      color: inherit;
      text-decoration: none;

      &:hover {
        color: $color-primary-600;
      }
    }
  }

  .variant-tags-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: $space-1;
    margin-bottom: $space-3;

    .color-dot-tag, .size-pill-tag {
      font-size: $fs-2xs;
      padding: 1px $space-2;
      border-radius: $radius-full;
      background: $color-neutral-100;
      color: $color-neutral-700;
      font-weight: $fw-medium;
    }
  }

  .price-row {
    @include flex-between;
    margin-top: auto;
    padding-top: $space-2;
    margin-bottom: $space-4;

    .current-price {
      font-size: $fs-xl;
      font-weight: $fw-extrabold;
      color: $color-primary-700;
    }

    .compare-price {
      font-size: $fs-xs;
      color: $color-neutral-400;
      text-decoration: line-through;
      margin-left: $space-1;
    }

    .stock-indicator {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: $fs-2xs;
      font-weight: $fw-bold;

      .stock-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
      }

      &.in-stock {
        color: $color-success;
        .stock-dot { background-color: $color-success; }
      }

      &.out-of-stock {
        color: $color-danger;
        .stock-dot { background-color: $color-danger; }
      }
    }
  }

  .card-actions {
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: $space-2;

    .btn {
      padding: $space-2 $space-3;
      font-size: $fs-xs;
      font-weight: $fw-bold;
      border-radius: $radius-lg;
      text-align: center;
      text-decoration: none;
      cursor: pointer;
    }

    .details-btn {
      border: 1px solid $color-neutral-300;
      background: $color-neutral-0;
      color: $color-neutral-700;

      &:hover {
        background: $color-neutral-100;
      }
    }

    .add-cart-btn {
      background: $color-primary-600;
      color: #ffffff;
      border: 1px solid $color-primary-600;

      &:hover:not(:disabled) {
        background: $color-primary-700;
      }

      &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
    }
  }
}

// 4. Promo Ribbon
.section-promo-ribbon {
  padding: $space-6 0;

  .promo-box {
    background: linear-gradient(135deg, $color-neutral-900 0%, #2e0d19 100%);
    border-radius: $radius-2xl;
    padding: $space-8 $space-8;
    color: #ffffff;
    @include flex-between;
    flex-wrap: wrap;
    gap: $space-6;
    border: 1px solid $color-primary-700;
    box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.3);

    .promo-badge {
      display: inline-block;
      font-size: $fs-2xs;
      font-weight: $fw-bold;
      color: $color-primary-300;
      letter-spacing: 0.08em;
      margin-bottom: $space-2;
    }

    .promo-heading {
      font-size: $fs-2xl;
      font-weight: $fw-extrabold;
      margin-bottom: $space-1;
    }

    .promo-sub {
      font-size: $fs-sm;
      color: $color-neutral-300;

      .code-pill {
        background: $color-primary-600;
        color: #ffffff;
        padding: 2px $space-2;
        border-radius: $radius-sm;
        font-family: $font-mono;
      }
    }

    .btn-promo-cta {
      padding: $space-3 $space-6;
      background: $color-primary-600;
      color: #ffffff;
      font-size: $fs-sm;
      font-weight: $fw-bold;
      border-radius: $radius-xl;
      text-decoration: none;
      white-space: nowrap;
      transition: all $transition-fast;

      &:hover {
        background: $color-primary-700;
        transform: translateY(-2px);
      }
    }
  }
}

// All Products Footer CTA
.all-products-footer-cta {
  text-align: center;
  margin-top: $space-10;

  .btn-explore-more {
    display: inline-flex;
    align-items: center;
    gap: $space-2;
    padding: $space-3 $space-8;
    border-radius: $radius-full;
    background: $color-primary-50;
    color: $color-primary-700;
    border: 1.5px solid $color-primary-300;
    font-size: $fs-sm;
    font-weight: $fw-bold;
    text-decoration: none;
    transition: all $transition-fast;

    &:hover {
      background: $color-primary-600;
      color: #ffffff;
      border-color: $color-primary-600;
    }
  }
}

// 7. Why Us Section
.section-why-us {
  background: $color-neutral-50;
  border-top: 1px solid $color-neutral-200;
  border-bottom: 1px solid $color-neutral-200;

  .why-us-header {
    margin-bottom: $space-12;

    .max-w-2xl {
      max-width: 650px;
    }

    .mx-auto {
      margin-left: auto;
      margin-right: auto;
    }

    .text-center {
      text-align: center;
    }
  }

  .why-us-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: $space-6;
  }

  .why-card {
    background: $color-neutral-0;
    border-radius: $radius-2xl;
    padding: $space-6;
    border: 1px solid $color-neutral-200;
    box-shadow: $shadow-xs;
    transition: all $transition-fast;

    &:hover {
      transform: translateY(-4px);
      border-color: $color-primary-300;
      box-shadow: 0 10px 25px -5px rgba(235, 40, 108, 0.12);
    }

    .why-icon-circle {
      width: 52px;
      height: 52px;
      border-radius: $radius-xl;
      background: $color-primary-50;
      border: 1px solid $color-primary-200;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      margin-bottom: $space-4;
    }

    .why-title {
      font-size: $fs-base;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin-bottom: $space-2;
    }

    .why-desc {
      font-size: $fs-xs;
      color: $color-neutral-600;
      line-height: 1.6;
    }
  }
}

// 8. Satisfaction Metrics
.section-satisfaction-bar {
  padding: $space-8 0;
  background: $color-neutral-0;

  .satisfaction-metrics-row {
    display: flex;
    align-items: center;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: $space-6;

    .metric-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;

      .metric-number {
        font-family: $font-heading;
        font-size: $fs-3xl;
        font-weight: $fw-extrabold;
        color: $color-primary-600;
        line-height: 1;
        margin-bottom: $space-1;
      }

      .metric-label {
        font-size: $fs-xs;
        color: $color-neutral-600;
        font-weight: $fw-semibold;
      }
    }

    .metric-divider {
      width: 1px;
      height: 40px;
      background: $color-neutral-200;

      @media (max-width: 768px) {
        display: none;
      }
    }
  }
}
</style>

