<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface RmaItem {
  id: number;
  item_name?: string;
  sku?: string;
  quantity_requested?: number;
  quantity_approved?: number;
  unit_price_bdt?: string;
}

interface CreditNoteData {
  id: number;
  credit_note_number: string;
  returned_vat_amount_bdt: string;
  total_credit_amount_bdt: string;
  tax_period: string;
  issue_date: string;
}

interface RmaRecord {
  id: number;
  rma_number: string;
  order_id: number;
  status: string;
  reason_category?: string;
  reason?: string;
  customer_notes: string | null;
  admin_notes: string | null;
  inspection_outcome: string | null;
  refund_amount_bdt: string | null;
  refund_status: string;
  refund_method: string | null;
  created_at: string;
  order?: {
    id: number;
    order_number: string;
    total_bdt: string;
  };
  credit_note?: CreditNoteData;
  creditNote?: CreditNoteData;
  items?: RmaItem[];
}

interface WarehouseOption {
  id: number;
  name: string;
  code: string;
  is_primary: boolean;
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
  warehouses?: WarehouseOption[];
  filters: {
    status?: string;
  };
}>();

const status = ref(props.filters.status || '');
const showInspectModal = ref(false);
const activeRma = ref<RmaRecord | null>(null);

const inspectForm = useForm({
  inspection_outcome: 'restock_available',
  warehouse_id: props.warehouses?.find(w => w.is_primary)?.id || props.warehouses?.[0]?.id || null,
  refund_method: 'original_gateway',
  admin_notes: '',
});

const handleFilter = () => {
  router.get('/admin/tax-compliance/rma', {
    status: status.value || undefined,
  }, { preserveState: true });
};

const openInspectModal = (rma: RmaRecord) => {
  activeRma.value = rma;
  inspectForm.inspection_outcome = 'restock_available';
  inspectForm.warehouse_id = props.warehouses?.find(w => w.is_primary)?.id || props.warehouses?.[0]?.id || null;
  inspectForm.refund_method = 'original_gateway';
  inspectForm.admin_notes = '';
  showInspectModal.value = true;
};

const closeInspectModal = () => {
  showInspectModal.value = false;
  activeRma.value = null;
};

const submitInspection = () => {
  if (!activeRma.value) return;

  inspectForm.post(`/admin/tax-compliance/rma/${activeRma.value.id}/inspect`, {
    preserveScroll: true,
    onSuccess: () => {
      closeInspectModal();
    },
  });
};

const getCreditNote = (rma: RmaRecord): CreditNoteData | undefined => {
  return rma.credit_note || rma.creditNote;
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
    case 'completed':
    case 'refunded':
      return 'badge-success';
    case 'inspected':
    case 'item_received':
    case 'approved':
      return 'badge-info';
    case 'rejected':
    case 'cancelled':
      return 'badge-danger';
    default:
      return 'badge-warning';
  }
};
</script>

