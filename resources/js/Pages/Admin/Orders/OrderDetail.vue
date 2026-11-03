<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

interface CourierOption {
  code: string;
  name: string;
  is_sandbox: boolean;
  is_active: boolean;
}

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
  couriers?: CourierOption[];
}>();

const showTransitionModal = ref(false);
const selectedTargetStatus = ref('');

const transitionForm = useForm({
  target_status: '',
  reason: '',
});

const defaultCouriers: CourierOption[] = [
  { code: 'steadfast', name: 'Steadfast Courier', is_sandbox: false, is_active: true },
  { code: 'pathao', name: 'Pathao Courier', is_sandbox: true, is_active: true },
];

const availableCouriers = computed(() => {
  return props.couriers && props.couriers.length > 0 ? props.couriers : defaultCouriers;
});

const selectedCourier = ref(
  props.order.courier_code ||
  (props.couriers && props.couriers.length > 0 ? props.couriers[0].code : 'steadfast')
);

const isBookingCourier = ref(false);
const courierBookingNotification = ref<{
  type: 'success' | 'error';
  message: string;
  labelUrl?: string;
} | null>(null);

const getCsrfToken = () => {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
};

const handleBookCourier = async () => {
  const courierName = availableCouriers.value.find((c) => c.code === selectedCourier.value)?.name || selectedCourier.value;

  const result = await Swal.fire({
    title: '<span style="color: #9d174d; font-weight: 800; font-size: 22px;">Book Courier Consignment?</span>',
    html: `
      <div class="swal-pink-card" style="background: #fdf2f8; border: 2px solid #fbcfe8; border-radius: 12px; padding: 18px 20px; color: #831843; font-size: 15px; font-weight: 600; line-height: 1.5; margin: 12px 0; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.12); text-align: center;">
        🚚 Are you sure you want to send and book order <b style="color: #be185d; font-size: 16px;">${props.order.order_number}</b> with <b style="color: #be185d; font-size: 16px;">${courierName}</b>?
      </div>
    `,
    icon: 'question',
    iconColor: '#db2777',
    background: '#fff5f7',
    showCancelButton: true,
    confirmButtonColor: '#db2777',
    cancelButtonColor: '#64748b',
    confirmButtonText: `🚀 Yes, Book with ${courierName}`,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusConfirm: true,
    customClass: {
      popup: 'swal-pink-popup',
    },
  });

  if (!result.isConfirmed) return;

  isBookingCourier.value = true;
  courierBookingNotification.value = null;

  try {
    const res = await fetch(`/admin/orders/${props.order.id}/book-courier`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        courier_code: selectedCourier.value,
      }),
    });

    const data = await res.json();
    if (res.ok && data.status === 'success') {
      const tracking = data.tracking_code || data.consignment_id;
      const labelUrl = `/admin/orders/${props.order.id}/shipping-label`;

      courierBookingNotification.value = {
        type: 'success',
        message: `Consignment successfully booked! Tracking Code: ${tracking}`,
        labelUrl,
      };

      Swal.fire({
        icon: 'success',
        title: 'Consignment Booked!',
        html: `Order <b>${props.order.order_number}</b> successfully booked with <b>${courierName}</b>!<br><br>Tracking Code: <code style="font-family: monospace; font-weight: bold; background: #eff6ff; padding: 4px 8px; border-radius: 4px; color: #1d4ed8; font-size: 14px;">${tracking}</code>`,
        confirmButtonColor: '#0f172a',
        confirmButtonText: '🏷️ Print 4x6 Label',
        showCancelButton: true,
        cancelButtonText: 'Close',
      }).then((modalRes) => {
        if (modalRes.isConfirmed) {
          window.open(labelUrl, '_blank');
        }
      });

      router.reload({ preserveScroll: true });
    } else {
      const errorMsg = data.message || 'Failed to book consignment with courier.';
      courierBookingNotification.value = {
        type: 'error',
        message: errorMsg,
      };

      Swal.fire({
        icon: 'error',
        title: 'Booking Failed',
        text: errorMsg,
        confirmButtonColor: '#dc2626',
      });
    }
  } catch (err: any) {
    const errorMsg = err?.message || 'Network error connecting to courier service.';
    courierBookingNotification.value = {
      type: 'error',
      message: errorMsg,
    };

    Swal.fire({
      icon: 'error',
      title: 'Network Error',
      text: errorMsg,
      confirmButtonColor: '#dc2626',
    });
  } finally {
    isBookingCourier.value = false;
  }
};

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
            :class="target === 'cancelled' ? 'btn-danger' : (target === 'returned' ? 'btn-returned' : 'btn-primary')"
            @click="openTransitionModal(target)"
          >
            <span v-if="target === 'returned'">🔄 Receive Return</span>
            <span v-else>Mark {{ target.replace(/_/g, ' ') }}</span>
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

            <!-- Booking Alert Notification -->
            <div
              v-if="courierBookingNotification"
              class="courier-banner"
              :class="`is-${courierBookingNotification.type}`"
            >
              <span>{{ courierBookingNotification.message }}</span>
              <a
                v-if="courierBookingNotification.labelUrl"
                :href="courierBookingNotification.labelUrl"
                target="_blank"
                class="banner-label-btn"
              >
                🏷️ Open 4x6 Label ↗
              </a>
            </div>

            <!-- When Already Booked -->
            <div v-if="order.courier_consignment_id || order.courier_tracking_code" class="booked-section">
              <div class="info-group">
                <label>Courier Provider:</label>
                <div class="courier-badge-row">
                  <span class="courier-badge-lg" :class="`courier-${order.courier_code || 'default'}`">
                    🚚 {{ (order.courier_code || 'courier').toUpperCase() }}
                  </span>
                  <span class="status-pill status-booked">✓ Booked</span>
                </div>
              </div>

              <div class="info-group">
                <label>Tracking Code:</label>
                <code class="tracking-code">{{ order.courier_tracking_code || order.courier_consignment_id }}</code>
              </div>

              <div v-if="order.courier_consignment_id" class="info-group">
                <label>Consignment ID:</label>
                <span class="consignment-text">{{ order.courier_consignment_id }}</span>
              </div>

              <div v-if="order.mushak_6_3_number" class="info-group">
                <label>NBR Mushak 6.3 #:</label>
                <code class="tax-code">{{ order.mushak_6_3_number }}</code>
              </div>

              <!-- Action Buttons for Booked Order -->
              <div class="courier-action-buttons">
                <a
                  :href="`/admin/orders/${order.id}/shipping-label`"
                  target="_blank"
                  class="btn btn-label-lg"
                  title="Open 4x6 Thermal Printable Barcode Shipping Label"
                >
                  🏷️ Print 4x6 Thermal Label
                </a>
                <a
                  :href="`/admin/orders/bag-tickets?order_ids=${order.id}`"
                  target="_blank"
                  class="btn btn-ticket-lg"
                  title="Print Packing Bag Ticket"
                >
                  🖨️ Print Packing Ticket
                </a>
              </div>
            </div>

            <!-- When Not Booked Yet -->
            <div v-else class="unbooked-section">
              <div class="unbooked-status-row">
                <span class="unbooked-tag">⚠️ Not Booked Yet</span>
                <span class="unbooked-desc">Assign this parcel to an active courier partner to book consignment & generate tracking.</span>
              </div>

              <div class="dispatch-form">
                <div class="form-group-courier">
                  <label class="courier-select-label">Select Courier Provider:</label>
                  <select v-model="selectedCourier" class="courier-select">
                    <option v-for="c in availableCouriers" :key="c.code" :value="c.code">
                      {{ c.name }} {{ c.is_sandbox ? '🧪 (Sandbox)' : '⚡ (Live)' }}
                    </option>
                  </select>
                </div>

                <button
                  type="button"
                  class="btn btn-book-courier"
                  :disabled="isBookingCourier || order.status === 'cancelled' || order.status === 'delivered' || order.status === 'returned'"
                  @click="handleBookCourier"
                >
                  <span v-if="isBookingCourier">⏳ Booking Consignment...</span>
                  <span v-else>🚀 Book & Send to Courier</span>
                </button>
              </div>

              <div class="unbooked-ticket-link">
                <a
                  :href="`/admin/orders/bag-tickets?order_ids=${order.id}`"
                  target="_blank"
                  class="btn btn-ticket-sm-link"
                >
                  🖨️ Print Packing Bag Ticket First
                </a>
              </div>
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

          <div v-if="selectedTargetStatus === 'returned'" class="return-restock-notice">
            <span class="notice-icon">🔄</span>
            <div>
              <strong>Warehouse Inward Restocking:</strong>
              <p>Receiving this parcel will automatically restore and restock the committed items back into your active warehouse inventory.</p>
            </div>
          </div>

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

    .courier-card {
      .courier-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 12px;
        border-radius: $radius-md;
        font-size: $fs-xs;
        margin-bottom: $space-3;
        font-weight: 600;

        &.is-success {
          background: #ecfdf5;
          border: 1px solid #a7f3d0;
          color: #065f46;
        }

        &.is-error {
          background: #fef2f2;
          border: 1px solid #fecaca;
          color: #991b1b;
        }

        .banner-label-btn {
          display: inline-block;
          font-weight: 700;
          color: inherit;
          text-decoration: underline;
          flex-shrink: 0;
        }
      }

      .booked-section {
        .courier-badge-row {
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .courier-badge-lg {
          display: inline-flex;
          align-items: center;
          gap: 4px;
          padding: 4px 10px;
          border-radius: $radius-full;
          font-size: 11px;
          font-weight: 800;
          letter-spacing: 0.5px;

          &.courier-steadfast {
            background: #e0f2fe;
            color: #0369a1;
          }

          &.courier-pathao {
            background: #fee2e2;
            color: #b91c1c;
          }

          &.courier-redx {
            background: #ffedd5;
            color: #c2410c;
          }
        }

        .status-booked {
          background: #dcfce7;
          color: #15803d;
          font-size: 11px;
          font-weight: 700;
          padding: 2px 8px;
          border-radius: $radius-full;
        }

        .tracking-code {
          font-family: monospace;
          font-weight: 700;
          font-size: $fs-sm;
          color: $color-primary;
          background: #f8fafc;
          border: 1px solid $color-border;
          padding: 3px 8px;
          border-radius: $radius-sm;
          display: inline-block;
        }

        .consignment-text {
          font-family: monospace;
          font-size: $fs-xs;
          color: $color-text-muted;
        }

        .tax-code {
          font-family: monospace;
          font-size: $fs-xs;
          color: #047857;
          background: #ecfdf5;
          padding: 2px 6px;
          border-radius: $radius-sm;
        }

        .courier-action-buttons {
          display: flex;
          flex-direction: column;
          gap: 8px;
          margin-top: $space-4;
          padding-top: $space-3;
          border-top: 1px solid $color-border;

          .btn-label-lg {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 14px;
            background: #0f172a;
            color: #ffffff;
            border-radius: $radius-md;
            font-weight: 700;
            font-size: $fs-xs;
            text-decoration: none;
            transition: background 0.2s;

            &:hover {
              background: #1e293b;
            }
          }

          .btn-ticket-lg {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: $radius-md;
            font-weight: 600;
            font-size: $fs-xs;
            text-decoration: none;
            transition: background 0.2s;

            &:hover {
              background: #e2e8f0;
            }
          }
        }
      }

      .unbooked-section {
        background: #fdf2f8;
        border: 1px solid #fbcfe8;
        border-radius: $radius-md;
        padding: $space-4;

        .unbooked-status-row {
          display: flex;
          flex-direction: column;
          gap: 4px;
          margin-bottom: $space-3;
          padding-bottom: $space-3;
          border-bottom: 1px dashed #fbcfe8;

          .unbooked-tag {
            display: inline-block;
            align-self: flex-start;
            padding: 3px 8px;
            border-radius: $radius-sm;
            background: #fce7f3;
            color: #9d174d;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
          }

          .unbooked-desc {
            font-size: 11px;
            color: #831843;
            line-height: 1.4;
          }
        }

        .dispatch-form {
          display: flex;
          flex-direction: column;
          gap: $space-3;

          .form-group-courier {
            .courier-select-label {
              display: block;
              font-size: $fs-xs;
              font-weight: 700;
              color: #831843;
              margin-bottom: 4px;
            }

            .courier-select {
              width: 100%;
              padding: 8px 10px;
              border: 1px solid #f472b6;
              border-radius: $radius-md;
              font-size: $fs-sm;
              font-weight: 600;
              background: #ffffff;
              color: #831843;
              outline: none;

              &:focus {
                border-color: #db2777;
                box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.2);
              }
            }
          }

          .btn-book-courier {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
            color: #ffffff;
            border: none;
            border-radius: $radius-md;
            font-weight: 700;
            font-size: $fs-sm;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(219, 39, 119, 0.3);
            transition: all 0.2s;

            &:hover:not(:disabled) {
              background: linear-gradient(135deg, #db2777 0%, #be185d 100%);
              box-shadow: 0 4px 10px rgba(219, 39, 119, 0.4);
            }

            &:disabled {
              opacity: 0.6;
              cursor: not-allowed;
            }
          }
        }

        .unbooked-ticket-link {
          margin-top: $space-2;
          text-align: center;

          .btn-ticket-sm-link {
            display: inline-block;
            font-size: 11px;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            padding: 4px 8px;

            &:hover {
              color: $color-primary;
              text-decoration: underline;
            }
          }
        }
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

  .btn-returned {
    background: #e11d48;
    color: #ffffff;
    border: 1px solid #be123c;
    font-weight: 700;

    &:hover {
      background: #be123c;
    }
  }

  .return-restock-notice {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: $radius-md;
    padding: 12px;
    margin: 12px 0 16px;
    font-size: 13px;
    color: #9f1239;

    .notice-icon {
      font-size: 20px;
      flex-shrink: 0;
      line-height: 1;
    }

    strong {
      color: #881337;
      display: block;
      margin-bottom: 2px;
    }

    p {
      margin: 0;
      font-size: 12px;
      color: #be123c;
      line-height: 1.4;
    }
  }
}

:global(.swal-pink-popup) {
  background-color: #fff5f7 !important;
  border: 2px solid #fbcfe8 !important;
  border-radius: 16px !important;
  box-shadow: 0 20px 40px rgba(219, 39, 119, 0.18) !important;
}

:global(.swal-pink-popup .swal2-title) {
  color: #9d174d !important;
}
</style>
