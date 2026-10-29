<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface VoiceSettingData {
  id: number;
  ulid: string;
  provider: string;
  is_active: boolean;
  is_sandbox: boolean;
  caller_id: string | null;
  language: string;
  dtmf_enabled: boolean;
  speech_enabled: boolean;
  retry_attempts: number;
  script_template_bn: string | null;
  script_template_en: string | null;
  masked_credentials: Record<string, string>;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_message: string | null;
}

interface CallLogItem {
  id: number;
  ulid: string;
  order_id: number | null;
  order?: {
    id: number;
    order_number: string;
    customer_name: string;
    total_bdt: string;
  } | null;
  phone_number: string;
  call_sid: string | null;
  provider: string;
  status: string;
  verification_result: string | null;
  response_mode: string | null;
  dtmf_digit: string | null;
  duration_seconds: number | null;
  created_at: string;
}

interface PaginatedLogs {
  data: CallLogItem[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
}

const props = defineProps<{
  setting: VoiceSettingData;
  logs: PaginatedLogs;
}>();

const form = useForm({
  provider: props.setting.provider || 'twilio',
  is_active: props.setting.is_active,
  is_sandbox: props.setting.is_sandbox,
  caller_id: props.setting.caller_id || '',
  language: props.setting.language || 'bn-BD',
  dtmf_enabled: props.setting.dtmf_enabled,
  speech_enabled: props.setting.speech_enabled,
  retry_attempts: props.setting.retry_attempts || 2,
  script_template_bn: props.setting.script_template_bn || '',
  script_template_en: props.setting.script_template_en || '',
  credentials: { ...props.setting.masked_credentials },
});

const testPhone = ref('+8801700000000');
const isTesting = ref(false);
const testResult = ref<{ success: boolean; call_sid?: string; message: string } | null>(null);

const saveSettings = () => {
  form.post(`/admin/communication/voice-settings/${props.setting.id}`, {
    preserveScroll: true,
  });
};

const triggerTestCall = async () => {
  if (!testPhone.value) {
    return;
  }

  isTesting.value = true;
  testResult.value = null;

  try {
    const res = await fetch('/admin/communication/voice-settings/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ phone: testPhone.value }),
    });

    const data = await res.json();
    testResult.value = {
      success: data.success,
      call_sid: data.call_sid,
      message: data.message,
    };
  } catch (err: any) {
    testResult.value = {
      success: false,
      message: err.message || 'Test call failed',
    };
  } finally {
    isTesting.value = false;
  }
};
</script>

