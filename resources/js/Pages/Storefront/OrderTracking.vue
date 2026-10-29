<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

interface Milestone {
  step: number;
  key: string;
  title: string;
  title_bn: string;
  description: string;
  description_bn: string;
  timestamp: string | null;
  is_completed: boolean;
  is_current: boolean;
}

interface CourierInfo {
  courier_code: string;
  courier_name: string;
  tracking_code: string;
  consignment_id: string;
  status: string;
  status_label: string;
  tracking_url: string | null;
  dispatched_at: string | null;
}

interface OrderItem {
  id: number;
  product_title: string;
  variant_sku: string;
  attributes: Record<string, string>;
  quantity: number;
  unit_price_bdt: string;
  total_price_bdt: string;
}

interface Financials {
  subtotal_bdt: string;
  discount_amount_bdt: string;
  shipping_fee_bdt: string;
  total_bdt: string;
  advance_paid_bdt: string;
  payable_cod_amount_bdt: string;
  payment_method: string;
  payment_method_label: string;
  payment_status: string;
  coupon_code: string | null;
}

interface ShippingAddress {
  customer_name: string;
  customer_phone_masked: string;
  delivery_zone: string;
  delivery_address: string;
  division: string | null;
  division_bn: string | null;
  district: string | null;
  district_bn: string | null;
  thana: string | null;
  thana_bn: string | null;
}

interface OrderSummary {
  id: number;
  ulid: string;
  order_number: string;
  status: string;
  status_label: string;
  status_label_bn: string;
  status_color: string;
  created_at: string;
  created_at_human: string;
  mushak_6_3_number: string | null;
}

interface TrackingData {
  order: OrderSummary;
  milestones: Milestone[];
  courier: CourierInfo | null;
  items: OrderItem[];
  financials: Financials;
  shipping_address: ShippingAddress;
}

const props = defineProps<{
  tracking?: TrackingData | null;
  query?: {
    order_number?: string;
    phone?: string;
  } | null;
  errorMessage?: string | null;
}>();

const form = useForm({
  order_number: props.query?.order_number || '',
  phone: props.query?.phone || '',
});

