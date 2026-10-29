<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface DisbursementItem {
    id: number;
    consignment_id: string;
    order_number: string | null;
    collected_amount_bdt: string;
    expected_amount_bdt: string;
    courier_fee_bdt: string;
    discrepancy_amount_bdt: string;
    is_reconciled: boolean;
    notes: string | null;
}

interface DisbursementRecord {
    id: number;
    ulid: string;
    courier_code: string;
    reference_invoice: string | null;
    disbursement_date: string;
    total_collected_bdt: string;
    total_courier_fee_bdt: string;
    net_disbursed_bdt: string;
    status: string;
    notes: string | null;
    items?: DisbursementItem[];
}

const props = defineProps<{
    metrics: {
        total_collected_bdt: string;
        total_fees_bdt: string;
        net_disbursed_bdt: string;
        discrepancy_count: number;
        reconciled_count: number;
    };
    disbursements: {
        data: DisbursementRecord[];
        current_page: number;
        last_page: number;
        total: number;
    };
}>();

const showAddModal = ref(false);
const activeDetail = ref<DisbursementRecord | null>(null);

const form = useForm({
    courier_code: 'steadfast',
    disbursement_date: new Date().toISOString().split('T')[0],
    reference_invoice: '',
    notes: '',
    items: [
        {
            consignment_id: '',
            order_number: '',
            collected_amount_bdt: 0,
            courier_fee_bdt: 70,
            notes: '',
        },
    ],
});

function addItemRow() {
    form.items.push({
        consignment_id: '',
        order_number: '',
        collected_amount_bdt: 0,
        courier_fee_bdt: 70,
        notes: '',
    });
}

