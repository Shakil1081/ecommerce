<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface SmsGatewayItem {
  id: number;
  ulid: string;
  code: string;
  name: string;
  is_active: boolean;
  is_sandbox: boolean;
  sender_id: string | null;
  balance_bdt: string | null;
  balance_updated_at: string | null;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_message: string | null;
  is_configured: boolean;
  masked_credentials: Record<string, string>;
}

const props = defineProps<{
  gateways: SmsGatewayItem[];
}>();

const expandedCode = ref<string>(props.gateways[0]?.code ?? 'greenweb');

const forms = ref<Record<string, ReturnType<typeof useForm>>>({});
const testingState = ref<Record<string, { loading: boolean; result: null | { success: boolean; message: string; latency_ms?: number } }>>({});
const balanceState = ref<Record<string, { loading: boolean; result: null | { success: boolean; balance?: number; currency?: string; message?: string } }>>({});
const testPhones = ref<Record<string, string>>({});

props.gateways.forEach((g) => {
  forms.value[g.code] = useForm({
    is_active: g.is_active,
    is_sandbox: g.is_sandbox,
    sender_id: g.sender_id || '',
    credentials: { ...g.masked_credentials },
  });

  testingState.value[g.code] = { loading: false, result: null };
  balanceState.value[g.code] = { loading: false, result: null };
  testPhones.value[g.code] = '01700000000';
});

const saveGateway = (gateway: SmsGatewayItem) => {
  forms.value[gateway.code].post(`/admin/communication/sms-settings/${gateway.id}`, {
    preserveScroll: true,
  });
};

const testGateway = async (gateway: SmsGatewayItem) => {
  const state = testingState.value[gateway.code];
  state.loading = true;
  state.result = null;

  try {
    const res = await fetch('/admin/communication/sms-settings/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        code: gateway.code,
        phone: testPhones.value[gateway.code] || '01700000000',
      }),
    });

    const data = await res.json();
    state.result = {
      success: data.success,
      message: data.message,
      latency_ms: data.latency_ms,
    };
  } catch (err: any) {
    state.result = {
      success: false,
      message: err.message || 'Connection failed',
    };
  } finally {
    state.loading = false;
  }
};

const checkBalance = async (gateway: SmsGatewayItem) => {
  const state = balanceState.value[gateway.code];
  state.loading = true;
  state.result = null;

  try {
    const res = await fetch('/admin/communication/sms-settings/balance', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ code: gateway.code }),
    });

    const data = await res.json();
    state.result = {
      success: data.success,
      balance: data.balance,
      currency: data.currency,
      message: data.message,
    };
    if (data.success && data.balance !== undefined) {
      gateway.balance_bdt = String(data.balance);
    }
  } catch (err: any) {
    state.result = {
      success: false,
      message: err.message || 'Balance check failed',
    };
  } finally {
    state.loading = false;
  }
};
</script>

