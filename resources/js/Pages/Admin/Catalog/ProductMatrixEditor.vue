<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface VariantItem {
  id: string;
  sku: string;
  barcode_ean: string | null;
  attributes: Record<string, string>;
  purchase_cost_bdt: number;
  selling_price_bdt: number;
  weight_kg: number;
  is_active: boolean;
}

interface ProductDetails {
  id: string;
  name: string;
  slug: string;
  category_name: string;
  brand_name: string;
  variants: VariantItem[];
}

const props = defineProps<{
  product: ProductDetails;
}>();

// Attribute rows for dynamic matrix generator
interface AttributeRow {
  name: string;
  valuesRaw: string;
}

const attributeRows = ref<AttributeRow[]>([
  { name: 'Color', valuesRaw: 'Midnight Black, Glacier Silver' },
  { name: 'Storage', valuesRaw: '128GB, 256GB' },
]);

const addAttributeRow = () => {
  attributeRows.value.push({ name: '', valuesRaw: '' });
};

const removeAttributeRow = (index: number) => {
  attributeRows.value.splice(index, 1);
};

// Calculate total combinations in preview
const totalCombinations = computed(() => {
  const counts = attributeRows.value
    .map(row => row.valuesRaw.split(',').map(s => s.trim()).filter(Boolean).length)
    .filter(count => count > 0);

  if (counts.length === 0) return 0;
  return counts.reduce((acc, curr) => acc * curr, 1);
});

const generateForm = useForm({
  attributes: {} as Record<string, string[]>,
  selling_price_bdt: props.product.variants[0]?.selling_price_bdt || 3500,
  purchase_cost_bdt: props.product.variants[0]?.purchase_cost_bdt || 2500,
  weight_kg: props.product.variants[0]?.weight_kg || 0.500,
});

const generateVariants = () => {
  const attrsMap: Record<string, string[]> = {};
  attributeRows.value.forEach(row => {
    const key = row.name.trim();
    const vals = row.valuesRaw.split(',').map(s => s.trim()).filter(Boolean);
    if (key !== '' && vals.length > 0) {
      attrsMap[key] = vals;
    }
  });

  generateForm.attributes = attrsMap;
  generateForm.post(`/admin/products/${props.product.id}/generate-variants`);
};
</script>

<template>
  <AdminLayout :title="`Matrix Variants: ${product.name}`">
    <Head :title="`Matrix Variants - ${product.name}`" />

    <div class="matrix-editor-container">
      <div class="page-header">
        <div>
          <div class="breadcrumbs">
            <Link href="/admin/products" class="crumb-link">Products</Link>
            <span class="crumb-sep">/</span>
            <span class="crumb-current">{{ product.name }}</span>
          </div>
          <h1 class="header-title">Matrix Variant Manager</h1>
          <p class="header-subtitle">
            Generate and manage multi-dimensional SKU combinations (Size &times; Color &times; Material) for
            <strong>{{ product.name }}</strong> ({{ product.brand_name }} &bull; {{ product.category_name }}).
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/products" class="btn btn-secondary">
            Back to Products
          </Link>
        </div>
      </div>

      <!-- Current Matrix Variants Table -->
      <div class="card table-card">
        <div class="card-inner-header">
          <div>
            <h2 class="card-title">Active SKU Variants</h2>
            <p class="card-subtitle">Currently active barcode/SKU units stocked across warehouses.</p>
          </div>
          <span class="badge badge-info">{{ product.variants.length }} Variants</span>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU & Barcode</th>
                <th>Attributes</th>
                <th>Cost Price</th>
                <th>Selling Price</th>
                <th>Margin</th>
                <th>Weight</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="variant in product.variants" :key="variant.id">
                <td>
                  <div class="sku-cell">
                    <span class="sku-code">{{ variant.sku }}</span>
                    <span v-if="variant.barcode_ean" class="barcode-text">
                      EAN: {{ variant.barcode_ean }}
                    </span>
                  </div>
                </td>
                <td>
                  <div class="attrs-chips">
                    <span
                      v-for="(val, key) in variant.attributes"
                      :key="key"
                      class="attr-chip"
                    >
                      <strong>{{ key }}:</strong> {{ val }}
                    </span>
                    <span v-if="Object.keys(variant.attributes).length === 0" class="no-attrs">
                      Default Base Variant
                    </span>
                  </div>
                </td>
                <td>
                  <span class="cost-text">৳{{ variant.purchase_cost_bdt.toLocaleString() }}</span>
                </td>
                <td>
                  <span class="price-text">৳{{ variant.selling_price_bdt.toLocaleString() }}</span>
                </td>
                <td>
                  <span class="margin-badge">
                    +৳{{ (variant.selling_price_bdt - variant.purchase_cost_bdt).toLocaleString() }}
                  </span>
                </td>
                <td>
                  <span class="weight-text">{{ variant.weight_kg }} kg</span>
                </td>
                <td>
                  <span v-if="variant.is_active" class="badge badge-success">Active</span>
                  <span v-else class="badge badge-warning">Inactive</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Multi-Dimensional Matrix Generator Form -->
      <div class="card generator-card">
        <div class="generator-header">
          <div>
            <h2 class="card-title">⚡ Multi-Attribute Matrix Generator</h2>
            <p class="card-subtitle">
              Define option sets (e.g. Color, Storage, Size) to automatically generate all Cartesian product combinations with unique SKUs.
            </p>
          </div>
          <div class="combination-counter">
            <span class="calc-label">Combinations to Generate:</span>
            <span class="calc-number">{{ totalCombinations }}</span>
          </div>
        </div>

        <div class="attributes-stack">
          <div v-for="(row, idx) in attributeRows" :key="idx" class="attribute-input-row">
            <div class="attr-name-col">
              <label class="form-label">Attribute Name</label>
              <input
                v-model="row.name"
                type="text"
                class="form-control"
                placeholder="e.g. Color or Size"
              />
            </div>
            <div class="attr-vals-col">
              <label class="form-label">Values (Comma separated)</label>
              <input
                v-model="row.valuesRaw"
                type="text"
                class="form-control"
                placeholder="e.g. Midnight Black, Glacier Silver, Ocean Blue"
              />
            </div>
            <button
              type="button"
              class="btn-icon-danger"
              title="Remove attribute"
              @click="removeAttributeRow(idx)"
            >
              ✕
            </button>
          </div>
        </div>

        <button type="button" class="btn btn-sm btn-secondary add-attr-btn" @click="addAttributeRow">
          ➕ Add Another Attribute
        </button>

        <div class="generator-pricing-grid">
          <div class="form-group">
            <label class="form-label">Default Selling Price (BDT ৳)</label>
            <input
              v-model.number="generateForm.selling_price_bdt"
              type="number"
              min="0"
              step="0.01"
              class="form-control"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label">Default Purchase Cost (BDT ৳)</label>
            <input
              v-model.number="generateForm.purchase_cost_bdt"
              type="number"
              min="0"
              step="0.01"
              class="form-control"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Default Unit Weight (kg)</label>
            <input
              v-model.number="generateForm.weight_kg"
              type="number"
              min="0"
              step="0.01"
              class="form-control"
            />
          </div>
        </div>

        <div class="generator-actions">
          <button
            type="button"
            class="btn btn-primary"
            :disabled="generateForm.processing || totalCombinations === 0"
            @click="generateVariants"
          >
            {{ generateForm.processing ? 'Generating Matrix...' : `Generate ${totalCombinations} Variants` }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.matrix-editor-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: $spacing-2;
  font-size: $font-size-xs;
  margin-bottom: $spacing-2;

  .crumb-link {
    color: $color-primary;
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }

  .crumb-sep {
    color: $color-text-muted;
  }

  .crumb-current {
    color: $color-text-muted;
  }
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

.card-inner-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: $spacing-4 $spacing-6;
  border-bottom: 1px solid $color-border;
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
}

