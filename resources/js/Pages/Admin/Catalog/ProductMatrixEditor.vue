<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface VariantItem {
  id: string;
  sku: string;
  barcode_ean: string | null;
  attributes: Record<string, string>;
  purchase_cost_bdt: number;
  selling_price_bdt: number;
  weight_kg: number;
  stock_quantity?: number;
  is_active: boolean;
}

interface ProductDetails {
  id: string;
  name: string;
  slug: string;
  category_name: string;
  brand_name: string;
  thumbnail_url: string | null;
  default_selling_price: number;
  default_purchase_cost: number;
  default_weight_kg: number;
  total_stock: number;
  variants: VariantItem[];
}

const props = defineProps<{
  product: ProductDetails;
}>();

// Attribute rows for dynamic matrix generator
interface AttributeRow {
  name: string;
  values: string[];
  inputValue: string;
}

// Initialize with smart attributes if existing variants have attributes, or clean defaults
const initializeAttributeRows = (): AttributeRow[] => {
  const existingAttrs: Record<string, Set<string>> = {};

  props.product.variants.forEach((v) => {
    if (v.attributes && typeof v.attributes === 'object') {
      Object.entries(v.attributes).forEach(([key, val]) => {
        if (!existingAttrs[key]) {
          existingAttrs[key] = new Set();
        }
        if (val) {
          existingAttrs[key].add(String(val));
        }
      });
    }
  });

  const keys = Object.keys(existingAttrs);
  if (keys.length > 0) {
    return keys.map((key) => ({
      name: key,
      values: Array.from(existingAttrs[key]),
      inputValue: '',
    }));
  }

  // Default initial configuration
  return [
    { name: 'Color', values: ['Black', 'Silver'], inputValue: '' },
    { name: 'Size', values: ['M', 'L'], inputValue: '' },
  ];
};

const attributeRows = ref<AttributeRow[]>(initializeAttributeRows());
const showPreview = ref(false);
const variantSearch = ref('');

// Quick preset buttons
const applyPreset = (presetType: 'apparel' | 'footwear' | 'tech' | 'volume') => {
  if (presetType === 'apparel') {
    attributeRows.value = [
      { name: 'Color', values: ['Black', 'Navy Blue', 'White'], inputValue: '' },
      { name: 'Size', values: ['S', 'M', 'L', 'XL'], inputValue: '' },
    ];
  } else if (presetType === 'footwear') {
    attributeRows.value = [
      { name: 'Color', values: ['Black', 'Brown'], inputValue: '' },
      { name: 'Shoe Size', values: ['40', '41', '42', '43', '44'], inputValue: '' },
    ];
  } else if (presetType === 'tech') {
    attributeRows.value = [
      { name: 'Color', values: ['Midnight', 'Starlight', 'Silver'], inputValue: '' },
      { name: 'Storage', values: ['128GB', '256GB', '512GB'], inputValue: '' },
    ];
  } else if (presetType === 'volume') {
    attributeRows.value = [
      { name: 'Volume', values: ['50ml', '100ml'], inputValue: '' },
      { name: 'Scent', values: ['Original', 'Lavender'], inputValue: '' },
    ];
  }
};

const addAttributeRow = (name = '') => {
  attributeRows.value.push({ name, values: [], inputValue: '' });
};

const removeAttributeRow = (index: number) => {
  attributeRows.value.splice(index, 1);
};

// Tag/Value management inside attribute row
const addTag = (row: AttributeRow) => {
  const val = row.inputValue.trim().replace(/^,+|,+$/g, '');
  if (!val) return;

  const parts = val.split(',').map((s) => s.trim()).filter(Boolean);
  parts.forEach((p) => {
    if (!row.values.includes(p)) {
      row.values.push(p);
    }
  });

  row.inputValue = '';
};

const removeTag = (row: AttributeRow, tagIdx: number) => {
  row.values.splice(tagIdx, 1);
};

const handleTagKeydown = (e: KeyboardEvent, row: AttributeRow) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addTag(row);
  }
};

// Calculate total Cartesian combinations
const totalCombinations = computed(() => {
  const counts = attributeRows.value
    .filter((row) => row.name.trim() !== '')
    .map((row) => row.values.length)
    .filter((count) => count > 0);

  if (counts.length === 0) return 0;
  return counts.reduce((acc, curr) => acc * curr, 1);
});

// Formula description e.g. "3 Colors × 2 Sizes = 6 Combinations"
const formulaText = computed(() => {
  const validRows = attributeRows.value.filter((r) => r.name.trim() !== '' && r.values.length > 0);
  if (validRows.length === 0) return '0 combinations';

  const parts = validRows.map((r) => `${r.values.length} ${r.name}`);
  return `${parts.join(' × ')} = ${totalCombinations.value} Total Combinations`;
});

// Form state
const baseSellingPrice = props.product.default_selling_price || props.product.variants[0]?.selling_price_bdt || 1500;
const basePurchaseCost = props.product.default_purchase_cost || props.product.variants[0]?.purchase_cost_bdt || 1000;
const baseWeight = props.product.default_weight_kg || props.product.variants[0]?.weight_kg || 0.500;

const generateForm = useForm({
  attributes: {} as Record<string, string[]>,
  selling_price_bdt: baseSellingPrice,
  purchase_cost_bdt: basePurchaseCost,
  weight_kg: baseWeight,
});

