<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface RmaItem {
  id: number;
  product_id: string;
  product_variant_id: string;
  quantity: number;
  return_reason: string;
  condition: string;
  inspection_status: string;
}

interface RmaRecord {
  id: number;
  rma_number: string;
  order_id: number;
  status: string;
  reason: string;
  customer_notes: string | null;
  inspection_notes: string | null;
  inspection_result: string | null;
  refund_amount_bdt: string | null;
  credit_note_id: number | null;
  created_at: string;
  order?: {
    id: number;
    order_number: string;
    grand_total: string;
  };
  credit_note?: {
    id: number;
    credit_note_number: string;
    vat_reduction_bdt: string;
    tax_period: string;
  };
  items?: RmaItem[];
}

interface PaginatedRmas {
  data: RmaRecord[];
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
  rmas: PaginatedRmas;
  filters: {
    status?: string;
  };
}>();

const status = ref(props.filters.status || '');

const handleFilter = () => {
  router.get('/admin/tax-compliance/rma', {
    status: status.value || undefined,
  }, { preserveState: true });
};

const formatCurrency = (val: string | number | null) => {
  if (!val) return '—';
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

const getStatusBadgeClass = (s: string) => {
  switch (s) {
    case 'refunded':
      return 'badge-success';
    case 'inspected':
    case 'item_received':
    case 'approved':
      return 'badge-info';
    case 'rejected':
      return 'badge-danger';
    default:
      return 'badge-warning';
  }
};
</script>

<template>
  <AdminLayout>
    <Head title="Customer Returns (RMA) & Credit Notes" />

    <div class="rma-index-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin/tax-compliance">Tax Compliance</Link> / <span>Customer Returns (RMA)</span>
          </div>
          <h1 class="page-title">Customer Returns & Mushak 6.7 Credit Notes</h1>
          <p class="page-subtitle">RMA processing, warehouse inspection, decreasing tax adjustments (হ্রাসকারী সমন্বয়), and statutory credit notes.</p>
        </div>
      </div>

      <!-- Filters Bar -->
      <div class="filter-card">
        <div class="filter-inputs">
          <select v-model="status" class="input-select" @change="handleFilter">
            <option value="">All RMA Statuses</option>
            <option value="requested">Requested</option>
            <option value="approved">Approved</option>
            <option value="item_received">Item Received</option>
            <option value="inspected">Inspected</option>
            <option value="refunded">Refunded</option>
            <option value="rejected">Rejected</option>
          </select>
          <button class="btn btn-secondary" @click="handleFilter">
            Apply Filter
          </button>
        </div>
      </div>

      <!-- RMA Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>RMA #</th>
              <th>Date</th>
              <th>Order Ref</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Inspection Result</th>
              <th class="text-right">Refund Amount</th>
              <th>Statutory Credit Note</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rmas.data.length === 0">
              <td colspan="8" class="empty-state">
                No RMA customer returns found.
              </td>
            </tr>
            <tr v-for="rma in rmas.data" :key="rma.id">
              <td class="font-mono font-semibold">{{ rma.rma_number }}</td>
              <td>{{ formatDate(rma.created_at) }}</td>
              <td>
                <span v-if="rma.order" class="font-mono text-xs">
                  #{{ rma.order.order_number }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <div class="text-sm font-medium">{{ rma.reason }}</div>
                <div v-if="rma.customer_notes" class="text-muted text-xs truncate max-w-xs">{{ rma.customer_notes }}</div>
              </td>
              <td>
                <span class="badge" :class="getStatusBadgeClass(rma.status)">
                  {{ rma.status.replace('_', ' ').toUpperCase() }}
                </span>
              </td>
              <td>
                <span v-if="rma.inspection_result" class="text-xs font-semibold" :class="rma.inspection_result === 'restockable' ? 'text-success' : 'text-danger'">
                  {{ rma.inspection_result.toUpperCase() }}
                </span>
                <span v-else class="text-muted text-xs">Pending Inspection</span>
              </td>
              <td class="text-right font-semibold">{{ formatCurrency(rma.refund_amount_bdt) }}</td>
              <td>
                <div v-if="rma.credit_note" class="credit-note-badge">
                  <div class="font-mono text-xs font-semibold text-primary">
                    {{ rma.credit_note.credit_note_number }}
                  </div>
                  <div class="text-xs text-muted">
                    VAT Red.: ৳{{ Number(rma.credit_note.vat_reduction_bdt).toFixed(2) }}
                  </div>
                </div>
                <span v-else class="text-muted text-xs">No Credit Note</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="rmas.last_page > 1" class="pagination-bar">
        <div class="pagination-info">
          Showing page {{ rmas.current_page }} of {{ rmas.last_page }} (Total {{ rmas.total }} RMAs)
        </div>
        <div class="pagination-links">
          <Link
            v-for="(link, idx) in rmas.links"
            :key="idx"
            :href="link.url || '#'"
            class="page-link"
            :class="{ active: link.active, disabled: !link.url }"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.rma-index-page {
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

.filter-card {
  margin-top: 1.5rem;
  background: #ffffff;
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
}

.filter-inputs {
  display: flex;
  gap: 0.75rem;
}

.input-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background: #ffffff;

  &:focus {
    outline: none;
    border-color: #3b82f6;
  }
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  border: none;
  transition: background-color 0.15s ease-in-out;

  &.btn-secondary {
    background-color: #e2e8f0;
    color: #334155;
    &:hover { background-color: #cbd5e1; }
  }
}

.table-container {
  margin-top: 1.5rem;
  background: #ffffff;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.875rem;

  th {
    background-color: #f8fafc;
    color: #475569;
    padding: 0.75rem 1rem;
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
  .font-medium { font-weight: 500; }
  .text-muted { color: #64748b; }
  .text-xs { font-size: 0.75rem; }
  .text-sm { font-size: 0.875rem; }
  .text-success { color: #16a34a; }
  .text-danger { color: #dc2626; }
  .text-primary { color: #2563eb; }
  .truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .max-w-xs { max-width: 20rem; }
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #94a3b8;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;

  &.badge-success {
    background-color: #ecfdf5;
    color: #059669;
  }

  &.badge-info {
    background-color: #eff6ff;
    color: #2563eb;
  }

  &.badge-warning {
    background-color: #fffbeb;
    color: #d97706;
  }

  &.badge-danger {
    background-color: #fef2f2;
    color: #dc2626;
  }
}

.credit-note-badge {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.25rem;
  padding: 0.25rem 0.5rem;
}

.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  font-size: 0.875rem;
  color: #64748b;
}

.pagination-links {
  display: flex;
  gap: 0.25rem;
}

.page-link {
  padding: 0.375rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  text-decoration: none;
  color: #334155;

  &.active {
    background-color: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
  }

  &.disabled {
    pointer-events: none;
    opacity: 0.5;
  }

  &:hover:not(.active):not(.disabled) {
    background-color: #f8fafc;
  }
}
</style>

