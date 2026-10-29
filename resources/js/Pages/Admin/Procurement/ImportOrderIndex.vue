<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface VariantItem {
  id: string;
  sku: string;
  barcode_ean: string | null;
  attributes: Record<string, string>;
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
  currency: string;
}

interface ImportOrderItem {
  id: number;
  ulid: string;
  po_number: string;
  supplier: {
    id: number;
    name: string;
    code: string;
  };
  status: string;
  currency: string;
  exchange_rate_to_bdt: string;
  total_fob_foreign: string;
  total_fob_bdt: string;
  lc_number: string | null;
  lc_issuing_bank: string | null;
  port_of_discharge: string;
  items_count?: number;
  items: Array<{
    id: number;
    quantity_ordered: number;
    quantity_received: number;
    unit_price_foreign: string;
    unit_price_bdt: string;
    variant?: {
      sku: string;
    };
  }>;
}

interface PaginatedOrders {
  data: ImportOrderItem[];
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
  currency: 'USD',
  exchange_rate_to_bdt: '122.5000',
  payment_method: 'letter_of_credit',
  lc_number: '',
  lc_issuing_bank: '',
  port_of_loading: 'Shenzhen, China',
  port_of_discharge: 'Chattogram',
  items: [
    {
      product_id: props.products.length > 0 ? props.products[0].id : '',
      product_variant_id: props.products.length > 0 && props.products[0].variants.length > 0 ? props.products[0].variants[0].id : '',
      quantity_ordered: 100,
      unit_price_foreign: '25.0000',
    },
  ],
});

const onSupplierChange = () => {
  const selected = props.suppliers.find((s) => s.id === Number(form.supplier_id));
  if (selected) {
    form.currency = selected.currency;
  }
};

const addItemRow = () => {
  const firstProd = props.products[0];
  form.items.push({
    product_id: firstProd ? firstProd.id : '',
    product_variant_id: firstProd && firstProd.variants[0] ? firstProd.variants[0].id : '',
    quantity_ordered: 50,
    unit_price_foreign: '10.0000',
  });
};

const removeItemRow = (index: number) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const onProductSelect = (index: number) => {
  const item = form.items[index];
  const prod = props.products.find((p) => p.id === item.product_id);
  if (prod && prod.variants.length > 0) {
    item.product_variant_id = prod.variants[0].id;
  }
};

const submitOrder = () => {
  form.post('/admin/procurement/import-orders', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset();
    },
  });
};
</script>

