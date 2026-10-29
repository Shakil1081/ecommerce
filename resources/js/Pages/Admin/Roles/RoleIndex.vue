<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface RoleItem {
  id: number;
  name: string;
  permissions_count: number;
  users_count: number;
  is_protected: boolean;
}

const props = defineProps<{
  roles: RoleItem[];
}>();

const deleteRole = (role: RoleItem) => {
  if (role.is_protected) {
    alert('The Super Admin role cannot be deleted.');
    return;
  }
  if (confirm(`Are you sure you want to delete role "${role.name}"?`)) {
    router.delete(`/admin/roles/${role.id}`);
  }
};
</script>

<template>
  <AdminLayout title="Roles & Permissions (RBAC)">
    <Head title="Enterprise Roles & Permissions" />

    <div class="roles-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Enterprise Roles & Permissions</h1>
          <p class="header-subtitle">
            Manage granular domain permissions across catalog, procurement, inventory, orders, logistics, and NBR VAT compliance.
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/staff" class="btn btn-secondary">
            <span>👥</span> Manage Staff Users
          </Link>
          <Link href="/admin/roles/create" class="btn btn-primary">
            <span>➕</span> Create New Role
          </Link>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Role Name</th>
                <th>Permissions</th>
                <th>Assigned Staff</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="role in roles" :key="role.id">
                <td>
                  <div class="role-identity">
                    <span class="role-icon">🛡️</span>
                    <div>
                      <div class="role-name">{{ role.name }}</div>
                      <div class="role-guard">Guard: web</div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge badge-info">
                    {{ role.permissions_count }} Permissions
                  </span>
                </td>
                <td>
                  <span class="badge badge-neutral">
                    {{ role.users_count }} Users
                  </span>
                </td>
                <td>
                  <span v-if="role.is_protected" class="badge badge-warning">
                    System Protected
                  </span>
                  <span v-else class="badge badge-success">
                    Custom Role
                  </span>
                </td>
                <td class="text-right actions-cell">
                  <Link :href="`/admin/roles/${role.id}/edit`" class="btn btn-sm btn-secondary">
                    Edit
                  </Link>
                  <button
                    v-if="!role.is_protected"
                    class="btn btn-sm btn-danger"
                    @click="deleteRole(role)"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.roles-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: $spacing-4;

  .header-title {
    font-size: $font-size-2xl;
    font-weight: 700;
    color: $color-text-main;
    margin-bottom: $spacing-1;
  }

  .header-subtitle {
    font-size: $font-size-sm;
    color: $color-text-muted;
    max-width: 650px;
  }

  .header-actions {
    display: flex;
    gap: $spacing-3;
  }
}

.table-card {
  padding: 0;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;

  th {
    background: $color-surface-hover;
    padding: $spacing-3 $spacing-4;
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-text-muted;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid $color-border;
  }

  td {
    padding: $spacing-4;
    border-bottom: 1px solid $color-border;
    font-size: $font-size-sm;
  }

  tr:last-child td {
    border-bottom: none;
  }

  .text-right {
    text-align: right;
  }
}

.role-identity {
  display: flex;
  align-items: center;
  gap: $spacing-3;

  .role-icon {
    font-size: 1.25rem;
  }

  .role-name {
    font-weight: 600;
    color: $color-text-main;
  }

  .role-guard {
    font-size: $font-size-xs;
    color: $color-text-muted;
  }
}

.actions-cell {
  display: flex;
  justify-content: flex-end;
  gap: $spacing-2;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: $spacing-1 $spacing-2;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  font-weight: 600;

  &.badge-info {
    background: hsla(217, 91%, 60%, 0.12);
    color: $color-primary;
  }

  &.badge-neutral {
    background: $color-surface-hover;
    color: $color-text-muted;
  }

  &.badge-warning {
    background: hsla(38, 92%, 50%, 0.12);
    color: $color-warning;
  }

  &.badge-success {
    background: hsla(142, 71%, 45%, 0.12);
    color: $color-success;
  }
}
</style>