<template>
  <AdminLayout>
    <Head title="Customer Returns (RMA) & Mushak 6.7 Credit Notes" />

    <div class="rma-index-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin/tax-compliance">Tax Compliance</Link> / <span>Customer Returns (RMA)</span>
          </div>
          <h1 class="page-title">Customer Returns & Mushak 6.7 Credit Notes</h1>
          <p class="page-subtitle">
            Customer return inspections, automated warehouse restocking, statutory NBR Credit Notes (মূসক-৬.৭), and decreasing VAT adjustments (হ্রাসকারী সমন্বয়).
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/orders/scan-returns" class="btn btn-primary">
            &#128269; Barcode Returns Console
          </Link>
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
            <option value="completed">Completed / Credited</option>
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
              <th>Inspection Outcome</th>
              <th class="text-right">Refund Value</th>
              <th>Statutory Mushak 6.7</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rmas.data.length === 0">
              <td colspan="9" class="empty-state">
                No customer returns (RMA) found.
              </td>
            </tr>
            <tr v-for="rma in rmas.data" :key="rma.id">
              <td class="font-mono font-semibold text-primary">#{{ rma.rma_number }}</td>
              <td>{{ formatDate(rma.created_at) }}</td>
              <td>
                <span v-if="rma.order" class="font-mono text-xs font-semibold">
                  #{{ rma.order.order_number }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <div class="text-sm font-medium">{{ rma.reason_category || rma.reason || 'Customer Return' }}</div>
                <div v-if="rma.customer_notes" class="text-muted text-xs truncate max-w-xs">{{ rma.customer_notes }}</div>
              </td>
              <td>
                <span class="badge" :class="getStatusBadgeClass(rma.status)">
                  {{ rma.status.replace('_', ' ').toUpperCase() }}
                </span>
              </td>
              <td>
                <span v-if="rma.inspection_outcome" class="text-xs font-semibold" :class="rma.inspection_outcome === 'restock_available' ? 'text-success' : 'text-danger'">
                  {{ rma.inspection_outcome.replace('_', ' ').toUpperCase() }}
                </span>
                <span v-else class="text-muted text-xs">Pending Inspection</span>
              </td>
              <td class="text-right font-semibold">{{ formatCurrency(rma.refund_amount_bdt) }}</td>
              <td>
                <div v-if="getCreditNote(rma)" class="credit-note-badge">
                  <Link :href="`/admin/tax-compliance/mushak-6-7/${getCreditNote(rma)?.id}`" class="cn-link" target="_blank">
                    <span class="cn-num font-mono font-bold">{{ getCreditNote(rma)?.credit_note_number }}</span>
                    <span class="print-icon">&#128438;&#65039;</span>
                  </Link>
                  <div class="text-xs text-muted">
                    VAT Red.: ৳{{ Number(getCreditNote(rma)?.returned_vat_amount_bdt || 0).toFixed(2) }}
                  </div>
                </div>
                <span v-else class="text-muted text-xs">No Credit Note</span>
              </td>
              <td class="text-center">
                <button
                  v-if="rma.status !== 'completed' && !getCreditNote(rma)"
                  @click="openInspectModal(rma)"
                  class="btn-action-inspect"
                >
                  &#9874;&#65039; Inspect & Credit
                </button>
                <Link
                  v-else-if="getCreditNote(rma)"
                  :href="`/admin/tax-compliance/mushak-6-7/${getCreditNote(rma)?.id}`"
                  class="btn-action-view"
                  target="_blank"
                >
                  View M6.7
                </Link>
                <span v-else class="text-muted text-xs">—</span>
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

      <!-- Inspection & Credit Note Modal -->
      <div v-if="showInspectModal && activeRma" class="modal-backdrop" @click.self="closeInspectModal">
        <div class="modal-card">
          <div class="modal-header">
            <div>
              <h2 class="modal-title">Inspect Return & Issue Mushak 6.7 Credit Note</h2>
              <p class="modal-subtitle">RMA #{{ activeRma.rma_number }} &bull; Order #{{ activeRma.order?.order_number }}</p>
            </div>
            <button @click="closeInspectModal" class="btn-close">&times;</button>
          </div>

          <form @submit.prevent="submitInspection" class="modal-body">
            <div class="info-alert">
              <strong>Statutory Compliance Note:</strong> Approving this return will issue a legal <strong>NBR Mushak 6.7 Credit Note</strong>, atomically reverse/restock inventory into your selected warehouse (if resalable), and register a decreasing tax adjustment for your monthly <strong>Mushak 9.1 VAT Return</strong>.
            </div>

            <div class="form-group">
              <label class="form-label">Inspection Outcome & Physical Goods Condition <span class="required">*</span></label>
              <select v-model="inspectForm.inspection_outcome" class="form-control" required>
                <option value="restock_available">&#9989; Resalable / Good Condition &mdash; Restock into Warehouse</option>
                <option value="quarantine_damaged">&#9888;&#65039; Damaged in Transit &mdash; Quarantine for Supplier Warranty</option>
                <option value="scrap">&#10060; Scrap / Broken &mdash; Write-off as Damage Loss</option>
              </select>
            </div>

            <div v-if="inspectForm.inspection_outcome === 'restock_available' && props.warehouses && props.warehouses.length > 0" class="form-group">
              <label class="form-label">Target Warehouse for Physical Restocking <span class="required">*</span></label>
              <select v-model="inspectForm.warehouse_id" class="form-control" required>
                <option v-for="w in props.warehouses" :key="w.id" :value="w.id">
                  {{ w.name }} ({{ w.code }}) {{ w.is_primary ? '★ Primary Hub' : '' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Customer Refund Channel</label>
              <select v-model="inspectForm.refund_method" class="form-control">
                <option value="original_gateway">Original Payment Gateway (bKash/Nagad/SSLCommerz)</option>
                <option value="bkash">Direct bKash MFS Disbursement</option>
                <option value="nagad">Direct Nagad MFS Disbursement</option>
                <option value="bank_transfer">Bank Electronic Transfer</option>
                <option value="store_credit">Customer Wallet / Store Credit</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Admin Inspection Notes & Reason Citation</label>
              <textarea
                v-model="inspectForm.admin_notes"
                class="form-control font-sans"
                rows="3"
                placeholder="e.g. Package verified in warehouse by QC team. Sealed unit returned intact without physical damage."
              ></textarea>
            </div>

            <div class="modal-footer">
              <button type="button" @click="closeInspectModal" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="inspectForm.processing">
                <span v-if="inspectForm.processing">Issuing Credit Note...</span>
                <span v-else>&#10003; Approve Inspection & Issue Mushak 6.7</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.rma-index-page {
  padding: 1.5rem;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
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
}

.page-subtitle {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.filter-card {
  background: #ffffff;
  padding: 1rem 1.25rem;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  margin-bottom: 1.25rem;
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
  min-width: 200px;
}

.table-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.875rem;

  th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
  }

  td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }

  tbody tr:hover {
    background: #f8fafc;
  }
}

