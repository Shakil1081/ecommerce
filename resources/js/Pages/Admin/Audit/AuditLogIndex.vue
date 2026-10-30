<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface AuditLogItem {
  id: number;
  ulid: string;
  event: string;
  auditable_type: string | null;
  auditable_id: string | null;
  description: string;
  old_values: Record<string, any> | null;
  new_values: Record<string, any> | null;
  user_id: number | null;
  user_name: string | null;
  user_email: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

interface PaginatedAuditLogs {
  data: AuditLogItem[];
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
  logs: PaginatedAuditLogs;
  eventsList: string[];
  filters: {
    event?: string;
    search?: string;
    date_from?: string;
    date_to?: string;
  };
}>();

const selectedEvent = ref(props.filters.event || '');
const searchQuery = ref(props.filters.search || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

// Inspection Modal state
const inspectingLog = ref<AuditLogItem | null>(null);

const handleFilter = () => {
  router.get('/admin/audit-logs', {
    event: selectedEvent.value || undefined,
    search: searchQuery.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  }, { preserveState: true });
};

const openInspector = (log: AuditLogItem) => {
  inspectingLog.value = log;
};

const closeInspector = () => {
  inspectingLog.value = null;
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};

const getEventBadgeClass = (evt: string) => {
  switch (evt) {
    case 'price_updated': return 'badge-amber';
    case 'stock_adjusted': return 'badge-blue';
    case 'order_status_changed': return 'badge-green';
    case 'mushak_issued': return 'badge-purple';
    case 'fraud_list_updated': return 'badge-red';
    default: return 'badge-gray';
  }
};

const getShortEntityName = (fullType: string | null) => {
  if (!fullType) return '—';
  const parts = fullType.split('\\');
  return parts[parts.length - 1];
};
</script>

<template>
  <AdminLayout>
    <Head title="Enterprise Immutable Audit Trail" />

    <div class="audit-log-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin">Dashboard</Link> / <span>Administration</span> / <span>Audit Trail</span>
          </div>
          <h1 class="page-title">Enterprise Immutable Audit Trail</h1>
          <p class="page-subtitle">Cryptographically frozen record of price modifications, stock movements, order state transitions, and statutory tax entries.</p>
        </div>
      </div>

      <!-- Filters Bar -->
      <div class="filter-card">
        <div class="filter-row">
          <select v-model="selectedEvent" class="input-select" @change="handleFilter">
            <option value="">All Audit Events</option>
            <option v-for="evt in eventsList" :key="evt" :value="evt">
              {{ evt.replace('_', ' ').toUpperCase() }}
            </option>
          </select>

          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search description, operator, IP or entity ID..."
            class="input-search"
            @keyup.enter="handleFilter"
          />

          <div class="date-group">
            <input v-model="dateFrom" type="date" class="input-date" />
            <span>to</span>
            <input v-model="dateTo" type="date" class="input-date" />
          </div>

          <button class="btn btn-primary" @click="handleFilter">
            Filter Logs
          </button>
        </div>
      </div>

      <!-- Audit Logs Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Event</th>
              <th>Entity</th>
              <th>Description</th>
              <th>Operator</th>
              <th>IP Address</th>
              <th class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="logs.data.length === 0">
              <td colspan="7" class="empty-state">No audit logs matching current filter.</td>
            </tr>
            <tr v-for="log in logs.data" :key="log.id">
              <td class="font-mono text-xs whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
              <td>
                <span class="event-badge" :class="getEventBadgeClass(log.event)">
                  {{ log.event.replace('_', ' ') }}
                </span>
              </td>
              <td>
                <div v-if="log.auditable_type" class="font-medium text-xs">
                  {{ getShortEntityName(log.auditable_type) }}
                  <span class="font-mono text-muted">#{{ log.auditable_id }}</span>
                </div>
                <span v-else class="text-muted text-xs">—</span>
              </td>
              <td>
                <div class="log-description">{{ log.description }}</div>
              </td>
              <td>
                <div v-if="log.user_name" class="font-medium text-xs">{{ log.user_name }}</div>
                <div v-if="log.user_email" class="text-muted text-xs font-mono">{{ log.user_email }}</div>
                <span v-if="!log.user_name && !log.user_email" class="text-muted text-xs">System / Automated</span>
              </td>
              <td class="font-mono text-xs text-muted">{{ log.ip_address || '—' }}</td>
              <td class="text-right">
                <button
                  v-if="log.old_values || log.new_values"
                  class="btn-inspect"
                  @click="openInspector(log)"
                >
                  Inspect Diff
                </button>
                <span v-else class="text-muted text-xs">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="logs.last_page > 1" class="pagination-bar">
        <div class="pagination-info">
          Showing page {{ logs.current_page }} of {{ logs.last_page }} (Total {{ logs.total }} audit entries)
        </div>
        <div class="pagination-links">
          <Link
            v-for="(link, idx) in logs.links"
            :key="idx"
            :href="link.url || '#'"
            class="page-link"
            :class="{ active: link.active, disabled: !link.url }"
            v-html="link.label"
          />
        </div>
      </div>

      <!-- Diff Inspector Modal -->
      <div v-if="inspectingLog" class="modal-backdrop" @click.self="closeInspector">
        <div class="modal-dialog">
          <div class="modal-header">
            <div>
              <h2 class="modal-title">Audit Record Mutation Diff</h2>
              <span class="modal-sub font-mono">{{ inspectingLog.event }} &bull; {{ formatDate(inspectingLog.created_at) }}</span>
            </div>
            <button class="modal-close" @click="closeInspector">&times;</button>
          </div>

          <div class="modal-body">
            <div class="description-box">
              {{ inspectingLog.description }}
            </div>

            <div class="diff-grid">
              <div class="diff-col col-old">
                <h3 class="diff-col-title">Previous Values (Before)</h3>
                <pre class="json-code">{{ JSON.stringify(inspectingLog.old_values, null, 2) || 'null' }}</pre>
              </div>
              <div class="diff-col col-new">
                <h3 class="diff-col-title">New Values (After)</h3>
                <pre class="json-code">{{ JSON.stringify(inspectingLog.new_values, null, 2) || 'null' }}</pre>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeInspector">Close Inspector</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.audit-log-page {
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

.filter-row {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  align-items: center;
}

.input-select, .input-date {
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  background: #ffffff;
}

.input-search {
  flex: 1;
  min-width: 200px;
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.8125rem;

  &:focus {
    outline: none;
    border-color: #3b82f6;
  }
}

.date-group {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8125rem;
  color: #64748b;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 600;
  font-size: 0.8125rem;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease-in-out;

  &.btn-primary {
    background-color: #0f172a;
    color: #ffffff;
    &:hover { background-color: #1e293b; }
  }

  &.btn-secondary {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    &:hover { background-color: #e2e8f0; }
  }
}

.table-container {
  margin-top: 1.5rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.8125rem;

  th {
    background-color: #f8fafc;
    color: #475569;
    padding: 0.625rem 0.75rem;
    font-weight: 600;
    border-bottom: 1px solid #e2e8f0;
  }

  td {
    padding: 0.625rem 0.75rem;
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
  .whitespace-nowrap { white-space: nowrap; }
}

.log-description {
  max-width: 28rem;
  line-height: 1.4;
}

.event-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: capitalize;

  &.badge-amber { background: #fef3c7; color: #b45309; }
  &.badge-blue { background: #eff6ff; color: #2563eb; }
  &.badge-green { background: #ecfdf5; color: #059669; }
  &.badge-purple { background: #f5f3ff; color: #7c3aed; }
  &.badge-red { background: #fef2f2; color: #dc2626; }
  &.badge-gray { background: #f1f5f9; color: #475569; }
}

.btn-inspect {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 0.25rem 0.6rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;

  &:hover {
    background: #e2e8f0;
  }
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #94a3b8;
}

.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  font-size: 0.8125rem;
  color: #64748b;
}

.pagination-links {
  display: flex;
  gap: 0.25rem;
}

.page-link {
  padding: 0.35rem 0.7rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  text-decoration: none;
  color: #334155;

  &.active {
    background-color: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
  }

  &.disabled {
    pointer-events: none;
    opacity: 0.5;
  }
}

/* Modal styling */
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}

.modal-dialog {
  background: #ffffff;
  border-radius: 0.5rem;
  width: 100%;
  max-width: 800px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  padding: 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;

  .modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
  }

  .modal-sub {
    font-size: 0.75rem;
    color: #64748b;
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
    &:hover { color: #0f172a; }
  }
}

.modal-body {
  padding: 1.25rem;
  overflow-y: auto;
}

.description-box {
  background: #f8fafc;
  padding: 0.75rem 1rem;
  border-radius: 0.375rem;
  border-left: 3px solid #3b82f6;
  font-size: 0.8125rem;
  color: #334155;
  margin-bottom: 1rem;
}

.diff-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.diff-col {
  display: flex;
  flex-direction: column;

  .diff-col-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 0.5rem;
  }

  &.col-old .diff-col-title { color: #b45309; }
  &.col-new .diff-col-title { color: #059669; }
}

.json-code {
  background: #0f172a;
  color: #f8fafc;
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-family: monospace;
  font-size: 0.75rem;
  overflow-x: auto;
  min-height: 120px;
}

.modal-footer {
  padding: 1rem 1.25rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
}
</style>
