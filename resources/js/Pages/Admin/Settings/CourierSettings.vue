<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CourierGatewayItem {
    id: number;
    ulid: string;
    code: string;
    name: string;
    is_active: boolean;
    is_sandbox: boolean;
    base_url: string | null;
    webhook_secret: string | null;
    supported_features: string[];
    sort_order: number;
    last_tested_at: string | null;
    last_test_status: string | null;
    last_test_message: string | null;
    is_configured: boolean;
    credentials: Record<string, string>;
    webhook_url: string;
}

const props = defineProps<{
    couriers: CourierGatewayItem[];
}>();

const expandedCode = ref<string>(props.couriers[0]?.code ?? 'steadfast');

const testingState = ref<Record<string, { loading: boolean; result: null | { success: boolean; message: string; latency_ms?: number } }>>({});

const forms = ref<Record<string, ReturnType<typeof useForm>>>({});

props.couriers.forEach((c) => {
    forms.value[c.code] = useForm({
        name: c.name,
        is_active: c.is_active,
        is_sandbox: c.is_sandbox,
        base_url: c.base_url || '',
        webhook_secret: c.webhook_secret || '',
        sort_order: c.sort_order,
        credentials: { ...c.credentials },
    });

    testingState.value[c.code] = { loading: false, result: null };
});

const copiedKey = ref<string | null>(null);

function copyToClipboard(text: string, key: string) {
    navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => {
        copiedKey.value = null;
    }, 2000);
}

async function testConnection(courier: CourierGatewayItem) {
    const state = testingState.value[courier.code];
    const form = forms.value[courier.code];
    if (!state || !form) return;

    state.loading = true;
    state.result = null;

    try {
        const response = await fetch('/admin/settings/couriers/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                code: courier.code,
                credentials: form.credentials,
                is_sandbox: form.is_sandbox,
                base_url: form.base_url || null,
            }),
        });

        const data = await response.json();
        state.result = {
            success: data.success,
            message: data.message,
            latency_ms: data.latency_ms,
        };
        router.reload({ only: ['couriers'] });
    } catch (e: any) {
        state.result = {
            success: false,
            message: e.message || 'Connection test request failed.',
        };
    } finally {
        state.loading = false;
    }
}

