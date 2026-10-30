<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/cartStore';

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
  products_count: number;
}

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

interface FilterState {
  category: string | null;
  brand: string | null;
  search: string | null;
  sort: string;
}

const props = defineProps<{
  products: ProductCard[];
  categories: CategoryItem[];
  brands: BrandItem[];
  filters: FilterState;
}>();

const cartStore = useCartStore();
const searchQuery = ref(props.filters.search || '');
const selectedBrand = ref(props.filters.brand || '');
const selectedSort = ref(props.filters.sort || 'featured');
const addingVariantId = ref<string | null>(null);

const applyFilters = (newCategorySlug?: string | null) => {
  const cat = newCategorySlug !== undefined ? newCategorySlug : props.filters.category;
  router.get('/shop', {
    category: cat || undefined,
    brand: selectedBrand.value || undefined,
    search: searchQuery.value || undefined,
    sort: selectedSort.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const handleSearch = () => {
  applyFilters();
};

const handleSortChange = () => {
  applyFilters();
};

const handleBrandChange = () => {
  applyFilters();
};

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
  <StorefrontLayout title="Online Storefront — Buy Genuine Imported & Domestic Products">
    <Head title="Shop All Products — Enterprise Storefront" />

    <div class="shop-container">
      <!-- Hero Banner -->
      <section class="shop-hero">
        <div class="container hero-content">
          <div class="hero-badge">
            <span>✨ 100% Genuine Direct Import & Local Sourcing</span>
          </div>
          <h1 class="hero-headline">
            Quality Consumer Electronics, Gadgets & Lifestyle Goods
          </h1>
          <p class="hero-subtext">
            Every product backed by NBR Mushak 6.3 statutory tax invoices, authorized warranties, and fast nationwide Cash on Delivery.
          </p>

          <!-- Search Input Bar in Hero -->
          <div class="hero-search-box">
            <span class="search-icon">🔍</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by product name, brand, model or origin country..."
              class="hero-search-input"
              @keyup.enter="handleSearch"
            />
            <button class="btn btn-primary search-btn" @click="handleSearch">
              Search
            </button>
          </div>
        </div>
      </section>

      <!-- Category Filter Pills Bar -->
      <section class="category-pills-bar">
        <div class="container pills-scroller">
          <button
            class="pill-btn"
            :class="{ active: !filters.category }"
            @click="applyFilters(null)"
          >
            All Categories
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            class="pill-btn"
            :class="{ active: filters.category === cat.slug }"
            @click="applyFilters(cat.slug)"
          >
            {{ cat.name }}
            <span v-if="cat.products_count > 0" class="pill-count">{{ cat.products_count }}</span>
          </button>
        </div>
      </section>

      <!-- Main Shop Catalog Body -->
      <div class="container shop-body">
        <!-- Filter Toolbar -->
        <div class="shop-toolbar">
          <div class="toolbar-stats">
            Showing <span class="font-bold">{{ products.length }}</span> items
            <span v-if="filters.category" class="filter-tag">
              in {{ categories.find(c => c.slug === filters.category)?.name }}
              <button class="remove-tag-btn" @click="applyFilters(null)">×</button>
            </span>
            <span v-if="filters.brand" class="filter-tag">
              Brand: {{ brands.find(b => b.slug === filters.brand)?.name }}
              <button class="remove-tag-btn" @click="selectedBrand = ''; applyFilters()">×</button>
            </span>
          </div>

          <div class="toolbar-controls">
            <!-- Brand Filter -->
            <select
              v-model="selectedBrand"
              class="toolbar-select"
              @change="handleBrandChange"
            >
              <option value="">All Brands</option>
              <option v-for="brand in brands" :key="brand.id" :value="brand.slug">
                {{ brand.name }} ({{ brand.origin_country }})
              </option>
            </select>

            <!-- Sort By -->
            <select
              v-model="selectedSort"
              class="toolbar-select"
              @change="handleSortChange"
            >
              <option value="featured">Featured / Best Match</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
              <option value="newest">Newest Arrivals</option>
            </select>
          </div>
        </div>

        <!-- Products Grid -->
        <div v-if="products.length > 0" class="products-grid">
          <div
            v-for="product in products"
            :key="product.id"
            class="product-card"
          >
            <!-- Badge Overlay -->
            <div class="card-badges">
              <span class="badge badge-origin">🌍 {{ product.origin_country }}</span>
              <span v-if="product.is_featured" class="badge badge-featured">🔥 Featured</span>
              <span v-if="product.warranty_policy" class="badge badge-warranty">🛡️ Official Warranty</span>
            </div>

            <!-- Thumbnail Container -->
            <Link :href="`/products/${product.slug}`" class="card-image-wrap">
              <img
                :src="product.image_url"
                :alt="product.name"
                class="card-image"
                loading="lazy"
              />
            </Link>

            <!-- Card Content -->
            <div class="card-body">
              <div class="card-category-brand">
                <span class="brand-text">{{ product.brand_name }}</span>
                <span class="cat-sep">•</span>
                <span class="cat-text">{{ product.category_name }}</span>
              </div>

              <h2 class="product-title">
                <Link :href="`/products/${product.slug}`">
                  {{ product.name }}
                </Link>
              </h2>

              <!-- Color / Size Attribute Indicators -->
              <div class="variant-tags-wrap" v-if="product.available_colors.length > 0 || product.available_sizes.length > 0">
                <span v-for="color in product.available_colors.slice(0, 3)" :key="color" class="color-dot-tag">
                  {{ color }}
                </span>
                <span v-for="size in product.available_sizes.slice(0, 2)" :key="size" class="size-pill-tag">
                  {{ size }}
                </span>
                <span v-if="product.available_colors.length > 3" class="more-attrs">
                  +{{ product.available_colors.length - 3 }} more
                </span>
              </div>

              <!-- Price Block -->
              <div class="price-row">
                <div class="price-box">
                  <span class="current-price">
                    {{ formatPrice(product.min_price_bdt) }}
                  </span>
                  <span v-if="product.min_price_bdt !== product.max_price_bdt" class="max-price-hint">
                    - {{ formatPrice(product.max_price_bdt) }}
                  </span>
                  <span v-if="product.compare_at_price_bdt && product.compare_at_price_bdt > product.min_price_bdt" class="compare-price">
                    {{ formatPrice(product.compare_at_price_bdt) }}
                  </span>
                </div>

                <div class="stock-indicator" :class="{ 'in-stock': product.in_stock, 'out-of-stock': !product.in_stock }">
                  <span class="stock-dot"></span>
                  <span class="stock-label">{{ product.in_stock ? 'In Stock' : 'Stock Out' }}</span>
                </div>
              </div>

              <!-- Card Actions -->
              <div class="card-actions">
                <Link :href="`/products/${product.slug}`" class="btn btn-outline details-btn">
                  View Options
                </Link>
                <button
                  type="button"
                  class="btn btn-primary add-cart-btn"
                  :disabled="!product.in_stock || addingVariantId === product.default_variant_id"
                  @click="addToCart(product)"
                >
                  <span v-if="addingVariantId === product.default_variant_id">⏳ Adding...</span>
                  <span v-else>🛒 Add to Cart</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-shop-state card">
          <div class="empty-icon">🔍</div>
          <h3 class="empty-title">No products match your criteria</h3>
          <p class="empty-desc">
            Try adjusting your search query, clearing category filters, or selecting a different brand.
          </p>
          <button class="btn btn-primary" @click="searchQuery = ''; selectedBrand = ''; applyFilters(null)">
            Reset All Filters
          </button>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.shop-container {
  display: flex;
  flex-direction: column;
  min-height: 80vh;
}

.shop-hero {
  background: linear-gradient(135deg, $color-neutral-900 0%, $color-neutral-800 100%);
  color: $color-neutral-0;
  padding: $space-12 $space-4 $space-10;
  text-align: center;

  .hero-content {
    max-width: 840px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .hero-badge {
    display: inline-flex;
    padding: $space-1 $space-3;
    border-radius: $radius-full;
    background: rgba($color-primary-500, 0.18);
    border: 1px solid rgba($color-primary-400, 0.35);
    color: $color-primary-300;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    margin-bottom: $space-4;
  }

  .hero-headline {
    font-size: $fs-3xl;
    font-weight: $fw-extrabold;
    line-height: 1.25;
    margin-bottom: $space-3;
    color: $color-neutral-0;

    @media (max-width: 768px) {
      font-size: $fs-2xl;
    }
  }

  .hero-subtext {
    font-size: $fs-base;
    color: $color-neutral-300;
    max-width: 640px;
    margin: 0 auto $space-6;
    line-height: 1.5;
  }

  .hero-search-box {
    width: 100%;
    max-width: 620px;
    display: flex;
    align-items: center;
    background: $color-neutral-0;
    border-radius: $radius-full;
    padding: $space-2 $space-2 $space-2 $space-4;
    box-shadow: $shadow-xl;
    gap: $space-2;

    .search-icon {
      font-size: $fs-lg;
      opacity: 0.6;
    }

    .hero-search-input {
      flex: 1;
      border: none;
      outline: none;
      font-size: $fs-base;
      color: $color-neutral-900;
      background: transparent;

      &::placeholder {
        color: $color-neutral-400;
      }
    }

    .search-btn {
      border-radius: $radius-full;
      padding: $space-2 $space-6;
      font-weight: $fw-bold;
    }
  }
}

.category-pills-bar {
  background: $color-neutral-0;
  border-bottom: 1px solid $color-neutral-200;
  padding: $space-3 0;
  position: sticky;
  top: 0;
  z-index: 10;

  .pills-scroller {
    display: flex;
    gap: $space-2;
    overflow-x: auto;
    padding-bottom: $space-1;
    scrollbar-width: thin;

    &::-webkit-scrollbar {
      height: 4px;
    }
  }

  .pill-btn {
    white-space: nowrap;
    padding: $space-2 $space-4;
    border-radius: $radius-full;
    border: 1px solid $color-neutral-300;
    background: $color-neutral-50;
    color: $color-neutral-700;
    font-size: $fs-sm;
    font-weight: $fw-medium;
    cursor: pointer;
    transition: all $transition-fast;
    display: inline-flex;
    align-items: center;
    gap: $space-2;

    &:hover {
      background: $color-neutral-100;
      border-color: $color-neutral-400;
    }

    &.active {
      background: $color-primary-600;
      color: $color-neutral-0;
      border-color: $color-primary-600;
      font-weight: $fw-semibold;

      .pill-count {
        background: rgba($color-neutral-0, 0.25);
        color: $color-neutral-0;
      }
    }

    .pill-count {
      padding: 1px 6px;
      border-radius: $radius-full;
      background: $color-neutral-200;
      color: $color-neutral-700;
      font-size: $fs-2xs;
    }
  }
}

.shop-body {
  padding: $space-8 0;
}

.shop-toolbar {
  @include flex-between;
  margin-bottom: $space-6;
  flex-wrap: wrap;
  gap: $space-4;

  .toolbar-stats {
    font-size: $fs-sm;
    color: $color-neutral-600;
    display: flex;
    align-items: center;
    gap: $space-2;
    flex-wrap: wrap;

    .filter-tag {
      display: inline-flex;
      align-items: center;
      gap: $space-1;
      padding: 2px $space-2;
      background: $color-primary-50;
      color: $color-primary-700;
      border-radius: $radius-md;
      font-size: $fs-xs;
      font-weight: $fw-medium;

      .remove-tag-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: $fs-sm;
        font-weight: $fw-bold;
        color: $color-primary-700;
      }
    }
  }

  .toolbar-controls {
    display: flex;
    gap: $space-3;

    .toolbar-select {
      padding: $space-2 $space-3;
      border-radius: $radius-lg;
      border: 1px solid $color-neutral-300;
      background: $color-neutral-0;
      font-size: $fs-sm;
      color: $color-neutral-800;
      cursor: pointer;
      outline: none;

      &:focus {
        border-color: $color-primary-500;
      }
    }
  }
}

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
  transition: transform $transition-fast, box-shadow $transition-fast;
  position: relative;

  &:hover {
    transform: translateY(-4px);
    box-shadow: $shadow-lg;
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

      &.badge-origin {
        background: rgba($color-neutral-900, 0.75);
        color: $color-neutral-0;
        backdrop-filter: blur(4px);
      }

      &.badge-featured {
        background: $color-warning;
        color: $color-neutral-900;
      }

      &.badge-warranty {
        background: rgba($color-primary-600, 0.9);
        color: $color-neutral-0;
      }
    }
  }

  .card-image-wrap {
    display: block;
    aspect-ratio: 1 / 1;
    background: $color-neutral-50;
    overflow: hidden;
    position: relative;

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

    .more-attrs {
      font-size: $fs-2xs;
      color: $color-neutral-400;
      align-self: center;
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

    .max-price-hint {
      font-size: $fs-sm;
      font-weight: $fw-semibold;
      color: $color-neutral-600;
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
      color: $color-neutral-0;
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

.empty-shop-state {
  text-align: center;
  padding: $space-12 $space-4;
  margin: $space-8 auto;
  max-width: 520px;

  .empty-icon {
    font-size: 3.5rem;
    margin-bottom: $space-3;
    opacity: 0.7;
  }

  .empty-title {
    font-size: $fs-xl;
    font-weight: $fw-bold;
    margin-bottom: $space-2;
  }

  .empty-desc {
    color: $color-neutral-500;
    font-size: $fs-sm;
    margin-bottom: $space-6;
    line-height: 1.5;
  }
}
</style>