<template>
  <AdminLayout title="SMS Gateway Engine & OTP Settings">
    <Head title="SMS Gateway Engine & OTP Settings" />

    <div class="settings-page">
      <div class="page-header">
        <div>
          <h1 class="page-title">SMS Gateway Engine & OTP Settings</h1>
          <p class="page-subtitle">
            Manage Bangladesh SMS aggregators (Greenweb, Reve Systems) and Global Twilio with pre-flight connection tests and balance inquiry.
          </p>
        </div>
      </div>

      <div class="gateways-list">
        <div
          v-for="gateway in gateways"
          :key="gateway.code"
          :class="['gateway-card card', { 'is-expanded': expandedCode === gateway.code }]"
        >
          <!-- Gateway Header Summary -->
          <div class="card-header" @click="expandedCode = expandedCode === gateway.code ? '' : gateway.code">
            <div class="header-left">
              <span class="gateway-icon">📱</span>
              <div>
                <h3 class="gateway-name">{{ gateway.name }}</h3>
                <span class="gateway-code font-mono text-muted">{{ gateway.code }}</span>
              </div>
            </div>

            <div class="header-right">
              <span v-if="gateway.balance_bdt !== null" class="balance-tag">
                Balance: ৳{{ Number(gateway.balance_bdt).toFixed(2) }}
              </span>
              <span :class="['status-pill', gateway.is_active ? 'active' : 'inactive']">
                {{ gateway.is_active ? '● Active' : '○ Inactive' }}
              </span>
              <span class="chevron">{{ expandedCode === gateway.code ? '▲' : '▼' }}</span>
            </div>
          </div>

          <!-- Gateway Configuration Body -->
          <div v-show="expandedCode === gateway.code" class="card-body">
            <form @submit.prevent="saveGateway(gateway)">
              <div class="form-grid">
                <!-- Left: Configuration Form -->
                <div class="config-col">
                  <div class="form-row">
                    <div class="form-group flex-1">
                      <label>Sender ID / Masking Header</label>
                      <input
                        v-model="forms[gateway.code].sender_id"
                        type="text"
                        placeholder="e.g. 8809612XXXXXX or ECOMSHOP"
                        class="form-control"
                      >
                    </div>

                    <div class="form-group flex-1">
                      <label>Environment Mode</label>
                      <select v-model="forms[gateway.code].is_sandbox" class="form-control">
                        <option :value="false">Live Production</option>
                        <option :value="true">Sandbox / Test Mode</option>
                      </select>
                    </div>
                  </div>

                  <!-- Dynamic Credential Fields -->
                  <div class="credentials-group">
                    <h4 class="creds-title">API Authentication Credentials</h4>

                    <!-- Greenweb Credentials -->
                    <template v-if="gateway.code === 'greenweb'">
                      <div class="form-group">
                        <label>Greenweb API Token</label>
                        <input
                          v-model="forms[gateway.code].credentials.token"
                          type="password"
                          placeholder="Enter Greenweb API token"
                          class="form-control"
                        >
                      </div>
                    </template>

                    <!-- Reve SMS Credentials -->
                    <template v-else-if="gateway.code === 'reve'">
                      <div class="form-row">
                        <div class="form-group flex-1">
                          <label>API Key</label>
                          <input
                            v-model="forms[gateway.code].credentials.api_key"
                            type="text"
                            placeholder="Reve API Key"
                            class="form-control"
                          >
                        </div>
                        <div class="form-group flex-1">
                          <label>Secret Key</label>
                          <input
                            v-model="forms[gateway.code].credentials.secret_key"
                            type="password"
                            placeholder="Reve Secret Key"
                            class="form-control"
                          >
                        </div>
                      </div>
                    </template>

                    <!-- Twilio SMS Credentials -->
                    <template v-else-if="gateway.code === 'twilio'">
                      <div class="form-row">
                        <div class="form-group flex-1">
                          <label>Account SID</label>
                          <input
                            v-model="forms[gateway.code].credentials.account_sid"
                            type="text"
                            placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                            class="form-control"
                          >
                        </div>
                        <div class="form-group flex-1">
                          <label>Auth Token</label>
                          <input
                            v-model="forms[gateway.code].credentials.auth_token"
                            type="password"
                            placeholder="Enter Auth Token"
                            class="form-control"
                          >
                        </div>
                      </div>
                      <div class="form-group">
                        <label>From Phone Number</label>
                        <input
                          v-model="forms[gateway.code].credentials.from_number"
                          type="text"
                          placeholder="+1xxxxxxxxxx"
                          class="form-control"
                        >
                      </div>
                    </template>
                  </div>

                  <div class="form-group toggle-group">
                    <label class="switch-label">
                      <input v-model="forms[gateway.code].is_active" type="checkbox" class="switch-checkbox">
                      <span class="switch-text">
                        <strong>Active Gateway for OTP & SMS Alerts</strong>
                        <small>Deliver order confirmation SMS and 4-digit OTP verification through this driver.</small>
                      </span>
                    </label>
                  </div>

                  <div class="form-actions">
                    <button type="submit" class="btn btn-primary" :disabled="forms[gateway.code].processing">
                      💾 Save Gateway Settings
                    </button>
                  </div>
                </div>

                <!-- Right: Pre-Flight Testing & Balance Query -->
                <div class="tools-col">
                  <!-- Balance Query Box -->
                  <div class="tool-card">
                    <div class="tool-header">
                      <h4>Account Balance</h4>
                      <button
                        type="button"
                        class="btn btn-sm btn-secondary"
                        :disabled="balanceState[gateway.code].loading"
                        @click="checkBalance(gateway)"
                      >
                        <span v-if="balanceState[gateway.code].loading">Checking...</span>
                        <span v-else>🔄 Check Balance</span>
                      </button>
                    </div>

                    <div v-if="gateway.balance_bdt !== null" class="balance-display">
                      <span class="balance-amount">৳{{ Number(gateway.balance_bdt).toFixed(2) }}</span>
                      <small v-if="gateway.balance_updated_at">Updated: {{ gateway.balance_updated_at }}</small>
                    </div>
                    <div v-else class="balance-placeholder">
                      <span>Click 'Check Balance' to query remaining credits.</span>
                    </div>

                    <div v-if="balanceState[gateway.code].result && !balanceState[gateway.code].result?.success" class="tool-alert alert-danger">
                      {{ balanceState[gateway.code].result?.message }}
                    </div>
                  </div>

                  <!-- Connection Test Box -->
                  <div class="tool-card">
                    <h4>Pre-Flight Connection Handshake</h4>
                    <p class="tool-desc">Verify API credentials and connectivity to gateway servers.</p>

                    <div class="test-row">
                      <input
                        v-model="testPhones[gateway.code]"
                        type="tel"
                        placeholder="017XXXXXXXX"
                        class="form-control"
                      >
                      <button
                        type="button"
                        class="btn btn-secondary"
                        :disabled="testingState[gateway.code].loading"
                        @click="testGateway(gateway)"
                      >
                        <span v-if="testingState[gateway.code].loading">Testing...</span>
                        <span v-else>⚡ Test</span>
                      </button>
                    </div>

                    <div
                      v-if="testingState[gateway.code].result"
                      :class="['tool-alert', testingState[gateway.code].result?.success ? 'alert-success' : 'alert-danger']"
                    >
                      <strong>{{ testingState[gateway.code].result?.success ? '✅ Handshake Succeeded' : '❌ Handshake Failed' }}</strong>
                      <p>{{ testingState[gateway.code].result?.message }}</p>
                      <small v-if="testingState[gateway.code].result?.latency_ms">
                        Latency: {{ testingState[gateway.code].result?.latency_ms }}ms
                      </small>
                    </div>
                  </div>
                </div>
              </div>
            </form>
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