.sku-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;

  .sku-code {
    font-family: monospace;
    font-weight: 700;
    color: $color-text-main;
  }

  .barcode-text {
    font-family: monospace;
    font-size: $font-size-xs;
    color: $color-text-muted;
  }
}

.attrs-chips {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-1;

  .attr-chip {
    background: hsla(217, 91%, 60%, 0.08);
    border: 1px solid hsla(217, 91%, 60%, 0.2);
    border-radius: $radius-sm;
    padding: 2px $spacing-2;
    font-size: $font-size-xs;
    color: $color-primary;
  }

  .no-attrs {
    font-size: $font-size-xs;
    color: $color-text-muted;
    font-style: italic;
  }
}

.cost-text {
  font-size: $font-size-sm;
  color: $color-text-muted;
}

.price-text {
  font-size: $font-size-sm;
  font-weight: 700;
  color: $color-primary;
}

.margin-badge {
  font-size: $font-size-xs;
  font-weight: 600;
  color: $color-success;
  background: hsla(142, 71%, 45%, 0.1);
  padding: 2px $spacing-2;
  border-radius: $radius-sm;
}

.weight-text {
  font-size: $font-size-xs;
  color: $color-text-muted;
}

.generator-card {
  display: flex;
  flex-direction: column;
  gap: $spacing-5;
}

.generator-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: $spacing-4;
  padding-bottom: $spacing-4;
  border-bottom: 1px solid $color-border;
}

.combination-counter {
  display: flex;
  align-items: center;
  gap: $spacing-3;
  background: hsla(160, 84%, 39%, 0.1);
  border: 1px solid hsla(160, 84%, 39%, 0.25);
  padding: $spacing-2 $spacing-4;
  border-radius: $radius-md;

  .calc-label {
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-text-main;
  }

  .calc-number {
    font-size: $font-size-xl;
    font-weight: 800;
    color: $color-primary;
  }
}

.attributes-stack {
  display: flex;
  flex-direction: column;
  gap: $spacing-3;
}

.attribute-input-row {
  display: grid;
  grid-template-columns: 240px 1fr auto;
  gap: $spacing-3;
  align-items: flex-end;
}

.add-attr-btn {
  align-self: flex-start;
}

.generator-pricing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: $spacing-4;
  padding-top: $spacing-4;
  border-top: 1px solid $color-border;
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

.btn-icon-danger {
  background: none;
  border: none;
  color: $color-danger;
  font-size: 1.1rem;
  font-weight: bold;
  cursor: pointer;
  padding: $spacing-3;

  &:hover {
    opacity: 0.8;
  }
}

.generator-actions {
  display: flex;
  justify-content: flex-end;
  padding-top: $spacing-4;
  border-top: 1px solid $color-border;
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

  &.badge-success {
    background: hsla(142, 71%, 45%, 0.12);
    color: $color-success;
  }

  &.badge-warning {
    background: hsla(38, 92%, 50%, 0.12);
    color: $color-warning;
  }
}
</style>

