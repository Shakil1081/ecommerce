<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CreditNoteItem {
  id: number;
  item_name: string;
  sku: string;
  quantity_returned: number;
  unit_price_exclusive_bdt: string;
  subtotal_exclusive_bdt: string;
  sd_rate_percent: string;
  sd_amount_bdt: string;
  vat_rate_percent: string;
  vat_amount_bdt: string;
  total_credit_bdt: string;
}

interface Mushak67CreditNote {
  id: number;
  ulid: string;
  credit_note_number: string;
  tax_period: string;
  issue_date: string;
  mushak_6_3_invoice_number: string;
  mushak_6_3_invoice_date: string;
  seller_name: string;
  seller_bin: string;
  seller_address: string;
  customer_name: string;
  customer_bin: string | null;
  customer_phone: string;
  customer_address: string;
  reason_for_return: string;
  original_invoice_total_bdt: string;
  returned_value_exclusive_bdt: string;
  returned_sd_amount_bdt: string;
  returned_vat_amount_bdt: string;
  total_credit_amount_bdt: string;
  is_adjusted_in_mushak_9_1: boolean;
  status: string;
  integrity_hash: string;
  order?: {
    id: number;
    order_number: string;
  };
  rma?: {
    id: number;
    rma_number: string;
    inspection_outcome: string | null;
  };
  items: CreditNoteItem[];
}

const props = defineProps<{
  creditNote: Mushak67CreditNote;
}>();

