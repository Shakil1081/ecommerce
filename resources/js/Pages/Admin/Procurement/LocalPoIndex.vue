<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface VariantItem {
  id: string;
  sku: string;
}

interface ProductItem {
  id: string;
  name: string;
  variants: VariantItem[];
}

interface SupplierItem {
  id: number;
  name: string;
  code: string;
  bin_number: string | null;
}

interface LocalOrderItem {
  id: number;
  ulid: string;
  po_number: string;
  order_date: string;
  supplier: {
    id: number;
    name: string;
    code: string;
    bin_number: string | null;
  };
  status: string;
  mushak_6_3_invoice_number: string | null;
  mushak_6_3_invoice_date: string | null;
  subtotal_bdt: string;
  vat_amount_bdt: string;
  vds_amount_bdt: string;
  mushak_6_6_certificate_number: string | null;
  tds_amount_bdt: string;
  total_payable_to_supplier_bdt: string;
  items: Array<{
    id: number;
    quantity_ordered: number;
    unit_price_bdt: string;
    subtotal_bdt: string;
  }>;
}

interface PaginatedOrders {
  data: LocalOrderItem[];
  current_page: number;
  last_page: number;
  total: number;
}

const props = defineProps<{
  orders: PaginatedOrders;
  suppliers: SupplierItem[];
  products: ProductItem[];
}>();

const showCreateModal = ref(false);

const form = useForm({
  supplier_id: props.suppliers.length > 0 ? props.suppliers[0].id : '',
  po_number: '',
  order_date: new Date().toISOString().substring(0, 10),
  delivery_deadline: '',
  mushak_6_3_invoice_number: '',
  mushak_6_3_invoice_date: '',
  vds_rate_percent: 5.0,
  tds_rate_percent: 2.0,
  items: [
    {
      product_id: props.products.length > 0 ? props.products[0].id : '',
      product_variant_id: props.products.length > 0 && props.products[0].variants.length > 0 ? props.products[0].variants[0].id : '',
      quantity_ordered: 50,
      unit_price_bdt: '1500.0000',
      vat_rate_percent: 15.0,
    },
  ],
});

const addItemRow = () => {
  const first = props.products[0];
  form.items.push({
    product_id: first ? first.id : '',
    product_variant_id: first && first.variants[0] ? first.variants[0].id : '',
    quantity_ordered: 20,
    unit_price_bdt: '1000.0000',
    vat_rate_percent: 15.0,
  });
};

const removeItemRow = (idx: number) => {
  if (form.items.length > 1) {
    form.items.splice(idx, 1);
  }
};

const onProductSelect = (index: number) => {
  const item = form.items[index];
  const prod = props.products.find((p) => p.id === item.product_id);
  if (prod && prod.variants.length > 0) {
    item.product_variant_id = prod.variants[0].id;
  }
};

const submitPo = () => {
  form.post('/admin/procurement/local-pos', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset();
    },
  });
};

const issueMushak66 = (po: LocalOrderItem) => {
  if (confirm(`Issue statutory NBR Mushak 6.6 Certificate for PO ${po.po_number}?`)) {
    router.post(`/admin/procurement/local-pos/${po.id}/issue-mushak66`);
  }
};
</script>

