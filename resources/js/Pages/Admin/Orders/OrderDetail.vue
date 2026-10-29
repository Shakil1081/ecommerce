<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface OrderItem {
  id: number;
  product_name: string;
  sku: string;
  attributes: Record<string, string> | null;
  quantity: number;
  unit_price_bdt: string;
  tier_savings_bdt: string;
  subtotal_bdt: string;
  unit_cogs_bdt: string;
}

interface StatusHistory {
  id: number;
  from_status: string | null;
  to_status: string;
  reason: string | null;
  created_at: string;
  changed_by_user?: {
    id: number;
    name: string;
  } | null;
}

interface OrderDetailRecord {
  id: number;
  ulid: string;
  order_number: string;
  status: string;
  payment_status: string;
  payment_method: string;
  customer_name: string;
  customer_phone: string;
  customer_email: string | null;
  delivery_zone: string;
  delivery_address: string;
  customer_notes: string | null;
  subtotal_bdt: string;
  discount_amount_bdt: string;
  shipping_fee_bdt: string;
  total_bdt: string;
  advance_paid_bdt: string;
  payable_cod_amount_bdt: string;
  coupon_code: string | null;
  fraud_risk_score: number;
  fraud_risk_level: string;
  courier_code: string | null;
  courier_consignment_id: string | null;
  courier_tracking_code: string | null;
  mushak_6_3_number: string | null;
  confirmed_at: string | null;
  shipped_at: string | null;
  delivered_at: string | null;
  cancelled_at: string | null;
  created_at: string;
  items: OrderItem[];
  status_histories: StatusHistory[];
  division?: { id: number; name_en: string; name_bn: string } | null;
  district?: { id: number; name_en: string; name_bn: string } | null;
  thana?: { id: number; name_en: string; name_bn: string } | null;
}

const props = defineProps<{
  order: OrderDetailRecord;
  allowedTransitions: string[];
}>();

const showTransitionModal = ref(false);
const selectedTargetStatus = ref('');

const transitionForm = useForm({
  target_status: '',
  reason: '',
});

const openTransitionModal = (status: string) => {
  selectedTargetStatus.value = status;
  transitionForm.target_status = status;
  transitionForm.reason = '';
  showTransitionModal.value = true;
};

const submitTransition = () => {
  transitionForm.post(`/admin/orders/${props.order.id}/transition`, {
    onSuccess: () => {
      showTransitionModal.value = false;
      transitionForm.reset();
    },
  });
};

