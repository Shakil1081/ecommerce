<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface PermissionItem {
  name: string;
  label: string;
}

interface PermissionGroup {
  name: string;
  permissions: PermissionItem[];
}

interface RoleData {
  id: number;
  name: string;
  is_protected?: boolean;
}

const props = defineProps<{
  role?: RoleData | null;
  permissionGroups: PermissionGroup[];
  assignedPermissions: string[];
}>();

const isEditing = !!props.role;

const form = useForm({
  name: props.role ? props.role.name : '',
  permissions: [...(props.assignedPermissions || [])],
});

// Group selection helpers
const isGroupFullySelected = (group: PermissionGroup) => {
  return group.permissions.every(p => form.permissions.includes(p.name));
};

const getGroupSelectedCount = (group: PermissionGroup) => {
  return group.permissions.filter(p => form.permissions.includes(p.name)).length;
};

const toggleGroup = (group: PermissionGroup) => {
  const allSelected = isGroupFullySelected(group);
  const groupPermNames = group.permissions.map(p => p.name);

  if (allSelected) {
    // Remove group permissions
    form.permissions = form.permissions.filter(p => !groupPermNames.includes(p));
  } else {
    // Add missing group permissions
    const newPerms = new Set([...form.permissions, ...groupPermNames]);
    form.permissions = Array.from(newPerms);
  }
};

const selectAllSystem = () => {
  const all: string[] = [];
  props.permissionGroups.forEach(g => {
    g.permissions.forEach(p => all.push(p.name));
  });
  form.permissions = all;
};

const clearAllSystem = () => {
  form.permissions = [];
};

const submit = () => {
  if (isEditing && props.role) {
    form.put(`/admin/roles/${props.role.id}`);
  } else {
    form.post('/admin/roles');
  }
};
</script>

<template>
  <AdminLayout :title="isEditing ? `Edit Role: ${role?.name}` : 'Create Role'">
    <Head :title="isEditing ? `Edit Role - ${role?.name}` : 'Create New Role'" />

    <div class="role-form-container">
      <div class="form-header">
        <div>
          <h1 class="header-title">
            {{ isEditing ? `Edit Role: ${role?.name}` : 'Create New Enterprise Role' }}
          </h1>
          <p class="header-subtitle">
            Configure role name and assign granular permissions across trading modules.
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/roles" class="btn btn-secondary">
            Cancel
          </Link>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Role') }}
          </button>
        </div>
      </div>

      <form @submit.prevent="submit" class="role-form">
        <!-- Role Details Card -->
        <div class="card detail-card">
          <h2 class="card-title">Role Information</h2>

          <div class="form-group">
            <label class="form-label" for="role-name">Role Name <span class="required">*</span></label>
            <input
              id="role-name"
              v-model="form.name"
              type="text"
              class="form-control"
              placeholder="e.g. Procurement Officer"
              :disabled="role?.is_protected"
              required
            />
            <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
            <span v-if="role?.is_protected" class="form-hint">
              The Super Admin role name cannot be modified.
            </span>
          </div>
        </div>

        <!-- Permissions Matrix Card -->
        <div class="card matrix-card">
          <div class="matrix-header">
            <div>
              <h2 class="card-title">Domain Permissions Matrix</h2>
              <p class="card-subtitle">
                Total Selected: <strong>{{ form.permissions.length }}</strong> permissions
              </p>
            </div>
            <div class="matrix-global-actions">
              <button type="button" class="btn btn-sm btn-secondary" @click="selectAllSystem">
                Select All
              </button>
              <button type="button" class="btn btn-sm btn-secondary" @click="clearAllSystem">
                Deselect All
              </button>
            </div>
          </div>

          <div class="groups-stack">
            <div
              v-for="group in permissionGroups"
              :key="group.name"
              class="permission-group-card"
            >
              <div class="group-header">
                <div class="group-title-wrap">
                  <h3 class="group-title">{{ group.name }}</h3>
                  <span class="group-counter">
                    {{ getGroupSelectedCount(group) }} / {{ group.permissions.length }}
                  </span>
                </div>
                <button
                  type="button"
                  class="btn-text-action"
                  @click="toggleGroup(group)"
                >
                  {{ isGroupFullySelected(group) ? 'Deselect All' : 'Select All' }}
                </button>
              </div>

              <div class="permissions-grid">
                <label
                  v-for="perm in group.permissions"
                  :key="perm.name"
                  class="permission-checkbox-card"
                  :class="{ active: form.permissions.includes(perm.name) }"
                >
                  <input
                    type="checkbox"
                    :value="perm.name"
                    v-model="form.permissions"
                    class="perm-checkbox"
                  />
                  <div class="perm-info">
                    <span class="perm-code">{{ perm.name }}</span>
                    <span class="perm-label">{{ perm.label }}</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="form-footer">
          <Link href="/admin/roles" class="btn btn-secondary">
            Cancel
          </Link>
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Role') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.role-form-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.form-header {
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
  }

  .header-actions {
    display: flex;
    gap: $spacing-3;
  }
}