function saveSettings(courier: CourierGatewayItem) {
    const form = forms.value[courier.code];
    if (!form) return;

    form.post(`/admin/settings/couriers/${courier.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="Courier Logistics Settings" />

        <div class="couriers-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Courier Logistics Gateways</h1>
                    <p class="page-subtitle">Configure Bangladesh couriers (Steadfast, Pathao, RedX, Paperfly, Sundarban) with encrypted API credentials and status webhooks.</p>
                </div>
            </div>

            <!-- Provider Selection Grid -->
            <div class="providers-grid">
                <div
                    v-for="courier in couriers"
                    :key="courier.code"
                    :class="['provider-tab', { active: expandedCode === courier.code }]"
                    @click="expandedCode = courier.code"
                >
                    <div class="tab-header">
                        <span class="provider-name">{{ courier.name }}</span>
                        <span :class="['status-dot', courier.is_active ? 'active' : 'inactive']"></span>
                    </div>
                    <div class="tab-meta">
                        <span class="mode-badge" :class="courier.is_sandbox ? 'sandbox' : 'live'">
                            {{ courier.is_sandbox ? 'Sandbox' : 'Production' }}
                        </span>
                        <span v-if="courier.is_configured" class="conf-badge success">Configured</span>
                        <span v-else class="conf-badge warning">Unconfigured</span>
                    </div>
                </div>
            </div>

            <!-- Configuration Card for Selected Courier -->
            <div v-for="courier in couriers" :key="'card-' + courier.code" v-show="expandedCode === courier.code" class="config-card">
                <form @submit.prevent="saveSettings(courier)" class="config-form">
                    <div class="card-header">
                        <div class="header-info">
                            <h2>{{ courier.name }}</h2>
                            <p class="provider-code">Gateway Code: <code>{{ courier.code }}</code></p>
                        </div>
                        <div class="header-actions">
                            <label class="switch-label">
                                <span>Active</span>
                                <input type="checkbox" v-model="forms[courier.code].is_active" />
                            </label>
                            <label class="switch-label">
                                <span>Sandbox Mode</span>
                                <input type="checkbox" v-model="forms[courier.code].is_sandbox" />
                            </label>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Display Name</label>
                            <input type="text" v-model="forms[courier.code].name" class="input-text" />
                        </div>
                        <div class="form-group">
                            <label>API Base URL</label>
                            <input type="text" v-model="forms[courier.code].base_url" placeholder="https://..." class="input-text" />
                        </div>
                    </div>

                    <!-- Dynamic Credentials Fields -->
                    <div class="credentials-box">
                        <h3>API Credentials & Secret Keys</h3>
                        <p class="section-desc">Keys are encrypted at rest with AES-256-CBC. Existing credentials remain intact if masked placeholders (••••) are preserved.</p>

                        <div class="cred-grid">
                            <div v-for="(val, field) in forms[courier.code].credentials" :key="field" class="form-group">
                                <label>{{ String(field).replace(/_/g, ' ').toUpperCase() }}</label>
                                <input
                                    type="text"
                                    v-model="forms[courier.code].credentials[field]"
                                    :placeholder="'Enter ' + String(field)"
                                    class="input-text font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Webhook Settings -->
                    <div class="webhook-box">
                        <h3>Inbound Status Webhook</h3>
                        <div class="form-group">
                            <label>Webhook Listener URL</label>
                            <div class="copy-input-group">
                                <input type="text" :value="courier.webhook_url" readonly class="input-text font-mono" />
                                <button type="button" @click="copyToClipboard(courier.webhook_url, courier.code)" class="btn-copy">
                                    {{ copiedKey === courier.code ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Webhook Secret Token (Optional)</label>
                            <input type="text" v-model="forms[courier.code].webhook_secret" placeholder="Webhook security secret" class="input-text font-mono" />
                        </div>
                    </div>

                    <!-- Diagnostics & Pre-Flight Testing -->
                    <div class="diagnostics-box">
                        <div class="diagnostics-header">
                            <div>
                                <h3>API Connection Health</h3>
                                <p v-if="courier.last_tested_at" class="last-tested">
                                    Last tested: {{ courier.last_tested_at }} —
                                    <span :class="courier.last_test_status === 'success' ? 'text-green' : 'text-red'">
                                        {{ courier.last_test_status }}
                                    </span>
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="testConnection(courier)"
                                :disabled="Boolean(testingState[courier.code]?.loading)"
                                class="btn-test"
                            >
                                <span v-if="testingState[courier.code]?.loading">Pinging API...</span>
                                <span v-else>⚡ Test API Connection</span>
                            </button>
                        </div>

                        <div v-if="testingState[courier.code]?.result" :class="['test-alert', testingState[courier.code]?.result?.success ? 'alert-success' : 'alert-error']">
                            <div class="alert-icon">{{ testingState[courier.code]?.result?.success ? '✓' : '✗' }}</div>
                            <div>
                                <div class="alert-message">{{ testingState[courier.code]?.result?.message }}</div>
                                <div v-if="testingState[courier.code]?.result?.latency_ms" class="alert-latency">
                                    Response latency: {{ testingState[courier.code]?.result?.latency_ms }} ms
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Actions -->
                    <div class="card-footer">
                        <button type="submit" :disabled="forms[courier.code].processing" class="btn-primary">
                            {{ forms[courier.code].processing ? 'Saving...' : 'Save ' + courier.name + ' Settings' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style lang="scss" scoped>
.couriers-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px;
}

.page-header {
    margin-bottom: 24px;

    .page-title {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
    }

    .page-subtitle {
        color: #64748b;
        font-size: 14px;
        margin-top: 4px;
    }
}

.providers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.provider-tab {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
        border-color: #cbd5e1;
    }

    &.active {
        border-color: #2563eb;
        box-shadow: 0 0 0 1px #2563eb;
    }

    .tab-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;

        .provider-name {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;

            &.active {
                background: #10b981;
            }
            &.inactive {
                background: #94a3b8;
            }
        }
    }

    .tab-meta {
        display: flex;
        gap: 6px;

        .mode-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;

            &.sandbox {
                background: #fef3c7;
                color: #d97706;
            }
            &.live {
                background: #dbeafe;
                color: #2563eb;
            }
        }

        .conf-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;

            &.success {
                background: #ecfdf5;
                color: #059669;
            }
            &.warning {
                background: #f8fafc;
                color: #64748b;
            }
        }
    }
}

.config-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;

        h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .provider-code code {
            font-size: 12px;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            color: #475569;
        }

        .header-actions {
            display: flex;
            gap: 16px;
        }

        .switch-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        padding: 20px 24px;
    }

    .credentials-box, .webhook-box, .diagnostics-box {
        padding: 20px 24px;
        border-top: 1px solid #f1f5f9;

        h3 {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .section-desc {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }
    }

    .cred-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
    }

    .copy-input-group {
        display: flex;
        gap: 8px;

        .btn-copy {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;

            &:hover {
                background: #e2e8f0;
            }
        }
    }

    .diagnostics-header {
        display: flex;
        justify-content: space-between;
        align-items: center;

        .last-tested {
            font-size: 13px;
            color: #64748b;
        }
    }

    .btn-test {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        padding: 8px 14px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;

        &:hover {
            background: #f1f5f9;
        }
        &:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    }

    .test-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 14px;
        padding: 12px;
        border-radius: 8px;

        &.alert-success {
            background: #ecfdf5;
            color: #065f46;
        }
        &.alert-error {
            background: #fef2f2;
            color: #991b1b;
        }

        .alert-latency {
            font-size: 11px;
            margin-top: 2px;
            opacity: 0.8;
        }
    }

    .card-footer {
        padding: 16px 24px;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        text-align: right;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 10px 20px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;

        &:hover {
            background: #1d4ed8;
        }
        &:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    }
}

.form-group {
    label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
    }

    .input-text {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;

        &:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }
    }

    .font-mono {
        font-family: monospace;
    }
}

.text-green {
    color: #10b981;
    font-weight: 600;
}
.text-red {
    color: #ef4444;
    font-weight: 600;
}
</style>