.credit-note-badge {
  .cn-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #2563eb;
    text-decoration: none;
    font-size: 0.8rem;
    &:hover { text-decoration: underline; color: #1d4ed8; }
  }

  .print-icon {
    font-size: 0.85rem;
  }
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 700;

  &.badge-success { background: #dcfce7; color: #15803d; }
  &.badge-info { background: #e0f2fe; color: #0369a1; }
  &.badge-warning { background: #fef3c7; color: #b45309; }
  &.badge-danger { background: #fee2e2; color: #b91c1c; }
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;

  &.btn-primary {
    background: #2563eb;
    color: #ffffff;
    &:hover { background: #1d4ed8; }
  }

  &.btn-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    &:hover { background: #e2e8f0; }
  }
}

.btn-action-inspect {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  padding: 0.35rem 0.65rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    background: #dcfce7;
    border-color: #86efac;
  }
}

.btn-action-view {
  display: inline-block;
  background: #f8fafc;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  padding: 0.35rem 0.65rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-decoration: none;

  &:hover {
    background: #eff6ff;
  }
}

.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  background: #ffffff;
  border-top: 1px solid #e2e8f0;
  font-size: 0.875rem;

  .pagination-links {
    display: flex;
    gap: 0.25rem;
  }

  .page-link {
    padding: 0.375rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.25rem;
    text-decoration: none;
    color: #334155;

    &.active {
      background: #2563eb;
      color: #ffffff;
      border-color: #2563eb;
    }

    &.disabled {
      color: #94a3b8;
      pointer-events: none;
    }
  }
}

/* Modal Styling */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 1rem;
}

.modal-card {
  background: #ffffff;
  width: 100%;
  max-width: 600px;
  border-radius: 0.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;

  .modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #0f172a;
  }

  .modal-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.2rem;
  }

  .btn-close {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    &:hover { color: #0f172a; }
  }
}

.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;

  .info-alert {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
    font-size: 0.8rem;
    color: #166534;
    line-height: 1.45;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  .form-label {
    font-size: 0.825rem;
    font-weight: 600;
    color: #334155;

    .required {
      color: #ef4444;
    }
  }

  .form-control {
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    width: 100%;

    &:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }
  }
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 0.5rem;
  border-top: 1px solid #f1f5f9;
}
</style>