// Projected margin
const projectedMarginBdt = computed(() => {
  return Number(generateForm.selling_price_bdt || 0) - Number(generateForm.purchase_cost_bdt || 0);
});

const projectedMarginPct = computed(() => {
  const sell = Number(generateForm.selling_price_bdt || 0);
  if (sell <= 0) return 0;
  return Math.round((projectedMarginBdt.value / sell) * 100);
});

// Cartesian product for visual preview table
const previewCombinations = computed(() => {
  const validRows = attributeRows.value.filter((r) => r.name.trim() !== '' && r.values.length > 0);
  if (validRows.length === 0) return [];

  let result: Array<Record<string, string>> = [{}];

  validRows.forEach((row) => {
    const key = row.name.trim();
    const nextResult: Array<Record<string, string>> = [];

    result.forEach((prevItem) => {
      row.values.forEach((val) => {
        nextResult.push({
          ...prevItem,
          [key]: val,
        });
      });
    });

    result = nextResult;
  });

  const slugBase = props.product.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '').toUpperCase();

  return result.slice(0, 50).map((comb) => {
    const suffix = Object.values(comb).map((v) => v.toLowerCase().replace(/[^a-z0-9]+/g, '-')).join('-');
    const sku = `${slugBase}-${suffix.toUpperCase()}`;
    return {
      sku,
      attributes: comb,
    };
  });
});

const generateVariants = () => {
  const attrsMap: Record<string, string[]> = {};

  attributeRows.value.forEach((row) => {
    // Add any uncommitted text from inputValue
    if (row.inputValue.trim()) {
      addTag(row);
    }
    const key = row.name.trim();
    if (key !== '' && row.values.length > 0) {
      attrsMap[key] = row.values;
    }
  });

  if (Object.keys(attrsMap).length === 0) {
    alert('Please specify at least one attribute with values before generating.');
    return;
  }

  generateForm.attributes = attrsMap;
  generateForm.post(`/admin/products/${props.product.id}/generate-variants`, {
    preserveScroll: true,
  });
};

const deleteVariant = (variant: VariantItem) => {
  if (confirm(`Are you sure you want to delete variant SKU "${variant.sku}"?`)) {
    router.delete(`/admin/products/${props.product.id}/variants/${variant.id}`, {
      preserveScroll: true,
    });
  }
};

// Filtered variants table
const filteredVariants = computed(() => {
  const q = variantSearch.value.trim().toLowerCase();
  if (!q) return props.product.variants;

  return props.product.variants.filter((v) => {
    if (v.sku.toLowerCase().includes(q)) return true;
    if (v.barcode_ean && v.barcode_ean.toLowerCase().includes(q)) return true;
    return Object.entries(v.attributes || {}).some(([k, val]) =>
      k.toLowerCase().includes(q) || String(val).toLowerCase().includes(q)
    );
  });
});

