<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ReturnItem {
  id: number;
  tax_period: string;
  return_type: string;
  status: string;
  taxpayer_bin: string;
  sales_standard_rated_vat_bdt: string;
  total_output_vat_bdt: string;
  local_purchase_vat_bdt: string;
  import_vat_rebatable_bdt: string;
  import_advance_tax_at_bdt: string;
  total_input_tax_credit_bdt: string;
  net_vat_payable_bdt: string;
  closing_balance_credit_bdt: string;
  submission_reference: string | null;
  submitted_at: string | null;
  treasury_challan_number: string | null;
  treasury_deposit_amount_bdt: string;
}

interface PaginatedReturns {
  data: ReturnItem[];
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
  returns: PaginatedReturns;
  currentPeriod: string;
}>();

const compileForm = useForm({
  tax_period: props.currentPeriod,
  force: false,
});

const handleCompile = () => {
  compileForm.post('/admin/tax-compliance/mushak-9-1/compile', {
    preserveScroll: true,
  });
};

// Modal for Submission
const selectedReturn = ref<ReturnItem | null>(null);
const submitForm = useForm({
  treasury_challan_number: '',
  treasury_challan_date: '',
  treasury_deposit_amount_bdt: '',
});

const openSubmitModal = (ret: ReturnItem) => {
  selectedReturn.value = ret;
  submitForm.treasury_deposit_amount_bdt = ret.net_vat_payable_bdt;
  submitForm.treasury_challan_number = '';
  submitForm.treasury_challan_date = new Date().toISOString().split('T')[0];
};

const closeSubmitModal = () => {
  selectedReturn.value = null;
};

const handleSubmitToNbr = () => {
  if (!selectedReturn.value) return;
  submitForm.post(`/admin/tax-compliance/mushak-9-1/${selectedReturn.value.id}/submit`, {
    preserveScroll: true,
    onSuccess: () => closeSubmitModal(),
  });
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
  });
};
</script>