<template>
  <AdminLayout title="Local Domestic Purchase Orders">
    <Head title="Local Purchase Orders - Mushak 6.3 & 6.6" />

    <div class="local-po-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Domestic Bangladesh Sourcing (Local PO)</h1>
          <p class="header-subtitle">
            Local procurement in BDT (৳) with statutory NBR Mushak 6.3 Tax Invoices, VDS deduction (Mushak 6.6), and TDS under Income Tax Act 2023.
          </p>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="showCreateModal = true">
            <span>🧾</span> Issue Local PO
          </button>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>PO Number & Date</th>
                <th>Domestic Supplier & BIN</th>
                <th>Supplier Mushak 6.3</th>
                <th>Subtotal & 15% VAT</th>
                <th>VDS & TDS Withholding</th>
                <th>Net Payable (BDT ৳)</th>
                <th>Mushak 6.6 Certificate</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="po in orders.data" :key="po.id">
                <td>
                  <div class="po-info">
                    <strong class="po-number">{{ po.po_number }}</strong>
                    <span class="po-date">{{ po.order_date }}</span>
                  </div>
                </td>
                <td>
                  <div class="supplier-info">
                    <span class="supplier-name">{{ po.supplier?.name }}</span>
                    <span v-if="po.supplier?.bin_number" class="bin-tag">BIN: {{ po.supplier.bin_number }}</span>
                  </div>
                </td>
                <td>
                  <div class="mushak-info">
                    <span v-if="po.mushak_6_3_invoice_number" class="mushak-num">{{ po.mushak_6_3_invoice_number }}</span>
                    <span v-if="po.mushak_6_3_invoice_date" class="mushak-date">{{ po.mushak_6_3_invoice_date }}</span>
                    <span v-if="!po.mushak_6_3_invoice_number" class="text-muted">Awaiting Delivery</span>
                  </div>
                </td>
                <td>
                  <div class="amount-info">
                    <span>Base: ৳{{ Number(po.subtotal_bdt).toLocaleString() }}</span>
                    <span class="vat-tag">+ VAT: ৳{{ Number(po.vat_amount_bdt).toLocaleString() }}</span>
                  </div>
                </td>
                <td>
                  <div class="withholding-info">
                    <span v-if="Number(po.vds_amount_bdt) > 0" class="vds-amount">- VDS: ৳{{ Number(po.vds_amount_bdt).toLocaleString() }}</span>
                    <span v-if="Number(po.tds_amount_bdt) > 0" class="tds-amount">- TDS: ৳{{ Number(po.tds_amount_bdt).toLocaleString() }}</span>
                    <span v-if="Number(po.vds_amount_bdt) === 0 && Number(po.tds_amount_bdt) === 0" class="text-muted">None</span>
                  </div>
                </td>
                <td>
                  <strong class="net-payable">৳{{ Number(po.total_payable_to_supplier_bdt).toLocaleString() }}</strong>
                </td>
                <td>
                  <div class="cert-info">
                    <span v-if="po.mushak_6_6_certificate_number" class="badge badge-success">
                      {{ po.mushak_6_6_certificate_number }}
                    </span>
                    <button
                      v-else-if="Number(po.vds_amount_bdt) > 0"
                      class="btn btn-secondary btn-xs"
                      @click="issueMushak66(po)"
                    >
                      Issue 6.6
                    </button>
                    <span v-else class="text-muted">N/A</span>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="'badge-' + po.status">
                    {{ po.status.toUpperCase() }}
                  </span>
                </td>
              </tr>
              <tr v-if="orders.data.length === 0">
                <td colspan="8" class="empty-state">No local purchase orders issued yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal-dialog modal-large">
        <div class="modal-header">
          <h2 class="modal-title">Issue Local Domestic Purchase Order (BDT ৳)</h2>
          <button class="modal-close-btn" @click="showCreateModal = false">✕</button>
        </div>
        <form @submit.prevent="submitPo">
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-4">
                <label class="form-label">Domestic Vendor *</label>
                <select v-model="form.supplier_id" class="form-select" required>
                  <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">
                    {{ sup.name }} (BIN: {{ sup.bin_number || 'N/A' }})
                  </option>
                </select>
              </div>
              <div class="form-group col-4">
                <label class="form-label">PO Number *</label>
                <input v-model="form.po_number" type="text" class="form-input" placeholder="e.g. LPO-2026-0001" required />
              </div>
              <div class="form-group col-4">
                <label class="form-label">Order Date *</label>
                <input v-model="form.order_date" type="date" class="form-input" required />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-3">
                <label class="form-label">Delivery Deadline</label>
                <input v-model="form.delivery_deadline" type="date" class="form-input" />
              </div>
              <div class="form-group col-3">
                <label class="form-label">Supplier Mushak 6.3 No.</label>
                <input v-model="form.mushak_6_3_invoice_number" type="text" class="form-input" placeholder="e.g. M63-1092" />
              </div>
              <div class="form-group col-3">
                <label class="form-label">VDS Withholding Rate (%)</label>
                <input v-model="form.vds_rate_percent" type="number" step="0.5" class="form-input" />
              </div>
              <div class="form-group col-3">
                <label class="form-label">TDS Rate (%) (Income Tax)</label>
                <input v-model="form.tds_rate_percent" type="number" step="0.5" class="form-input" />
              </div>
            </div>

            <!-- Items Section -->
            <div class="items-section">
              <div class="items-header">
                <h3>Procurement Line Items</h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="addItemRow">➕ Add Item</button>
              </div>
              <table class="items-table">
                <thead>
                  <tr>
                    <th>Product & Variant</th>
                    <th style="width: 140px;">Quantity</th>
                    <th style="width: 180px;">Unit Price (BDT ৳)</th>
                    <th style="width: 60px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in form.items" :key="idx">
                    <td>
                      <div class="product-variant-selector">
                        <select v-model="item.product_id" class="form-select" @change="onProductSelect(idx)">
                          <option v-for="prod in products" :key="prod.id" :value="prod.id">
                            {{ prod.name }}
                          </option>
                        </select>
                        <select v-model="item.product_variant_id" class="form-select">
                          <option
                            v-for="v in (products.find((p) => p.id === item.product_id)?.variants || [])"
                            :key="v.id"
                            :value="v.id"
                          >
                            {{ v.sku }}
                          </option>
                        </select>
                      </div>
                    </td>
                    <td>
                      <input v-model.number="item.quantity_ordered" type="number" min="1" class="form-input" required />
                    </td>
                    <td>
                      <input v-model="item.unit_price_bdt" type="number" step="0.0001" min="0" class="form-input" required />
                    </td>
                    <td>
                      <button type="button" class="btn-remove" @click="removeItemRow(idx)" :disabled="form.items.length <= 1">✕</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Issue PO</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.local-po-container {
  display: flex;
  flex-direction: column;
  gap: $space-6;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;

  .header-title {
    font-size: $fs-2xl;
    font-weight: 700;
  }

  .header-subtitle {
    font-size: $fs-sm;
    color: $color-text-muted;
    margin-top: $space-1;
  }
}