const submitTrackingSearch = () => {
  form.get('/track-order', {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <StorefrontLayout title="Live Order Tracking Portal | রিয়েল-টাইম অর্ডার ট্র্যাকিং">
    <Head>
      <meta name="description" content="Track your package delivery status in real-time across Bangladesh using your Order Number and Mobile Phone.">
    </Head>

    <div class="tracking-portal container">
      <!-- Search Banner -->
      <section class="search-banner">
        <div class="banner-content">
          <span class="badge badge-primary">24/7 Self-Service Tracking</span>
          <h1 class="portal-title">Track Your Order / অর্ডার ট্র্যাক করুন</h1>
          <p class="portal-subtitle">
            Enter your Order Number and Mobile Phone to see real-time packaging, courier dispatch, and live delivery updates.
          </p>

          <form class="tracking-form" @submit.prevent="submitTrackingSearch">
            <div class="form-row">
              <div class="form-group">
                <label for="order_number">Order Number / অর্ডার নম্বর</label>
                <input
                  id="order_number"
                  v-model="form.order_number"
                  type="text"
                  placeholder="e.g. ORD-20261029-XXXX"
                  required
                  class="form-control"
                >
              </div>

              <div class="form-group">
                <label for="phone">Phone Number / মোবাইল নম্বর</label>
                <input
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  placeholder="e.g. 017XXXXXXXX"
                  required
                  class="form-control"
                >
              </div>

              <div class="form-group form-action">
                <button
                  type="submit"
                  class="btn btn-primary track-btn"
                  :disabled="form.processing"
                >
                  <span v-if="form.processing">Searching...</span>
                  <span v-else>🔍 Track Order</span>
                </button>
              </div>
            </div>
          </form>

          <!-- Error Alert -->
          <div v-if="errorMessage" class="alert alert-danger">
            <span>⚠️ {{ errorMessage }}</span>
          </div>
        </div>
      </section>

      <!-- Tracking Results Section -->
      <section v-if="tracking" class="tracking-results">
        <!-- Order Header Card -->
        <div class="order-header-card">
          <div class="header-main">
            <div>
              <span class="order-label">Tracking Order</span>
              <h2 class="order-number">{{ tracking.order.order_number }}</h2>
              <span class="order-date">Placed on {{ tracking.order.created_at_human }}</span>
            </div>
            <div class="status-badge-container">
              <span :class="['status-pill', `status-${tracking.order.status_color}`]">
                {{ tracking.order.status_label }} ({{ tracking.order.status_label_bn }})
              </span>
              <span v-if="tracking.order.mushak_6_3_number" class="mushak-pill">
                NBR Mushak 6.3: #{{ tracking.order.mushak_6_3_number }}
              </span>
            </div>
          </div>
        </div>

        <!-- Milestone Stepper Timeline -->
        <div class="timeline-card">
          <h3 class="section-title">Delivery Progress / ডেলিভারি অগ্রগতি</h3>

          <div class="stepper-wrapper">
            <div
              v-for="milestone in tracking.milestones"
              :key="milestone.step"
              :class="[
                'step-item',
                { 'is-completed': milestone.is_completed },
                { 'is-current': milestone.is_current }
              ]"
            >
              <div class="step-marker">
                <span v-if="milestone.is_completed" class="check-icon">✓</span>
                <span v-else class="step-num">{{ milestone.step }}</span>
              </div>
              <div class="step-details">
                <h4 class="step-title">
                  {{ milestone.title }}
                  <span class="step-title-bn">({{ milestone.title_bn }})</span>
                </h4>
                <p class="step-desc">{{ milestone.description }}</p>
                <span v-if="milestone.timestamp" class="step-time">
                  🕒 {{ milestone.timestamp }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Courier Consignment Card (If Dispatched) -->
        <div v-if="tracking.courier" class="courier-card">
          <div class="courier-content">
            <div class="courier-icon">🚚</div>
            <div class="courier-info">
              <span class="courier-label">Courier Logistics Partner</span>
              <h4 class="courier-name">{{ tracking.courier.courier_name }}</h4>
              <div class="courier-meta">
                <span v-if="tracking.courier.tracking_code" class="tracking-pill">
                  Tracking Code: <strong>{{ tracking.courier.tracking_code }}</strong>
                </span>
                <span v-if="tracking.courier.consignment_id" class="tracking-pill">
                  Consignment ID: <strong>{{ tracking.courier.consignment_id }}</strong>
                </span>
                <span class="status-pill status-primary">
                  {{ tracking.courier.status_label }}
                </span>
              </div>
            </div>
            <div v-if="tracking.courier.tracking_url" class="courier-action">
              <a
                :href="tracking.courier.tracking_url"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-secondary live-courier-btn"
              >
                🔗 Track on {{ tracking.courier.courier_name }} &rarr;
              </a>
            </div>
          </div>
        </div>

        <!-- Details Grid (Shipping & Financials) -->
        <div class="details-grid">
          <!-- Delivery Address Card -->
          <div class="card detail-card">
            <div class="card-header">
              <h4 class="card-title">📍 Shipping Destination</h4>
            </div>
            <div class="card-body">
              <div class="info-row">
                <span class="info-label">Recipient Name:</span>
                <span class="info-value">{{ tracking.shipping_address.customer_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Contact Phone:</span>
                <span class="info-value font-mono">{{ tracking.shipping_address.customer_phone_masked }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Delivery Zone:</span>
                <span class="info-value badge badge-neutral">{{ tracking.shipping_address.delivery_zone }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Address:</span>
                <span class="info-value">{{ tracking.shipping_address.delivery_address }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">District & Thana:</span>
                <span class="info-value">
                  {{ tracking.shipping_address.thana }}, {{ tracking.shipping_address.district }}
                </span>
              </div>
            </div>
          </div>

          <!-- Payment & Financial Summary -->
          <div class="card detail-card">
            <div class="card-header">
              <h4 class="card-title">💳 Payment & Invoicing</h4>
            </div>
            <div class="card-body">
              <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value font-medium">{{ tracking.financials.payment_method_label }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Payment Status:</span>
                <span class="info-value status-pill status-primary">
                  {{ tracking.financials.payment_status }}
                </span>
              </div>
              <div class="info-divider" />
              <div class="info-row">
                <span class="info-label">Subtotal:</span>
                <span class="info-value">৳{{ tracking.financials.subtotal_bdt }}</span>
              </div>
              <div v-if="Number(tracking.financials.discount_amount_bdt) > 0" class="info-row text-success">
                <span class="info-label">Discount:</span>
                <span class="info-value">-৳{{ tracking.financials.discount_amount_bdt }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Shipping Delivery Charge:</span>
                <span class="info-value">৳{{ tracking.financials.shipping_fee_bdt }}</span>
              </div>
              <div class="info-row total-row">
                <span class="info-label">Total Payable:</span>
                <span class="info-value total-price">৳{{ tracking.financials.total_bdt }}</span>
              </div>
              <div v-if="Number(tracking.financials.advance_paid_bdt) > 0" class="info-row">
                <span class="info-label">Advance Paid:</span>
                <span class="info-value text-success">-৳{{ tracking.financials.advance_paid_bdt }}</span>
              </div>
              <div class="info-row cod-payable-row">
                <span class="info-label">Remaining COD on Delivery:</span>
                <span class="info-value cod-amount">৳{{ tracking.financials.payable_cod_amount_bdt }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Items Ordered Card -->
        <div class="card items-card">
          <div class="card-header">
            <h4 class="card-title">📦 Ordered Items ({{ tracking.items.length }})</h4>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Product Item</th>
                    <th>SKU / Attributes</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total (BDT)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in tracking.items" :key="item.id">
                    <td>
                      <span class="item-title">{{ item.product_title }}</span>
                    </td>
                    <td>
                      <span class="font-mono text-muted">{{ item.variant_sku }}</span>
                      <div v-if="Object.keys(item.attributes).length" class="item-attrs">
                        <span v-for="(val, key) in item.attributes" :key="key" class="attr-tag">
                          {{ key }}: {{ val }}
                        </span>
                      </div>
                    </td>
                    <td class="text-center">{{ item.quantity }}</td>
                    <td class="text-right">৳{{ item.unit_price_bdt }}</td>
                    <td class="text-right font-medium">৳{{ item.total_price_bdt }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- Help / Explanatory Card (When no order loaded) -->
      <section v-else-if="!errorMessage" class="help-section">
        <div class="help-card card">
          <div class="card-body">
            <h3 class="help-title">Need help finding your order? / অর্ডার খুঁজে পাচ্ছেন না?</h3>
            <p class="help-desc">
              Your Order Number was sent to your registered phone number via SMS and WhatsApp right after checkout.
              It starts with <code>ORD-</code> followed by the date and sequence number.
            </p>
            <div class="help-features">
              <div class="feature-item">
                <span class="feature-icon">⚡</span>
                <div>
                  <strong>Live Tracking</strong>
                  <p>Real-time synchronization with Steadfast, Pathao, RedX, and Paperfly couriers.</p>
                </div>
              </div>
              <div class="feature-item">
                <span class="feature-icon">🛡️</span>
                <div>
                  <strong>Protected Privacy</strong>
                  <p>Customer contact information is securely masked to safeguard your data.</p>
                </div>
              </div>
              <div class="feature-item">
                <span class="feature-icon">🧾</span>
                <div>
                  <strong>Statutory Mushak 6.3</strong>
                  <p>View your NBR VAT tax invoice serial number directly upon delivery.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.tracking-portal {
  padding: $space-8 0 $space-16 0;
}

.search-banner {
  background: linear-gradient(135deg, $color-neutral-900 0%, $color-primary-950 100%);
  color: $color-neutral-0;
  border-radius: $radius-xl;
  padding: $space-10 $space-8;
  margin-bottom: $space-8;
  box-shadow: $shadow-lg;

  .banner-content {
    max-width: 820px;
    margin: 0 auto;
    text-align: center;
  }

  .portal-title {
    color: $color-neutral-0;
    font-size: $fs-3xl;
    font-weight: $fw-bold;
    margin: $space-3 0 $space-2 0;
  }

  .portal-subtitle {
    color: $color-neutral-300;
    font-size: $fs-base;
    margin-bottom: $space-6;
    line-height: $lh-relaxed;
  }
}

.tracking-form {
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: $radius-lg;
  padding: $space-5;
  margin-bottom: $space-4;

  .form-row {
    display: flex;
    gap: $space-4;
    align-items: flex-end;

    @media (max-width: $bp-md) {
      flex-direction: column;
      align-items: stretch;
    }
  }

  .form-group {
    flex: 1;
    text-align: left;

    label {
      display: block;
      color: $color-neutral-200;
      font-size: $fs-xs;
      font-weight: $fw-medium;
      margin-bottom: $space-1;
    }

    .form-control {
      width: 100%;
      background: $color-neutral-0;
      color: $color-neutral-900;
      border: 1px solid $color-neutral-300;
      border-radius: $radius-md;
      padding: $space-2 $space-3;
      font-size: $fs-sm;

      &:focus {
        outline: none;
        border-color: $color-primary-500;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
      }
    }
  }

  .form-action {
    flex: 0 0 auto;

    .track-btn {
      width: 100%;
      height: 40px;
      padding: 0 $space-6;
      font-weight: $fw-semibold;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: $space-2;
    }
  }
}

.alert-danger {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid $color-danger;
  color: #fca5a5;
  padding: $space-3 $space-4;
  border-radius: $radius-md;
  font-size: $fs-sm;
  margin-top: $space-4;
}

.order-header-card {
  background: $color-neutral-0;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-lg;
  padding: $space-6;
  margin-bottom: $space-6;
  box-shadow: $shadow-sm;

  .header-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: $space-4;
  }

  .order-label {
    font-size: $fs-xs;
    text-transform: uppercase;
    color: $color-neutral-500;
    font-weight: $fw-semibold;
  }

  .order-number {
    font-size: $fs-2xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    font-family: $font-mono;
    margin: $space-1 0;
  }

  .order-date {
    font-size: $fs-sm;
    color: $color-neutral-500;
  }

  .status-badge-container {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: $space-2;

    @media (max-width: $bp-md) {
      align-items: flex-start;
    }
  }

  .status-pill {
    padding: $space-1 $space-3;
    border-radius: $radius-full;
    font-size: $fs-xs;
    font-weight: $fw-semibold;

    &.status-primary { background: $color-primary-100; color: $color-primary-800; }
    &.status-info { background: $color-secondary-100; color: $color-secondary-800; }
    &.status-success { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
    &.status-warning { background: hsl(38, 92%, 90%); color: hsl(38, 92%, 25%); }
    &.status-danger { background: hsl(0, 84%, 92%); color: hsl(0, 84%, 30%); }
  }

  .mushak-pill {
    font-size: $fs-2xs;
    background: $color-neutral-100;
    color: $color-neutral-700;
    padding: 2px $space-2;
    border-radius: $radius-sm;
    font-family: $font-mono;
  }
}

.timeline-card {
  background: $color-neutral-0;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-lg;
  padding: $space-6;
  margin-bottom: $space-6;
  box-shadow: $shadow-sm;

  .section-title {
    font-size: $fs-lg;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    margin-bottom: $space-6;
  }

  .stepper-wrapper {
    display: flex;
    justify-content: space-between;
    position: relative;
    gap: $space-2;

    @media (max-width: $bp-lg) {
      flex-direction: column;
      gap: $space-6;
    }
  }

  .step-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;

    @media (max-width: $bp-lg) {
      flex-direction: row;
      align-items: flex-start;
      text-align: left;
      gap: $space-4;
    }

    .step-marker {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: $color-neutral-200;
      color: $color-neutral-600;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: $fw-bold;
      font-size: $fs-sm;
      margin-bottom: $space-3;
      border: 3px solid $color-neutral-0;
      box-shadow: 0 0 0 1px $color-neutral-300;
      z-index: 2;
    }

    &.is-completed {
      .step-marker {
        background: $color-primary-500;
        color: $color-neutral-0;
        box-shadow: 0 0 0 1px $color-primary-500;
      }
      .step-title {
        color: $color-neutral-900;
        font-weight: $fw-semibold;
      }
    }

    &.is-current {
      .step-marker {
        background: $color-secondary-500;
        color: $color-neutral-0;
        animation: pulse 2s infinite;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
      }
      .step-title {
        color: $color-secondary-600;
        font-weight: $fw-bold;
      }
    }

    .step-title {
      font-size: $fs-sm;
      color: $color-neutral-600;
      margin-bottom: 2px;

      .step-title-bn {
        display: block;
        font-size: $fs-xs;
        color: $color-neutral-400;
      }
    }

    .step-desc {
      font-size: $fs-2xs;
      color: $color-neutral-500;
      max-width: 160px;
      line-height: $lh-tight;
      margin-bottom: $space-1;

      @media (max-width: $bp-lg) {
        max-width: none;
      }
    }

    .step-time {
      font-size: $fs-2xs;
      color: $color-primary-700;
      font-weight: $fw-medium;
      background: $color-primary-50;
      padding: 1px $space-2;
      border-radius: $radius-sm;
    }
  }
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

.courier-card {
  background: linear-gradient(135deg, hsl(214, 95%, 96%) 0%, hsl(160, 75%, 96%) 100%);
  border: 1px solid $color-secondary-200;
  border-radius: $radius-lg;
  padding: $space-5;
  margin-bottom: $space-6;

  .courier-content {
    display: flex;
    align-items: center;
    gap: $space-4;
    flex-wrap: wrap;
  }

  .courier-icon {
    font-size: 2.5rem;
    line-height: 1;
  }

  .courier-info {
    flex: 1;

    .courier-label {
      font-size: $fs-xs;
      text-transform: uppercase;
      color: $color-secondary-700;
      font-weight: $fw-semibold;
    }

    .courier-name {
      font-size: $fs-xl;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 2px 0 $space-2 0;
    }

    .courier-meta {
      display: flex;
      flex-wrap: wrap;
      gap: $space-2;
      align-items: center;
    }

    .tracking-pill {
      font-size: $fs-xs;
      background: $color-neutral-0;
      border: 1px solid $color-neutral-300;
      padding: 2px $space-2;
      border-radius: $radius-sm;
      font-family: $font-mono;
    }
  }

  .live-courier-btn {
    font-weight: $fw-semibold;
    padding: $space-2 $space-4;
  }
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: $space-6;
  margin-bottom: $space-6;

  @media (max-width: $bp-md) {
    grid-template-columns: 1fr;
  }

  .detail-card {
    background: $color-neutral-0;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-lg;
    box-shadow: $shadow-sm;

    .card-header {
      padding: $space-4 $space-5;
      border-bottom: 1px solid $color-neutral-200;
      background: $color-neutral-50;

      .card-title {
        font-size: $fs-base;
        font-weight: $fw-bold;
        color: $color-neutral-900;
        margin: 0;
      }
    }

    .card-body {
      padding: $space-5;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: $space-3;
      font-size: $fs-sm;

      &:last-child {
        margin-bottom: 0;
      }

      .info-label {
        color: $color-neutral-500;
      }

      .info-value {
        color: $color-neutral-800;
        text-align: right;
        max-width: 60%;
      }
    }

    .info-divider {
      height: 1px;
      background: $color-neutral-200;
      margin: $space-4 0;
    }

    .total-row {
      font-weight: $fw-bold;
      font-size: $fs-base;

      .total-price {
        color: $color-primary-600;
        font-size: $fs-lg;
      }
    }

    .cod-payable-row {
      background: hsl(38, 92%, 95%);
      border: 1px solid hsl(38, 92%, 75%);
      padding: $space-2 $space-3;
      border-radius: $radius-md;
      margin-top: $space-2;

      .cod-amount {
        color: hsl(38, 92%, 25%);
        font-weight: $fw-extrabold;
        font-size: $fs-base;
      }
    }
  }
}

.items-card {
  background: $color-neutral-0;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-lg;
  box-shadow: $shadow-sm;

  .card-header {
    padding: $space-4 $space-5;
    border-bottom: 1px solid $color-neutral-200;
    background: $color-neutral-50;

    .card-title {
      font-size: $fs-base;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 0;
    }
  }

  .table {
    width: 100%;
    border-collapse: collapse;

    th {
      background: $color-neutral-50;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: $color-neutral-600;
      text-transform: uppercase;
      padding: $space-3 $space-5;
      border-bottom: 1px solid $color-neutral-200;
    }

    td {
      padding: $space-4 $space-5;
      font-size: $fs-sm;
      border-bottom: 1px solid $color-neutral-100;
      vertical-align: middle;
    }

    tr:last-child td {
      border-bottom: none;
    }
  }

  .item-title {
    font-weight: $fw-medium;
    color: $color-neutral-900;
  }

  .item-attrs {
    display: flex;
    gap: $space-1;
    margin-top: $space-1;

    .attr-tag {
      font-size: $fs-2xs;
      background: $color-neutral-100;
      color: $color-neutral-600;
      padding: 1px $space-2;
      border-radius: $radius-sm;
    }
  }
}

.help-section {
  .help-card {
    background: $color-neutral-0;
    border: 1px dashed $color-neutral-300;
    border-radius: $radius-lg;
    padding: $space-6;
    text-align: center;
  }

  .help-title {
    font-size: $fs-xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    margin-bottom: $space-2;
  }

  .help-desc {
    font-size: $fs-sm;
    color: $color-neutral-600;
    max-width: 620px;
    margin: 0 auto $space-8 auto;
    line-height: $lh-relaxed;

    code {
      background: $color-neutral-100;
      padding: 2px $space-2;
      border-radius: $radius-sm;
      font-family: $font-mono;
      color: $color-primary-700;
    }
  }

  .help-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: $space-6;
    text-align: left;

    @media (max-width: $bp-md) {
      grid-template-columns: 1fr;
    }

    .feature-item {
      display: flex;
      gap: $space-3;

      .feature-icon {
        font-size: 1.5rem;
      }

      strong {
        display: block;
        font-size: $fs-sm;
        color: $color-neutral-900;
        margin-bottom: 2px;
      }

      p {
        font-size: $fs-xs;
        color: $color-neutral-500;
        line-height: $lh-normal;
        margin: 0;
      }
    }
  }
}
</style>
