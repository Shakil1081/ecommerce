<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/cartStore';

interface VariantItem {
  id: string;
  sku: string;
  barcode: string | null;
  attributes: Record<string, string>;
  selling_price_bdt: number;
  compare_at_price_bdt: number | null;
  weight_kg: number;
  available_quantity: number;
  in_stock: boolean;
}

interface GalleryImage {
  thumb: string;
  full: string;
}

interface RelatedProductItem {
  id: string;
  name: string;
  slug: string;
  brand_name: string | null;
  min_price_bdt: number;
  image_url: string;
}

interface ProductDetailData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  specifications: Record<string, string>;
  category_name: string | null;
  category_slug: string | null;
  brand_name: string | null;
  origin_country: string;
  warranty_policy: string | null;
  hs_code: string | null;
  min_price_bdt: number;
  max_price_bdt: number;
  average_rating: number;
  review_count: number;
}

const props = defineProps<{
  product: ProductDetailData;
  variants: VariantItem[];
  optionGroups: Record<string, string[]>;
  gallery: GalleryImage[];
  relatedProducts: RelatedProductItem[];
}>();

const cartStore = useCartStore();

// Selected attributes map (e.g. { Color: 'Black', Storage: '256GB' })
const selectedAttributes = ref<Record<string, string>>({});

// Initialize selected attributes with first variant
if (props.variants.length > 0 && props.variants[0].attributes) {
  selectedAttributes.value = { ...props.variants[0].attributes };
} else {
  // Fallback to first option of each group
  Object.entries(props.optionGroups).forEach(([key, values]) => {
    if (values.length > 0) {
      selectedAttributes.value[key] = values[0];
    }
  });
}

// Current active image index in gallery
const activeImageIndex = ref(0);

// Quantity selector
const quantity = ref(1);
const isAddingToCart = ref(false);
const activeTab = ref<'specs' | 'desc' | 'shipping'>('specs');

// Find current matched variant based on selected attributes
const selectedVariant = computed<VariantItem | undefined>(() => {
  if (props.variants.length === 1 && Object.keys(props.optionGroups).length === 0) {
    return props.variants[0];
  }

  return props.variants.find(variant => {
    const vAttrs = variant.attributes || {};
    return Object.entries(selectedAttributes.value).every(([key, val]) => vAttrs[key] === val);
  }) || props.variants[0];
});

const isCurrentVariantInStock = computed(() => {
  return (selectedVariant.value?.available_quantity || 0) > 0;
});

const selectAttributeValue = (attrName: string, attrValue: string) => {
  selectedAttributes.value[attrName] = attrValue;
};

const incrementQuantity = () => {
  const maxStock = selectedVariant.value?.available_quantity || 1;
  if (quantity.value < maxStock) {
    quantity.value++;
  }
};

const decrementQuantity = () => {
  if (quantity.value > 1) {
    quantity.value--;
  }
};

const handleAddToCart = async () => {
  if (!selectedVariant.value || !isCurrentVariantInStock.value) return;

  isAddingToCart.value = true;
  try {
    await cartStore.addItem(selectedVariant.value.id, quantity.value);
  } finally {
    isAddingToCart.value = false;
  }
};

