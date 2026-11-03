<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
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

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  has_more_pages: boolean;
  prev_page_url: string | null;
  next_page_url: string | null;
  links: PaginationLink[];
}

interface PriceBounds {
  min: number;
  max: number;
}

interface FilterState {
  category: string | null;
  brand: string | null;
  search: string | null;
  sort: string;
  min_price?: number | null;
  max_price?: number | null;
  color?: string | null;
  in_stock?: boolean;
  warranty?: boolean;
  page?: number;
}

const props = defineProps<{
  products: ProductCard[];
  pagination?: PaginationMeta;
  categories: CategoryItem[];
  brands: BrandItem[];
  filters: FilterState;
  availableColors?: string[];
  priceBounds?: PriceBounds;
}>();

const cartStore = useCartStore();
const searchQuery = ref(props.filters.search || '');
const selectedBrand = ref(props.filters.brand || '');
const selectedSort = ref(props.filters.sort || 'featured');
const selectedColor = ref(props.filters.color || '');
const minPriceInput = ref(props.filters.min_price ? String(props.filters.min_price) : '');
const maxPriceInput = ref(props.filters.max_price ? String(props.filters.max_price) : '');
const inStockOnly = ref(Boolean(props.filters.in_stock));
const warrantyOnly = ref(Boolean(props.filters.warranty));

const addingVariantId = ref<string | null>(null);
const mobileFilterOpen = ref(false);
const showAllColors = ref(false);

// Scroller ref for top category arrow slider
const pillsContainer = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(true);

const trendingKeywords = [
  'ZARA Bags',
  'iPhone 16 Pro',
  'Samsung S24',
  'Wireless Earbuds',
  'Backpack',
  'Smart Watch',
  'Air Fryer',
];

const priceBrackets = [
  { label: 'All Prices', min: null, max: null },
  { label: 'Under ৳1,000', min: null, max: 1000 },
  { label: '৳1,000 – ৳3,000', min: 1000, max: 3000 },
  { label: '৳3,000 – ৳10,000', min: 3000, max: 10000 },
  { label: '৳10,000 – ৳30,000', min: 10000, max: 30000 },
  { label: '৳30,000+', min: 30000, max: null },
];

const pageNumberLinks = computed(() => {
  if (!props.pagination?.links || props.pagination.links.length <= 2) return [];
  return props.pagination.links.slice(1, -1);
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (props.filters.category) count++;
  if (props.filters.brand) count++;
  if (props.filters.color) count++;
  if (props.filters.min_price || props.filters.max_price) count++;
  if (props.filters.in_stock) count++;
  if (props.filters.warranty) count++;
  if (props.filters.search) count++;
  return count;
});

const displayedColors = computed(() => {
  const all = props.availableColors || [];
  if (showAllColors.value || all.length <= 12) {
    return all;
  }
  return all.slice(0, 12);
});

const getCategoryIcon = (slug?: string | null): string => {
  if (!slug) return '🏷️';
  const s = slug.toLowerCase();
  if (s.includes('bag') || s.includes('fashion') || s.includes('women')) return '👜';
  if (s.includes('smart') || s.includes('phone') || s.includes('mobile')) return '📱';
  if (s.includes('electronic') || s.includes('audio') || s.includes('gadget') || s.includes('headphone')) return '🎧';
  if (s.includes('computer') || s.includes('office') || s.includes('it') || s.includes('laptop')) return '💻';
  if (s.includes('kitchen') || s.includes('home') || s.includes('appliance')) return '🍳';
  return '📦';
};

const getColorHex = (colorName: string): string => {
  const map: Record<string, string> = {
    'baby pink': '#f9a8d4',
    'beige taupe': '#d6c7b2',
    'black': '#171717',
    'classic black': '#18181b',
    'midnight black': '#09090b',
    'obsidian black': '#111827',
    'onyx black': '#1f2937',
    'black titanium': '#27272a',
    'titanium black': '#27272a',
    'blue titanium': '#334155',
    'burgundy red': '#881337',
    'maroon burgundy': '#701a75',
    'caramel tan': '#b45309',
    'tan brown': '#92400e',
    'emerald green': '#047857',
    'ivory white': '#ffffff',
    'pearl white': '#f8fafc',
    'white': '#ffffff',
    'mocha brown': '#582f0e',
    'natural titanium': '#94a3b8',
    'titanium gray': '#64748b',
    'navy blue': '#1e3a8a',
    'royal blue': '#2563eb',
    'nude beige': '#e2d4c0',
    'powder blue': '#93c5fd',
    'rose gold': '#e11d48',
    'silver': '#e2e8f0',
    'soft lilac': '#c084fc',
    'titanium violet': '#7c3aed',
  };
  return map[colorName.toLowerCase().trim()] || '#cbd5e1';
};

const isLightColor = (colorName: string): boolean => {
  const light = ['white', 'ivory white', 'pearl white', 'silver', 'baby pink', 'nude beige', 'powder blue', 'soft lilac', 'beige taupe'];
  return light.includes(colorName.toLowerCase().trim());
};

const updateScrollButtons = () => {
  if (!pillsContainer.value) return;
  const el = pillsContainer.value;
  canScrollLeft.value = el.scrollLeft > 15;
  canScrollRight.value = el.scrollLeft < (el.scrollWidth - el.clientWidth - 15);
};

const scrollPills = (direction: 'left' | 'right') => {
  if (!pillsContainer.value) return;
  const offset = direction === 'left' ? -260 : 260;
  pillsContainer.value.scrollBy({ left: offset, behavior: 'smooth' });
  setTimeout(updateScrollButtons, 350);
};

onMounted(() => {
  if (pillsContainer.value) {
    pillsContainer.value.addEventListener('scroll', updateScrollButtons, { passive: true });
    updateScrollButtons();
  }
  window.addEventListener('resize', updateScrollButtons);
});

onUnmounted(() => {
  if (pillsContainer.value) {
    pillsContainer.value.removeEventListener('scroll', updateScrollButtons);
  }
  window.removeEventListener('resize', updateScrollButtons);
});

const applyFilters = (overrides?: Partial<FilterState>) => {
  const category = overrides && 'category' in overrides ? overrides.category : props.filters.category;
  const brand = overrides && 'brand' in overrides ? overrides.brand : selectedBrand.value;
  const color = overrides && 'color' in overrides ? overrides.color : selectedColor.value;
  const sort = overrides && 'sort' in overrides ? overrides.sort : selectedSort.value;
  const search = overrides && 'search' in overrides ? overrides.search : searchQuery.value;

  const minPrice = overrides && 'min_price' in overrides
    ? overrides.min_price
    : (minPriceInput.value ? Number(minPriceInput.value) : undefined);

  const maxPrice = overrides && 'max_price' in overrides
    ? overrides.max_price
    : (maxPriceInput.value ? Number(maxPriceInput.value) : undefined);

  const inStock = overrides && 'in_stock' in overrides ? overrides.in_stock : inStockOnly.value;
  const warranty = overrides && 'warranty' in overrides ? overrides.warranty : warrantyOnly.value;

  router.get('/shop', {
    category: category || undefined,
    brand: brand || undefined,
    color: color || undefined,
    search: search || undefined,
    sort: sort || undefined,
    min_price: minPrice && minPrice > 0 ? minPrice : undefined,
    max_price: maxPrice && maxPrice > 0 ? maxPrice : undefined,
    in_stock: inStock ? '1' : undefined,
    warranty: warranty ? '1' : undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });

  mobileFilterOpen.value = false;
};

