<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface SecuritySettingData {
  id: number | null;
  provider: 'recaptcha_v2' | 'recaptcha_v3' | 'turnstile';
  site_key: string;
  masked_secret_key: string;
  has_secret_key: boolean;
  is_active: boolean;
  score_threshold: number;
  pages_enabled: string[];
  last_tested_at: string | null;
  last_test_status: string | null;
}

const props = defineProps<{
  setting: SecuritySettingData;
  availablePages: string[];
  availableProviders: string[];
}>();

const form = useForm({
  provider: props.setting.provider || 'recaptcha_v3',
  site_key: props.setting.site_key || '',
  secret_key: '',
  is_active: props.setting.is_active || false,
  score_threshold: props.setting.score_threshold ?? 0.50,
  pages_enabled: props.setting.pages_enabled || ['checkout', 'login', 'register'],
});

const isTesting = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);

function togglePage(page: string) {
  const index = form.pages_enabled.indexOf(page);
  if (index > -1) {
    form.pages_enabled.splice(index, 1);
  } else {
    form.pages_enabled.push(page);
  }
}

async function testConnection() {
  isTesting.value = true;
  testResult.value = null;

  try {
    const response = await fetch('/admin/settings/bot-security/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        provider: form.provider,
        site_key: form.site_key,
        secret_key: form.secret_key,
      }),
    });

    const data = await response.json();
    testResult.value = data;
  } catch (error: any) {
    testResult.value = {
      success: false,
      message: error?.message || 'Connection test failed.',
    };
  } finally {
    isTesting.value = false;
  }
}

function submitForm() {
  form.post('/admin/settings/bot-security');
}
</script>

<template>
  <AdminLayout>
    <Head title="Bot Security & CAPTCHA Settings" />

    <div class="bot-security-page">
      <header class="page-header">
        <div>
          <h1 class="page-title">Bot Security & Anti-Bot Protection</h1>
          <p class="page-subtitle">
            Configure Google reCAPTCHA (v2 Checkbox / v3 Invisible) or Cloudflare Turnstile protection for checkout, authentication, and customer inquiries.
          </p>
        </div>

        <div class="header-status">
          <span
            class="status-pill"
            :class="form.is_active ? 'status-pill--active' : 'status-pill--inactive'"
          >
            {{ form.is_active ? 'PROTECTION ACTIVE' : 'PROTECTION DISABLED' }}
          </span>
        </div>
      </header>

      <form class="settings-form" @submit.prevent="submitForm">
        <!-- Master Active Toggle Card -->
        <div class="card master-toggle-card">
          <div class="master-toggle-info">
            <h3>Master Bot Protection Switch</h3>
            <p>Toggle verification enforcement across all selected public pages.</p>
          </div>
          <label class="switch">
            <input v-model="form.is_active" type="checkbox" />
            <span class="slider round"></span>
          </label>
        </div>

        <!-- Provider Selection Cards -->
        <div class="card">
          <h3 class="card-title">Security Provider</h3>
          <p class="card-subtitle">Choose between Google reCAPTCHA or Cloudflare Turnstile.</p>

          <div class="provider-grid">
            <div
              class="provider-card"
              :class="{ 'provider-card--active': form.provider === 'recaptcha_v3' }"
              @click="form.provider = 'recaptcha_v3'"
            >
              <div class="provider-badge">Invisible AI</div>
              <h4>Google reCAPTCHA v3</h4>
              <p>Frictionless, score-based verification (0.0 to 1.0) without annoying user puzzles.</p>
            </div>

            <div
              class="provider-card"
              :class="{ 'provider-card--active': form.provider === 'turnstile' }"
              @click="form.provider = 'turnstile'"
            >
              <div class="provider-badge provider-badge--privacy">Privacy First</div>
              <h4>Cloudflare Turnstile</h4>
              <p>Smart, privacy-preserving CAPTCHA alternative with zero user data harvesting.</p>
            </div>

            <div
              class="provider-card"
              :class="{ 'provider-card--active': form.provider === 'recaptcha_v2' }"
              @click="form.provider = 'recaptcha_v2'"
            >
              <div class="provider-badge provider-badge--classic">Classic</div>
              <h4>Google reCAPTCHA v2</h4>
              <p>Explicit "I'm not a robot" checkbox challenge with fallback image puzzle.</p>
            </div>
          </div>
        </div>

        <!-- Credentials Card -->
        <div class="card">
          <h3 class="card-title">API Credentials</h3>
          <p class="card-subtitle">Encrypted database storage for provider keys.</p>

          <div class="form-grid">
            <div class="form-group">
              <label>Public Site Key *</label>
              <input
                v-model="form.site_key"
                type="text"
                placeholder="e.g. 6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"
              />
              <span v-if="form.errors.site_key" class="error-text">{{ form.errors.site_key }}</span>
            </div>

            <div class="form-group">
              <label>Secret Key *</label>
              <input
                v-model="form.secret_key"
                type="password"
                :placeholder="setting.has_secret_key ? setting.masked_secret_key : 'Enter provider secret key'"
              />
              <small v-if="setting.has_secret_key" class="helper-text">
                Current key is saved ({{ setting.masked_secret_key }}). Leave blank to keep existing key.
              </small>
              <span v-if="form.errors.secret_key" class="error-text">{{ form.errors.secret_key }}</span>
            </div>
          </div>

          <!-- Score Threshold (for reCAPTCHA v3) -->
          <div v-if="form.provider === 'recaptcha_v3'" class="form-group threshold-group">
            <div class="threshold-header">
              <label>Minimum Human Score Threshold ({{ Number(form.score_threshold).toFixed(2) }})</label>
              <span class="threshold-hint">0.0 (Bot) to 1.0 (Human). Standard recommended: 0.50</span>
            </div>
            <input
              v-model="form.score_threshold"
              type="range"
              min="0.10"
              max="0.90"
              step="0.05"
            />
          </div>
        </div>

        <!-- Protected Pages Card -->
        <div class="card">
          <h3 class="card-title">Protected Pages & Actions</h3>
          <p class="card-subtitle">Select which public-facing actions require bot verification.</p>

          <div class="pages-grid">
            <label
              v-for="page in availablePages"
              :key="page"
              class="page-checkbox-card"
              :class="{ 'page-checkbox-card--checked': form.pages_enabled.includes(page) }"
            >
              <input
                type="checkbox"
                :checked="form.pages_enabled.includes(page)"
                @change="togglePage(page)"
              />
              <div class="page-info">
                <span class="page-name">{{ page.toUpperCase() }}</span>
                <span class="page-desc">Enforce challenge on {{ page }} route</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Connection Test Feedback -->
        <div v-if="testResult" class="test-feedback" :class="testResult.success ? 'test-feedback--success' : 'test-feedback--danger'">
          <div class="test-icon">
            <svg v-if="testResult.success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <div>
            <div class="test-title">{{ testResult.success ? 'Handshake Verified' : 'Handshake Failed' }}</div>
            <div class="test-message">{{ testResult.message }}</div>
          </div>
        </div>

        <!-- Actions Bar -->
        <div class="form-actions">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="isTesting"
            @click="testConnection"
          >
            {{ isTesting ? 'Testing Handshake...' : 'Test API Handshake' }}
          </button>

          <button
            type="submit"
            class="btn btn-primary"
            :disabled="form.processing"
          >
            {{ form.processing ? 'Saving...' : 'Save Security Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.bot-security-page {
  padding: 1.5rem;
  max-width: 900px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.status-pill {
  display: inline-block;
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 9999px;

  &--active {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
  }

  &--inactive {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
  }
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #0f172a;
}

.card-subtitle {
  font-size: 0.8125rem;
  color: #64748b;
  margin-top: 0.25rem;
  margin-bottom: 1.25rem;
}

.master-toggle-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-left: 4px solid #2563eb;
}

.master-toggle-info {
  h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
  }
  p {
    font-size: 0.8125rem;
    color: #64748b;
    margin-top: 0.2rem;
  }
}

