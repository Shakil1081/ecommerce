<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AuthCard from '@/Components/Account/AuthCard.vue';

defineProps<{
  timingToken: string;
}>();

const form = useForm({
  login: '',
  password: '',
  remember: true,
});

const submit = () => {
  form.post('/account/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <StorefrontLayout title="Log in">
    <SeoHead title="Log in to your account" :noindex="true" />

    <AuthCard title="Welcome back" subtitle="Log in to track orders, save addresses and keep a wishlist.">
      <form @submit.prevent="submit" novalidate>
        <div class="sf-field">
          <label class="sf-field-label" for="login">Email or mobile number</label>
          <input
            id="login"
            v-model="form.login"
            class="sf-input"
            :class="{ 'has-error': form.errors.login }"
            type="text"
            autocomplete="username"
            placeholder="you@example.com or 017XXXXXXXX"
            required
            autofocus
          >
          <span v-if="form.errors.login" class="sf-field-error" role="alert">{{ form.errors.login }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            class="sf-input"
            :class="{ 'has-error': form.errors.password }"
            type="password"
            autocomplete="current-password"
            required
          >
          <span v-if="form.errors.password" class="sf-field-error" role="alert">{{ form.errors.password }}</span>
        </div>

        <div class="row-between">
          <label class="sf-check">
            <input v-model="form.remember" type="checkbox"> Keep me logged in
          </label>
          <Link href="/account/forgot-password" class="small-link">Forgot password?</Link>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="form.processing">
          {{ form.processing ? 'Logging in…' : 'Log in' }}
        </button>
      </form>

      <template #footer>
        New here? <Link href="/account/register">Create an account</Link>
      </template>
    </AuthCard>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.row-between {
  @include flex-between;
  margin-bottom: $space-5;
  gap: $space-3;
}

.small-link {
  font-size: $fs-sm;
  color: $color-primary-600;
  text-decoration: none;
}
</style>
