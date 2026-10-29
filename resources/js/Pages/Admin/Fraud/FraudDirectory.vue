<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface FraudListEntry {
  id: number;
  type: 'blacklist' | 'whitelist';
  phone_number: string | null;
  ip_address: string | null;
  device_fingerprint: string | null;
  customer_name: string | null;
  reason: string | null;
  blocked_by: {
    id: number;
    name: string;
    email: string;
  } | null;
  created_at: string;
}

interface PaginatedEntries {
  data: FraudListEntry[];
  current_page: number;
  last_page: number;
  total: number;
}

const props = defineProps<{
  entries: PaginatedEntries;
  counts: {
    blacklist: number;
    whitelist: number;
    total: number;
  };
  filters: {
    type: string;
    search?: string;
  };
}>();

const showAddModal = ref(false);
const searchQuery = ref(props.filters.search || '');
const activeTab = ref(props.filters.type || 'blacklist');

const form = useForm({
  type: props.filters.type || 'blacklist',
  customer_name: '',
  phone_number: '',
  ip_address: '',
  device_fingerprint: '',
  reason: '',
  identifier: '',
});

function switchTab(type: string) {
  activeTab.value = type;
  router.get('/admin/fraud-directory', {
    type,
    search: searchQuery.value || undefined,
  }, { preserveState: true });
}

function handleSearch() {
  router.get('/admin/fraud-directory', {
    type: activeTab.value,
    search: searchQuery.value || undefined,
  }, { preserveState: true });
}

function openAddModal() {
  form.type = activeTab.value;
  form.customer_name = '';
  form.phone_number = '';
  form.ip_address = '';
  form.device_fingerprint = '';
  form.reason = '';
  showAddModal.value = true;
}

function submitForm() {
  form.post('/admin/fraud-directory', {
    onSuccess: () => {
      showAddModal.value = false;
      form.reset();
    },
  });
}

function deleteEntry(id: number) {
  if (confirm('Are you sure you want to remove this entry from the fraud directory?')) {
    router.delete(`/admin/fraud-directory/${id}`);
  }
}
</script>

<template>
  <AdminLayout>
    <Head title="Merchant Anti-Fraud Directory" />

    <div class="fraud-directory-page">
      <!-- Header -->
      <header class="page-header">
        <div>
          <h1 class="page-title">Merchant Anti-Fraud Directory</h1>
          <p class="page-subtitle">
            Manage blocked phone numbers, abusive IP addresses, device fingerprints, and VIP customer whitelist.
          </p>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="openAddModal">
            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Customer Entry
          </button>
        </div>
      </header>

      <!-- Stats Summary -->
      <div class="stats-grid">
        <div class="stat-card" :class="{ 'stat-card--active': activeTab === 'blacklist' }" @click="switchTab('blacklist')">
          <div class="stat-icon stat-icon--danger">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
          <div>
            <div class="stat-value">{{ counts.blacklist }}</div>
            <div class="stat-label">Blacklisted (Blocked)</div>
          </div>
        </div>

        <div class="stat-card" :class="{ 'stat-card--active': activeTab === 'whitelist' }" @click="switchTab('whitelist')">
          <div class="stat-icon stat-icon--success">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <div class="stat-value">{{ counts.whitelist }}</div>
            <div class="stat-label">Whitelisted (VIP Zero-Friction)</div>
          </div>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="filters-bar">
        <div class="tabs">
          <button
            class="tab-btn"
            :class="{ 'tab-btn--active': activeTab === 'blacklist' }"
            @click="switchTab('blacklist')"
          >
            Blacklist ({{ counts.blacklist }})
          </button>
          <button
            class="tab-btn"
            :class="{ 'tab-btn--active': activeTab === 'whitelist' }"
            @click="switchTab('whitelist')"
          >
            Whitelist ({{ counts.whitelist }})
          </button>
        </div>

        <div class="search-box">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search phone, IP, device, name or reason..."
            @keyup.enter="handleSearch"
          />
          <button class="btn btn-secondary" @click="handleSearch">Search</button>
        </div>
      </div>

      <!-- Directory Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Customer</th>
              <th>Phone Number</th>
              <th>IP Address</th>
              <th>Device Fingerprint</th>
              <th>Reason / Notes</th>
              <th>Created By</th>
              <th>Date Added</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries.data" :key="entry.id">
              <td class="font-mono">#{{ entry.id }}</td>
              <td>
                <div class="customer-name">{{ entry.customer_name || '—' }}</div>
                <span
                  class="badge"
                  :class="entry.type === 'blacklist' ? 'badge--danger' : 'badge--success'"
                >
                  {{ entry.type === 'blacklist' ? 'BLOCKED' : 'WHITELISTED' }}
                </span>
              </td>
              <td>
                <span v-if="entry.phone_number" class="font-mono text-primary">{{ entry.phone_number }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="entry.ip_address" class="font-mono text-secondary">{{ entry.ip_address }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="entry.device_fingerprint" class="font-mono text-xs" :title="entry.device_fingerprint">
                  {{ entry.device_fingerprint.substring(0, 16) }}...
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="reason-cell">
                <span :title="entry.reason || ''">{{ entry.reason || '—' }}</span>
              </td>
              <td>
                <div v-if="entry.blocked_by" class="text-xs">
                  <div>{{ entry.blocked_by.name }}</div>
                  <div class="text-muted">{{ entry.blocked_by.email }}</div>
                </div>
                <span v-else class="text-muted">System</span>
              </td>
              <td class="text-xs text-muted">
                {{ new Date(entry.created_at).toLocaleDateString() }}
              </td>
              <td class="text-right">
                <button class="btn-delete" title="Remove Entry" @click="deleteEntry(entry.id)">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </td>
            </tr>

            <tr v-if="entries.data.length === 0">
              <td colspan="9" class="empty-state">
                No {{ activeTab }} entries found matching your query.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Entry Modal -->
    <div v-if="showAddModal" class="modal-overlay">
      <div class="modal-card">
        <header class="modal-header">
          <h3>Add to Merchant {{ form.type === 'blacklist' ? 'Blacklist' : 'Whitelist' }}</h3>
          <button class="close-btn" @click="showAddModal = false">&times;</button>
        </header>

        <form @submit.prevent="submitForm">
          <div class="modal-body">
            <div class="form-group">
              <label>Directory List Type *</label>
              <div class="radio-group">
                <label class="radio-label">
                  <input v-model="form.type" type="radio" value="blacklist" />
                  <span class="text-danger font-medium">Blacklist (Block Orders)</span>
                </label>
                <label class="radio-label">
                  <input v-model="form.type" type="radio" value="whitelist" />
                  <span class="text-success font-medium">Whitelist (VIP Zero Friction)</span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label>Customer Name (Optional)</label>
              <input v-model="form.customer_name" type="text" placeholder="e.g. Shakil Ahmed" />
            </div>

            <div class="form-group">
              <label>Bangladesh Phone Number</label>
              <input v-model="form.phone_number" type="text" placeholder="e.g. 01712345678" />
              <small class="helper-text">Accepts 013-019 Bangladesh mobile numbers.</small>
            </div>

            <div class="form-group">
              <label>IP Address</label>
              <input v-model="form.ip_address" type="text" placeholder="e.g. 103.145.12.4" />
            </div>

            <div class="form-group">
              <label>Device Fingerprint</label>
              <input v-model="form.device_fingerprint" type="text" placeholder="Browser / device fingerprint hash" />
            </div>

            <div class="form-group">
              <label>Reason / Audit Note</label>
              <textarea v-model="form.reason" rows="3" placeholder="Reason for blocking or whitelisting this customer..."></textarea>
            </div>

            <div v-if="form.errors.identifier" class="error-banner">
              {{ form.errors.identifier }}
            </div>
          </div>

          <footer class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showAddModal = false">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ form.processing ? 'Saving...' : 'Add to Directory' }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.fraud-directory-page {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
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

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  padding: 1.25rem;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
  }

  &--active {
    border-color: #2563eb;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
  }
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  border-radius: 0.5rem;

  svg {
    width: 1.5rem;
    height: 1.5rem;
  }

  &--danger {
    background: #fef2f2;
    color: #ef4444;
  }

  &--success {
    background: #ecfdf5;
    color: #10b981;
  }
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
}

