<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ProductItem {
  id: string;
  name: string;
  slug: string;
  thumbnail_url: string | null;
  hs_code: string | null;
  origin_country: string;
  warranty_policy: string | null;
  category_name: string;
  brand_name: string;
  variants_count: number;
  min_price_bdt: number;
  max_price_bdt: number;
  total_stock: number;
  is_published: boolean;
  is_featured: boolean;
  created_at: string;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedProducts {
  data: ProductItem[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: PaginationLink[];
}

interface CategoryOption {
  id: number;
  name: string;
}

interface Filters {
  search?: string | null;
  category_id?: string | number | null;
  status?: string | null;
}

const props = defineProps<{
  products: PaginatedProducts;
  categories: CategoryOption[];
  filters: Filters;
}>();

// Search & Filter state
const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category_id || 'all');
const selectedStatus = ref(props.filters.status || 'all');

const hasActiveFilters = computed(() => {
  return (
    Boolean(search.value) ||
    (selectedCategory.value !== 'all' && selectedCategory.value !== '') ||
    (selectedStatus.value !== 'all' && selectedStatus.value !== '')
  );
});

const handleSearch = () => {
  router.get(
    '/admin/products',
    {
      search: search.value.trim() || undefined,
      category_id: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
      status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
    },
    { preserveState: true }
  );
};

const clearSearch = () => {
  search.value = '';
  handleSearch();
};

const resetFilters = () => {
  search.value = '';
  selectedCategory.value = 'all';
  selectedStatus.value = 'all';
  router.get('/admin/products');
};

// Dropdown Action Menu state
const activeDropdownId = ref<string | null>(null);

const toggleDropdown = (id: string) => {
  activeDropdownId.value = activeDropdownId.value === id ? null : id;
};

const closeDropdown = () => {
  activeDropdownId.value = null;
};

onMounted(() => {
  window.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
  window.removeEventListener('click', closeDropdown);
});

const deleteProduct = (product: ProductItem) => {
  activeDropdownId.value = null;
  if (confirm(`Are you sure you want to delete product "${product.name}" and all associated matrix variants?`)) {
    router.delete(`/admin/products/${product.id}`);
  }
};
</script>

<template>
  <AdminLayout title="Product Catalog & Matrix Management">
    <Head title="Products & Matrix Catalog" />

    <div class="catalog-container">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="header-title">Product Catalog & Matrix Variants</h1>
          <p class="header-subtitle">
            Manage single-vendor commercial goods, customs HS tariff classifications, origin countries, and multi-attribute variants.
          </p>
        </div>
        <div class="header-actions">
          <a
            href="/shop"
            target="_blank"
            class="btn btn-storefront-view"
            title="Open public storefront catalog in a new tab"
          >
            <span>🛍️</span> View Public Shop
            <span class="ext-arrow">↗</span>
          </a>
          <Link href="/admin/products/create" class="btn btn-primary">
            <span>➕</span> Add New Product
          </Link>
        </div>
      </div>

      <!-- Search & Filters Toolbar -->
      <div class="filters-card">
        <div class="search-box">
          <span class="search-icon">🔍</span>
          <input
            v-model="search"
            type="text"
            class="search-input"
            placeholder="Search products by title, SKU, brand, or HS code..."
            @keyup.enter="handleSearch"
          />
          <button
            v-if="search"
            type="button"
            class="clear-search-btn"
            title="Clear search"
            @click="clearSearch"
          >
            ✕
          </button>
        </div>

        <div class="filter-controls">
          <div class="select-wrap">
            <select v-model="selectedCategory" class="filter-select" @change="handleSearch">
              <option value="all">All Categories</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div class="select-wrap">
            <select v-model="selectedStatus" class="filter-select" @change="handleSearch">
              <option value="all">All Statuses</option>
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
          </div>

          <button type="button" class="btn btn-primary btn-search" @click="handleSearch">
            Search
          </button>

          <button
            v-if="hasActiveFilters"
            type="button"
            class="btn btn-outline btn-reset"
            title="Reset all filters"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Products Data Table Card -->
      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Product Information</th>
                <th>Category & Brand</th>
                <th>Origin & HS Code</th>
                <th>Price & Variants</th>
                <th>Stock Level</th>
                <th>Status</th>
                <th class="text-right" style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in products.data" :key="product.id">
                <!-- Product Information Column: Thumbnail & Clean Title Only, nothing under title -->
                <td>
                  <div class="product-identity">
                    <div class="product-thumb-wrap">
                      <img
                        v-if="product.thumbnail_url"
                        :src="product.thumbnail_url"
                        :alt="product.name"
                        class="product-thumb-img"
                        loading="lazy"
                        @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
                      />
                      <div v-else class="product-thumb-placeholder">
                        <span class="product-icon">📦</span>
                      </div>
                    </div>
                    <div class="product-info-wrap">
                      <Link
                        :href="`/admin/products/${product.id}/edit`"
                        class="product-name"
                        :title="`Edit ${product.name}`"
                      >
                        {{ product.name }}
                      </Link>
                    </div>
                  </div>
                </td>

                <!-- Category & Brand -->
                <td>
                  <div class="cat-brand-cell">
                    <span class="cat-name">{{ product.category_name }}</span>
                    <span class="brand-name">{{ product.brand_name }}</span>
                  </div>
                </td>

                <!-- Origin & HS Code -->
                <td>
                  <div class="origin-cell">
                    <span class="origin-tag">🌍 {{ product.origin_country }}</span>
                    <span v-if="product.hs_code" class="badge badge-info">
                      HS: {{ product.hs_code }}
                    </span>
                  </div>
                </td>

                <!-- Price & Variants -->
                <td>
                  <div class="price-cell">
                    <span class="price-range">
                      ৳{{ product.min_price_bdt.toLocaleString() }}
                      <span v-if="product.min_price_bdt !== product.max_price_bdt">
                        - ৳{{ product.max_price_bdt.toLocaleString() }}
                      </span>
                    </span>
                    <div class="price-badges">
                      <span class="badge badge-neutral">
                        {{ product.variants_count }} {{ product.variants_count === 1 ? 'Variant' : 'Variants' }}
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Stock Level -->
                <td>
                  <div class="stock-cell">
                    <span
                      class="stock-pill"
                      :class="product.total_stock > 10 ? 'stock-healthy' : product.total_stock > 0 ? 'stock-low' : 'stock-out'"
                      :title="`Current physical inventory in warehouse: ${product.total_stock} units`"
                    >
                      <span v-if="product.total_stock > 10">✅ {{ product.total_stock }} in stock</span>
                      <span v-else-if="product.total_stock > 0">⚠️ Low: {{ product.total_stock }} left</span>
                      <span v-else>❌ 0 in stock</span>
                    </span>
                  </div>
                </td>

                <!-- Status -->
                <td>
                  <span v-if="product.is_published" class="badge badge-success">
                    Published
                  </span>
                  <span v-else class="badge badge-warning">
                    Draft
                  </span>
                </td>

                <!-- Dropdown Action Button -->
                <td class="text-right actions-cell">
                  <div class="action-dropdown-wrap">
                    <button
                      type="button"
                      class="btn-dropdown-trigger"
                      :class="{ 'is-active': activeDropdownId === product.id }"
                      title="Actions"
                      @click.stop="toggleDropdown(product.id)"
                    >
                      <span>Actions</span>
                      <span class="chevron" :class="{ 'is-open': activeDropdownId === product.id }">▾</span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                      v-if="activeDropdownId === product.id"
                      class="action-dropdown-menu"
                      @click.stop
                    >
                      <Link
                        :href="`/admin/products/${product.id}/edit`"
                        class="dropdown-item"
                        @click="closeDropdown"
                      >
                        <span class="dropdown-item-icon">✏️</span>
                        <span>Edit Product</span>
                      </Link>

                      <Link
                        :href="`/admin/products/${product.id}/matrix`"
                        class="dropdown-item"
                        @click="closeDropdown"
                      >
                        <span class="dropdown-item-icon">⚡</span>
                        <span>Matrix Variants</span>
                      </Link>

                      <a
                        :href="`/products/${product.slug}`"
                        target="_blank"
                        class="dropdown-item"
                        @click="closeDropdown"
                      >
                        <span class="dropdown-item-icon">👁️</span>
                        <span>Public View ↗</span>
                      </a>

                      <div class="dropdown-divider"></div>

                      <button
                        type="button"
                        class="dropdown-item dropdown-item-danger"
                        @click="deleteProduct(product)"
                      >
                        <span class="dropdown-item-icon">🗑️</span>
                        <span>Delete Product</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="products.data.length === 0">
                <td colspan="7" class="empty-state-cell">
                  <div class="empty-catalog-box">
                    <span class="empty-catalog-icon">📦</span>
                    <h3 class="empty-catalog-title">No products found</h3>
                    <p class="empty-catalog-desc">
                      <span v-if="hasActiveFilters">
                        No products match your current search and filter criteria. Try resetting your filters.
                      </span>
                      <span v-else>
                        Get started by adding your first commercial product with HS tariffs, origin details, and matrix variants.
                      </span>
                    </p>
                    <button
                      v-if="hasActiveFilters"
                      type="button"
                      class="btn btn-primary"
                      @click="resetFilters"
                    >
                      Reset All Filters
                    </button>
                    <Link
                      v-else
                      href="/admin/products/create"
                      class="btn btn-primary btn-lg"
                    >
                      <span>➕</span> Add New Product
                    </Link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="products.links && products.links.length > 3" class="pagination-container">
          <div class="pagination-info">
            Showing <strong>{{ products.from || 0 }}</strong> to <strong>{{ products.to || 0 }}</strong> of <strong>{{ products.total }}</strong> products
          </div>
          <div class="pagination-links">
            <template v-for="(link, i) in products.links" :key="i">
              <Link
                v-if="link.url"
                :href="link.url"
                class="pagination-link"
                :class="{ 'is-active': link.active }"
                v-html="link.label"
              />
              <span v-else class="pagination-link is-disabled" v-html="link.label" />
            </template>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.catalog-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: $spacing-4;

  .header-title {
    font-size: $font-size-2xl;
    font-weight: 700;
    color: hsl(342, 45%, 15%);
    margin-bottom: $spacing-1;
    font-family: $font-heading;
  }

  .header-subtitle {
    font-size: $font-size-sm;
    color: $color-text-muted;
    max-width: 650px;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: $spacing-3;
    flex-wrap: wrap;
  }
}

