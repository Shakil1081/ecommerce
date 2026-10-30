<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

interface AiSettingItem {
  id: number;
  ulid: string;
  provider: string;
  name: string;
  is_active: boolean;
  default_model: string;
  temperature: number;
  max_tokens: number;
  assigned_features: string[];
  sort_order: number;
  masked_credentials?: Record<string, string>;
}

interface AiUsageLogItem {
  id: number;
  provider: string;
  model: string;
  feature: string;
  prompt_tokens: number;
  completion_tokens: number;
  total_tokens: number;
  estimated_cost_usd: string;
  estimated_cost_bdt: string;
  latency_ms: number;
  status: string;
  created_at: string;
  user?: {
    name: string;
  };
}

interface AiStats {
  total_requests: number;
  total_tokens: number;
  total_cost_usd: string;
  total_cost_bdt: string;
  active_providers_count: number;
}

const props = defineProps<{
  settings: AiSettingItem[];
  usageLogs: AiUsageLogItem[];
  stats: AiStats;
}>();

// Edit state for each provider
const forms = ref<Record<string, ReturnType<typeof useForm>>>({});
const testStatuses = ref<Record<string, { loading: boolean; success?: boolean; message?: string; latency?: number }>>({});

props.settings.forEach((s) => {
  forms.value[s.provider] = useForm({
    is_active: s.is_active,
    default_model: s.default_model,
    temperature: s.temperature,
    max_tokens: s.max_tokens,
    assigned_features: [...(s.assigned_features || [])],
    credentials: { ...(s.masked_credentials || {}) },
  });

  testStatuses.value[s.provider] = { loading: false };
});

const availableFeatures = [
  { id: 'catalog_enrichment', label: 'Catalog & SEO Enrichment' },
  { id: 'smart_restocking', label: 'Smart Restocking & Forecasting' },
  { id: 'customer_support', label: 'Customer Dispute & RMA Reply' },
  { id: 'tax_anomaly', label: 'Statutory Tax Anomaly Detection' },
];

const toggleFeature = (provider: string, featureId: string) => {
  const form = forms.value[provider];
  const idx = form.assigned_features.indexOf(featureId);
  if (idx > -1) {
    form.assigned_features.splice(idx, 1);
  } else {
    form.assigned_features.push(featureId);
  }
};

const saveSetting = (setting: AiSettingItem) => {
  const form = forms.value[setting.provider];
  form.post(`/admin/ai-settings/${setting.id}`, {
    preserveScroll: true,
  });
};

