<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface StaffData {
  id: number;
  name: string;
  email: string;
}

const props = defineProps<{
  staff?: StaffData | null;
  availableRoles: string[];
  assignedRoles: string[];
}>();

const isEditing = !!props.staff;

const form = useForm({
  name: props.staff ? props.staff.name : '',
  email: props.staff ? props.staff.email : '',
  password: '',
  roles: [...(props.assignedRoles || [])],
});

const submit = () => {
  if (isEditing && props.staff) {
    form.put(`/admin/staff/${props.staff.id}`);
  } else {
    form.post('/admin/staff');
  }
};
</script>

<template>
  <AdminLayout :title="isEditing ? `Edit Staff: ${staff?.name}` : 'Add Staff Member'">
    <Head :title="isEditing ? `Edit Staff - ${staff?.name}` : 'Add Staff Member'" />

    <div class="staff-form-container">
      <div class="form-header">
        <div>
          <h1 class="header-title">
            {{ isEditing ? `Edit Staff: ${staff?.name}` : 'Add New Staff Member' }}
          </h1>
          <p class="header-subtitle">
            Configure staff credentials and assign administrative roles.
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/staff" class="btn btn-secondary">
            Cancel
          </Link>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Staff Member') }}
          </button>
        </div>
      </div>

      <form @submit.prevent="submit" class="staff-form">
        <div class="card form-card">
          <h2 class="card-title">User Account Information</h2>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="staff-name">Full Name <span class="required">*</span></label>
              <input
                id="staff-name"
                v-model="form.name"
                type="text"
                class="form-control"
                placeholder="e.g. Shakil Ahmed"
                required
              />
              <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="staff-email">Work Email <span class="required">*</span></label>
              <input
                id="staff-email"
                v-model="form.email"
                type="email"
                class="form-control"
                placeholder="e.g. shakil@tradinghub.com.bd"
                required
              />
              <span v-if="form.errors.email" class="form-error">{{ form.errors.email }}</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="staff-password">
                Password
                <span v-if="!isEditing" class="required">*</span>
                <span v-else class="optional-hint">(Leave blank to keep current)</span>
              </label>
              <input
                id="staff-password"
                v-model="form.password"
                type="password"
                class="form-control"
                placeholder="••••••••"
                :required="!isEditing"
              />
              <span v-if="form.errors.password" class="form-error">{{ form.errors.password }}</span>
            </div>
          </div>
        </div>

        <div class="card form-card">
          <h2 class="card-title">Assigned Roles & Access Scope</h2>
          <p class="card-subtitle">
            Select one or more roles defining which trading modules this user can access.
          </p>

          <span v-if="form.errors.roles" class="form-error">{{ form.errors.roles }}</span>

          <div class="roles-selection-grid">
            <label
              v-for="role in availableRoles"
              :key="role"
              class="role-choice-card"
              :class="{ active: form.roles.includes(role) }"
            >
              <input
                type="checkbox"
                :value="role"
                v-model="form.roles"
                class="role-checkbox"
              />
              <div class="role-info">
                <span class="role-title">🛡️ {{ role }}</span>
                <span v-if="role === 'Super Admin'" class="role-desc">
                  Full system bypass access to all operations, tax, and settings.
                </span>
                <span v-else class="role-desc">
                  Standard permissions defined in role configuration.
                </span>
              </div>
            </label>
          </div>
        </div>

        <div class="form-footer">
          <Link href="/admin/staff" class="btn btn-secondary">
            Cancel
          </Link>
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Staff Member') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.staff-form-container {
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

.staff-form {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.form-card {
  display: flex;
  flex-direction: column;
  gap: $spacing-4;
}

.card-title {
  font-size: $font-size-lg;
  font-weight: 600;
  color: $color-text-main;
}

.card-subtitle {
  font-size: $font-size-sm;
  color: $color-text-muted;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: $spacing-4;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: $spacing-2;
}

.form-label {
  font-size: $font-size-sm;
  font-weight: 600;
  color: $color-text-main;

  .required {
    color: $color-danger;
  }

  .optional-hint {
    font-size: $font-size-xs;
    font-weight: normal;
    color: $color-text-muted;
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
}

.form-error {
  font-size: $font-size-xs;
  color: $color-danger;
}

.roles-selection-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: $spacing-3;
}

.role-choice-card {
  display: flex;
  align-items: flex-start;
  gap: $spacing-3;
  padding: $spacing-4;
  border: 1px solid $color-border;
  border-radius: $radius-md;
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

  .role-checkbox {
    margin-top: 3px;
    cursor: pointer;
  }

  .role-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .role-title {
    font-size: $font-size-sm;
    font-weight: 600;
    color: $color-text-main;
  }

  .role-desc {
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

