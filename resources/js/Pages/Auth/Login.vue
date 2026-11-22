<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

interface BotSecurityProps {
  enabled: boolean;
  provider: 'recaptcha_v2' | 'recaptcha_v3' | 'turnstile';
  site_key: string;
}

const props = defineProps<{
  botSecurity?: BotSecurityProps;
  timingToken?: string;
  honeypotField?: string;
}>();

const page = usePage<SharedProps>();

const form = useForm({
  email: '',
  password: '',
  remember: false,
  user_company_fax: '', // Invisible Honeypot trap
  _form_timing_token: props.timingToken || '',
  'g-recaptcha-response': '',
  captcha_token: '',
  captcha: '',
});

const showPassword = ref(false);
const recaptchaWidgetId = ref<number | null>(null);
const isScriptLoaded = ref(false);

// Dynamically load Google reCAPTCHA or Turnstile script when enabled
onMounted(() => {
  if (props.botSecurity?.enabled && props.botSecurity.site_key) {
    if (props.botSecurity.provider === 'turnstile') {
      const renderTurnstile = () => {
        // @ts-expect-error Turnstile global
        if (window.turnstile) {
          const container = document.getElementById('turnstile-widget');
          if (container && !container.hasChildNodes()) {
            // @ts-expect-error Turnstile global
            window.turnstile.render(container, {
              sitekey: props.botSecurity?.site_key,
              callback: (token: string) => {
                form.captcha_token = token;
                form['g-recaptcha-response'] = token;
                form.clearErrors('captcha');
              },
            });
          }
        }
      };

      // @ts-expect-error Turnstile global
      if (window.turnstile) {
        renderTurnstile();
      } else if (!document.querySelector('script[src*="turnstile/v0/api.js"]')) {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.onload = () => {
          isScriptLoaded.value = true;
          renderTurnstile();
        };
        document.head.appendChild(script);
      }
    } else if (props.botSecurity.provider === 'recaptcha_v2') {
      const renderRecaptchaV2 = () => {
        // @ts-expect-error grecaptcha global
        if (window.grecaptcha && window.grecaptcha.render) {
          const container = document.getElementById('recaptcha-v2-container');
          if (container && !container.hasChildNodes()) {
            try {
              // @ts-expect-error grecaptcha global
              recaptchaWidgetId.value = window.grecaptcha.render(container, {
                sitekey: props.botSecurity?.site_key,
                callback: (response: string) => {
                  form['g-recaptcha-response'] = response;
                  form.captcha_token = response;
                  form.clearErrors('captcha');
                },
                'expired-callback': () => {
                  form['g-recaptcha-response'] = '';
                  form.captcha_token = '';
                },
              });
              isScriptLoaded.value = true;
            } catch (err) {
              console.warn('reCAPTCHA render notice:', err);
            }
          }
        }
      };

      // @ts-expect-error Window callback
      window.onGoogleRecaptchaLoaded = renderRecaptchaV2;

      // Check if grecaptcha already loaded on window (e.g. client navigation)
      // @ts-expect-error grecaptcha global
      if (window.grecaptcha && window.grecaptcha.render) {
        renderRecaptchaV2();
      } else if (!document.querySelector('script[src*="recaptcha/api.js"]')) {
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?onload=onGoogleRecaptchaLoaded&render=explicit';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
      }
    } else if (props.botSecurity.provider === 'recaptcha_v3') {
      if (!document.querySelector(`script[src*="render=${props.botSecurity.site_key}"]`)) {
        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.js?render=${props.botSecurity.site_key}`;
        script.async = true;
        script.defer = true;
        script.onload = () => {
          isScriptLoaded.value = true;
        };
        document.head.appendChild(script);
      } else {
        isScriptLoaded.value = true;
      }
    }
  }
});

const submit = async () => {
  // Validate reCAPTCHA v2 checkbox is completed
  if (
    props.botSecurity?.enabled &&
    props.botSecurity.provider === 'recaptcha_v2' &&
    !form['g-recaptcha-response'] &&
    !form.captcha_token
  ) {
    form.setError('captcha', 'Please check the box to confirm you are not a robot.');
    return;
  }

  // Validate Turnstile token is completed
  if (
    props.botSecurity?.enabled &&
    props.botSecurity.provider === 'turnstile' &&
    !form.captcha_token
  ) {
    form.setError('captcha', 'Please complete the Cloudflare security verification.');
    return;
  }

  // Execute reCAPTCHA v3 invisible execution if applicable
  if (
    props.botSecurity?.enabled &&
    props.botSecurity.provider === 'recaptcha_v3' &&
    props.botSecurity.site_key
  ) {
    // @ts-expect-error grecaptcha global
    if (window.grecaptcha) {
      try {
        // @ts-expect-error grecaptcha global
        const token = await window.grecaptcha.execute(props.botSecurity.site_key, { action: 'admin_login' });
        form['g-recaptcha-response'] = token;
        form.captcha_token = token;
      } catch (err) {
        console.warn('reCAPTCHA execution fallback:', err);
      }
    }
  }

  form.post('/login', {
    onFinish: () => {
      form.reset('password');
      // Reset v2 widget if exists
      // @ts-expect-error grecaptcha global
      if (recaptchaWidgetId.value !== null && window.grecaptcha) {
        // @ts-expect-error grecaptcha global
        window.grecaptcha.reset(recaptchaWidgetId.value);
        form['g-recaptcha-response'] = '';
        form.captcha_token = '';
      }
    },
  });
};
</script>

<template>
  <div class="login-page">
    <Head title="Admin Sign In" />

    <div class="login-card">
      <Link href="/" class="brand-link">
        <img src="/images/logo.jpg" alt="Pick & Pearl" class="brand-logo">
        <span class="brand-title">Pick &amp; Pearl</span>
      </Link>

      <h1 class="login-title">Admin sign in</h1>

      <div v-if="page.props.flash?.info" class="alert alert-info">{{ page.props.flash.info }}</div>
      <div v-if="page.props.flash?.error" class="alert alert-danger">{{ page.props.flash.error }}</div>

      <form class="login-form" @submit.prevent="submit">
        <!-- Invisible honeypot + timing token (bot protection) -->
        <input
          v-model="form.user_company_fax"
          type="text"
          name="user_company_fax"
          class="invisible-honeypot"
          tabindex="-1"
          autocomplete="off"
          aria-hidden="true"
        >
        <input v-model="form._form_timing_token" type="hidden" name="_form_timing_token">

        <div class="form-group">
          <label for="email" class="form-label">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="form-input"
            :class="{ 'is-invalid': form.errors.email }"
            required
            autocomplete="email"
            autofocus
          >
          <span v-if="form.errors.email" class="form-error">{{ form.errors.email }}</span>
        </div>

        <div class="form-group">
          <div class="password-label-row">
            <label for="password" class="form-label">Password</label>
            <button type="button" class="toggle-pwd-btn" @click="showPassword = !showPassword">
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="form-input"
            :class="{ 'is-invalid': form.errors.password }"
            required
            autocomplete="current-password"
          >
          <span v-if="form.errors.password" class="form-error">{{ form.errors.password }}</span>
        </div>

        <!-- Google reCAPTCHA v2 -->
        <div v-if="botSecurity?.enabled && botSecurity.provider === 'recaptcha_v2'" class="captcha-wrapper">
          <div id="recaptcha-v2-container" class="recaptcha-box" />
        </div>

        <!-- Cloudflare Turnstile -->
        <div v-if="botSecurity?.enabled && botSecurity.provider === 'turnstile'" class="captcha-wrapper">
          <div id="turnstile-widget" />
        </div>

        <span v-if="form.errors.captcha" class="form-error text-center">{{ form.errors.captcha }}</span>

        <label class="remember-label">
          <input v-model="form.remember" type="checkbox" class="remember-checkbox">
          <span>Keep me signed in</span>
        </label>

        <button type="submit" class="btn-submit" :disabled="form.processing">
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <Link href="/" class="return-link">← Back to store</Link>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: $color-neutral-100;
  padding: $space-6 $space-4;
}

.login-card {
  width: 100%;
  max-width: 400px;
  background: $color-neutral-0;
  border-radius: $radius-xl;
  padding: $space-8 $space-6;
  box-shadow: $shadow-lg;

  @media (max-width: 480px) {
    padding: $space-6 $space-4;
  }
}

.brand-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: $space-3;
  text-decoration: none;
  margin-bottom: $space-6;

  .brand-logo {
    width: 40px;
    height: 40px;
    border-radius: $radius-full;
    object-fit: cover;
  }

  .brand-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: $fs-xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }
}

.login-title {
  font-size: $fs-xl;
  font-weight: $fw-bold;
  color: $color-neutral-900;
  text-align: center;
  margin-bottom: $space-6;
}

.alert {
  padding: $space-3 $space-4;
  border-radius: $radius-md;
  font-size: $fs-sm;
  margin-bottom: $space-4;

  &-info {
    background: hsl(199, 89%, 95%);
    color: hsl(199, 89%, 28%);
  }

  &-danger {
    background: hsl(0, 84%, 96%);
    color: hsl(0, 84%, 35%);
  }
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: $space-4;

  .invisible-honeypot {
    display: none !important;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: $space-1;
  }

  .form-label {
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    color: $color-neutral-700;
  }

  .password-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .toggle-pwd-btn {
      background: none;
      border: none;
      padding: 0;
      color: $color-primary-600;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      cursor: pointer;
    }
  }

  .form-input {
    width: 100%;
    height: 44px;
    padding: 0 $space-4;
    border: 1px solid $color-neutral-300;
    border-radius: $radius-md;
    font-size: 16px;
    color: $color-neutral-900;
    background: $color-neutral-0;
    outline: none;
    transition: border-color $transition-fast, box-shadow $transition-fast;

    &:focus {
      border-color: $color-primary-500;
      box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.12);
    }

    &.is-invalid {
      border-color: $color-danger;
    }
  }

  .captcha-wrapper {
    display: flex;
    justify-content: center;

    .recaptcha-box {
      min-height: 78px;
    }
  }

  .form-error {
    font-size: $fs-xs;
    color: $color-danger;

    &.text-center {
      text-align: center;
    }
  }

  .remember-label {
    display: inline-flex;
    align-items: center;
    gap: $space-2;
    font-size: $fs-sm;
    color: $color-neutral-600;
    cursor: pointer;

    .remember-checkbox {
      accent-color: $color-primary-600;
      width: 15px;
      height: 15px;
    }
  }

  .btn-submit {
    height: 46px;
    background: $color-primary-600;
    color: #ffffff;
    border: none;
    border-radius: $radius-md;
    font-size: $fs-base;
    font-weight: $fw-semibold;
    cursor: pointer;
    transition: background $transition-fast;

    &:hover:not(:disabled) {
      background: $color-primary-700;
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
}

.return-link {
  display: block;
  margin-top: $space-5;
  text-align: center;
  font-size: $fs-sm;
  color: $color-neutral-600;
  text-decoration: none;

  &:hover {
    color: $color-primary-600;
  }
}
</style>