.gateways-list {
  display: flex;
  flex-direction: column;
  gap: $space-4;
}

.gateway-card {
  background: $color-neutral-0;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-lg;
  box-shadow: $shadow-sm;
  overflow: hidden;

  .card-header {
    padding: $space-4 $space-6;
    background: $color-neutral-50;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background $transition-fast;

    &:hover {
      background: $color-neutral-100;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: $space-3;

      .gateway-icon {
        font-size: 1.5rem;
      }

      .gateway-name {
        font-size: $fs-base;
        font-weight: $fw-bold;
        color: $color-neutral-900;
        margin: 0;
      }

      .gateway-code {
        font-size: $fs-xs;
      }
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: $space-3;

      .balance-tag {
        font-size: $fs-xs;
        background: hsl(142, 71%, 92%);
        color: hsl(142, 71%, 25%);
        padding: 2px $space-2;
        border-radius: $radius-sm;
        font-weight: $fw-semibold;
      }

      .status-pill {
        padding: $space-1 $space-3;
        border-radius: $radius-full;
        font-size: $fs-xs;
        font-weight: $fw-semibold;

        &.active { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
        &.inactive { background: $color-neutral-200; color: $color-neutral-600; }
      }

      .chevron {
        font-size: $fs-xs;
        color: $color-neutral-400;
      }
    }
  }

  .card-body {
    padding: $space-6;
  }
}

.form-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: $space-6;

  @media (max-width: $bp-lg) {
    grid-template-columns: 1fr;
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

      strong { font-size: $fs-sm; color: $color-neutral-900; }
      small { font-size: $fs-xs; color: $color-neutral-500; }
    }
  }
}

.form-actions {
  margin-top: $space-4;
  display: flex;
  justify-content: flex-end;
}

.tools-col {
  display: flex;
  flex-direction: column;
  gap: $space-4;
}

.tool-card {
  background: $color-neutral-50;
  border: 1px solid $color-neutral-200;
  border-radius: $radius-md;
  padding: $space-4;

  .tool-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $space-2;

    h4 {
      font-size: $fs-sm;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 0;
    }
  }

  .tool-desc {
    font-size: $fs-xs;
    color: $color-neutral-500;
    margin-bottom: $space-3;
  }

  .balance-display {
    display: flex;
    flex-direction: column;

    .balance-amount {
      font-size: $fs-2xl;
      font-weight: $fw-extrabold;
      color: $color-primary-600;
    }

    small {
      font-size: $fs-2xs;
      color: $color-neutral-400;
    }
  }

  .balance-placeholder {
    font-size: $fs-xs;
    color: $color-neutral-500;
    font-style: italic;
  }

  .test-row {
    display: flex;
    gap: $space-2;
    margin-bottom: $space-3;
  }

  .tool-alert {
    padding: $space-2 $space-3;
    border-radius: $radius-md;
    font-size: $fs-xs;

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
      margin: 2px 0;
    }
  }
}
</style>

