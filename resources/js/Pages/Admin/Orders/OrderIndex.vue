<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface OrderItem {
  id: number;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price_bdt: string;
  subtotal_bdt: string;
}

interface OrderRecord {
  id: number;
  ulid: string;
  order_number: string;
  customer_name: string;
  customer_phone: string;
  delivery_zone: string;
  delivery_address: string;
  subtotal_bdt: string;
  discount_amount_bdt: string;
  shipping_fee_bdt: string;
  total_bdt: string;
  status: string;
  payment_status: string;
  payment_method: string;
  ticket_print_count: number;
  ticket_printed_at: string | null;
  created_at: string;
  items: OrderItem[];
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const props = defineProps<{
  orders: {
    data: OrderRecord[];
    links: PaginationLink[];
    total: number;
    current_page: number;
  };
  filters: {
    status: string;
    delivery_zone: string;
    search: string | null;
  };
  counts: {
    all: number;
    pending: number;
    confirmed: number;
    processing: number;
    shipped: number;
    delivered: number;
    cancelled: number;
  };
}>();

const search = ref(props.filters.search || '');
const selectedZone = ref(props.filters.delivery_zone || 'all');

const handleSearch = () => {
  router.get(
    '/admin/orders',
    {
      status: props.filters.status,
      delivery_zone: selectedZone.value,
      search: search.value || undefined,
    },
    { preserveState: true }
  );
};

const handleStatusFilter = (targetStatus: string) => {
  router.get(
    '/admin/orders',
    {
      status: targetStatus,
      delivery_zone: selectedZone.value,
      search: search.value || undefined,
    },
    { preserveState: true }
  );
};

const formatCurrency = (val: string | number) => {
  return Number(val).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getStatusBadgeClass = (status: string) => {
  return `badge-status-${status.replace(/_/g, '-')}`;
};

const selectedOrderIds = ref<number[]>([]);
const isConfirming = ref<Record<number, boolean>>({});
const isTransitioning = ref<Record<number, boolean>>({});
const isBulkConfirming = ref(false);
const isBulkTransitioning = ref(false);

const selectedOrders = computed(() => {
  return props.orders.data.filter((o) => selectedOrderIds.value.includes(o.id));
});

const confirmedSelectedCount = computed(() => {
  return selectedOrders.value.filter((o) => o.status === 'confirmed').length;
});

const processingSelectedCount = computed(() => {
  return selectedOrders.value.filter((o) => o.status === 'processing' || o.status === 'picked_and_packed').length;
});

const pendingSelectedCount = computed(() => {
  return selectedOrders.value.filter((o) => o.status === 'pending').length;
});

const isAllSelected = computed(() => {
  return props.orders.data.length > 0 && selectedOrderIds.value.length === props.orders.data.length;
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedOrderIds.value = [];
  } else {
    selectedOrderIds.value = props.orders.data.map((o) => o.id);
  }
};

const confirmOrder = (order: OrderRecord) => {
  isConfirming.value[order.id] = true;
  router.post(
    `/admin/orders/${order.id}/transition`,
    {
      target_status: 'confirmed',
      reason: 'Confirmed directly from orders table action button.',
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isConfirming.value[order.id] = false;
      },
    }
  );
};

const transitionOrder = (order: OrderRecord, targetStatus: string, reason?: string) => {
  isTransitioning.value[order.id] = true;
  router.post(
    `/admin/orders/${order.id}/transition`,
    {
      target_status: targetStatus,
      reason: reason || `Transitioned to ${targetStatus} from orders table action button.`,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isTransitioning.value[order.id] = false;
      },
    }
  );
};

const handleBulkConfirm = () => {
  if (selectedOrderIds.value.length === 0) return;
  if (!confirm(`Are you sure you want to confirm all ${selectedOrderIds.value.length} selected orders?`)) return;

  isBulkConfirming.value = true;
  router.post(
    '/admin/orders/bulk-confirm',
    { order_ids: selectedOrderIds.value },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        selectedOrderIds.value = [];
      },
      onFinish: () => {
        isBulkConfirming.value = false;
      },
    }
  );
};

