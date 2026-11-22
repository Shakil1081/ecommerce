<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AccountShell from '@/Components/Account/AccountShell.vue';
import OrderStatusBadge from '@/Components/Account/OrderStatusBadge.vue';
import { formatBdt, orderStatusLabel, paymentMethodLabel, returnReasonLabel } from '@/Utils/format';

interface OrderItem {
  id: number;
  product_name: string;
  sku: string;
  attributes: Record<string, string>;
  quantity: number;
  unit_price_bdt: string;
  subtotal_bdt: string;
  returnable_quantity: number;
}

interface TimelineEntry {
  status: string;
  note: string | null;
  at: string | null;
}

interface ReturnRequest {
  rma_number: string;
  status: string;
  reason_category: string;
  refund_status: string;
  refund_amount_bdt: string;
  created_at: string | null;
  items: { item_name: string; quantity: number }[];
}

const props = defineProps<{
  order: {
    order_number: string;
    status: string;
    payment_method: string;
    payment_status: string;
    total_bdt: string;
    placed_at: string | null;
    customer_name: string;
    customer_phone: string;
    delivery_address: string;
    district: string | null;
    subtotal_bdt: string;
    discount_amount_bdt: string;
    shipping_fee_bdt: string;
    advance_paid_bdt: string;
    courier_tracking_code: string | null;
    awaiting_otp: boolean;
    items: OrderItem[];
    timeline: TimelineEntry[];
  };
  returns: ReturnRequest[];
  returnPolicy: {
    can_request: boolean;
    window_days: number;
    window_ends_at: string | null;
    reasons: string[];
  };
}>();

const showReturnForm = ref(false);

const returnForm = useForm({
  reason: props.returnPolicy.reasons[0] ?? 'defective',
  notes: '',
  items: props.order.items.map((item) => ({ order_item_id: item.id, quantity: 0 })),
});

const itemsById = computed(() => new Map(props.order.items.map((item) => [item.id, item])));
const itemById = (id: number): OrderItem => itemsById.value.get(id) as OrderItem;

