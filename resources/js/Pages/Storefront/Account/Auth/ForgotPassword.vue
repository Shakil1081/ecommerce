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
  email: '',
  user_company_fax: '',
  _form_timing_token: props.timingToken,
});

// The ensure.human middleware reports bot-guard failures under a key that is not a form field
const botError = computed(() => (form.errors as Record<string, string | undefined>).bot_protection);

const submit = () => form.post('/account/forgot-password');
</script>

<template>
  <StorefrontLayout title="Forgot password">
    <SeoHead title="Reset your password" :noindex="true" />

    <AuthCard title="Forgot your password?" subtitle="Enter your account email and we'll send you a link to set a new password.">
      <form @submit.prevent="submit" novalidate>
        <div v-if="botError" class="sf-alert is-error">{{ botError }}</div>

        <div class="sf-field">
          <label class="sf-field-label" for="email">Email</label>
          <input id="email" v-model="form.email" class="sf-input" :class="{ 'has-error': form.errors.email }" type="email" autocomplete="email" required autofocus>
          <span v-if="form.errors.email" class="sf-field-error" role="alert">{{ form.errors.email }}</span>
        </div>

        <div class="sf-honeypot" aria-hidden="true">
          <input v-model="form.user_company_fax" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="form.processing">
          {{ form.processing ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>

      <template #footer>
        Remembered it? <Link href="/account/login">Back to log in</Link>
      </template>
    </AuthCard>
  </StorefrontLayout>
</template>
