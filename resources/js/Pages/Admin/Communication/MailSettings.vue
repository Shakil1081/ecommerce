<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface MailSettingData {
  id: number;
  ulid: string;
  driver: string;
  host: string;
  port: number;
  encryption: string;
  username: string;
  has_password: boolean;
  masked_password: string;
  from_address: string;
  from_name: string;
  is_active: boolean;
  is_configured: boolean;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_message: string | null;
}

const props = defineProps<{
  mailSetting: MailSettingData;
}>();

const form = useForm({
  driver: props.mailSetting.driver || 'smtp',
  host: props.mailSetting.host || '',
  port: props.mailSetting.port || 587,
  encryption: props.mailSetting.encryption || 'tls',
  username: props.mailSetting.username || '',
  password: '',
  from_address: props.mailSetting.from_address || '',
  from_name: props.mailSetting.from_name || '',
  is_active: props.mailSetting.is_active,
});

const testEmail = ref('');
const isTesting = ref(false);
const testResult = ref<{ success: boolean; message: string; latency_ms?: number } | null>(null);

const saveSettings = () => {
  form.post('/admin/communication/mail-settings', {
    preserveScroll: true,
  });
};

const sendTestEmail = async () => {
  if (!testEmail.value) {
    return;
  }

  isTesting.value = true;
  testResult.value = null;

  try {
    const res = await fetch('/admin/communication/mail-settings/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ recipient_email: testEmail.value }),
    });

    const data = await res.json();
    testResult.value = {
      success: data.success,
      message: data.message,
      latency_ms: data.latency_ms,
    };
  } catch (err: any) {
    testResult.value = {
      success: false,
      message: err.message || 'Connection failed',
    };
  } finally {
    isTesting.value = false;
  }
};
</script>

<template>
  <AdminLayout title="Dynamic SMTP Mail Settings">
    <Head title="Dynamic SMTP Mail Settings" />

    <div class="settings-page">
      <div class="page-header">
        <div>
          <h1 class="page-title">Dynamic SMTP Mail Settings</h1>
          <p class="page-subtitle">
            Configure system transactional email transport without modifying .env or server restart.
          </p>
        </div>
        <div class="status-summary">
          <span :class="['status-badge', mailSetting.is_active ? 'active' : 'inactive']">
            {{ mailSetting.is_active ? '● Mailer Active' : '○ Mailer Inactive' }}
          </span>
          <span v-if="mailSetting.last_tested_at" class="last-tested">
            Last tested: {{ mailSetting.last_tested_at }}
          </span>
        </div>
      </div>

      <div class="settings-grid">
        <!-- Settings Form Card -->
        <div class="card form-card">
          <div class="card-header">
            <h3 class="card-title">SMTP Server Configuration</h3>
          </div>
          <div class="card-body">
            <form @submit.prevent="saveSettings">
              <div class="form-row">
                <div class="form-group flex-2">
                  <label for="host">SMTP Host / Server</label>
                  <input
                    id="host"
                    v-model="form.host"
                    type="text"
                    placeholder="e.g. smtp.mailgun.org, smtp.sendgrid.net"
                    required
                    class="form-control"
                  >
                  <span v-if="form.errors.host" class="error-text">{{ form.errors.host }}</span>
                </div>

                <div class="form-group flex-1">
                  <label for="port">Port</label>
                  <input
                    id="port"
                    v-model="form.port"
                    type="number"
                    placeholder="587"
                    required
                    class="form-control"
                  >
                </div>

                <div class="form-group flex-1">
                  <label for="encryption">Encryption</label>
                  <select id="encryption" v-model="form.encryption" class="form-control">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">None</option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group flex-1">
                  <label for="username">SMTP Username</label>
                  <input
                    id="username"
                    v-model="form.username"
                    type="text"
                    placeholder="API key or user"
                    class="form-control"
                  >
                </div>

                <div class="form-group flex-1">
                  <label for="password">
                    SMTP Password / API Key
                    <span v-if="mailSetting.has_password" class="text-muted">
                      (Current: {{ mailSetting.masked_password }})
                    </span>
                  </label>
                  <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    placeholder="Enter new password to change"
                    class="form-control"
                  >
                </div>
              </div>

              <div class="divider" />

              <div class="form-row">
                <div class="form-group flex-1">
                  <label for="from_address">From Email Address</label>
                  <input
                    id="from_address"
                    v-model="form.from_address"
                    type="email"
                    placeholder="orders@yourdomain.com"
                    required
                    class="form-control"
                  >
                </div>

                <div class="form-group flex-1">
                  <label for="from_name">From Sender Name</label>
                  <input
                    id="from_name"
                    v-model="form.from_name"
                    type="text"
                    placeholder="e.g. Bangladesh Commercial Store"
                    required
                    class="form-control"
                  >
                </div>
              </div>

              <div class="form-group toggle-group">
                <label class="switch-label">
                  <input v-model="form.is_active" type="checkbox" class="switch-checkbox">
                  <span class="switch-text">
                    <strong>Activate Dynamic Mailer</strong>
                    <small>Route all platform transactional emails through this database-configured SMTP.</small>
                  </span>
                </label>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                  💾 Save SMTP Settings
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Pre-Flight Test Card -->
        <div class="card test-card">
          <div class="card-header">
            <h3 class="card-title">Pre-Flight SMTP Handshake</h3>
          </div>
          <div class="card-body">
            <p class="test-desc">
              Test connection and verify TLS handshake by sending a live transactional email to your mailbox before saving.
            </p>

            <div class="test-form">
              <label for="test_email">Test Recipient Email</label>
              <div class="input-with-button">
                <input
                  id="test_email"
                  v-model="testEmail"
                  type="email"
                  placeholder="you@company.com"
                  class="form-control"
                >
                <button
                  type="button"
                  class="btn btn-secondary"
                  :disabled="isTesting || !testEmail"
                  @click="sendTestEmail"
                >
                  <span v-if="isTesting">Connecting...</span>
                  <span v-else>🚀 Send Test</span>
                </button>
              </div>
            </div>

            <!-- Test Result Banner -->
            <div v-if="testResult" :class="['test-alert', testResult.success ? 'alert-success' : 'alert-danger']">
              <strong>{{ testResult.success ? '✅ SMTP Connection Verified' : '❌ Connection Failed' }}</strong>
              <p>{{ testResult.message }}</p>
              <span v-if="testResult.latency_ms" class="latency-tag">
                Latency: {{ testResult.latency_ms }}ms
              </span>
            </div>

            <div class="tips-box">
              <h4>💡 Best Practice Tips</h4>
              <ul>
                <li>Use Port <code>587</code> with TLS for SendGrid, Mailgun, and Amazon SES.</li>
                <li>Ensure your domain has SPF and DKIM records configured to maximize inbox deliverability.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.settings-page {
  padding: $space-6;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: $space-6;

  .page-title {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }

  .page-subtitle {
    font-size: $fs-sm;
    color: $color-neutral-500;
    margin-top: 2px;
  }
}