const formatCurrency = (val: string | number) => {
  return Number(val).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getStatusBadgeClass = (status: string) => {
  return `badge-status-${status.replace(/_/g, '-')}`;
};
</script>

<template>
  <AdminLayout>
    <Head :title="`Order ${order.order_number}`" />

    <div class="order-detail-page">
      <!-- Top Action Bar -->
      <div class="action-bar">
        <div class="left-col">
          <Link href="/admin/orders" class="btn btn-outline btn-sm">
            ← Back to Orders
          </Link>
          <div class="order-identity">
            <h1 class="order-title">{{ order.order_number }}</h1>
            <span class="status-badge" :class="getStatusBadgeClass(order.status)">
              {{ order.status.replace(/_/g, ' ') }}
            </span>
          </div>
        </div>

        <!-- Available Lifecycle Actions -->
        <div class="lifecycle-actions" v-if="allowedTransitions.length > 0">
          <span class="action-prompt">Next Action:</span>
          <button
            v-for="target in allowedTransitions"
            :key="target"
            class="btn btn-sm"
            :class="target === 'cancelled' ? 'btn-danger' : 'btn-primary'"
            @click="openTransitionModal(target)"
          >
            Mark {{ target.replace(/_/g, ' ') }}
          </button>
        </div>
      </div>

      <!-- Main Layout Grid -->
      <div class="detail-grid">
        <!-- Left: Items & Financials & Audit Timeline -->
        <div class="main-column">
          <!-- Order Items Card -->
          <div class="card items-card">
            <h2 class="card-title">📦 Order Items ({{ order.items.length }})</h2>
            <table class="items-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>SKU</th>
                  <th>Unit Price</th>
                  <th>Qty</th>
                  <th>Unit COGS</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in order.items" :key="item.id">
                  <td>
                    <div class="product-cell">
                      <strong>{{ item.product_name }}</strong>
                      <div v-if="item.attributes" class="attr-badges">
                        <span v-for="(v, k) in item.attributes" :key="k" class="attr-pill">
                          {{ k }}: {{ v }}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td class="font-mono text-muted">{{ item.sku }}</td>
                  <td>৳{{ formatCurrency(item.unit_price_bdt) }}</td>
                  <td><strong>{{ item.quantity }}</strong></td>
                  <td class="font-mono text-muted">৳{{ formatCurrency(item.unit_cogs_bdt) }}</td>
                  <td><strong>৳{{ formatCurrency(item.subtotal_bdt) }}</strong></td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Financial Breakdown Card -->
          <div class="card financials-card">
            <h2 class="card-title">💰 Payment & Financial Breakdown</h2>
            <div class="financials-grid">
              <div class="fin-row">
                <span class="fin-label">Items Subtotal:</span>
                <span class="fin-value">৳{{ formatCurrency(order.subtotal_bdt) }}</span>
              </div>
              <div v-if="Number(order.discount_amount_bdt) > 0" class="fin-row discount-row">
                <span class="fin-label">Coupon Discount ({{ order.coupon_code }}):</span>
                <span class="fin-value text-success">-৳{{ formatCurrency(order.discount_amount_bdt) }}</span>
              </div>
              <div class="fin-row">
                <span class="fin-label">Delivery Shipping Fee ({{ order.delivery_zone.replace(/_/g, ' ') }}):</span>
                <span class="fin-value">৳{{ formatCurrency(order.shipping_fee_bdt) }}</span>
              </div>
              <div class="fin-row total-row">
                <span class="fin-label">Grand Total:</span>
                <span class="fin-value">৳{{ formatCurrency(order.total_bdt) }}</span>
              </div>
              <div v-if="Number(order.advance_paid_bdt) > 0" class="fin-row">
                <span class="fin-label">Advance Paid Deposit:</span>
                <span class="fin-value text-success">৳{{ formatCurrency(order.advance_paid_bdt) }}</span>
              </div>
              <div class="fin-row cod-payable-row">
                <span class="fin-label">Courier COD Cash to Collect:</span>
                <span class="fin-value text-primary">৳{{ formatCurrency(order.payable_cod_amount_bdt) }}</span>
              </div>
            </div>
          </div>

          <!-- Status Transition Timeline Audit History -->
          <div class="card timeline-card">
            <h2 class="card-title">📜 Lifecycle Audit History & Status Timeline</h2>
            <div class="timeline-list">
              <div v-for="history in order.status_histories" :key="history.id" class="timeline-item">
                <div class="timeline-dot" />
                <div class="timeline-content">
                  <div class="timeline-header">
                    <span class="timeline-transition">
                      <span v-if="history.from_status" class="status-from">{{ history.from_status }} → </span>
                      <strong class="status-to">{{ history.to_status }}</strong>
                    </span>
                    <span class="timeline-date">{{ new Date(history.created_at).toLocaleString('en-GB') }}</span>
                  </div>
                  <p v-if="history.reason" class="timeline-reason">{{ history.reason }}</p>
                  <span class="timeline-author">By: {{ history.changed_by_user?.name || 'System Auto-Action' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Sidebar: Customer & Logistics -->
        <div class="side-column">
          <!-- Customer & Risk Profile Card -->
          <div class="card customer-card">
            <h2 class="card-title">👤 Customer & Anti-Fraud</h2>
            <div class="info-group">
              <label>Name:</label>
              <strong>{{ order.customer_name }}</strong>
            </div>
            <div class="info-group">
              <label>Phone:</label>
              <a :href="`tel:${order.customer_phone}`" class="phone-link">
                📞 {{ order.customer_phone }}
              </a>
            </div>
            <div v-if="order.customer_email" class="info-group">
              <label>Email:</label>
              <span>{{ order.customer_email }}</span>
            </div>
            <div class="risk-badge-wrap">
              <span class="risk-label">Anti-Fraud Risk:</span>
              <span class="risk-pill" :class="`risk-${order.fraud_risk_level}`">
                Score {{ order.fraud_risk_score }}/100 ({{ order.fraud_risk_level.toUpperCase() }})
              </span>
            </div>
          </div>

          <!-- Delivery Address Card -->
          <div class="card address-card">
            <h2 class="card-title">📍 Delivery Address</h2>
            <div class="cascading-geo">
              <span class="geo-tag">{{ order.division?.name_en || 'Dhaka' }} Division</span>
              <span class="geo-separator">→</span>
              <span class="geo-tag">{{ order.district?.name_en || 'Dhaka' }} District</span>
              <span v-if="order.thana" class="geo-separator">→</span>
              <span v-if="order.thana" class="geo-tag">{{ order.thana?.name_en }}</span>
            </div>
            <p class="street-address">{{ order.delivery_address }}</p>
            <div v-if="order.customer_notes" class="customer-notes">
              <label>Customer Delivery Note:</label>
              <p>“{{ order.customer_notes }}”</p>
            </div>
          </div>

          <!-- Logistics & Shipping Consignment Card -->
          <div class="card courier-card">
            <h2 class="card-title">🚚 Courier Consignment</h2>
            <div class="info-group">
              <label>Courier Provider:</label>
              <strong>{{ order.courier_code?.toUpperCase() || 'Not Booked Yet' }}</strong>
            </div>
            <div v-if="order.courier_tracking_code" class="info-group">
              <label>Tracking Code:</label>
              <code class="tracking-code">{{ order.courier_tracking_code }}</code>
            </div>
            <div v-if="order.mushak_6_3_number" class="info-group">
              <label>NBR Mushak 6.3 #:</label>
              <code class="tax-code">{{ order.mushak_6_3_number }}</code>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Transition Modal -->
      <div v-if="showTransitionModal" class="modal-backdrop" @click="showTransitionModal = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-title">Confirm Status Transition</h3>
          <p class="modal-subtitle">
            Transition order <strong>{{ order.order_number }}</strong> to
            <span class="status-target">{{ selectedTargetStatus.replace(/_/g, ' ') }}</span>?
          </p>

          <form @submit.prevent="submitTransition">
            <div class="form-group">
              <label class="form-label">Reason / Internal Note (Optional):</label>
              <textarea
                v-model="transitionForm.reason"
                class="form-textarea"
                rows="3"
                placeholder="e.g. Phone confirmed with customer, ready for picking..."
              />
              <span v-if="transitionForm.errors.target_status" class="error-msg">
                {{ transitionForm.errors.target_status }}
              </span>
            </div>

            <div class="modal-actions">
              <button
                type="button"
                class="btn btn-outline"
                @click="showTransitionModal = false"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="transitionForm.processing"
              >
                {{ transitionForm.processing ? 'Saving...' : 'Confirm Transition' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.order-detail-page {
  padding: $space-6;

  .action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $space-6;
    flex-wrap: wrap;
    gap: $space-4;

    .left-col {
      display: flex;
      align-items: center;
      gap: $space-4;

      .order-identity {
        display: flex;
        align-items: center;
        gap: $space-3;

        .order-title {
          font-size: $fs-2xl;
          font-weight: 800;
          color: $color-text-main;
        }
      }
    }

    .lifecycle-actions {
      display: flex;
      align-items: center;
      gap: $space-2;

      .action-prompt {
        font-size: $fs-xs;
        font-weight: 600;
        color: $color-text-muted;
      }
    }
  }

  .detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: $space-6;

    @media (max-width: 992px) {
      grid-template-columns: 1fr;
    }
  }

  .card {
    background: $color-surface;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    padding: $space-5;
    margin-bottom: $space-6;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

    .card-title {
      font-size: $fs-base;
      font-weight: 700;
      color: $color-text-main;
      margin-bottom: $space-4;
      padding-bottom: $space-2;
      border-bottom: 1px solid $color-border;
    }
  }

  .items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: $fs-sm;

    th {
      background: $color-bg-subtle;
      padding: $space-2 $space-3;
      text-align: left;
      font-weight: 700;
      color: $color-text-muted;
      font-size: $fs-xs;
      border-bottom: 1px solid $color-border;
    }

    td {
      padding: $space-3;
      border-bottom: 1px solid $color-border;
      vertical-align: middle;
    }

    .product-cell {
      .attr-badges {
        display: flex;
        gap: 4px;
        margin-top: 4px;

        .attr-pill {
          background: $color-bg-subtle;
          padding: 2px 6px;
          border-radius: $radius-sm;
          font-size: 10px;
          color: $color-text-muted;
        }
      }
    }
  }

  .financials-grid {
    display: flex;
    flex-direction: column;
    gap: $space-2;
    max-width: 480px;

    .fin-row {
      display: flex;
      justify-content: space-between;
      font-size: $fs-sm;
      padding: $space-1 0;

      &.total-row {
        border-top: 2px solid $color-border;
        border-bottom: 2px solid $color-border;
        padding: $space-2 0;
        font-weight: 800;
        font-size: $fs-base;
      }

      &.cod-payable-row {
        font-size: $fs-base;
        font-weight: 800;
      }
    }
  }

  .timeline-list {
    position: relative;
    padding-left: $space-6;

    &::before {
      content: '';
      position: absolute;
      top: 0;
      bottom: 0;
      left: 7px;
      width: 2px;
      background: $color-border;
    }

    .timeline-item {
      position: relative;
      margin-bottom: $space-4;

      .timeline-dot {
        position: absolute;
        top: 4px;
        left: -24px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: $color-primary;
        border: 2px solid white;
      }

      .timeline-content {
        background: $color-bg-subtle;
        padding: $space-3;
        border-radius: $radius-md;

        .timeline-header {
          display: flex;
          justify-content: space-between;
          font-size: $fs-xs;
          margin-bottom: 4px;

          .timeline-transition {
            font-weight: 700;
            text-transform: uppercase;
          }

          .timeline-date {
            color: $color-text-muted;
          }
        }

        .timeline-reason {
          font-size: $fs-xs;
          margin: 4px 0;
          color: $color-text-main;
        }

        .timeline-author {
          font-size: 11px;
          color: $color-text-muted;
        }
      }
    }
  }

  .side-column {
    .info-group {
      margin-bottom: $space-3;
      font-size: $fs-sm;

      label {
        display: block;
        font-size: $fs-xs;
        color: $color-text-muted;
        margin-bottom: 2px;
      }

      .phone-link {
        color: $color-primary;
        text-decoration: none;
        font-weight: 700;
      }
    }

    .risk-badge-wrap {
      margin-top: $space-4;
      padding-top: $space-3;
      border-top: 1px dashed $color-border;
      display: flex;
      justify-content: space-between;
      align-items: center;

      .risk-pill {
        padding: 3px 8px;
        border-radius: $radius-sm;
        font-size: 11px;
        font-weight: 700;

        &.risk-low {
          background: rgba($color-success, 0.15);
          color: $color-success;
        }
        &.risk-medium {
          background: rgba($color-warning, 0.2);
          color: #b45309;
        }
        &.risk-high,
        &.risk-blacklisted {
          background: rgba($color-danger, 0.15);
          color: $color-danger;
        }
      }
    }

    .cascading-geo {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: $fs-xs;
      font-weight: 600;
      color: $color-primary;
      margin-bottom: $space-2;
    }

    .street-address {
      font-size: $fs-sm;
      color: $color-text-main;
      line-height: 1.5;
    }

    .customer-notes {
      margin-top: $space-3;
      padding: $space-2;
      background: #fefce8;
      border: 1px solid #fef08a;
      border-radius: $radius-sm;
      font-size: $fs-xs;

      label {
        font-weight: 700;
        color: #854d0e;
      }
      p {
        margin-top: 2px;
        font-style: italic;
      }
    }
  }

  .status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: $radius-full;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;

    &.badge-status-pending { background: #fef3c7; color: #b45309; }
    &.badge-status-confirmed { background: #e0f2fe; color: #0369a1; }
    &.badge-status-processing { background: #ede9fe; color: #6d28d9; }
    &.badge-status-picked-and-packed { background: #fae8ff; color: #86198f; }
    &.badge-status-handed-to-courier { background: #dbeafe; color: #1d4ed8; }
    &.badge-status-out-for-delivery { background: #ccfbf1; color: #0f766e; }
    &.badge-status-delivered { background: #dcfce7; color: #15803d; }
    &.badge-status-cancelled { background: #fee2e2; color: #b91c1c; }
    &.badge-status-returned { background: #ffe4e6; color: #be123c; }
  }

  .modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
  }

  .modal-card {
    background: $color-surface;
    border-radius: $radius-lg;
    padding: $space-6;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);

    .modal-title {
      font-size: $fs-lg;
      font-weight: 700;
    }
    .modal-subtitle {
      font-size: $fs-sm;
      color: $color-text-muted;
      margin: $space-2 0 $space-4;

      .status-target {
        font-weight: 700;
        text-transform: uppercase;
        color: $color-primary;
      }
    }

    .form-group {
      margin-bottom: $space-4;

      .form-label {
        display: block;
        font-size: $fs-xs;
        font-weight: 600;
        margin-bottom: 4px;
      }

      .form-textarea {
        width: 100%;
        padding: $space-2;
        border: 1px solid $color-border;
        border-radius: $radius-md;
        font-size: $fs-sm;
        outline: none;

        &:focus {
          border-color: $color-primary;
        }
      }

      .error-msg {
        font-size: 11px;
        color: $color-danger;
      }
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: $space-2;
    }
  }
}
</style>