<template>
  <AdminLayout title="AI Automated Outbound Voice Verification">
    <Head title="AI Voice Verification Settings & Logs" />

    <div class="settings-page">
      <div class="page-header">
        <div>
          <h1 class="page-title">AI Outbound Voice Calling Verification</h1>
          <p class="page-subtitle">
            Automated AI agent calls COD customers within 60 seconds with bilingual prompts to confirm (DTMF 1) or cancel (DTMF 2) orders.
          </p>
        </div>
        <div class="status-summary">
          <span :class="['status-badge', setting.is_active ? 'active' : 'inactive']">
            {{ setting.is_active ? '● Voice Agent Active' : '○ Voice Agent Inactive' }}
          </span>
          <span v-if="setting.last_tested_at" class="last-tested">
            Last tested: {{ setting.last_tested_at }}
          </span>
        </div>
      </div>

      <div class="settings-grid">
        <!-- Voice Settings Form -->
        <div class="card form-card">
          <div class="card-header">
            <h3 class="card-title">Voice Gateway & Prompt Configuration</h3>
          </div>
          <div class="card-body">
            <form @submit.prevent="saveSettings">
              <div class="form-row">
                <div class="form-group flex-1">
                  <label>Voice Provider</label>
                  <select v-model="form.provider" class="form-control">
                    <option value="twilio">Twilio Voice (TwiML & Programmable Voice)</option>
                    <option value="retell">Retell AI Voice Engine</option>
                    <option value="vapi">Vapi.ai Conversational Voice</option>
                  </select>
                </div>

                <div class="form-group flex-1">
                  <label>Environment Mode</label>
                  <select v-model="form.is_sandbox" class="form-control">
                    <option :value="false">Live Production</option>
                    <option :value="true">Sandbox / Simulated OBD</option>
                  </select>
                </div>

                <div class="form-group flex-1">
                  <label>Caller Phone / Verified Caller ID</label>
                  <input
                    v-model="form.caller_id"
                    type="text"
                    placeholder="+88096XXXXXXXX"
                    class="form-control"
                  >
                </div>
              </div>

              <!-- Provider Credentials -->
              <div class="credentials-group">
                <h4 class="creds-title">Provider Authentication Credentials</h4>
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label>Account SID</label>
                    <input
                      v-model="form.credentials.account_sid"
                      type="text"
                      placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                      class="form-control"
                    >
                  </div>
                  <div class="form-group flex-1">
                    <label>Auth Token</label>
                    <input
                      v-model="form.credentials.auth_token"
                      type="password"
                      placeholder="Enter Auth Token"
                      class="form-control"
                    >
                  </div>
                </div>
                <div class="form-group">
                  <label>Outbound Voice Number (E.164)</label>
                  <input
                    v-model="form.credentials.from_number"
                    type="text"
                    placeholder="+12015550123"
                    class="form-control"
                  >
                </div>
              </div>

              <!-- Interactive Mode Toggles -->
              <div class="form-row">
                <div class="form-group flex-1 toggle-card">
                  <label class="switch-label">
                    <input v-model="form.dtmf_enabled" type="checkbox" class="switch-checkbox">
                    <span class="switch-text">
                      <strong>DTMF Touch-Tone Input</strong>
                      <small>Listen for customer keypress: 1 to confirm, 2 to cancel.</small>
                    </span>
                  </label>
                </div>

                <div class="form-group flex-1 toggle-card">
                  <label class="switch-label">
                    <input v-model="form.speech_enabled" type="checkbox" class="switch-checkbox">
                    <span class="switch-text">
                      <strong>Speech Intent Detection (AI)</strong>
                      <small>Conversational NLP: understand "Yes confirm" or "No cancel".</small>
                    </span>
                  </label>
                </div>
              </div>

              <!-- Bilingual Prompt Templates -->
              <div class="form-group">
                <label>Bangla Voice Script (বাংলা ভয়েস প্রম্পট)</label>
                <textarea
                  v-model="form.script_template_bn"
                  rows="2"
                  class="form-control font-mono"
                  placeholder="আসসালামু আলাইকুম। আপনার অর্ডারের মোট মূল্য {total_amount} টাকা। নিশ্চিত করতে ১ চাপুন, বাতিল করতে ২ চাপুন।"
                />
              </div>

              <div class="form-group">
                <label>English Voice Script (Fallback / English Prompt)</label>
                <textarea
                  v-model="form.script_template_en"
                  rows="2"
                  class="form-control font-mono"
                  placeholder="Hello. Your order total is {total_amount} BDT. Press 1 to confirm, or 2 to cancel."
                />
              </div>

              <div class="form-group toggle-group">
                <label class="switch-label">
                  <input v-model="form.is_active" type="checkbox" class="switch-checkbox">
                  <span class="switch-text">
                    <strong>Enable Automatic Outbound Verification for COD Orders</strong>
                    <small>Dials customers immediately upon Cash on Delivery checkout to eliminate fake orders.</small>
                  </span>
                </label>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                  💾 Save Voice Settings
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Pre-Flight Test Call Box -->
        <div class="card test-card">
          <div class="card-header">
            <h3 class="card-title">Pre-Flight Test Call</h3>
          </div>
          <div class="card-body">
            <p class="test-desc">
              Test outbound call connection by placing a live call to your mobile phone before activating on production.
            </p>

            <div class="test-form">
              <label>Test Recipient Phone</label>
              <div class="input-with-button">
                <input
                  v-model="testPhone"
                  type="tel"
                  placeholder="+88017XXXXXXXX"
                  class="form-control"
                >
                <button
                  type="button"
                  class="btn btn-secondary"
                  :disabled="isTesting || !testPhone"
                  @click="triggerTestCall"
                >
                  <span v-if="isTesting">Dialing...</span>
                  <span v-else>📞 Call Now</span>
                </button>
              </div>
            </div>

            <div v-if="testResult" :class="['test-alert', testResult.success ? 'alert-success' : 'alert-danger']">
              <strong>{{ testResult.success ? '✅ Call Dispatched' : '❌ Call Failed' }}</strong>
              <p>{{ testResult.message }}</p>
              <small v-if="testResult.call_sid" class="font-mono">
                SID: {{ testResult.call_sid }}
              </small>
            </div>

            <div class="webhook-info">
              <h4>Webhook Handshake Endpoint</h4>
              <p>Twilio Voice Webhook URL configured in your Twilio Console:</p>
              <code class="webhook-url">https://yourdomain.com/webhooks/voice/twilio</code>
            </div>
          </div>
        </div>
      </div>

      <!-- Call Logs Section -->
      <div class="card logs-card">
        <div class="card-header">
          <h3 class="card-title">Recent Voice Call Verification Logs ({{ logs.total }})</h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Order Reference</th>
                  <th>Customer Phone</th>
                  <th>Status</th>
                  <th>Verification Result</th>
                  <th>DTMF Key</th>
                  <th>Duration</th>
                  <th>Call SID</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="logs.data.length === 0">
                  <td colspan="8" class="text-center text-muted p-6">
                    No verification calls recorded yet.
                  </td>
                </tr>
                <tr v-for="log in logs.data" :key="log.id">
                  <td class="text-muted text-sm">{{ log.created_at }}</td>
                  <td>
                    <Link v-if="log.order" :href="`/admin/orders/${log.order.id}`" class="order-link font-mono">
                      {{ log.order.order_number }}
                    </Link>
                    <span v-else class="text-muted">N/A (Test Call)</span>
                  </td>
                  <td class="font-mono">{{ log.phone_number }}</td>
                  <td>
                    <span :class="['status-pill', `status-${log.status}`]">
                      {{ log.status }}
                    </span>
                  </td>
                  <td>
                    <span
                      v-if="log.verification_result"
                      :class="['result-pill', log.verification_result === 'confirmed' ? 'result-confirmed' : 'result-cancelled']"
                    >
                      {{ log.verification_result }}
                    </span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td class="text-center font-mono">
                    <span v-if="log.dtmf_digit" class="dtmf-badge">Key {{ log.dtmf_digit }}</span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td class="text-muted">{{ log.duration_seconds ? `${log.duration_seconds}s` : '-' }}</td>
                  <td class="font-mono text-xs text-muted">{{ log.call_sid || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="logs.last_page > 1" class="pagination-bar">
            <span class="pagination-info">Page {{ logs.current_page }} of {{ logs.last_page }}</span>
            <div class="pagination-links">
              <Link
                v-if="logs.prev_page_url"
                :href="logs.prev_page_url"
                class="btn btn-sm btn-secondary"
              >
                &larr; Previous
              </Link>
              <Link
                v-if="logs.next_page_url"
                :href="logs.next_page_url"
                class="btn btn-sm btn-secondary"
              >
                Next &rarr;
              </Link>
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

    &.active { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
    &.inactive { background: $color-neutral-200; color: $color-neutral-600; }
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
  margin-bottom: $space-6;

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

    &.p-0 {
      padding: 0;
    }
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
}

.credentials-group {
  background: $color-neutral-50;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-md;
  padding: $space-4;
  margin-bottom: $space-4;

  .creds-title {
    font-size: $fs-xs;
    font-weight: $fw-bold;
    text-transform: uppercase;
    color: $color-neutral-600;
    margin: 0 0 $space-3 0;
  }
}

.toggle-card {
  background: $color-neutral-50;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-md;
  padding: $space-3;
}

.toggle-group {
  background: $color-neutral-50;
  padding: $space-4;
  border-radius: $radius-md;
  border: 1px solid $color-neutral-200;
}

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

    strong { font-size: $fs-sm; color: $color-neutral-900; }
    small { font-size: $fs-xs; color: $color-neutral-500; }
  }
}

