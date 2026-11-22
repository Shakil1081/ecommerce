<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AccountShell from '@/Components/Account/AccountShell.vue';

const props = defineProps<{
  customer: { name: string; email: string; phone: string | null };
}>();

const profileForm = useForm({
  name: props.customer.name,
  email: props.customer.email,
  phone: props.customer.phone ?? '',
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const saveProfile = () => profileForm.put('/account/profile', { preserveScroll: true });

const savePassword = () => {
  passwordForm.put('/account/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => passwordForm.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <StorefrontLayout title="Profile & Password">
    <SeoHead title="Profile & Password" :noindex="true" />

    <AccountShell heading="Profile & Password">
      <div class="forms-grid">
        <form class="card panel" @submit.prevent="saveProfile" novalidate>
          <h2>Personal details</h2>

          <div class="sf-field">
            <label class="sf-field-label" for="name">Full name</label>
            <input id="name" v-model="profileForm.name" class="sf-input" :class="{ 'has-error': profileForm.errors.name }" type="text" autocomplete="name" required>
            <span v-if="profileForm.errors.name" class="sf-field-error">{{ profileForm.errors.name }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="phone">Mobile number</label>
            <input id="phone" v-model="profileForm.phone" class="sf-input" :class="{ 'has-error': profileForm.errors.phone }" type="tel" inputmode="tel" autocomplete="tel" required>
            <span v-if="profileForm.errors.phone" class="sf-field-error">{{ profileForm.errors.phone }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="email">Email</label>
            <input id="email" v-model="profileForm.email" class="sf-input" :class="{ 'has-error': profileForm.errors.email }" type="email" autocomplete="email" required>
            <span v-if="profileForm.errors.email" class="sf-field-error">{{ profileForm.errors.email }}</span>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="profileForm.processing || !profileForm.isDirty">
            {{ profileForm.processing ? 'Saving…' : 'Save changes' }}
          </button>
        </form>

        <form class="card panel" @submit.prevent="savePassword" novalidate>
          <h2>Change password</h2>

          <div class="sf-field">
            <label class="sf-field-label" for="current_password">Current password</label>
            <input id="current_password" v-model="passwordForm.current_password" class="sf-input" :class="{ 'has-error': passwordForm.errors.current_password }" type="password" autocomplete="current-password" required>
            <span v-if="passwordForm.errors.current_password" class="sf-field-error">{{ passwordForm.errors.current_password }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="new_password">New password</label>
            <input id="new_password" v-model="passwordForm.password" class="sf-input" :class="{ 'has-error': passwordForm.errors.password }" type="password" autocomplete="new-password" minlength="8" required>
            <span class="sf-field-hint">At least 8 characters.</span>
            <span v-if="passwordForm.errors.password" class="sf-field-error">{{ passwordForm.errors.password }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" v-model="passwordForm.password_confirmation" class="sf-input" type="password" autocomplete="new-password" required>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="passwordForm.processing">
            {{ passwordForm.processing ? 'Saving…' : 'Change password' }}
          </button>
        </form>
      </div>
    </AccountShell>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.forms-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: $space-6;
  align-items: start;

  @media (max-width: 960px) {
    grid-template-columns: 1fr;
  }
}

.panel {
  padding: $space-5;

  h2 {
    font-size: $fs-base;
    font-weight: $fw-semibold;
    color: $color-neutral-900;
    margin-bottom: $space-4;
  }
}
</style>
