<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface InvoiceItem {
  id: number;
  item_name: string;
  sku: string;
  hs_code: string | null;
  unit_of_measure: string;
  quantity: number;
  unit_price_exclusive_bdt: string;
  subtotal_exclusive_bdt: string;
  sd_rate_percent: string;
  sd_amount_bdt: string;
  vat_rate_percent: string;
  vat_amount_bdt: string;
  total_with_tax_bdt: string;
}

interface MushakInvoice {
  id: number;
  ulid: string;
  invoice_number: string;
  tax_period: string;
  issue_date: string;
  seller_name: string;
  seller_bin: string;
  seller_address: string;
  tax_circle: string | null;
  tax_commissionerate: string | null;
  customer_name: string;
  customer_bin: string | null;
  customer_phone: string;
  customer_address: string;
  destination_address: string;
  vehicle_type_and_number: string | null;
  subtotal_exclusive_bdt: string;
  supplementary_duty_total_bdt: string;
  vat_total_bdt: string;
  shipping_fee_bdt: string;
  discount_amount_bdt: string;
  grand_total_bdt: string;
  integrity_hash: string;
  status: string;
  order?: {
    id: number;
    order_number: string;
  };
  items: InvoiceItem[];
}

const props = defineProps<{
  invoice: MushakInvoice;
}>();