const handleBulkTransition = (targetStatus: string, actionLabel: string) => {
  if (selectedOrderIds.value.length === 0) return;
  if (!confirm(`Are you sure you want to transition ${selectedOrderIds.value.length} selected order(s) to '${actionLabel}'?`)) return;

  isBulkTransitioning.value = true;
  router.post(
    '/admin/orders/bulk-transition',
    {
      order_ids: selectedOrderIds.value,
      target_status: targetStatus,
      reason: `Bulk transitioned to ${targetStatus} from admin orders dashboard.`,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        selectedOrderIds.value = [];
      },
      onFinish: () => {
        isBulkTransitioning.value = false;
      },
    }
  );
};

const printBagTickets = (orderIds?: number[]) => {
  const ids = orderIds && orderIds.length > 0 ? orderIds : selectedOrderIds.value;
  if (ids.length === 0) return;
  const url = `/admin/orders/bag-tickets?order_ids=${ids.join(',')}`;
  window.open(url, '_blank');
};
</script>

<template>
  <AdminLayout>
    <Head title="Orders & Anti-Fraud Management" />

    <div class="orders-management-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">🛍️ Orders & Dispatch Management</h1>
          <p class="page-subtitle">Track real-time order lifecycle transitions, customer verification, and courier handoffs.</p>
        </div>
        <div class="header-actions">
          <button
            v-if="orders.data.length > 0"
            type="button"
            class="btn btn-outline btn-sm btn-header-print"
            title="Open printable bag tickets for all orders currently listed"
            @click="printBagTickets(orders.data.map((o) => o.id))"
          >
            🖨️ Print All Page Bag Tickets ({{ orders.data.length }})
          </button>
        </div>
      </div>

      <!-- Status Filter Tabs -->
      <div class="status-tabs">
        <button
          class="status-tab"
          :class="{ 'is-active': filters.status === 'all' }"
          @click="handleStatusFilter('all')"
        >
          All Orders <span class="tab-badge">{{ counts.all }}</span>
        </button>
        <button
          class="status-tab tab-pending"
          :class="{ 'is-active': filters.status === 'pending' }"
          @click="handleStatusFilter('pending')"
        >
          Pending Verification <span class="tab-badge">{{ counts.pending }}</span>
        </button>
        <button
          class="status-tab tab-confirmed"
          :class="{ 'is-active': filters.status === 'confirmed' }"
          @click="handleStatusFilter('confirmed')"
        >
          Confirmed <span class="tab-badge">{{ counts.confirmed }}</span>
        </button>
        <button
          class="status-tab tab-processing"
          :class="{ 'is-active': filters.status === 'processing' }"
          @click="handleStatusFilter('processing')"
        >
          Processing & Pick <span class="tab-badge">{{ counts.processing }}</span>
        </button>
        <button
          class="status-tab tab-shipped"
          :class="{ 'is-active': filters.status === 'handed_to_courier' }"
          @click="handleStatusFilter('handed_to_courier')"
        >
          With Courier <span class="tab-badge">{{ counts.shipped }}</span>
        </button>
        <button
          class="status-tab tab-delivered"
          :class="{ 'is-active': filters.status === 'delivered' }"
          @click="handleStatusFilter('delivered')"
        >
          Delivered <span class="tab-badge">{{ counts.delivered }}</span>
        </button>
        <button
          class="status-tab tab-cancelled"
          :class="{ 'is-active': filters.status === 'cancelled' }"
          @click="handleStatusFilter('cancelled')"
        >
          Cancelled <span class="tab-badge">{{ counts.cancelled }}</span>
        </button>
      </div>

      <!-- Filter Controls Bar -->
      <div class="filters-bar">
        <div class="search-wrap">
          <input
            v-model="search"
            type="text"
            placeholder="Search by Order #, Customer Name, Phone..."
            class="input-search"
            @keyup.enter="handleSearch"
          />
          <button class="btn btn-primary btn-search" @click="handleSearch">Search</button>
        </div>

        <div class="zone-filter-wrap">
          <label class="filter-label">Delivery Zone:</label>
          <select v-model="selectedZone" class="select-zone" @change="handleSearch">
            <option value="all">All Zones</option>
            <option value="inside_dhaka">Inside Dhaka (৳70)</option>
            <option value="dhaka_suburbs">Dhaka Suburbs (৳100)</option>
            <option value="outside_dhaka">Outside Dhaka (৳130)</option>
          </select>
        </div>
      </div>

      <!-- Bulk Actions Bar -->
      <div v-if="selectedOrderIds.length > 0" class="bulk-actions-bar">
        <div class="bulk-info">
          <span class="bulk-count-badge">{{ selectedOrderIds.length }}</span>
          <span>order(s) selected</span>
        </div>
        <div class="bulk-btn-group">
          <button
            type="button"
            class="btn btn-print-bulk"
            title="Print in-bag tickets with Code128 barcodes for selected orders"
            @click="printBagTickets()"
          >
            🖨️ Print {{ selectedOrderIds.length }} Bag Ticket(s) with Barcode
          </button>

          <!-- Bulk Confirm for Pending Orders -->
          <button
            v-if="pendingSelectedCount > 0 || filters.status === 'pending'"
            type="button"
            class="btn btn-confirm-bulk"
            :disabled="isBulkConfirming"
            title="Confirm selected pending orders and reserve stock"
            @click="handleBulkConfirm"
          >
            <span v-if="isBulkConfirming">⏳ Confirming...</span>
            <span v-else>✓ Confirm Selected ({{ pendingSelectedCount || selectedOrderIds.length }})</span>
          </button>

          <!-- Bulk Move Confirmed Orders to Processing & Pick -->
          <button
            v-if="confirmedSelectedCount > 0 || filters.status === 'confirmed'"
            type="button"
            class="btn btn-process-bulk"
            :disabled="isBulkTransitioning"
            title="Move confirmed orders to Processing & Pick"
            @click="handleBulkTransition('processing', 'Processing & Pick')"
          >
            <span v-if="isBulkTransitioning">⏳ Updating...</span>
            <span v-else>📦 Move to Processing & Pick ({{ confirmedSelectedCount || selectedOrderIds.length }})</span>
          </button>

          <!-- Bulk Assign Processing Orders to With Courier -->
          <button
            v-if="processingSelectedCount > 0 || filters.status === 'processing'"
            type="button"
            class="btn btn-courier-bulk"
            :disabled="isBulkTransitioning"
            title="Bulk assign orders to With Courier"
            @click="handleBulkTransition('handed_to_courier', 'With Courier')"
          >
            <span v-if="isBulkTransitioning">⏳ Updating...</span>
            <span v-else>🚚 Assign With Courier ({{ processingSelectedCount || selectedOrderIds.length }})</span>
          </button>

          <button
            type="button"
            class="btn btn-deselect"
            @click="selectedOrderIds = []"
          >
            ✕ Deselect All
          </button>
        </div>
      </div>

      <!-- Orders Data Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="checkbox-cell">
                <input
                  type="checkbox"
                  class="checkbox-custom"
                  :checked="isAllSelected"
                  title="Select All on Page"
                  @change="toggleSelectAll"
                />
              </th>
              <th>Order #</th>
              <th>Customer</th>
              <th>Zone</th>
              <th>Bag Ticket</th>
              <th>Items</th>
              <th>Total BDT</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Date</th>
              <th style="min-width: 220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="order in orders.data"
              :key="order.id"
              class="order-row"
              :class="{ 'is-selected': selectedOrderIds.includes(order.id) }"
            >
              <td class="checkbox-cell">
                <input
                  type="checkbox"
                  class="checkbox-custom"
                  :value="order.id"
                  v-model="selectedOrderIds"
                />
              </td>
              <td class="font-mono">
                <Link :href="`/admin/orders/${order.id}`" class="order-link">
                  <strong>{{ order.order_number }}</strong>
                </Link>
              </td>
              <td>
                <div class="customer-info">
                  <span class="cust-name">{{ order.customer_name }}</span>
                  <span class="cust-phone">{{ order.customer_phone }}</span>
                </div>
              </td>
              <td>
                <span class="zone-pill" :class="`zone-${order.delivery_zone}`">
                  {{ order.delivery_zone === 'inside_dhaka' ? 'Inside Dhaka' : order.delivery_zone === 'dhaka_suburbs' ? 'Dhaka Suburbs' : 'Outside Dhaka' }}
                </span>
              </td>
              <td>
                <div class="ticket-status-wrap">
                  <span
                    v-if="order.ticket_print_count > 0"
                    class="print-count-badge is-printed"
                    :title="`Printed ${order.ticket_print_count} time(s). Last at: ${order.ticket_printed_at || 'N/A'}`"
                  >
                    🖨️ {{ order.ticket_print_count }}x Printed
                  </span>
                  <span
                    v-else
                    class="print-count-badge is-unprinted"
                    title="Bag ticket has not been printed yet"
                  >
                    ⚠️ 0x (Unprinted)
                  </span>
                </div>
              </td>
              <td>
                <span class="items-count-badge">{{ order.items?.length || 0 }} SKU(s)</span>
              </td>
              <td class="order-total-cell">
                <strong>৳{{ formatCurrency(order.total_bdt) }}</strong>
              </td>
              <td>
                <span class="payment-method-badge">{{ order.payment_method.toUpperCase() }}</span>
              </td>
              <td>
                <span class="status-badge" :class="getStatusBadgeClass(order.status)">
                  {{ order.status.replace(/_/g, ' ') }}
                </span>
              </td>
              <td class="order-date-cell">
                {{ new Date(order.created_at).toLocaleDateString('en-GB') }}
              </td>
              <td class="actions-cell">
                <div class="row-actions">
                  <!-- Quick Confirm Order Button directly in Table -->
                  <button
                    v-if="order.status === 'pending'"
                    type="button"
                    class="btn btn-confirm-sm"
                    :disabled="isConfirming[order.id]"
                    title="Confirm order & reserve warehouse stock"
                    @click="confirmOrder(order)"
                  >
                    <span v-if="isConfirming[order.id]">⏳</span>
                    <span v-else>✓ Confirm</span>
                  </button>

                  <!-- Quick Move to Processing & Pick -->
                  <button
                    v-else-if="order.status === 'confirmed'"
                    type="button"
                    class="btn btn-process-sm"
                    :disabled="isTransitioning[order.id]"
                    title="Move to Processing & Pick"
                    @click="transitionOrder(order, 'processing', 'Moved to Processing & Pick from orders table')"
                  >
                    <span v-if="isTransitioning[order.id]">⏳</span>
                    <span v-else>📦 Pick & Pack</span>
                  </button>

                  <!-- Quick Assign With Courier -->
                  <button
                    v-else-if="order.status === 'processing' || order.status === 'picked_and_packed'"
                    type="button"
                    class="btn btn-courier-sm"
                    :disabled="isTransitioning[order.id]"
                    title="Assign / Hand over to Courier"
                    @click="transitionOrder(order, 'handed_to_courier', 'Handed over to courier from orders table')"
                  >
                    <span v-if="isTransitioning[order.id]">⏳</span>
                    <span v-else>🚚 With Courier</span>
                  </button>

                  <!-- Quick Bag Ticket Print with Barcode -->
                  <button
                    type="button"
                    class="btn btn-ticket-sm"
                    title="Print in-bag packing ticket with Code128 barcode"
                    @click="printBagTickets([order.id])"
                  >
                    🖨️ Ticket
                  </button>

                  <Link :href="`/admin/orders/${order.id}`" class="btn btn-outline btn-sm">
                    View →
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="orders.data.length === 0">
              <td colspan="11" class="empty-state">
                <div class="empty-wrap">
                  <span class="empty-icon">📭</span>
                  <p>No orders found matching the selected filters.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Links -->
      <div v-if="orders.links.length > 3" class="pagination-wrap">
        <template v-for="(link, i) in orders.links" :key="i">
          <Link
            v-if="link.url"
            :href="link.url"
            class="pagination-link"
            :class="{ 'is-active': link.active }"
            v-html="link.label"
          />
          <span v-else class="pagination-link is-disabled" v-html="link.label" />
        </template>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.orders-management-page {
  padding: $space-6;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: $space-4;
    margin-bottom: $space-6;
    flex-wrap: wrap;

    .page-title {
      font-size: $fs-2xl;
      font-weight: 800;
      color: $color-text-main;
    }
    .page-subtitle {
      font-size: $fs-sm;
      color: $color-text-muted;
      margin-top: $space-1;
    }

    .btn-header-print {
      border-color: #cbd5e1;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;

      &:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
      }
    }
  }

  .bulk-actions-bar {
    position: sticky;
    top: 70px;
    z-index: 50;
    background: #0f172a;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: $radius-lg;
    margin-bottom: $space-4;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: $space-3;
    flex-wrap: wrap;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);

    .bulk-info {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 600;

      .bulk-count-badge {
        background: #eb286c;
        color: #ffffff;
        padding: 2px 8px;
        border-radius: $radius-full;
        font-weight: 800;
        font-size: 12px;
      }
    }

    .bulk-btn-group {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;

      .btn-print-bulk {
        background: #eb286c;
        color: #ffffff;
        border: none;
        padding: 7px 16px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease;

        &:hover {
          background: #d41f5e;
        }
      }

      .btn-confirm-bulk {
        background: #16a34a;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;

        &:hover:not(:disabled) {
          background: #15803d;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-process-bulk {
        background: #7c3aed;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;

        &:hover:not(:disabled) {
          background: #6d28d9;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-courier-bulk {
        background: #2563eb;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;

        &:hover:not(:disabled) {
          background: #1d4ed8;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-deselect {
        background: transparent;
        color: #94a3b8;
        border: 1px solid #475569;
        padding: 6px 12px;
        border-radius: $radius-md;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;

        &:hover {
          color: #ffffff;
          border-color: #cbd5e1;
        }
      }
    }
  }

  .status-tabs {
    display: flex;
    gap: $space-2;
    overflow-x: auto;
    padding-bottom: $space-2;
    margin-bottom: $space-6;
    border-bottom: 1px solid $color-border;

    .status-tab {
      background: $color-surface;
      border: 1px solid $color-border;
      border-radius: $radius-md;
      padding: $space-2 $space-4;
      font-size: $fs-xs;
      font-weight: 600;
      color: $color-text-muted;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: $space-2;
      white-space: nowrap;
      transition: all 0.15s ease;

      .tab-badge {
        background: $color-bg-subtle;
        padding: 2px 6px;
        border-radius: $radius-full;
        font-size: 11px;
      }

      &:hover {
        border-color: $color-primary;
        color: $color-primary;
      }

      &.is-active {
        background: $color-primary;
        border-color: $color-primary;
        color: white;

        .tab-badge {
          background: rgba(255, 255, 255, 0.25);
          color: white;
        }
      }
    }
  }

  .filters-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: $space-4;
    margin-bottom: $space-6;
    flex-wrap: wrap;

    .search-wrap {
      display: flex;
      gap: $space-2;
      flex: 1;
      max-width: 440px;

      .input-search {
        flex: 1;
        padding: $space-2 $space-3;
        border: 1px solid $color-border;
        border-radius: $radius-md;
        font-size: $fs-sm;
        outline: none;

        &:focus {
          border-color: $color-primary;
        }
      }
    }

    .zone-filter-wrap {
      display: flex;
      align-items: center;
      gap: $space-2;

      .filter-label {
        font-size: $fs-xs;
        font-weight: 600;
        color: $color-text-muted;
      }

      .select-zone {
        padding: $space-2 $space-3;
        border: 1px solid $color-border;
        border-radius: $radius-md;
        font-size: $fs-sm;
        background: $color-surface;
      }
    }
  }

  .table-container {
    background: $color-surface;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: $fs-sm;

      th {
        background: $color-bg-subtle;
        padding: $space-3 $space-4;
        text-align: left;
        font-weight: 700;
        color: $color-text-muted;
        font-size: $fs-xs;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid $color-border;
      }

      td {
        padding: $space-4;
        border-bottom: 1px solid $color-border;
        vertical-align: middle;
      }

      .checkbox-cell {
        width: 40px;
        text-align: center;
        padding-left: $space-3;
        padding-right: $space-2;

        .checkbox-custom {
          width: 17px;
          height: 17px;
          cursor: pointer;
          accent-color: $color-primary;
          border-radius: $radius-sm;
        }
      }

      .order-row {
        transition: background 0.15s ease;

        &:hover {
          background: $color-bg-subtle;
        }

        &.is-selected {
          background: rgba($color-primary, 0.05);
        }
      }

      .actions-cell {
        .row-actions {
          display: flex;
          align-items: center;
          gap: $space-2;
          white-space: nowrap;

          .btn-confirm-sm {
            background: #16a34a;
            color: #ffffff;
            border: none;
            padding: 5px 10px;
            border-radius: $radius-md;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;

            &:hover:not(:disabled) {
              background: #15803d;
            }

            &:disabled {
              opacity: 0.6;
              cursor: not-allowed;
            }
          }

          .btn-process-sm {
            background: #7c3aed;
            color: #ffffff;
            border: none;
            padding: 5px 10px;
            border-radius: $radius-md;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;

            &:hover:not(:disabled) {
              background: #6d28d9;
            }

            &:disabled {
              opacity: 0.6;
              cursor: not-allowed;
            }
          }

          .btn-courier-sm {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 5px 10px;
            border-radius: $radius-md;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;

            &:hover:not(:disabled) {
              background: #1d4ed8;
            }

            &:disabled {
              opacity: 0.6;
              cursor: not-allowed;
            }
          }

          .btn-ticket-sm {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            padding: 5px 9px;
            border-radius: $radius-md;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 3px;

            &:hover {
              background: #e2e8f0;
              border-color: #94a3b8;
            }
          }
        }
      }

      .order-link {
        color: $color-primary;
        text-decoration: none;

        &:hover {
          text-decoration: underline;
        }
      }

      .customer-info {
        display: flex;
        flex-direction: column;
        .cust-name {
          font-weight: 600;
          color: $color-text-main;
        }
        .cust-phone {
          font-size: $fs-xs;
          color: $color-text-muted;
        }
      }

      .zone-pill {
        padding: 3px 8px;
        border-radius: $radius-sm;
        font-size: 11px;
        font-weight: 600;

        &.zone-inside_dhaka {
          background: rgba($color-success, 0.1);
          color: $color-success;
        }
        &.zone-dhaka_suburbs {
          background: rgba($color-warning, 0.15);
          color: #b45309;
        }
        &.zone-outside_dhaka {
          background: rgba(147, 51, 234, 0.1);
          color: #7e22ce;
        }
      }

      .ticket-status-wrap {
        display: flex;
        align-items: center;

        .print-count-badge {
          display: inline-flex;
          align-items: center;
          gap: 4px;
          padding: 3px 8px;
          border-radius: $radius-full;
          font-size: 11px;
          font-weight: 700;
          white-space: nowrap;

          &.is-printed {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
          }

          &.is-unprinted {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
          }
        }
      }

      .items-count-badge {
        font-size: $fs-xs;
        color: $color-text-muted;
      }

      .payment-method-badge {
        background: $color-bg-subtle;
        padding: 2px 6px;
        border-radius: $radius-sm;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.05em;
      }

      .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: $radius-full;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;

        &.badge-status-pending {
          background: #fef3c7;
          color: #b45309;
        }
        &.badge-status-confirmed {
          background: #e0f2fe;
          color: #0369a1;
        }
        &.badge-status-processing {
          background: #ede9fe;
          color: #6d28d9;
        }
        &.badge-status-picked-and-packed {
          background: #fae8ff;
          color: #86198f;
        }
        &.badge-status-handed-to-courier {
          background: #dbeafe;
          color: #1d4ed8;
        }
        &.badge-status-out-for-delivery {
          background: #ccfbf1;
          color: #0f766e;
        }
        &.badge-status-delivered {
          background: #dcfce7;
          color: #15803d;
        }
        &.badge-status-cancelled {
          background: #fee2e2;
          color: #b91c1c;
        }
        &.badge-status-returned {
          background: #ffe4e6;
          color: #be123c;
        }
      }

      .empty-state {
        text-align: center;
        padding: $space-12;

        .empty-wrap {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: $space-2;
          color: $color-text-muted;

          .empty-icon {
            font-size: 32px;
          }
        }
      }
    }
  }

  .pagination-wrap {
    display: flex;
    justify-content: center;
    gap: $space-1;
    margin-top: $space-6;

    .pagination-link {
      padding: $space-2 $space-3;
      border: 1px solid $color-border;
      border-radius: $radius-md;
      font-size: $fs-xs;
      color: $color-text-main;
      text-decoration: none;

      &.is-active {
        background: $color-primary;
        border-color: $color-primary;
        color: white;
      }

      &.is-disabled {
        color: $color-text-muted;
        cursor: not-allowed;
      }
    }
  }
}
</style>
