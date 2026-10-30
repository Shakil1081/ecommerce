<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Stats {
  current_period: string;
  mushak_6_3_count: number;
  total_sales_vat_bdt: string;
  mushak_9_1_status: string;
  mushak_6_6_vds_count: number;
  total_vds_bdt: string;
  mushak_6_5_transfers_count: number;
  rma_returns_count: number;
  mushak_6_7_credit_notes_count: number;
  mushak_6_8_debit_notes_count: number;
}

interface InvoiceSummary {
  id: number;
  invoice_number: string;
  issue_date: string;
  customer_name: string;
  grand_total_bdt: string;
  vat_total_bdt: string;
  status: string;
}

interface ReturnSummary {
  id: number;
  tax_period: string;
  status: string;
  total_output_vat_bdt: string;
  total_input_tax_credit_bdt: string;
  net_vat_payable_bdt: string;
  closing_balance_credit_bdt: string;
}

interface RmaSummary {
  id: number;
  rma_number: string;
  created_at: string;
  reason_category: string;
  status: string;
  refund_amount_bdt: string;
}

defineProps<{
  stats: Stats;
  recentInvoices: InvoiceSummary[];
  recentReturns: ReturnSummary[];
  recentRmas: RmaSummary[];
}>();

const formatCurrency = (val: string | number) => {
  const num = typeof val === 'string' ? parseFloat(val) : val;
  return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', minimumFractionDigits: 2 }).format(num || 0);
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};
</script>