.btn-storefront-view {
  display: inline-flex;
  align-items: center;
  gap: $spacing-2;
  padding: 9px 16px;
  border-radius: $radius-lg;
  background-color: $color-primary-50;
  color: $color-primary-700;
  border: 1px solid $color-primary-200;
  font-size: $font-size-sm;
  font-weight: 600;
  text-decoration: none;
  transition: all $transition-base;

  .ext-arrow {
    font-size: 13px;
    opacity: 0.8;
    transition: transform $transition-fast;
  }

  &:hover {
    background-color: $color-primary-100;
    border-color: $color-primary-400;
    color: $color-primary-800;
    box-shadow: 0 4px 12px rgba(235, 40, 108, 0.15);
    transform: translateY(-1px);

    .ext-arrow {
      transform: translate(2px, -2px);
    }
  }
}

// SEARCH & FILTERS TOOLBAR
.filters-card {
  background: #ffffff;
  border: 1px solid hsl(342, 40%, 92%);
  border-radius: $radius-xl;
  padding: $spacing-4;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: $spacing-4;
  flex-wrap: wrap;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);

  .search-box {
    position: relative;
    flex: 1;
    min-width: 280px;

    .search-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 14px;
      opacity: 0.6;
      pointer-events: none;
    }

    .search-input {
      width: 100%;
      padding: 10px 38px 10px 40px;
      background: hsl(342, 40%, 98%);
      border: 1px solid hsl(342, 30%, 88%);
      border-radius: $radius-lg;
      font-size: $font-size-sm;
      color: $color-text-main;
      outline: none;
      transition: all $transition-fast;

      &::placeholder {
        color: #94a3b8;
      }

      &:focus {
        background: #ffffff;
        border-color: $color-primary-500;
        box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
      }
    }

    .clear-search-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #94a3b8;
      font-size: 14px;
      cursor: pointer;
      padding: 4px;

      &:hover {
        color: #ef4444;
      }
    }
  }

  .filter-controls {
    display: flex;
    align-items: center;
    gap: $spacing-3;
    flex-wrap: wrap;

    .select-wrap {
      .filter-select {
        padding: 10px 14px;
        background: hsl(342, 40%, 98%);
        border: 1px solid hsl(342, 30%, 88%);
        border-radius: $radius-lg;
        font-size: $font-size-sm;
        color: $color-text-main;
        outline: none;
        cursor: pointer;
        transition: all $transition-fast;

        &:focus {
          background: #ffffff;
          border-color: $color-primary-500;
          box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
        }
      }
    }

    .btn-search {
      padding: 10px 18px;
      font-size: $font-size-sm;
      font-weight: 600;
    }

    .btn-reset {
      padding: 10px 14px;
      font-size: $font-size-sm;
      font-weight: 600;
      border-color: hsl(342, 30%, 88%);
      color: #64748b;

      &:hover {
        background: #f1f5f9;
        color: #0f172a;
      }
    }
  }
}

