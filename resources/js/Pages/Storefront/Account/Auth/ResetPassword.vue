<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AuthCard from '@/Components/Account/AuthCard.vue';

const props = defineProps<{
  token: string;
  email: string;
}>();

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post('/account/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <StorefrontLayout title="Set a new password">
    <SeoHead title="Set a new password" :noindex="true" />

    <AuthCard title="Set a new password">
      <form @submit.prevent="submit" novalidate>
        <div class="sf-field">
          <label class="sf-field-label" for="email">Email</label>
          <input id="email" v-model="form.email" class="sf-input" :class="{ 'has-error': form.errors.email }" type="email" autocomplete="email" required>
          <span v-if="form.errors.email" class="sf-field-error" role="alert">{{ form.errors.email }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="password">New password</label>
          <input id="password" v-model="form.password" class="sf-input" :class="{ 'has-error': form.errors.password }" type="password" autocomplete="new-password" minlength="8" required autofocus>
          <span class="sf-field-hint">At least 8 characters.</span>
          <span v-if="form.errors.password" class="sf-field-error" role="alert">{{ form.errors.password }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="password_confirmation">Confirm new password</label>
          <input id="password_confirmation" v-model="form.password_confirmation" class="sf-input" type="password" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="form.processing">
          {{ form.processing ? 'Saving…' : 'Save new password' }}
        </button>
      </form>

      <template #footer>
        <Link href="/account/login">Back to log in</Link>
      </template>
    </AuthCard>
  </StorefrontLayout>
</template>
