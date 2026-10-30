<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CategoryBreakdownItem {
  category_name: string;
  units_sold: number;
  revenue_bdt: string;
  cogs_bdt: string;
  gross_profit_bdt: string;
  margin_percent: number;
}

interface TopSkuItem {
  sku: string;
  product_name: string;
  units_sold: number;
  revenue_bdt: string;
  cogs_bdt: string;
  gross_profit_bdt: string;
  margin_percent: number;
}

interface DailyTrendItem {
  date: string;
  revenue_bdt: string;
  cogs_bdt: string;
  profit_bdt: string;
}

interface PnLData {
  period: string;
  startDate: string;
  endDate: string;
  grossSalesTurnoverBdt: string;
  totalDiscountsBdt: string;
  netSalesRevenueBdt: string;
  vatCollectedBdt: string;
  totalCogsBdt: string;
  grossTradingProfitBdt: string;
  grossMarginPercent: number;
  shippingFeesCollectedBdt: string;
  courierCostBdt: string;
  shippingVarianceBdt: string;
  gatewayFeesBdt: string;
  returnLossesBdt: string;
  netOperatingProfitBdt: string;
  netMarginPercent: number;
  totalOrdersCount: number;
  deliveredOrdersCount: number;
  cancelledOrdersCount: number;
  averageOrderValueBdt: string;
  categoryBreakdown: CategoryBreakdownItem[];
  topPerformingSkus: TopSkuItem[];
  dailyTrend: DailyTrendItem[];
}

const props = defineProps<{
  pnl: PnLData;
  filters: {
    period: string;
    start_date?: string;
    end_date?: string;
  };
}>();

const activePeriod = ref(props.filters.period || 'this_month');
const customStart = ref(props.filters.start_date || '');
const customEnd = ref(props.filters.end_date || '');

const setPeriod = (p: string) => {
  activePeriod.value = p;
  router.get('/admin/analytics/trading-pnl', {
    period: p,
  }, { preserveState: true });
};

const applyCustomDates = () => {
  router.get('/admin/analytics/trading-pnl', {
    period: 'custom',
    start_date: customStart.value,
    end_date: customEnd.value,
  }, { preserveState: true });
};

const formatCurrency = (val: string | number) => {
  const num = typeof val === 'string' ? parseFloat(val) : val;
  return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', minimumFractionDigits: 2 }).format(num || 0);
};

const handlePrint = () => {
  window.print();
};
</script>