// TABLE & ACTIONS
.table-card {
  padding: 0;
  overflow: visible; // Allows dropdown menu to overflow cleanly
  border: 1px solid hsl(342, 40%, 92%);
  box-shadow: 0 4px 12px rgba(235, 40, 108, 0.04), 0 1px 3px rgba(15, 23, 42, 0.04);
  background-color: #ffffff;
}

.table-responsive {
  overflow-x: auto;
  overflow-y: visible;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;

  th {
    background: hsl(342, 50%, 98%);
    padding: $spacing-3 $spacing-4;
    font-size: $font-size-xs;
    font-weight: 700;
    color: hsl(342, 35%, 35%);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid hsl(342, 35%, 90%);
  }

  td {
    padding: $spacing-4;
    border-bottom: 1px solid hsl(342, 30%, 93%);
    font-size: $font-size-sm;
    transition: background-color $transition-fast;
    vertical-align: middle;
  }

  tr:hover td {
    background-color: hsl(342, 60%, 99.2%);
  }

  tr:last-child td {
    border-bottom: none;
  }

  .text-right {
    text-align: right;
  }
}

// PRODUCT IDENTITY (Clean Thumbnail + Title Only, Nothing Under Title)
.product-identity {
  display: flex;
  align-items: center;
  gap: $spacing-3;

  .product-thumb-wrap {
    width: 44px;
    height: 44px;
    border-radius: $radius-md;
    border: 1px solid hsl(342, 30%, 88%);
    background: #ffffff;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

    .product-thumb-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .product-thumb-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: hsl(342, 45%, 98%);

      .product-icon {
        font-size: 1.25rem;
      }
    }
  }

  .product-info-wrap {
    min-width: 0;

    .product-name {
      font-weight: 700;
      color: $color-text-main;
      font-size: 14.5px;
      text-decoration: none;
      line-height: 1.4;
      display: inline-block;
      transition: color $transition-fast;

      &:hover {
        color: $color-primary-600;
        text-decoration: underline;
      }
    }
  }
}

