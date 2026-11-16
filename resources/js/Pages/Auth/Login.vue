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

const fillDemoAdmin = () => {
  form.email = 'admin@ecommerce.test';
  form.password = 'password';
  form.remember = true;
};

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
    <Head title="Secure Sign In - Pick & Pearl Admin Portal" />

    <div class="login-card-container">
      <!-- Security Badge Strip -->
      <div class="security-shield-bar">
        <span class="shield-tag">
          <span class="shield-dot" /> 256-Bit TLS Encrypted
        </span>
        <span v-if="botSecurity?.enabled" class="shield-tag badge-captcha">
          <span>🤖</span> reCAPTCHA Protected
        </span>
        <span class="shield-tag">
          <span>🛡️</span> Anti-Brute Force
        </span>
      </div>

      <!-- Brand & Header -->
      <div class="login-header">
        <Link href="/" class="brand-link">
          <img src="/images/logo.jpg" alt="Pick & Pearl" class="login-brand-logo" />
          <div class="brand-text-block">
            <span class="brand-title">Pick & Pearl</span>
            <span class="brand-subtitle">Jewelry • Fashion • Accessories</span>
          </div>
        </Link>
        <h1 class="login-title">Secure Admin Sign In</h1>
        <p class="login-subtitle">
          Protected commercial back-office gateway for procurement, inventory, logistics, and NBR tax operations.
        </p>
      </div>

      <!-- Flash Notifications -->
      <div v-if="page.props.flash?.info || page.props.flash?.error" class="login-flash-wrap">
        <div v-if="page.props.flash?.info" class="alert alert-info">
          <span>ℹ️</span> {{ page.props.flash.info }}
        </div>
        <div v-if="page.props.flash?.error" class="alert alert-danger">
          <span>⚠️</span> {{ page.props.flash.error }}
        </div>
      </div>

      <!-- Quick Demo Login Helper -->
      <div class="demo-helper-box">
        <div class="demo-info">
          <span class="demo-icon">🔑</span>
          <div class="demo-text">
            <strong>Default Super Admin Account</strong>
            <span class="demo-creds">admin@ecommerce.test / password</span>
          </div>
        </div>
        <button type="button" class="btn-fill-demo" @click="fillDemoAdmin">
          Auto Fill
        </button>
      </div>

      <!-- Login Form -->
      <form class="login-form" @submit.prevent="submit">
        <!-- 🛡️ Invisible Spambot Honeypot Field -->
        <input
          v-model="form.user_company_fax"
          type="text"
          name="user_company_fax"
          class="invisible-honeypot"
          tabindex="-1"
          autocomplete="off"
          aria-hidden="true"
        >

        <!-- 🛡️ Form Submission Timing Token -->
        <input
          v-model="form._form_timing_token"
          type="hidden"
          name="_form_timing_token"
        >

        <!-- Email Field -->
        <div class="form-group">
          <label for="email" class="form-label">Authorized Email Address</label>
          <div class="input-wrapper">
            <span class="input-icon">✉️</span>
            <input
              id="email"
              v-model="form.email"
              type="email"
              class="form-input"
              :class="{ 'is-invalid': form.errors.email }"
              placeholder="admin@ecommerce.test"
              required
              autocomplete="email"
              autofocus
            >
          </div>
          <span v-if="form.errors.email" class="form-error">{{ form.errors.email }}</span>
        </div>

        <!-- Password Field -->
        <div class="form-group">
          <div class="password-label-row">
            <label for="password" class="form-label">Master Password</label>
            <button
              type="button"
              class="toggle-pwd-btn"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
          <div class="input-wrapper">
            <span class="input-icon">🔒</span>
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-input"
              :class="{ 'is-invalid': form.errors.password }"
              placeholder="••••••••"
              required
              autocomplete="current-password"
            >
          </div>
          <span v-if="form.errors.password" class="form-error">{{ form.errors.password }}</span>
        </div>

        <!-- Google reCAPTCHA v2 Checkbox Container -->
        <div
          v-if="botSecurity?.enabled && botSecurity.provider === 'recaptcha_v2'"
          class="captcha-wrapper"
        >
          <div class="captcha-label-row">
            <span class="captcha-title">🤖 Bot Protection &amp; Verification</span>
          </div>
          <div id="recaptcha-v2-container" class="recaptcha-box" />
          <span v-if="form.errors.captcha" class="form-error captcha-error">{{ form.errors.captcha }}</span>
        </div>

        <!-- Cloudflare Turnstile Container -->
        <div
          v-if="botSecurity?.enabled && botSecurity.provider === 'turnstile'"
          class="captcha-wrapper"
        >
          <div id="turnstile-widget" />
          <span v-if="form.errors.captcha" class="form-error">{{ form.errors.captcha }}</span>
        </div>

        <!-- CAPTCHA Error Display for v3 -->
        <div v-if="form.errors.captcha" class="form-error text-center">
          {{ form.errors.captcha }}
        </div>

        <!-- Remember Me Checkbox -->
        <div class="form-options-row">
          <label class="remember-label">
            <input
              v-model="form.remember"
              type="checkbox"
              class="remember-checkbox"
            >
            <span>Maintain secure 30-day session</span>
          </label>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          class="btn-submit"
          :disabled="form.processing"
        >
          <span v-if="form.processing">Verifying Credentials...</span>
          <span v-else>🛡️ Authenticate & Sign In →</span>
        </button>
      </form>

      <!-- Security Notice Footer -->
      <div class="security-audit-notice">
        <span class="lock-icon">🔒</span>
        <span class="notice-text">
          Access attempts are logged with client IP address, timestamp and browser fingerprint for compliance audit. Unauthorized access is prohibited.
        </span>
      </div>

      <!-- Footer Return Link -->
      <div class="login-footer">
        <Link href="/" class="return-storefront-link">
          ← Return to Customer Storefront
        </Link>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, $color-neutral-900 0%, #1c0813 50%, $color-neutral-900 100%);
  padding: $space-6 $space-4;
}