const selectedUnits = computed(() => returnForm.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

const submitReturn = () => {
  returnForm.post(`/account/orders/${props.order.order_number}/returns`, {
    preserveScroll: true,
    onSuccess: () => {
      showReturnForm.value = false;
      returnForm.reset();
    },
  });
};

const hasDiscount = computed(() => Number(props.order.discount_amount_bdt) > 0);
const hasAdvance = computed(() => Number(props.order.advance_paid_bdt) > 0);

const rmaStatusLabel = (status: string): string => ({
  requested: 'Requested',
  approved: 'Approved',
  received: 'Received',
  inspected: 'Inspected',
  completed: 'Completed',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}[status] ?? status);
</script>

<template>
  <StorefrontLayout :title="`Order ${order.order_number}`">
    <SeoHead :title="`Order ${order.order_number}`" :noindex="true" />

    <AccountShell :heading="`Order ${order.order_number}`" :subheading="`Placed ${order.placed_at}`">
      <Link href="/account/orders" class="back-link">← All orders</Link>

      <div v-if="order.awaiting_otp" class="sf-alert is-info">
        This order is waiting for phone verification.
        <Link :href="`/orders/${order.order_number}/verify`">Enter your SMS code</Link>
      </div>

      <div class="summary-row card">
        <div>
          <span class="label">Status</span>
          <OrderStatusBadge :status="order.status" />
        </div>
        <div>
          <span class="label">Payment</span>
          <span class="value">{{ paymentMethodLabel(order.payment_method) }}</span>
        </div>
        <div>
          <span class="label">Total</span>
          <span class="value strong">{{ formatBdt(order.total_bdt) }}</span>
        </div>
        <div v-if="order.courier_tracking_code">
          <span class="label">Tracking code</span>
          <span class="value mono">{{ order.courier_tracking_code }}</span>
        </div>
      </div>

      <div class="detail-grid">
        <div class="card panel">
          <h2>Items</h2>
          <ul class="items">
            <li v-for="item in order.items" :key="item.id" class="item">
              <div class="item-info">
                <strong>{{ item.product_name }}</strong>
                <span class="item-meta">
                  <template v-for="(value, key) in item.attributes" :key="key">{{ key }}: {{ value }} · </template>
                  Qty {{ item.quantity }} × {{ formatBdt(item.unit_price_bdt) }}
                </span>
              </div>
              <span class="item-total">{{ formatBdt(item.subtotal_bdt) }}</span>
            </li>
          </ul>

          <dl class="totals">
            <dt>Subtotal</dt><dd>{{ formatBdt(order.subtotal_bdt) }}</dd>
            <template v-if="hasDiscount">
              <dt>Discount</dt><dd>− {{ formatBdt(order.discount_amount_bdt) }}</dd>
            </template>
            <dt>Delivery</dt><dd>{{ formatBdt(order.shipping_fee_bdt) }}</dd>
            <template v-if="hasAdvance">
              <dt>Advance paid</dt><dd>{{ formatBdt(order.advance_paid_bdt) }}</dd>
            </template>
            <dt class="grand">Total</dt><dd class="grand">{{ formatBdt(order.total_bdt) }}</dd>
          </dl>
        </div>

        <div class="side-col">
          <div class="card panel">
            <h2>Delivery address</h2>
            <p class="address">
              <strong>{{ order.customer_name }}</strong><br>
              {{ order.customer_phone }}<br>
              {{ order.delivery_address }}<template v-if="order.district">, {{ order.district }}</template>
            </p>
          </div>

          <div class="card panel">
            <h2>Order timeline</h2>
            <ol class="timeline">
              <li v-for="(entry, index) in order.timeline" :key="index">
                <span class="dot" aria-hidden="true" />
                <div>
                  <strong>{{ entry.note || orderStatusLabel(entry.status) }}</strong>
                  <span class="when">{{ entry.at }}</span>
                </div>
              </li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Returns -->
      <div class="card panel returns-panel">
        <div class="returns-header">
          <h2>Returns</h2>
          <button
            v-if="returnPolicy.can_request && !showReturnForm"
            type="button"
            class="btn btn-outline btn-sm"
            @click="showReturnForm = true"
          >
            Request a return
          </button>
        </div>

        <p v-if="returnPolicy.can_request" class="policy-note">
          You can request a return until <strong>{{ returnPolicy.window_ends_at }}</strong>.
          See our <Link href="/returns">Return &amp; Refund Policy</Link>.
        </p>
        <p v-else-if="order.status === 'delivered'" class="policy-note">
          The {{ returnPolicy.window_days }}-day return window for this order has ended, or all items are already in a return.
          Need help? <Link href="/contact">Contact us</Link>.
        </p>
        <p v-else class="policy-note">
          Returns can be requested after the order is delivered.
        </p>

        <form v-if="showReturnForm" class="return-form" @submit.prevent="submitReturn">
          <p class="sf-field-label">Which items do you want to return?</p>
          <template v-for="(row, index) in returnForm.items" :key="row.order_item_id">
            <div v-if="itemById(row.order_item_id).returnable_quantity > 0" class="return-item">
              <span class="name">{{ itemById(row.order_item_id).product_name }}</span>
              <label class="qty">
                Qty
                <select v-model.number="returnForm.items[index].quantity" class="sf-input qty-select">
                  <option v-for="n in itemById(row.order_item_id).returnable_quantity + 1" :key="n" :value="n - 1">{{ n - 1 }}</option>
                </select>
              </label>
            </div>
          </template>
          <span v-if="returnForm.errors.items" class="sf-field-error" role="alert">{{ returnForm.errors.items }}</span>

          <div class="sf-field reason-field">
            <label class="sf-field-label" for="reason">Reason</label>
            <select id="reason" v-model="returnForm.reason" class="sf-input">
              <option v-for="reason in returnPolicy.reasons" :key="reason" :value="reason">{{ returnReasonLabel(reason) }}</option>
            </select>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="notes">Tell us more (optional)</label>
            <textarea id="notes" v-model="returnForm.notes" class="sf-input" maxlength="1000" placeholder="What's wrong with the product?" />
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-ghost" @click="showReturnForm = false">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="returnForm.processing || selectedUnits === 0">
              {{ returnForm.processing ? 'Submitting…' : 'Submit return request' }}
            </button>
          </div>
        </form>

        <ul v-if="returns.length" class="rma-list">
          <li v-for="rma in returns" :key="rma.rma_number" class="rma">
            <div class="rma-top">
              <strong class="mono">{{ rma.rma_number }}</strong>
              <span class="rma-status">{{ rmaStatusLabel(rma.status) }}</span>
            </div>
            <span class="rma-meta">
              {{ rma.created_at }} · {{ returnReasonLabel(rma.reason_category) }} ·
              <template v-for="(item, i) in rma.items" :key="i">{{ item.item_name }} × {{ item.quantity }}<template v-if="i < rma.items.length - 1">, </template></template>
            </span>
          </li>
        </ul>
      </div>
    </AccountShell>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.back-link {
  display: inline-block;
  margin-bottom: $space-4;
  font-size: $fs-sm;
  color: $color-neutral-600;
  text-decoration: none;
}

.summary-row {
  display: flex;
  flex-wrap: wrap;
  gap: $space-6;
  padding: $space-4 $space-5;
  margin-bottom: $space-6;

  > div {
    display: flex;
    flex-direction: column;
    gap: $space-1;
  }

  .label {
    font-size: $fs-xs;
    color: $color-neutral-500;
  }

  .value {
    font-size: $fs-sm;
    color: $color-neutral-900;

    &.strong {
      font-weight: $fw-bold;
    }
  }
}

.mono {
  font-family: $font-mono;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: $space-6;
  margin-bottom: $space-6;

  @media (max-width: 960px) {
    grid-template-columns: 1fr;
  }
}

.side-col {
  display: flex;
  flex-direction: column;
  gap: $space-6;
}

.panel {
  padding: $space-5;

  h2 {
    font-size: $fs-base;
    font-weight: $fw-semibold;
    color: $color-neutral-900;
    margin-bottom: $space-4;
  }
}

.items .item {
  @include flex-between;
  align-items: flex-start;
  gap: $space-4;
  padding: $space-3 0;
  border-bottom: 1px solid $color-neutral-100;

  .item-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: $fs-sm;
    color: $color-neutral-900;
  }

  .item-meta {
    font-size: $fs-xs;
    color: $color-neutral-500;
  }

  .item-total {
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    white-space: nowrap;
  }
}