<template>
  <AdminLayout>
    <Head title="NBR VAT & Statutory Tax Compliance" />

    <div class="tax-dashboard">
      <div class="header-section">
        <div>
          <h1 class="page-title">NBR VAT & Tax Compliance Engine</h1>
          <p class="page-subtitle">
            Statutory tax invoices (Mushak 6.3), monthly VAT returns (Mushak 9.1), VDS certificates (6.6), and credit/debit notes.
          </p>
        </div>
        <div class="period-badge">
          <span class="badge-label">Active Tax Period</span>
          <span class="badge-value">{{ stats.current_period }}</span>
        </div>
      </div>

      <!-- Quick Metrics Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">🧾</div>
          <div class="stat-content">
            <span class="stat-label">Mushak 6.3 Invoices</span>
            <div class="stat-value">{{ stats.mushak_6_3_count }}</div>
            <span class="stat-meta">Output VAT: {{ formatCurrency(stats.total_sales_vat_bdt) }}</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <span class="stat-label">Mushak 9.1 Status</span>
            <div class="stat-value status-tag" :class="stats.mushak_9_1_status">
              {{ stats.mushak_9_1_status.toUpperCase() }}
            </div>
            <span class="stat-meta">Period: {{ stats.current_period }}</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">📑</div>
          <div class="stat-content">
            <span class="stat-label">Mushak 6.6 (VDS)</span>
            <div class="stat-value">{{ stats.mushak_6_6_vds_count }}</div>
            <span class="stat-meta">Withheld: {{ formatCurrency(stats.total_vds_bdt) }}</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">🔄</div>
          <div class="stat-content">
            <span class="stat-label">RMA Returns & Credit Notes</span>
            <div class="stat-value">{{ stats.rma_returns_count }}</div>
            <span class="stat-meta">6.7 Credit Notes: {{ stats.mushak_6_7_credit_notes_count }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Navigation Navigation Pills -->
      <div class="quick-nav">
        <Link href="/admin/tax-compliance/mushak-6-3" class="nav-pill">
          <span>🧾</span> Mushak 6.3 Invoices
        </Link>
        <Link href="/admin/tax-compliance/mushak-9-1" class="nav-pill">
          <span>📊</span> Mushak 9.1 Returns
        </Link>
        <Link href="/admin/tax-compliance/mushak-6-6" class="nav-pill">
          <span>📑</span> Mushak 6.6 Certificates
        </Link>
        <Link href="/admin/tax-compliance/rma" class="nav-pill">
          <span>🔄</span> Customer RMA
        </Link>
        <Link href="/admin/tax-compliance/registers" class="nav-pill">
          <span>📖</span> Registers (6.1 & 6.2)
        </Link>
      </div>

      <div class="tables-grid">
        <!-- Recent Invoices -->
        <div class="card-table">
          <div class="card-header">
            <h3>Recent Mushak 6.3 Invoices</h3>
            <Link href="/admin/tax-compliance/mushak-6-3" class="view-all-link">View All →</Link>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>VAT (BDT)</th>
                <th>Total</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in recentInvoices" :key="inv.id">
                <td class="font-mono text-primary">{{ inv.invoice_number }}</td>
                <td>{{ inv.customer_name }}</td>
                <td>{{ formatDate(inv.issue_date) }}</td>
                <td>{{ formatCurrency(inv.vat_total_bdt) }}</td>
                <td class="font-bold">{{ formatCurrency(inv.grand_total_bdt) }}</td>
                <td>
                  <Link :href="`/admin/tax-compliance/mushak-6-3/${inv.id}`" class="action-btn">
                    Print / View
                  </Link>
                </td>
              </tr>
              <tr v-if="recentInvoices.length === 0">
                <td colspan="6" class="text-center text-muted">No Mushak 6.3 invoices issued yet.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Recent Monthly Returns -->
        <div class="card-table">
          <div class="card-header">
            <h3>Monthly VAT Returns (Mushak 9.1)</h3>
            <Link href="/admin/tax-compliance/mushak-9-1" class="view-all-link">Manage Returns →</Link>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Period</th>
                <th>Status</th>
                <th>Output VAT</th>
                <th>Input Credit</th>
                <th>Net Payable</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ret in recentReturns" :key="ret.id">
                <td class="font-bold">{{ ret.tax_period }}</td>
                <td>
                  <span class="status-pill" :class="ret.status">{{ ret.status }}</span>
                </td>
                <td>{{ formatCurrency(ret.total_output_vat_bdt) }}</td>
                <td class="text-success">{{ formatCurrency(ret.total_input_tax_credit_bdt) }}</td>
                <td class="font-bold">
                  {{ parseFloat(ret.net_vat_payable_bdt) > 0 ? formatCurrency(ret.net_vat_payable_bdt) : 'Ref: ' + formatCurrency(ret.closing_balance_credit_bdt) }}
                </td>
              </tr>
              <tr v-if="recentReturns.length === 0">
                <td colspan="5" class="text-center text-muted">No Mushak 9.1 returns compiled yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.tax-dashboard {
  padding: 1.5rem;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 0.25rem;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
}

.period-badge {
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.badge-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #6b7280;
  font-weight: 600;
}

.badge-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.stat-icon {
  font-size: 2rem;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-label {
  font-size: 0.75rem;
  color: #6b7280;
  text-transform: uppercase;
  font-weight: 600;
}

.stat-value {
  font-size: 1.375rem;
  font-weight: 700;
  color: #111827;
}

.stat-meta {
  font-size: 0.75rem;
  color: #4b5563;
  margin-top: 0.25rem;
}

.status-tag {
  display: inline-block;
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  border-radius: 0.25rem;
  font-weight: 600;
  &.finalized { background: #dbeafe; color: #1e40af; }
  &.submitted { background: #dcfce7; color: #166534; }
  &.draft { background: #fef3c7; color: #92400e; }
}

.quick-nav {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.nav-pill {
  background: #ffffff;
  border: 1px solid #d1d5db;
  color: #374151;
  padding: 0.625rem 1rem;
  border-radius: 9999px;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;

  &:hover {
    border-color: #3b82f6;
    color: #2563eb;
    background: #eff6ff;
  }
}

.tables-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;

  @media (min-width: 1024px) {
    grid-template-columns: 1fr 1fr;
  }
}

.card-table {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  overflow: hidden;
}

.card-header {
  padding: 1rem 1.25rem;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;

  h3 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #111827;
  }
}

.view-all-link {
  font-size: 0.75rem;
  color: #2563eb;
  text-decoration: none;
  font-weight: 600;

  &:hover {
    text-decoration: underline;
  }
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;

  th, td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
  }

  th {
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.7rem;
    background: #fafafa;
  }
}

.font-mono { font-family: monospace; }
.font-bold { font-weight: 600; }
.text-primary { color: #2563eb; }
.text-success { color: #16a34a; }
.text-center { text-align: center; }
.text-muted { color: #9ca3af; }

.action-btn {
  font-size: 0.75rem;
  color: #2563eb;
  background: #eff6ff;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  text-decoration: none;
  font-weight: 500;

  &:hover {
    background: #dbeafe;
  }
}

.status-pill {
  font-size: 0.7rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-weight: 600;
  &.submitted { background: #dcfce7; color: #166534; }
  &.finalized { background: #dbeafe; color: #1e40af; }
  &.draft { background: #fef3c7; color: #92400e; }
}
</style>

