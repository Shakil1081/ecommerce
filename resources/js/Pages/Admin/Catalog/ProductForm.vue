<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CategoryOption {
  id: number;
  name: string;
}

interface BrandOption {
  id: number;
  name: string;
  origin_country: string;
}

interface ProductData {
  id: string;
  name: string;
  category_id: number;
  brand_id: number | null;
  hs_code: string | null;
  origin_country: string;
  warranty_policy: string | null;
  description: string | null;
  specifications: Record<string, string>;
  is_published: boolean;
  is_featured: boolean;
}

const props = defineProps<{
  product?: ProductData | null;
  categories: CategoryOption[];
  brands: BrandOption[];
  countries: string[];
}>();

const isEditing = !!props.product;

// Initial specifications rows
const initialSpecs = props.product?.specifications
  ? Object.entries(props.product.specifications).map(([k, v]) => ({ key: k, value: v }))
  : [{ key: '', value: '' }];

const specRows = ref(initialSpecs);

const form = useForm({
  name: props.product ? props.product.name : '',
  category_id: props.product ? props.product.category_id : (props.categories[0]?.id || ''),
  brand_id: props.product ? props.product.brand_id : '',
  hs_code: props.product ? props.product.hs_code : '',
  origin_country: props.product ? props.product.origin_country : 'Bangladesh',
  warranty_policy: props.product ? props.product.warranty_policy : '',
  description: props.product ? props.product.description : '',
  default_selling_price: 1000,
  default_purchase_cost: 700,
  specifications: {} as Record<string, string>,
  is_published: props.product ? props.product.is_published : true,
  is_featured: props.product ? props.product.is_featured : false,
});

const addSpecRow = () => {
  specRows.value.push({ key: '', value: '' });
};

const removeSpecRow = (index: number) => {
  specRows.value.splice(index, 1);
};

const submit = () => {
  // Convert specRows to key-value object
  const specsMap: Record<string, string> = {};
  specRows.value.forEach(row => {
    if (row.key.trim() !== '') {
      specsMap[row.key.trim()] = row.value.trim();
    }
  });
  form.specifications = specsMap;

  if (isEditing && props.product) {
    form.put(`/admin/products/${props.product.id}`);
  } else {
    form.post('/admin/products');
  }
};
</script>