.role-form {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.card-title {
  font-size: $font-size-lg;
  font-weight: 600;
  color: $color-text-main;
  margin-bottom: $spacing-2;
}

.card-subtitle {
  font-size: $font-size-sm;
  color: $color-text-muted;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: $spacing-2;
  max-width: 500px;
}

.form-label {
  font-size: $font-size-sm;
  font-weight: 600;
  color: $color-text-main;

  .required {
    color: $color-danger;
  }
}

.form-control {
  padding: $spacing-3 $spacing-4;
  border-radius: $radius-md;
  border: 1px solid $color-border;
  background: $color-surface;
  color: $color-text-main;
  font-size: $font-size-sm;
  outline: none;
  transition: border-color $transition-fast;

  &:focus {
    border-color: $color-primary;
  }

  &:disabled {
    background: $color-surface-hover;
    cursor: not-allowed;
    opacity: 0.7;
  }
}

.form-error {
  font-size: $font-size-xs;
  color: $color-danger;
}

.form-hint {
  font-size: $font-size-xs;
  color: $color-text-muted;
}

.matrix-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: $spacing-6;
  padding-bottom: $spacing-4;
  border-bottom: 1px solid $color-border;
}

.matrix-global-actions {
  display: flex;
  gap: $spacing-2;
}

.groups-stack {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.permission-group-card {
  border: 1px solid $color-border;
  border-radius: $radius-md;
  padding: $spacing-4;
  background: hsla(220, 14%, 96%, 0.4);

  .group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-4;
    padding-bottom: $spacing-3;
    border-bottom: 1px solid hsla(220, 13%, 91%, 0.8);
  }

  .group-title-wrap {
    display: flex;
    align-items: center;
    gap: $spacing-3;
  }

  .group-title {
    font-size: $font-size-base;
    font-weight: 600;
    color: $color-text-main;
  }

  .group-counter {
    background: $color-surface-hover;
    padding: 2px $spacing-2;
    border-radius: $radius-sm;
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-primary;
  }

  .btn-text-action {
    background: none;
    border: none;
    color: $color-primary;
    font-size: $font-size-xs;
    font-weight: 600;
    cursor: pointer;

    &:hover {
      text-decoration: underline;
    }
  }
}

.permissions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: $spacing-3;
}

.permission-checkbox-card {
  display: flex;
  align-items: flex-start;
  gap: $spacing-3;
  padding: $spacing-3;
  border: 1px solid $color-border;
  border-radius: $radius-sm;
  background: $color-surface;
  cursor: pointer;
  transition: all $transition-fast;

  &:hover {
    border-color: $color-primary;
    background: hsla(217, 91%, 60%, 0.04);
  }

  &.active {
    border-color: $color-primary;
    background: hsla(217, 91%, 60%, 0.08);
  }

  .perm-checkbox {
    margin-top: 3px;
    cursor: pointer;
  }

  .perm-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .perm-code {
    font-family: monospace;
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-text-main;
  }

  .perm-label {
    font-size: $font-size-xs;
    color: $color-text-muted;
    line-height: 1.4;
  }
}

.form-footer {
  display: flex;
  justify-content: flex-end;
  gap: $spacing-3;
}
</style>