.form-actions {
  margin-top: $space-4;
  display: flex;
  justify-content: flex-end;
}

.test-desc {
  font-size: $fs-sm;
  color: $color-neutral-600;
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

  p { margin: $space-1 0; font-size: $fs-xs; }
}

.webhook-info {
  background: $color-neutral-50;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-md;
  padding: $space-3;

  h4 { font-size: $fs-xs; font-weight: $fw-bold; color: $color-neutral-800; margin: 0 0 $space-1 0; }
  p { font-size: $fs-xs; color: $color-neutral-500; margin: 0 0 $space-2 0; }
  .webhook-url { font-size: $fs-2xs; background: $color-neutral-200; padding: 2px $space-2; border-radius: $radius-sm; }
}

.logs-card {
  .table {
    width: 100%;
    border-collapse: collapse;

    th {
      background: $color-neutral-50;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: $color-neutral-600;
      text-transform: uppercase;
      padding: $space-3 $space-4;
      border-bottom: 1px solid $color-neutral-200;
    }

    td {
      padding: $space-3 $space-4;
      font-size: $fs-sm;
      border-bottom: 1px solid $color-neutral-100;
      vertical-align: middle;
    }
  }

  .order-link {
    color: $color-primary-600;
    font-weight: $fw-semibold;
    text-decoration: none;

    &:hover { text-decoration: underline; }
  }

  .status-pill {
    font-size: $fs-2xs;
    padding: 2px $space-2;
    border-radius: $radius-full;
    font-weight: $fw-semibold;

    &.status-completed { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
    &.status-initiated, &.status-ringing { background: hsl(214, 95%, 90%); color: hsl(214, 95%, 25%); }
    &.status-failed, &.status-busy { background: hsl(0, 84%, 90%); color: hsl(0, 84%, 25%); }
  }

  .result-pill {
    font-size: $fs-2xs;
    padding: 2px $space-2;
    border-radius: $radius-sm;
    font-weight: $fw-bold;

    &.result-confirmed { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
    &.result-cancelled { background: hsl(0, 84%, 90%); color: hsl(0, 84%, 25%); }
  }

  .dtmf-badge {
    background: $color-neutral-100;
    border: 1px solid $color-neutral-300;
    padding: 2px $space-2;
    border-radius: $radius-sm;
    font-size: $fs-2xs;
  }

  .pagination-bar {
    padding: $space-3 $space-4;
    border-top: 1px solid $color-neutral-200;
    display: flex;
    justify-content: space-between;
    align-items: center;

    .pagination-info { font-size: $fs-xs; color: $color-neutral-500; }
    .pagination-links { display: flex; gap: $space-2; }
  }
}
</style>
