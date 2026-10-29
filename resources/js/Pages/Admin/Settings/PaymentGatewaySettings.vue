<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface WebhookUrls {
    callback_url?: string;
    success_url?: string;
    fail_url?: string;
    cancel_url?: string;
    ipn_url?: string;
}

interface PaymentGatewayItem {
    id: number;
    ulid: string;
    code: string;
    name: string;
    is_active: boolean;
    is_sandbox: boolean;
    surcharge_percentage: number;
    min_order_amount: number | null;
    max_order_amount: number | null;
    sort_order: number;
    instructions: string | null;
    is_configured: boolean;
    masked_credentials: Record<string, string>;
    last_tested_at: string | null;
    last_test_status: string | null;
    last_test_message: string | null;
    webhook_urls: WebhookUrls;
}

const props = defineProps<{
    gateways: PaymentGatewayItem[];
}>();

// Active editing gateway
const expandedCode = ref<string>('bkash');

// Connection test state per gateway
const testingState = ref<Record<string, { loading: boolean; result: null | { success: boolean; message: string; latency_ms?: number } }>>({});

// Forms per gateway for individual saving
const forms = ref<Record<string, ReturnType<typeof useForm>>>({});

props.gateways.forEach((gw) => {
    forms.value[gw.code] = useForm({
        code: gw.code,
        name: gw.name,
        is_active: gw.is_active,
        is_sandbox: gw.is_sandbox,
        surcharge_percentage: gw.surcharge_percentage,
        sort_order: gw.sort_order,
        instructions: gw.instructions || '',
        credentials: { ...gw.masked_credentials },
    });

    testingState.value[gw.code] = { loading: false, result: null };
});

function toggleExpand(code: string) {
    expandedCode.value = expandedCode.value === code ? '' : code;
}

function saveGateway(code: string) {
    const form = forms.value[code];
    if (!form) return;

    form.post('/admin/settings/payment-gateways', {
        preserveScroll: true,
    });
}

async function testConnection(code: string) {
    const form = forms.value[code];
    const state = testingState.value[code];
    if (!form || !state) return;

    state.loading = true;
    state.result = null;

    try {
        const response = await fetch('/admin/settings/payment-gateways/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                code: code,
                is_sandbox: form.is_sandbox,
                credentials: form.credentials,
            }),
        });

        const data = await response.json();
        state.result = {
            success: data.success,
            message: data.message,
            latency_ms: data.latency_ms,
        };
    } catch (e: unknown) {
        state.result = {
            success: false,
            message: 'Network error communicating with server test endpoint.',
        };
    } finally {
        state.loading = false;
    }
}

const copyTooltip = ref<string | null>(null);

function copyToClipboard(url: string | undefined, key: string) {
    if (!url) return;
    navigator.clipboard.writeText(url);
    copyTooltip.value = key;
    setTimeout(() => {
        copyTooltip.value = null;
    }, 2000);
}
</script>

