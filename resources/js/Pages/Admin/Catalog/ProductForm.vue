<script setup lang="ts">
import { ref, computed, watch } from 'vue';
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
  slug?: string;
  category_id: number;
  brand_id: number | null;
  hs_code: string | null;
  origin_country: string;
  warranty_policy: string | null;
  description: string | null;
  specifications: Record<string, string>;
  is_published: boolean;
  is_featured: boolean;
  seo_title?: string | null;
  seo_description?: string | null;
  image_url?: string | null;
  default_selling_price?: number;
  default_purchase_cost?: number;
  compare_at_price?: number;
  sku?: string;
  barcode_ean?: string;
  weight_kg?: number;
  stock_quantity?: number;
}

const props = defineProps<{
  product?: ProductData | null;
  categories: CategoryOption[];
  brands: BrandOption[];
  countries: string[];
}>();

const isEditing = !!props.product;

// Slug auto-generation state
const isSlugManual = ref(!!props.product?.slug);

// Initial specifications rows
const initialSpecs = props.product?.specifications && Object.keys(props.product.specifications).length > 0
  ? Object.entries(props.product.specifications).map(([k, v]) => ({ key: k, value: v }))
  : [
      { key: 'Brand Model', value: '' },
      { key: 'Warranty Period', value: '1 Year Brand Warranty' },
    ];

const specRows = ref(initialSpecs);

// Image preview state
const imagePreview = ref<string>(props.product?.image_url || '');
const imageInputMode = ref<'url' | 'upload'>('url');
const imageFileInput = ref<HTMLInputElement | null>(null);

const form = useForm({
  name: props.product ? props.product.name : '',
  slug: props.product ? (props.product.slug || '') : '',
  category_id: props.product ? props.product.category_id : (props.categories[0]?.id || ''),
  brand_id: props.product ? props.product.brand_id : '',
  hs_code: props.product ? props.product.hs_code : '8517.62.90',
  origin_country: props.product ? props.product.origin_country : 'Bangladesh',
  warranty_policy: props.product ? props.product.warranty_policy : '1 Year Official Brand Warranty',
  description: props.product ? props.product.description : '',
  default_selling_price: props.product?.default_selling_price ?? 1250,
  default_purchase_cost: props.product?.default_purchase_cost ?? 900,
  compare_at_price: props.product?.compare_at_price ?? 1450,
  sku: props.product?.sku ?? '',
  barcode_ean: props.product?.barcode_ean ?? '',
  weight_kg: props.product?.weight_kg ?? 0.35,
  stock_quantity: props.product?.stock_quantity ?? 50,
  specifications: {} as Record<string, string>,
  is_published: props.product ? props.product.is_published : true,
  is_featured: props.product ? props.product.is_featured : false,
  seo_title: props.product?.seo_title ?? '',
  seo_description: props.product?.seo_description ?? '',
  image_url: props.product?.image_url ?? '',
  image: null as File | null,
});

// Auto-generate slug when name changes (unless manually edited)
watch(
  () => form.name,
  (newName) => {
    if (!isSlugManual.value && !isEditing) {
      form.slug = newName
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
    }
    if (!form.sku && !isEditing) {
      generateSku();
    }
  }
);

// Auto-generate SKU
const generateSku = () => {
  if (!form.name) return;
  const prefix = form.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 3)
    .map((word) => word.substring(0, 3).toUpperCase())
    .join('-');
  const randomSuffix = Math.floor(1000 + Math.random() * 9000);
  form.sku = `${prefix}-${randomSuffix}`;
};

// Profit Margin & Markup calculations
const profitMargin = computed(() => {
  const selling = Number(form.default_selling_price) || 0;
  const cost = Number(form.default_purchase_cost) || 0;
  if (selling <= 0) return 0;
  const profit = selling - cost;
  return Math.round((profit / selling) * 100 * 10) / 10;
});

const grossProfit = computed(() => {
  const selling = Number(form.default_selling_price) || 0;
  const cost = Number(form.default_purchase_cost) || 0;
  return selling - cost;
});

const markupPercent = computed(() => {
  const selling = Number(form.default_selling_price) || 0;
  const cost = Number(form.default_purchase_cost) || 0;
  if (cost <= 0) return 0;
  return Math.round(((selling - cost) / cost) * 100 * 10) / 10;
});

// Specification presets
const specPresets = [
  'Battery Capacity',
  'Display / Screen',
  'Processor / Chip',
  'Connectivity',
  'Weight & Dimensions',
  'Package Contents',
];

const addSpecPreset = (keyName: string) => {
  const existing = specRows.value.find((r) => r.key.toLowerCase() === keyName.toLowerCase());
  if (!existing) {
    specRows.value.push({ key: keyName, value: '' });
  }
};

const addSpecRow = () => {
  specRows.value.push({ key: '', value: '' });
};

const removeSpecRow = (index: number) => {
  specRows.value.splice(index, 1);
};

// Image handling
const handleFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    const file = target.files[0];
    form.image = file;
    const reader = new FileReader();
    reader.onload = (event) => {
      imagePreview.value = (event.target?.result as string) || '';
    };
    reader.readAsDataURL(file);
  }
};

const updateImageUrl = () => {
  if (form.image_url) {
    imagePreview.value = form.image_url;
  }
};

const clearImage = () => {
  imagePreview.value = '';
  form.image_url = '';
  form.image = null;
  if (imageFileInput.value) {
    imageFileInput.value.value = '';
  }
};