.login-card-container {
  width: 100%;
  max-width: 460px;
  background: $color-neutral-0;
  border-radius: $radius-2xl;
  padding: $space-8 $space-6;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
  border: 1px solid rgba($color-primary-300, 0.25);

  @media (max-width: 480px) {
    padding: $space-6 $space-4;
  }
}

.security-shield-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: $space-2;
  margin-bottom: $space-6;
  padding-bottom: $space-4;
  border-bottom: 1px solid $color-neutral-200;

  .shield-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: $fs-2xs;
    font-weight: $fw-bold;
    color: $color-neutral-600;
    background: $color-neutral-100;
    padding: 2px $space-2;
    border-radius: $radius-sm;

    .shield-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background-color: $color-success;
    }

    &.badge-captcha {
      background: $color-primary-50;
      color: $color-primary-700;
      border: 1px solid $color-primary-200;
    }
  }
}

.login-header {
  text-align: center;
  margin-bottom: $space-6;

  .brand-link {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    margin-bottom: $space-3;

    .login-brand-logo {
      width: 48px;
      height: 48px;
      border-radius: $radius-full;
      object-fit: cover;
      border: 1.5px solid #d4af37;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: transform $transition-fast;
    }

    &:hover .login-brand-logo {
      transform: scale(1.05);
    }

    .brand-text-block {
      display: flex;
      flex-direction: column;
      text-align: left;
      line-height: 1.15;

      .brand-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 1.4rem;
        font-weight: 800;
        color: $color-neutral-900;
        letter-spacing: 0.04em;
      }

      .brand-subtitle {
        font-size: 8.5px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #d4af37;
        font-weight: 700;
      }
    }
  }

  .login-title {
    font-size: $fs-xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    margin-bottom: $space-2;
  }

  .login-subtitle {
    font-size: $fs-xs;
    color: $color-neutral-500;
    line-height: 1.5;
  }
}

.login-flash-wrap {
  margin-bottom: $space-4;

  .alert {
    padding: $space-3 $space-4;
    border-radius: $radius-lg;
    font-size: $fs-xs;
    font-weight: $fw-medium;
    display: flex;
    align-items: center;
    gap: $space-2;

    &-info {
      background: hsl(199, 89%, 95%);
      color: hsl(199, 89%, 28%);
      border: 1px solid hsl(199, 89%, 80%);
    }

    &-danger {
      background: hsl(0, 84%, 96%);
      color: hsl(0, 84%, 35%);
      border: 1px solid hsl(0, 84%, 85%);
    }
  }
}