const formatCurrency = (val: string | number) => {
  return Number(val || 0).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
  <AdminLayout :title="`Matrix Variants: ${product.name}`">
    <Head :title="`Matrix Variants - ${product.name}`" />

    <div class="matrix-editor-container">
      <!-- Breadcrumb & Navigation -->
      <div class="top-nav-row">
        <div class="breadcrumbs">
          <Link href="/admin/products" class="crumb-link">📦 Product Catalog</Link>
          <span class="crumb-sep">/</span>
          <span class="crumb-current">{{ product.name }}</span>
          <span class="crumb-sep">/</span>
          <span class="crumb-badge">Matrix Generator</span>
        </div>

        <div class="top-actions">
          <a
            :href="`/products/${product.slug}`"
            target="_blank"
            class="btn btn-outline btn-sm"
            title="View product live on public shop"
          >
            <span>👁️</span> View on Shop ↗
          </a>
          <Link :href="`/admin/products/${product.id}/edit`" class="btn btn-outline btn-sm">
            <span>✏️</span> Edit Product Details
          </Link>
          <Link href="/admin/products" class="btn btn-secondary btn-sm">
            ← Back to Products
          </Link>
        </div>
      </div>

      <!-- Product Summary Hero Card -->
      <div class="product-summary-card">
        <div class="product-hero-identity">
          <div class="product-hero-thumb">
            <img
              v-if="product.thumbnail_url"
              :src="product.thumbnail_url"
              :alt="product.name"
              class="thumb-img"
            />
            <span v-else class="thumb-icon">📦</span>
          </div>

          <div class="product-hero-meta">
            <div class="hero-badges-row">
              <span class="badge badge-category">{{ product.category_name }}</span>
              <span class="badge badge-brand">{{ product.brand_name }}</span>
            </div>
            <h1 class="hero-product-title">{{ product.name }}</h1>
            <p class="hero-subtitle">
              Matrix Variant Combinations & Barcode SKUs
            </p>
          </div>
        </div>

        <div class="product-hero-kpis">
          <div class="kpi-pill">
            <span class="kpi-label">Active SKUs</span>
            <span class="kpi-value text-accent">{{ product.variants.length }}</span>
          </div>
          <div class="kpi-pill">
            <span class="kpi-label">Warehouse Stock</span>
            <span class="kpi-value" :class="product.total_stock > 0 ? 'text-emerald' : 'text-rose'">
              {{ product.total_stock }} <small class="text-xs font-normal">units</small>
            </span>
          </div>
          <div class="kpi-pill">
            <span class="kpi-label">Base Selling Price</span>
            <span class="kpi-value">৳{{ formatCurrency(baseSellingPrice) }}</span>
          </div>
        </div>
      </div>

      <!-- MULTI-ATTRIBUTE MATRIX GENERATOR (FLAGSHIP UI) -->
      <div class="card generator-card">
        <div class="generator-header">
          <div class="header-titles">
            <div class="title-with-badge">
              <h2 class="card-title">⚡ Multi-Attribute Matrix Generator</h2>
              <span class="badge badge-generator-ready">Automated Cartesian Engine</span>
            </div>
            <p class="card-subtitle">
              Define product option sets (e.g. Size, Color, Storage, Material) to instantly generate all unique SKU combinations with warehouse stock tracking.
            </p>
          </div>

          <div class="combination-summary-pill" :class="{ 'is-active': totalCombinations > 0 }">
            <div class="counter-box">
              <span class="counter-number">{{ totalCombinations }}</span>
              <span class="counter-label">Combinations</span>
            </div>
          </div>
        </div>

        <!-- Quick 1-Click Attribute Presets -->
        <div class="presets-toolbar">
          <span class="presets-label">⚡ Quick Presets:</span>
          <div class="preset-buttons-group">
            <button
              type="button"
              class="btn-preset"
              @click="applyPreset('apparel')"
            >
              👕 Apparel (Size + Color)
            </button>
            <button
              type="button"
              class="btn-preset"
              @click="applyPreset('footwear')"
            >
              👟 Footwear (Shoe Size + Color)
            </button>
            <button
              type="button"
              class="btn-preset"
              @click="applyPreset('tech')"
            >
              📱 Tech (Storage + Color)
            </button>
            <button
              type="button"
              class="btn-preset"
              @click="applyPreset('volume')"
            >
              ⚖️ Volume (Volume + Scent)
            </button>
          </div>
        </div>

        <!-- Dynamic Attributes Stack -->
        <div class="attributes-stack">
          <div
            v-for="(row, idx) in attributeRows"
            :key="idx"
            class="attribute-row-card"
          >
            <div class="attr-row-header">
              <div class="attr-index-badge">Option #{{ idx + 1 }}</div>
              <button
                v-if="attributeRows.length > 1"
                type="button"
                class="btn-remove-attr"
                title="Remove this attribute"
                @click="removeAttributeRow(idx)"
              >
                ✕ Remove Option
              </button>
            </div>

            <div class="attr-row-body">
              <!-- Attribute Name Input -->
              <div class="attr-name-field">
                <label class="field-label">
                  Attribute Name <span class="required">*</span>
                </label>
                <input
                  v-model="row.name"
                  type="text"
                  class="field-input font-bold"
                  placeholder="e.g. Color, Size, Storage, Material"
                  required
                />
                <!-- Quick attribute name suggestion chips -->
                <div class="name-suggestions">
                  <span class="sugg-hint">Suggestions:</span>
                  <button
                    v-for="sugg in ['Color', 'Size', 'Storage', 'RAM', 'Material', 'Weight']"
                    :key="sugg"
                    type="button"
                    class="sugg-chip"
                    @click="row.name = sugg"
                  >
                    {{ sugg }}
                  </button>
                </div>
              </div>

              <!-- Interactive Tag Values Input -->
              <div class="attr-values-field">
                <div class="label-with-count">
                  <label class="field-label">Option Values</label>
                  <span class="values-count-badge">{{ row.values.length }} values defined</span>
                </div>

                <!-- Tag Container -->
                <div class="tags-input-container" @click="($event.currentTarget as HTMLElement).querySelector('input')?.focus()">
                  <!-- Rendered Value Tags -->
                  <span
                    v-for="(tag, tagIdx) in row.values"
                    :key="tagIdx"
                    class="tag-pill"
                  >
                    <span class="tag-text">{{ tag }}</span>
                    <button
                      type="button"
                      class="tag-close-btn"
                      title="Remove value"
                      @click.stop="removeTag(row, tagIdx)"
                    >
                      ✕
                    </button>
                  </span>

                  <!-- Inline Text Input for adding tags -->
                  <input
                    v-model="row.inputValue"
                    type="text"
                    class="tag-inline-input"
                    placeholder="Type value & press Enter or comma..."
                    @keydown="handleTagKeydown($event, row)"
                    @blur="addTag(row)"
                  />
                </div>

                <div class="field-help-text">
                  Press <strong>Enter</strong> or <strong>comma (,)</strong> to create tag pills (e.g. <code>Midnight Black</code>, <code>Glacier Silver</code>).
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Option Button -->
        <div class="add-attribute-bar">
          <button
            type="button"
            class="btn btn-outline btn-add-attr"
            @click="addAttributeRow()"
          >
            <span>➕</span> Add Another Attribute Option
          </button>
          <span class="formula-badge font-mono">{{ formulaText }}</span>
        </div>

        <!-- Default Pricing & Physical Dimensions for Matrix -->
        <div class="generator-defaults-box">
          <div class="defaults-title-row">
            <h3 class="defaults-title">Default Variant Financials & Logistics</h3>
            <span class="defaults-desc">These defaults will be applied to all generated SKU variants (can be customized later per variant).</span>
          </div>

          <div class="defaults-grid">
            <div class="form-group">
              <label class="form-label">
                Default Selling Price (BDT ৳) <span class="required">*</span>
              </label>
              <div class="currency-input-wrap">
                <span class="currency-symbol">৳</span>
                <input
                  v-model.number="generateForm.selling_price_bdt"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-control with-symbol"
                  required
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Default Purchase Cost / COGS (BDT ৳)</label>
              <div class="currency-input-wrap">
                <span class="currency-symbol">৳</span>
                <input
                  v-model.number="generateForm.purchase_cost_bdt"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-control with-symbol"
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Unit Weight (kg)</label>
              <div class="unit-input-wrap">
                <input
                  v-model.number="generateForm.weight_kg"
                  type="number"
                  min="0"
                  step="0.001"
                  class="form-control"
                />
                <span class="unit-suffix">kg</span>
              </div>
            </div>

            <div class="margin-preview-box">
              <span class="margin-label">Projected Margin</span>
              <span class="margin-val text-emerald">
                +৳{{ formatCurrency(projectedMarginBdt) }}
                <small class="margin-pct">({{ projectedMarginPct }}%)</small>
              </span>
            </div>
          </div>
        </div>

        <!-- Interactive Combinations Preview Accordion -->
        <div v-if="totalCombinations > 0" class="preview-section">
          <div class="preview-toggle-bar">
            <button
              type="button"
              class="btn-toggle-preview"
              @click="showPreview = !showPreview"
            >
              <span>{{ showPreview ? '▼ Hide Combinations Preview' : '▶ Show Combinations Preview' }} ({{ totalCombinations }})</span>
            </button>
            <span class="preview-hint">Previewing exact SKU codes to be generated</span>
          </div>

          <div v-if="showPreview" class="preview-table-wrap">
            <table class="preview-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Generated SKU</th>
                  <th>Attribute Combination</th>
                  <th>Selling Price</th>
                  <th>Weight</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(comb, cIdx) in previewCombinations" :key="cIdx">
                  <td class="text-muted">{{ cIdx + 1 }}</td>
                  <td class="font-mono font-bold text-accent">{{ comb.sku }}</td>
                  <td>
                    <div class="preview-chips">
                      <span
                        v-for="(val, k) in comb.attributes"
                        :key="k"
                        class="preview-chip"
                      >
                        <strong>{{ k }}:</strong> {{ val }}
                      </span>
                    </div>
                  </td>
                  <td>৳{{ formatCurrency(generateForm.selling_price_bdt) }}</td>
                  <td>{{ generateForm.weight_kg }} kg</td>
                </tr>
              </tbody>
            </table>
            <div v-if="totalCombinations > 50" class="preview-more-notice">
              Showing first 50 combinations of {{ totalCombinations }} total.
            </div>
          </div>
        </div>

        <!-- Primary Generation CTA Bar -->
        <div class="generator-cta-bar">
          <div class="cta-notice">
            <span>💡 <strong>Existing Variants Safe</strong>: Running generator will create new combinations or update existing matching SKUs without data loss.</span>
          </div>

          <button
            type="button"
            class="btn btn-primary btn-generate-cta"
            :disabled="generateForm.processing || totalCombinations === 0"
            @click="generateVariants"
          >
            <span v-if="generateForm.processing" class="spinner">⏳</span>
            <span v-else>⚡ Generate &amp; Save {{ totalCombinations }} Matrix Variants</span>
          </button>
        </div>
      </div>

      <!-- ACTIVE SKU VARIANTS TABLE -->
      <div class="card table-card">
        <div class="card-inner-header">
          <div class="header-left">
            <h2 class="card-title">📦 Active SKU Variants ({{ product.variants.length }})</h2>
            <p class="card-subtitle">
              Currently active barcode/SKU units stocked and tracked across warehouses.
            </p>
          </div>

          <div class="header-right">
            <!-- Variant search input -->
            <div class="variant-search-wrap">
              <span class="search-icon">🔍</span>
              <input
                v-model="variantSearch"
                type="text"
                class="variant-search-input"
                placeholder="Filter variants by SKU or attribute..."
              />
              <button
                v-if="variantSearch"
                type="button"
                class="clear-btn"
                @click="variantSearch = ''"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU &amp; Barcode</th>
                <th>Attributes</th>
                <th>Cost Price</th>
                <th>Selling Price</th>
                <th>Gross Margin</th>
                <th>Weight</th>
                <th>Physical Stock</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="variant in filteredVariants" :key="variant.id">
                <td>
                  <div class="sku-cell">
                    <span class="sku-code font-mono">{{ variant.sku }}</span>
                    <span v-if="variant.barcode_ean" class="barcode-text font-mono">
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
                      <span class="attr-key">{{ key }}:</span>
                      <strong class="attr-val">{{ val }}</strong>
                    </span>
                    <span v-if="!variant.attributes || Object.keys(variant.attributes).length === 0" class="no-attrs">
                      Base Single Variant
                    </span>
                  </div>
                </td>
                <td>
                  <span class="cost-text">৳{{ formatCurrency(variant.purchase_cost_bdt) }}</span>
                </td>
                <td>
                  <span class="price-text">৳{{ formatCurrency(variant.selling_price_bdt) }}</span>
                </td>
                <td>
                  <div class="margin-cell">
                    <span class="margin-badge">
                      +৳{{ formatCurrency(variant.selling_price_bdt - variant.purchase_cost_bdt) }}
                    </span>
                    <small class="margin-pct-label">
                      {{ variant.selling_price_bdt > 0 ? Math.round(((variant.selling_price_bdt - variant.purchase_cost_bdt) / variant.selling_price_bdt) * 100) : 0 }}%
                    </small>
                  </div>
                </td>
                <td>
                  <span class="weight-text">{{ variant.weight_kg }} kg</span>
                </td>
                <td>
                  <span
                    class="stock-pill"
                    :class="(variant.stock_quantity || 0) > 0 ? 'stock-healthy' : 'stock-out'"
                  >
                    <span v-if="(variant.stock_quantity || 0) > 0">✅ {{ variant.stock_quantity }} in stock</span>
                    <span v-else>⚠️ 0 units</span>
                  </span>
                </td>
                <td>
                  <span v-if="variant.is_active" class="badge badge-success">Active</span>
                  <span v-else class="badge badge-warning">Inactive</span>
                </td>
                <td class="text-right">
                  <button
                    type="button"
                    class="btn-delete-variant"
                    title="Delete this variant"
                    @click="deleteVariant(variant)"
                  >
                    🗑️
                  </button>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="filteredVariants.length === 0">
                <td colspan="9" class="empty-variants-cell">
                  <div class="empty-box">
                    <span class="empty-icon">⚡</span>
                    <h4 class="empty-title">
                      {{ variantSearch ? 'No matching variants found' : 'No Matrix Variants Generated Yet' }}
                    </h4>
                    <p class="empty-desc">
                      {{ variantSearch
                        ? 'Try clearing your search query above.'
                        : 'Use the Multi-Attribute Matrix Generator above to define your option combinations and generate SKUs.'
                      }}
                    </p>
                    <button
                      v-if="variantSearch"
                      type="button"
                      class="btn btn-outline btn-sm"
                      @click="variantSearch = ''"
                    >
                      Clear Search
                    </button>
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
.matrix-editor-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
  max-width: 1400px;
  margin: 0 auto;
}