const formatCurrency = (val: string | number) => {
  const num = typeof val === 'string' ? parseFloat(val) : val;
  return new Intl.NumberFormat('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
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

const triggerPrint = () => {
  window.print();
};
</script>

<template>
  <AdminLayout>
    <Head :title="`Mushak 6.3 - ${invoice.invoice_number}`" />

    <div class="mushak-show-page">
      <!-- Toolbar (Hidden during print) -->
      <div class="print-toolbar no-print">
        <Link href="/admin/tax-compliance/mushak-6-3" class="back-link">
          ← Back to Invoices
        </Link>
        <button @click="triggerPrint" class="btn-print">
          🖨️ Print Statutory Mushak 6.3
        </button>
      </div>

      <!-- Printable Document Container -->
      <div class="invoice-document" id="printable-area">
        <!-- NBR Statutory Header -->
        <div class="gov-header">
          <div class="emblem">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
          <div class="dept">জাতীয় রাজস্ব বোর্ড</div>
          <div class="form-title">কর চালানপত্র</div>
          <div class="form-subtitle">
            [বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য]
          </div>
          <div class="form-tag">মূসক-৬.৩ / MUSHAK-6.3</div>
        </div>

        <!-- Registered Entity & Invoice Meta Grid -->
        <div class="meta-section">
          <div class="meta-col">
            <div class="meta-row">
              <span class="meta-label">নিবন্ধিত ব্যক্তির নাম (Seller):</span>
              <span class="meta-val font-bold">{{ invoice.seller_name }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">ব্যবসায় শনাক্তকরণ সংখ্যা (BIN):</span>
              <span class="meta-val font-mono font-bold">{{ invoice.seller_bin }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">চালানপত্র জারির ঠিকানা:</span>
              <span class="meta-val">{{ invoice.seller_address }}</span>
            </div>
            <div class="meta-row" v-if="invoice.tax_circle">
              <span class="meta-label">কর সার্কেল ও কমিশনারেট:</span>
              <span class="meta-val">{{ invoice.tax_circle }} | {{ invoice.tax_commissionerate }}</span>
            </div>
          </div>

          <div class="meta-col meta-right">
            <div class="meta-row">
              <span class="meta-label">চালানপত্র নম্বর (Invoice No):</span>
              <span class="meta-val font-mono font-bold text-lg text-primary">{{ invoice.invoice_number }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">ইস্যুর তারিখ ও সময়:</span>
              <span class="meta-val">{{ formatDate(invoice.issue_date) }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">কর মেয়াদ (Tax Period):</span>
              <span class="meta-val font-bold">{{ invoice.tax_period }}</span>
            </div>
            <div class="meta-row" v-if="invoice.order">
              <span class="meta-label">অর্ডার নম্বর (Order Ref):</span>
              <span class="meta-val font-mono">{{ invoice.order.order_number }}</span>
            </div>
          </div>
        </div>

        <!-- Purchaser Information Box -->
        <div class="purchaser-box">
          <div class="purchaser-title">ক্রেতার বিবরণ (Purchaser Information)</div>
          <div class="purchaser-grid">
            <div>
              <strong>ক্রেতার নাম:</strong> {{ invoice.customer_name }}
            </div>
            <div>
              <strong>ক্রেতার BIN (যদি থাকে):</strong> {{ invoice.customer_bin || 'N/A' }}
            </div>
            <div>
              <strong>ক্রেতার মোবাইল:</strong> {{ invoice.customer_phone }}
            </div>
            <div>
              <strong>পণ্য প্রেরণের গন্তব্যস্থল:</strong> {{ invoice.destination_address }}
            </div>
            <div v-if="invoice.vehicle_type_and_number" class="col-span-2">
              <strong>যানবাহনের প্রকৃতি ও নম্বর:</strong> {{ invoice.vehicle_type_and_number }}
            </div>
          </div>
        </div>

        <!-- Statutory Items Table -->
        <table class="statutory-table">
          <thead>
            <tr>
              <th rowspan="2" class="text-center w-12">ক্রমিক</th>
              <th rowspan="2">পণ্য বা সেবার বিবরণ (Description)</th>
              <th rowspan="2" class="text-center">HS কোড</th>
              <th rowspan="2" class="text-center">একক</th>
              <th rowspan="2" class="text-center">পরিমাণ</th>
              <th rowspan="2" class="text-right">একক মূল্য (৳)</th>
              <th rowspan="2" class="text-right">মোট মূল্য (৳)<br>(কর ব্যতীত)</th>
              <th colspan="2" class="text-center">সম্পূরক শুল্ক (SD)</th>
              <th colspan="2" class="text-center">মূল্য সংযোজন কর (VAT)</th>
              <th rowspan="2" class="text-right">সর্বমোট মূল্য (৳)<br>(সকল করসহ)</th>
            </tr>
            <tr>
              <th class="text-center">হার (%)</th>
              <th class="text-right">পরিমাণ (৳)</th>
              <th class="text-center">হার (%)</th>
              <th class="text-right">পরিমাণ (৳)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in invoice.items" :key="item.id">
              <td class="text-center">{{ index + 1 }}</td>
              <td>
                <div class="font-bold">{{ item.item_name }}</div>
                <div class="text-xs text-muted">SKU: {{ item.sku }}</div>
              </td>
              <td class="text-center font-mono">{{ item.hs_code || '—' }}</td>
              <td class="text-center">{{ item.unit_of_measure }}</td>
              <td class="text-center font-bold">{{ item.quantity }}</td>
              <td class="text-right">{{ formatCurrency(item.unit_price_exclusive_bdt) }}</td>
              <td class="text-right">{{ formatCurrency(item.subtotal_exclusive_bdt) }}</td>
              <td class="text-center">{{ item.sd_rate_percent }}%</td>
              <td class="text-right">{{ formatCurrency(item.sd_amount_bdt) }}</td>
              <td class="text-center">{{ item.vat_rate_percent }}%</td>
              <td class="text-right font-bold">{{ formatCurrency(item.vat_amount_bdt) }}</td>
              <td class="text-right font-bold">{{ formatCurrency(item.total_with_tax_bdt) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="totals-row font-bold">
              <td colspan="6" class="text-right">মোট (Subtotal):</td>
              <td class="text-right">{{ formatCurrency(invoice.subtotal_exclusive_bdt) }}</td>
              <td></td>
              <td class="text-right">{{ formatCurrency(invoice.supplementary_duty_total_bdt) }}</td>
              <td></td>
              <td class="text-right">{{ formatCurrency(invoice.vat_total_bdt) }}</td>
              <td class="text-right text-lg">{{ formatCurrency(invoice.grand_total_bdt) }}</td>
            </tr>
          </tfoot>
        </table>

        <!-- Integrity & Signatures Section -->
        <div class="signatures-section">
          <div class="integrity-info">
            <div><strong>SHA-256 ডিজিটাল সত্যতা ফিঙ্গারপ্রিন্ট:</strong></div>
            <div class="hash-text font-mono">{{ invoice.integrity_hash }}</div>
            <div class="nbr-disclaimer">
              * এটি জাতীয় রাজস্ব বোর্ড (NBR) বিধিমোতাবেক প্রস্তুতকৃত একটি ইলেকট্রনিক ট্যাক্স চালানপত্র।
            </div>
          </div>

          <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-title">ক্ষমতাপ্রাপ্ত কর্মকর্তার স্বাক্ষর ও সিল</div>
            <div class="signature-subtitle">Authorized Signatory</div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.mushak-show-page {
  padding: 1.5rem;
}

.print-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  background: #ffffff;
  padding: 1rem 1.5rem;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
}

.back-link {
  color: #2563eb;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.875rem;
  &:hover { text-decoration: underline; }
}

.btn-print {
  background: #1f2937;
  color: #ffffff;
  border: none;
  padding: 0.5rem 1.25rem;
  border-radius: 0.375rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:hover { background: #111827; }
}

.invoice-document {
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 2.5rem;
  max-width: 960px;
  margin: 0 auto;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  color: #111827;
}

.gov-header {
  text-align: center;
  border-bottom: 2px solid #111827;
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;

  .emblem {
    font-size: 1.125rem;
    font-weight: 700;
  }
  .dept {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
  }
  .form-title {
    font-size: 1.375rem;
    font-weight: 800;
    margin-top: 0.25rem;
    color: #111827;
  }
  .form-subtitle {
    font-size: 0.75rem;
    color: #4b5563;
  }
  .form-tag {
    display: inline-block;
    background: #111827;
    color: #ffffff;
    font-size: 0.8125rem;
    font-weight: 700;
    padding: 0.125rem 0.75rem;
    border-radius: 0.25rem;
    margin-top: 0.5rem;
    letter-spacing: 0.05em;
  }
}

.meta-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 1.5rem;
  font-size: 0.8125rem;
}

.meta-row {
  display: flex;
  margin-bottom: 0.375rem;
  gap: 0.5rem;
}

.meta-label {
  color: #4b5563;
  min-width: 170px;
  font-weight: 500;
}

.meta-val {
  color: #111827;
}

.meta-right {
  .meta-label {
    min-width: 150px;
  }
}

.purchaser-box {
  border: 1px solid #9ca3af;
  border-radius: 0.375rem;
  padding: 0.875rem 1rem;
  margin-bottom: 1.5rem;
  background: #f9fafb;
}

.purchaser-title {
  font-size: 0.8125rem;
  font-weight: 700;
  border-bottom: 1px dashed #d1d5db;
  padding-bottom: 0.25rem;
  margin-bottom: 0.5rem;
  color: #1f2937;
}

.purchaser-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  font-size: 0.8125rem;
}

.statutory-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.75rem;
  margin-bottom: 2rem;

  th, td {
    border: 1px solid #6b7280;
    padding: 0.5rem 0.375rem;
  }

  th {
    background: #f3f4f6;
    font-weight: 700;
    color: #111827;
  }

  .totals-row td {
    background: #f9fafb;
    border-top: 2px solid #111827;
  }
}

.signatures-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 2.5rem;
  padding-top: 1.5rem;
}

.integrity-info {
  max-width: 500px;
  font-size: 0.7rem;
  color: #4b5563;
}

.hash-text {
  font-size: 0.65rem;
  word-break: break-all;
  color: #1f2937;
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  margin: 0.25rem 0;
}

.nbr-disclaimer {
  font-style: italic;
  font-size: 0.65rem;
}

.signature-box {
  text-align: center;
  min-width: 220px;
}

.signature-line {
  border-top: 1px solid #111827;
  margin-bottom: 0.375rem;
}

.signature-title {
  font-size: 0.75rem;
  font-weight: 700;
  color: #111827;
}

.signature-subtitle {
  font-size: 0.65rem;
  color: #6b7280;
}

.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-primary { color: #2563eb; }
.text-muted { color: #6b7280; }
.text-xs { font-size: 0.65rem; }
.text-lg { font-size: 1rem; }
.w-12 { width: 3rem; }
.col-span-2 { grid-column: span 2; }

@media print {
  .no-print {
    display: none !important;
  }
  .mushak-show-page {
    padding: 0;
    margin: 0;
  }
  .invoice-document {
    border: none;
    box-shadow: none;
    padding: 0;
    max-width: 100%;
  }
}
</style>

