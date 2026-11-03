<script setup lang="ts">
import { ref, computed } from 'vue';
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

const computedTotalCollected = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.collected_amount_bdt) || 0), 0);
});

const computedTotalFees = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.courier_fee_bdt) || 0), 0);
});

const computedNetDisbursed = computed(() => {
    return computedTotalCollected.value - computedTotalFees.value;
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

function addMultipleRows(count: number = 3) {
    for (let i = 0; i < count; i++) {
        form.items.push({
            consignment_id: '',
            order_number: '',
            collected_amount_bdt: 0,
            courier_fee_bdt: 70,
            notes: '',
        });
    }
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
                <div class="modal-card wide modal-settlement">
                    <div class="modal-header">
                        <div class="modal-header-content">
                            <div class="header-icon-wrap">
                                <span class="header-icon">📑</span>
                            </div>
                            <div>
                                <h3 class="modal-title">Disbursement Statement Details</h3>
                                <p class="modal-subtitle">
                                    Reference: <strong>{{ activeDetail.reference_invoice || activeDetail.courier_code.toUpperCase() }}</strong> • Disbursed Date: <strong>{{ activeDetail.disbursement_date }}</strong>
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="activeDetail = null" class="btn-close" aria-label="Close modal">✕</button>
                    </div>
                    <div class="modal-body">
                        <div class="detail-summary-strip">
                            <div class="summary-pill">
                                <span class="lbl">Courier:</span>
                                <strong class="courier-badge">{{ activeDetail.courier_code.toUpperCase() }}</strong>
                            </div>
                            <div class="summary-pill">
                                <span class="lbl">Collected:</span>
                                <strong>৳ {{ parseFloat(activeDetail.total_collected_bdt).toFixed(2) }}</strong>
                            </div>
                            <div class="summary-pill">
                                <span class="lbl">Courier Fee:</span>
                                <strong class="text-red">৳ {{ parseFloat(activeDetail.total_courier_fee_bdt).toFixed(2) }}</strong>
                            </div>
                            <div class="summary-pill">
                                <span class="lbl">Net Disbursed:</span>
                                <strong class="text-green">৳ {{ parseFloat(activeDetail.net_disbursed_bdt).toFixed(2) }}</strong>
                            </div>
                            <div class="summary-pill">
                                <span class="lbl">Status:</span>
                                <span :class="['status-pill', activeDetail.status]">{{ activeDetail.status.toUpperCase() }}</span>
                            </div>
                        </div>

                        <div class="detail-table-wrap">
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
            </div>

            <!-- Add Disbursement Modal (Record Courier Settlement Statement) -->
            <div v-if="showAddModal" class="modal-backdrop" @click.self="showAddModal = false">
                <div class="modal-card wide modal-settlement">
                    <div class="modal-header">
                        <div class="modal-header-content">
                            <div class="header-icon-wrap">
                                <span class="header-icon">💰</span>
                            </div>
                            <div>
                                <h3 class="modal-title">Record Courier Settlement Statement</h3>
                                <p class="modal-subtitle">
                                    Input courier payout voucher details and consignment breakdowns to automatically reconcile COD receipts and detect fee discrepancies.
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="showAddModal = false" class="btn-close" aria-label="Close modal">✕</button>
                    </div>

                    <form @submit.prevent="submitDisbursement" class="modal-form">
                        <!-- Top Metadata Card -->
                        <div class="metadata-card">
                            <div class="metadata-grid">
                                <div class="form-group">
                                    <label>
                                        <span class="label-icon">🚚</span>
                                        <span>Courier Partner <span class="required-star">*</span></span>
                                    </label>
                                    <select v-model="form.courier_code" class="input-text styled-select">
                                        <option value="steadfast">⚡ Steadfast Courier</option>
                                        <option value="pathao">🛵 Pathao Courier</option>
                                        <option value="redx">📦 RedX Delivery</option>
                                        <option value="paperfly">✈️ Paperfly Delivery</option>
                                        <option value="sundarban">🏢 Sundarban Courier</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <span class="label-icon">📅</span>
                                        <span>Disbursement Date <span class="required-star">*</span></span>
                                    </label>
                                    <input type="date" v-model="form.disbursement_date" class="input-text" required />
                                </div>

                                <div class="form-group">
                                    <label>
                                        <span class="label-icon">🧾</span>
                                        <span>Statement / Invoice Ref</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="form.reference_invoice"
                                        placeholder="e.g. ST-PAY-20261103"
                                        class="input-text"
                                    />
                                </div>

                                <div class="form-group full-col">
                                    <label>
                                        <span class="label-icon">📝</span>
                                        <span>Settlement Notes / Bank Remittance Reference</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="form.notes"
                                        placeholder="e.g. City Bank Account Deposit #TRX-948210 / Steadfast Weekly Payout"
                                        class="input-text"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Consignments Table Editor -->
                        <div class="items-editor">
                            <div class="editor-header">
                                <div class="editor-title-wrap">
                                    <h4>Consignment Breakdown</h4>
                                    <span class="editor-badge">{{ form.items.length }} {{ form.items.length === 1 ? 'Parcel' : 'Parcels' }}</span>
                                </div>
                                <div class="editor-actions">
                                    <button type="button" @click="addItemRow" class="btn-action-sm">
                                        <span>➕</span> Add Consignment
                                    </button>
                                    <button type="button" @click="addMultipleRows(3)" class="btn-action-outline-sm">
                                        +3 Rows
                                    </button>
                                </div>
                            </div>

                            <div class="consignments-table-wrap">
                                <table class="consignments-editor-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 44px;">#</th>
                                            <th style="min-width: 170px;">Consignment ID <span class="required-star">*</span></th>
                                            <th style="min-width: 150px;">Order Number</th>
                                            <th style="min-width: 140px;">Collected COD (৳) <span class="required-star">*</span></th>
                                            <th style="min-width: 130px;">Courier Fee (৳)</th>
                                            <th style="min-width: 130px;">Net Payout (৳)</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, idx) in form.items" :key="idx" class="editor-row">
                                            <td class="row-index">
                                                <span class="index-pill">{{ idx + 1 }}</span>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    v-model="item.consignment_id"
                                                    placeholder="e.g. ST-982341"
                                                    class="input-cell font-mono"
                                                    required
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    v-model="item.order_number"
                                                    placeholder="e.g. ORD-2026-0001"
                                                    class="input-cell font-mono"
                                                />
                                            </td>
                                            <td>
                                                <div class="cell-currency-wrap">
                                                    <span class="cell-prefix">৳</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        v-model.number="item.collected_amount_bdt"
                                                        placeholder="0.00"
                                                        class="input-cell input-currency"
                                                        required
                                                    />
                                                </div>
                                            </td>
                                            <td>
                                                <div class="cell-currency-wrap">
                                                    <span class="cell-prefix">৳</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        v-model.number="item.courier_fee_bdt"
                                                        placeholder="70.00"
                                                        class="input-cell input-currency"
                                                    />
                                                </div>
                                            </td>
                                            <td class="cell-net">
                                                <span class="net-value" :class="{ 'is-negative': ((Number(item.collected_amount_bdt) || 0) - (Number(item.courier_fee_bdt) || 0)) < 0 }">
                                                    ৳ {{ ((Number(item.collected_amount_bdt) || 0) - (Number(item.courier_fee_bdt) || 0)).toFixed(2) }}
                                                </span>
                                            </td>
                                            <td class="cell-actions">
                                                <button
                                                    type="button"
                                                    @click="removeItemRow(idx)"
                                                    class="btn-trash-row"
                                                    :disabled="form.items.length === 1"
                                                    title="Remove row"
                                                >
                                                    ✕
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Live Calculation Summary Strip -->
                            <div class="live-summary-strip">
                                <div class="summary-metric">
                                    <span class="metric-title">Total Parcels</span>
                                    <span class="metric-number">{{ form.items.length }}</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-metric">
                                    <span class="metric-title">Gross COD Collected</span>
                                    <span class="metric-number">৳ {{ computedTotalCollected.toFixed(2) }}</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-metric">
                                    <span class="metric-title">Total Courier Fees</span>
                                    <span class="metric-number text-red">- ৳ {{ computedTotalFees.toFixed(2) }}</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-metric highlight-payout">
                                    <span class="metric-title">Net Bank Payout</span>
                                    <span class="metric-number text-payout">৳ {{ computedNetDisbursed.toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="modal-actions">
                            <button type="button" @click="showAddModal = false" class="btn-cancel">
                                Cancel
                            </button>
                            <button type="submit" :disabled="form.processing" class="btn-primary-gradient">
                                <span v-if="form.processing">⏳ Reconciling Statement...</span>
                                <span v-else>💰 Record & Reconcile Statement</span>
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
    max-width: 1200px;
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
    border: 1px solid #fbcfe8;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 12px rgba(219, 39, 119, 0.04);

    .card-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.04em;
    }

    .card-value {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        margin: 8px 0 4px 0;
    }

    .card-sub {
        font-size: 12px;
        color: #94a3b8;
    }
}

.status-summary {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.pill-reconciled {
    background: #ecfdf5;
    color: #059669;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #a7f3d0;
}

.pill-discrepancy {
    background: #fef2f2;
    color: #dc2626;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #fecaca;
}

.table-container {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
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
        letter-spacing: 0.04em;
    }

    .courier-badge {
        font-size: 11px;
        background: linear-gradient(135deg, #be185d 0%, #9d174d 100%);
        color: #fff;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 700;
        letter-spacing: 0.05em;
    }

    .status-pill {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;

        &.reconciled {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        &.discrepancy {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        &.pending {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }
    }

    .btn-detail {
        background: #fff5f7;
        color: #db2777;
        border: 1px solid #fbcfe8;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;

        &:hover {
            background: #fdf2f8;
            border-color: #f472b6;
            color: #be185d;
        }
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }
}

// -------------------------------------------------------------
// MODAL STYLING (Refined Baby Pink / Rose Aesthetic)
// -------------------------------------------------------------
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: 20px;
}

.modal-card {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(219, 39, 119, 0.2), 0 10px 25px -5px rgba(0, 0, 0, 0.15);
    border: 1px solid #fbcfe8;
    position: relative;
    display: flex;
    flex-direction: column;

    &.wide {
        max-width: 1020px;
    }

    &::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #ec4899 0%, #db2777 50%, #f43f5e 100%);
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 20px 24px 16px;
        background: linear-gradient(135deg, #fff5f7 0%, #fdf2f8 50%, #fce7f3 100%);
        border-bottom: 1px solid #fbcfe8;

        .modal-header-content {
            display: flex;
            align-items: center;
            gap: 14px;

            .header-icon-wrap {
                width: 44px;
                height: 44px;
                border-radius: 10px;
                background: #ffffff;
                border: 1px solid #fbcfe8;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 8px rgba(219, 39, 119, 0.1);

                .header-icon {
                    font-size: 22px;
                }
            }

            .modal-title {
                font-size: 18px;
                font-weight: 700;
                color: #831843;
                margin: 0;
                line-height: 1.3;
            }

            .modal-subtitle {
                font-size: 13px;
                color: #9d174d;
                margin: 4px 0 0 0;
                line-height: 1.4;
            }
        }

        .btn-close {
            background: #ffffff;
            border: 1px solid #fbcfe8;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            color: #9d174d;
            transition: all 0.15s ease;
            flex-shrink: 0;

            &:hover {
                background: #fdf2f8;
                color: #831843;
                border-color: #f472b6;
                transform: scale(1.05);
            }
        }
    }

    .modal-body {
        padding: 20px 24px;
    }

    .modal-form {
        padding: 20px 24px;
    }
}

// Detail modal helpers
.detail-summary-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    background: #fff5f7;
    border: 1px solid #fbcfe8;
    border-radius: 10px;
    margin-bottom: 16px;

    .summary-pill {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #475569;

        .lbl {
            color: #94a3b8;
            font-size: 12px;
        }
    }
}

.detail-table-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

// Metadata Card in Add Modal
.metadata-card {
    background: linear-gradient(135deg, #fff5f7 0%, #fdf2f8 50%, #fce7f3 100%);
    border: 1px solid #fbcfe8;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
}

.metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;

    .full-col {
        grid-column: 1 / -1;
    }
}

.form-group {
    label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #831843;
        margin-bottom: 6px;

        .label-icon {
            font-size: 14px;
        }

        .required-star {
            color: #e11d48;
            font-weight: 700;
        }
    }

    .input-text {
        width: 100%;
        padding: 9px 12px;
        background: #ffffff;
        border: 1.5px solid #fbcfe8;
        border-radius: 8px;
        font-size: 13px;
        color: #1e293b;
        transition: all 0.15s ease;

        &:focus {
            outline: none;
            border-color: #db2777;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
        }

        &::placeholder {
            color: #94a3b8;
        }
    }

    .styled-select {
        cursor: pointer;
        font-weight: 500;
    }
}

// Consignments Items Editor
.items-editor {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);

    .editor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;

        .editor-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;

            h4 {
                font-size: 14px;
                font-weight: 700;
                color: #0f172a;
                margin: 0;
            }

            .editor-badge {
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                background: #f1f5f9;
                color: #475569;
                border-radius: 12px;
            }
        }

        .editor-actions {
            display: flex;
            align-items: center;
            gap: 8px;

            .btn-action-sm {
                background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
                color: #ffffff;
                border: none;
                padding: 6px 14px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                box-shadow: 0 2px 6px rgba(219, 39, 119, 0.2);
                transition: all 0.15s ease;

                &:hover {
                    opacity: 0.95;
                    transform: translateY(-1px);
                }
            }

            .btn-action-outline-sm {
                background: #ffffff;
                border: 1px solid #fbcfe8;
                color: #db2777;
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.15s ease;

                &:hover {
                    background: #fff5f7;
                    border-color: #f472b6;
                }
            }
        }
    }

    .consignments-table-wrap {
        max-height: 380px;
        overflow-y: auto;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        margin-bottom: 14px;
    }

    .consignments-editor-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;

        thead {
            position: sticky;
            top: 0;
            z-index: 2;

            th {
                background: #f8fafc;
                font-weight: 600;
                color: #64748b;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                padding: 10px 12px;
                border-bottom: 2px solid #e2e8f0;
                text-align: left;

                .required-star {
                    color: #e11d48;
                    font-weight: 700;
                }
            }
        }

        tbody {
            .editor-row {
                border-bottom: 1px solid #f1f5f9;
                transition: background 0.15s ease;

                &:hover {
                    background: #fff5f7;
                }

                td {
                    padding: 8px 10px;
                    vertical-align: middle;
                }

                .row-index {
                    text-align: center;

                    .index-pill {
                        display: inline-block;
                        width: 24px;
                        height: 24px;
                        line-height: 24px;
                        border-radius: 50%;
                        background: #f1f5f9;
                        color: #64748b;
                        font-size: 11px;
                        font-weight: 700;
                    }
                }

                .input-cell {
                    width: 100%;
                    padding: 7px 10px;
                    border: 1.5px solid #e2e8f0;
                    border-radius: 6px;
                    font-size: 13px;
                    background: #ffffff;
                    color: #1e293b;
                    transition: all 0.15s ease;

                    &:focus {
                        outline: none;
                        border-color: #db2777;
                        box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.12);
                    }

                    &.font-mono {
                        font-family: monospace;
                        font-size: 12px;
                    }
                }

                .cell-currency-wrap {
                    position: relative;
                    display: flex;
                    align-items: center;

                    .cell-prefix {
                        position: absolute;
                        left: 10px;
                        color: #94a3b8;
                        font-weight: 600;
                        font-size: 12px;
                        pointer-events: none;
                    }

                    .input-currency {
                        padding-left: 24px;
                        text-align: right;
                        font-variant-numeric: tabular-nums;
                    }
                }

                .cell-net {
                    text-align: right;
                    padding-right: 14px;

                    .net-value {
                        font-weight: 700;
                        font-size: 13px;
                        color: #059669;
                        font-variant-numeric: tabular-nums;

                        &.is-negative {
                            color: #dc2626;
                        }
                    }
                }

                .cell-actions {
                    text-align: center;

                    .btn-trash-row {
                        background: #fef2f2;
                        color: #dc2626;
                        border: 1px solid #fecaca;
                        width: 28px;
                        height: 28px;
                        border-radius: 6px;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        font-size: 12px;
                        font-weight: 700;
                        transition: all 0.15s ease;

                        &:hover:not(:disabled) {
                            background: #fee2e2;
                            border-color: #fca5a5;
                            transform: scale(1.08);
                        }

                        &:disabled {
                            opacity: 0.35;
                            cursor: not-allowed;
                        }
                    }
                }
            }
        }
    }

    // Live Summary Strip at bottom of table
    .live-summary-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #fff5f7 0%, #fdf2f8 50%, #fce7f3 100%);
        border: 1px solid #fbcfe8;
        border-radius: 10px;
        padding: 12px 20px;
        flex-wrap: wrap;
        gap: 12px;

        .summary-metric {
            display: flex;
            flex-direction: column;

            .metric-title {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                color: #831843;
                letter-spacing: 0.03em;
            }

            .metric-number {
                font-size: 15px;
                font-weight: 800;
                color: #0f172a;
                margin-top: 2px;
                font-variant-numeric: tabular-nums;
            }

            &.highlight-payout {
                background: #ffffff;
                padding: 6px 14px;
                border-radius: 8px;
                border: 1.5px solid #a7f3d0;
                box-shadow: 0 2px 6px rgba(5, 150, 105, 0.08);

                .metric-title {
                    color: #065f46;
                }

                .text-payout {
                    color: #059669;
                    font-size: 16px;
                }
            }
        }

        .summary-divider {
            width: 1px;
            height: 32px;
            background: #fbcfe8;
        }
    }
}

// Modal Actions Footer
.modal-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    padding-top: 10px;

    .btn-cancel {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;

        &:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
    }

    .btn-primary-gradient {
        background: linear-gradient(135deg, #ec4899 0%, #db2777 50%, #f43f5e 100%);
        color: #ffffff;
        border: none;
        padding: 11px 24px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(219, 39, 119, 0.25);
        transition: all 0.15s ease;

        &:hover:not(:disabled) {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(219, 39, 119, 0.35);
        }

        &:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
    }
}

.btn-primary {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(219, 39, 119, 0.2);
    transition: all 0.15s ease;

    &:hover {
        opacity: 0.95;
        transform: translateY(-1px);
    }
}

.text-green { color: #059669; }
.text-red { color: #dc2626; }
.font-bold { font-weight: 700; }
</style>