<template>
  <AdminLayout>
    <Head title="Monthly Mushak 9.1 VAT Returns" />

    <div class="mushak-91-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin/tax-compliance">Tax Compliance</Link> / <span>Mushak 9.1</span>
          </div>
          <h1 class="page-title">Monthly VAT Returns (Mushak 9.1)</h1>
          <p class="page-subtitle">Compile, review rebatable input credits, and submit statutory monthly returns.</p>
        </div>
      </div>

      <!-- Compiler Card -->
      <div class="compiler-card">
        <div class="card-title">Compile / Recalculate Return</div>
        <form @submit.prevent="handleCompile" class="compile-form">
          <div class="form-group">
            <label>Tax Period (YYYY-MM)</label>
            <input
              v-model="compileForm.tax_period"
              type="text"
              placeholder="e.g. 2026-10"
              class="form-input"
              required
            />
          </div>

          <div class="checkbox-group">
            <label>
              <input v-model="compileForm.force" type="checkbox" />
              Force recalculate if already compiled
            </label>
          </div>

          <button type="submit" class="btn-compile" :disabled="compileForm.processing">
            {{ compileForm.processing ? 'Compiling Return...' : '⚙️ Compile Monthly Return' }}
          </button>
        </form>
      </div>

      <!-- Returns Table -->
      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Period</th>
              <th>Status</th>
              <th>Total Output VAT</th>
              <th>Local Input Credit</th>
              <th>Import Credit (VAT+AT)</th>
              <th>Total Input Credit</th>
              <th>Net Tax Math</th>
              <th>Submission Reference</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ret in returns.data" :key="ret.id">
              <td class="font-bold text-primary">{{ ret.tax_period }}</td>
              <td>
                <span class="status-pill" :class="ret.status">{{ ret.status.toUpperCase() }}</span>
              </td>
              <td>{{ formatCurrency(ret.total_output_vat_bdt) }}</td>
              <td>{{ formatCurrency(ret.local_purchase_vat_bdt) }}</td>
              <td>
                {{ formatCurrency(parseFloat(ret.import_vat_rebatable_bdt) + parseFloat(ret.import_advance_tax_at_bdt)) }}
                <div class="text-xs text-muted">AT: {{ formatCurrency(ret.import_advance_tax_at_bdt) }}</div>
              </td>
              <td class="font-bold text-success">{{ formatCurrency(ret.total_input_tax_credit_bdt) }}</td>
              <td>
                <div v-if="parseFloat(ret.net_vat_payable_bdt) > 0" class="text-danger font-bold">
                  Payable: {{ formatCurrency(ret.net_vat_payable_bdt) }}
                </div>
                <div v-else class="text-success font-bold">
                  Credit: {{ formatCurrency(ret.closing_balance_credit_bdt) }}
                </div>
              </td>
              <td>
                <div v-if="ret.submission_reference" class="font-mono text-xs">
                  {{ ret.submission_reference }}
                  <div class="text-muted" v-if="ret.submitted_at">{{ formatDate(ret.submitted_at) }}</div>
                </div>
                <span v-else class="text-muted">Unsubmitted</span>
              </td>
              <td>
                <button
                  v-if="ret.status !== 'submitted'"
                  @click="openSubmitModal(ret)"
                  class="btn-submit"
                >
                  Submit to NBR
                </button>
                <span v-else class="badge-done">✓ Submitted</span>
              </td>
            </tr>
            <tr v-if="returns.data.length === 0">
              <td colspan="9" class="text-center text-muted py-6">No Mushak 9.1 returns compiled yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Submission Modal -->
      <div v-if="selectedReturn" class="modal-backdrop">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3>Submit Mushak 9.1 (Period: {{ selectedReturn.tax_period }})</h3>
            <button @click="closeSubmitModal" class="close-btn">&times;</button>
          </div>
          <form @submit.prevent="handleSubmitToNbr" class="modal-body">
            <div class="summary-box">
              <div><strong>Net VAT Payable:</strong> {{ formatCurrency(selectedReturn.net_vat_payable_bdt) }}</div>
              <div><strong>Closing Balance Credit:</strong> {{ formatCurrency(selectedReturn.closing_balance_credit_bdt) }}</div>
            </div>

            <div class="form-group">
              <label>Treasury Challan Number (if VAT deposited)</label>
              <input
                v-model="submitForm.treasury_challan_number"
                type="text"
                placeholder="e.g. TR-2026-CHAL-99881"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Treasury Challan Date</label>
              <input
                v-model="submitForm.treasury_challan_date"
                type="date"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Deposit Amount (৳ BDT)</label>
              <input
                v-model="submitForm.treasury_deposit_amount_bdt"
                type="number"
                step="0.01"
                class="form-input"
              />
            </div>

            <div class="modal-footer">
              <button type="button" @click="closeSubmitModal" class="btn-cancel">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="submitForm.processing">
                {{ submitForm.processing ? 'Submitting...' : 'Confirm Submission' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.mushak-91-page {
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

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
}

.compiler-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.card-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: #1f2937;
}

.compile-form {
  display: flex;
  align-items: flex-end;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;

  label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #4b5563;
  }
}

.form-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.checkbox-group {
  display: flex;
  align-items: center;
  font-size: 0.8125rem;
  color: #4b5563;
  margin-bottom: 0.5rem;
}

.btn-compile {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 0.5rem 1.25rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;

  &:hover { background: #1d4ed8; }
  &:disabled { opacity: 0.6; }
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

.status-pill {
  font-size: 0.7rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-weight: 700;
  &.finalized { background: #dbeafe; color: #1e40af; }
  &.submitted { background: #dcfce7; color: #166534; }
  &.draft { background: #fef3c7; color: #92400e; }
}

.text-danger { color: #dc2626; }
.text-success { color: #16a34a; }
.text-muted { color: #6b7280; }
.text-xs { font-size: 0.7rem; }
.font-mono { font-family: monospace; }
.font-bold { font-weight: 600; }
.text-primary { color: #2563eb; }

.btn-submit {
  font-size: 0.75rem;
  background: #10b981;
  color: #ffffff;
  border: none;
  padding: 0.25rem 0.625rem;
  border-radius: 0.25rem;
  font-weight: 600;
  cursor: pointer;

  &:hover { background: #059669; }
}

.badge-done {
  font-size: 0.75rem;
  color: #16a34a;
  font-weight: 600;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-dialog {
  background: #ffffff;
  border-radius: 0.5rem;
  width: 100%;
  max-width: 500px;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  padding: 1rem 1.25rem;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;

  h3 {
    font-size: 1rem;
    font-weight: 700;
  }
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
}

.modal-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.summary-box {
  background: #f3f4f6;
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  display: flex;
  justify-content: space-between;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1rem;
}

.btn-cancel {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  cursor: pointer;
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
}
</style>