<template>
    <AdminLayout>
        <Head title="Payment Gateways & Advance COD Settings" />

        <div class="settings-container">
            <!-- Header -->
            <div class="header-card">
                <div class="header-content">
                    <div class="icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <line x1="2" y1="10" x2="22" y2="10" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="title">Payment Gateways & Advance COD Settings</h1>
                        <p class="subtitle">
                            Configure Bangladesh Mobile Financial Services (bKash, Nagad), SSLCommerz, and COD advance delivery fees.
                            All credentials are encrypted at rest with AES-256.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Gateway List -->
            <div class="gateways-list">
                <div
                    v-for="gw in props.gateways"
                    :key="gw.code"
                    class="gateway-card"
                    :class="{ active: gw.is_active, expanded: expandedCode === gw.code }"
                >
                    <!-- Card Top Header -->
                    <div class="card-summary" @click="toggleExpand(gw.code)">
                        <div class="summary-left">
                            <span class="provider-badge" :class="gw.code">{{ gw.code.toUpperCase() }}</span>
                            <div>
                                <h2 class="gw-title">{{ gw.name }}</h2>
                                <span class="gw-code">ID: {{ gw.code }}</span>
                            </div>
                        </div>

                        <div class="summary-right">
                            <span v-if="gw.is_active" class="status-pill active">
                                <span class="dot"></span> Active
                            </span>
                            <span v-else class="status-pill inactive">
                                <span class="dot"></span> Inactive
                            </span>

                            <span v-if="gw.code !== 'cod' && gw.code !== 'bank_transfer'" class="env-pill" :class="gw.is_sandbox ? 'sandbox' : 'live'">
                                {{ gw.is_sandbox ? 'Sandbox (Test)' : 'Production (Live)' }}
                            </span>

                            <span v-if="gw.last_test_status === 'successful'" class="test-pill pass" title="Pre-flight handshake verified">
                                ✓ Verified {{ gw.last_tested_at }}
                            </span>
                            <span v-else-if="gw.last_test_status === 'failed'" class="test-pill fail" title="Connection handshake failed">
                                ✕ Error {{ gw.last_tested_at }}
                            </span>

                            <button type="button" class="expand-btn">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    :class="{ rotated: expandedCode === gw.code }"
                                >
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Expandable Drawer Form -->
                    <div v-if="expandedCode === gw.code" class="card-drawer">
                        <form @submit.prevent="saveGateway(gw.code)">
                            <div class="form-grid">
                                <!-- Global Gateway Options -->
                                <div class="form-group">
                                    <label class="form-label">Display Name at Checkout</label>
                                    <input
                                        v-model="forms[gw.code].name"
                                        type="text"
                                        class="form-control"
                                        required
                                    />
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Sort Priority</label>
                                    <input
                                        v-model="forms[gw.code].sort_order"
                                        type="number"
                                        min="0"
                                        class="form-control"
                                    />
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Customer Surcharge Fee (%)</label>
                                    <input
                                        v-model="forms[gw.code].surcharge_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="form-control"
                                        placeholder="0.00"
                                    />
                                    <span class="hint">E.g., 1.50% gateway fee passed to buyer</span>
                                </div>

                                <div class="form-group toggles-row">
                                    <label class="toggle-control">
                                        <input v-model="forms[gw.code].is_active" type="checkbox" />
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label">Active on Checkout</span>
                                    </label>

                                    <label v-if="gw.code !== 'cod' && gw.code !== 'bank_transfer'" class="toggle-control">
                                        <input v-model="forms[gw.code].is_sandbox" type="checkbox" />
                                        <span class="toggle-slider sandbox"></span>
                                        <span class="toggle-label">Sandbox Mode</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Gateway Specific Credentials -->
                            <div v-if="gw.code === 'bkash'" class="credentials-block">
                                <h3 class="sec-title">bKash Tokenized API Credentials</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">App Key</label>
                                        <input
                                            v-model="forms[gw.code].credentials.app_key"
                                            type="text"
                                            class="form-control code-font"
                                            placeholder="Enter bKash App Key"
                                        />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">App Secret</label>
                                        <input
                                            v-model="forms[gw.code].credentials.app_secret"
                                            type="password"
                                            class="form-control code-font"
                                            placeholder="••••••••••••"
                                        />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Merchant Username</label>
                                        <input
                                            v-model="forms[gw.code].credentials.username"
                                            type="text"
                                            class="form-control"
                                            placeholder="Enter merchant username"
                                        />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Merchant Password</label>
                                        <input
                                            v-model="forms[gw.code].credentials.password"
                                            type="password"
                                            class="form-control"
                                            placeholder="••••••••••••"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="gw.code === 'nagad'" class="credentials-block">
                                <h3 class="sec-title">Nagad Digital Payment Credentials & RSA Keys</h3>
                                <div class="form-group">
                                    <label class="form-label">Merchant ID</label>
                                    <input
                                        v-model="forms[gw.code].credentials.merchant_id"
                                        type="text"
                                        class="form-control code-font"
                                        placeholder="E.g. 683002007104225"
                                    />
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Merchant RSA Private Key (PEM)</label>
                                        <textarea
                                            v-model="forms[gw.code].credentials.merchant_private_key"
                                            rows="4"
                                            class="form-control code-font"
                                            placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                                        ></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Nagad PG RSA Public Key (PEM)</label>
                                        <textarea
                                            v-model="forms[gw.code].credentials.pg_public_key"
                                            rows="4"
                                            class="form-control code-font"
                                            placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="gw.code === 'sslcommerz'" class="credentials-block">
                                <h3 class="sec-title">SSLCommerz Hosted Checkout Credentials</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Store ID</label>
                                        <input
                                            v-model="forms[gw.code].credentials.store_id"
                                            type="text"
                                            class="form-control code-font"
                                            placeholder="E.g. yourstoreid"
                                        />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Store Password</label>
                                        <input
                                            v-model="forms[gw.code].credentials.store_passwd"
                                            type="password"
                                            class="form-control code-font"
                                            placeholder="••••••••••••"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="gw.code === 'cod' || gw.code === 'bank_transfer'" class="credentials-block">
                                <h3 class="sec-title">Customer Instructions</h3>
                                <div class="form-group">
                                    <label class="form-label">Guidelines displayed upon selection</label>
                                    <textarea
                                        v-model="forms[gw.code].instructions"
                                        rows="3"
                                        class="form-control"
                                        placeholder="Enter customer payment instructions, bank account details, or advance delivery terms."
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Webhook & IPN Information -->
                            <div v-if="Object.keys(gw.webhook_urls).length > 0" class="webhook-block">
                                <h3 class="sec-title">Webhook / IPN Endpoints (Provide to Payment Provider)</h3>
                                <div v-for="(url, key) in gw.webhook_urls" :key="key" class="url-row">
                                    <span class="url-label">{{ key.replace('_', ' ').toUpperCase() }}:</span>
                                    <code class="url-text">{{ url }}</code>
                                    <button
                                        type="button"
                                        class="copy-btn"
                                        @click="copyToClipboard(url, `${gw.code}_${key}`)"
                                    >
                                        {{ copyTooltip === `${gw.code}_${key}` ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Test Output Alert -->
                            <div
                                v-if="testingState[gw.code]?.result"
                                class="test-result-box"
                                :class="testingState[gw.code].result?.success ? 'success' : 'error'"
                            >
                                <span class="result-icon">{{ testingState[gw.code].result?.success ? '✓' : '✕' }}</span>
                                <div class="result-body">
                                    <strong>{{ testingState[gw.code].result?.success ? 'Connection Verified' : 'Handshake Failed' }}:</strong>
                                    {{ testingState[gw.code].result?.message }}
                                    <span v-if="testingState[gw.code].result?.latency_ms" class="latency">
                                        ({{ testingState[gw.code].result?.latency_ms }}ms)
                                    </span>
                                </div>
                            </div>

                            <!-- Card Footer Action Buttons -->
                            <div class="card-actions">
                                <button
                                    type="button"
                                    class="btn-test"
                                    :disabled="testingState[gw.code]?.loading"
                                    @click="testConnection(gw.code)"
                                >
                                    <span v-if="testingState[gw.code]?.loading" class="spinner"></span>
                                    <span v-else>⚡ Test API Connection</span>
                                </button>

                                <button
                                    type="submit"
                                    class="btn-save"
                                    :disabled="forms[gw.code].processing"
                                >
                                    {{ forms[gw.code].processing ? 'Saving...' : 'Save Settings' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style lang="scss" scoped>
.settings-container {
    max-width: 1080px;
    margin: 0 auto;
    padding: 24px;
}

.header-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;

    .header-content {
        display: flex;
        align-items: center;
        gap: 16px;

        .icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;

            svg {
                width: 24px;
                height: 24px;
            }
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-top: 4px;
        }
    }
}

.gateways-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.gateway-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s ease;

    &.active {
        border-color: #93c5fd;
    }

    &.expanded {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
}

.card-summary {
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    background: #ffffff;
    user-select: none;

    &:hover {
        background: #f9fafb;
    }
}

.summary-left {
    display: flex;
    align-items: center;
    gap: 16px;

    .provider-badge {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.5px;
        padding: 4px 8px;
        border-radius: 6px;
        background: #f3f4f6;
        color: #374151;

        &.bkash {
            background: #fdf2f8;
            color: #db2777;
        }

        &.nagad {
            background: #fff7ed;
            color: #ea580c;
        }

        &.sslcommerz {
            background: #eff6ff;
            color: #1d4ed8;
        }

        &.cod {
            background: #ecfdf5;
            color: #059669;
        }

        &.bank_transfer {
            background: #f5f3ff;
            color: #7c3aed;
        }
    }

    .gw-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }

    .gw-code {
        font-size: 12px;
        color: #9ca3af;
    }
}

.summary-right {
    display: flex;
    align-items: center;
    gap: 12px;

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;

        .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        &.active {
            background: #ecfdf5;
            color: #059669;

            .dot {
                background: #059669;
            }
        }

        &.inactive {
            background: #f3f4f6;
            color: #6b7280;

            .dot {
                background: #9ca3af;
            }
        }
    }

    .env-pill {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;

        &.sandbox {
            background: #fef3c7;
            color: #d97706;
        }

        &.live {
            background: #dbeafe;
            color: #1e40af;
        }
    }

    .test-pill {
        font-size: 12px;
        font-weight: 500;
        padding: 3px 8px;
        border-radius: 4px;

        &.pass {
            background: #ecfdf5;
            color: #059669;
        }

        &.fail {
            background: #fef2f2;
            color: #dc2626;
        }
    }

    .expand-btn {
        background: transparent;
        border: none;
        color: #6b7280;
        display: flex;
        align-items: center;
        padding: 4px;
        cursor: pointer;

        svg {
            width: 20px;
            height: 20px;
            transition: transform 0.2s ease;

            &.rotated {
                transform: rotate(180deg);
            }
        }
    }
}

