<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ProductItem {
  id: string;
  name: string;
  slug: string;
  hs_code: string | null;
  origin_country: string;
  warranty_policy: string | null;
  category_name: string;
  brand_name: string;
  variants_count: number;
  min_price_bdt: number;
  max_price_bdt: number;
  is_published: boolean;
  is_featured: boolean;
  created_at: string;
}

defineProps<{
  products: ProductItem[];
}>();

const deleteProduct = (product: ProductItem) => {
  if (confirm(`Are you sure you want to delete product "${product.name}" and all associated matrix variants?`)) {
    router.delete(`/admin/products/${product.id}`);
  }
};
</script>

<template>
  <AdminLayout title="Product Catalog & Matrix Management">
    <Head title="Products & Matrix Catalog" />

    <div class="catalog-container">
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

      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Product Information</th>
                <th>Category & Brand</th>
                <th>Origin & HS Code</th>
                <th>Variants & Price Range</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in products" :key="product.id">
                <td>
                  <div class="product-identity">
                    <span class="product-icon">📦</span>
                    <div class="product-info-wrap">
                      <div class="product-name-row">
                        <span class="product-name">{{ product.name }}</span>
                        <a
                          :href="`/products/${product.slug}`"
                          target="_blank"
                          class="public-link-pill"
                          title="Open product live on public storefront in new tab"
                        >
                          <span class="pill-label">Public View</span>
                          <span class="ext-arrow">↗</span>
                        </a>
                      </div>
                      <div class="product-meta-row">
                        <a
                          :href="`/products/${product.slug}`"
                          target="_blank"
                          class="public-slug-link"
                          title="View public URL: /products/{{ product.slug }}"
                        >
                          /products/{{ product.slug }}
                        </a>
                        <span class="product-warranty" v-if="product.warranty_policy">
                          • 🛡️ {{ product.warranty_policy }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="cat-brand-cell">
                    <span class="cat-name">{{ product.category_name }}</span>
                    <span class="brand-name">{{ product.brand_name }}</span>
                  </div>
                </td>
                <td>
                  <div class="origin-cell">
                    <span class="origin-tag">🌍 {{ product.origin_country }}</span>
                    <span v-if="product.hs_code" class="badge badge-info">
                      HS: {{ product.hs_code }}
                    </span>
                  </div>
                </td>
                <td>
                  <div class="price-cell">
                    <span class="price-range">
                      ৳{{ product.min_price_bdt.toLocaleString() }}
                      <span v-if="product.min_price_bdt !== product.max_price_bdt">
                        - ৳{{ product.max_price_bdt.toLocaleString() }}
                      </span>
                    </span>
                    <span class="badge badge-neutral">
                      {{ product.variants_count }} {{ product.variants_count === 1 ? 'Variant' : 'Variants' }}
                    </span>
                  </div>
                </td>
                <td>
                  <span v-if="product.is_published" class="badge badge-success">
                    Published
                  </span>
                  <span v-else class="badge badge-warning">
                    Draft
                  </span>
                </td>
                <td class="text-right actions-cell">
                  <a
                    :href="`/products/${product.slug}`"
                    target="_blank"
                    class="btn btn-sm btn-view-public"
                    title="View live product on public storefront (opens in new tab)"
                  >
                    <span>👁️</span> Public View
                  </a>
                  <Link :href="`/admin/products/${product.id}/matrix`" class="btn btn-sm btn-primary">
                    ⚡ Matrix
                  </Link>
                  <Link :href="`/admin/products/${product.id}/edit`" class="btn btn-sm btn-secondary">
                    Edit
                  </Link>
                  <button class="btn btn-sm btn-danger" @click="deleteProduct(product)">
                    Delete
                  </button>
                </td>
              </tr>
              <tr v-if="products.length === 0">
                <td colspan="6" class="empty-state-cell">
                  <div class="empty-catalog-box">
                    <span class="empty-catalog-icon">📦</span>
                    <h3 class="empty-catalog-title">No products found in the catalog</h3>
                    <p class="empty-catalog-desc">
                      Get started by adding your first commercial product with HS tariffs, origin details, and matrix variants.
                    </p>
                    <Link href="/admin/products/create" class="btn btn-primary btn-lg">
                      <span>➕</span> Add New Product
                    </Link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
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

.table-card {
  padding: 0;
  overflow: hidden;
  border: 1px solid hsl(342, 40%, 92%);
  box-shadow: 0 4px 12px rgba(235, 40, 108, 0.04), 0 1px 3px rgba(15, 23, 42, 0.04);
  background-color: #ffffff;
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

.product-identity {
  display: flex;
  align-items: flex-start;
  gap: $spacing-3;

  .product-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
    margin-top: 2px;
  }

  .product-info-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .product-name-row {
    display: flex;
    align-items: center;
    gap: $spacing-2;
    flex-wrap: wrap;

    .product-name {
      font-weight: 600;
      color: $color-text-main;
      font-size: 14.5px;
    }

    .public-link-pill {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 1px 7px;
      border-radius: $radius-full;
      background: $color-primary-50;
      border: 1px solid $color-primary-200;
      color: $color-primary-600;
      font-size: 11px;
      font-weight: 600;
      text-decoration: none;
      transition: all $transition-fast;

      .ext-arrow {
        font-size: 10px;
        transition: transform $transition-fast;
      }

      &:hover {
        background: $color-primary-500;
        border-color: $color-primary-500;
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(235, 40, 108, 0.3);

        .ext-arrow {
          transform: translate(1px, -1px);
        }
      }
    }
  }

  .product-meta-row {
    display: flex;
    align-items: center;
    gap: $spacing-2;
    flex-wrap: wrap;

    .public-slug-link {
      font-family: $font-mono;
      font-size: 11.5px;
      color: hsl(342, 45%, 45%);
      text-decoration: none;
      transition: color $transition-fast;

      &:hover {
        color: $color-primary-600;
        text-decoration: underline;
      }
    }

    .product-warranty {
      font-size: $font-size-xs;
      color: $color-text-muted;
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
}

.actions-cell {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: $spacing-2;
  flex-wrap: nowrap;
}

.btn-view-public {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background-color: hsl(342, 90%, 96%);
  color: $color-primary-700;
  border: 1px solid hsl(342, 70%, 85%);
  font-weight: 600;
  text-decoration: none;
  transition: all $transition-fast;

  &:hover {
    background-color: $color-primary-600;
    border-color: $color-primary-600;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(235, 40, 108, 0.35);
    transform: translateY(-1px);
  }
}

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
</style>