const formatCurrency = (val: string | number) => {
  const num = typeof val === 'string' ? parseFloat(val) : val;
  return new Intl.NumberFormat('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatDateOnly = (dateStr: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const triggerPrint = () => {
  window.print();
};
</script>

<template>
  <AdminLayout>
    <Head :title="`Mushak 6.7 - ${creditNote.credit_note_number}`" />

    <div class="mushak-show-page">
      <!-- Toolbar (Hidden during print) -->
      <div class="print-toolbar no-print">
        <Link href="/admin/tax-compliance/rma" class="back-link">
          &larr; Back to RMA & Returns
        </Link>
        <div class="toolbar-actions">
          <Link :href="`/admin/tax-compliance/mushak-6-3`" class="btn-secondary">
            View Tax Invoices (6.3)
          </Link>
          <button @click="triggerPrint" class="btn-print">
            &#128438;&#65039; Print Statutory Mushak 6.7
          </button>
        </div>
      </div>

      <!-- Printable Document Container -->
      <div class="invoice-document" id="printable-area">
        <!-- NBR Statutory Header -->
        <div class="gov-header">
          <div class="emblem">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
          <div class="dept">জাতীয় রাজস্ব বোর্ড</div>
          <div class="form-title">ক্রেডিট নোট</div>
          <div class="form-subtitle">
            [বিধি ৪০ এর উপ-বিধি (১) এর দফা (ছ) দ্রষ্টব্য]
          </div>
          <div class="form-tag">মূসক-৬.৭ / MUSHAK-6.7</div>
        </div>

        <!-- Registered Entity & Credit Note Meta Grid -->
        <div class="meta-section">
          <div class="meta-col">
            <div class="meta-row">
              <span class="meta-label">নিবন্ধিত ব্যক্তির নাম (Seller):</span>
              <span class="meta-val font-bold">{{ creditNote.seller_name }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">ব্যবসায় শনাক্তকরণ সংখ্যা (BIN):</span>
              <span class="meta-val font-mono font-bold">{{ creditNote.seller_bin }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">চালানপত্র জারির ঠিকানা:</span>
              <span class="meta-val">{{ creditNote.seller_address }}</span>
            </div>
          </div>

          <div class="meta-col right-align">
            <div class="meta-row">
              <span class="meta-label">ক্রেডিট নোট নম্বর:</span>
              <span class="meta-val font-mono font-bold text-primary">{{ creditNote.credit_note_number }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">ইস্যুর তারিখ ও সময়:</span>
              <span class="meta-val font-mono">{{ formatDate(creditNote.issue_date) }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">কর মেয়াদ (Tax Period):</span>
              <span class="meta-val font-mono font-bold">{{ creditNote.tax_period }}</span>
            </div>
            <div class="meta-row" v-if="creditNote.rma">
              <span class="meta-label">RMA রেফারেন্স:</span>
              <span class="meta-val font-mono">{{ creditNote.rma.rma_number }}</span>
            </div>
          </div>
        </div>

        <!-- Original Invoice Linkage Box -->
        <div class="linkage-card">
          <div class="linkage-title">মূল কর চালানপত্রের বিবরণ (Original Mushak 6.3 Tax Invoice Reference)</div>
          <div class="linkage-grid">
            <div>
              <span class="sub-label">মূল চালান নম্বর:</span>
              <strong class="font-mono">{{ creditNote.mushak_6_3_invoice_number }}</strong>
            </div>
            <div>
              <span class="sub-label">মূল চালান জারির তারিখ:</span>
              <span>{{ formatDateOnly(creditNote.mushak_6_3_invoice_date) }}</span>
            </div>
            <div>
              <span class="sub-label">মূল চালানের সর্বমোট মূল্য:</span>
              <strong class="font-mono">৳{{ formatCurrency(creditNote.original_invoice_total_bdt) }}</strong>
            </div>
            <div>
              <span class="sub-label">অর্ডার নম্বর:</span>
              <span class="font-mono font-semibold">{{ creditNote.order?.order_number || 'N/A' }}</span>
            </div>
          </div>
        </div>

        <!-- Customer & Reason Details -->
        <div class="customer-section">
          <div class="cust-row">
            <span class="cust-label">ক্রেতার নাম (Buyer Name):</span>
            <span class="cust-val font-bold">{{ creditNote.customer_name }}</span>
            <span class="cust-label ml-4">ক্রেতার বিআইএন (BIN):</span>
            <span class="cust-val font-mono">{{ creditNote.customer_bin || 'প্রযোজ্য নয় (Retail Buyer)' }}</span>
          </div>
          <div class="cust-row">
            <span class="cust-label">ঠিকানা (Address):</span>
            <span class="cust-val">{{ creditNote.customer_address }}</span>
            <span class="cust-label ml-4">মোবাইল:</span>
            <span class="cust-val font-mono">{{ creditNote.customer_phone }}</span>
          </div>
          <div class="cust-row reason-row">
            <span class="cust-label text-danger font-semibold">ফেরত বা সমন্বয়ের কারণ (Reason):</span>
            <span class="cust-val text-danger font-medium">{{ creditNote.reason_for_return }}</span>
          </div>
        </div>

        <!-- Table of Returned Goods & Tax Adjustments -->
        <table class="tax-table">
          <thead>
            <tr>
              <th rowspan="2" class="text-center w-10">ক্রমিক<br />(Sl)</th>
              <th rowspan="2">পণ্য বা সেবার বিবরণ ও এসকেইউ<br />(Description of Goods & SKU)</th>
              <th rowspan="2" class="text-center">সরবরাহের একক<br />(Unit)</th>
              <th rowspan="2" class="text-center">ফেরতের পরিমাণ<br />(Qty)</th>
              <th rowspan="2" class="text-right">একক মূল্য (টাকা)<br />(Unit Exclusive)</th>
              <th rowspan="2" class="text-right">সমন্বয়যোগ্য মূল্য<br />(Exclusive Value)</th>
              <th colspan="2" class="text-center group-header">সম্পূরক শুল্ক (SD)</th>
              <th colspan="2" class="text-center group-header">হ্রাসকারী মূসক (VAT)</th>
              <th rowspan="2" class="text-right">মোট সমন্বয়যোগ্য মূল্য (টাকা)<br />(Total Credit Amount)</th>
            </tr>
            <tr>
              <th class="text-center">হার (%)</th>
              <th class="text-right">পরিমাণ (টাকা)</th>
              <th class="text-center">হার (%)</th>
              <th class="text-right">পরিমাণ (টাকা)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in creditNote.items" :key="item.id">
              <td class="text-center font-mono">{{ idx + 1 }}</td>
              <td>
                <div class="item-title font-medium">{{ item.item_name }}</div>
                <div class="item-sub font-mono text-xs text-muted">SKU: {{ item.sku }}</div>
              </td>
              <td class="text-center">টি (Pcs)</td>
              <td class="text-center font-mono font-bold">{{ item.quantity_returned }}</td>
              <td class="text-right font-mono">{{ formatCurrency(item.unit_price_exclusive_bdt) }}</td>
              <td class="text-right font-mono">{{ formatCurrency(item.subtotal_exclusive_bdt) }}</td>
              <td class="text-center font-mono">{{ item.sd_rate_percent }}%</td>
              <td class="text-right font-mono">{{ formatCurrency(item.sd_amount_bdt) }}</td>
              <td class="text-center font-mono font-semibold">{{ item.vat_rate_percent }}%</td>
              <td class="text-right font-mono font-bold text-primary">{{ formatCurrency(item.vat_amount_bdt) }}</td>
              <td class="text-right font-mono font-bold">{{ formatCurrency(item.total_credit_bdt) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="totals-row font-bold">
              <td colspan="5" class="text-right">সর্বমোট সমন্বয় (Grand Total Credit Adjustment):</td>
              <td class="text-right font-mono">৳{{ formatCurrency(creditNote.returned_value_exclusive_bdt) }}</td>
              <td></td>
              <td class="text-right font-mono">৳{{ formatCurrency(creditNote.returned_sd_amount_bdt) }}</td>
              <td></td>
              <td class="text-right font-mono text-primary">৳{{ formatCurrency(creditNote.returned_vat_amount_bdt) }}</td>
              <td class="text-right font-mono text-lg text-primary">৳{{ formatCurrency(creditNote.total_credit_amount_bdt) }}</td>
            </tr>
          </tfoot>
        </table>

        <!-- Adjustment Statement for Mushak 9.1 -->
        <div class="adjustment-notice">
          <div class="notice-badge">&#10003; NBR মূসক-৯.১ দাখিলপত্র হ্রাসকারী সমন্বয় (Decreasing Adjustment) অনুমোদন</div>
          <p>
            এই ক্রেডিট নোটের মাধ্যমে অনুমোদিত হ্রাসকারী সমন্বয় <strong>৳{{ formatCurrency(creditNote.returned_vat_amount_bdt) }}</strong> চলতি কর মেয়াদের (<strong>{{ creditNote.tax_period }}</strong>) মাসিক মূসক দাখিলপত্র <strong>মূসক-৯.১ এর অংশ-৪ (হ্রাসকারী সমন্বয় - বিক্রয় ফেরত)</strong> এর আওতায় সমন্বয় দাবি করা হইয়াছে।
          </p>
        </div>

        <!-- Integrity & Signatures Section -->
        <div class="signatures-section">
          <div class="integrity-info">
            <div><strong>SHA-256 ডিজিটাল সত্যতা ফিঙ্গারপ্রিন্ট:</strong></div>
            <div class="hash-text font-mono">{{ creditNote.integrity_hash }}</div>
            <div class="nbr-disclaimer">
              * এটি জাতীয় রাজস্ব বোর্ড (NBR) বিধিমালা ২০১৬ অনুযায়ী ইলেকট্রনিক ক্রেডিট নোট।
            </div>
          </div>

          <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-title">ক্ষমতাপ্রাপ্ত কর্মকর্তার স্বাক্ষর ও সিল</div>
            <div class="signature-subtitle">Authorized Officer Signature</div>
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

  .toolbar-actions {
    display: flex;
    gap: 0.75rem;
  }
}

.back-link {
  color: #2563eb;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.875rem;
  &:hover { text-decoration: underline; }
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 600;
  text-decoration: none;
  font-size: 0.875rem;
  border: 1px solid #d1d5db;
  &:hover { background: #e5e7eb; }
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
  font-size: 0.875rem;

  &:hover { background: #111827; }
}

.invoice-document {
  background: #ffffff;
  padding: 2.5rem;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  max-width: 960px;
  margin: 0 auto;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
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
    margin-bottom: 0.25rem;
  }

  .dept {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
  }

  .form-title {
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: #b91c1c;
  }

  .form-subtitle {
    font-size: 0.8rem;
    color: #4b5563;
    margin-top: 0.15rem;
  }

  .form-tag {
    display: inline-block;
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 0.15rem 0.6rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    margin-top: 0.5rem;
  }
}

.meta-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1rem;
  font-size: 0.85rem;

  .meta-col {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;

    &.right-align {
      align-items: flex-end;
      text-align: right;
    }
  }

  .meta-row {
    display: flex;
    gap: 0.5rem;
  }

  .meta-label {
    color: #4b5563;
  }
}

.linkage-card {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 0.375rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;

  .linkage-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.4rem;
  }

  .linkage-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    font-size: 0.825rem;

    .sub-label {
      display: block;
      color: #64748b;
      font-size: 0.75rem;
    }
  }
}

.customer-section {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1.25rem;
  font-size: 0.825rem;

  .cust-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.35rem;

    &:last-child {
      margin-bottom: 0;
    }

    &.reason-row {
      margin-top: 0.4rem;
      padding-top: 0.4rem;
      border-top: 1px dashed #e2e8f0;
    }
  }

  .cust-label {
    color: #64748b;
  }

  .ml-4 {
    margin-left: 1rem;
  }
}

.tax-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1.25rem;
  font-size: 0.8rem;

  th, td {
    border: 1px solid #cbd5e1;
    padding: 0.5rem 0.6rem;
  }

  th {
    background: #f8fafc;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.25;

    &.group-header {
      background: #f1f5f9;
      font-size: 0.75rem;
    }
  }

  .totals-row {
    background: #f8fafc;
    border-top: 2px solid #0f172a;
  }
}

.adjustment-notice {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 0.375rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1.5rem;
  font-size: 0.8rem;
  color: #166534;

  .notice-badge {
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  p {
    margin: 0;
    line-height: 1.4;
  }
}

.signatures-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 2rem;
  padding-top: 1.5rem;

  .integrity-info {
    max-width: 60%;
    font-size: 0.75rem;
    color: #4b5563;

    .hash-text {
      word-break: break-all;
      background: #f3f4f6;
      padding: 0.35rem;
      border-radius: 0.25rem;
      font-size: 0.7rem;
      margin: 0.25rem 0;
    }

    .nbr-disclaimer {
      font-style: italic;
      color: #6b7280;
    }
  }

  .signature-box {
    text-align: center;
    width: 220px;

    .signature-line {
      border-bottom: 1px solid #111827;
      margin-bottom: 0.5rem;
      height: 40px;
    }

    .signature-title {
      font-size: 0.8rem;
      font-weight: 700;
    }

    .signature-subtitle {
      font-size: 0.7rem;
      color: #6b7280;
    }
  }
}

@media print {
  .no-print {
    display: none !important;
  }

  body {
    background: #ffffff !important;
    margin: 0;
  }

  .mushak-show-page {
    padding: 0;
  }

  .invoice-document {
    border: none;
    box-shadow: none;
    padding: 0;
    max-width: 100%;
  }
}
</style>