.cat-brand-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;

  .cat-name {
    font-weight: 600;
    color: $color-text-main;
  }

  .brand-name {
    font-size: $font-size-xs;
    color: $color-text-muted;
  }
}

.origin-cell {
  display: flex;
  flex-direction: column;
  gap: $spacing-1;

  .origin-tag {
    font-size: $font-size-xs;
    color: $color-text-main;
  }
}

.price-cell {
  display: flex;
  flex-direction: column;
  gap: $spacing-1;

  .price-range {
    font-weight: 700;
    color: $color-primary-600;
    font-size: $font-size-sm;
  }

  .price-badges {
    display: flex;
    align-items: center;
    gap: $spacing-1;
    flex-wrap: wrap;
  }
}

.stock-cell {
  display: flex;
  align-items: center;

  .stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: $radius-full;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;

    &.stock-healthy {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
    }

    &.stock-low {
      background: #fffbeb;
      color: #b45309;
      border: 1px solid #fde68a;
    }

    &.stock-out {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }
  }
}

// DROPDOWN ACTION BUTTON
.actions-cell {
  position: relative;
}

.action-dropdown-wrap {
  position: relative;
  display: inline-block;
  text-align: right;

  .btn-dropdown-trigger {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 6px 14px;
    border-radius: $radius-md;
    font-size: $font-size-xs;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all $transition-fast;
    white-space: nowrap;

    .chevron {
      font-size: 10px;
      transition: transform $transition-fast;

      &.is-open {
        transform: rotate(180deg);
      }
    }

    &:hover,
    &.is-active {
      background: hsl(342, 95%, 97%);
      border-color: $color-primary-400;
      color: $color-primary-700;
      box-shadow: 0 2px 6px rgba(235, 40, 108, 0.15);
    }
  }

  .action-dropdown-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: $radius-lg;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04);
    min-width: 175px;
    z-index: 100;
    padding: 6px 0;
    text-align: left;
    animation: fadeIn 0.15s ease;

    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 16px;
      font-size: $font-size-xs;
      font-weight: 600;
      color: #334155;
      text-decoration: none;
      width: 100%;
      border: none;
      background: transparent;
      cursor: pointer;
      transition: background-color $transition-fast, color $transition-fast;
      white-space: nowrap;

      .dropdown-item-icon {
        font-size: 13px;
        width: 16px;
        display: inline-flex;
        justify-content: center;
      }

      &:hover {
        background-color: hsl(342, 95%, 97%);
        color: $color-primary-700;
      }

      &.dropdown-item-danger {
        color: #ef4444;

        &:hover {
          background-color: #fef2f2;
          color: #dc2626;
        }
      }
    }

    .dropdown-divider {
      height: 1px;
      background-color: #f1f5f9;
      margin: 4px 0;
    }
  }
}