.card-drawer {
    border-top: 1px solid #f3f4f6;
    padding: 24px;
    background: #fafafa;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;

    .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
    }

    .form-control {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        background: #ffffff;
        outline: none;
        transition: border-color 0.15s ease;

        &:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        &.code-font {
            font-family: monospace;
            font-size: 13px;
        }
    }

    .hint {
        font-size: 11px;
        color: #6b7280;
    }
}

.toggles-row {
    flex-direction: row !important;
    align-items: center;
    gap: 24px !important;
    padding-top: 24px;
}

.toggle-control {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;

    input {
        display: none;

        &:checked + .toggle-slider {
            background: #2563eb;

            &::before {
                transform: translateX(14px);
            }

            &.sandbox {
                background: #d97706;
            }
        }
    }

    .toggle-slider {
        position: relative;
        width: 32px;
        height: 18px;
        background: #d1d5db;
        border-radius: 9999px;
        transition: background 0.2s ease;

        &::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 14px;
            height: 14px;
            background: #ffffff;
            border-radius: 50%;
            transition: transform 0.2s ease;
        }
    }

    .toggle-label {
        font-size: 13px;
        font-weight: 500;
        color: #374151;
    }
}

.credentials-block {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px dashed #e5e7eb;

    .sec-title {
        font-size: 14px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 12px;
    }
}