<template>
  <AdminLayout title="Global Import Orders">
    <Head title="Global Commercial Import Orders (CI / LC / BOE)" />

    <div class="import-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Global Commercial Import Orders</h1>
          <p class="header-subtitle">
            Manage international procurement via Letter of Credit (LC) and Telegraphic Transfer (TT) with bank FX settlement rates in BDT (৳).
          </p>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="showCreateModal = true">
            <span>🚢</span> New Import Purchase Order
          </button>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>PO Number</th>
                <th>Supplier & Currency</th>
                <th>LC Details & Port</th>
                <th>FX Settlement Rate</th>
                <th>Total FOB Foreign</th>
                <th>Total FOB in BDT (৳)</th>
                <th>Lifecycle Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders.data" :key="order.id">
                <td>
                  <div class="po-info">
                    <strong class="po-number">{{ order.po_number }}</strong>
                    <span class="items-count">{{ order.items.length }} line items</span>
                  </div>
                </td>
                <td>
                  <div class="supplier-info">
                    <span class="supplier-name">{{ order.supplier?.name }}</span>
                    <span class="currency-tag">{{ order.currency }}</span>
                  </div>
                </td>
                <td>
                  <div class="lc-info">
                    <span v-if="order.lc_number" class="lc-number">LC: {{ order.lc_number }}</span>
                    <span v-if="order.lc_issuing_bank" class="bank-name">{{ order.lc_issuing_bank }}</span>
                    <span class="port-discharge">Port: {{ order.port_of_discharge }}</span>
                  </div>
                </td>
                <td>
                  <span class="fx-rate">1 {{ order.currency }} = ৳{{ order.exchange_rate_to_bdt }}</span>
                </td>
                <td>
                  <span class="fob-foreign">{{ order.currency }} {{ Number(order.total_fob_foreign).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                </td>
                <td>
                  <strong class="fob-bdt">৳{{ Number(order.total_fob_bdt).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong>
                </td>
                <td>
                  <span class="badge" :class="'badge-' + order.status">
                    {{ order.status.replace('_', ' ').toUpperCase() }}
                  </span>
                </td>
              </tr>
              <tr v-if="orders.data.length === 0">
                <td colspan="7" class="empty-state">No import orders created yet. Click "New Import Purchase Order" to initialize one.</td>
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
          <h2 class="modal-title">Create Global Commercial Import Order</h2>
          <button class="modal-close-btn" @click="showCreateModal = false">✕</button>
        </div>
        <form @submit.prevent="submitOrder">
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-4">
                <label class="form-label">Overseas Supplier *</label>
                <select v-model="form.supplier_id" class="form-select" @change="onSupplierChange" required>
                  <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">
                    {{ sup.name }} ({{ sup.currency }})
                  </option>
                </select>
              </div>
              <div class="form-group col-4">
                <label class="form-label">PO Number *</label>
                <input v-model="form.po_number" type="text" class="form-input" placeholder="e.g. IPO-2026-0001" required />
              </div>
              <div class="form-group col-4">
                <label class="form-label">Bank Settlement Rate (BDT) *</label>
                <input v-model="form.exchange_rate_to_bdt" type="number" step="0.0001" class="form-input" required />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-4">
                <label class="form-label">Payment Method</label>
                <select v-model="form.payment_method" class="form-select">
                  <option value="letter_of_credit">Letter of Credit (LC)</option>
                  <option value="telegraphic_transfer">Telegraphic Transfer (TT)</option>
                  <option value="open_account">Open Account</option>
                </select>
              </div>
              <div class="form-group col-4">
                <label class="form-label">LC Number</label>
                <input v-model="form.lc_number" type="text" class="form-input" placeholder="e.g. LC-EBL-2026-001" />
              </div>
              <div class="form-group col-4">
                <label class="form-label">LC Issuing Bank</label>
                <input v-model="form.lc_issuing_bank" type="text" class="form-input" placeholder="e.g. Eastern Bank PLC" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-6">
                <label class="form-label">Port of Loading</label>
                <input v-model="form.port_of_loading" type="text" class="form-input" />
              </div>
              <div class="form-group col-6">
                <label class="form-label">Port of Discharge (Bangladesh) *</label>
                <input v-model="form.port_of_discharge" type="text" class="form-input" required />
              </div>
            </div>

            <!-- Items Table -->
            <div class="items-section">
              <div class="items-header">
                <h3>Order Items (Commercial Invoice Lines)</h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="addItemRow">➕ Add Item Line</button>
              </div>
              <table class="items-table">
                <thead>
                  <tr>
                    <th>Product & Matrix Variant</th>
                    <th style="width: 140px;">Quantity</th>
                    <th style="width: 180px;">Unit Price ({{ form.currency }})</th>
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
                      <input v-model="item.unit_price_foreign" type="number" step="0.0001" min="0" class="form-input" required />
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
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Create Import PO</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.import-container {
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

.po-info {
  display: flex;
  flex-direction: column;
  gap: 2px;

  .po-number {
    color: $color-primary;
  }

  .items-count {
    font-size: $fs-xs;
    color: $color-text-muted;
  }
}

.supplier-info, .lc-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: $fs-xs;
}

.fob-bdt {
  color: #16a34a;
  font-size: $fs-base;
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: $radius-full;
  font-size: $fs-xs;
  font-weight: 600;

  &.badge-pi_received {
    background: hsla(217, 91%, 60%, 0.1);
    color: #2563eb;
  }

  &.badge-lc_opened {
    background: hsla(262, 83%, 58%, 0.1);
    color: #7c3aed;
  }

  &.badge-in_transit {
    background: hsla(38, 92%, 50%, 0.1);
    color: #d97706;
  }

  &.badge-customs_clearing {
    background: hsla(180, 80%, 40%, 0.1);
    color: #0d9488;
  }

  &.badge-received {
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
  .col-6 {
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

