<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

interface OrderItemDetail {
  name: string;
  sku: string;
  quantity: number;
  price: number;
  total: number;
}

interface OrderDetail {
  order_number: string;
  customer_name: string;
  customer_phone: string;
  delivery_address: string;
  delivery_zone: string;
  payment_method: string;
  subtotal_bdt: number;
  shipping_fee_bdt: number;
  total_bdt: number;
  items: OrderItemDetail[];
}

defineProps<{
  orderNumber: string;
  order?: OrderDetail | null;
}>();

const formatPrice = (amount: number): string => {
  return new Intl.NumberFormat('en-BD', {
    style: 'currency',
    currency: 'BDT',
    maximumFractionDigits: 0,
  }).format(amount).replace('BDT', '৳');
};

const getZoneLabel = (zone: string): string => {
  switch (zone) {
    case 'inside_dhaka':
      return 'ঢাকা সিটির ভিতরে (Inside Dhaka)';
    case 'dhaka_suburbs':
      return 'ঢাকা উপশহর (Dhaka Suburbs)';
    case 'outside_dhaka':
      return 'ঢাকার বাইরে সারা বাংলাদেশ (Outside Dhaka)';
    default:
      return zone;
  }
};
</script>

<template>
  <StorefrontLayout title="Order Confirmed - Thank You!">
    <Head title="Order Confirmed - Thank You!" />

    <div class="confirmed-page-container">
      <div class="confirmed-card card">
        <!-- Success Icon & Header -->
        <div class="success-header">
          <div class="celebrate-badge">🎉</div>
          <h1 class="success-title">ধন্যবাদ! আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে!</h1>
          <p class="success-subtitle">
            আপনার অর্ডারটি যাচাই ও প্যাকেজিংয়ের জন্য আমাদের ওয়্যারহাউসে পাঠানো হয়েছে। শীঘ্রই আমাদের প্রতিনিধি আপনার নম্বরে কল দিয়ে অর্ডারটি নিশ্চিত করতে পারেন।
          </p>

          <div class="order-number-banner">
            <span class="label">অর্ডার নম্বর (Order ID):</span>
            <span class="order-id">{{ orderNumber }}</span>
          </div>
        </div>

        <!-- Order Items & Price Summary -->
        <div v-if="order" class="order-details-grid">
          <div class="order-summary-box">
            <h3 class="box-title">📦 পণ্যের বিবরণ (Order Items)</h3>
            <div class="items-list">
              <div v-for="(item, idx) in order.items" :key="idx" class="item-row">
                <div class="item-info">
                  <span class="item-name">{{ item.name }}</span>
                  <span class="item-qty">পরিমাণ: {{ item.quantity }} টি</span>
                </div>
                <span class="item-price">{{ formatPrice(item.total) }}</span>
              </div>
            </div>

            <div class="totals-breakdown">
              <div class="breakdown-row">
                <span>পণ্যের মোট মূল্য (Subtotal):</span>
                <span>{{ formatPrice(order.subtotal_bdt) }}</span>
              </div>
              <div class="breakdown-row">
                <span>ডেলিভারি চার্জ (Delivery Fee):</span>
                <span>{{ order.shipping_fee_bdt === 0 ? 'ফ্রি' : formatPrice(order.shipping_fee_bdt) }}</span>
              </div>
              <div class="breakdown-row grand-total">
                <span>সর্বমোট প্রদেয় মূল্য (Total Payable):</span>
                <span class="total-amount">{{ formatPrice(order.total_bdt) }}</span>
              </div>
            </div>
          </div>

          <div class="delivery-details-box">
            <h3 class="box-title">📍 ডেলিভারি ও পেমেন্ট তথ্য</h3>
            <div class="info-list">
              <div class="info-item">
                <span class="info-label">গ্রাহকের নাম:</span>
                <span class="info-value">{{ order.customer_name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">মোবাইল নম্বর:</span>
                <span class="info-value font-mono">{{ order.customer_phone }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">ডেলিভারি এরিয়া:</span>
                <span class="info-value">{{ getZoneLabel(order.delivery_zone) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">সম্পূর্ণ ঠিকানা:</span>
                <span class="info-value">{{ order.delivery_address }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">পেমেন্ট পদ্ধতি:</span>
                <span class="info-badge">💵 ক্যাশ অন ডেলিভারি (পণ্য পেয়ে টাকা দিন)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Next Actions & Order Tracking -->
        <div class="action-footer">
          <Link :href="`/track-order?phone=${order?.customer_phone || ''}`" class="btn btn-outline-primary">
            🔍 অর্ডার ট্র্যাক করুন
          </Link>
          <Link href="/shop" class="btn btn-primary">
            🛍️ আরও কেনাকাটা করুন
          </Link>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.confirmed-page-container {
  max-width: 820px;
  margin: $space-8 auto;
  padding: 0 $space-4;
}

.confirmed-card {
  padding: $space-8;
  background-color: #ffffff;
  border-radius: $radius-2xl;
  border: 1px solid hsl(342, 40%, 92%);
  box-shadow: 0 10px 30px rgba(235, 40, 108, 0.06);
}

.success-header {
  text-align: center;
  margin-bottom: $space-8;

  .celebrate-badge {
    font-size: 3.5rem;
    margin-bottom: $space-3;
    animation: bounce 1s infinite alternate;
  }

  .success-title {
    font-size: $fs-2xl;
    font-weight: 700;
    color: hsl(342, 45%, 15%);
    font-family: $font-heading;
    margin-bottom: $space-2;
  }

  .success-subtitle {
    font-size: $fs-sm;
    color: $color-neutral-500;
    max-width: 600px;
    margin: 0 auto $space-5;
    line-height: 1.6;
  }

  .order-number-banner {
    display: inline-flex;
    align-items: center;
    gap: $space-2;
    padding: $space-2 $space-5;
    border-radius: $radius-full;
    background: $color-primary-50;
    border: 1px solid $color-primary-200;

    .label {
      font-size: $fs-xs;
      font-weight: 600;
      color: $color-primary-700;
    }

    .order-id {
      font-family: $font-mono;
      font-size: $fs-base;
      font-weight: 700;
      color: $color-primary-800;
    }
  }
}

.order-details-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: $space-6;
  margin-bottom: $space-8;

  @include media-up(md) {
    grid-template-columns: 1fr 1fr;
  }
}

.order-summary-box,
.delivery-details-box {
  background: hsl(342, 50%, 98.5%);
  border: 1px solid hsl(342, 35%, 92%);
  border-radius: $radius-xl;
  padding: $space-5;

  .box-title {
    font-size: $fs-base;
    font-weight: 700;
    color: hsl(342, 40%, 18%);
    margin-bottom: $space-4;
    padding-bottom: $space-2;
    border-bottom: 1px solid hsl(342, 30%, 90%);
  }
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: $space-3;
  margin-bottom: $space-4;

  .item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    font-size: $fs-sm;

    .item-info {
      display: flex;
      flex-direction: column;
      gap: 2px;

      .item-name {
        font-weight: 600;
        color: $color-neutral-800;
      }

      .item-qty {
        font-size: $fs-xs;
        color: $color-neutral-500;
      }
    }

    .item-price {
      font-weight: 700;
      color: $color-primary-700;
    }
  }
}

.totals-breakdown {
  border-top: 1px solid hsl(342, 30%, 90%);
  padding-top: $space-3;
  display: flex;
  flex-direction: column;
  gap: $space-2;
  font-size: $fs-sm;

  .breakdown-row {
    display: flex;
    justify-content: space-between;
    color: $color-neutral-500;

    &.grand-total {
      margin-top: $space-2;
      padding-top: $space-2;
      border-top: 1px dashed hsl(342, 35%, 85%);
      font-size: $fs-base;
      font-weight: 700;
      color: hsl(342, 45%, 15%);

      .total-amount {
        color: $color-primary-600;
        font-size: $fs-lg;
      }
    }
  }
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: $space-3;
  font-size: $fs-sm;

  .info-item {
    display: flex;
    flex-direction: column;
    gap: 2px;

    .info-label {
      font-size: $fs-xs;
      color: $color-neutral-500;
      font-weight: 500;
    }

    .info-value {
      font-weight: 600;
      color: $color-neutral-800;
      line-height: 1.4;
    }

    .info-badge {
      display: inline-block;
      font-size: $fs-xs;
      font-weight: 600;
      color: $color-success;
      background: hsla(142, 71%, 45%, 0.1);
      padding: 3px 8px;
      border-radius: $radius-md;
      margin-top: 2px;
    }
  }
}

.action-footer {
  display: flex;
  justify-content: center;
  gap: $space-4;
  flex-wrap: wrap;
  padding-top: $space-4;
  border-top: 1px solid hsl(342, 35%, 92%);

  .btn {
    padding: $space-3 $space-6;
    font-size: $fs-sm;
    font-weight: 600;
    border-radius: $radius-lg;
    text-decoration: none;
  }
}

@keyframes bounce {
  from {
    transform: translateY(0);
  }
  to {
    transform: translateY(-8px);
  }
}
</style>