// PAGINATION
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: $spacing-4 $spacing-6;
  border-top: 1px solid hsl(342, 35%, 93%);
  background: hsl(342, 50%, 99.5%);
  flex-wrap: wrap;
  gap: $spacing-3;

  .pagination-info {
    font-size: $font-size-xs;
    color: $color-text-muted;

    strong {
      color: $color-text-main;
      font-weight: 700;
    }
  }

  .pagination-links {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;

    .pagination-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 32px;
      height: 32px;
      padding: 0 10px;
      border-radius: $radius-md;
      font-size: $font-size-xs;
      font-weight: 600;
      text-decoration: none;
      color: #475569;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      transition: all $transition-fast;

      &:hover:not(.is-disabled) {
        background: hsl(342, 95%, 97%);
        border-color: $color-primary-400;
        color: $color-primary-700;
      }

      &.is-active {
        background: $color-primary-600;
        border-color: $color-primary-600;
        color: #ffffff;
        font-weight: 700;
      }

      &.is-disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: #f8fafc;
      }
    }
  }
}

// EMPTY STATE
.empty-state-cell {
  text-align: center;
  padding: $spacing-12 $spacing-4;

  .empty-catalog-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: $spacing-3;
    max-width: 440px;
    margin: 0 auto;

    .empty-catalog-icon {
      font-size: 3rem;
      opacity: 0.7;
    }

    .empty-catalog-title {
      font-size: $font-size-lg;
      font-weight: 700;
      color: $color-text-main;
      margin: 0;
    }

    .empty-catalog-desc {
      font-size: $font-size-sm;
      color: $color-text-muted;
      margin: 0 0 $spacing-2;
      line-height: 1.5;
    }

    .btn-lg {
      padding: $spacing-3 $spacing-6;
      font-size: $font-size-base;
      font-weight: 600;
    }
  }
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px $spacing-2;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  font-weight: 600;

  &.badge-info {
    background: hsl(342, 85%, 95%);
    color: $color-primary-700;
    border: 1px solid hsl(342, 60%, 88%);
  }

  &.badge-neutral {
    background: hsl(342, 30%, 96%);
    color: hsl(342, 25%, 35%);
  }

  &.badge-warning {
    background: hsla(38, 92%, 50%, 0.12);
    color: $color-warning;
  }

  &.badge-success {
    background: hsla(142, 71%, 45%, 0.12);
    color: $color-success;
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