.provider-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.provider-card {
  border: 2px solid #e2e8f0;
  border-radius: 0.625rem;
  padding: 1.25rem;
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;

  &:hover {
    border-color: #cbd5e1;
  }

  &--active {
    border-color: #2563eb;
    background: #eff6ff;
  }

  h4 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #0f172a;
    margin-top: 0.5rem;
    margin-bottom: 0.35rem;
  }

  p {
    font-size: 0.75rem;
    color: #64748b;
    line-height: 1.4;
  }
}

.provider-badge {
  display: inline-block;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 0.25rem;
  background: #dbeafe;
  color: #1e40af;

  &--privacy {
    background: #fef3c7;
    color: #b45309;
  }

  &--classic {
    background: #e2e8f0;
    color: #475569;
  }
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.25rem;
}

.form-group {
  margin-bottom: 1rem;

  label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #334155;
    margin-bottom: 0.375rem;
  }

  input[type="text"], input[type="password"] {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;

    &:focus {
      outline: none;
      border-color: #2563eb;
    }
  }
}

.threshold-group {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px dashed #e2e8f0;

  input[type="range"] {
    width: 100%;
    accent-color: #2563eb;
  }
}

.threshold-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.threshold-hint {
  font-size: 0.75rem;
  color: #64748b;
}

.pages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}

.page-checkbox-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    border-color: #cbd5e1;
  }

  &--checked {
    border-color: #2563eb;
    background: #f8fafc;
  }

  input[type="checkbox"] {
    accent-color: #2563eb;
    width: 1.125rem;
    height: 1.125rem;
  }
}

.page-name {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #0f172a;
}

.page-desc {
  display: block;
  font-size: 0.6875rem;
  color: #64748b;
}

.test-feedback {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-radius: 0.5rem;
  margin-bottom: 1.5rem;

  &--success {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
  }

  &--danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
  }

  .test-icon {
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;

    svg {
      width: 100%;
      height: 100%;
    }
  }

  .test-title {
    font-weight: 600;
    font-size: 0.875rem;
  }

  .test-message {
    font-size: 0.8125rem;
  }
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;

  &-primary {
    background: #2563eb;
    color: #ffffff;

    &:hover {
      background: #1d4ed8;
    }
  }

  &-secondary {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #334155;

    &:hover {
      background: #f8fafc;
    }
  }
}

.switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;

  input {
    opacity: 0;
    width: 0;
    height: 0;
  }
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.2s;
  border-radius: 26px;

  &:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.2s;
    border-radius: 50%;
  }
}

input:checked + .slider {
  background-color: #2563eb;
}

input:checked + .slider:before {
  transform: translateX(22px);
}

.helper-text {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.25rem;
  display: block;
}

.error-text {
  font-size: 0.75rem;
  color: #dc2626;
  margin-top: 0.25rem;
  display: block;
}
</style>
