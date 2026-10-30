<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Register61Entry {
  type: string;
  date: string;
  source_document: string;
  po_number: string;
  supplier_name: string;
  supplier_bin: string;
  description: string;
  taxable_value_bdt: string;
  vat_amount_bdt: string;
  total_amount_bdt: string;
}

interface Register61 {
  tax_period: string;
  seller_name: string;
  seller_bin: string;
  total_local_purchases_bdt: string;
  total_local_vat_bdt: string;
  total_import_purchases_bdt: string;
  total_import_duties_bdt: string;
  total_inward_purchases_bdt: string;
  entries: Register61Entry[];
}

interface Register62Entry {
  date: string;
  invoice_number: string;
  customer_name: string;
  customer_bin: string;
  customer_phone: string;
  destination_address: string | null;
  items_summary: string;
  subtotal_exclusive_bdt: string;
  sd_amount_bdt: string;
  vat_amount_bdt: string;
  grand_total_bdt: string;
  status: string;
}

interface Register62 {
  tax_period: string;
  seller_name: string;
  seller_bin: string;
  total_sales_exclusive_bdt: string;
  total_sd_bdt: string;
  total_vat_bdt: string;
  total_sales_inclusive_bdt: string;
  invoices_count: number;
  entries: Register62Entry[];
}

const props = defineProps<{
  taxPeriod: string;
  register61: Register61;
  register62: Register62;
}>();

const activeTab = ref<'6.1' | '6.2'>('6.1');
const selectedPeriod = ref(props.taxPeriod);