.filters-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  background: #f1f5f9;
  padding: 0.25rem;
  border-radius: 0.5rem;
}

.tab-btn {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border: none;
  background: transparent;
  color: #64748b;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;

  &--active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
}

.search-box {
  display: flex;
  gap: 0.5rem;

  input {
    min-width: 280px;
    padding: 0.5rem 0.875rem;
    font-size: 0.875rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;

    &:focus {
      outline: none;
      border-color: #2563eb;
    }
  }
}

.table-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;

  th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    text-align: left;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
  }

  td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }

  tr:hover td {
    background: #fafafa;
  }
}

.badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 0.25rem;

  &--danger {
    background: #fef2f2;
    color: #dc2626;
  }

  &--success {
    background: #ecfdf5;
    color: #059669;
  }
}

.customer-name {
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.reason-cell {
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #475569;
}

.btn-delete {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.25rem;

  &:hover {
    color: #ef4444;
    background: #fef2f2;
  }

  svg {
    width: 1.125rem;
    height: 1.125rem;
  }
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: #94a3b8;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-card {
  background: #ffffff;
  border-radius: 0.75rem;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;

  h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #0f172a;
  }

  .close-btn {
    border: none;
    background: transparent;
    font-size: 1.5rem;
    color: #94a3b8;
    cursor: pointer;
  }
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.form-group {
  margin-bottom: 1rem;

  label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #334155;
    margin-bottom: 0.375rem;
  }

  input, textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;

    &:focus {
      outline: none;
      border-color: #2563eb;
    }
  }
}

.radio-group {
  display: flex;
  gap: 1.5rem;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.helper-text {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.25rem;
  display: block;
}

.error-banner {
  background: #fef2f2;
  color: #dc2626;
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  margin-top: 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;

  &-primary {
    background: #2563eb;
    color: #ffffff;

    &:hover {
      background: #1d4ed8;
    }
  }

  &-secondary {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #334155;

    &:hover {
      background: #f8fafc;
    }
  }
}

.btn-icon {
  width: 1rem;
  height: 1rem;
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.text-right {
  text-align: right;
}

.text-danger {
  color: #dc2626;
}

.text-success {
  color: #059669;
}

.text-primary {
  color: #2563eb;
}

.text-secondary {
  color: #475569;
}

.text-muted {
  color: #94a3b8;
}

.text-xs {
  font-size: 0.75rem;
}
</style>