const runTestConnection = async (setting: AiSettingItem) => {
  const form = forms.value[setting.provider];
  const testState = testStatuses.value[setting.provider];
  testState.loading = true;
  testState.message = undefined;

  try {
    const res = await axios.post('/admin/ai-settings/test', {
      setting_id: setting.id,
      provider: setting.provider,
      model: form.default_model,
      credentials: form.credentials,
    });

    testState.loading = false;
    testState.success = res.data.success;
    testState.message = res.data.message;
    testState.latency = res.data.latency_ms;
  } catch (err: any) {
    testState.loading = false;
    testState.success = false;
    testState.message = err.response?.data?.message || err.message || 'Connection test failed.';
  }
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<template>
  <AdminLayout>
    <Head title="Decoupled AI Engine Settings" />

    <div class="ai-settings-page">
      <div class="header-section">
        <div>
          <div class="breadcrumb">
            <Link href="/admin">Dashboard</Link> / <span>AI Intelligence</span>
          </div>
          <h1 class="page-title">Decoupled Multi-Provider AI Engine</h1>
          <p class="page-subtitle">Configure Google Gemini, OpenAI, Claude, or Ollama with encrypted credentials and feature assignments.</p>
        </div>
      </div>

      <!-- Metric Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <span class="label">Total Prompts Dispatched</span>
          <span class="value">{{ stats.total_requests.toLocaleString() }}</span>
          <span class="sub-label">E-Commerce AI operations</span>
        </div>
        <div class="stat-card">
          <span class="label">Total Tokens Metered</span>
          <span class="value font-mono">{{ stats.total_tokens.toLocaleString() }}</span>
          <span class="sub-label">Prompt + Completion</span>
        </div>
        <div class="stat-card">
          <span class="label">Total Estimated Cost</span>
          <span class="value text-primary font-mono">৳{{ Number(stats.total_cost_bdt).toFixed(2) }}</span>
          <span class="sub-label">Approx. ${{ Number(stats.total_cost_usd).toFixed(4) }} USD</span>
        </div>
        <div class="stat-card">
          <span class="label">Active Providers</span>
          <span class="value text-success">{{ stats.active_providers_count }}</span>
          <span class="sub-label">Enabled in back-office</span>
        </div>
      </div>

      <!-- Provider Settings Cards -->
      <div class="providers-grid">
        <div v-for="setting in settings" :key="setting.id" class="provider-card">
          <div class="provider-header">
            <div class="provider-info">
              <span class="provider-badge" :class="'provider-' + setting.provider">{{ setting.provider.toUpperCase() }}</span>
              <h2 class="provider-name">{{ setting.name }}</h2>
            </div>
            <label class="toggle-switch">
              <input v-model="forms[setting.provider].is_active" type="checkbox" />
              <span class="slider"></span>
            </label>
          </div>

          <form @submit.prevent="saveSetting(setting)" class="provider-form">
            <!-- Model Input -->
            <div class="form-group">
              <label>Default Model</label>
              <input v-model="forms[setting.provider].default_model" type="text" class="form-input font-mono" />
            </div>

            <!-- Credentials -->
            <div v-if="setting.provider !== 'ollama'" class="form-group">
              <label>API Key (Encrypted at rest)</label>
              <input
                v-model="forms[setting.provider].credentials.api_key"
                type="password"
                placeholder="Enter new API key or leave untouched..."
                class="form-input font-mono"
              />
            </div>

            <!-- Endpoint for Ollama or custom -->
            <div v-if="setting.provider === 'ollama' || setting.provider === 'openai' || setting.provider === 'claude'" class="form-group">
              <label>API Endpoint</label>
              <input v-model="forms[setting.provider].credentials.endpoint" type="text" class="form-input font-mono" />
            </div>

            <!-- Hyperparameters -->
            <div class="form-row">
              <div class="form-group">
                <label>Temperature ({{ forms[setting.provider].temperature }})</label>
                <input
                  v-model.number="forms[setting.provider].temperature"
                  type="range"
                  min="0"
                  max="1.5"
                  step="0.05"
                  class="range-slider"
                />
              </div>
              <div class="form-group">
                <label>Max Output Tokens</label>
                <input v-model.number="forms[setting.provider].max_tokens" type="number" class="form-input font-mono" />
              </div>
            </div>

            <!-- Assigned Features -->
            <div class="form-group">
              <label>Assigned Operational Features</label>
              <div class="features-checklist">
                <label v-for="feat in availableFeatures" :key="feat.id" class="feature-item">
                  <input
                    type="checkbox"
                    :checked="forms[setting.provider].assigned_features.includes(feat.id)"
                    @change="toggleFeature(setting.provider, feat.id)"
                  />
                  <span>{{ feat.label }}</span>
                </label>
              </div>
            </div>

            <!-- Test Connection Status Box -->
            <div v-if="testStatuses[setting.provider].message" class="test-feedback" :class="testStatuses[setting.provider].success ? 'feedback-success' : 'feedback-error'">
              <span>{{ testStatuses[setting.provider].message }}</span>
              <span v-if="testStatuses[setting.provider].latency" class="latency-badge">{{ testStatuses[setting.provider].latency }}ms</span>
            </div>

            <!-- Actions Bar -->
            <div class="card-actions">
              <button
                type="button"
                class="btn btn-secondary"
                :disabled="testStatuses[setting.provider].loading"
                @click="runTestConnection(setting)"
              >
                {{ testStatuses[setting.provider].loading ? 'Testing Handshake...' : 'Test Connection' }}
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="forms[setting.provider].processing"
              >
                Save Settings
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Recent Usage Log Table -->
      <div class="table-section">
        <h2 class="section-title">Recent AI Usage & Metering Logs</h2>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date / Time</th>
                <th>Provider</th>
                <th>Model</th>
                <th>Feature</th>
                <th class="text-right">Prompt Tokens</th>
                <th class="text-right">Completion</th>
                <th class="text-right">Total Tokens</th>
                <th class="text-right">Est. Cost (BDT)</th>
                <th class="text-right">Latency</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="usageLogs.length === 0">
                <td colspan="10" class="empty-state">No AI activity recorded yet.</td>
              </tr>
              <tr v-for="log in usageLogs" :key="log.id">
                <td>{{ formatDate(log.created_at) }}</td>
                <td class="font-semibold">{{ log.provider.toUpperCase() }}</td>
                <td class="font-mono text-xs">{{ log.model }}</td>
                <td><span class="feature-tag">{{ log.feature }}</span></td>
                <td class="text-right font-mono">{{ log.prompt_tokens }}</td>
                <td class="text-right font-mono">{{ log.completion_tokens }}</td>
                <td class="text-right font-mono font-semibold">{{ log.total_tokens }}</td>
                <td class="text-right font-mono font-semibold text-primary">৳{{ Number(log.estimated_cost_bdt).toFixed(3) }}</td>
                <td class="text-right font-mono text-xs">{{ log.latency_ms }}ms</td>
                <td>
                  <span class="status-badge" :class="log.status === 'success' ? 'status-success' : 'status-danger'">
                    {{ log.status.toUpperCase() }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.ai-settings-page {
  padding: 1.5rem;
}

.breadcrumb {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.5rem;

  a {
    color: #3b82f6;
    text-decoration: none;
    &:hover { text-decoration: underline; }
  }
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.page-subtitle {
  color: #64748b;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1.5rem;
}

.stat-card {
  background: #ffffff;
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;

  .label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
  }

  .value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0.35rem 0;
  }

  .sub-label {
    font-size: 0.75rem;
    color: #94a3b8;
  }
}

.providers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.provider-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
}