.totals {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: $space-2;
  margin-top: $space-4;
  font-size: $fs-sm;
  color: $color-neutral-600;

  dd {
    text-align: right;
    color: $color-neutral-900;
  }

  .grand {
    font-weight: $fw-bold;
    color: $color-neutral-900;
    font-size: $fs-base;
    padding-top: $space-2;
    border-top: 1px solid $color-neutral-200;
  }
}

.address {
  font-size: $fs-sm;
  color: $color-neutral-700;
  line-height: $lh-relaxed;
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: $space-4;

  li {
    display: flex;
    gap: $space-3;
    font-size: $fs-sm;
    color: $color-neutral-900;
  }

  .dot {
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    margin-top: 5px;
    border-radius: $radius-full;
    background: $color-primary-500;
  }

  .when {
    display: block;
    font-size: $fs-xs;
    color: $color-neutral-500;
  }
}

.returns-header {
  @include flex-between;
  margin-bottom: $space-2;

  h2 {
    margin-bottom: 0;
  }
}

.policy-note {
  font-size: $fs-sm;
  color: $color-neutral-600;

  a {
    color: $color-primary-600;
  }
}

.return-form {
  margin-top: $space-5;
  padding-top: $space-5;
  border-top: 1px solid $color-neutral-200;
}

.return-item {
  @include flex-between;
  gap: $space-4;
  padding: $space-2 0;
  font-size: $fs-sm;

  .qty {
    display: flex;
    align-items: center;
    gap: $space-2;
    color: $color-neutral-500;
  }

  .qty-select {
    width: 76px;
    padding: 6px 10px;
  }
}

.reason-field {
  margin-top: $space-4;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: $space-3;
  flex-wrap: wrap;
}

.rma-list {
  margin-top: $space-5;
  display: flex;
  flex-direction: column;
  gap: $space-3;
}

.rma {
  padding: $space-3 $space-4;
  border-radius: $radius-md;
  background: $color-neutral-50;
  border: 1px solid $color-neutral-200;

  .rma-top {
    @include flex-between;
    font-size: $fs-sm;
  }

  .rma-status {
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    color: $color-primary-700;
  }

  .rma-meta {
    font-size: $fs-xs;
    color: $color-neutral-500;
  }
}
</style>