const handleSearch = () => {
  applyFilters();
};

const searchTrending = (tag: string) => {
  searchQuery.value = tag;
  applyFilters();
};

const clearSearch = () => {
  searchQuery.value = '';
  applyFilters({ search: '' });
};

const handleSortChange = () => {
  applyFilters();
};

const handleBrandChange = () => {
  applyFilters();
};

const toggleColor = (colorName: string) => {
  if (selectedColor.value === colorName) {
    selectedColor.value = '';
  } else {
    selectedColor.value = colorName;
  }
  applyFilters({ color: selectedColor.value });
};

const selectPriceBracket = (bracket: { min: number | null; max: number | null }) => {
  minPriceInput.value = bracket.min !== null ? String(bracket.min) : '';
  maxPriceInput.value = bracket.max !== null ? String(bracket.max) : '';
  applyFilters({
    min_price: bracket.min,
    max_price: bracket.max,
  });
};

const isBracketActive = (bracket: { min: number | null; max: number | null }): boolean => {
  const currentMin = props.filters.min_price ?? null;
  const currentMax = props.filters.max_price ?? null;
  return currentMin === bracket.min && currentMax === bracket.max;
};

const applyCustomPrice = () => {
  const min = minPriceInput.value ? Number(minPriceInput.value) : null;
  const max = maxPriceInput.value ? Number(maxPriceInput.value) : null;
  applyFilters({
    min_price: min,
    max_price: max,
  });
};

