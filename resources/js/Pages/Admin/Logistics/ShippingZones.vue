<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ZoneRate {
    base_rate_bdt: string;
    base_weight_kg: string;
    extra_rate_per_kg_bdt: string;
    free_shipping_threshold_bdt: string | null;
}

interface ShippingZoneItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    estimated_days_min: number;
    estimated_days_max: number;
    rate: ZoneRate;
}

const props = defineProps<{
    zones: ShippingZoneItem[];
}>();

const editingZone = ref<ShippingZoneItem | null>(null);

const form = useForm({
    name: '',
    estimated_days_min: 1,
    estimated_days_max: 2,
    base_rate_bdt: '70.0000',
    base_weight_kg: '1.000',
    extra_rate_per_kg_bdt: '20.0000',
    free_shipping_threshold_bdt: '' as string | number,
});

function openEdit(zone: ShippingZoneItem) {
    editingZone.value = zone;
    form.name = zone.name;
    form.estimated_days_min = zone.estimated_days_min;
    form.estimated_days_max = zone.estimated_days_max;
    form.base_rate_bdt = zone.rate.base_rate_bdt;
    form.base_weight_kg = zone.rate.base_weight_kg;
    form.extra_rate_per_kg_bdt = zone.rate.extra_rate_per_kg_bdt;
    form.free_shipping_threshold_bdt = zone.rate.free_shipping_threshold_bdt || '';
}

function saveZone() {
    if (!editingZone.value) return;

    form.post(`/admin/logistics/zones/${editingZone.value.id}/rate`, {
        preserveScroll: true,
        onSuccess: () => {
            editingZone.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="Shipping Zones & Delivery Pricing" />

        <div class="zones-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Dynamic Delivery Zones & Pricing Matrix</h1>
                    <p class="page-subtitle">Configure Bangladesh delivery zones (Inside Dhaka, Suburbs, Outside Dhaka), weight brackets, and free delivery thresholds.</p>
                </div>
            </div>

            <!-- Zones Grid -->
            <div class="zones-grid">
                <div v-for="zone in zones" :key="zone.code" class="zone-card">
                    <div class="card-header">
                        <div>
                            <h2 class="zone-name">{{ zone.name }}</h2>
                            <p class="zone-code">Zone Code: <code>{{ zone.code }}</code></p>
                        </div>
                        <span class="days-badge">
                            ⏱️ {{ zone.estimated_days_min }}-{{ zone.estimated_days_max }} Days
                        </span>
                    </div>

                    <p class="zone-desc">{{ zone.description }}</p>

                    <div class="rate-breakdown">
                        <div class="rate-metric">
                            <span class="metric-label">Base Delivery Fee</span>
                            <span class="metric-value">৳ {{ parseFloat(zone.rate.base_rate_bdt).toFixed(2) }}</span>
                            <span class="metric-sub">Up to {{ parseFloat(zone.rate.base_weight_kg).toFixed(1) }} kg</span>
                        </div>
                        <div class="rate-metric">
                            <span class="metric-label">Extra per kg</span>
                            <span class="metric-value">+৳ {{ parseFloat(zone.rate.extra_rate_per_kg_bdt).toFixed(2) }}</span>
                            <span class="metric-sub">Per additional kg</span>
                        </div>
                        <div class="rate-metric">
                            <span class="metric-label">Free Delivery Over</span>
                            <span class="metric-value text-green">
                                {{ zone.rate.free_shipping_threshold_bdt ? '৳ ' + parseFloat(zone.rate.free_shipping_threshold_bdt).toFixed(0) : 'None' }}
                            </span>
                            <span class="metric-sub">Cart subtotal threshold</span>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="button" @click="openEdit(zone)" class="btn-edit">
                            ✏️ Edit Zone Pricing
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div v-if="editingZone" class="modal-backdrop" @click.self="editingZone = null">
                <div class="modal-card">
                    <div class="modal-header">
                        <h3>Edit Pricing: {{ editingZone.name }}</h3>
                        <button type="button" @click="editingZone = null" class="btn-close">✕</button>
                    </div>

                    <form @submit.prevent="saveZone" class="modal-form">
                        <div class="form-group">
                            <label>Zone Display Name</label>
                            <input type="text" v-model="form.name" class="input-text" required />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Estimated Days (Min)</label>
                                <input type="number" v-model="form.estimated_days_min" min="1" class="input-text" required />
                            </div>
                            <div class="form-group">
                                <label>Estimated Days (Max)</label>
                                <input type="number" v-model="form.estimated_days_max" min="1" class="input-text" required />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Base Rate (৳ BDT)</label>
                                <input type="number" step="0.01" v-model="form.base_rate_bdt" class="input-text" required />
                            </div>
                            <div class="form-group">
                                <label>Base Weight Limit (kg)</label>
                                <input type="number" step="0.1" v-model="form.base_weight_kg" class="input-text" required />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Extra Fee per kg (৳ BDT)</label>
                                <input type="number" step="0.01" v-model="form.extra_rate_per_kg_bdt" class="input-text" required />
                            </div>
                            <div class="form-group">
                                <label>Free Shipping Minimum (৳ BDT)</label>
                                <input type="number" step="1" v-model="form.free_shipping_threshold_bdt" placeholder="e.g. 2500" class="input-text" />
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" @click="editingZone = null" class="btn-cancel">Cancel</button>
                            <button type="submit" :disabled="form.processing" class="btn-save">
                                {{ form.processing ? 'Saving...' : 'Save Zone Pricing' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style lang="scss" scoped>
.zones-container {
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

.zones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.zone-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;

        .zone-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .zone-code code {
            font-size: 11px;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            color: #475569;
        }

        .days-badge {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
        }
    }

    .zone-desc {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 16px;
        min-height: 38px;
    }

    .rate-breakdown {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 16px;

        .rate-metric {
            display: flex;
            flex-direction: column;

            .metric-label {
                font-size: 10px;
                color: #64748b;
                font-weight: 600;
                text-transform: uppercase;
            }

            .metric-value {
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
                margin: 2px 0;
            }

            .metric-sub {
                font-size: 9px;
                color: #94a3b8;
            }
        }
    }

    .card-footer {
        .btn-edit {
            width: 100%;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;

            &:hover {
                background: #f8fafc;
                border-color: #94a3b8;
            }
        }
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
    max-width: 500px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);

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

            &:hover {
                color: #0f172a;
            }
        }
    }

    .modal-form {
        padding: 20px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .form-group {
        margin-bottom: 14px;

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

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 10px;

        .btn-cancel {
            background: #f1f5f9;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-save {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;

            &:hover {
                background: #1d4ed8;
            }
            &:disabled {
                opacity: 0.6;
            }
        }
    }
}

.text-green {
    color: #059669;
}
</style>