.data-table {
  width: 100%;
  border-collapse: collapse;

  th {
    background: $color-bg-subtle;
    padding: $space-3 $space-4;
    text-align: left;
    font-size: $fs-xs;
    font-weight: 600;
    text-transform: uppercase;
    color: $color-text-muted;
    border-bottom: 1px solid $color-border;
  }

  td {
    padding: $space-4;
    border-bottom: 1px solid $color-border;
    font-size: $fs-sm;
    vertical-align: middle;
  }
}

.po-info, .supplier-info, .mushak-info, .amount-info, .withholding-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: $fs-xs;
}

.net-payable {
  color: #16a34a;
  font-size: $fs-base;
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: $radius-full;
  font-size: $fs-xs;
  font-weight: 600;

  &.badge-issued {
    background: hsla(217, 91%, 60%, 0.1);
    color: #2563eb;
  }

  &.badge-received {
    background: hsla(142, 70%, 40%, 0.1);
    color: #16a34a;
  }

  &.badge-partially_received {
    background: hsla(38, 92%, 50%, 0.1);
    color: #d97706;
  }

  &.badge-success {
    background: hsla(142, 70%, 40%, 0.1);
    color: #16a34a;
  }
}

.empty-state {
  text-align: center;
  padding: $space-8;
  color: $color-text-muted;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.modal-dialog {
  background: $color-surface;
  border-radius: $radius-lg;
  width: 90%;
  max-width: 860px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: $space-4 $space-6;
  border-bottom: 1px solid $color-border;

  .modal-title {
    font-size: $fs-lg;
    font-weight: 700;
  }

  .modal-close-btn {
    background: transparent;
    border: none;
    font-size: $fs-lg;
    cursor: pointer;
  }
}

.modal-body {
  padding: $space-6;
  display: flex;
  flex-direction: column;
  gap: $space-4;
}

.modal-footer {
  padding: $space-4 $space-6;
  border-top: 1px solid $color-border;
  display: flex;
  justify-content: flex-end;
  gap: $space-3;
}

.form-row {
  display: flex;
  gap: $space-4;

  .col-4 {
    flex: 1;
  }
  .col-3 {
    flex: 1;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: $space-1;
}

.form-label {
  font-size: $fs-xs;
  font-weight: 600;
}

.form-input, .form-select {
  padding: $space-2 $space-3;
  border: 1px solid $color-border;
  border-radius: $radius-md;
  font-size: $fs-sm;
}

.items-section {
  display: flex;
  flex-direction: column;
  gap: $space-3;
  margin-top: $space-4;

  .items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    h3 {
      font-size: $fs-sm;
      font-weight: 700;
    }
  }

  .items-table {
    width: 100%;
    border-collapse: collapse;

    th {
      background: $color-bg-subtle;
      padding: $space-2 $space-3;
      font-size: $fs-xs;
      text-align: left;
    }

    td {
      padding: $space-2;
    }
  }

  .product-variant-selector {
    display: flex;
    gap: $space-2;
  }

  .btn-remove {
    background: transparent;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-weight: 700;
  }
}
</style>