const clearAllFilters = () => {
  searchQuery.value = '';
  selectedBrand.value = '';
  selectedColor.value = '';
  minPriceInput.value = '';
  maxPriceInput.value = '';
  inStockOnly.value = false;
  warrantyOnly.value = false;

  router.get('/shop', {
    sort: selectedSort.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
  mobileFilterOpen.value = false;
};

const goToPage = (url: string | null) => {
  if (!url) return;
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: false,
  });
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
      <!-- Enhanced Hero Banner with Pink Glow & Trust Perks -->
      <section class="shop-hero">
        <div class="container hero-content">
          <div class="hero-badge">
            <span class="badge-sparkle">✨</span>
            <span>100% Genuine Direct Import & Local Sourcing</span>
          </div>

          <h1 class="hero-headline">
            Quality Consumer Electronics, Gadgets & Lifestyle Goods
          </h1>

          <p class="hero-subtext">
            Every product backed by authorized warranties and fast nationwide Cash on Delivery.
          </p>

          <!-- Hero Search Box -->
          <div class="hero-search-box">
            <span class="search-icon">🔍</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by product name, brand, color, or model..."
              class="hero-search-input"
              @keyup.enter="handleSearch"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="search-clear-btn"
              title="Clear search"
              @click="clearSearch"
            >
              ✕
            </button>
            <button class="btn btn-primary search-btn" @click="handleSearch">
              Search
            </button>
          </div>

          <!-- Clickable Trending Keywords -->
          <div class="trending-tags-wrap">
            <span class="trending-label">🔥 Trending Searches:</span>
            <div class="trending-chips">
              <button
                v-for="tag in trendingKeywords"
                :key="tag"
                type="button"
                class="trending-chip"
                :class="{ active: searchQuery.toLowerCase() === tag.toLowerCase() }"
                @click="searchTrending(tag)"
              >
                {{ tag }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Sticky Top Category Arrow Slider -->
      <section class="category-pills-bar">
        <div class="container pills-wrapper">
          <!-- Left Arrow Navigation -->
          <button
            type="button"
            class="pill-arrow-btn pill-arrow-left"
            :class="{ 'is-disabled': !canScrollLeft }"
            :disabled="!canScrollLeft"
            aria-label="Previous categories"
            @click="scrollPills('left')"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>

          <!-- Horizontal Scroller -->
          <div
            ref="pillsContainer"
            class="pills-scroller"
            @scroll="updateScrollButtons"
          >
            <button
              class="pill-btn"
              :class="{ active: !filters.category }"
              @click="applyFilters({ category: null })"
            >
              <span class="pill-icon">🏷️</span>
              <span class="pill-name">All Categories</span>
            </button>

            <button
              v-for="cat in categories"
              :key="cat.id"
              class="pill-btn"
              :class="{ active: filters.category === cat.slug }"
              @click="applyFilters({ category: cat.slug })"
            >
              <span class="pill-icon">{{ getCategoryIcon(cat.slug) }}</span>
              <span class="pill-name">{{ cat.name }}</span>
              <span v-if="cat.products_count > 0" class="pill-count">{{ cat.products_count }}</span>
            </button>
          </div>

          <!-- Right Arrow Navigation -->
          <button
            type="button"
            class="pill-arrow-btn pill-arrow-right"
            :class="{ 'is-disabled': !canScrollRight }"
            :disabled="!canScrollRight"
            aria-label="Next categories"
            @click="scrollPills('right')"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>
      </section>

      <!-- Main Shop Body with Facet Filter Sidebar & Products -->
      <div class="container shop-body">
        <div class="shop-layout">
          <!-- Desktop Filter Sidebar -->
          <aside class="shop-sidebar">
            <div class="sidebar-card">
              <div class="sidebar-header">
                <div class="header-title-box">
                  <span class="header-icon">⚙️</span>
                  <h3 class="header-title">Filters</h3>
                  <span v-if="activeFilterCount > 0" class="filter-count-badge">{{ activeFilterCount }}</span>
                </div>
                <button
                  v-if="activeFilterCount > 0"
                  type="button"
                  class="reset-filters-link"
                  @click="clearAllFilters"
                >
                  Clear All
                </button>
              </div>

              <!-- Filter Section: Price Range -->
              <div class="filter-group">
                <h4 class="filter-group-title">
                  <span>Price Range (BDT)</span>
                </h4>
                <div class="price-brackets-list">
                  <button
                    v-for="bracket in priceBrackets"
                    :key="bracket.label"
                    type="button"
                    class="price-bracket-btn"
                    :class="{ active: isBracketActive(bracket) }"
                    @click="selectPriceBracket(bracket)"
                  >
                    <span>{{ bracket.label }}</span>
                  </button>
                </div>

                <!-- Custom Min/Max Inputs -->
                <div class="custom-price-inputs">
                  <div class="price-input-wrap">
                    <span class="currency-symbol">৳</span>
                    <input
                      v-model="minPriceInput"
                      type="number"
                      placeholder="Min"
                      class="price-input"
                      @keyup.enter="applyCustomPrice"
                    />
                  </div>
                  <span class="price-range-sep">–</span>
                  <div class="price-input-wrap">
                    <span class="currency-symbol">৳</span>
                    <input
                      v-model="maxPriceInput"
                      type="number"
                      placeholder="Max"
                      class="price-input"
                      @keyup.enter="applyCustomPrice"
                    />
                  </div>
                  <button
                    type="button"
                    class="btn-apply-price"
                    title="Apply Price Filter"
                    @click="applyCustomPrice"
                  >
                    Go
                  </button>
                </div>
              </div>

              <!-- Filter Section: Color Options -->
              <div v-if="availableColors && availableColors.length > 0" class="filter-group">
                <div class="filter-group-header">
                  <h4 class="filter-group-title">Color Options</h4>
                  <span v-if="selectedColor" class="active-color-name">({{ selectedColor }})</span>
                </div>
                <div class="color-swatches-grid">
                  <button
                    v-for="color in displayedColors"
                    :key="color"
                    type="button"
                    class="color-swatch-item"
                    :class="{
                      active: selectedColor === color,
                      'is-light': isLightColor(color)
                    }"
                    :title="color"
                    @click="toggleColor(color)"
                  >
                    <span
                      class="color-dot"
                      :style="{ backgroundColor: getColorHex(color) }"
                    >
                      <svg v-if="selectedColor === color" class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"></polyline>
                      </svg>
                    </span>
                    <span class="color-label">{{ color }}</span>
                  </button>
                </div>
                <button
                  v-if="availableColors.length > 12"
                  type="button"
                  class="show-more-colors-btn"
                  @click="showAllColors = !showAllColors"
                >
                  {{ showAllColors ? 'Show Fewer Colors ▲' : `+ Show All ${availableColors.length} Colors ▼` }}
                </button>
              </div>

              <!-- Filter Section: Stock & Warranty Toggles -->
              <div class="filter-group">
                <h4 class="filter-group-title">Availability & Perks</h4>
                <div class="toggles-list">
                  <label class="custom-checkbox-row">
                    <input
                      v-model="inStockOnly"
                      type="checkbox"
                      class="custom-check-input"
                      @change="applyFilters()"
                    />
                    <span class="check-box-indicator"></span>
                    <span class="check-label">
                      <span class="stock-live-dot"></span>
                      In Stock Only
                    </span>
                  </label>

                  <label class="custom-checkbox-row">
                    <input
                      v-model="warrantyOnly"
                      type="checkbox"
                      class="custom-check-input"
                      @change="applyFilters()"
                    />
                    <span class="check-box-indicator"></span>
                    <span class="check-label">
                      <span class="perk-inline-icon">🛡️</span>
                      Official Warranty
                    </span>
                  </label>
                </div>
              </div>

              <!-- Filter Section: Brands -->
              <div v-if="brands && brands.length > 0" class="filter-group">
                <h4 class="filter-group-title">Brands</h4>
                <div class="brands-list-scroller">
                  <label
                    v-for="brand in brands"
                    :key="brand.id"
                    class="custom-checkbox-row brand-row"
                  >
                    <input
                      type="radio"
                      name="shop_brand"
                      :value="brand.slug"
                      :checked="selectedBrand === brand.slug"
                      class="custom-check-input"
                      @change="selectedBrand = brand.slug; applyFilters()"
                    />
                    <span class="check-box-indicator radio-indicator"></span>
                    <span class="check-label brand-name-label">
                      <span>{{ brand.name }}</span>
                      <span class="brand-origin-badge">{{ brand.origin_country }}</span>
                    </span>
                    <span v-if="brand.products_count > 0" class="brand-count">{{ brand.products_count }}</span>
                  </label>
                </div>
              </div>
            </div>
          </aside>

          <!-- Main Catalog Content -->
          <main class="shop-main">
            <!-- Filter Toolbar -->
            <div class="shop-toolbar">
              <div class="toolbar-left">
                <!-- Mobile Filter Trigger Button -->
                <button
                  type="button"
                  class="mobile-filter-btn"
                  @click="mobileFilterOpen = true"
                >
                  <span class="filter-btn-icon">⚙️</span>
                  <span>Filters</span>
                  <span v-if="activeFilterCount > 0" class="filter-pill-badge">{{ activeFilterCount }}</span>
                </button>

                <div class="toolbar-stats">
                  Showing <span class="font-bold">{{ pagination ? `${pagination.from || 0}–${pagination.to || 0} of ${pagination.total}` : products.length }}</span> items
                </div>
              </div>

              <div class="toolbar-controls">
                <!-- Sort Dropdown -->
                <div class="sort-select-wrap">
                  <label class="sort-label">Sort By:</label>
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
            </div>

            <!-- Active Filters Badges Row -->
            <div v-if="activeFilterCount > 0" class="active-filters-bar">
              <span class="active-filters-title">Active Filters:</span>

              <span v-if="filters.search" class="active-filter-chip">
                <span>Search: "<strong>{{ filters.search }}</strong>"</span>
                <button type="button" class="remove-chip-btn" @click="clearSearch">×</button>
              </span>

              <span v-if="filters.category" class="active-filter-chip">
                <span>Category: <strong>{{ categories.find(c => c.slug === filters.category)?.name }}</strong></span>
                <button type="button" class="remove-chip-btn" @click="applyFilters({ category: null })">×</button>
              </span>

              <span v-if="filters.brand" class="active-filter-chip">
                <span>Brand: <strong>{{ brands.find(b => b.slug === filters.brand)?.name }}</strong></span>
                <button type="button" class="remove-chip-btn" @click="selectedBrand = ''; applyFilters({ brand: '' })">×</button>
              </span>

              <span v-if="filters.color" class="active-filter-chip">
                <span class="chip-color-dot" :style="{ backgroundColor: getColorHex(filters.color) }"></span>
                <span>Color: <strong>{{ filters.color }}</strong></span>
                <button type="button" class="remove-chip-btn" @click="toggleColor(filters.color)">×</button>
              </span>

              <span v-if="filters.min_price || filters.max_price" class="active-filter-chip">
                <span>
                  Price:
                  <strong v-if="filters.min_price && filters.max_price">৳{{ filters.min_price }} - ৳{{ filters.max_price }}</strong>
                  <strong v-else-if="filters.min_price">From ৳{{ filters.min_price }}</strong>
                  <strong v-else-if="filters.max_price">Up to ৳{{ filters.max_price }}</strong>
                </span>
                <button type="button" class="remove-chip-btn" @click="minPriceInput = ''; maxPriceInput = ''; applyFilters({ min_price: null, max_price: null })">×</button>
              </span>

              <span v-if="filters.in_stock" class="active-filter-chip">
                <span>In Stock Only</span>
                <button type="button" class="remove-chip-btn" @click="inStockOnly = false; applyFilters({ in_stock: false })">×</button>
              </span>

              <span v-if="filters.warranty" class="active-filter-chip">
                <span>Official Warranty</span>
                <button type="button" class="remove-chip-btn" @click="warrantyOnly = false; applyFilters({ warranty: false })">×</button>
              </span>

              <button type="button" class="clear-all-chips-btn" @click="clearAllFilters">
                Reset All Filters
              </button>
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
                      <span class="micro-color-dot" :style="{ backgroundColor: getColorHex(color) }"></span>
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
                      <span v-if="addingVariantId === product.default_variant_id" class="btn-cart-content">
                        <svg class="cart-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10"></circle>
                        </svg>
                        <span>Adding...</span>
                      </span>
                      <span v-else class="btn-cart-content">
                        <svg class="cart-btn-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                          <circle cx="9" cy="21" r="1"></circle>
                          <circle cx="20" cy="21" r="1"></circle>
                          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span>Add to Cart</span>
                      </span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pagination Controls -->
            <div v-if="pagination && pagination.last_page > 1" class="pagination-wrapper">
              <div class="pagination-summary">
                Showing <span class="font-bold">{{ pagination.from }}</span> to <span class="font-bold">{{ pagination.to }}</span> of <span class="font-bold">{{ pagination.total }}</span> products
              </div>

              <nav class="pagination-nav" aria-label="Catalog pages">
                <button
                  type="button"
                  class="page-nav-btn prev-btn"
                  :disabled="!pagination.prev_page_url"
                  @click="goToPage(pagination.prev_page_url)"
                >
                  ← Previous
                </button>

                <div class="page-numbers-list">
                  <template v-for="(link, idx) in pageNumberLinks" :key="idx">
                    <span v-if="link.label === '...'" class="pagination-ellipsis">…</span>
                    <button
                      v-else
                      type="button"
                      class="page-number-btn"
                      :class="{ active: link.active }"
                      :disabled="link.active || !link.url"
                      @click="goToPage(link.url)"
                      v-html="link.label"
                    ></button>
                  </template>
                </div>

                <button
                  type="button"
                  class="page-nav-btn next-btn"
                  :disabled="!pagination.next_page_url"
                  @click="goToPage(pagination.next_page_url)"
                >
                  Next →
                </button>
              </nav>
            </div>

            <!-- Empty State -->
            <div v-else-if="products.length === 0" class="empty-shop-state card">
              <div class="empty-icon">🔍</div>
              <h3 class="empty-title">No products match your criteria</h3>
              <p class="empty-desc">
                Try clearing some filters, widening your price range, or searching for other items.
              </p>
              <button class="btn btn-primary" @click="clearAllFilters">
                Reset All Filters
              </button>
            </div>
          </main>
        </div>
      </div>
    </div>

    <!-- Mobile Slide-Out Filter Drawer -->
    <Teleport to="body">
      <div
        v-if="mobileFilterOpen"
        class="mobile-filter-backdrop"
        @click="mobileFilterOpen = false"
      ></div>

      <div
        class="mobile-filter-drawer"
        :class="{ open: mobileFilterOpen }"
      >
        <div class="drawer-header">
          <div class="drawer-title-box">
            <span class="drawer-icon">⚙️</span>
            <h3 class="drawer-title">Filter Products</h3>
            <span v-if="activeFilterCount > 0" class="drawer-badge">{{ activeFilterCount }} active</span>
          </div>
          <button
            type="button"
            class="drawer-close-btn"
            aria-label="Close filters"
            @click="mobileFilterOpen = false"
          >
            ✕
          </button>
        </div>

        <div class="drawer-body">
          <!-- Mobile Price Range -->
          <div class="drawer-section">
            <h4 class="drawer-section-title">Price Range (BDT)</h4>
            <div class="price-brackets-list">
              <button
                v-for="bracket in priceBrackets"
                :key="bracket.label"
                type="button"
                class="price-bracket-btn"
                :class="{ active: isBracketActive(bracket) }"
                @click="selectPriceBracket(bracket)"
              >
                <span>{{ bracket.label }}</span>
              </button>
            </div>

            <div class="custom-price-inputs">
              <div class="price-input-wrap">
                <span class="currency-symbol">৳</span>
                <input
                  v-model="minPriceInput"
                  type="number"
                  placeholder="Min"
                  class="price-input"
                />
              </div>
              <span class="price-range-sep">–</span>
              <div class="price-input-wrap">
                <span class="currency-symbol">৳</span>
                <input
                  v-model="maxPriceInput"
                  type="number"
                  placeholder="Max"
                  class="price-input"
                />
              </div>
              <button
                type="button"
                class="btn-apply-price"
                @click="applyCustomPrice"
              >
                Go
              </button>
            </div>
          </div>

          <!-- Mobile Color Options -->
          <div v-if="availableColors && availableColors.length > 0" class="drawer-section">
            <h4 class="drawer-section-title">Color Options</h4>
            <div class="color-swatches-grid">
              <button
                v-for="color in (availableColors || [])"
                :key="color"
                type="button"
                class="color-swatch-item"
                :class="{
                  active: selectedColor === color,
                  'is-light': isLightColor(color)
                }"
                @click="toggleColor(color)"
              >
                <span
                  class="color-dot"
                  :style="{ backgroundColor: getColorHex(color) }"
                >
                  <svg v-if="selectedColor === color" class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </span>
                <span class="color-label">{{ color }}</span>
              </button>
            </div>
          </div>

          <!-- Mobile Availability & Perks -->
          <div class="drawer-section">
            <h4 class="drawer-section-title">Availability & Perks</h4>
            <div class="toggles-list">
              <label class="custom-checkbox-row">
                <input
                  v-model="inStockOnly"
                  type="checkbox"
                  class="custom-check-input"
                  @change="applyFilters()"
                />
                <span class="check-box-indicator"></span>
                <span class="check-label">
                  <span class="stock-live-dot"></span>
                  In Stock Only
                </span>
              </label>

              <label class="custom-checkbox-row">
                <input
                  v-model="warrantyOnly"
                  type="checkbox"
                  class="custom-check-input"
                  @change="applyFilters()"
                />
                <span class="check-box-indicator"></span>
                <span class="check-label">
                  <span class="perk-inline-icon">🛡️</span>
                  Official Warranty
                </span>
              </label>
            </div>
          </div>

          <!-- Mobile Brands -->
          <div v-if="brands && brands.length > 0" class="drawer-section">
            <h4 class="drawer-section-title">Brands</h4>
            <div class="brands-list-scroller">
              <label
                v-for="brand in brands"
                :key="brand.id"
                class="custom-checkbox-row brand-row"
              >
                <input
                  type="radio"
                  name="mobile_shop_brand"
                  :value="brand.slug"
                  :checked="selectedBrand === brand.slug"
                  class="custom-check-input"
                  @change="selectedBrand = brand.slug; applyFilters()"
                />
                <span class="check-box-indicator radio-indicator"></span>
                <span class="check-label brand-name-label">
                  <span>{{ brand.name }}</span>
                  <span class="brand-origin-badge">{{ brand.origin_country }}</span>
                </span>
                <span v-if="brand.products_count > 0" class="brand-count">{{ brand.products_count }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="drawer-footer">
          <button
            type="button"
            class="btn btn-outline"
            @click="clearAllFilters"
          >
            Clear All
          </button>
          <button
            type="button"
            class="btn btn-primary apply-drawer-btn"
            @click="mobileFilterOpen = false"
          >
            Show {{ pagination?.total ?? products.length }} Results
          </button>
        </div>
      </div>
    </Teleport>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.shop-container {
  display: flex;
  flex-direction: column;
  min-height: 80vh;
}

// ==============================================================================
// 1. ENHANCED HERO BANNER
// ==============================================================================
.shop-hero {
  background: linear-gradient(135deg, #fff2f6 0%, #fde4ed 50%, #fbd5e3 100%);
  color: #3b0d1e;
  padding: $space-10 $space-4 $space-10;
  text-align: center;
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: -50px;
    left: 50%;
    transform: translateX(-50%);
    width: 650px;
    height: 300px;
    background: radial-gradient(circle, rgba(235, 40, 108, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 1;
  }

  .hero-content {
    max-width: 860px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
  }

  .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px $space-4;
    border-radius: $radius-full;
    background: #ffffff;
    border: 1.5px solid $color-primary-200;
    color: $color-primary-700;
    font-size: $fs-xs;
    font-weight: $fw-bold;
    margin-bottom: $space-4;
    box-shadow: 0 4px 14px rgba(235, 40, 108, 0.08);

    .badge-sparkle {
      font-size: 13px;
    }
  }

  .hero-headline {
    font-size: 2.25rem;
    font-weight: $fw-extrabold;
    line-height: 1.25;
    margin-bottom: $space-3;
    color: #3b0d1e;
    letter-spacing: -0.02em;

    @media (max-width: 768px) {
      font-size: 1.75rem;
    }
  }

  .hero-subtext {
    font-size: $fs-base;
    color: #723247;
    max-width: 660px;
    margin: 0 auto $space-5;
    line-height: 1.55;

    @media (max-width: 768px) {
      font-size: $fs-sm;
      margin-bottom: $space-4;
    }
  }

  // Search box
  .hero-search-box {
    width: 100%;
    max-width: 640px;
    display: flex;
    align-items: center;
    background: #ffffff;
    border-radius: $radius-full;
    padding: 6px 6px 6px $space-4;
    box-shadow: 0 12px 30px -6px rgba(235, 40, 108, 0.18);
    border: 1.5px solid $color-primary-300;
    gap: $space-2;
    transition: all $transition-fast;

    &:focus-within {
      border-color: $color-primary-500;
      box-shadow: 0 14px 34px -4px rgba(235, 40, 108, 0.28);
    }

    .search-icon {
      font-size: $fs-lg;
      opacity: 0.65;
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

    .search-clear-btn {
      background: none;
      border: none;
      color: $color-neutral-400;
      font-size: 14px;
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 50%;
      line-height: 1;

      &:hover {
        color: $color-neutral-700;
        background: $color-neutral-100;
      }
    }

    .search-btn {
      border-radius: $radius-full;
      padding: 10px 24px;
      font-weight: $fw-bold;
      background: linear-gradient(135deg, $color-primary-500 0%, $color-primary-600 100%);
      color: #ffffff;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(235, 40, 108, 0.3);
      transition: all $transition-fast;

      &:hover {
        background: linear-gradient(135deg, $color-primary-600 0%, $color-primary-700 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(235, 40, 108, 0.4);
      }
    }
  }

  // Trending tags
  .trending-tags-wrap {
    margin-top: $space-4;
    display: flex;
    align-items: center;
    gap: $space-2;
    flex-wrap: wrap;
    justify-content: center;

    .trending-label {
      font-size: 12px;
      font-weight: $fw-bold;
      color: #723247;
    }

    .trending-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .trending-chip {
      padding: 3px 10px;
      border-radius: $radius-full;
      background: #ffffff;
      border: 1px solid rgba(235, 40, 108, 0.2);
      color: #611830;
      font-size: 11px;
      font-weight: $fw-medium;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover {
        background: $color-primary-50;
        border-color: $color-primary-400;
        color: $color-primary-700;
        transform: translateY(-1px);
      }

      &.active {
        background: $color-primary-600;
        color: #ffffff;
        border-color: $color-primary-600;
        font-weight: $fw-bold;
      }
    }
  }
}

// ==============================================================================
// 2. STICKY CATEGORY PILLS BAR WITH ARROW SLIDER
// ==============================================================================
.category-pills-bar {
  background: #ffffff;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  padding: 10px 0;
  position: sticky;
  top: 0;
  z-index: 20;

  .pills-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .pill-arrow-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #ffffff;
    border: 1.5px solid $color-neutral-200;
    color: $color-neutral-700;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: all $transition-fast;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    z-index: 2;

    svg {
      width: 16px;
      height: 16px;
    }

    &:hover:not(:disabled) {
      background: $color-primary-50;
      border-color: $color-primary-400;
      color: $color-primary-600;
      transform: scale(1.08);
    }

    &.is-disabled, &:disabled {
      opacity: 0.35;
      cursor: not-allowed;
      border-color: $color-neutral-200;
      box-shadow: none;
    }
  }

  .pills-scroller {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 2px 4px;
    scrollbar-width: none;
    scroll-behavior: smooth;
    flex: 1;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .pill-btn {
    white-space: nowrap;
    padding: 7px 14px;
    border-radius: $radius-full;
    border: 1.5px solid $color-neutral-200;
    background: $color-neutral-50;
    color: $color-neutral-700;
    font-size: 13px;
    font-weight: $fw-medium;
    cursor: pointer;
    transition: all $transition-fast;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;

    .pill-icon {
      font-size: 14px;
    }

    .pill-name {
      line-height: 1;
    }

    &:hover {
      background: $color-neutral-100;
      border-color: $color-neutral-300;
      color: $color-neutral-900;
    }

    &.active {
      background: linear-gradient(135deg, $color-primary-500 0%, $color-primary-600 100%);
      color: #ffffff;
      border-color: $color-primary-600;
      font-weight: $fw-bold;
      box-shadow: 0 4px 12px rgba(235, 40, 108, 0.28);

      .pill-count {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
      }
    }

    .pill-count {
      padding: 1px 7px;
      border-radius: $radius-full;
      background: $color-neutral-200;
      color: $color-neutral-700;
      font-size: 10px;
      font-weight: $fw-bold;
    }
  }
}

// ==============================================================================
// 3. MAIN SHOP LAYOUT & FILTER SIDEBAR
// ==============================================================================
.shop-body {
  padding: $space-8 0 $space-16;
}

.shop-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: $space-8;
  align-items: start;

  @media (max-width: 991px) {
    grid-template-columns: 1fr;
    gap: 0;
  }
}

// Sidebar Styles
.shop-sidebar {
  position: sticky;
  top: 75px;
  max-height: calc(100vh - 90px);
  overflow-y: auto;
  scrollbar-width: thin;
  padding-right: 4px;

  @media (max-width: 991px) {
    display: none;
  }

  &::-webkit-scrollbar {
    width: 4px;
  }
  &::-webkit-scrollbar-thumb {
    background: $color-neutral-300;
    border-radius: 4px;
  }

  .sidebar-card {
    background: #ffffff;
    border-radius: $radius-2xl;
    border: 1px solid hsl(342, 30%, 90%);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
    padding: $space-5;
  }

  .sidebar-header {
    @include flex-between;
    padding-bottom: $space-4;
    border-bottom: 1px solid $color-neutral-100;
    margin-bottom: $space-5;

    .header-title-box {
      display: flex;
      align-items: center;
      gap: 6px;

      .header-icon {
        font-size: 16px;
      }

      .header-title {
        font-size: $fs-base;
        font-weight: $fw-bold;
        color: $color-neutral-900;
        margin: 0;
      }

      .filter-count-badge {
        padding: 1px 7px;
        background: $color-primary-50;
        color: $color-primary-700;
        border: 1px solid $color-primary-200;
        border-radius: $radius-full;
        font-size: 11px;
        font-weight: $fw-bold;
      }
    }

    .reset-filters-link {
      background: none;
      border: none;
      color: $color-primary-600;
      font-size: 12px;
      font-weight: $fw-semibold;
      cursor: pointer;
      padding: 0;

      &:hover {
        text-decoration: underline;
        color: $color-primary-700;
      }
    }
  }

  .filter-group {
    margin-bottom: $space-6;
    padding-bottom: $space-6;
    border-bottom: 1px solid $color-neutral-100;

    &:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }

    .filter-group-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: $space-3;
    }

    .filter-group-title {
      font-size: 13px;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin: 0 0 $space-3 0;
    }

    .active-color-name {
      font-size: 11px;
      color: $color-primary-600;
      font-weight: $fw-bold;
    }
  }

  // Price brackets
  .price-brackets-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: $space-3;

    .price-bracket-btn {
      width: 100%;
      text-align: left;
      padding: 6px 10px;
      border-radius: $radius-md;
      background: transparent;
      border: 1px solid transparent;
      color: $color-neutral-700;
      font-size: 12px;
      font-weight: $fw-medium;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover {
        background: $color-neutral-50;
        color: $color-neutral-900;
      }

      &.active {
        background: $color-primary-50;
        border-color: $color-primary-200;
        color: $color-primary-700;
        font-weight: $fw-bold;
      }
    }
  }

  // Custom price input
  .custom-price-inputs {
    display: flex;
    align-items: center;
    gap: 6px;

    .price-input-wrap {
      flex: 1;
      position: relative;
      display: flex;
      align-items: center;

      .currency-symbol {
        position: absolute;
        left: 8px;
        font-size: 12px;
        color: $color-neutral-400;
        font-weight: $fw-bold;
      }

      .price-input {
        width: 100%;
        padding: 6px 6px 6px 20px;
        border: 1px solid $color-neutral-300;
        border-radius: $radius-md;
        font-size: 12px;
        color: $color-neutral-900;
        outline: none;

        &:focus {
          border-color: $color-primary-500;
        }
      }
    }

    .price-range-sep {
      color: $color-neutral-400;
      font-size: 12px;
    }

    .btn-apply-price {
      padding: 6px 12px;
      border-radius: $radius-md;
      background: $color-neutral-800;
      color: #ffffff;
      border: none;
      font-size: 12px;
      font-weight: $fw-bold;
      cursor: pointer;
      transition: background $transition-fast;

      &:hover {
        background: $color-primary-600;
      }
    }
  }

  // Color Swatches
  .color-swatches-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;

    .color-swatch-item {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 5px 8px;
      border-radius: $radius-md;
      border: 1px solid $color-neutral-200;
      background: $color-neutral-50;
      cursor: pointer;
      transition: all $transition-fast;
      text-align: left;

      &:hover {
        border-color: $color-neutral-400;
        background: #ffffff;
      }

      &.active {
        border-color: $color-primary-500;
        background: $color-primary-50;
        box-shadow: 0 1px 6px rgba(235, 40, 108, 0.15);

        .color-label {
          color: $color-primary-700;
          font-weight: $fw-bold;
        }
      }

      .color-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);

        .check-icon {
          width: 9px;
          height: 9px;
          stroke: #ffffff;
        }
      }

      &.is-light .color-dot .check-icon {
        stroke: #18181b;
      }

      .color-label {
        font-size: 11px;
        color: $color-neutral-700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }
  }

  .show-more-colors-btn {
    width: 100%;
    margin-top: $space-2;
    padding: 5px;
    background: none;
    border: none;
    color: $color-primary-600;
    font-size: 11px;
    font-weight: $fw-bold;
    cursor: pointer;
    text-align: center;

    &:hover {
      text-decoration: underline;
    }
  }

  // Checkboxes & Radios
  .toggles-list {
    display: flex;
    flex-direction: column;
    gap: $space-2;
  }

  .custom-checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
    font-size: 13px;
    color: $color-neutral-800;

    .custom-check-input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;

      &:checked + .check-box-indicator {
        background: $color-primary-600;
        border-color: $color-primary-600;

        &::after {
          display: block;
        }
      }

      &:checked + .radio-indicator {
        background: $color-primary-600;
        border-color: $color-primary-600;
      }
    }

    .check-box-indicator {
      width: 16px;
      height: 16px;
      border-radius: 4px;
      border: 1.5px solid $color-neutral-300;
      background: #ffffff;
      position: relative;
      flex-shrink: 0;
      transition: all $transition-fast;

      &::after {
        content: '';
        position: absolute;
        display: none;
        left: 5px;
        top: 2px;
        width: 4px;
        height: 8px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
      }

      &.radio-indicator {
        border-radius: 50%;

        &::after {
          left: 4px;
          top: 4px;
          width: 6px;
          height: 6px;
          border-radius: 50%;
          background: #ffffff;
          border: none;
          transform: none;
        }
      }
    }

    .check-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: $fw-medium;

      .stock-live-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: $color-success;
      }

      .perk-inline-icon {
        font-size: 13px;
      }
    }

    &.brand-row {
      justify-content: space-between;
      padding: 5px 6px;
      border-radius: $radius-md;
      transition: background $transition-fast;

      &:hover {
        background: $color-neutral-50;
      }

      .brand-name-label {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 6px;
      }

      .brand-origin-badge {
        font-size: 10px;
        padding: 1px 5px;
        background: $color-neutral-100;
        border-radius: $radius-sm;
        color: $color-neutral-500;
      }

      .brand-count {
        font-size: 10px;
        color: $color-neutral-400;
        font-weight: $fw-bold;
      }
    }
  }

  .brands-list-scroller {
    max-height: 220px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    scrollbar-width: thin;

    &::-webkit-scrollbar {
      width: 4px;
    }
  }
}

