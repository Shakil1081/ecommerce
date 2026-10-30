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
                    <div>
                      <div class="product-name">{{ product.name }}</div>
                      <div class="product-warranty" v-if="product.warranty_policy">
                        🛡️ {{ product.warranty_policy }}
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
    color: $color-text-main;
    margin-bottom: $spacing-1;
  }

  .header-subtitle {
    font-size: $font-size-sm;
    color: $color-text-muted;
    max-width: 650px;
  }

  .header-actions {
    display: flex;
    gap: $spacing-3;
  }
}

.table-card {
  padding: 0;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;

  th {
    background: $color-surface-hover;
    padding: $spacing-3 $spacing-4;
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-text-muted;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid $color-border;
  }

  td {
    padding: $spacing-4;
    border-bottom: 1px solid $color-border;
    font-size: $font-size-sm;
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
  align-items: center;
  gap: $spacing-3;

  .product-icon {
    font-size: 1.5rem;
  }

  .product-name {
    font-weight: 600;
    color: $color-text-main;
  }

  .product-warranty {
    font-size: $font-size-xs;
    color: $color-text-muted;
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
    color: $color-primary;
    font-size: $font-size-sm;
  }
}

.actions-cell {
  display: flex;
  justify-content: flex-end;
  gap: $spacing-2;
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
    background: hsla(217, 91%, 60%, 0.12);
    color: $color-primary;
  }

  &.badge-neutral {
    background: $color-surface-hover;
    color: $color-text-muted;
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