<template>
  <AdminLayout>
    <Head title="Executive Trading Profit & Loss (P&L)" />

    <div class="trading-pnl-page">
      <!-- Header Bar -->
      <div class="header-section no-print">
        <div>
          <div class="breadcrumb">
            <Link href="/admin">Dashboard</Link> / <span>Analytics</span> / <span>Trading P&L</span>
          </div>
          <h1 class="page-title">Executive Trading Profit & Loss (P&L)</h1>
          <p class="page-subtitle">Consolidated commercial trading economics in BDT (৳) factoring dual-sourcing landed COGS, VAT, logistics variance, and gateway costs.</p>
        </div>
        <div class="header-actions">
          <button class="btn btn-secondary" @click="handlePrint">
            Print P&L Report
          </button>
        </div>
      </div>

      <!-- Controls & Period Filter Bar -->
      <div class="filter-controls-card no-print">
        <div class="period-pills">
          <button
            class="pill-btn"
            :class="{ active: activePeriod === 'today' }"
            @click="setPeriod('today')"
          >
            Today
          </button>
          <button
            class="pill-btn"
            :class="{ active: activePeriod === 'this_week' }"
            @click="setPeriod('this_week')"
          >
            This Week
          </button>
          <button
            class="pill-btn"
            :class="{ active: activePeriod === 'this_month' }"
            @click="setPeriod('this_month')"
          >
            This Month
          </button>
          <button
            class="pill-btn"
            :class="{ active: activePeriod === 'this_quarter' }"
            @click="setPeriod('this_quarter')"
          >
            This Quarter
          </button>
        </div>

        <div class="custom-date-picker">
          <input v-model="customStart" type="date" class="date-input" />
          <span class="to-separator">to</span>
          <input v-model="customEnd" type="date" class="date-input" />
          <button class="btn btn-primary btn-sm" @click="applyCustomDates">
            Apply
          </button>
        </div>
      </div>

      <!-- Executive P&L Waterfall Cards -->
      <div class="waterfall-grid">
        <div class="waterfall-card card-revenue">
          <span class="metric-label">Gross Sales Turnover</span>
          <span class="metric-value font-mono">{{ formatCurrency(pnl.grossSalesTurnoverBdt) }}</span>
          <span class="metric-sub">Discounts: -{{ formatCurrency(pnl.totalDiscountsBdt) }}</span>
        </div>

        <div class="waterfall-card card-net">
          <span class="metric-label">Net Sales Revenue</span>
          <span class="metric-value font-mono">{{ formatCurrency(pnl.netSalesRevenueBdt) }}</span>
          <span class="metric-sub">Output VAT: {{ formatCurrency(pnl.vatCollectedBdt) }}</span>
        </div>

        <div class="waterfall-card card-cogs">
          <span class="metric-label">Cost of Goods Sold (COGS)</span>
          <span class="metric-value font-mono text-danger">-{{ formatCurrency(pnl.totalCogsBdt) }}</span>
          <span class="metric-sub">Import Landed + Local Sourcing</span>
        </div>

        <div class="waterfall-card card-gross-profit">
          <span class="metric-label">Gross Trading Profit</span>
          <span class="metric-value font-mono text-success">{{ formatCurrency(pnl.grossTradingProfitBdt) }}</span>
          <span class="margin-badge" :class="pnl.grossMarginPercent >= 20 ? 'margin-high' : 'margin-medium'">
            Margin: {{ pnl.grossMarginPercent }}%
          </span>
        </div>
      </div>

      <!-- Secondary Cost & Variance Grid -->
      <div class="secondary-grid">
        <div class="secondary-card">
          <span class="label">Logistics Variance</span>
          <div class="val-with-sign">
            <span class="value font-mono" :class="Number(pnl.shippingVarianceBdt) >= 0 ? 'text-success' : 'text-danger'">
              {{ Number(pnl.shippingVarianceBdt) >= 0 ? '+' : '' }}{{ formatCurrency(pnl.shippingVarianceBdt) }}
            </span>
          </div>
          <span class="sub-label">
            Collected ৳{{ Number(pnl.shippingFeesCollectedBdt).toFixed(0) }} vs Courier ৳{{ Number(pnl.courierCostBdt).toFixed(0) }}
          </span>
        </div>

        <div class="secondary-card">
          <span class="label">Payment Gateway Fees</span>
          <span class="value font-mono text-danger">-{{ formatCurrency(pnl.gatewayFeesBdt) }}</span>
          <span class="sub-label">bKash, Nagad, Card Merchant Fees</span>
        </div>

        <div class="secondary-card">
          <span class="label">RMA Scrap & Damaged Loss</span>
          <span class="value font-mono text-danger">-{{ formatCurrency(pnl.returnLossesBdt) }}</span>
          <span class="sub-label">Customer returns written off</span>
        </div>

        <div class="secondary-card card-net-profit">
          <span class="label font-bold text-dark">Net Operating Profit</span>
          <span class="value font-mono font-bold" :class="Number(pnl.netOperatingProfitBdt) >= 0 ? 'text-success' : 'text-danger'">
            {{ formatCurrency(pnl.netOperatingProfitBdt) }}
          </span>
          <span class="margin-badge margin-high">
            Net Margin: {{ pnl.netMarginPercent }}%
          </span>
        </div>
      </div>

      <!-- Category Profitability Breakdown Table -->
      <div class="section-card">
        <div class="section-card-header">
          <h2 class="section-heading">Category Profitability Breakdown</h2>
          <span class="section-subtext">Trading gross margins grouped by product categories</span>
        </div>

        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Category</th>
                <th class="text-right">Units Sold</th>
                <th class="text-right">Revenue (BDT)</th>
                <th class="text-right">COGS (BDT)</th>
                <th class="text-right">Gross Profit</th>
                <th class="text-right">Gross Margin %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="pnl.categoryBreakdown.length === 0">
                <td colspan="6" class="empty-state">No category sales recorded in this period.</td>
              </tr>
              <tr v-for="cat in pnl.categoryBreakdown" :key="cat.category_name">
                <td class="font-semibold">{{ cat.category_name }}</td>
                <td class="text-right font-mono">{{ cat.units_sold }}</td>
                <td class="text-right font-mono">{{ formatCurrency(cat.revenue_bdt) }}</td>
                <td class="text-right font-mono text-muted">{{ formatCurrency(cat.cogs_bdt) }}</td>
                <td class="text-right font-mono font-semibold text-success">{{ formatCurrency(cat.gross_profit_bdt) }}</td>
                <td class="text-right">
                  <span class="margin-pill" :class="cat.margin_percent >= 25 ? 'pill-high' : 'pill-med'">
                    {{ cat.margin_percent }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top SKUs by Gross Profit Table -->
      <div class="section-card">
        <div class="section-card-header">
          <h2 class="section-heading">Top Products by Gross Profit Contribution</h2>
          <span class="section-subtext">Highest profit-generating items in this period</span>
        </div>

        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th class="text-right">Units Sold</th>
                <th class="text-right">Revenue (BDT)</th>
                <th class="text-right">COGS (BDT)</th>
                <th class="text-right">Gross Profit</th>
                <th class="text-right">Margin %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="pnl.topPerformingSkus.length === 0">
                <td colspan="7" class="empty-state">No products sold in this period.</td>
              </tr>
              <tr v-for="sku in pnl.topPerformingSkus" :key="sku.sku">
                <td class="font-mono text-xs font-semibold">{{ sku.sku }}</td>
                <td>{{ sku.product_name }}</td>
                <td class="text-right font-mono">{{ sku.units_sold }}</td>
                <td class="text-right font-mono">{{ formatCurrency(sku.revenue_bdt) }}</td>
                <td class="text-right font-mono text-muted">{{ formatCurrency(sku.cogs_bdt) }}</td>
                <td class="text-right font-mono font-semibold text-success">{{ formatCurrency(sku.gross_profit_bdt) }}</td>
                <td class="text-right font-mono font-semibold">{{ sku.margin_percent }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.trading-pnl-page {
  padding: 1.5rem;
}

.breadcrumb {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.5rem;

  a {
    color: #3b82f6;
    text-decoration: none;
    &:hover { text-decoration: underline; }
  }
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.page-subtitle {
  color: #64748b;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.filter-controls-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.period-pills {
  display: flex;
  gap: 0.35rem;
}

.pill-btn {
  padding: 0.4rem 0.85rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  background-color: #f8fafc;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease-in-out;

  &.active {
    background-color: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
  }
}

.custom-date-picker {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-input {
  padding: 0.35rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
}

.to-separator {
  font-size: 0.8125rem;
  color: #64748b;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 600;
  font-size: 0.8125rem;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease-in-out;

  &.btn-sm {
    padding: 0.35rem 0.75rem;
  }

  &.btn-primary {
    background-color: #3b82f6;
    color: #ffffff;
    &:hover { background-color: #2563eb; }
  }

  &.btn-secondary {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    &:hover { background-color: #e2e8f0; }
  }
}

/* Waterfall cards */
.waterfall-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.waterfall-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;

  .metric-label {
    font-size: 0.8125rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
  }

  .metric-value {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0.5rem 0;
    color: #0f172a;
  }

  .metric-sub {
    font-size: 0.75rem;
    color: #94a3b8;
  }

  &.card-gross-profit {
    border-color: #a7f3d0;
    background: #f0fdf4;
  }
}

.margin-badge {
  display: inline-block;
  align-self: flex-start;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;

  &.margin-high {
    background-color: #d1fae5;
    color: #047857;
  }

  &.margin-medium {
    background-color: #fef3c7;
    color: #b45309;
  }
}

/* Secondary Cost Grid */
.secondary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.secondary-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;

  .label {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
  }

  .value {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0.35rem 0;
  }

  .sub-label {
    font-size: 0.75rem;
    color: #94a3b8;
  }

  &.card-net-profit {
    border-color: #93c5fd;
    background: #eff6ff;
  }
}

/* Section Cards & Tables */
.section-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  margin-bottom: 2rem;
  overflow: hidden;
}

.section-card-header {
  padding: 1.25rem;
  border-bottom: 1px solid #e2e8f0;

  .section-heading {
    font-size: 1.125rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
  }

  .section-subtext {
    font-size: 0.8125rem;
    color: #64748b;
  }
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.8125rem;

  th {
    background-color: #f8fafc;
    color: #475569;
    padding: 0.625rem 1rem;
    font-weight: 600;
    border-bottom: 1px solid #e2e8f0;
  }

  td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
  }

  tr:last-child td {
    border-bottom: none;
  }

  .text-right { text-align: right; }
  .font-mono { font-family: monospace; }
  .font-semibold { font-weight: 600; }
  .font-bold { font-weight: 700; }
  .text-danger { color: #dc2626; }
  .text-success { color: #16a34a; }
  .text-muted { color: #64748b; }
}

.margin-pill {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 0.25rem;
  font-weight: 700;
  font-size: 0.75rem;

  &.pill-high {
    background-color: #ecfdf5;
    color: #059669;
  }

  &.pill-med {
    background-color: #fffbeb;
    color: #d97706;
  }
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #94a3b8;
}

@media print {
  .no-print {
    display: none !important;
  }

  .trading-pnl-page {
    padding: 0;
  }

  .waterfall-card, .secondary-card, .section-card {
    border: 1px solid #000000 !important;
  }
}
</style>
