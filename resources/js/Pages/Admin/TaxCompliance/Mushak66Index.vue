<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CertificateItem {
  id: number;
  certificate_number: string;
  tax_period: string;
  issue_date: string;
  supplier_name: string;
  supplier_bin: string;
  source_mushak_6_3_number: string;
  source_mushak_6_3_date: string | null;
  procurement_nature: string;
  total_purchase_amount_bdt: string;
  vat_applicable_amount_bdt: string;
  vds_rate_percent: string;
  vds_deducted_amount_bdt: string;
  treasury_deposit_status: string;
  treasury_challan_number: string | null;
  treasury_deposit_date: string | null;
  supplier?: {
    id: number;
    name: string;
    bin_number: string;
  };
  local_purchase_order?: {
    id: number;
    po_number: string;
  };
}

interface PaginatedCertificates {
  data: CertificateItem[];
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
  certificates: PaginatedCertificates;
  filters: {
    search?: string;
  };
}>();

const search = ref(props.filters.search || '');

const handleFilter = () => {
  router.get('/admin/tax-compliance/mushak-6-6', {
    search: search.value || undefined,
  }, { preserveState: true });
};

const formatCurrency = (val: string | number) => {
  const num = typeof val === 'string' ? parseFloat(val) : val;
  return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', minimumFractionDigits: 2 }).format(num || 0);
};

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};
</script>

<template>
  <AdminLayout>
    <Head title="Mushak 6.6 VDS Withholding Certificates" />

    <div class="mushak-66-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin/tax-compliance">Tax Compliance</Link> / <span>Mushak 6.6</span>
          </div>
          <h1 class="page-title">VDS Withholding Certificates (Mushak 6.6)</h1>
          <p class="page-subtitle">উৎসে মূসক কর্তন সনদপত্র — Statutory certificates for VAT deducted at source on domestic procurement.</p>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-card">
        <div class="filter-inputs">
          <input
            v-model="search"
            type="text"
            placeholder="Search by Certificate #, Supplier Name or BIN..."
            class="input-search"
            @keyup.enter="handleFilter"
          />
          <button class="btn btn-primary" @click="handleFilter">
            Filter
          </button>
        </div>
      </div>

      <!-- Certificates Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Certificate #</th>
              <th>Issue Date</th>
              <th>Supplier / Vendor</th>
              <th>Supplier BIN</th>
              <th>PO / Mushak 6.3 Ref</th>
              <th class="text-right">Base Amount</th>
              <th class="text-right">VDS Rate</th>
              <th class="text-right">VDS Deducted</th>
              <th>Deposit Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="certificates.data.length === 0">
              <td colspan="9" class="empty-state">
                No Mushak 6.6 VDS certificates found.
              </td>
            </tr>
            <tr v-for="cert in certificates.data" :key="cert.id">
              <td class="font-mono font-semibold">{{ cert.certificate_number }}</td>
              <td>{{ formatDate(cert.issue_date) }}</td>
              <td>
                <div class="font-medium">{{ cert.supplier_name }}</div>
                <div class="text-muted text-xs">{{ cert.procurement_nature }}</div>
              </td>
              <td>
                <span class="bin-badge">{{ cert.supplier_bin }}</span>
              </td>
              <td>
                <div v-if="cert.local_purchase_order" class="font-mono text-xs">
                  PO: {{ cert.local_purchase_order.po_number }}
                </div>
                <div class="text-muted text-xs">6.3: {{ cert.source_mushak_6_3_number }}</div>
              </td>
              <td class="text-right">{{ formatCurrency(cert.vat_applicable_amount_bdt) }}</td>
              <td class="text-right">{{ cert.vds_rate_percent }}%</td>
              <td class="text-right font-semibold text-danger">{{ formatCurrency(cert.vds_deducted_amount_bdt) }}</td>
              <td>
                <span
                  class="status-badge"
                  :class="cert.treasury_deposit_status === 'deposited' ? 'status-deposited' : 'status-pending'"
                >
                  {{ cert.treasury_deposit_status === 'deposited' ? 'Treasury Deposited' : 'Pending Deposit' }}
                </span>
                <div v-if="cert.treasury_challan_number" class="text-xs text-muted mt-1 font-mono">
                  TR: {{ cert.treasury_challan_number }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="certificates.last_page > 1" class="pagination-bar">
        <div class="pagination-info">
          Showing page {{ certificates.current_page }} of {{ certificates.last_page }} (Total {{ certificates.total }} certificates)
        </div>
        <div class="pagination-links">
          <Link
            v-for="(link, idx) in certificates.links"
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
.mushak-66-page {
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

.input-search {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;

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

  &.btn-primary {
    background-color: #3b82f6;
    color: #ffffff;
    &:hover { background-color: #2563eb; }
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
  .text-danger { color: #dc2626; }
  .mt-1 { margin-top: 0.25rem; }
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #94a3b8;
}

.bin-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  background-color: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.75rem;
  color: #334155;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;

  &.status-deposited {
    background-color: #ecfdf5;
    color: #059669;
  }

  &.status-pending {
    background-color: #fffbeb;
    color: #d97706;
  }
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