.status-summary {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: $space-1;

  .status-badge {
    padding: $space-1 $space-3;
    border-radius: $radius-full;
    font-size: $fs-xs;
    font-weight: $fw-semibold;

    &.active {
      background: hsl(142, 71%, 90%);
      color: hsl(142, 71%, 25%);
    }

    &.inactive {
      background: $color-neutral-200;
      color: $color-neutral-600;
    }
  }

  .last-tested {
    font-size: $fs-2xs;
    color: $color-neutral-400;
  }
}

.settings-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: $space-6;

  @media (max-width: $bp-lg) {
    grid-template-columns: 1fr;
  }
}

.card {
  background: $color-neutral-0;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-lg;
  box-shadow: $shadow-sm;

  .card-header {
    padding: $space-4 $space-5;
    border-bottom: 1px solid $color-neutral-200;
    background: $color-neutral-50;

    .card-title {
      font-size: $fs-base;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 0;
    }
  }

  .card-body {
    padding: $space-5;
  }
}

.form-row {
  display: flex;
  gap: $space-4;
  margin-bottom: $space-4;

  @media (max-width: $bp-md) {
    flex-direction: column;
  }

  .flex-1 { flex: 1; }
  .flex-2 { flex: 2; }
}

.form-group {
  margin-bottom: $space-4;

  label {
    display: block;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    color: $color-neutral-700;
    margin-bottom: $space-1;
  }

  .form-control {
    width: 100%;
    padding: $space-2 $space-3;
    border: 1px solid $color-neutral-300;
    border-radius: $radius-md;
    font-size: $fs-sm;

    &:focus {
      outline: none;
      border-color: $color-primary-500;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }
  }

  .error-text {
    color: $color-danger;
    font-size: $fs-xs;
    margin-top: 2px;
  }
}

.divider {
  height: 1px;
  background: $color-neutral-200;
  margin: $space-5 0;
}

.toggle-group {
  background: $color-neutral-50;
  padding: $space-4;
  border-radius: $radius-md;
  border: 1px solid $color-neutral-200;

  .switch-label {
    display: flex;
    align-items: flex-start;
    gap: $space-3;
    cursor: pointer;

    .switch-checkbox {
      margin-top: 3px;
      width: 18px;
      height: 18px;
      accent-color: $color-primary-500;
    }

    .switch-text {
      display: flex;
      flex-direction: column;

      strong {
        font-size: $fs-sm;
        color: $color-neutral-900;
      }

      small {
        font-size: $fs-xs;
        color: $color-neutral-500;
      }
    }
  }
}

.form-actions {
  margin-top: $space-6;
  display: flex;
  justify-content: flex-end;
}

.test-desc {
  font-size: $fs-sm;
  color: $color-neutral-600;
  line-height: $lh-normal;
  margin-bottom: $space-4;
}

.test-form {
  margin-bottom: $space-4;

  label {
    display: block;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    color: $color-neutral-700;
    margin-bottom: $space-1;
  }

  .input-with-button {
    display: flex;
    gap: $space-2;
  }
}

.test-alert {
  padding: $space-3 $space-4;
  border-radius: $radius-md;
  margin-bottom: $space-4;
  font-size: $fs-sm;

  &.alert-success {
    background: hsl(142, 71%, 92%);
    border: 1px solid hsl(142, 71%, 70%);
    color: hsl(142, 71%, 25%);
  }

  &.alert-danger {
    background: hsl(0, 84%, 92%);
    border: 1px solid hsl(0, 84%, 70%);
    color: hsl(0, 84%, 25%);
  }

  p {
    margin: $space-1 0;
    font-size: $fs-xs;
  }

  .latency-tag {
    font-size: $fs-2xs;
    font-family: $font-mono;
    font-weight: $fw-bold;
  }
}

.tips-box {
  background: $color-secondary-50;
  border: 1px solid $color-secondary-200;
  border-radius: $radius-md;
  padding: $space-4;

  h4 {
    font-size: $fs-xs;
    font-weight: $fw-bold;
    color: $color-secondary-800;
    margin: 0 0 $space-2 0;
  }

  ul {
    margin: 0;
    padding-left: $space-4;
    font-size: $fs-xs;
    color: $color-secondary-700;

    li {
      margin-bottom: $space-1;
    }
  }
}
</style>
