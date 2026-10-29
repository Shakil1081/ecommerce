<script setup lang="ts">
import { ref } from 'vue';
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

      <!-- Orders Data Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Zone</th>
              <th>Items</th>
              <th>Total BDT</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders.data" :key="order.id" class="order-row">
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
              <td>
                <Link :href="`/admin/orders/${order.id}`" class="btn btn-outline btn-sm">
                  View →
                </Link>
              </td>
            </tr>
            <tr v-if="orders.data.length === 0">
              <td colspan="9" class="empty-state">
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
    margin-bottom: $space-6;
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

      .order-row:hover {
        background: $color-bg-subtle;
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
