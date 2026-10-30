<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface InvoiceItem {
  id: number;
  invoice_number: string;
  tax_period: string;
  issue_date: string;
  customer_name: string;
  customer_phone: string;
  subtotal_exclusive_bdt: string;
  vat_total_bdt: string;
  grand_total_bdt: string;
  status: string;
  order?: {
    id: number;
    order_number: string;
  };
}

interface PaginatedInvoices {
  data: InvoiceItem[];
  current_page: number;
  last_page: number;
  total: number;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
}

const props = defineProps<{
  invoices: PaginatedInvoices;
  filters: {
    search?: string;
    tax_period?: string;
  };
}>();

const search = ref(props.filters.search || '');
const taxPeriod = ref(props.filters.tax_period || '');

const handleFilter = () => {
  router.get('/admin/tax-compliance/mushak-6-3', {
    search: search.value || undefined,
    tax_period: taxPeriod.value || undefined,
  }, { preserveState: true });
};

const formatCurrency = (val: string | number) => {
  const num = typeof val === 'string' ? parseFloat(val) : val;
  return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', minimumFractionDigits: 2 }).format(num || 0);
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<template>
  <AdminLayout>
    <Head title="Mushak 6.3 Tax Invoices" />

    <div class="mushak-index-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin/tax-compliance">Tax Compliance</Link> / <span>Mushak 6.3</span>
          </div>
          <h1 class="page-title">Statutory Tax Invoices (Mushak 6.3)</h1>
          <p class="page-subtitle">Official NBR Tax Invoices (কর চালানপত্র) issued for outward sales.</p>
        </div>
      </div>

      <!-- Filters Bar -->
      <div class="filter-card">
        <div class="filter-inputs">
          <input
            v-model="search"
            type="text"
            placeholder="Search by Invoice #, Customer or Phone..."
            class="input-search"
            @keyup.enter="handleFilter"
          />
          <input
            v-model="taxPeriod"
            type="text"
            placeholder="Tax Period (YYYY-MM)"
            class="input-period"
            @keyup.enter="handleFilter"
          />
          <button @click="handleFilter" class="btn-primary">Filter</button>
        </div>
        <div class="total-count">Total Invoices: <strong>{{ invoices.total }}</strong></div>
      </div>

      <!-- Invoices Table -->
      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice Number</th>
              <th>Order #</th>
              <th>Customer</th>
              <th>Period</th>
              <th>Issue Date</th>
              <th>Exclusive</th>
              <th>VAT (15%/5%)</th>
              <th>Grand Total</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in invoices.data" :key="inv.id">
              <td class="font-mono text-primary font-bold">{{ inv.invoice_number }}</td>
              <td>{{ inv.order?.order_number ?? 'N/A' }}</td>
              <td>
                <div class="customer-info">
                  <span>{{ inv.customer_name }}</span>
                  <span class="text-muted text-xs">{{ inv.customer_phone }}</span>
                </div>
              </td>
              <td><span class="period-pill">{{ inv.tax_period }}</span></td>
              <td>{{ formatDate(inv.issue_date) }}</td>
              <td>{{ formatCurrency(inv.subtotal_exclusive_bdt) }}</td>
              <td class="text-vat">{{ formatCurrency(inv.vat_total_bdt) }}</td>
              <td class="font-bold">{{ formatCurrency(inv.grand_total_bdt) }}</td>
              <td>
                <span class="status-badge" :class="inv.status">{{ inv.status }}</span>
              </td>
              <td>
                <Link :href="`/admin/tax-compliance/mushak-6-3/${inv.id}`" class="btn-view">
                  View / Print
                </Link>
              </td>
            </tr>
            <tr v-if="invoices.data.length === 0">
              <td colspan="10" class="text-center text-muted py-6">No Mushak 6.3 tax invoices found matching criteria.</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination-bar" v-if="invoices.links.length > 3">
          <template v-for="(link, index) in invoices.links" :key="index">
            <Link
              v-if="link.url"
              :href="link.url"
              class="page-link"
              :class="{ active: link.active }"
              v-html="link.label"
            />
            <span v-else class="page-link disabled" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.mushak-index-page {
  padding: 1.5rem;
}

.breadcrumb {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.25rem;

  a {
    color: #2563eb;
    text-decoration: none;
    &:hover { text-decoration: underline; }
  }
}

.header-section {
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
}

.filter-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.filter-inputs {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.input-search {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  width: 280px;
}

.input-period {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  width: 160px;
}

.btn-primary {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;

  &:hover {
    background: #1d4ed8;
  }
}

.total-count {
  font-size: 0.875rem;
  color: #4b5563;
}

.table-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;

  th, td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    text-align: left;
  }

  th {
    background: #f9fafb;
    font-weight: 600;
    font-size: 0.7rem;
    color: #6b7280;
    text-transform: uppercase;
  }
}

.customer-info {
  display: flex;
  flex-direction: column;
}

.period-pill {
  background: #f3f4f6;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-family: monospace;
}

.text-vat {
  color: #d97706;
  font-weight: 600;
}

.status-badge {
  font-size: 0.7rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-weight: 600;
  &.issued { background: #dcfce7; color: #166534; }
  &.cancelled { background: #fee2e2; color: #991b1b; }
}

.btn-view {
  font-size: 0.75rem;
  background: #eff6ff;
  color: #2563eb;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  text-decoration: none;
  font-weight: 600;

  &:hover {
    background: #dbeafe;
  }
}

.pagination-bar {
  display: flex;
  justify-content: flex-end;
  padding: 0.75rem 1rem;
  gap: 0.25rem;
  border-top: 1px solid #e5e7eb;
}

.page-link {
  padding: 0.375rem 0.75rem;
  border: 1px solid #e5e7eb;
  font-size: 0.75rem;
  border-radius: 0.25rem;
  text-decoration: none;
  color: #374151;

  &.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
  }

  &.disabled {
    color: #9ca3af;
    cursor: not-allowed;
  }
}
</style>