const handlePeriodChange = () => {
  router.get('/admin/tax-compliance/registers', {
    tax_period: selectedPeriod.value,
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
    <Head title="NBR Statutory Registers (Mushak 6.1 & 6.2)" />

    <div class="registers-page">
      <!-- Header Bar (Hidden in print) -->
      <div class="header-section no-print">
        <div>
          <div class="breadcrumb">
            <Link href="/admin/tax-compliance">Tax Compliance</Link> / <span>Statutory Registers</span>
          </div>
          <h1 class="page-title">NBR Statutory Registers (মূসক রেজিস্টার)</h1>
          <p class="page-subtitle">Official Bangladesh NBR Purchase Register (Mushak 6.1) & Sales Register (Mushak 6.2).</p>
        </div>
        <div class="actions-group">
          <button class="btn btn-secondary" @click="handlePrint">
            Print Register
          </button>
        </div>
      </div>

      <!-- Controls & Tabs (Hidden in print) -->
      <div class="controls-card no-print">
        <div class="tabs-header">
          <button
            class="tab-btn"
            :class="{ active: activeTab === '6.1' }"
            @click="activeTab = '6.1'"
          >
            Mushak 6.1: Purchase Register (ক্রয় হিসাব পুস্তক)
          </button>
          <button
            class="tab-btn"
            :class="{ active: activeTab === '6.2' }"
            @click="activeTab = '6.2'"
          >
            Mushak 6.2: Sales Register (বিক্রয় হিসাব পুস্তক)
          </button>
        </div>

        <div class="period-selector">
          <label for="tax-period-input">Tax Period:</label>
          <input
            id="tax-period-input"
            v-model="selectedPeriod"
            type="text"
            placeholder="YYYY-MM"
            class="period-input"
            @keyup.enter="handlePeriodChange"
          />
          <button class="btn btn-primary btn-sm" @click="handlePeriodChange">
            Load Period
          </button>
        </div>
      </div>

      <!-- TAB 1: Mushak 6.1 Purchase Register -->
      <div v-show="activeTab === '6.1'" class="register-view">
        <!-- Government Print Header -->
        <div class="gov-header">
          <div class="gov-title">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
          <div class="gov-subtitle">জাতীয় রাজস্ব বোর্ড</div>
          <div class="form-title">ক্রয় হিসাব পুস্তক</div>
          <div class="form-rule">[ বিধি ৪০ এর উপ-বিধি (১) এর দফা (ক) দ্রষ্টব্য ]</div>
          <div class="form-tag">মূসক-৬.১ (MUSHAK 6.1)</div>

          <div class="entity-info-grid">
            <div><strong>প্রতিষ্ঠান/করদাতার নাম:</strong> {{ register61.seller_name }}</div>
            <div><strong>করদাতা শনাক্তকরণ সংখ্যা (BIN):</strong> {{ register61.seller_bin }}</div>
            <div><strong>কর মেয়াদ (Tax Period):</strong> {{ register61.tax_period }}</div>
          </div>
        </div>

        <!-- Metric Summary Cards (No Print) -->
        <div class="summary-cards-grid no-print">
          <div class="metric-card">
            <span class="label">Total Domestic Procurement</span>
            <span class="value">{{ formatCurrency(register61.total_local_purchases_bdt) }}</span>
            <span class="sub-label">Mushak 6.3 Local Purchases</span>
          </div>
          <div class="metric-card">
            <span class="label">Total Local Input VAT</span>
            <span class="value text-success">{{ formatCurrency(register61.total_local_vat_bdt) }}</span>
            <span class="sub-label">Eligible for Mushak 9.1 Rebate</span>
          </div>
          <div class="metric-card">
            <span class="label">Total Commercial Import Value</span>
            <span class="value">{{ formatCurrency(register61.total_import_purchases_bdt) }}</span>
            <span class="sub-label">Customs Assessment Value</span>
          </div>
          <div class="metric-card">
            <span class="label">Total Inward Register Entries</span>
            <span class="value font-mono">{{ register61.entries.length }}</span>
            <span class="sub-label">Inward Transactions Recorded</span>
          </div>
        </div>

        <!-- Official Register Table -->
        <div class="table-container">
          <table class="gov-table">
            <thead>
              <tr>
                <th style="width: 40px;">ক্রমিক</th>
                <th>তারিখ</th>
                <th>উৎস দলিল / চালান নং</th>
                <th>সরবরাহকারীর নাম</th>
                <th>সরবরাহকারীর BIN / স্টেশন</th>
                <th>পণ্য/সেবার বিবরণ</th>
                <th class="text-right">করযোগ্য মূল্য (BDT)</th>
                <th class="text-right">মূসক (VAT) (BDT)</th>
                <th class="text-right">মোট মূল্য (BDT)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="register61.entries.length === 0">
                <td colspan="9" class="empty-state">
                  No inward purchase or import records for period {{ register61.tax_period }}.
                </td>
              </tr>
              <tr v-for="(entry, idx) in register61.entries" :key="idx">
                <td class="text-center font-mono">{{ idx + 1 }}</td>
                <td>{{ entry.date }}</td>
                <td class="font-mono text-xs font-semibold">{{ entry.source_document }}</td>
                <td>{{ entry.supplier_name }}</td>
                <td class="font-mono text-xs">{{ entry.supplier_bin }}</td>
                <td>{{ entry.description }}</td>
                <td class="text-right font-mono">{{ formatCurrency(entry.taxable_value_bdt) }}</td>
                <td class="text-right font-mono text-success">{{ formatCurrency(entry.vat_amount_bdt) }}</td>
                <td class="text-right font-mono font-semibold">{{ formatCurrency(entry.total_amount_bdt) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="totals-row">
                <td colspan="6" class="text-right font-semibold">সর্বমোট (Grand Total Inward Value):</td>
                <td class="text-right font-mono font-semibold">{{ formatCurrency(register61.total_inward_purchases_bdt) }}</td>
                <td class="text-right font-mono font-semibold text-success">{{ formatCurrency(register61.total_local_vat_bdt) }}</td>
                <td class="text-right font-mono font-bold">
                  {{ formatCurrency(Number(register61.total_inward_purchases_bdt) + Number(register61.total_local_vat_bdt)) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- TAB 2: Mushak 6.2 Sales Register -->
      <div v-show="activeTab === '6.2'" class="register-view">
        <!-- Government Print Header -->
        <div class="gov-header">
          <div class="gov-title">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
          <div class="gov-subtitle">জাতীয় রাজস্ব বোর্ড</div>
          <div class="form-title">বিক্রয় হিসাব পুস্তক / ব্যবসায়ীর বিক্রয় হিসাব পুস্তক</div>
          <div class="form-rule">[ বিধি ৪০ এর উপ-বিধি (১) এর দফা (খ) ও বিধি ৪১ এর দফা (ক) দ্রষ্টব্য ]</div>
          <div class="form-tag">মূসক-৬.২ / ৬.২.১ (MUSHAK 6.2 / 6.2.1)</div>

          <div class="entity-info-grid">
            <div><strong>প্রতিষ্ঠান/করদাতার নাম:</strong> {{ register62.seller_name }}</div>
            <div><strong>করদাতা শনাক্তকরণ সংখ্যা (BIN):</strong> {{ register62.seller_bin }}</div>
            <div><strong>কর মেয়াদ (Tax Period):</strong> {{ register62.tax_period }}</div>
          </div>
        </div>

        <!-- Metric Summary Cards (No Print) -->
        <div class="summary-cards-grid no-print">
          <div class="metric-card">
            <span class="label">Taxable Sales (Exclusive)</span>
            <span class="value">{{ formatCurrency(register62.total_sales_exclusive_bdt) }}</span>
            <span class="sub-label">Total Outward Turnover</span>
          </div>
          <div class="metric-card">
            <span class="label">Output VAT Collected</span>
            <span class="value text-primary">{{ formatCurrency(register62.total_vat_bdt) }}</span>
            <span class="sub-label">Section 15 Output Tax</span>
          </div>
          <div class="metric-card">
            <span class="label">Gross Sales (Inclusive)</span>
            <span class="value font-semibold">{{ formatCurrency(register62.total_sales_inclusive_bdt) }}</span>
            <span class="sub-label">Including SD & VAT</span>
          </div>
          <div class="metric-card">
            <span class="label">Invoices Count</span>
            <span class="value font-mono">{{ register62.invoices_count }}</span>
            <span class="sub-label">Issued Mushak 6.3</span>
          </div>
        </div>

        <!-- Official Register Table -->
        <div class="table-container">
          <table class="gov-table">
            <thead>
              <tr>
                <th style="width: 40px;">ক্রমিক</th>
                <th>তারিখ ও সময়</th>
                <th>কর চালানপত্র (Mushak 6.3) #</th>
                <th>ক্রেতার নাম ও মোবাইল</th>
                <th>ক্রেতার BIN</th>
                <th>পণ্য/সেবার বিবরণ</th>
                <th class="text-right">করযোগ্য মূল্য (BDT)</th>
                <th class="text-right">সম্পূরক শুল্ক (SD)</th>
                <th class="text-right">মূসক (VAT) (BDT)</th>
                <th class="text-right">সর্বমোট চালান মূল্য</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="register62.entries.length === 0">
                <td colspan="10" class="empty-state">
                  No outward sales invoices found for period {{ register62.tax_period }}.
                </td>
              </tr>
              <tr v-for="(entry, idx) in register62.entries" :key="idx">
                <td class="text-center font-mono">{{ idx + 1 }}</td>
                <td>{{ entry.date }}</td>
                <td class="font-mono text-xs font-semibold">{{ entry.invoice_number }}</td>
                <td>
                  <div class="font-medium">{{ entry.customer_name }}</div>
                  <div class="text-muted text-xs font-mono">{{ entry.customer_phone }}</div>
                </td>
                <td class="font-mono text-xs">{{ entry.customer_bin }}</td>
                <td class="text-xs max-w-xs truncate">{{ entry.items_summary }}</td>
                <td class="text-right font-mono">{{ formatCurrency(entry.subtotal_exclusive_bdt) }}</td>
                <td class="text-right font-mono">{{ formatCurrency(entry.sd_amount_bdt) }}</td>
                <td class="text-right font-mono text-primary">{{ formatCurrency(entry.vat_amount_bdt) }}</td>
                <td class="text-right font-mono font-semibold">{{ formatCurrency(entry.grand_total_bdt) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="totals-row">
                <td colspan="6" class="text-right font-semibold">সর্বমোট (Grand Total Outward Turnover):</td>
                <td class="text-right font-mono font-semibold">{{ formatCurrency(register62.total_sales_exclusive_bdt) }}</td>
                <td class="text-right font-mono font-semibold">{{ formatCurrency(register62.total_sd_bdt) }}</td>
                <td class="text-right font-mono font-semibold text-primary">{{ formatCurrency(register62.total_vat_bdt) }}</td>
                <td class="text-right font-mono font-bold">{{ formatCurrency(register62.total_sales_inclusive_bdt) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.registers-page {
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

.controls-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.tabs-header {
  display: flex;
  gap: 0.5rem;
}

.tab-btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background-color: #f8fafc;
  color: #475569;
  transition: all 0.15s ease-in-out;

  &.active {
    background-color: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
  }
}

.period-selector {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #475569;

  label {
    font-weight: 600;
  }
}

.period-input {
  width: 120px;
  padding: 0.375rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-family: monospace;
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

  &.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
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

/* Gov Print Form Header */
.gov-header {
  text-align: center;
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #0f172a;
  padding-bottom: 1rem;
}

.gov-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0f172a;
}

.gov-subtitle {
  font-size: 0.95rem;
  color: #334155;
}

.form-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin-top: 0.25rem;
}

.form-rule {
  font-size: 0.75rem;
  color: #64748b;
}

.form-tag {
  display: inline-block;
  margin-top: 0.5rem;
  padding: 0.2rem 0.75rem;
  background-color: #0f172a;
  color: #ffffff;
  font-weight: 700;
  font-size: 0.8125rem;
  border-radius: 0.25rem;
}

.entity-info-grid {
  display: flex;
  justify-content: space-between;
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  text-align: left;
}

.summary-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.metric-card {
  background: #ffffff;
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;

  .label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
  }

  .value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0.375rem 0;
  }

  .sub-label {
    font-size: 0.75rem;
    color: #94a3b8;
  }
}

.table-container {
  background: #ffffff;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  overflow-x: auto;
}

.gov-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
  text-align: left;

  th {
    background-color: #f8fafc;
    color: #334155;
    font-weight: 700;
    padding: 0.625rem 0.75rem;
    border: 1px solid #cbd5e1;
  }

  td {
    padding: 0.625rem 0.75rem;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    vertical-align: middle;
  }

  .totals-row td {
    background-color: #f8fafc;
    border-top: 2px solid #0f172a;
    padding: 0.75rem;
  }

  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .font-mono { font-family: monospace; }
  .font-semibold { font-weight: 600; }
  .font-bold { font-weight: 700; }
  .font-medium { font-weight: 500; }
  .text-muted { color: #64748b; }
  .text-xs { font-size: 0.75rem; }
  .text-success { color: #16a34a; }
  .text-primary { color: #2563eb; }
  .truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .max-w-xs { max-width: 16rem; }
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

  .registers-page {
    padding: 0;
  }

  .table-container {
    border: none;
  }

  .gov-table th, .gov-table td {
    border: 1px solid #000000 !important;
    color: #000000 !important;
  }
}
</style>