.webhook-block {
    margin-top: 20px;
    padding: 14px;
    background: #f3f4f6;
    border-radius: 8px;

    .sec-title {
        font-size: 13px;
        font-weight: 700;
        color: #374151;
        margin: 0 0 10px 0;
    }

    .url-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;

        &:last-child {
            margin-bottom: 0;
        }

        .url-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            min-width: 90px;
        }

        .url-text {
            flex: 1;
            font-size: 12px;
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            overflow-x: auto;
            white-space: nowrap;
        }

        .copy-btn {
            padding: 4px 10px;
            font-size: 12px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;

            &:hover {
                background: #f9fafb;
                border-color: #9ca3af;
            }
        }
    }
}

.test-result-box {
    margin-top: 16px;
    padding: 12px 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;

    &.success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    &.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .result-icon {
        font-size: 16px;
        font-weight: 800;
    }

    .latency {
        color: #6b7280;
        font-size: 11px;
    }
}

.card-actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;

    .btn-test {
        padding: 8px 16px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;

        &:hover:not(:disabled) {
            background: #f3f4f6;
        }

        &:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    }

    .btn-save {
        padding: 8px 20px;
        background: #2563eb;
        border: 1px solid #2563eb;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #ffffff;
        cursor: pointer;

        &:hover:not(:disabled) {
            background: #1d4ed8;
        }

        &:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    }
}

.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid #9ca3af;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