// ==============================================================================
// 4. MAIN CONTENT & PRODUCTS
// ==============================================================================
.shop-main {
  min-width: 0;
}

// Toolbar
.shop-toolbar {
  @include flex-between;
  margin-bottom: $space-4;
  flex-wrap: wrap;
  gap: $space-3;
  padding: $space-3 $space-4;
  background: #ffffff;
  border-radius: $radius-xl;
  border: 1px solid hsl(342, 30%, 92%);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);

  .toolbar-left {
    display: flex;
    align-items: center;
    gap: $space-3;
  }

  .mobile-filter-btn {
    display: none;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: $radius-lg;
    background: $color-primary-50;
    border: 1.5px solid $color-primary-200;
    color: $color-primary-700;
    font-size: 13px;
    font-weight: $fw-bold;
    cursor: pointer;

    @media (max-width: 991px) {
      display: inline-flex;
    }

    .filter-btn-icon {
      font-size: 14px;
    }

    .filter-pill-badge {
      background: $color-primary-600;
      color: #ffffff;
      border-radius: $radius-full;
      padding: 1px 6px;
      font-size: 10px;
    }
  }

  .toolbar-stats {
    font-size: $fs-sm;
    color: $color-neutral-600;

    .font-bold {
      color: $color-neutral-900;
      font-weight: $fw-bold;
    }
  }

  .toolbar-controls {
    display: flex;
    align-items: center;
    gap: $space-3;

    .sort-select-wrap {
      display: flex;
      align-items: center;
      gap: 6px;

      .sort-label {
        font-size: 12px;
        color: $color-neutral-500;
        font-weight: $fw-semibold;
      }
    }

    .toolbar-select {
      padding: 6px 10px;
      border-radius: $radius-md;
      border: 1.5px solid $color-neutral-300;
      background: #ffffff;
      font-size: 13px;
      font-weight: $fw-medium;
      color: $color-neutral-800;
      cursor: pointer;
      outline: none;

      &:focus {
        border-color: $color-primary-500;
      }
    }
  }
}

