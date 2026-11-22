<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  profile: {
    name: string;
    email: string;
    roles: string[];
    member_since: string | null;
  };
}>();

const detailsForm = useForm({
  name: props.profile.name,
  email: props.profile.email,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const showPasswords = ref(false);

const saveDetails = () => detailsForm.put('/admin/profile', { preserveScroll: true });

const savePassword = () => {
  passwordForm.put('/admin/profile/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => passwordForm.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <AdminLayout title="My Profile">
    <Head title="My Profile" />

    <div class="profile-container">
      <div class="page-header">
        <h1 class="header-title">My Profile</h1>
        <p class="header-subtitle">
          <template v-if="profile.roles.length">Role: <strong>{{ profile.roles.join(', ') }}</strong></template>
          <template v-if="profile.member_since"> · Account created {{ profile.member_since }}</template>
        </p>
      </div>

      <div class="forms-grid">
        <!-- Name & email -->
        <form class="card form-card" @submit.prevent="saveDetails" novalidate>
          <h2 class="card-title">Account details</h2>

          <div class="form-group">
            <label class="form-label" for="name">Full name</label>
            <input id="name" v-model="detailsForm.name" class="form-control" :class="{ 'is-invalid': detailsForm.errors.name }" type="text" autocomplete="name" required>
            <span v-if="detailsForm.errors.name" class="form-error">{{ detailsForm.errors.name }}</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Login email</label>
            <input id="email" v-model="detailsForm.email" class="form-control" :class="{ 'is-invalid': detailsForm.errors.email }" type="email" autocomplete="email" required>
            <span class="form-hint">You sign in at /wp-admin with this email.</span>
            <span v-if="detailsForm.errors.email" class="form-error">{{ detailsForm.errors.email }}</span>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" :disabled="detailsForm.processing || !detailsForm.isDirty">
              {{ detailsForm.processing ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </form>

        <!-- Password -->
        <form class="card form-card" @submit.prevent="savePassword" novalidate>
          <div class="card-title-row">
            <h2 class="card-title">Change password</h2>
            <button type="button" class="toggle-btn" @click="showPasswords = !showPasswords">
              {{ showPasswords ? 'Hide' : 'Show' }}
            </button>
          </div>

          <div class="form-group">
            <label class="form-label" for="current_password">Current password</label>
            <input id="current_password" v-model="passwordForm.current_password" class="form-control" :class="{ 'is-invalid': passwordForm.errors.current_password }" :type="showPasswords ? 'text' : 'password'" autocomplete="current-password" required>
            <span v-if="passwordForm.errors.current_password" class="form-error">{{ passwordForm.errors.current_password }}</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="new_password">New password</label>
            <input id="new_password" v-model="passwordForm.password" class="form-control" :class="{ 'is-invalid': passwordForm.errors.password }" :type="showPasswords ? 'text' : 'password'" autocomplete="new-password" minlength="8" required>
            <span class="form-hint">At least 8 characters.</span>
            <span v-if="passwordForm.errors.password" class="form-error">{{ passwordForm.errors.password }}</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" v-model="passwordForm.password_confirmation" class="form-control" :type="showPasswords ? 'text' : 'password'" autocomplete="new-password" required>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" :disabled="passwordForm.processing">
              {{ passwordForm.processing ? 'Saving…' : 'Change password' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.profile-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
  max-width: 1000px;
}

.page-header {
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
}

.forms-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: $spacing-6;
  align-items: start;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
  }
}

.form-card {
  padding: $spacing-6;
  display: flex;
  flex-direction: column;
  gap: $spacing-4;
}

.card-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: $font-size-lg;
  font-weight: 600;
  color: $color-text-main;
}

.toggle-btn {
  background: none;
  border: none;
  padding: 0;
  font-size: $font-size-sm;
  font-weight: 600;
  color: $color-primary;
  cursor: pointer;
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
}

.form-control {
  padding: $spacing-3 $spacing-4;
  border-radius: $radius-md;
  border: 1px solid $color-border;
  background: $color-surface;
  color: $color-text-main;
  font-size: $font-size-sm;
  font-family: inherit;
  outline: none;
  transition: border-color $transition-fast;

  &:focus {
    border-color: $color-primary;
  }

  &.is-invalid {
    border-color: $color-danger;
  }
}

.form-hint {
  font-size: $font-size-xs;
  color: $color-text-muted;
}

.form-error {
  font-size: $font-size-xs;
  color: $color-danger;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}
</style>
