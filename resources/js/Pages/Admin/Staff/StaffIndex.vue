<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface StaffUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
  created_at: string;
}

defineProps<{
  staff: StaffUser[];
}>();

const deleteStaff = (user: StaffUser) => {
  if (confirm(`Are you sure you want to remove staff member "${user.name}"?`)) {
    router.delete(`/admin/staff/${user.id}`);
  }
};
</script>

<template>
  <AdminLayout title="Staff Accounts & Role Assignment">
    <Head title="Staff Users & Roles" />

    <div class="staff-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Staff Accounts & Access Control</h1>
          <p class="header-subtitle">
            Manage back-office staff members, procurement specialists, VAT accountants, and warehouse operators.
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/roles" class="btn btn-secondary">
            <span>🛡️</span> Manage Roles
          </Link>
          <Link href="/admin/staff/create" class="btn btn-primary">
            <span>➕</span> Add Staff Member
          </Link>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Assigned Roles</th>
                <th>Created At</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in staff" :key="user.id">
                <td>
                  <div class="user-identity">
                    <span class="user-avatar">👤</span>
                    <div>
                      <div class="user-name">{{ user.name }}</div>
                      <div class="user-email">{{ user.email }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="roles-badges">
                    <span
                      v-for="role in user.roles"
                      :key="role"
                      class="badge"
                      :class="role === 'Super Admin' ? 'badge-primary' : 'badge-neutral'"
                    >
                      {{ role }}
                    </span>
                    <span v-if="user.roles.length === 0" class="no-roles">
                      No roles assigned
                    </span>
                  </div>
                </td>
                <td>
                  <span class="date-text">{{ user.created_at }}</span>
                </td>
                <td class="text-right actions-cell">
                  <Link :href="`/admin/staff/${user.id}/edit`" class="btn btn-sm btn-secondary">
                    Edit
                  </Link>
                  <button
                    class="btn btn-sm btn-danger"
                    @click="deleteStaff(user)"
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
.staff-container {
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

.user-identity {
  display: flex;
  align-items: center;
  gap: $spacing-3;

  .user-avatar {
    font-size: 1.25rem;
  }

  .user-name {
    font-weight: 600;
    color: $color-text-main;
  }

  .user-email {
    font-size: $font-size-xs;
    color: $color-text-muted;
  }
}

.roles-badges {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-1;

  .no-roles {
    font-size: $font-size-xs;
    color: $color-text-muted;
    font-style: italic;
  }
}

.date-text {
  font-size: $font-size-xs;
  color: $color-text-muted;
}

.actions-cell {
  display: flex;
  justify-content: flex-end;
  gap: $spacing-2;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px $spacing-2;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  font-weight: 600;

  &.badge-primary {
    background: hsla(217, 91%, 60%, 0.12);
    color: $color-primary;
  }

  &.badge-neutral {
    background: $color-surface-hover;
    color: $color-text-muted;
  }
}
</style>