// TOP NAVIGATION
.top-nav-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: $spacing-3;

  .breadcrumbs {
    display: flex;
    align-items: center;
    gap: $spacing-2;
    font-size: $font-size-xs;

    .crumb-link {
      color: $color-primary-600;
      text-decoration: none;
      font-weight: 600;

      &:hover {
        text-decoration: underline;
      }
    }

    .crumb-sep {
      color: #94a3b8;
    }

    .crumb-current {
      color: $color-text-main;
      font-weight: 600;
    }

    .crumb-badge {
      background: hsl(342, 95%, 97%);
      color: $color-primary-700;
      border: 1px solid hsl(342, 70%, 88%);
      font-weight: 700;
      padding: 1px 8px;
      border-radius: $radius-full;
    }
  }

  .top-actions {
    display: flex;
    align-items: center;
    gap: $spacing-2;
    flex-wrap: wrap;
  }
}

// PRODUCT SUMMARY HERO CARD
.product-summary-card {
  background: #ffffff;
  border: 1px solid hsl(342, 40%, 92%);
  border-radius: $radius-xl;
  padding: $spacing-5;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: $spacing-4;
  box-shadow: 0 4px 12px rgba(235, 40, 108, 0.04), 0 1px 3px rgba(15, 23, 42, 0.04);

  .product-hero-identity {
    display: flex;
    align-items: center;
    gap: $spacing-4;

    .product-hero-thumb {
      width: 56px;
      height: 56px;
      border-radius: $radius-lg;
      border: 1px solid hsl(342, 30%, 88%);
      overflow: hidden;
      background: hsl(342, 50%, 98%);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;

      .thumb-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .thumb-icon {
        font-size: 1.8rem;
      }
    }

    .product-hero-meta {
      .hero-badges-row {
        display: flex;
        gap: 6px;
        margin-bottom: 4px;

        .badge-category {
          background: hsl(342, 95%, 97%);
          color: $color-primary-700;
          border: 1px solid hsl(342, 70%, 88%);
          font-size: 11px;
          font-weight: 700;
        }

        .badge-brand {
          background: #f1f5f9;
          color: #334155;
          border: 1px solid #e2e8f0;
          font-size: 11px;
          font-weight: 600;
        }
      }

      .hero-product-title {
        font-size: $font-size-xl;
        font-weight: 800;
        color: hsl(342, 45%, 15%);
        margin: 0;
        font-family: $font-heading;
      }

      .hero-subtitle {
        font-size: $font-size-xs;
        color: $color-text-muted;
        margin: 2px 0 0;
      }
    }
  }

  .product-hero-kpis {
    display: flex;
    gap: $spacing-3;
    flex-wrap: wrap;

    .kpi-pill {
      background: hsl(342, 40%, 99%);
      border: 1px solid hsl(342, 30%, 90%);
      border-radius: $radius-lg;
      padding: 8px 16px;
      display: flex;
      flex-direction: column;

      .kpi-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: $color-text-muted;
      }

      .kpi-value {
        font-size: $font-size-lg;
        font-weight: 800;
        color: $color-text-main;
        margin-top: 2px;

        &.text-accent {
          color: $color-primary-600;
        }

        &.text-emerald {
          color: #059669;
        }

        &.text-rose {
          color: #e11d48;
        }
      }
    }
  }
}