// Active Filter Chips
.active-filters-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: $space-6;
  padding: $space-2 0;

  .active-filters-title {
    font-size: 12px;
    font-weight: $fw-bold;
    color: $color-neutral-500;
  }

  .active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    background: $color-primary-50;
    color: $color-primary-800;
    border: 1px solid $color-primary-200;
    border-radius: $radius-full;
    font-size: 12px;
    font-weight: $fw-medium;

    .chip-color-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      border: 1px solid rgba(0, 0, 0, 0.15);
    }

    .remove-chip-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: $fw-bold;
      color: $color-primary-700;
      padding: 0 2px;
      line-height: 1;

      &:hover {
        color: $color-danger;
      }
    }
  }

  .clear-all-chips-btn {
    background: none;
    border: none;
    color: $color-neutral-500;
    font-size: 12px;
    font-weight: $fw-semibold;
    cursor: pointer;
    text-decoration: underline;

    &:hover {
      color: $color-primary-600;
    }
  }
}

// Products Grid
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: $space-6;

  @media (max-width: 640px) {
    grid-template-columns: 1fr;
    gap: $space-4;
  }
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

    .color-dot-tag {
      font-size: $fs-2xs;
      padding: 1px $space-2;
      border-radius: $radius-full;
      background: $color-neutral-100;
      color: $color-neutral-700;
      font-weight: $fw-medium;
      display: inline-flex;
      align-items: center;
      gap: 4px;

      .micro-color-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        border: 1px solid rgba(0, 0, 0, 0.15);
      }
    }

    .size-pill-tag {
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
      display: inline-flex;
      align-items: center;
      justify-content: center;

      .btn-cart-content {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;

        .cart-btn-svg {
          width: 16px;
          height: 16px;
          flex-shrink: 0;
          transition: transform 0.2s ease;
        }

        .cart-spinner {
          width: 15px;
          height: 15px;
          animation: spin-loader 0.8s linear infinite;
        }
      }

      &:hover:not(:disabled) {
        background: $color-primary-700;

        .cart-btn-svg {
          transform: scale(1.15) rotate(-6deg);
        }
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

// Pagination Controls
.pagination-wrapper {
  margin-top: $space-8;
  padding: $space-5 $space-6;
  background: #ffffff;
  border-radius: $radius-2xl;
  border: 1px solid hsl(342, 35%, 90%);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: $space-4;
  box-shadow: 0 4px 18px rgba(235, 40, 108, 0.05);

  @media (max-width: 640px) {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .pagination-summary {
    font-size: $fs-sm;
    color: $color-neutral-600;

    .font-bold {
      font-weight: $fw-bold;
      color: hsl(342, 45%, 18%);
    }
  }

  .pagination-nav {
    display: flex;
    align-items: center;
    gap: $space-2;
    flex-wrap: wrap;
    justify-content: center;

    .page-nav-btn {
      padding: 8px 16px;
      border: 1.5px solid hsl(342, 30%, 86%);
      background: #ffffff;
      color: hsl(342, 40%, 25%);
      font-size: 13px;
      font-weight: $fw-semibold;
      border-radius: $radius-lg;
      cursor: pointer;
      transition: all $transition-fast;
      user-select: none;
      display: inline-flex;
      align-items: center;

      &:hover:not(:disabled) {
        border-color: $color-primary-400;
        color: $color-primary-600;
        background: $color-primary-50;
        transform: translateY(-1px);
      }

      &:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: $color-neutral-100;
        border-color: $color-neutral-200;
        color: $color-neutral-400;
      }
    }

    .page-numbers-list {
      display: flex;
      align-items: center;
      gap: 6px;

      .pagination-ellipsis {
        padding: 0 4px;
        color: $color-neutral-400;
        font-weight: $fw-bold;
        user-select: none;
      }

      .page-number-btn {
        min-width: 38px;
        height: 38px;
        padding: 0 8px;
        border: 1.5px solid hsl(342, 30%, 86%);
        background: #ffffff;
        color: hsl(342, 40%, 25%);
        font-size: 13px;
        font-weight: $fw-semibold;
        border-radius: $radius-lg;
        cursor: pointer;
        transition: all $transition-fast;
        display: inline-flex;
        align-items: center;
        justify-content: center;

        &:hover:not(:disabled):not(.active) {
          border-color: $color-primary-400;
          color: $color-primary-600;
          background: $color-primary-50;
        }

        &.active {
          background: linear-gradient(135deg, $color-primary-500 0%, $color-primary-600 100%);
          color: #ffffff;
          border-color: $color-primary-600;
          font-weight: $fw-bold;
          box-shadow: 0 3px 10px rgba(235, 40, 108, 0.35);
          cursor: default;
        }

        &:disabled:not(.active) {
          cursor: not-allowed;
          opacity: 0.5;
        }
      }
    }
  }
}

// ==============================================================================
// 5. MOBILE SLIDE-OUT FILTER DRAWER
// ==============================================================================
.mobile-filter-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 999;
}

.mobile-filter-drawer {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  width: 85%;
  max-width: 360px;
  background: #ffffff;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
  transform: translateX(-100%);
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);

  &.open {
    transform: translateX(0);
  }

  .drawer-header {
    @include flex-between;
    padding: $space-4 $space-5;
    border-bottom: 1px solid $color-neutral-200;

    .drawer-title-box {
      display: flex;
      align-items: center;
      gap: 8px;

      .drawer-icon {
        font-size: 16px;
      }

      .drawer-title {
        font-size: $fs-base;
        font-weight: $fw-bold;
        margin: 0;
      }

      .drawer-badge {
        padding: 2px 8px;
        background: $color-primary-50;
        color: $color-primary-700;
        border-radius: $radius-full;
        font-size: 11px;
        font-weight: $fw-bold;
      }
    }

    .drawer-close-btn {
      background: none;
      border: none;
      font-size: 18px;
      color: $color-neutral-400;
      cursor: pointer;
      padding: 4px;

      &:hover {
        color: $color-neutral-800;
      }
    }
  }

  .drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: $space-5;

    .drawer-section {
      margin-bottom: $space-6;
      padding-bottom: $space-6;
      border-bottom: 1px solid $color-neutral-100;

      &:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
      }

      .drawer-section-title {
        font-size: 13px;
        font-weight: $fw-bold;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: $color-neutral-900;
        margin: 0 0 $space-3 0;
      }
    }

    .price-brackets-list {
      display: flex;
      flex-direction: column;
      gap: 5px;
      margin-bottom: $space-3;

      .price-bracket-btn {
        width: 100%;
        text-align: left;
        padding: 7px 10px;
        border-radius: $radius-md;
        background: transparent;
        border: 1px solid transparent;
        color: $color-neutral-700;
        font-size: 13px;
        font-weight: $fw-medium;
        cursor: pointer;

        &.active {
          background: $color-primary-50;
          border-color: $color-primary-200;
          color: $color-primary-700;
          font-weight: $fw-bold;
        }
      }
    }

    .custom-price-inputs {
      display: flex;
      align-items: center;
      gap: 6px;

      .price-input-wrap {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;

        .currency-symbol {
          position: absolute;
          left: 8px;
          font-size: 12px;
          color: $color-neutral-400;
          font-weight: $fw-bold;
        }

        .price-input {
          width: 100%;
          padding: 6px 6px 6px 20px;
          border: 1px solid $color-neutral-300;
          border-radius: $radius-md;
          font-size: 12px;
          outline: none;
        }
      }

      .btn-apply-price {
        padding: 6px 12px;
        border-radius: $radius-md;
        background: $color-neutral-800;
        color: #ffffff;
        border: none;
        font-size: 12px;
        font-weight: $fw-bold;
        cursor: pointer;
      }
    }

    .color-swatches-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 6px;

      .color-swatch-item {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 6px 8px;
        border-radius: $radius-md;
        border: 1px solid $color-neutral-200;
        background: $color-neutral-50;
        cursor: pointer;

        &.active {
          border-color: $color-primary-500;
          background: $color-primary-50;

          .color-label {
            color: $color-primary-700;
            font-weight: $fw-bold;
          }
        }

        .color-dot {
          width: 14px;
          height: 14px;
          border-radius: 50%;
          flex-shrink: 0;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);

          .check-icon {
            width: 9px;
            height: 9px;
            stroke: #ffffff;
          }
        }

        &.is-light .color-dot .check-icon {
          stroke: #18181b;
        }

        .color-label {
          font-size: 12px;
          color: $color-neutral-700;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
      }
    }

    .toggles-list {
      display: flex;
      flex-direction: column;
      gap: $space-3;
    }

    .custom-checkbox-row {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-size: 13px;
      color: $color-neutral-800;

      .custom-check-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;

        &:checked + .check-box-indicator {
          background: $color-primary-600;
          border-color: $color-primary-600;

          &::after {
            display: block;
          }
        }

        &:checked + .radio-indicator {
          background: $color-primary-600;
          border-color: $color-primary-600;
        }
      }

      .check-box-indicator {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        border: 1.5px solid $color-neutral-300;
        background: #ffffff;
        position: relative;
        flex-shrink: 0;

        &::after {
          content: '';
          position: absolute;
          display: none;
          left: 5px;
          top: 2px;
          width: 4px;
          height: 8px;
          border: solid white;
          border-width: 0 2px 2px 0;
          transform: rotate(45deg);
        }

        &.radio-indicator {
          border-radius: 50%;

          &::after {
            left: 5px;
            top: 5px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ffffff;
            border: none;
            transform: none;
          }
        }
      }

      .check-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: $fw-medium;

        .stock-live-dot {
          width: 8px;
          height: 8px;
          border-radius: 50%;
          background: $color-success;
        }
      }

      &.brand-row {
        justify-content: space-between;
        padding: 6px 8px;
        border-radius: $radius-md;

        .brand-name-label {
          flex: 1;
          display: flex;
          align-items: center;
          gap: 6px;
        }

        .brand-origin-badge {
          font-size: 10px;
          padding: 1px 5px;
          background: $color-neutral-100;
          border-radius: $radius-sm;
          color: $color-neutral-500;
        }

        .brand-count {
          font-size: 10px;
          color: $color-neutral-400;
          font-weight: $fw-bold;
        }
      }
    }

    .brands-list-scroller {
      max-height: 240px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
  }

  .drawer-footer {
    padding: $space-4 $space-5;
    border-top: 1px solid $color-neutral-200;
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: $space-3;
    background: $color-neutral-50;

    .apply-drawer-btn {
      font-weight: $fw-bold;
    }
  }
}

@keyframes spin-loader {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