.demo-helper-box {
  background: $color-primary-50;
  border: 1px dashed $color-primary-300;
  border-radius: $radius-xl;
  padding: $space-3 $space-4;
  margin-bottom: $space-6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $space-3;

  .demo-info {
    display: flex;
    align-items: center;
    gap: $space-2;

    .demo-icon {
      font-size: $fs-lg;
    }

    .demo-text {
      display: flex;
      flex-direction: column;
      font-size: $fs-xs;
      color: $color-neutral-800;

      .demo-creds {
        font-family: $font-mono;
        font-size: $fs-2xs;
        color: $color-primary-700;
        font-weight: $fw-semibold;
      }
    }
  }

  .btn-fill-demo {
    padding: 4px $space-3;
    font-size: $fs-2xs;
    font-weight: $fw-bold;
    background: $color-primary-600;
    color: #ffffff;
    border: none;
    border-radius: $radius-md;
    cursor: pointer;
    white-space: nowrap;
    transition: background $transition-fast;

    &:hover {
      background: $color-primary-700;
    }
  }
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: $space-4;

  .invisible-honeypot {
    display: none !important;
    position: absolute;
    left: -9999px;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: $space-1;
  }

  .form-label {
    font-size: $fs-xs;
    font-weight: $fw-bold;
    color: $color-neutral-700;
  }

  .password-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .toggle-pwd-btn {
      background: none;
      border: none;
      color: $color-primary-600;
      font-size: $fs-2xs;
      font-weight: $fw-semibold;
      cursor: pointer;

      &:hover {
        text-decoration: underline;
      }
    }
  }

  .input-wrapper {
    position: relative;
    display: flex;
    align-items: center;

    .input-icon {
      position: absolute;
      left: $space-3;
      font-size: $fs-sm;
      opacity: 0.6;
      pointer-events: none;
    }

    .form-input {
      width: 100%;
      height: 44px;
      padding: 0 $space-4 0 ($space-8 + $space-1);
      border: 1.5px solid $color-neutral-300;
      border-radius: $radius-lg;
      font-size: $fs-sm;
      color: $color-neutral-900;
      background: $color-neutral-50;
      outline: none;
      transition: all $transition-fast;

      &:focus {
        border-color: $color-primary-500;
        background: $color-neutral-0;
        box-shadow: 0 0 0 3px rgba(235, 40, 108, 0.15);
      }

      &.is-invalid {
        border-color: $color-danger;
        background: hsl(0, 84%, 98%);
      }
    }
  }

  .captcha-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: $space-3 0;
    padding: $space-3;
    background: hsl(342, 60%, 98%);
    border: 1px solid hsl(342, 40%, 90%);
    border-radius: $radius-lg;

    .captcha-label-row {
      width: 100%;
      text-align: left;
      margin-bottom: $space-2;

      .captcha-title {
        font-size: $fs-2xs;
        font-weight: $fw-bold;
        color: hsl(342, 70%, 35%);
        text-transform: uppercase;
        letter-spacing: 0.03em;
      }
    }

    .recaptcha-box {
      min-height: 78px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .captcha-error {
      margin-top: $space-2;
      text-align: center;
      width: 100%;
    }
  }

  .form-error {
    font-size: $fs-2xs;
    color: $color-danger;
    font-weight: $fw-semibold;

    &.text-center {
      text-align: center;
    }
  }

  .form-options-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .remember-label {
      display: inline-flex;
      align-items: center;
      gap: $space-2;
      font-size: $fs-xs;
      color: $color-neutral-600;
      cursor: pointer;

      .remember-checkbox {
        accent-color: $color-primary-600;
        width: 15px;
        height: 15px;
        border-radius: $radius-sm;
      }
    }
  }

  .btn-submit {
    height: 46px;
    background: $color-primary-600;
    color: #ffffff;
    border: none;
    border-radius: $radius-xl;
    font-size: $fs-sm;
    font-weight: $fw-bold;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(235, 40, 108, 0.25);
    transition: all $transition-fast;
    margin-top: $space-2;

    &:hover:not(:disabled) {
      background: $color-primary-700;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(235, 40, 108, 0.35);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
}

.security-audit-notice {
  margin-top: $space-6;
  padding: $space-3 $space-4;
  border-radius: $radius-lg;
  background: $color-neutral-100;
  display: flex;
  align-items: flex-start;
  gap: $space-2;

  .lock-icon {
    font-size: $fs-sm;
    flex-shrink: 0;
  }

  .notice-text {
    font-size: $fs-2xs;
    color: $color-neutral-600;
    line-height: 1.4;
  }
}

.login-footer {
  margin-top: $space-4;
  padding-top: $space-4;
  border-top: 1px solid $color-neutral-200;
  text-align: center;

  .return-storefront-link {
    font-size: $fs-xs;
    color: $color-neutral-600;
    text-decoration: none;
    font-weight: $fw-medium;
    transition: color $transition-fast;

    &:hover {
      color: $color-primary-600;
    }
  }
}
</style>