function removeItemRow(index: number) {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function submitDisbursement() {
    form.post('/admin/logistics/cod-reconciliation', {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="COD Cash Reconciliation" />

        <div class="reconcile-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Courier COD Cash Reconciliation</h1>
                    <p class="page-subtitle">Reconcile Cash on Delivery (COD) payouts against merchant consignments and detect courier financial discrepancies.</p>
                </div>
                <button type="button" @click="showAddModal = true" class="btn-primary">
                    + Record Courier Settlement
                </button>
            </div>

            <!-- Financial Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="card-label">Total COD Cash Collected</span>
                    <span class="card-value">৳ {{ parseFloat(metrics.total_collected_bdt).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                    <span class="card-sub">Gross cash from couriers</span>
                </div>
                <div class="metric-card">
                    <span class="card-label">Total Courier Fees Deducted</span>
                    <span class="card-value text-red">৳ {{ parseFloat(metrics.total_fees_bdt).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                    <span class="card-sub">Delivery & COD service fees</span>
                </div>
                <div class="metric-card">
                    <span class="card-label">Net Bank Payouts Disbursed</span>
                    <span class="card-value text-green">৳ {{ parseFloat(metrics.net_disbursed_bdt).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                    <span class="card-sub">Net revenue deposited</span>
                </div>
                <div class="metric-card">
                    <span class="card-label">Settlement Audit Status</span>
                    <div class="status-summary">
                        <span class="pill-reconciled">{{ metrics.reconciled_count }} Reconciled</span>
                        <span v-if="metrics.discrepancy_count > 0" class="pill-discrepancy">{{ metrics.discrepancy_count }} Discrepancy</span>
                    </div>
                </div>
            </div>

            <!-- Disbursements Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Courier</th>
                            <th>Settlement Ref</th>
                            <th>Date</th>
                            <th>Total Collected</th>
                            <th>Courier Fees</th>
                            <th>Net Disbursed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in disbursements.data" :key="d.ulid">
                            <td>
                                <strong class="courier-badge">{{ d.courier_code.toUpperCase() }}</strong>
                            </td>
                            <td>{{ d.reference_invoice || 'N/A' }}</td>
                            <td>{{ d.disbursement_date }}</td>
                            <td>৳ {{ parseFloat(d.total_collected_bdt).toFixed(2) }}</td>
                            <td>৳ {{ parseFloat(d.total_courier_fee_bdt).toFixed(2) }}</td>
                            <td><strong>৳ {{ parseFloat(d.net_disbursed_bdt).toFixed(2) }}</strong></td>
                            <td>
                                <span :class="['status-pill', d.status]">
                                    {{ d.status.toUpperCase() }}
                                </span>
                            </td>
                            <td>
                                <button type="button" @click="activeDetail = d" class="btn-detail">
                                    View Items ({{ d.items?.length || 0 }})
                                </button>
                            </td>
                        </tr>
                        <tr v-if="disbursements.data.length === 0">
                            <td colspan="8" class="empty-state">No courier disbursement statements recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Detail Modal -->
            <div v-if="activeDetail" class="modal-backdrop" @click.self="activeDetail = null">
                <div class="modal-card wide">
                    <div class="modal-header">
                        <h3>Disbursement Statement: {{ activeDetail.reference_invoice || activeDetail.courier_code }} ({{ activeDetail.disbursement_date }})</h3>
                        <button type="button" @click="activeDetail = null" class="btn-close">✕</button>
                    </div>
                    <div class="modal-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Consignment ID</th>
                                    <th>Order #</th>
                                    <th>Collected Amount</th>
                                    <th>Expected Amount</th>
                                    <th>Courier Fee</th>
                                    <th>Discrepancy</th>
                                    <th>Match Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in activeDetail.items" :key="item.id">
                                    <td><code>{{ item.consignment_id }}</code></td>
                                    <td>{{ item.order_number || 'N/A' }}</td>
                                    <td>৳ {{ parseFloat(item.collected_amount_bdt).toFixed(2) }}</td>
                                    <td>৳ {{ parseFloat(item.expected_amount_bdt).toFixed(2) }}</td>
                                    <td>৳ {{ parseFloat(item.courier_fee_bdt).toFixed(2) }}</td>
                                    <td :class="parseFloat(item.discrepancy_amount_bdt) !== 0 ? 'text-red font-bold' : ''">
                                        ৳ {{ parseFloat(item.discrepancy_amount_bdt).toFixed(2) }}
                                    </td>
                                    <td>
                                        <span :class="item.is_reconciled ? 'pill-reconciled' : 'pill-discrepancy'">
                                            {{ item.is_reconciled ? 'Reconciled' : 'Discrepancy' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Disbursement Modal -->
            <div v-if="showAddModal" class="modal-backdrop" @click.self="showAddModal = false">
                <div class="modal-card wide">
                    <div class="modal-header">
                        <h3>Record Courier Settlement Statement</h3>
                        <button type="button" @click="showAddModal = false" class="btn-close">✕</button>
                    </div>

                    <form @submit.prevent="submitDisbursement" class="modal-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Courier Provider</label>
                                <select v-model="form.courier_code" class="input-text">
                                    <option value="steadfast">Steadfast Courier</option>
                                    <option value="pathao">Pathao Courier</option>
                                    <option value="redx">RedX Delivery</option>
                                    <option value="paperfly">Paperfly</option>
                                    <option value="sundarban">Sundarban</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Disbursement Date</label>
                                <input type="date" v-model="form.disbursement_date" class="input-text" required />
                            </div>
                            <div class="form-group">
                                <label>Courier Statement / Invoice Ref</label>
                                <input type="text" v-model="form.reference_invoice" placeholder="e.g. ST-PAY-20261029" class="input-text" />
                            </div>
                        </div>

                        <div class="items-editor">
                            <div class="editor-header">
                                <h4>Settlement Consignments</h4>
                                <button type="button" @click="addItemRow" class="btn-add-item">+ Add Row</button>
                            </div>

                            <div v-for="(item, idx) in form.items" :key="idx" class="item-input-row">
                                <input type="text" v-model="item.consignment_id" placeholder="Consignment ID" class="input-text" required />
                                <input type="text" v-model="item.order_number" placeholder="Order Number" class="input-text" />
                                <input type="number" step="0.01" v-model="item.collected_amount_bdt" placeholder="Collected ৳" class="input-text" required />
                                <input type="number" step="0.01" v-model="item.courier_fee_bdt" placeholder="Fee ৳" class="input-text" />
                                <button type="button" @click="removeItemRow(idx)" class="btn-remove">✕</button>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" @click="showAddModal = false" class="btn-cancel">Cancel</button>
                            <button type="submit" :disabled="form.processing" class="btn-primary">
                                {{ form.processing ? 'Reconciling...' : 'Record & Reconcile' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style lang="scss" scoped>
.reconcile-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.metric-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;

    .card-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
    }

    .card-value {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 6px 0 2px 0;
    }

    .card-sub {
        font-size: 11px;
        color: #94a3b8;
    }
}

.status-summary {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}

.pill-reconciled {
    background: #ecfdf5;
    color: #059669;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.pill-discrepancy {
    background: #fef2f2;
    color: #dc2626;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.table-container {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;

    th, td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid #f1f5f9;
    }

    th {
        background: #f8fafc;
        font-weight: 600;
        color: #475569;
        font-size: 11px;
        text-transform: uppercase;
    }

    .courier-badge {
        font-size: 11px;
        background: #0f172a;
        color: #fff;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .status-pill {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;

        &.reconciled {
            background: #ecfdf5;
            color: #059669;
        }
        &.discrepancy {
            background: #fef2f2;
            color: #dc2626;
        }
        &.pending {
            background: #fffbeb;
            color: #d97706;
        }
    }

    .btn-detail {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;

        &:hover {
            background: #e2e8f0;
        }
    }

    .empty-state {
        text-align: center;
        padding: 32px;
        color: #94a3b8;
    }
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 16px;
}

.modal-card {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);

    &.wide {
        max-width: 850px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;

        h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #94a3b8;
        }
    }

    .modal-body {
        padding: 20px;
    }

    .modal-form {
        padding: 20px;
    }
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.form-group {
    label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 4px;
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
        }
    }
}

.items-editor {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    background: #f8fafc;

    .editor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;

        h4 {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .btn-add-item {
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
    }

    .item-input-row {
        display: grid;
        grid-template-columns: 2fr 2fr 1.5fr 1.5fr auto;
        gap: 8px;
        margin-bottom: 8px;

        .btn-remove {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 4px;
            padding: 0 10px;
            cursor: pointer;
            font-weight: bold;
        }
    }
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;

    .btn-cancel {
        background: #f1f5f9;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
}

.btn-primary {
    background: #2563eb;
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;

    &:hover {
        background: #1d4ed8;
    }
}

.text-green { color: #059669; }
.text-red { color: #dc2626; }
.font-bold { font-weight: 700; }
</style>