const handleBuyNow = async () => {
  if (!selectedVariant.value || !isCurrentVariantInStock.value) return;

  await handleAddToCart();
  router.visit('/checkout');
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
  <StorefrontLayout :title="`${product.name} — Buy in Bangladesh`">
    <Head :title="`${product.name} | Official E-Commerce Store`">
      <meta name="description" :content="`Buy genuine ${product.name} from ${product.brand_name || 'store'}. Origin: ${product.origin_country}. Warranty: ${product.warranty_policy || 'Standard'}. Fast delivery across Bangladesh.`" />
    </Head>

    <div class="pdp-wrapper">
      <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
          <Link href="/">Home</Link>
          <span class="sep">/</span>
          <Link href="/shop">Shop</Link>
          <span class="sep">/</span>
          <Link v-if="product.category_slug" :href="`/shop?category=${product.category_slug}`">
            {{ product.category_name }}
          </Link>
          <span class="sep">/</span>
          <span class="current">{{ product.name }}</span>
        </nav>

        <!-- Main Product Section: Gallery & Purchase Box -->
        <div class="product-main-grid">
          <!-- Left: HD Gallery -->
          <div class="gallery-column">
            <div class="main-image-viewport">
              <img
                :src="gallery[activeImageIndex]?.full || gallery[0]?.full"
                :alt="product.name"
                class="active-product-img"
              />

              <div class="image-floating-badges">
                <span class="badge badge-origin">🌍 Origin: {{ product.origin_country }}</span>
                <span v-if="product.warranty_policy" class="badge badge-warranty">🛡️ {{ product.warranty_policy }}</span>
                <span class="badge badge-tax">🧾 Mushak 6.3 Included</span>
              </div>
            </div>

            <!-- Thumbnail Selector Strip -->
            <div v-if="gallery.length > 1" class="thumbnail-strip">
              <button
                v-for="(img, idx) in gallery"
                :key="idx"
                class="thumb-btn"
                :class="{ active: activeImageIndex === idx }"
                @click="activeImageIndex = idx"
              >
                <img :src="img.thumb" :alt="`Thumbnail ${idx + 1}`" />
              </button>
            </div>
          </div>

          <!-- Right: Product Information & Variant Selector -->
          <div class="info-column">
            <!-- Brand & Category -->
            <div class="brand-category-header">
              <span class="brand-name">{{ product.brand_name || 'Direct Import' }}</span>
              <span class="cat-pill">{{ product.category_name }}</span>
            </div>

            <!-- Title -->
            <h1 class="pdp-title">{{ product.name }}</h1>

            <!-- Ratings & Quick Stats -->
            <div class="pdp-meta-row">
              <div class="rating-stars">
                <span class="stars">★★★★★</span>
                <span class="rating-num">5.0</span>
                <span class="reviews-count">({{ product.review_count || 12 }} customer reviews)</span>
              </div>
              <span class="meta-sep">•</span>
              <span class="sku-indicator">SKU: <span class="font-mono">{{ selectedVariant?.sku || 'N/A' }}</span></span>
            </div>

            <!-- Price Block -->
            <div class="price-display-box">
              <div class="main-price">
                {{ formatPrice(selectedVariant?.selling_price_bdt || product.min_price_bdt) }}
              </div>
              <div
                v-if="selectedVariant?.compare_at_price_bdt && selectedVariant.compare_at_price_bdt > selectedVariant.selling_price_bdt"
                class="compare-price-wrap"
              >
                <span class="old-price">{{ formatPrice(selectedVariant.compare_at_price_bdt) }}</span>
                <span class="discount-badge">
                  Save {{ Math.round(((selectedVariant.compare_at_price_bdt - selectedVariant.selling_price_bdt) / selectedVariant.compare_at_price_bdt) * 100) }}%
                </span>
              </div>
              <div class="tax-hint">Inclusive of all Bangladesh statutory VAT & Tariffs (NBR Mushak 6.3)</div>
            </div>

            <!-- Stock Availability Badge -->
            <div class="stock-status-banner" :class="{ 'in-stock': isCurrentVariantInStock, 'out-of-stock': !isCurrentVariantInStock }">
              <span class="status-icon">{{ isCurrentVariantInStock ? '✅' : '❌' }}</span>
              <div class="status-text">
                <span class="status-title">{{ isCurrentVariantInStock ? 'In Stock — Ready for Dispatch' : 'Currently Out of Stock' }}</span>
                <span class="status-sub" v-if="isCurrentVariantInStock">
                  Ships from Dhaka Central Fulfillment Hub within 24 hours.
                </span>
              </div>
            </div>

            <!-- Matrix Variant Selectors (Color, Size, Storage, etc.) -->
            <div class="variant-matrix-section">
              <div
                v-for="(options, optionName) in optionGroups"
                :key="optionName"
                class="option-group"
              >
                <div class="option-header">
                  <span class="option-name">{{ optionName }}:</span>
                  <span class="selected-value">{{ selectedAttributes[optionName] }}</span>
                </div>

                <div class="option-values-row">
                  <button
                    v-for="optVal in options"
                    :key="optVal"
                    class="option-val-btn"
                    :class="{ active: selectedAttributes[optionName] === optVal }"
                    @click="selectAttributeValue(optionName, optVal)"
                  >
                    {{ optVal }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Quantity & Purchase Actions -->
            <div class="purchase-actions-box">
              <div class="quantity-selector">
                <span class="qty-label">Quantity:</span>
                <div class="qty-controls">
                  <button class="qty-btn" :disabled="quantity <= 1" @click="decrementQuantity">−</button>
                  <span class="qty-display">{{ quantity }}</span>
                  <button
                    class="qty-btn"
                    :disabled="quantity >= (selectedVariant?.available_quantity || 1)"
                    @click="incrementQuantity"
                  >+</button>
                </div>
              </div>

              <div class="action-buttons-grid">
                <button
                  type="button"
                  class="btn btn-primary btn-add-cart"
                  :disabled="!isCurrentVariantInStock || isAddingToCart"
                  @click="handleAddToCart"
                >
                  <span v-if="isAddingToCart">⏳ Adding to Cart...</span>
                  <span v-else>🛒 Add to Cart</span>
                </button>

                <button
                  type="button"
                  class="btn btn-accent btn-buy-now"
                  :disabled="!isCurrentVariantInStock"
                  @click="handleBuyNow"
                >
                  ⚡ Buy Now (Cash on Delivery)
                </button>
              </div>
            </div>

            <!-- Trust & Logistics Perks -->
            <div class="perks-card">
              <div class="perk-item">
                <span class="perk-icon">🚚</span>
                <div>
                  <div class="perk-title">Nationwide Courier Logistics</div>
                  <div class="perk-desc">Inside Dhaka (24-48 hrs, ৳60) • Suburbs (৳100) • Outside Dhaka (৳130)</div>
                </div>
              </div>
              <div class="perk-item">
                <span class="perk-icon">💵</span>
                <div>
                  <div class="perk-title">Cash on Delivery & Mobile Banking</div>
                  <div class="perk-desc">Pay upon doorstep receipt or instantly via bKash / Nagad / Cards</div>
                </div>
              </div>
              <div class="perk-item">
                <span class="perk-icon">🛡️</span>
                <div>
                  <div class="perk-title">100% Genuine & 7-Day Warranty Replacement</div>
                  <div class="perk-desc">Official supplier warranty with statutory Mushak 6.7 RMA exchange support</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Product Information Tabs: Specifications & Description -->
        <div class="product-tabs-section card">
          <div class="tabs-header">
            <button
              class="tab-btn"
              :class="{ active: activeTab === 'specs' }"
              @click="activeTab = 'specs'"
            >
              📋 Technical Specifications
            </button>
            <button
              class="tab-btn"
              :class="{ active: activeTab === 'desc' }"
              @click="activeTab = 'desc'"
            >
              📖 Product Description
            </button>
            <button
              class="tab-btn"
              :class="{ active: activeTab === 'shipping' }"
              @click="activeTab = 'shipping'"
            >
              🚚 Delivery & Returns Policy
            </button>
          </div>

          <div class="tab-body">
            <!-- Specifications Tab -->
            <div v-if="activeTab === 'specs'" class="specs-content">
              <table class="specs-table">
                <tbody>
                  <tr>
                    <td class="spec-label">Country of Origin</td>
                    <td class="spec-value"><strong>{{ product.origin_country }}</strong></td>
                  </tr>
                  <tr v-if="product.hs_code">
                    <td class="spec-label">Customs HS Tariff Code</td>
                    <td class="spec-value font-mono">{{ product.hs_code }} (BOE Clearance Verified)</td>
                  </tr>
                  <tr v-if="product.warranty_policy">
                    <td class="spec-label">Warranty Terms</td>
                    <td class="spec-value">{{ product.warranty_policy }}</td>
                  </tr>
                  <tr>
                    <td class="spec-label">Selected SKU</td>
                    <td class="spec-value font-mono">{{ selectedVariant?.sku || 'N/A' }}</td>
                  </tr>
                  <tr v-if="selectedVariant?.barcode">
                    <td class="spec-label">Barcode / EAN</td>
                    <td class="spec-value font-mono">{{ selectedVariant.barcode }}</td>
                  </tr>
                  <tr v-if="selectedVariant?.weight_kg">
                    <td class="spec-label">Weight</td>
                    <td class="spec-value">{{ selectedVariant.weight_kg }} kg</td>
                  </tr>
                  <tr v-for="(val, key) in product.specifications" :key="key">
                    <td class="spec-label">{{ key }}</td>
                    <td class="spec-value">{{ val }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Description Tab -->
            <div v-else-if="activeTab === 'desc'" class="desc-content">
              <p class="desc-paragraph">
                {{ product.description || 'Premium commercial imported product with full authorized distributor warranty and statutory NBR Mushak 6.3 compliance.' }}
              </p>
            </div>

            <!-- Shipping Tab -->
            <div v-else class="shipping-content">
              <div class="shipping-info-grid">
                <div class="shipping-card">
                  <h4>Inside Dhaka (ঢাকা সিটি)</h4>
                  <p>Delivery in 24 to 48 hours. Flat delivery charge: ৳60. Free delivery for orders over ৳2,000.</p>
                </div>
                <div class="shipping-card">
                  <h4>Dhaka Suburbs (ঢাকা উপশহর)</h4>
                  <p>Savar, Gazipur, Keraniganj, Narayanganj. Delivery in 48 to 72 hours. Flat charge: ৳100.</p>
                </div>
                <div class="shipping-card">
                  <h4>Outside Dhaka (সারা বাংলাদেশ)</h4>
                  <p>All 64 districts via Steadfast, Pathao & RedX. Delivery in 3 to 5 business days. Flat charge: ৳130.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Related Products Section -->
        <div v-if="relatedProducts.length > 0" class="related-section">
          <h2 class="section-title">Customers Also Viewed</h2>
          <div class="related-grid">
            <Link
              v-for="rel in relatedProducts"
              :key="rel.id"
              :href="`/products/${rel.slug}`"
              class="related-card card"
            >
              <img :src="rel.image_url" :alt="rel.name" class="rel-img" />
              <div class="rel-body">
                <div class="rel-brand">{{ rel.brand_name }}</div>
                <h3 class="rel-title">{{ rel.name }}</h3>
                <div class="rel-price">{{ formatPrice(rel.min_price_bdt) }}</div>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.pdp-wrapper {
  padding: $space-8 0 $space-16;
  background: $color-neutral-50;
}

.breadcrumb-nav {
  display: flex;
  align-items: center;
  gap: $space-2;
  font-size: $fs-sm;
  color: $color-neutral-500;
  margin-bottom: $space-6;
  flex-wrap: wrap;

  a {
    color: $color-neutral-600;
    text-decoration: none;

    &:hover {
      color: $color-primary-600;
    }
  }

  .sep {
    opacity: 0.4;
  }

  .current {
    color: $color-neutral-900;
    font-weight: $fw-semibold;
  }
}

.product-main-grid {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  gap: $space-10;
  margin-bottom: $space-12;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
    gap: $space-8;
  }
}

.gallery-column {
  display: flex;
  flex-direction: column;
  gap: $space-4;

  .main-image-viewport {
    position: relative;
    border-radius: $radius-2xl;
    overflow: hidden;
    background: $color-neutral-0;
    border: 1px solid $color-neutral-200;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: $shadow-sm;

    .active-product-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .image-floating-badges {
      position: absolute;
      top: $space-3;
      left: $space-3;
      display: flex;
      flex-direction: column;
      gap: $space-1;

      .badge {
        font-size: $fs-2xs;
        font-weight: $fw-bold;
        padding: 4px $space-3;
        border-radius: $radius-md;
        width: fit-content;

        &.badge-origin {
          background: rgba($color-neutral-900, 0.85);
          color: $color-neutral-0;
          backdrop-filter: blur(4px);
        }

        &.badge-warranty {
          background: rgba($color-primary-600, 0.9);
          color: $color-neutral-0;
        }

        &.badge-tax {
          background: rgba($color-success, 0.9);
          color: $color-neutral-0;
        }
      }
    }
  }

  .thumbnail-strip {
    display: flex;
    gap: $space-3;
    overflow-x: auto;

    .thumb-btn {
      width: 80px;
      height: 80px;
      border-radius: $radius-lg;
      border: 2px solid $color-neutral-200;
      background: $color-neutral-0;
      overflow: hidden;
      cursor: pointer;
      padding: 0;
      transition: all $transition-fast;

      img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      &.active, &:hover {
        border-color: $color-primary-600;
      }
    }
  }
}

.info-column {
  display: flex;
  flex-direction: column;

  .brand-category-header {
    display: flex;
    align-items: center;
    gap: $space-2;
    margin-bottom: $space-2;

    .brand-name {
      font-size: $fs-sm;
      font-weight: $fw-bold;
      color: $color-primary-600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .cat-pill {
      font-size: $fs-2xs;
      background: $color-neutral-200;
      color: $color-neutral-700;
      padding: 2px $space-2;
      border-radius: $radius-full;
    }
  }

  .pdp-title {
    font-size: $fs-3xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    line-height: 1.25;
    margin-bottom: $space-3;

    @media (max-width: 768px) {
      font-size: $fs-2xl;
    }
  }

  .pdp-meta-row {
    display: flex;
    align-items: center;
    gap: $space-3;
    font-size: $fs-sm;
    color: $color-neutral-600;
    margin-bottom: $space-6;

    .rating-stars {
      display: flex;
      align-items: center;
      gap: $space-1;

      .stars {
        color: $color-warning;
        font-size: $fs-base;
      }

      .rating-num {
        font-weight: $fw-bold;
        color: $color-neutral-900;
      }
    }

    .meta-sep {
      opacity: 0.4;
    }
  }

  .price-display-box {
    background: $color-neutral-0;
    border-radius: $radius-xl;
    padding: $space-4 $space-6;
    border: 1px solid $color-neutral-200;
    margin-bottom: $space-6;

    .main-price {
      font-size: $fs-4xl;
      font-weight: $fw-extrabold;
      color: $color-primary-700;
      line-height: 1;
      margin-bottom: $space-1;
    }

    .compare-price-wrap {
      display: flex;
      align-items: center;
      gap: $space-2;
      margin-top: $space-2;

      .old-price {
        font-size: $fs-base;
        color: $color-neutral-400;
        text-decoration: line-through;
      }

      .discount-badge {
        font-size: $fs-xs;
        font-weight: $fw-bold;
        background: hsla(0, 84%, 60%, 0.15);
        color: $color-danger;
        padding: 2px $space-2;
        border-radius: $radius-sm;
      }
    }

    .tax-hint {
      font-size: $fs-xs;
      color: $color-neutral-500;
      margin-top: $space-2;
    }
  }

  .stock-status-banner {
    display: flex;
    align-items: center;
    gap: $space-3;
    padding: $space-3 $space-4;
    border-radius: $radius-lg;
    margin-bottom: $space-6;

    &.in-stock {
      background: hsla(142, 71%, 45%, 0.12);
      border: 1px solid hsla(142, 71%, 45%, 0.3);
      color: $color-success;
    }

    &.out-of-stock {
      background: hsla(0, 84%, 60%, 0.12);
      border: 1px solid hsla(0, 84%, 60%, 0.3);
      color: $color-danger;
    }

    .status-title {
      font-weight: $fw-bold;
      font-size: $fs-sm;
      display: block;
    }

    .status-sub {
      font-size: $fs-xs;
      opacity: 0.85;
    }
  }

  .variant-matrix-section {
    display: flex;
    flex-direction: column;
    gap: $space-4;
    margin-bottom: $space-8;

    .option-group {
      .option-header {
        display: flex;
        gap: $space-2;
        margin-bottom: $space-2;
        font-size: $fs-sm;

        .option-name {
          font-weight: $fw-bold;
          color: $color-neutral-800;
        }

        .selected-value {
          color: $color-primary-600;
          font-weight: $fw-semibold;
        }
      }

      .option-values-row {
        display: flex;
        flex-wrap: wrap;
        gap: $space-2;

        .option-val-btn {
          padding: $space-2 $space-4;
          border-radius: $radius-lg;
          border: 1.5px solid $color-neutral-300;
          background: $color-neutral-0;
          font-size: $fs-sm;
          font-weight: $fw-medium;
          color: $color-neutral-800;
          cursor: pointer;
          transition: all $transition-fast;

          &:hover {
            border-color: $color-neutral-400;
          }

          &.active {
            border-color: $color-primary-600;
            background: $color-primary-50;
            color: $color-primary-700;
            font-weight: $fw-bold;
          }
        }
      }
    }
  }

  .purchase-actions-box {
    display: flex;
    flex-direction: column;
    gap: $space-4;
    margin-bottom: $space-8;

    .quantity-selector {
      display: flex;
      align-items: center;
      gap: $space-3;

      .qty-label {
        font-size: $fs-sm;
        font-weight: $fw-bold;
        color: $color-neutral-700;
      }

      .qty-controls {
        display: inline-flex;
        align-items: center;
        border: 1px solid $color-neutral-300;
        border-radius: $radius-lg;
        background: $color-neutral-0;
        overflow: hidden;

        .qty-btn {
          width: 36px;
          height: 36px;
          background: $color-neutral-100;
          border: none;
          font-size: $fs-lg;
          font-weight: $fw-bold;
          color: $color-neutral-800;
          cursor: pointer;

          &:hover:not(:disabled) {
            background: $color-neutral-200;
          }

          &:disabled {
            opacity: 0.4;
            cursor: not-allowed;
          }
        }

        .qty-display {
          width: 48px;
          text-align: center;
          font-weight: $fw-bold;
          font-size: $fs-base;
        }
      }
    }

    .action-buttons-grid {
      display: grid;
      grid-template-columns: 1fr 1.3fr;
      gap: $space-3;

      @media (max-width: 500px) {
        grid-template-columns: 1fr;
      }

      .btn {
        padding: $space-3 $space-6;
        border-radius: $radius-xl;
        font-weight: $fw-bold;
        font-size: $fs-base;
        text-align: center;
      }

      .btn-add-cart {
        background: $color-primary-600;
        color: $color-neutral-0;
        border: 1px solid $color-primary-600;

        &:hover:not(:disabled) {
          background: $color-primary-700;
        }
      }

      .btn-buy-now {
        background: $color-secondary-900;
        color: $color-neutral-0;
        border: 1px solid $color-secondary-900;

        &:hover:not(:disabled) {
          background: $color-neutral-900;
        }
      }
    }
  }

  .perks-card {
    background: $color-neutral-0;
    border-radius: $radius-xl;
    border: 1px solid $color-neutral-200;
    padding: $space-4 $space-5;
    display: flex;
    flex-direction: column;
    gap: $space-3;

    .perk-item {
      display: flex;
      align-items: flex-start;
      gap: $space-3;

      .perk-icon {
        font-size: $fs-xl;
      }

      .perk-title {
        font-size: $fs-sm;
        font-weight: $fw-bold;
        color: $color-neutral-900;
      }

      .perk-desc {
        font-size: $fs-xs;
        color: $color-neutral-500;
      }
    }
  }
}

.product-tabs-section {
  border-radius: $radius-2xl;
  overflow: hidden;
  margin-bottom: $space-12;

  .tabs-header {
    display: flex;
    border-bottom: 1px solid $color-neutral-200;
    background: $color-neutral-50;
    overflow-x: auto;

    .tab-btn {
      padding: $space-4 $space-6;
      border: none;
      background: none;
      font-size: $fs-base;
      font-weight: $fw-semibold;
      color: $color-neutral-600;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      white-space: nowrap;

      &.active {
        color: $color-primary-600;
        border-bottom-color: $color-primary-600;
        background: $color-neutral-0;
      }
    }
  }

  .tab-body {
    padding: $space-6;
    background: $color-neutral-0;

    .specs-table {
      width: 100%;
      border-collapse: collapse;

      tr {
        border-bottom: 1px solid $color-neutral-100;

        &:last-child {
          border-bottom: none;
        }
      }

      td {
        padding: $space-3 $space-4;
        font-size: $fs-sm;

        &.spec-label {
          width: 35%;
          color: $color-neutral-500;
          font-weight: $fw-medium;
        }

        &.spec-value {
          color: $color-neutral-900;
        }
      }
    }

    .desc-paragraph {
      font-size: $fs-base;
      line-height: 1.7;
      color: $color-neutral-700;
    }

    .shipping-info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: $space-4;

      .shipping-card {
        padding: $space-4;
        background: $color-neutral-50;
        border-radius: $radius-lg;
        border: 1px solid $color-neutral-200;

        h4 {
          font-size: $fs-sm;
          font-weight: $fw-bold;
          margin-bottom: $space-2;
          color: $color-neutral-900;
        }

        p {
          font-size: $fs-xs;
          color: $color-neutral-600;
          line-height: 1.5;
        }
      }
    }
  }
}

.related-section {
  .section-title {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    margin-bottom: $space-6;
    color: $color-neutral-900;
  }

  .related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: $space-6;

    .related-card {
      text-decoration: none;
      color: inherit;
      border-radius: $radius-xl;
      overflow: hidden;
      transition: transform $transition-fast, box-shadow $transition-fast;

      &:hover {
        transform: translateY(-4px);
        box-shadow: $shadow-md;
      }

      .rel-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
      }

      .rel-body {
        padding: $space-3;

        .rel-brand {
          font-size: $fs-2xs;
          font-weight: $fw-bold;
          color: $color-primary-600;
        }

        .rel-title {
          font-size: $fs-sm;
          font-weight: $fw-bold;
          margin: $space-1 0;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .rel-price {
          font-size: $fs-base;
          font-weight: $fw-extrabold;
          color: $color-primary-700;
        }
      }
    }
  }
}
</style>