<template>
  <AdminLayout :title="isEditing ? `Edit Product: ${product?.name}` : 'Add New Product'">
    <Head :title="isEditing ? `Edit Product - ${product?.name}` : 'Add New Product'" />

    <div class="product-form-container">
      <div class="form-header">
        <div>
          <h1 class="header-title">
            {{ isEditing ? `Edit Product: ${product?.name}` : 'Add New Commercial Product' }}
          </h1>
          <p class="header-subtitle">
            Configure catalog item details, customs HS codes, and sourcing attributes.
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/products" class="btn btn-secondary">
            Cancel
          </Link>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Product') }}
          </button>
        </div>
      </div>

      <form @submit.prevent="submit" class="product-form">
        <!-- Basic Information Card -->
        <div class="card form-card">
          <h2 class="card-title">General Information</h2>

          <div class="form-grid">
            <div class="form-group span-2">
              <label class="form-label" for="prod-name">Product Name <span class="required">*</span></label>
              <input
                id="prod-name"
                v-model="form.name"
                type="text"
                class="form-control"
                placeholder="e.g. Xiaomi Watch S3"
                required
              />
              <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="prod-cat">Category <span class="required">*</span></label>
              <select id="prod-cat" v-model="form.category_id" class="form-control" required>
                <option value="" disabled>Select Category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
              <span v-if="form.errors.category_id" class="form-error">{{ form.errors.category_id }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="prod-brand">Brand / Manufacturer</label>
              <select id="prod-brand" v-model="form.brand_id" class="form-control">
                <option value="">Generic / No Brand</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">
                  {{ b.name }} ({{ b.origin_country }})
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Sourcing & Regulatory Customs Information Card -->
        <div class="card form-card">
          <h2 class="card-title">Sourcing, Customs & Compliance</h2>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="prod-country">Country of Origin <span class="required">*</span></label>
              <select id="prod-country" v-model="form.origin_country" class="form-control" required>
                <option v-for="country in countries" :key="country" :value="country">
                  {{ country }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="prod-hs">
                Customs HS Tariff Code
                <span class="optional-hint">(8-digit BOE Bill of Entry classification)</span>
              </label>
              <input
                id="prod-hs"
                v-model="form.hs_code"
                type="text"
                class="form-control"
                placeholder="e.g. 8517.62.90"
              />
            </div>

            <div class="form-group span-2">
              <label class="form-label" for="prod-warranty">Commercial Warranty Policy</label>
              <input
                id="prod-warranty"
                v-model="form.warranty_policy"
                type="text"
                class="form-control"
                placeholder="e.g. 1 Year Official Brand Warranty / 7 Days Replacement"
              />
            </div>
          </div>
        </div>

        <!-- Initial Base Pricing (Create Only) -->
        <div v-if="!isEditing" class="card form-card">
          <h2 class="card-title">Base Variant Pricing</h2>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="base-price">Retail Selling Price (BDT ৳) <span class="required">*</span></label>
              <input
                id="base-price"
                v-model.number="form.default_selling_price"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label" for="base-cost">Supplier Purchase Cost / Landed Cost (BDT ৳)</label>
              <input
                id="base-cost"
                v-model.number="form.default_purchase_cost"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
              />
            </div>
          </div>
        </div>

        <!-- Technical Specifications Table -->
        <div class="card form-card">
          <div class="card-header-flex">
            <div>
              <h2 class="card-title">Technical Specifications</h2>
              <p class="card-subtitle">Detailed product spec sheet displayed on product page.</p>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" @click="addSpecRow">
              ➕ Add Specification
            </button>
          </div>

          <div class="specs-table-wrap">
            <div v-for="(row, idx) in specRows" :key="idx" class="spec-row">
              <input
                v-model="row.key"
                type="text"
                class="form-control"
                placeholder="Feature (e.g. Battery Capacity)"
              />
              <input
                v-model="row.value"
                type="text"
                class="form-control"
                placeholder="Specification (e.g. 5000 mAh)"
              />
              <button
                type="button"
                class="btn-icon-danger"
                title="Remove specification"
                @click="removeSpecRow(idx)"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <!-- Publishing & Visibility -->
        <div class="card form-card">
          <h2 class="card-title">Visibility & Storefront Settings</h2>

          <div class="toggle-grid">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.is_published" />
              <span>Publish to Storefront (Visible to Customers)</span>
            </label>
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.is_featured" />
              <span>Feature on Homepage and Bestseller Carousel</span>
            </label>
          </div>
        </div>

        <div class="form-footer">
          <Link href="/admin/products" class="btn btn-secondary">
            Cancel
          </Link>
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Product') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.product-form-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.form-header {
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
  }

  .header-actions {
    display: flex;
    gap: $spacing-3;
  }
}

.product-form {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.form-card {
  display: flex;
  flex-direction: column;
  gap: $spacing-4;
}

.card-title {
  font-size: $font-size-lg;
  font-weight: 600;
  color: $color-text-main;
}

.card-subtitle {
  font-size: $font-size-xs;
  color: $color-text-muted;
}

.card-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: $spacing-4;

  .span-2 {
    grid-column: span 2;

    @media (max-width: 768px) {
      grid-column: span 1;
    }
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: $spacing-2;
}

.form-label {
  font-size: $font-size-sm;
  font-weight: 600;
  color: $color-text-main;

  .required {
    color: $color-danger;
  }

  .optional-hint {
    font-size: $font-size-xs;
    font-weight: normal;
    color: $color-text-muted;
  }
}

.form-control {
  padding: $spacing-3 $spacing-4;
  border-radius: $radius-md;
  border: 1px solid $color-border;
  background: $color-surface;
  color: $color-text-main;
  font-size: $font-size-sm;
  outline: none;
  transition: border-color $transition-fast;

  &:focus {
    border-color: $color-primary;
  }
}

.form-error {
  font-size: $font-size-xs;
  color: $color-danger;
}

.specs-table-wrap {
  display: flex;
  flex-direction: column;
  gap: $spacing-3;
}

.spec-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: $spacing-3;
  align-items: center;
}

.btn-icon-danger {
  background: none;
  border: none;
  color: $color-danger;
  font-size: 1.1rem;
  font-weight: bold;
  cursor: pointer;
  padding: $spacing-2;

  &:hover {
    opacity: 0.8;
  }
}

.toggle-grid {
  display: flex;
  flex-direction: column;
  gap: $spacing-3;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: $spacing-3;
  font-size: $font-size-sm;
  color: $color-text-main;
  cursor: pointer;

  input[type="checkbox"] {
    cursor: pointer;
  }
}

.form-footer {
  display: flex;
  justify-content: flex-end;
  gap: $spacing-3;
}
</style>