.provider-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 1rem;
  margin-bottom: 1rem;
}

.provider-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.provider-badge {
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 700;

  &.provider-gemini { background: #eff6ff; color: #2563eb; }
  &.provider-openai { background: #f0fdf4; color: #16a34a; }
  &.provider-claude { background: #fff7ed; color: #ea580c; }
  &.provider-ollama { background: #f8fafc; color: #334155; }
}

.provider-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;

  input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: 0.2s;
    border-radius: 24px;

    &:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: 0.2s;
      border-radius: 50%;
    }
  }

  input:checked + .slider {
    background-color: #10b981;
  }

  input:checked + .slider:before {
    transform: translateX(20px);
  }
}

.provider-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;

  label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #475569;
  }
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;

  &:focus {
    outline: none;
    border-color: #3b82f6;
  }
}

.range-slider {
  width: 100%;
}

.features-checklist {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.5rem;
  background: #f8fafc;
  padding: 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid #e2e8f0;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: #334155;
  cursor: pointer;
}

.test-feedback {
  padding: 0.625rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  display: flex;
  justify-content: space-between;
  align-items: center;

  &.feedback-success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
  }

  &.feedback-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
  }

  .latency-badge {
    background: #ffffff;
    padding: 0.15rem 0.4rem;
    border-radius: 0.25rem;
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 600;
  }
}

.card-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.5rem;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 600;
  font-size: 0.8125rem;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease-in-out;

  &.btn-primary {
    background-color: #0f172a;
    color: #ffffff;
    &:hover:not(:disabled) { background-color: #1e293b; }
  }

  &.btn-secondary {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    &:hover:not(:disabled) { background-color: #e2e8f0; }
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.table-section {
  margin-top: 2rem;
}

.section-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 1rem;
}

.table-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.8125rem;

  th {
    background-color: #f8fafc;
    color: #475569;
    padding: 0.625rem 0.75rem;
    font-weight: 600;
    border-bottom: 1px solid #e2e8f0;
  }

  td {
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
  }

  .text-right { text-align: right; }
  .font-mono { font-family: monospace; }
  .font-semibold { font-weight: 600; }
  .text-primary { color: #2563eb; }
  .text-success { color: #16a34a; }
}

.feature-tag {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  background-color: #f1f5f9;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-family: monospace;
}

.status-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;

  &.status-success {
    background-color: #ecfdf5;
    color: #059669;
  }

  &.status-danger {
    background-color: #fef2f2;
    color: #dc2626;
  }
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #94a3b8;
}
</style>
