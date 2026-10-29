<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface WarehouseItem {
  id: number;
  name: string;
  code: string;
}

interface GrnRecord {
  id: number;
  ulid: string;
  grn_number: string;
  grn_date: string;
  source_type: 'import' | 'local';
  warehouse: WarehouseItem;
  received_by?: {
    name: string;
  };
  supplier_challan_number: string | null;
  truck_or_container_number: string | null;
  status: string;
  total_ordered_quantity: number;
  total_received_quantity: number;
  total_damaged_quantity: number;
  items: Array<{
    id: number;
    quantity_ordered: number;
    quantity_received: number;
    quantity_damaged: number;
    barcode_scanned: string | null;
    bin_location: string | null;
    discrepancy_reason: string | null;
    variant?: {
      sku: string;
    };
  }>;
}

interface PaginatedGrns {
  data: GrnRecord[];
  current_page: number;
  last_page: number;
  total: number;
}

defineProps<{
  grns: PaginatedGrns;
  warehouses: WarehouseItem[];
}>();

const processGrn = (grn: GrnRecord) => {
  if (confirm(`Confirm physical stock intake for ${grn.grn_number} into ${grn.warehouse.name}?`)) {
    router.post(`/admin/procurement/grn/${grn.id}/receive`);
  }
};
</script>

<template>
  <AdminLayout title="Inward GRN Receiving">
    <Head title="Inward Goods Received Notes (GRN) & Receiving" />

    <div class="grn-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Inward Goods Received Notes (GRN)</h1>
          <p class="header-subtitle">
            Physical inward receiving into designated multi-warehouse hubs, barcode scans, and discrepancy tracking.
          </p>
        </div>
      </div>

      <!-- GRN Table -->
      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>GRN Number & Date</th>
                <th>Sourcing Origin</th>
                <th>Destination Warehouse</th>
                <th>Delivery Logistics</th>
                <th>Ordered vs Received</th>
                <th>Damaged Discrepancy</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="grn in grns.data" :key="grn.id">
                <td>
                  <div class="grn-info">
                    <strong class="grn-number">{{ grn.grn_number }}</strong>
                    <span class="grn-date">{{ grn.grn_date }}</span>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="grn.source_type === 'import' ? 'badge-primary' : 'badge-info'">
                    {{ grn.source_type === 'import' ? 'Global Import' : 'Local Purchase' }}
                  </span>
                </td>
                <td>
                  <div class="warehouse-info">
                    <strong class="wh-name">{{ grn.warehouse?.name }}</strong>
                    <span class="wh-code">{{ grn.warehouse?.code }}</span>
                  </div>
                </td>
                <td>
                  <div class="logistics-info">
                    <span v-if="grn.truck_or_container_number">Vehicle/Cont: {{ grn.truck_or_container_number }}</span>
                    <span v-if="grn.supplier_challan_number">Challan: {{ grn.supplier_challan_number }}</span>
                  </div>
                </td>
                <td>
                  <div class="qty-info">
                    <span>Ordered: {{ grn.total_ordered_quantity }} pcs</span>
                    <strong class="received-qty">Received: {{ grn.total_received_quantity }} pcs</strong>
                  </div>
                </td>
                <td>
                  <span v-if="grn.total_damaged_quantity > 0" class="badge badge-danger">
                    ⚠️ {{ grn.total_damaged_quantity }} pcs Damaged
                  </span>
                  <span v-else class="text-muted">None (100% Intact)</span>
                </td>
                <td>
                  <span class="badge" :class="'badge-' + grn.status">
                    {{ grn.status.replace('_', ' ').toUpperCase() }}
                  </span>
                </td>
                <td>
                  <button
                    v-if="grn.status === 'draft' || grn.status === 'in_inspection'"
                    class="btn btn-primary btn-sm"
                    @click="processGrn(grn)"
                  >
                    Commit Stock
                  </button>
                  <span v-else class="text-muted">Committed</span>
                </td>
              </tr>
              <tr v-if="grns.data.length === 0">
                <td colspan="8" class="empty-state">No inward goods received notes generated yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.grn-container {
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

.grn-info, .warehouse-info, .logistics-info, .qty-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: $fs-xs;
}

.received-qty {
  color: #16a34a;
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

  &.badge-completed {
    background: hsla(142, 70%, 40%, 0.1);
    color: #16a34a;
  }

  &.badge-discrepancy_flagged, &.badge-danger {
    background: hsla(0, 84%, 60%, 0.1);
    color: #dc2626;
  }

  &.badge-draft {
    background: hsla(215, 16%, 47%, 0.1);
    color: #64748b;
  }
}

.empty-state {
  text-align: center;
  padding: $space-8;
  color: $color-text-muted;
}
</style>
