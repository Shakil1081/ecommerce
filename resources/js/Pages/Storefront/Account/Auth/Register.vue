<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AuthCard from '@/Components/Account/AuthCard.vue';

const props = defineProps<{
  timingToken: string;
}>();

const form = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  user_company_fax: '', // honeypot (ensure.human)
  _form_timing_token: props.timingToken,
});

// The ensure.human middleware reports bot-guard failures under a key that is not a form field
const botError = computed(() => (form.errors as Record<string, string | undefined>).bot_protection);

const submit = () => {
  form.post('/account/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <StorefrontLayout title="Create account">
    <SeoHead title="Create your account" :noindex="true" />

    <AuthCard title="Create your account" subtitle="Faster checkout, order history and easy returns.">
      <form @submit.prevent="submit" novalidate>
        <div v-if="botError" class="sf-alert is-error">{{ botError }}</div>

        <div class="sf-field">
          <label class="sf-field-label" for="name">Full name</label>
          <input id="name" v-model="form.name" class="sf-input" :class="{ 'has-error': form.errors.name }" type="text" autocomplete="name" required autofocus>
          <span v-if="form.errors.name" class="sf-field-error" role="alert">{{ form.errors.name }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="phone">Mobile number</label>
          <input id="phone" v-model="form.phone" class="sf-input" :class="{ 'has-error': form.errors.phone }" type="tel" inputmode="tel" autocomplete="tel" placeholder="017XXXXXXXX" required>
          <span v-if="form.errors.phone" class="sf-field-error" role="alert">{{ form.errors.phone }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="email">Email</label>
          <input id="email" v-model="form.email" class="sf-input" :class="{ 'has-error': form.errors.email }" type="email" autocomplete="email" required>
          <span class="sf-field-hint">Used for order receipts and password reset.</span>
          <span v-if="form.errors.email" class="sf-field-error" role="alert">{{ form.errors.email }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="password">Password</label>
          <input id="password" v-model="form.password" class="sf-input" :class="{ 'has-error': form.errors.password }" type="password" autocomplete="new-password" minlength="8" required>
          <span class="sf-field-hint">At least 8 characters.</span>
          <span v-if="form.errors.password" class="sf-field-error" role="alert">{{ form.errors.password }}</span>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="password_confirmation">Confirm password</label>
          <input id="password_confirmation" v-model="form.password_confirmation" class="sf-input" type="password" autocomplete="new-password" required>
        </div>

        <div class="sf-honeypot" aria-hidden="true">
          <label for="user_company_fax">Company fax</label>
          <input id="user_company_fax" v-model="form.user_company_fax" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="form.processing">
          {{ form.processing ? 'Creating account…' : 'Create account' }}
        </button>

        <p class="terms-note">
          By creating an account you agree to our <Link href="/terms">Terms</Link> and <Link href="/privacy">Privacy Policy</Link>.
        </p>
      </form>

      <template #footer>
        Already have an account? <Link href="/account/login">Log in</Link>
      </template>
    </AuthCard>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.terms-note {
  margin-top: $space-4;
  font-size: $fs-xs;
  color: $color-neutral-500;
  text-align: center;

  a {
    color: $color-neutral-700;
  }
}
</style>