// MULTI-ATTRIBUTE MATRIX GENERATOR
.generator-card {
  background: #ffffff;
  border: 1px solid hsl(342, 40%, 90%);
  border-radius: $radius-xl;
  padding: $spacing-6;
  box-shadow: 0 4px 16px rgba(235, 40, 108, 0.05);
  display: flex;
  flex-direction: column;
  gap: $spacing-5;

  .generator-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: $spacing-4;
    padding-bottom: $spacing-4;
    border-bottom: 1px solid hsl(342, 35%, 92%);

    .header-titles {
      .title-with-badge {
        display: flex;
        align-items: center;
        gap: $spacing-3;
        flex-wrap: wrap;

        .card-title {
          font-size: $font-size-xl;
          font-weight: 800;
          color: hsl(342, 45%, 15%);
          margin: 0;
          font-family: $font-heading;
        }

        .badge-generator-ready {
          background: linear-gradient(135deg, hsl(342, 95%, 97%) 0%, hsl(342, 85%, 93%) 100%);
          color: $color-primary-700;
          border: 1px solid hsl(342, 70%, 85%);
          font-size: 11px;
          font-weight: 700;
          padding: 3px 10px;
          border-radius: $radius-full;
        }
      }

      .card-subtitle {
        font-size: $font-size-xs;
        color: $color-text-muted;
        margin: $spacing-1 0 0;
        max-width: 720px;
        line-height: 1.5;
      }
    }

    .combination-summary-pill {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: $radius-xl;
      padding: 8px 18px;
      display: flex;
      align-items: center;
      transition: all $transition-fast;

      &.is-active {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-color: #a7f3d0;

        .counter-number {
          color: #047857;
        }
      }

      .counter-box {
        display: flex;
        flex-direction: column;
        align-items: center;

        .counter-number {
          font-size: 26px;
          font-weight: 800;
          color: #64748b;
          line-height: 1;
        }

        .counter-label {
          font-size: 11px;
          text-transform: uppercase;
          font-weight: 700;
          color: $color-text-muted;
          margin-top: 2px;
          letter-spacing: 0.05em;
        }
      }
    }
  }

  // PRESETS TOOLBAR
  .presets-toolbar {
    display: flex;
    align-items: center;
    gap: $spacing-2;
    flex-wrap: wrap;
    background: hsl(342, 45%, 98.5%);
    border: 1px dashed hsl(342, 40%, 88%);
    border-radius: $radius-lg;
    padding: $spacing-2 $spacing-3;

    .presets-label {
      font-size: $font-size-xs;
      font-weight: 700;
      color: hsl(342, 40%, 35%);
      margin-right: 4px;
    }

    .preset-buttons-group {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;

      .btn-preset {
        background: #ffffff;
        border: 1px solid hsl(342, 35%, 85%);
        color: hsl(342, 45%, 25%);
        padding: 4px 10px;
        border-radius: $radius-md;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all $transition-fast;

        &:hover {
          background: hsl(342, 95%, 97%);
          border-color: $color-primary-500;
          color: $color-primary-700;
          transform: translateY(-1px);
        }
      }
    }
  }

  // ATTRIBUTES STACK
  .attributes-stack {
    display: flex;
    flex-direction: column;
    gap: $spacing-3;

    .attribute-row-card {
      background: hsl(342, 45%, 99.5%);
      border: 1px solid hsl(342, 35%, 90%);
      border-radius: $radius-lg;
      padding: $spacing-4;
      display: flex;
      flex-direction: column;
      gap: $spacing-3;
      transition: all $transition-fast;

      &:hover {
        border-color: hsl(342, 50%, 80%);
        box-shadow: 0 2px 8px rgba(235, 40, 108, 0.04);
      }

      .attr-row-header {
        display: flex;
        justify-content: space-between;
        align-items: center;

        .attr-index-badge {
          font-size: 11px;
          font-weight: 800;
          color: hsl(342, 40%, 40%);
          text-transform: uppercase;
          letter-spacing: 0.05em;
        }

        .btn-remove-attr {
          background: none;
          border: none;
          color: #ef4444;
          font-size: $font-size-xs;
          font-weight: 600;
          cursor: pointer;

          &:hover {
            text-decoration: underline;
          }
        }
      }

      .attr-row-body {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: $spacing-4;
        align-items: flex-start;

        @media (max-width: 900px) {
          grid-template-columns: 1fr;
        }

        .attr-name-field {
          display: flex;
          flex-direction: column;
          gap: 6px;

          .field-label {
            font-size: $font-size-xs;
            font-weight: 700;
            color: $color-text-main;

            .required {
              color: #ef4444;
            }
          }

          .field-input {
            padding: 8px 12px;
            border-radius: $radius-md;
            border: 1px solid hsl(342, 30%, 85%);
            background: #ffffff;
            font-size: $font-size-sm;
            outline: none;
            transition: all $transition-fast;

            &:focus {
              border-color: $color-primary-500;
              box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
            }
          }

          .name-suggestions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 2px;

            .sugg-hint {
              font-size: 10px;
              color: $color-text-muted;
            }

            .sugg-chip {
              background: #f1f5f9;
              border: 1px solid #e2e8f0;
              border-radius: $radius-sm;
              padding: 1px 5px;
              font-size: 10px;
              font-weight: 600;
              color: #475569;
              cursor: pointer;

              &:hover {
                background: hsl(342, 95%, 97%);
                color: $color-primary-700;
                border-color: hsl(342, 70%, 85%);
              }
            }
          }
        }

        .attr-values-field {
          display: flex;
          flex-direction: column;
          gap: 6px;

          .label-with-count {
            display: flex;
            justify-content: space-between;
            align-items: center;

            .field-label {
              font-size: $font-size-xs;
              font-weight: 700;
              color: $color-text-main;
            }

            .values-count-badge {
              font-size: 11px;
              font-weight: 700;
              color: $color-primary-700;
              background: hsl(342, 95%, 97%);
              padding: 1px 7px;
              border-radius: $radius-full;
            }
          }

          .tags-input-container {
            min-height: 44px;
            background: #ffffff;
            border: 1px solid hsl(342, 30%, 85%);
            border-radius: $radius-md;
            padding: 6px 10px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            cursor: text;
            transition: all $transition-fast;

            &:focus-within {
              border-color: $color-primary-500;
              box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
            }

            .tag-pill {
              background: hsl(342, 95%, 97%);
              border: 1px solid hsl(342, 70%, 85%);
              color: $color-primary-800;
              padding: 3px 8px;
              border-radius: $radius-md;
              font-size: 12px;
              font-weight: 700;
              display: inline-flex;
              align-items: center;
              gap: 6px;

              .tag-close-btn {
                background: none;
                border: none;
                color: $color-primary-600;
                font-size: 11px;
                font-weight: 800;
                cursor: pointer;
                padding: 0;
                line-height: 1;

                &:hover {
                  color: #ef4444;
                }
              }
            }

            .tag-inline-input {
              flex: 1;
              min-width: 180px;
              border: none;
              outline: none;
              font-size: $font-size-sm;
              color: $color-text-main;
              padding: 2px 4px;
              background: transparent;

              &::placeholder {
                color: #94a3b8;
                font-size: 12px;
              }
            }
          }

          .field-help-text {
            font-size: 11px;
            color: $color-text-muted;

            code {
              background: #f1f5f9;
              padding: 1px 4px;
              border-radius: 3px;
              font-family: monospace;
            }
          }
        }
      }
    }
  }

  .add-attribute-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: $spacing-3;

    .btn-add-attr {
      font-weight: 700;
      font-size: $font-size-xs;
      border-color: hsl(342, 35%, 85%);
      color: $color-primary-700;

      &:hover {
        background: hsl(342, 95%, 97%);
      }
    }

    .formula-badge {
      font-size: $font-size-xs;
      color: #475569;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      padding: 4px 10px;
      border-radius: $radius-md;
    }
  }

  // GENERATOR DEFAULTS BOX
  .generator-defaults-box {
    background: hsl(342, 45%, 99%);
    border: 1px solid hsl(342, 35%, 92%);
    border-radius: $radius-lg;
    padding: $spacing-4;

    .defaults-title-row {
      margin-bottom: $spacing-3;

      .defaults-title {
        font-size: $font-size-sm;
        font-weight: 700;
        color: hsl(342, 45%, 15%);
        margin: 0;
      }

      .defaults-desc {
        font-size: 11px;
        color: $color-text-muted;
      }
    }

    .defaults-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: $spacing-4;
      align-items: flex-end;

      .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;

        .form-label {
          font-size: 12px;
          font-weight: 700;
          color: $color-text-main;

          .required {
            color: #ef4444;
          }
        }

        .currency-input-wrap,
        .unit-input-wrap {
          position: relative;
          display: flex;
          align-items: center;

          .currency-symbol {
            position: absolute;
            left: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            pointer-events: none;
          }

          .unit-suffix {
            position: absolute;
            right: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            pointer-events: none;
          }

          .form-control {
            width: 100%;
            padding: 8px 12px;
            border-radius: $radius-md;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            font-size: $font-size-sm;
            outline: none;
            transition: all $transition-fast;

            &.with-symbol {
              padding-left: 26px;
            }

            &:focus {
              border-color: $color-primary-500;
              box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
            }
          }
        }
      }

      .margin-preview-box {
        background: #ffffff;
        border: 1px solid #bbf7d0;
        border-radius: $radius-md;
        padding: 8px 12px;
        display: flex;
        flex-direction: column;

        .margin-label {
          font-size: 10px;
          text-transform: uppercase;
          font-weight: 700;
          color: #166534;
          letter-spacing: 0.05em;
        }

        .margin-val {
          font-size: $font-size-base;
          font-weight: 800;

          .margin-pct {
            font-size: 11px;
            font-weight: 600;
          }
        }
      }
    }
  }

  // PREVIEW SECTION
  .preview-section {
    border: 1px solid #e2e8f0;
    border-radius: $radius-lg;
    overflow: hidden;

    .preview-toggle-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 14px;
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;

      .btn-toggle-preview {
        background: none;
        border: none;
        font-size: 12px;
        font-weight: 700;
        color: $color-primary-700;
        cursor: pointer;

        &:hover {
          text-decoration: underline;
        }
      }

      .preview-hint {
        font-size: 11px;
        color: $color-text-muted;
      }
    }

    .preview-table-wrap {
      max-height: 280px;
      overflow-y: auto;

      .preview-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;

        th {
          background: #ffffff;
          padding: 8px 12px;
          color: #64748b;
          font-weight: 700;
          border-bottom: 1px solid #e2e8f0;
          position: sticky;
          top: 0;
          z-index: 5;
        }

        td {
          padding: 8px 12px;
          border-bottom: 1px solid #f1f5f9;
        }

        .preview-chips {
          display: flex;
          gap: 4px;
          flex-wrap: wrap;

          .preview-chip {
            background: #f1f5f9;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 11px;
          }
        }
      }

      .preview-more-notice {
        padding: 8px;
        text-align: center;
        background: #f8fafc;
        font-size: 11px;
        color: $color-text-muted;
      }
    }
  }

  // CTA ACTION BAR
  .generator-cta-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: $spacing-4;
    padding-top: $spacing-4;
    border-top: 1px solid hsl(342, 35%, 92%);

    .cta-notice {
      font-size: $font-size-xs;
      color: #64748b;
      max-width: 500px;
    }

    .btn-generate-cta {
      padding: 12px 28px;
      font-size: $font-size-sm;
      font-weight: 800;
      background: linear-gradient(135deg, $color-primary-500 0%, $color-primary-600 100%);
      box-shadow: 0 4px 12px rgba(235, 40, 108, 0.25);
      border: none;
      transition: all $transition-fast;

      &:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(235, 40, 108, 0.35);
      }

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
    }
  }
}