// Submit handler
const submit = (overridePublished?: boolean) => {
  if (overridePublished !== undefined) {
    form.is_published = overridePublished;
  }

  // Convert specRows to clean key-value dictionary
  const specsMap: Record<string, string> = {};
  specRows.value.forEach((row) => {
    if (row.key.trim() !== '') {
      specsMap[row.key.trim()] = row.value.trim();
    }
  });
  form.specifications = specsMap;

  if (isEditing && props.product) {
    if (form.image) {
      form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/products/${props.product.id}`, {
        forceFormData: true,
      });
    } else {
      form.put(`/admin/products/${props.product.id}`);
    }
  } else {
    form.post('/admin/products', {
      forceFormData: true,
    });
  }
};
</script>

<template>
  <AdminLayout :title="isEditing ? `Edit Product: ${product?.name}` : 'Add New Commercial Product'">
    <Head :title="isEditing ? `Edit Product - ${product?.name}` : 'Add New Product'" />

    <div class="product-form-wrapper">
      <!-- Breadcrumb Navigation -->
      <nav class="form-breadcrumbs" aria-label="Breadcrumbs">
        <Link href="/admin/dashboard" class="crumb-link">Dashboard</Link>
        <span class="crumb-sep">/</span>
        <Link href="/admin/products" class="crumb-link">Product Catalog</Link>
        <span class="crumb-sep">/</span>
        <span class="crumb-current">{{ isEditing ? 'Edit Product' : 'Add New Product' }}</span>
      </nav>

      <!-- Page Header -->
      <header class="form-top-header">
        <div class="header-intro">
          <div class="title-with-badge">
            <h1 class="header-title">
              {{ isEditing ? `Edit Product: ${product?.name}` : 'Add New Commercial Product' }}
            </h1>
            <span class="status-pill" :class="form.is_published ? 'published' : 'draft'">
              {{ form.is_published ? '● Live Storefront' : '○ Draft' }}
            </span>
          </div>
          <p class="header-subtitle">
            Configure catalog item details, customs HS codes, multi-cloud media, landed cost, and inventory.
          </p>
        </div>

        <div class="header-action-group">
          <Link href="/admin/products" class="btn btn-secondary">
            Cancel
          </Link>
          <button
            type="button"
            class="btn btn-outline"
            :disabled="form.processing"
            @click="submit(false)"
          >
            Save Draft
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="form.processing"
            @click="submit(true)"
          >
            <span v-if="form.processing" class="spinner-sm" />
            <span>{{ isEditing ? 'Save Changes' : 'Publish Product' }}</span>
          </button>
        </div>
      </header>

      <!-- 2-Column Split Form Layout -->
      <form @submit.prevent="submit()" class="form-layout-grid">
        <!-- ========================================== -->
        <!-- LEFT COLUMN: Main Product Details (68%)   -->
        <!-- ========================================== -->
        <div class="main-column">
          <!-- Card 1: Product Identification -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">🏷️</span>
              <div>
                <h2 class="card-heading">Product Identification & Content</h2>
                <p class="card-subheading">Core title, customer-facing description, and URL permalink.</p>
              </div>
            </div>

            <div class="card-body">
              <!-- Product Title -->
              <div class="form-field">
                <label class="field-label" for="prod-name">
                  Product Name <span class="required-mark">*</span>
                </label>
                <input
                  id="prod-name"
                  v-model="form.name"
                  type="text"
                  class="field-input field-input-lg"
                  :class="{ 'has-error': form.errors.name }"
                  placeholder="e.g. Apple iPhone 15 Pro Max 256GB - Natural Titanium"
                  required
                  autofocus
                />
                <span v-if="form.errors.name" class="field-error-msg">{{ form.errors.name }}</span>
              </div>

              <!-- URL Slug Field -->
              <div class="form-field">
                <div class="slug-header-row">
                  <label class="field-label" for="prod-slug">
                    Storefront URL Slug
                  </label>
                  <button
                    type="button"
                    class="slug-toggle-btn"
                    @click="isSlugManual = !isSlugManual"
                  >
                    {{ isSlugManual ? '🔒 Lock Auto Slug' : '✏️ Edit Slug Manually' }}
                  </button>
                </div>
                <div class="input-prefix-wrap">
                  <span class="input-prefix">/products/</span>
                  <input
                    id="prod-slug"
                    v-model="form.slug"
                    type="text"
                    class="field-input prefix-input"
                    :readonly="!isSlugManual && !isEditing"
                    placeholder="apple-iphone-15-pro-max"
                  />
                </div>
                <span v-if="form.errors.slug" class="field-error-msg">{{ form.errors.slug }}</span>
              </div>

              <!-- Product Description -->
              <div class="form-field">
                <div class="description-header-row">
                  <label class="field-label" for="prod-desc">
                    Product Description & Key Highlights
                  </label>
                  <span class="char-counter">{{ (form.description || '').length }} characters</span>
                </div>
                <div class="textarea-toolbar">
                  <button type="button" class="toolbar-btn" title="Bold" @click="form.description += ' **bold text**'"><b>B</b></button>
                  <button type="button" class="toolbar-btn" title="Bullet List" @click="form.description += '\n• Feature item'">• List</button>
                  <button type="button" class="toolbar-btn" title="Warranty Note" @click="form.description += '\n\n**Warranty & Delivery:** 100% Genuine product with official warranty.'">🛡️ Warranty</button>
                </div>
                <textarea
                  id="prod-desc"
                  v-model="form.description"
                  rows="6"
                  class="field-textarea"
                  placeholder="Provide detailed technical specifications, box contents, genuine import origin details, and usage recommendations for Bangladeshi shoppers..."
                />
                <span v-if="form.errors.description" class="field-error-msg">{{ form.errors.description }}</span>
              </div>
            </div>
          </section>

          <!-- Card 2: Media & Image Gallery -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">🖼️</span>
              <div>
                <h2 class="card-heading">Media & Photography Assets</h2>
                <p class="card-subheading">High-definition product photography. Automatically converted to WebP & AVIF.</p>
              </div>
            </div>

            <div class="card-body">
              <!-- Upload Mode Selector -->
              <div class="media-tabs">
                <button
                  type="button"
                  class="media-tab"
                  :class="{ active: imageInputMode === 'url' }"
                  @click="imageInputMode = 'url'"
                >
                  🔗 Image URL (Fast Link)
                </button>
                <button
                  type="button"
                  class="media-tab"
                  :class="{ active: imageInputMode === 'upload' }"
                  @click="imageInputMode = 'upload'"
                >
                  📁 File Upload
                </button>
              </div>

              <!-- Mode A: Direct URL Input -->
              <div v-if="imageInputMode === 'url'" class="form-field">
                <div class="url-input-row">
                  <input
                    v-model="form.image_url"
                    type="url"
                    class="field-input"
                    placeholder="https://images.unsplash.com/... or supplier image URL"
                    @blur="updateImageUrl"
                  />
                  <button type="button" class="btn btn-secondary btn-sm" @click="updateImageUrl">
                    Preview
                  </button>
                </div>
                <p class="field-hint">Supports external image links from Unsplash, Cloudinary, AWS S3, or manufacturer CDN.</p>
              </div>

              <!-- Mode B: File Upload -->
              <div v-else class="form-field">
                <div class="file-dropzone" @click="imageFileInput?.click()">
                  <input
                    ref="imageFileInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/avif"
                    class="hidden-file-input"
                    @change="handleFileChange"
                  />
                  <span class="dropzone-icon">☁️</span>
                  <p class="dropzone-text">Click to browse or drag and drop image file</p>
                  <p class="dropzone-sub">PNG, JPG, WebP, or AVIF (Up to 10MB)</p>
                </div>
              </div>

              <!-- Live Image Preview Banner -->
              <div v-if="imagePreview" class="image-preview-card">
                <div class="preview-img-box">
                  <img :src="imagePreview" alt="Product preview" class="preview-img" />
                  <span class="preview-badge">Primary Storefront Thumbnail</span>
                </div>
                <div class="preview-meta">
                  <div class="meta-row">
                    <span class="meta-label">Format:</span>
                    <span class="meta-val">WebP / AVIF Ready</span>
                  </div>
                  <div class="meta-row">
                    <span class="meta-label">Breakpoints:</span>
                    <span class="meta-val">thumb_300, card_600, gallery_1200, zoom_2000</span>
                  </div>
                  <button type="button" class="btn-remove-img" @click="clearImage">
                    🗑️ Remove Image
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Card 3: SKU, Inventory & Logistics Identification -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">📦</span>
              <div>
                <h2 class="card-heading">SKU, Inventory & Logistics Identification</h2>
                <p class="card-subheading">Track barcodes, initial warehouse intake, and shipping parcel weight.</p>
              </div>
            </div>

            <div class="card-body">
              <div class="grid-3-col">
                <!-- SKU Code -->
                <div class="form-field">
                  <div class="field-label-flex">
                    <label class="field-label" for="sku-code">SKU Code</label>
                    <button type="button" class="link-btn-action" @click="generateSku">⚡ Auto</button>
                  </div>
                  <input
                    id="sku-code"
                    v-model="form.sku"
                    type="text"
                    class="field-input uppercase-text"
                    placeholder="e.g. IPHONE-15PM-256"
                  />
                  <span v-if="form.errors.sku" class="field-error-msg">{{ form.errors.sku }}</span>
                </div>

                <!-- Barcode / EAN-13 -->
                <div class="form-field">
                  <label class="field-label" for="barcode-ean">Barcode / EAN-13</label>
                  <input
                    id="barcode-ean"
                    v-model="form.barcode_ean"
                    type="text"
                    class="field-input"
                    placeholder="e.g. 195949038289"
                  />
                </div>

                <!-- Initial Physical Stock Quantity -->
                <div class="form-field">
                  <label class="field-label" for="stock-qty">Initial Stock (Units)</label>
                  <input
                    id="stock-qty"
                    v-model.number="form.stock_quantity"
                    type="number"
                    min="0"
                    step="1"
                    class="field-input"
                    placeholder="50"
                  />
                </div>
              </div>

              <div class="grid-2-col">
                <!-- Package Weight for Shipping -->
                <div class="form-field">
                  <label class="field-label" for="weight-kg">
                    Package Weight (Kg)
                    <span class="field-subtext">(For Steadfast / Pathao courier charges)</span>
                  </label>
                  <div class="input-suffix-wrap">
                    <input
                      id="weight-kg"
                      v-model.number="form.weight_kg"
                      type="number"
                      min="0.01"
                      step="0.01"
                      class="field-input suffix-input"
                      placeholder="0.35"
                    />
                    <span class="input-suffix">kg</span>
                  </div>
                </div>

                <!-- Commercial Warranty Policy -->
                <div class="form-field">
                  <label class="field-label" for="prod-warranty">Commercial Warranty Policy</label>
                  <input
                    id="prod-warranty"
                    v-model="form.warranty_policy"
                    type="text"
                    class="field-input"
                    placeholder="e.g. 1 Year Official Brand Warranty / 7 Days Replacement"
                  />
                </div>
              </div>
            </div>
          </section>

          <!-- Card 4: Technical Specifications Sheet -->
          <section class="form-card">
            <div class="card-header-between">
              <div class="card-header-left">
                <span class="card-icon">📑</span>
                <div>
                  <h2 class="card-heading">Technical Specifications Sheet</h2>
                  <p class="card-subheading">Rendered in the specification table on the storefront product detail page.</p>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline" @click="addSpecRow">
                ➕ Add Row
              </button>
            </div>

            <div class="card-body">
              <!-- Quick Preset Pills -->
              <div class="preset-pills-row">
                <span class="preset-label">Quick Presets:</span>
                <button
                  v-for="preset in specPresets"
                  :key="preset"
                  type="button"
                  class="preset-pill"
                  @click="addSpecPreset(preset)"
                >
                  + {{ preset }}
                </button>
              </div>

              <!-- Specifications Rows Table -->
              <div class="specs-table-container">
                <div class="specs-table-header">
                  <span class="col-head">Feature / Spec Name</span>
                  <span class="col-head">Specification Value</span>
                  <span class="col-head action-col">Action</span>
                </div>

                <div
                  v-for="(row, idx) in specRows"
                  :key="idx"
                  class="specs-table-row"
                >
                  <input
                    v-model="row.key"
                    type="text"
                    class="field-input spec-key-input"
                    placeholder="e.g. Battery Capacity"
                  />
                  <input
                    v-model="row.value"
                    type="text"
                    class="field-input spec-val-input"
                    placeholder="e.g. 4422 mAh / 29 Hours Playback"
                  />
                  <button
                    type="button"
                    class="btn-row-delete"
                    title="Remove row"
                    @click="removeSpecRow(idx)"
                  >
                    🗑️
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Card 5: Search Engine Optimization (SEO) -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">🌐</span>
              <div>
                <h2 class="card-heading">Search Engine Optimization (SEO & Social)</h2>
                <p class="card-subheading">Control how this product appears on Google Search, Facebook, and WhatsApp.</p>
              </div>
            </div>

            <div class="card-body">
              <!-- Meta Title -->
              <div class="form-field">
                <div class="field-label-flex">
                  <label class="field-label" for="seo-title">Meta Title</label>
                  <span class="char-counter" :class="{ 'char-warning': (form.seo_title || '').length > 60 }">
                    {{ (form.seo_title || '').length }} / 60 chars
                  </span>
                </div>
                <input
                  id="seo-title"
                  v-model="form.seo_title"
                  type="text"
                  class="field-input"
                  :placeholder="form.name ? `${form.name} - Best Price in Bangladesh` : 'Product Meta Title'"
                />
              </div>

              <!-- Meta Description -->
              <div class="form-field">
                <div class="field-label-flex">
                  <label class="field-label" for="seo-desc">Meta Description</label>
                  <span class="char-counter" :class="{ 'char-warning': (form.seo_description || '').length > 160 }">
                    {{ (form.seo_description || '').length }} / 160 chars
                  </span>
                </div>
                <textarea
                  id="seo-desc"
                  v-model="form.seo_description"
                  rows="3"
                  class="field-textarea"
                  :placeholder="form.description ? form.description.substring(0, 155) : 'Buy genuine product at best price in Bangladesh with official brand warranty...'"
                />
              </div>

              <!-- Google SERP Live Snippet Box -->
              <div class="google-snippet-preview">
                <span class="snippet-tag">Google Search Result Preview</span>
                <div class="snippet-url">
                  https://tradinghub.test › products › {{ form.slug || 'product-slug' }}
                </div>
                <div class="snippet-title">
                  {{ form.seo_title || form.name || 'Your Product Title Will Appear Here' }}
                </div>
                <div class="snippet-desc">
                  {{ form.seo_description || form.description || 'Discover genuine product with fast nationwide delivery in Bangladesh. Official warranty and cash on delivery available.' }}
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- ========================================== -->
        <!-- RIGHT COLUMN: Sidebar Metadata (32%)       -->
        <!-- ========================================== -->
        <aside class="sidebar-column">
          <!-- Card 1: Pricing & Profit Margin Calculator -->
          <section class="form-card highlight-card">
            <div class="card-header">
              <span class="card-icon">💰</span>
              <div>
                <h2 class="card-heading">Pricing & Profit Margins</h2>
                <p class="card-subheading">Commercial retail pricing and automated gross margin calculation.</p>
              </div>
            </div>

            <div class="card-body">
              <!-- Retail Selling Price -->
              <div class="form-field">
                <label class="field-label" for="selling-price">
                  Retail Selling Price (BDT ৳) <span class="required-mark">*</span>
                </label>
                <div class="currency-input-wrap">
                  <span class="currency-symbol">৳</span>
                  <input
                    id="selling-price"
                    v-model.number="form.default_selling_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="field-input currency-input"
                    required
                  />
                </div>
                <span v-if="form.errors.default_selling_price" class="field-error-msg">
                  {{ form.errors.default_selling_price }}
                </span>
              </div>

              <!-- Compare-at Original Price -->
              <div class="form-field">
                <label class="field-label" for="compare-price">
                  Original / Strike-through Price (BDT ৳)
                  <span class="field-subtext">(Displays discount badge)</span>
                </label>
                <div class="currency-input-wrap">
                  <span class="currency-symbol">৳</span>
                  <input
                    id="compare-price"
                    v-model.number="form.compare_at_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="field-input currency-input"
                  />
                </div>
              </div>

              <!-- Purchase Cost / Landed Cost -->
              <div class="form-field">
                <label class="field-label" for="purchase-cost">
                  Supplier Purchase / Landed Cost (BDT ৳)
                </label>
                <div class="currency-input-wrap">
                  <span class="currency-symbol">৳</span>
                  <input
                    id="purchase-cost"
                    v-model.number="form.default_purchase_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    class="field-input currency-input"
                  />
                </div>
              </div>

              <!-- Real-Time Profit Analytics Box -->
              <div class="profit-analytics-box">
                <div class="analytics-row">
                  <span class="analytics-label">Gross Margin:</span>
                  <span
                    class="analytics-badge"
                    :class="profitMargin >= 20 ? 'badge-positive' : 'badge-neutral'"
                  >
                    {{ profitMargin }}%
                  </span>
                </div>
                <div class="analytics-row">
                  <span class="analytics-label">Profit per Unit:</span>
                  <span class="analytics-val profit-val">
                    ৳{{ grossProfit > 0 ? grossProfit.toLocaleString() : 0 }}
                  </span>
                </div>
                <div class="analytics-row">
                  <span class="analytics-label">Supplier Markup:</span>
                  <span class="analytics-val">{{ markupPercent }}%</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Card 2: Organization & Taxonomy -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">🗂️</span>
              <div>
                <h2 class="card-heading">Organization & Brand</h2>
                <p class="card-subheading">Assign to storefront categories and brand directory.</p>
              </div>
            </div>

            <div class="card-body">
              <!-- Category Select -->
              <div class="form-field">
                <label class="field-label" for="prod-cat">
                  Category <span class="required-mark">*</span>
                </label>
                <select id="prod-cat" v-model="form.category_id" class="field-select" required>
                  <option value="" disabled>Select Primary Category</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
                <span v-if="form.errors.category_id" class="field-error-msg">
                  {{ form.errors.category_id }}
                </span>
              </div>

              <!-- Brand Select -->
              <div class="form-field">
                <label class="field-label" for="prod-brand">Brand / Manufacturer</label>
                <select id="prod-brand" v-model="form.brand_id" class="field-select">
                  <option value="">Generic / No Brand</option>
                  <option v-for="b in brands" :key="b.id" :value="b.id">
                    {{ b.name }} ({{ b.origin_country }})
                  </option>
                </select>
              </div>
            </div>
          </section>

          <!-- Card 3: Sourcing, Customs & NBR Compliance -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">🏛️</span>
              <div>
                <h2 class="card-heading">Customs & Tax Compliance</h2>
                <p class="card-subheading">Bill of Entry (BOE) HS classification & NBR Mushak 6.3 standards.</p>
              </div>
            </div>

            <div class="card-body">
              <!-- Country of Origin -->
              <div class="form-field">
                <label class="field-label" for="prod-country">
                  Country of Origin <span class="required-mark">*</span>
                </label>
                <select id="prod-country" v-model="form.origin_country" class="field-select" required>
                  <option v-for="c in countries" :key="c" :value="c">
                    {{ c }}
                  </option>
                </select>
              </div>

              <!-- HS Tariff Code -->
              <div class="form-field">
                <label class="field-label" for="prod-hs">
                  Customs HS Tariff Code (8-Digit)
                </label>
                <input
                  id="prod-hs"
                  v-model="form.hs_code"
                  type="text"
                  class="field-input"
                  placeholder="e.g. 8517.62.90"
                />
                <p class="field-hint">Used for Customs BOE assessment at Chattogram port / Dhaka ICD.</p>
              </div>

              <!-- VAT Status Badge -->
              <div class="vat-compliance-pill">
                <span class="vat-icon">✅</span>
                <div class="vat-info">
                  <span class="vat-title">Standard 15% NBR VAT Rate</span>
                  <span class="vat-sub">Auto-generates Mushak 6.3 Tax Invoice upon customer order confirmation.</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Card 4: Storefront Visibility & Publishing -->
          <section class="form-card">
            <div class="card-header">
              <span class="card-icon">👁️</span>
              <div>
                <h2 class="card-heading">Storefront Visibility</h2>
                <p class="card-subheading">Control live customer publication and promotional placement.</p>
              </div>
            </div>

            <div class="card-body">
              <div class="toggle-control-box">
                <label class="switch-row" for="toggle-publish">
                  <div class="switch-info">
                    <span class="switch-title">Publish to Storefront</span>
                    <span class="switch-sub">When active, shoppers can find and purchase this item.</span>
                  </div>
                  <input
                    id="toggle-publish"
                    type="checkbox"
                    v-model="form.is_published"
                    class="custom-toggle-input"
                  />
                </label>

                <div class="divider-line" />

                <label class="switch-row" for="toggle-featured">
                  <div class="switch-info">
                    <span class="switch-title">⭐ Feature on Homepage</span>
                    <span class="switch-sub">Display in Bestsellers and New Arrivals showcase banners.</span>
                  </div>
                  <input
                    id="toggle-featured"
                    type="checkbox"
                    v-model="form.is_featured"
                    class="custom-toggle-input"
                  />
                </label>
              </div>
            </div>
          </section>
        </aside>
      </form>

      <!-- Sticky Floating Bottom Save Bar -->
      <div class="floating-save-bar">
        <div class="bar-left">
          <span class="status-indicator-dot" :class="form.is_published ? 'online' : 'offline'" />
          <span class="status-text">
            {{ form.is_published ? 'Ready to publish publicly' : 'Will be saved as unpublished draft' }}
          </span>
        </div>
        <div class="bar-right">
          <Link href="/admin/products" class="btn btn-secondary btn-sm">
            Cancel
          </Link>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="form.processing"
            @click="submit()"
          >
            <span v-if="form.processing" class="spinner-sm" />
            <span>{{ isEditing ? 'Save Product Changes' : 'Create Commercial Product' }}</span>
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.product-form-wrapper {
  display: flex;
  flex-direction: column;
  gap: $space-5;
  padding-bottom: 80px; // Spacing for floating bottom bar
}

// Breadcrumbs
.form-breadcrumbs {
  display: flex;
  align-items: center;
  gap: $space-2;
  font-size: $fs-xs;
  color: $color-neutral-500;

  .crumb-link {
    color: $color-neutral-600;
    text-decoration: none;
    transition: color $transition-fast;

    &:hover {
      color: $color-primary-600;
    }
  }

  .crumb-sep {
    color: $color-neutral-300;
  }

  .crumb-current {
    font-weight: $fw-semibold;
    color: $color-neutral-800;
  }
}

// Header
.form-top-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: $space-4;
  padding-bottom: $space-3;
  border-bottom: 1px solid $color-neutral-200;

  .header-intro {
    .title-with-badge {
      display: flex;
      align-items: center;
      gap: $space-3;
      flex-wrap: wrap;

      .header-title {
        font-family: $font-heading;
        font-size: $fs-2xl;
        font-weight: $fw-bold;
        color: $color-neutral-900;
        margin: 0;
      }

      .status-pill {
        font-size: $fs-xs;
        font-weight: $fw-bold;
        padding: 3px $space-3;
        border-radius: $radius-full;

        &.published {
          background-color: hsl(142, 76%, 94%);
          color: hsl(142, 76%, 28%);
        }

        &.draft {
          background-color: $color-neutral-200;
          color: $color-neutral-700;
        }
      }
    }

    .header-subtitle {
      font-size: $fs-sm;
      color: $color-neutral-500;
      margin: $space-1 0 0;
    }
  }

  .header-action-group {
    display: flex;
    align-items: center;
    gap: $space-2;
  }
}

// 2-Column Split Grid
.form-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: $space-6;

  @media (min-width: 1024px) {
    grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
  }

  .main-column {
    display: flex;
    flex-direction: column;
    gap: $space-5;
    min-width: 0;
  }

  .sidebar-column {
    display: flex;
    flex-direction: column;
    gap: $space-5;
    min-width: 0;
  }
}

// Generic Card Architecture
.form-card {
  background: $color-neutral-0;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-xl;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  transition: box-shadow $transition-fast;

  &:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
  }

  &.highlight-card {
    border-color: rgba($color-primary-400, 0.4);
    box-shadow: 0 2px 8px rgba($color-primary-500, 0.08);
  }

  .card-header,
  .card-header-between {
    padding: $space-4 $space-5;
    border-bottom: 1px solid $color-neutral-200;
    background-color: $color-neutral-50;
    display: flex;
    align-items: center;
    gap: $space-3;

    .card-icon {
      font-size: $fs-xl;
      flex-shrink: 0;
    }

    .card-heading {
      font-size: $fs-base;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 0;
    }

    .card-subheading {
      font-size: $fs-xs;
      color: $color-neutral-500;
      margin: 2px 0 0;
    }
  }

  .card-header-between {
    justify-content: space-between;

    .card-header-left {
      display: flex;
      align-items: center;
      gap: $space-3;
    }
  }

  .card-body {
    padding: $space-5;
    display: flex;
    flex-direction: column;
    gap: $space-4;
  }
}

// Form Fields & Controls
.form-field {
  display: flex;
  flex-direction: column;
  gap: $space-2;

  .field-label {
    font-size: $fs-xs;
    font-weight: $fw-bold;
    color: $color-neutral-700;
    display: flex;
    align-items: center;
    gap: $space-1;

    .required-mark {
      color: $color-danger;
    }

    .field-subtext {
      font-weight: $fw-normal;
      color: $color-neutral-400;
      font-size: $fs-2xs;
    }
  }

  .field-label-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .field-hint {
    font-size: $fs-2xs;
    color: $color-neutral-500;
    margin: 2px 0 0;
  }

  .field-error-msg {
    font-size: $fs-xs;
    color: $color-danger;
    font-weight: $fw-medium;
  }
}

.field-input,
.field-select,
.field-textarea {
  width: 100%;
  padding: 9px 13px;
  background-color: $color-neutral-0;
  border: 1px solid $color-neutral-300;
  border-radius: $radius-lg;
  font-size: $fs-sm;
  color: $color-neutral-900;
  outline: none;
  transition: all $transition-fast;

  &:focus {
    border-color: $color-primary-500;
    box-shadow: 0 0 0 3px rgba($color-primary-500, 0.15);
  }

  &.has-error {
    border-color: $color-danger;
    background-color: hsl(0, 100%, 99%);
  }

  &.field-input-lg {
    font-size: $fs-base;
    font-weight: $fw-semibold;
    padding: 11px 14px;
  }

  &.uppercase-text {
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-family: $font-mono;
  }
}

.field-textarea {
  resize: vertical;
  line-height: $lh-normal;
}

// Slug and Prefix Inputs
.slug-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .slug-toggle-btn {
    background: transparent;
    border: none;
    color: $color-primary-600;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    cursor: pointer;

    &:hover {
      text-decoration: underline;
    }
  }
}

.input-prefix-wrap,
.input-suffix-wrap,
.currency-input-wrap {
  display: flex;
  align-items: center;
  position: relative;
  width: 100%;

  .input-prefix,
  .currency-symbol {
    position: absolute;
    left: 12px;
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    color: $color-neutral-400;
    pointer-events: none;
  }

  .input-suffix {
    position: absolute;
    right: 12px;
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    color: $color-neutral-400;
    pointer-events: none;
  }

  .prefix-input,
  .currency-input {
    padding-left: 80px;
  }

  .currency-input {
    padding-left: 32px;
  }

  .suffix-input {
    padding-right: 40px;
  }
}

// Textarea Toolbar
.description-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .char-counter {
    font-size: $fs-2xs;
    color: $color-neutral-400;

    &.char-warning {
      color: $color-danger;
      font-weight: $fw-bold;
    }
  }
}

.textarea-toolbar {
  display: flex;
  gap: 4px;
  padding: 4px $space-2;
  background-color: $color-neutral-100;
  border: 1px solid $color-neutral-300;
  border-bottom: none;
  border-radius: $radius-lg $radius-lg 0 0;

  .toolbar-btn {
    padding: 3px $space-2;
    background: $color-neutral-0;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-sm;
    font-size: $fs-xs;
    color: $color-neutral-700;
    cursor: pointer;
    transition: all $transition-fast;

    &:hover {
      background: $color-primary-50;
      color: $color-primary-700;
      border-color: $color-primary-300;
    }
  }
}

.textarea-toolbar + .field-textarea {
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

// Media Upload & Dropzone
.media-tabs {
  display: flex;
  gap: $space-2;
  margin-bottom: $space-1;

  .media-tab {
    padding: 6px $space-3;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    color: $color-neutral-600;
    background: $color-neutral-100;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-md;
    cursor: pointer;
    transition: all $transition-fast;

    &.active {
      background: $color-primary-50;
      color: $color-primary-700;
      border-color: $color-primary-300;
    }
  }
}

.url-input-row {
  display: flex;
  gap: $space-2;
}

.file-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: $space-6;
  border: 2px dashed $color-neutral-300;
  border-radius: $radius-xl;
  background: $color-neutral-50;
  cursor: pointer;
  transition: all $transition-fast;

  &:hover {
    border-color: $color-primary-400;
    background: $color-primary-50;
  }

  .hidden-file-input {
    display: none;
  }

  .dropzone-icon {
    font-size: $fs-3xl;
    margin-bottom: $space-1;
  }

  .dropzone-text {
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    color: $color-neutral-800;
    margin: 0;
  }

  .dropzone-sub {
    font-size: $fs-xs;
    color: $color-neutral-400;
    margin: 4px 0 0;
  }
}

.image-preview-card {
  display: flex;
  gap: $space-4;
  padding: $space-4;
  background: $color-neutral-50;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-lg;
  align-items: center;

  .preview-img-box {
    position: relative;
    width: 120px;
    height: 120px;
    border-radius: $radius-md;
    overflow: hidden;
    background: #000;
    flex-shrink: 0;

    .preview-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .preview-badge {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.7);
      color: #fff;
      font-size: 8px;
      padding: 2px 4px;
      text-align: center;
      font-weight: $fw-bold;
    }
  }

  .preview-meta {
    display: flex;
    flex-direction: column;
    gap: $space-2;
    flex: 1;

    .meta-row {
      display: flex;
      gap: $space-2;
      font-size: $fs-xs;

      .meta-label {
        font-weight: $fw-bold;
        color: $color-neutral-500;
      }

      .meta-val {
        color: $color-neutral-700;
      }
    }

    .btn-remove-img {
      align-self: flex-start;
      margin-top: $space-1;
      background: rgba($color-danger, 0.1);
      border: 1px solid rgba($color-danger, 0.3);
      color: $color-danger;
      padding: 4px $space-3;
      border-radius: $radius-md;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      cursor: pointer;
      transition: all $transition-fast;

      &:hover {
        background: $color-danger;
        color: #fff;
      }
    }
  }
}

// Grids
.grid-2-col {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: $space-4;
}

.grid-3-col {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: $space-4;
}

.link-btn-action {
  background: transparent;
  border: none;
  color: $color-primary-600;
  font-size: $fs-2xs;
  font-weight: $fw-bold;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }
}

// Technical Specifications Table
.preset-pills-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: $space-1;
  padding-bottom: $space-2;

  .preset-label {
    font-size: $fs-2xs;
    font-weight: $fw-bold;
    color: $color-neutral-500;
    margin-right: 4px;
  }

  .preset-pill {
    padding: 3px $space-2;
    border-radius: $radius-full;
    background: $color-neutral-100;
    border: 1px solid $color-neutral-200;
    font-size: $fs-2xs;
    font-weight: $fw-semibold;
    color: $color-neutral-700;
    cursor: pointer;
    transition: all $transition-fast;

    &:hover {
      background: $color-primary-50;
      border-color: $color-primary-300;
      color: $color-primary-700;
    }
  }
}

.specs-table-container {
  display: flex;
  flex-direction: column;
  gap: $space-2;

  .specs-table-header {
    display: grid;
    grid-template-columns: 1fr 1fr 40px;
    gap: $space-2;
    padding: 0 $space-1;

    .col-head {
      font-size: $fs-2xs;
      font-weight: $fw-bold;
      color: $color-neutral-400;
      text-transform: uppercase;
      letter-spacing: 0.05em;

      &.action-col {
        text-align: center;
      }
    }
  }

  .specs-table-row {
    display: grid;
    grid-template-columns: 1fr 1fr 40px;
    gap: $space-2;
    align-items: center;

    .btn-row-delete {
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: 1px solid transparent;
      border-radius: $radius-md;
      cursor: pointer;
      color: $color-neutral-400;
      transition: all $transition-fast;

      &:hover {
        background: rgba($color-danger, 0.1);
        color: $color-danger;
        border-color: rgba($color-danger, 0.2);
      }
    }
  }
}

// Google SERP Snippet
.google-snippet-preview {
  margin-top: $space-2;
  padding: $space-4;
  background: #f8f9fa;
  border: 1px solid #dfe1e5;
  border-radius: $radius-lg;
  font-family: Arial, sans-serif;

  .snippet-tag {
    display: block;
    font-size: 10px;
    font-weight: bold;
    color: #5f6368;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: $space-2;
  }

  .snippet-url {
    font-size: 12px;
    color: #202124;
    margin-bottom: 2px;
  }

  .snippet-title {
    font-size: 16px;
    color: #1a0dab;
    font-weight: 500;
    margin-bottom: 4px;
    cursor: pointer;

    &:hover {
      text-decoration: underline;
    }
  }

  .snippet-desc {
    font-size: 13px;
    color: #4d5156;
    line-height: 1.4;
  }
}

// Profit Analytics Box
.profit-analytics-box {
  background: $color-primary-50;
  border: 1px solid $color-primary-100;
  border-radius: $radius-lg;
  padding: $space-4;
  display: flex;
  flex-direction: column;
  gap: $space-3;

  .analytics-row {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .analytics-label {
      font-size: $fs-xs;
      font-weight: $fw-bold;
      color: $color-neutral-700;
    }

    .analytics-val {
      font-size: $fs-sm;
      font-weight: $fw-semibold;
      color: $color-neutral-800;

      &.profit-val {
        color: hsl(142, 76%, 32%);
        font-weight: $fw-bold;
      }
    }

    .analytics-badge {
      font-size: $fs-xs;
      font-weight: $fw-bold;
      padding: 2px $space-3;
      border-radius: $radius-full;

      &.badge-positive {
        background: hsl(142, 76%, 88%);
        color: hsl(142, 76%, 24%);
      }

      &.badge-neutral {
        background: $color-neutral-200;
        color: $color-neutral-700;
      }
    }
  }
}

// Tax Compliance Pill
.vat-compliance-pill {
  display: flex;
  gap: $space-3;
  padding: $space-3;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: $radius-lg;
  align-items: flex-start;

  .vat-icon {
    font-size: $fs-base;
  }

  .vat-info {
    display: flex;
    flex-direction: column;

    .vat-title {
      font-size: $fs-xs;
      font-weight: $fw-bold;
      color: #166534;
    }

    .vat-sub {
      font-size: $fs-2xs;
      color: #15803d;
      margin-top: 2px;
    }
  }
}

// Custom Switch & Toggles
.toggle-control-box {
  display: flex;
  flex-direction: column;
  gap: $space-3;

  .divider-line {
    height: 1px;
    background: $color-neutral-200;
  }

  .switch-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;

    .switch-info {
      display: flex;
      flex-direction: column;
      padding-right: $space-3;

      .switch-title {
        font-size: $fs-sm;
        font-weight: $fw-bold;
        color: $color-neutral-800;
      }

      .switch-sub {
        font-size: $fs-2xs;
        color: $color-neutral-500;
        margin-top: 2px;
      }
    }

    .custom-toggle-input {
      width: 20px;
      height: 20px;
      cursor: pointer;
      accent-color: $color-primary-500;
      flex-shrink: 0;
    }
  }
}

// Floating Bottom Save Bar
.floating-save-bar {
  position: fixed;
  bottom: 0;
  left: 260px;
  right: 0;
  background: rgba($color-neutral-0, 0.95);
  backdrop-filter: blur(8px);
  border-top: 1px solid $color-neutral-200;
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
  padding: $space-3 $space-6;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 50;
  transition: left $transition-base;

  .bar-left {
    display: flex;
    align-items: center;
    gap: $space-2;

    .status-indicator-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;

      &.online {
        background-color: hsl(142, 76%, 45%);
        box-shadow: 0 0 6px hsl(142, 76%, 50%);
      }

      &.offline {
        background-color: $color-neutral-400;
      }
    }

    .status-text {
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: $color-neutral-600;
    }
  }

  .bar-right {
    display: flex;
    align-items: center;
    gap: $space-3;
  }
}

:deep(.sidebar-collapsed) .floating-save-bar {
  left: 72px;
}

// Button Primitives
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: $space-2;
  padding: 8px 16px;
  border-radius: $radius-lg;
  font-size: $fs-sm;
  font-weight: $fw-semibold;
  cursor: pointer;
  transition: all $transition-fast;
  text-decoration: none;
  border: 1px solid transparent;

  &-sm {
    padding: 5px 12px;
    font-size: $fs-xs;
  }

  &-primary {
    background: linear-gradient(135deg, $color-primary-500 0%, $color-primary-600 100%);
    color: #fff;
    box-shadow: 0 2px 6px rgba($color-primary-500, 0.35);

    &:hover:not(:disabled) {
      background: linear-gradient(135deg, $color-primary-600 0%, $color-primary-700 100%);
      box-shadow: 0 4px 12px rgba($color-primary-500, 0.45);
      transform: translateY(-1px);
    }
  }

  &-secondary {
    background: $color-neutral-100;
    color: $color-neutral-700;
    border-color: $color-neutral-300;

    &:hover:not(:disabled) {
      background: $color-neutral-200;
      color: $color-neutral-900;
    }
  }

  &-outline {
    background: transparent;
    color: $color-neutral-700;
    border-color: $color-neutral-300;

    &:hover:not(:disabled) {
      background: $color-neutral-50;
      border-color: $color-neutral-400;
      color: $color-neutral-900;
    }
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
