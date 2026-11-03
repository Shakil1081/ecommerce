<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface MetricsPayload {
  total_booked: number;
  total_dispatched: number;
  delivered_count: number;
  delivery_success_rate: number;
  returned_count: number;
  return_rate: number;
  in_transit_count: number;
  pending_queue_count: number;
  total_order_amount_bdt: number;
  active_order_amount_bdt: number;
  total_courier_charge_bdt: number;
  cod_collected_bdt: number;
  cod_pending_in_transit_bdt: number;
  cod_pending_queue_bdt: number;
  total_cod_pending_bdt: number;
  parcels_pending_settlement: number;
}

interface CourierBreakdownItem {
  code: string;
  name: string;
  is_active: boolean;
  is_sandbox: boolean;
  booked_count: number;
  in_transit_count: number;
  delivered_count: number;
  returned_count: number;
  success_rate: number;
  cod_collectible_bdt: number;
  cod_collected_bdt: number;
}

interface ZoneBreakdownItem {
  name: string;
  count: number;
  total_cod: number;
}

interface ParcelItem {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  payment_method: string;
  customer_name: string;
  customer_phone: string;
  courier_code: string;
  courier_tracking_code: string | null;
  courier_consignment_id: string | null;
  delivery_zone: string;
  district_name: string;
  delivery_address: string;
  total_bdt: string;
  shipping_fee_bdt: string;
  payable_cod_amount_bdt: string;
  items_count: number;
  created_at: string;
  shipped_at: string;
  delivered_at: string;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedParcels {
  data: ParcelItem[];
  links: PaginationLink[];
  total: number;
}

interface FilterProps {
  status: string;
  courier: string;
  date_from: string | null;
  date_to: string | null;
  search: string | null;
}

interface CourierOption {
  code: string;
  name: string;
}

const props = defineProps<{
  metrics: MetricsPayload;
  courierBreakdown: Record<string, CourierBreakdownItem>;
  zoneBreakdown: Record<string, ZoneBreakdownItem>;
  parcels: PaginatedParcels;
  filters: FilterProps;
  availableCouriers: CourierOption[];
}>();

// Filter States
const statusFilter = ref(props.filters.status || 'all');
const courierFilter = ref(props.filters.courier || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const search = ref(props.filters.search || '');

const formatCurrency = (val: number | string): string => {
  return Number(val).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const handleFilter = () => {
  router.get('/admin/logistics/delivery-overview', {
    status: statusFilter.value,
    courier: courierFilter.value,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    search: search.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const setStatusTab = (status: string) => {
  statusFilter.value = status;
  handleFilter();
};

const applyDatePreset = (preset: 'today' | 'yesterday' | 'last_7_days' | 'this_month' | 'clear') => {
  const now = new Date();
  const formatYmd = (d: Date) => d.toISOString().split('T')[0];

  if (preset === 'today') {
    dateFrom.value = formatYmd(now);
    dateTo.value = formatYmd(now);
  } else if (preset === 'yesterday') {
    const y = new Date(now);
    y.setDate(y.getDate() - 1);
    dateFrom.value = formatYmd(y);
    dateTo.value = formatYmd(y);
  } else if (preset === 'last_7_days') {
    const past = new Date(now);
    past.setDate(past.getDate() - 7);
    dateFrom.value = formatYmd(past);
    dateTo.value = formatYmd(now);
  } else if (preset === 'this_month') {
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    dateFrom.value = formatYmd(firstDay);
    dateTo.value = formatYmd(now);
  } else if (preset === 'clear') {
    dateFrom.value = '';
    dateTo.value = '';
  }
  handleFilter();
};

const resetAllFilters = () => {
  statusFilter.value = 'all';
  courierFilter.value = 'all';
  dateFrom.value = '';
  dateTo.value = '';
  search.value = '';
  handleFilter();
};

const hasActiveFilters = computed(() => {
  return statusFilter.value !== 'all' || courierFilter.value !== 'all' || !!dateFrom.value || !!dateTo.value || !!search.value;
});

const exportCsvUrl = computed(() => {
  const params = new URLSearchParams();
  if (statusFilter.value !== 'all') params.append('status', statusFilter.value);
  if (courierFilter.value !== 'all') params.append('courier', courierFilter.value);
  if (dateFrom.value) params.append('date_from', dateFrom.value);
  if (dateTo.value) params.append('date_to', dateTo.value);
  if (search.value) params.append('search', search.value);
  return `/admin/logistics/delivery-overview/export-csv?${params.toString()}`;
});
</script>

<template>
  <AdminLayout>
    <Head title="Courier Delivery & COD Settlement Command Center" />

    <div class="delivery-dashboard-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <div class="header-tag">📊 Logistics Intelligence</div>
          <h1 class="page-title">Courier Delivery & COD Settlement Command Center</h1>
          <p class="page-subtitle">
            Real-time fulfillment tracking, in-transit parcels, delivery success rates, and cash-on-delivery (COD) settlement reconciliation.
          </p>
        </div>

        <div class="header-actions">
          <a :href="exportCsvUrl" class="btn btn-export" title="Download delivery & settlement statement as CSV">
            📥 Export CSV Statement
          </a>
          <Link href="/admin/orders/scan-handover" class="btn btn-primary" title="Open scan & handover console">
            📦 Scan & Handover Parcels
          </Link>
          <Link href="/admin/orders/scan-returns" class="btn btn-outline" title="Open scan returns console">
            📷 Scan Returns
          </Link>
          <Link href="/admin/logistics/cod-reconciliation" class="btn btn-outline" title="Open COD bank reconciliation">
            💰 Bank Reconciliation
          </Link>
        </div>
      </div>

      <!-- Top 10 Core Metrics KPIs Grid -->
      <div class="kpi-section">
        <h2 class="section-title">📌 Key Performance & Settlement Metrics (Live Database)</h2>
        <div class="kpi-grid">
          <!-- 1. Total Parcels Sent/Booked -->
          <div class="kpi-card card-booked">
            <div class="kpi-header">
              <span class="kpi-icon">📦</span>
              <span class="kpi-label">1. Parcels Sent / Booked</span>
            </div>
            <div class="kpi-value">{{ metrics.total_booked }} <span class="kpi-unit">Parcels</span></div>
            <div class="kpi-sub">{{ metrics.total_dispatched }} physically dispatched</div>
          </div>

          <!-- 2. Successfully Delivered -->
          <div class="kpi-card card-delivered">
            <div class="kpi-header">
              <span class="kpi-icon">✅</span>
              <span class="kpi-label">2. Successfully Delivered</span>
            </div>
            <div class="kpi-value">{{ metrics.delivered_count }} <span class="kpi-unit">Parcels</span></div>
            <div class="kpi-sub">Success Rate: <strong>{{ metrics.delivery_success_rate }}%</strong></div>
          </div>

          <!-- 3. Returned Parcels -->
          <div class="kpi-card card-returned">
            <div class="kpi-header">
              <span class="kpi-icon">🔄</span>
              <span class="kpi-label">3. Returned Parcels</span>
            </div>
            <div class="kpi-value">{{ metrics.returned_count }} <span class="kpi-unit">Parcels</span></div>
            <div class="kpi-sub">Return Rate: <strong>{{ metrics.return_rate }}%</strong> (Restocked)</div>
          </div>

          <!-- 4. Currently In Transit -->
          <div class="kpi-card card-transit">
            <div class="kpi-header">
              <span class="kpi-icon">🚚</span>
              <span class="kpi-label">4. Currently In Transit</span>
            </div>
            <div class="kpi-value">{{ metrics.in_transit_count }} <span class="kpi-unit">Parcels</span></div>
            <div class="kpi-sub">Out with Courier Riders</div>
          </div>

          <!-- 5. Pending / Courier Queue -->
          <div class="kpi-card card-queue">
            <div class="kpi-header">
              <span class="kpi-icon">⏳</span>
              <span class="kpi-label">5. Pending In Warehouse Queue</span>
            </div>
            <div class="kpi-value">{{ metrics.pending_queue_count }} <span class="kpi-unit">Parcels</span></div>
            <div class="kpi-sub">In picking & packing</div>
          </div>

          <!-- 6. Total Order Amount -->
          <div class="kpi-card card-order-amount">
            <div class="kpi-header">
              <span class="kpi-icon">💵</span>
              <span class="kpi-label">6. Total Order Amount</span>
            </div>
            <div class="kpi-value font-mono">৳{{ formatCurrency(metrics.total_order_amount_bdt) }}</div>
            <div class="kpi-sub">Active Orders: ৳{{ formatCurrency(metrics.active_order_amount_bdt) }}</div>
          </div>

          <!-- 7. Total Courier Charge -->
          <div class="kpi-card card-charge">
            <div class="kpi-header">
              <span class="kpi-icon">🏷️</span>
              <span class="kpi-label">7. Total Courier Charge</span>
            </div>
            <div class="kpi-value font-mono">৳{{ formatCurrency(metrics.total_courier_charge_bdt) }}</div>
            <div class="kpi-sub">Delivery fee allocation</div>
          </div>

          <!-- 8. COD Collected by Courier -->
          <div class="kpi-card card-collected">
            <div class="kpi-header">
              <span class="kpi-icon">💰</span>
              <span class="kpi-label">8. COD Collected by Courier</span>
            </div>
            <div class="kpi-value font-mono">৳{{ formatCurrency(metrics.cod_collected_bdt) }}</div>
            <div class="kpi-sub">Collected from delivered orders</div>
          </div>

          <!-- 9. COD Pending to be Paid -->
          <div class="kpi-card card-pending-cod">
            <div class="kpi-header">
              <span class="kpi-icon">💸</span>
              <span class="kpi-label">9. COD Pending (In Transit)</span>
            </div>
            <div class="kpi-value font-mono">৳{{ formatCurrency(metrics.cod_pending_in_transit_bdt) }}</div>
            <div class="kpi-sub">Total Active COD: ৳{{ formatCurrency(metrics.total_cod_pending_bdt) }}</div>
          </div>

          <!-- 10. Parcels Pending Settlement -->
          <div class="kpi-card card-settlement">
            <div class="kpi-header">
              <span class="kpi-icon">💳</span>
              <span class="kpi-label">10. Parcels Pending Settlement</span>
            </div>
            <div class="kpi-value">{{ metrics.parcels_pending_settlement }} <span class="kpi-unit">Parcels</span></div>
            <div class="kpi-sub">Awaiting delivery & bank payout</div>
          </div>
        </div>
      </div>

      <!-- Mid Section: Courier Breakdown & Zone Distribution -->
      <div class="analytics-row">
        <!-- Courier Performance Cards -->
        <div class="analytics-card courier-comparison-card">
          <div class="card-header-row">
            <h3 class="card-heading">🚚 Courier Partner Performance & COD Exposure</h3>
            <span class="badge-sub">Live Integration Stats</span>
          </div>

          <div class="courier-grid">
            <div
              v-for="(c, cCode) in courierBreakdown"
              :key="cCode"
              class="courier-stat-box"
              :class="`box-courier-${cCode}`"
            >
              <div class="box-top">
                <span class="courier-title">{{ c.name }}</span>
                <span class="status-pill" :class="c.is_active ? 'is-active' : 'is-inactive'">
                  {{ c.is_active ? (c.is_sandbox ? '🧪 Sandbox' : '⚡ Live') : 'Disabled' }}
                </span>
              </div>

              <div class="box-kpis">
                <div class="box-kpi">
                  <span class="kpi-sm-lbl">Total Booked</span>
                  <strong class="kpi-sm-val">{{ c.booked_count }}</strong>
                </div>
                <div class="box-kpi">
                  <span class="kpi-sm-lbl">In Transit</span>
                  <strong class="kpi-sm-val text-primary">{{ c.in_transit_count }}</strong>
                </div>
                <div class="box-kpi">
                  <span class="kpi-sm-lbl">Delivered</span>
                  <strong class="kpi-sm-val text-success">{{ c.delivered_count }}</strong>
                </div>
                <div class="box-kpi">
                  <span class="kpi-sm-lbl">Returned</span>
                  <strong class="kpi-sm-val text-danger">{{ c.returned_count }}</strong>
                </div>
              </div>

              <div class="box-financials">
                <div class="fin-row">
                  <span>COD In Transit:</span>
                  <strong class="font-mono text-warning">৳{{ formatCurrency(c.cod_collectible_bdt) }}</strong>
                </div>
                <div class="fin-row">
                  <span>COD Collected:</span>
                  <strong class="font-mono text-success">৳{{ formatCurrency(c.cod_collected_bdt) }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Delivery Zone Breakdown -->
        <div class="analytics-card zone-card">
          <div class="card-header-row">
            <h3 class="card-heading">🗺️ Delivery Zone Distribution</h3>
            <span class="badge-sub">Orders by Region</span>
          </div>

          <div class="zone-list">
            <div v-for="(z, zKey) in zoneBreakdown" :key="zKey" class="zone-item">
              <div class="zone-info">
                <span class="zone-name">{{ z.name }}</span>
                <span class="zone-count">{{ z.count }} parcel(s)</span>
              </div>
              <div class="zone-bar-wrap">
                <div
                  class="zone-bar-fill"
                  :class="`fill-${zKey}`"
                  :style="{ width: `${metrics.total_booked > 0 ? (z.count / metrics.total_booked) * 100 : 0}%` }"
                ></div>
              </div>
              <div class="zone-cod font-mono">
                COD: ৳{{ formatCurrency(z.total_cod) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Parcels Tracking & Reconciliation Table Card -->
      <div class="table-card">
        <div class="table-card-header">
          <div class="title-wrap">
            <h3 class="card-heading">📦 Parcel Tracking & Settlement Directory</h3>
            <span class="count-tag">{{ parcels.total }} Record(s)</span>
          </div>

          <!-- Status Filter Tabs -->
          <div class="status-tab-group">
            <button
              class="status-btn"
              :class="{ 'is-active': statusFilter === 'all' }"
              @click="setStatusTab('all')"
            >
              All ({{ metrics.total_booked + metrics.pending_queue_count }})
            </button>
            <button
              class="status-btn"
              :class="{ 'is-active': statusFilter === 'in_transit' }"
              @click="setStatusTab('in_transit')"
            >
              🚚 In Transit ({{ metrics.in_transit_count }})
            </button>
            <button
              class="status-btn"
              :class="{ 'is-active': statusFilter === 'pending_queue' }"
              @click="setStatusTab('pending_queue')"
            >
              ⏳ In Queue ({{ metrics.pending_queue_count }})
            </button>
            <button
              class="status-btn"
              :class="{ 'is-active': statusFilter === 'delivered' }"
              @click="setStatusTab('delivered')"
            >
              ✅ Delivered ({{ metrics.delivered_count }})
            </button>
            <button
              class="status-btn"
              :class="{ 'is-active': statusFilter === 'returned' }"
              @click="setStatusTab('returned')"
            >
              🔄 Returned ({{ metrics.returned_count }})
            </button>
          </div>
        </div>

        <!-- Filter Controls Toolbar -->
        <div class="filter-toolbar">
          <div class="search-wrap">
            <input
              v-model="search"
              type="text"
              placeholder="Search by Order #, Tracking #, Customer, Phone..."
              class="filter-input-search"
              @keyup.enter="handleFilter"
            />
            <button class="btn btn-sm btn-primary" @click="handleFilter">Search</button>
          </div>

          <div class="filter-group">
            <label class="filter-lbl">Courier:</label>
            <select v-model="courierFilter" class="filter-select" @change="handleFilter">
              <option value="all">All Couriers</option>
              <option v-for="c in availableCouriers" :key="c.code" :value="c.code">
                {{ c.name }}
              </option>
            </select>
          </div>

          <div class="filter-group date-group">
            <label class="filter-lbl">Dates:</label>
            <input v-model="dateFrom" type="date" class="filter-date" @change="handleFilter" />
            <span class="to-text">to</span>
            <input v-model="dateTo" type="date" class="filter-date" @change="handleFilter" />
          </div>

          <div class="quick-presets">
            <button type="button" class="preset-btn" @click="applyDatePreset('today')">Today</button>
            <button type="button" class="preset-btn" @click="applyDatePreset('yesterday')">Yesterday</button>
            <button type="button" class="preset-btn" @click="applyDatePreset('last_7_days')">7 Days</button>
            <button type="button" class="preset-btn" @click="applyDatePreset('this_month')">This Month</button>
            <button v-if="hasActiveFilters" type="button" class="preset-btn btn-clear" @click="resetAllFilters">Reset</button>
          </div>
        </div>

        <!-- Table View -->
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Courier & Tracking</th>
                <th>Customer</th>
                <th>Destination</th>
                <th>Order Total</th>
                <th>Courier Fee</th>
                <th>COD Amount</th>
                <th>Parcel Status</th>
                <th>Payment</th>
                <th>Dates</th>
                <th style="text-align: right; width: 130px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in parcels.data" :key="p.id" class="table-row">
                <td class="font-mono font-bold">
                  <Link :href="`/admin/orders/${p.id}`" class="order-link">
                    {{ p.order_number }}
                  </Link>
                  <div class="sub-sku">{{ p.items_count }} SKU unit(s)</div>
                </td>
                <td>
                  <span class="courier-badge" :class="`badge-courier-${p.courier_code?.toLowerCase()}`">
                    🚚 {{ p.courier_code }}
                  </span>
                  <div class="tracking-text font-mono">
                    {{ p.courier_tracking_code || p.courier_consignment_id || 'Not Booked' }}
                  </div>
                </td>
                <td>
                  <div class="font-bold">{{ p.customer_name }}</div>
                  <div class="text-xs text-muted">📞 {{ p.customer_phone }}</div>
                </td>
                <td>
                  <div class="font-bold">{{ p.district_name }}</div>
                  <span class="zone-pill">{{ p.delivery_zone }}</span>
                </td>
                <td class="font-mono font-bold">
                  ৳{{ formatCurrency(p.total_bdt) }}
                </td>
                <td class="font-mono text-muted">
                  ৳{{ formatCurrency(p.shipping_fee_bdt) }}
                </td>
                <td class="font-mono font-bold text-success">
                  ৳{{ formatCurrency(p.payable_cod_amount_bdt) }}
                </td>
                <td>
                  <span class="status-pill" :class="`status-${p.status}`">
                    {{ p.status.replace(/_/g, ' ') }}
                  </span>
                </td>
                <td>
                  <span class="payment-pill" :class="`pay-${p.payment_status}`">
                    {{ p.payment_status }}
                  </span>
                </td>
                <td class="text-xs">
                  <div>Placed: {{ p.created_at }}</div>
                  <div v-if="p.shipped_at !== 'N/A'" class="text-muted">Shipped: {{ p.shipped_at }}</div>
                </td>
                <td class="text-right">
                  <div class="action-buttons">
                    <a
                      v-if="p.courier_consignment_id || p.courier_tracking_code"
                      :href="`/admin/orders/${p.id}/shipping-label`"
                      target="_blank"
                      class="btn-action-mini"
                      title="Print 4x6 thermal shipping label"
                    >
                      🏷️
                    </a>
                    <a
                      :href="`/admin/orders/bag-tickets?order_ids=${p.id}`"
                      target="_blank"
                      class="btn-action-mini"
                      title="Print in-bag packing ticket"
                    >
                      🖨️
                    </a>
                    <Link
                      :href="`/admin/orders/${p.id}`"
                      class="btn-action-mini"
                      title="View full order details"
                    >
                      👁️
                    </Link>
                  </div>
                </td>
              </tr>

              <tr v-if="parcels.data.length === 0">
                <td colspan="11" class="empty-state">
                  <div class="empty-wrap">
                    <span class="empty-icon">📭</span>
                    <p class="empty-text">No parcel records found matching the active filter criteria.</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="parcels.links.length > 3" class="pagination-bar">
          <template v-for="(link, i) in parcels.links" :key="i">
            <Link
              v-if="link.url"
              :href="link.url"
              class="page-link"
              :class="{ 'is-active': link.active }"
              v-html="link.label"
            />
            <span v-else class="page-link is-disabled" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.delivery-dashboard-page {
  padding: $space-6;
  max-width: 1500px;
  margin: 0 auto;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: $space-6;
    flex-wrap: wrap;
    gap: $space-4;

    .header-tag {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: $color-primary;
      margin-bottom: 4px;
    }

    .page-title {
      font-size: $fs-2xl;
      font-weight: 800;
      color: $color-text-main;
      letter-spacing: -0.5px;
    }

    .page-subtitle {
      font-size: $fs-sm;
      color: $color-text-muted;
      margin-top: 4px;
      max-width: 700px;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: $space-2;
      flex-wrap: wrap;

      .btn-export {
        background: #0f172a;
        color: #ffffff;
        font-weight: 700;
        font-size: $fs-xs;
        padding: 8px 14px;
        border-radius: $radius-md;
        text-decoration: none;
        transition: background 0.2s;

        &:hover {
          background: #1e293b;
        }
      }
    }
  }

  .kpi-section {
    margin-bottom: $space-6;

    .section-title {
      font-size: $fs-sm;
      font-weight: 800;
      text-transform: uppercase;
      color: $color-text-muted;
      letter-spacing: 0.5px;
      margin-bottom: $space-3;
    }

    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: $space-3;

      @media (max-width: 1200px) {
        grid-template-columns: repeat(3, 1fr);
      }
      @media (max-width: 768px) {
        grid-template-columns: repeat(2, 1fr);
      }

      .kpi-card {
        background: #ffffff;
        border: 1px solid $color-border;
        border-radius: $radius-lg;
        padding: $space-4;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        justify-content: space-between;

        .kpi-header {
          display: flex;
          align-items: center;
          gap: 6px;
          margin-bottom: 8px;

          .kpi-icon {
            font-size: 16px;
          }

          .kpi-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: $color-text-muted;
          }
        }

        .kpi-value {
          font-size: 19px;
          font-weight: 800;
          color: $color-text-main;
          line-height: 1.2;

          .kpi-unit {
            font-size: 12px;
            font-weight: 600;
            color: $color-text-muted;
          }
        }

        .kpi-sub {
          font-size: 11px;
          color: $color-text-muted;
          margin-top: 6px;
        }

        &.card-booked .kpi-value { color: #0284c7; }
        &.card-delivered .kpi-value { color: #16a34a; }
        &.card-returned .kpi-value { color: #dc2626; }
        &.card-transit .kpi-value { color: #2563eb; }
        &.card-queue .kpi-value { color: #d97706; }
        &.card-collected .kpi-value { color: #059669; }
        &.card-pending-cod .kpi-value { color: #ea580c; }
      }
    }
  }

  .analytics-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: $space-4;
    margin-bottom: $space-6;

    @media (max-width: 1024px) {
      grid-template-columns: 1fr;
    }

    .analytics-card {
      background: #ffffff;
      border: 1px solid $color-border;
      border-radius: $radius-lg;
      padding: $space-5;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

      .card-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: $space-4;

        .card-heading {
          font-size: $fs-base;
          font-weight: 800;
          color: $color-text-main;
        }

        .badge-sub {
          font-size: 11px;
          font-weight: 700;
          color: $color-text-muted;
          background: #f1f5f9;
          padding: 2px 8px;
          border-radius: $radius-full;
        }
      }

      .courier-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: $space-3;

        .courier-stat-box {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: $radius-md;
          padding: $space-4;

          .box-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: $space-3;

            .courier-title {
              font-weight: 800;
              font-size: $fs-sm;
              color: $color-text-main;
            }

            .status-pill {
              font-size: 10px;
              font-weight: 700;
              padding: 2px 6px;
              border-radius: $radius-full;

              &.is-active { background: #dcfce7; color: #166534; }
              &.is-inactive { background: #f1f5f9; color: #64748b; }
            }
          }

          .box-kpis {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            margin-bottom: $space-3;
            text-align: center;
            background: #ffffff;
            padding: 8px 4px;
            border-radius: $radius-sm;
            border: 1px solid #f1f5f9;

            .box-kpi {
              .kpi-sm-lbl {
                display: block;
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                color: $color-text-muted;
              }
              .kpi-sm-val {
                font-size: 14px;
                font-weight: 800;
              }
            }
          }

          .box-financials {
            font-size: 11px;
            display: flex;
            flex-direction: column;
            gap: 2px;

            .fin-row {
              display: flex;
              justify-content: space-between;
            }
          }
        }
      }

      .zone-list {
        display: flex;
        flex-direction: column;
        gap: $space-4;

        .zone-item {
          .zone-info {
            display: flex;
            justify-content: space-between;
            font-size: $fs-xs;
            font-weight: 700;
            margin-bottom: 4px;
          }

          .zone-bar-wrap {
            height: 8px;
            background: #f1f5f9;
            border-radius: $radius-full;
            overflow: hidden;
            margin-bottom: 4px;

            .zone-bar-fill {
              height: 100%;
              border-radius: $radius-full;

              &.fill-inside_dhaka { background: #10b981; }
              &.fill-dhaka_suburbs { background: #f59e0b; }
              &.fill-outside_dhaka { background: #6366f1; }
            }
          }

          .zone-cod {
            font-size: 11px;
            color: $color-text-muted;
            text-align: right;
          }
        }
      }
    }
  }

  .table-card {
    background: #ffffff;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

    .table-card-header {
      padding: $space-5 $space-6;
      border-bottom: 1px solid $color-border;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: $space-3;

      .title-wrap {
        display: flex;
        align-items: center;
        gap: $space-3;

        .card-heading {
          font-size: $fs-lg;
          font-weight: 800;
          color: $color-text-main;
        }

        .count-tag {
          font-size: 11px;
          font-weight: 700;
          background: #eff6ff;
          color: #1d4ed8;
          padding: 2px 8px;
          border-radius: $radius-full;
        }
      }

      .status-tab-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;

        .status-btn {
          padding: 6px 12px;
          border-radius: $radius-md;
          font-size: $fs-xs;
          font-weight: 700;
          border: 1px solid $color-border;
          background: #ffffff;
          cursor: pointer;
          transition: all 0.15s ease;

          &.is-active {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
          }

          &:hover:not(.is-active) {
            background: #f8fafc;
            border-color: $color-primary;
          }
        }
      }
    }

    .filter-toolbar {
      padding: $space-4 $space-6;
      background: #f8fafc;
      border-bottom: 1px solid $color-border;
      display: flex;
      align-items: center;
      gap: $space-3;
      flex-wrap: wrap;

      .search-wrap {
        display: flex;
        gap: 6px;

        .filter-input-search {
          padding: 6px 12px;
          border: 1px solid $color-border;
          border-radius: $radius-md;
          font-size: $fs-xs;
          width: 260px;
          outline: none;

          &:focus {
            border-color: $color-primary;
          }
        }
      }

      .filter-group {
        display: flex;
        align-items: center;
        gap: 6px;

        .filter-lbl {
          font-size: 11px;
          font-weight: 700;
          text-transform: uppercase;
          color: $color-text-muted;
        }

        .filter-select,
        .filter-date {
          padding: 6px 10px;
          border: 1px solid $color-border;
          border-radius: $radius-md;
          font-size: $fs-xs;
          outline: none;
          background: #ffffff;

          &:focus {
            border-color: $color-primary;
          }
        }

        .to-text {
          font-size: 11px;
          color: $color-text-muted;
        }
      }

      .quick-presets {
        display: flex;
        align-items: center;
        gap: 4px;

        .preset-btn {
          font-size: 10px;
          font-weight: 700;
          padding: 4px 8px;
          border-radius: $radius-sm;
          border: 1px solid $color-border;
          background: #ffffff;
          cursor: pointer;

          &:hover {
            border-color: $color-primary;
          }

          &.btn-clear {
            color: #dc2626;
            border-color: #fca5a5;
          }
        }
      }
    }

    .table-responsive {
      overflow-x: auto;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: $fs-sm;

      th {
        background: #ffffff;
        padding: 10px 14px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: $color-text-muted;
        border-bottom: 2px solid $color-border;
      }

      td {
        padding: 12px 14px;
        border-bottom: 1px solid $color-border;
        vertical-align: middle;
      }

      .table-row:hover {
        background: #f8fafc;
      }

      .order-link {
        color: $color-primary;
        text-decoration: none;

        &:hover {
          text-decoration: underline;
        }
      }

      .sub-sku {
        font-size: 11px;
        color: $color-text-muted;
      }

      .courier-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: $radius-sm;

        &.badge-courier-steadfast { background: #e0f2fe; color: #0369a1; }
        &.badge-courier-pathao { background: #fee2e2; color: #b91c1c; }
        &.badge-courier-redx { background: #ffedd5; color: #c2410c; }
      }

      .tracking-text {
        font-size: 11px;
        color: $color-text-muted;
        margin-top: 2px;
      }

      .zone-pill {
        display: inline-block;
        font-size: 9px;
        text-transform: uppercase;
        color: $color-text-muted;
      }

      .status-pill {
        display: inline-block;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: $radius-full;
        text-transform: uppercase;

        &.status-handed_to_courier { background: #dbeafe; color: #1e40af; }
        &.status-out_for_delivery { background: #ccfbf1; color: #0f766e; }
        &.status-delivered { background: #dcfce7; color: #166534; }
        &.status-returned { background: #ffe4e6; color: #be123c; }
        &.status-processing { background: #f3e8ff; color: #7e22ce; }
        &.status-picked_and_packed { background: #fae8ff; color: #86198f; }
        &.status-confirmed { background: #e0e7ff; color: #3730a3; }
        &.status-pending { background: #fef3c7; color: #92400e; }
      }

      .payment-pill {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: $radius-sm;
        text-transform: uppercase;

        &.pay-paid { background: #dcfce7; color: #15803d; }
        &.pay-pending { background: #fef3c7; color: #b45309; }
      }

      .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 4px;

        .btn-action-mini {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 28px;
          height: 28px;
          border: 1px solid $color-border;
          border-radius: $radius-sm;
          background: #ffffff;
          font-size: 12px;
          text-decoration: none;
          color: $color-text-main;
          transition: all 0.15s ease;

          &:hover {
            border-color: $color-primary;
            background: #eff6ff;
          }
        }
      }

      .empty-state {
        text-align: center;
        padding: $space-8;

        .empty-icon {
          font-size: 36px;
        }

        .empty-text {
          font-size: $fs-sm;
          color: $color-text-muted;
          margin-top: 6px;
        }
      }
    }

    .pagination-bar {
      padding: $space-4 $space-6;
      border-top: 1px solid $color-border;
      display: flex;
      justify-content: center;
      gap: 4px;

      .page-link {
        padding: 6px 12px;
        border: 1px solid $color-border;
        border-radius: $radius-md;
        font-size: $fs-xs;
        color: $color-text-main;
        text-decoration: none;

        &.is-active {
          background: $color-primary;
          color: #ffffff;
          border-color: $color-primary;
        }

        &.is-disabled {
          opacity: 0.4;
          cursor: not-allowed;
        }

        &:hover:not(.is-active):not(.is-disabled) {
          border-color: $color-primary;
        }
      }
    }
  }
}
</style>