// ACTIVE VARIANTS TABLE CARD
.table-card {
  padding: 0;
  overflow: hidden;
  border: 1px solid hsl(342, 40%, 92%);
  box-shadow: 0 4px 12px rgba(235, 40, 108, 0.04), 0 1px 3px rgba(15, 23, 42, 0.04);
  background-color: #ffffff;
  border-radius: $radius-xl;

  .card-inner-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: $spacing-4 $spacing-6;
    border-bottom: 1px solid hsl(342, 35%, 92%);
    background: hsl(342, 50%, 99.5%);
    flex-wrap: wrap;
    gap: $spacing-3;

    .header-left {
      .card-title {
        font-size: $font-size-base;
        font-weight: 800;
        color: hsl(342, 45%, 15%);
        margin: 0;
        font-family: $font-heading;
      }

      .card-subtitle {
        font-size: $font-size-xs;
        color: $color-text-muted;
        margin-top: 2px;
      }
    }

    .header-right {
      .variant-search-wrap {
        position: relative;
        min-width: 260px;

        .search-icon {
          position: absolute;
          left: 10px;
          top: 50%;
          transform: translateY(-50%);
          font-size: 12px;
          color: #94a3b8;
          pointer-events: none;
        }

        .variant-search-input {
          width: 100%;
          padding: 6px 28px 6px 30px;
          font-size: $font-size-xs;
          border: 1px solid #cbd5e1;
          border-radius: $radius-md;
          outline: none;

          &:focus {
            border-color: $color-primary-500;
          }
        }

        .clear-btn {
          position: absolute;
          right: 8px;
          top: 50%;
          transform: translateY(-50%);
          background: none;
          border: none;
          color: #94a3b8;
          cursor: pointer;
          font-size: 11px;
        }
      }
    }
  }

  .data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;

    th {
      background: hsl(342, 50%, 98.5%);
      padding: $spacing-3 $spacing-4;
      font-size: $font-size-xs;
      font-weight: 700;
      color: hsl(342, 35%, 35%);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 1px solid hsl(342, 35%, 90%);
    }

    td {
      padding: $spacing-3 $spacing-4;
      border-bottom: 1px solid hsl(342, 30%, 93%);
      font-size: $font-size-sm;
      vertical-align: middle;
    }

    tr:hover td {
      background-color: hsl(342, 60%, 99.2%);
    }

    tr:last-child td {
      border-bottom: none;
    }

    .sku-cell {
      display: flex;
      flex-direction: column;
      gap: 2px;

      .sku-code {
        font-weight: 800;
        color: $color-text-main;
        font-size: 13px;
      }

      .barcode-text {
        font-size: 11px;
        color: #64748b;
      }
    }

    .attrs-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;

      .attr-chip {
        background: hsl(342, 95%, 97%);
        border: 1px solid hsl(342, 70%, 88%);
        border-radius: $radius-sm;
        padding: 2px 7px;
        font-size: 11px;
        color: $color-primary-800;

        .attr-key {
          color: #64748b;
          margin-right: 3px;
        }

        .attr-val {
          font-weight: 700;
        }
      }

      .no-attrs {
        font-size: 11px;
        color: $color-text-muted;
        font-style: italic;
      }
    }

    .cost-text {
      font-size: 13px;
      color: #64748b;
    }

    .price-text {
      font-size: 13px;
      font-weight: 800;
      color: $color-primary-700;
    }

    .margin-cell {
      display: flex;
      align-items: center;
      gap: 4px;

      .margin-badge {
        font-size: 11px;
        font-weight: 700;
        color: #047857;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 2px 6px;
        border-radius: $radius-sm;
      }

      .margin-pct-label {
        font-size: 10px;
        color: #059669;
        font-weight: 700;
      }
    }

    .weight-text {
      font-size: 12px;
      color: #64748b;
    }

    .stock-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 8px;
      border-radius: $radius-full;
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;

      &.stock-healthy {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
      }

      &.stock-out {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
      }
    }

    .btn-delete-variant {
      background: none;
      border: 1px solid #fecaca;
      border-radius: $radius-md;
      padding: 4px 8px;
      color: #ef4444;
      cursor: pointer;
      font-size: 12px;
      transition: all $transition-fast;

      &:hover {
        background: #fef2f2;
        border-color: #ef4444;
      }
    }

    .empty-variants-cell {
      text-align: center;
      padding: $spacing-10 $spacing-4;

      .empty-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: $spacing-2;
        max-width: 380px;
        margin: 0 auto;

        .empty-icon {
          font-size: 2.2rem;
          opacity: 0.6;
        }

        .empty-title {
          font-size: $font-size-base;
          font-weight: 700;
          color: $color-text-main;
          margin: 0;
        }

        .empty-desc {
          font-size: $font-size-xs;
          color: $color-text-muted;
          margin: 0 0 $spacing-2;
          line-height: 1.5;
        }
      }
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
