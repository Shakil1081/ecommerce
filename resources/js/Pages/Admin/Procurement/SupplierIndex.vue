<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface SupplierItem {
  id: number;
  ulid: string;
  code: string;
  name: string;
  company_name_bn: string | null;
  type: 'international' | 'local';
  country_code: string;
  currency: string;
  bin_number: string | null;
  tin_number: string | null;
  contact_person: string | null;
  email: string | null;
  phone: string | null;
  is_vds_applicable: boolean;
  is_tds_applicable: boolean;
  is_active: boolean;
}

interface PaginatedSuppliers {
  data: SupplierItem[];
  current_page: number;
  last_page: number;
  total: number;
}

const props = defineProps<{
  suppliers: PaginatedSuppliers;
  filters: {
    search?: string;
    type?: string;
  };
}>();

const showCreateModal = ref(false);
const searchQuery = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');

const form = useForm({
  name: '',
  company_name_bn: '',
  code: '',
  type: 'local' as 'international' | 'local',
  country_code: 'BD',
  currency: 'BDT',
  bin_number: '',
  tin_number: '',
  contact_person: '',
  email: '',
  phone: '',
  address: '',
  payment_terms: '',
  is_vds_applicable: false,
  is_tds_applicable: false,
  is_active: true,
});

const onTypeChange = () => {
  if (form.type === 'international') {
    form.country_code = 'CN';
    form.currency = 'USD';
    form.is_vds_applicable = false;
    form.is_tds_applicable = false;
  } else {
    form.country_code = 'BD';
    form.currency = 'BDT';
    form.is_vds_applicable = true;
    form.is_tds_applicable = true;
  }
};

const handleFilter = () => {
  router.get(
    '/admin/procurement/suppliers',
    { search: searchQuery.value, type: typeFilter.value },
    { preserveState: true, replace: true }
  );
};

const submitSupplier = () => {
  form.post('/admin/procurement/suppliers', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset();
    },
  });
};
</script>

<template>
  <AdminLayout title="Supplier Directory">
    <Head title="Suppliers Directory - International & Local" />

    <div class="supplier-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Supplier Directory</h1>
          <p class="header-subtitle">
            Manage global international manufacturers (China, UAE, USA) and domestic Bangladesh distributors with NBR BIN/e-TIN verification.
          </p>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="showCreateModal = true">
            <span>➕</span> Add New Supplier
          </button>
        </div>
      </div>

      <!-- Filters Toolbar -->
      <div class="card filter-card">
        <div class="filter-row">
          <div class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by supplier name, code, BIN, or email..."
              class="form-input"
              @keyup.enter="handleFilter"
            />
          </div>
          <div class="filter-select-wrapper">
            <select v-model="typeFilter" class="form-select" @change="handleFilter">
              <option value="">All Sourcing Origins</option>
              <option value="international">Global Commercial Import (International)</option>
              <option value="local">Domestic Bangladesh Sourcing (Local)</option>
            </select>
          </div>
          <button class="btn btn-secondary" @click="handleFilter">Filter</button>
        </div>
      </div>

      <!-- Suppliers Table -->
      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Code & Supplier Name</th>
                <th>Sourcing Type</th>
                <th>Origin & Currency</th>
                <th>NBR BIN & e-TIN</th>
                <th>VDS / TDS Withholding</th>
                <th>Contact Information</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="supplier in suppliers.data" :key="supplier.id">
                <td>
                  <div class="supplier-info">
                    <span class="supplier-code">{{ supplier.code }}</span>
                    <strong class="supplier-name">{{ supplier.name }}</strong>
                    <span v-if="supplier.company_name_bn" class="supplier-bn">{{ supplier.company_name_bn }}</span>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="supplier.type === 'international' ? 'badge-primary' : 'badge-info'">
                    {{ supplier.type === 'international' ? 'Global Import' : 'Local Domestic' }}
                  </span>
                </td>
                <td>
                  <div class="origin-info">
                    <span class="flag-code">{{ supplier.country_code }}</span>
                    <span class="currency-tag">{{ supplier.currency }}</span>
                  </div>
                </td>
                <td>
                  <div class="tax-info">
                    <div v-if="supplier.bin_number" class="bin-row">
                      <span class="tax-label">BIN:</span> <code>{{ supplier.bin_number }}</code>
                    </div>
                    <div v-if="supplier.tin_number" class="tin-row">
                      <span class="tax-label">TIN:</span> <code>{{ supplier.tin_number }}</code>
                    </div>
                    <span v-if="!supplier.bin_number && !supplier.tin_number" class="text-muted">N/A</span>
                  </div>
                </td>
                <td>
                  <div class="withholding-badges">
                    <span v-if="supplier.is_vds_applicable" class="badge badge-success">VDS Applicable</span>
                    <span v-if="supplier.is_tds_applicable" class="badge badge-warning">TDS Applicable</span>
                    <span v-if="!supplier.is_vds_applicable && !supplier.is_tds_applicable" class="text-muted">None</span>
                  </div>
                </td>
                <td>
                  <div class="contact-info">
                    <span v-if="supplier.contact_person" class="contact-person">{{ supplier.contact_person }}</span>
                    <span v-if="supplier.email" class="contact-email">{{ supplier.email }}</span>
                    <span v-if="supplier.phone" class="contact-phone">{{ supplier.phone }}</span>
                  </div>
                </td>
                <td>
                  <span class="status-indicator" :class="supplier.is_active ? 'active' : 'inactive'">
                    {{ supplier.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
              <tr v-if="suppliers.data.length === 0">
                <td colspan="7" class="empty-state">No suppliers registered yet. Click "Add New Supplier" to create one.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal-dialog">
        <div class="modal-header">
          <h2 class="modal-title">Register New Sourcing Supplier</h2>
          <button class="modal-close-btn" @click="showCreateModal = false">✕</button>
        </div>
        <form @submit.prevent="submitSupplier">
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-6">
                <label class="form-label">Sourcing Origin Type *</label>
                <select v-model="form.type" class="form-select" @change="onTypeChange">
                  <option value="local">Domestic Bangladesh Sourcing (BDT ৳)</option>
                  <option value="international">Global Commercial Importation (USD/RMB/EUR)</option>
                </select>
              </div>
              <div class="form-group col-6">
                <label class="form-label">Supplier Code *</label>
                <input v-model="form.code" type="text" class="form-input" placeholder="e.g. SUP-LOC-001" required />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-6">
                <label class="form-label">Company / Supplier Name *</label>
                <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Walton Hi-Tech Industries PLC" required />
              </div>
              <div class="form-group col-6">
                <label class="form-label">Company Name (Bangla বাংলা)</label>
                <input v-model="form.company_name_bn" type="text" class="form-input" placeholder="e.g. ওয়ালটন হাই-টেক ইন্ডাস্ট্রিজ" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-6">
                <label class="form-label">Country Code (ISO) *</label>
                <input v-model="form.country_code" type="text" class="form-input" maxlength="3" required />
              </div>
              <div class="form-group col-6">
                <label class="form-label">Settlement Currency *</label>
                <input v-model="form.currency" type="text" class="form-input" maxlength="3" required />
              </div>
            </div>

            <div v-if="form.type === 'local'" class="form-row">
              <div class="form-group col-6">
                <label class="form-label">NBR 9 or 13-Digit BIN Number</label>
                <input v-model="form.bin_number" type="text" class="form-input" placeholder="e.g. 001234567-0101" />
              </div>
              <div class="form-group col-6">
                <label class="form-label">12-Digit e-TIN Number</label>
                <input v-model="form.tin_number" type="text" class="form-input" placeholder="e.g. 123456789012" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-4">
                <label class="form-label">Contact Person</label>
                <input v-model="form.contact_person" type="text" class="form-input" />
              </div>
              <div class="form-group col-4">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-input" />
              </div>
              <div class="form-group col-4">
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-input" />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Address</label>
              <textarea v-model="form.address" class="form-textarea" rows="2"></textarea>
            </div>

            <div v-if="form.type === 'local'" class="form-row checkbox-row">
              <label class="checkbox-label">
                <input v-model="form.is_vds_applicable" type="checkbox" />
                <span>Subject to VAT Deducted at Source (VDS - Mushak 6.6)</span>
              </label>
              <label class="checkbox-label">
                <input v-model="form.is_tds_applicable" type="checkbox" />
                <span>Subject to Tax Deducted at Source (TDS - Income Tax 2023)</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Save Supplier</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.supplier-container {
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
    color: $color-text-main;
  }

  .header-subtitle {
    font-size: $fs-sm;
    color: $color-text-muted;
    margin-top: $space-1;
  }
}

.filter-card {
  padding: $space-4;

  .filter-row {
    display: flex;
    gap: $space-4;
    align-items: center;

    .search-input-wrapper {
      position: relative;
      flex: 1;

      .search-icon {
        position: absolute;
        left: $space-3;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.5;
      }

      .form-input {
        padding-left: $space-8;
        width: 100%;
      }
    }

    .filter-select-wrapper {
      min-width: 280px;
    }
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

.supplier-info {
  display: flex;
  flex-direction: column;
  gap: $space-1;

  .supplier-code {
    font-size: $fs-xs;
    font-weight: 600;
    color: $color-primary;
  }

  .supplier-name {
    color: $color-text-main;
  }

  .supplier-bn {
    font-size: $fs-xs;
    color: $color-text-muted;
  }
}

.origin-info {
  display: flex;
  gap: $space-2;
  align-items: center;

  .flag-code {
    font-weight: 700;
    padding: 2px 6px;
    background: $color-bg-subtle;
    border-radius: $radius-sm;
    border: 1px solid $color-border;
  }

  .currency-tag {
    font-size: $fs-xs;
    color: $color-text-muted;
  }
}

.tax-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: $fs-xs;

  .tax-label {
    font-weight: 600;
    color: $color-text-muted;
  }
}

.withholding-badges {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.contact-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: $fs-xs;

  .contact-person {
    font-weight: 600;
  }

  .contact-email, .contact-phone {
    color: $color-text-muted;
  }
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: $radius-full;
  font-size: $fs-xs;
  font-weight: 600;

  &.badge-primary {
    background: hsla(217, 91%, 60%, 0.1);
    color: #2563eb;
  }

  &.badge-info {
    background: hsla(180, 80%, 40%, 0.1);
    color: #0d9488;
  }

  &.badge-success {
    background: hsla(142, 70%, 40%, 0.1);
    color: #16a34a;
  }

  &.badge-warning {
    background: hsla(38, 92%, 50%, 0.1);
    color: #d97706;
  }
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  font-size: $fs-xs;

  &.active {
    color: #16a34a;
    &::before {
      content: '●';
    }
  }

  &.inactive {
    color: #94a3b8;
    &::before {
      content: '○';
    }
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
  max-width: 720px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
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

  .col-6 {
    flex: 1;
  }
  .col-4 {
    flex: 1;
  }
}

.checkbox-row {
  flex-direction: column;
  gap: $space-2;

  .checkbox-label {
    display: flex;
    align-items: center;
    gap: $space-2;
    font-size: $fs-sm;
    cursor: pointer;
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
  color: $color-text-main;
}

.form-input, .form-select, .form-textarea {
  padding: $space-2 $space-3;
  border: 1px solid $color-border;
  border-radius: $radius-md;
  background: $color-surface;
  font-size: $fs-sm;

  &:focus {
    outline: none;
    border-color: $color-primary;
  }
}
</style>

