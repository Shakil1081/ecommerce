<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

interface StatsProps {
  today_handed_over_count: number;
  total_with_courier_count: number;
  pending_dispatch_count: number;
}

interface CourierOption {
  code: string;
  name: string;
  is_sandbox: boolean;
  is_active: boolean;
}

interface OrderItemPayload {
  id: number;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price_bdt: string;
}

interface ScannedOrderPayload {
  id: number;
  order_number: string;
  status: string;
  customer_name: string;
  customer_phone: string;
  courier_code: string;
  courier_tracking_code: string | null;
  courier_consignment_id: string | null;
  delivery_zone: string;
  district_name: string;
  delivery_address: string;
  total_bdt: string;
  payable_cod_amount_bdt: string;
  items_count: number;
  items: OrderItemPayload[];
  shipped_at: string;
  scanned_at: string;
}

interface RecentHandover {
  id: number;
  order_number: string;
  customer_name: string;
  customer_phone: string;
  courier_code: string;
  courier_tracking_code: string | null;
  courier_consignment_id: string | null;
  delivery_zone: string;
  district_name: string;
  total_bdt: string;
  payable_cod_amount_bdt: string;
  items_count: number;
  shipped_at: string;
}

interface SessionLogItem {
  id: string;
  order_id: number;
  order_number: string;
  courier_code: string;
  tracking_code: string;
  customer_name: string;
  customer_phone: string;
  destination: string;
  cod_amount: number;
  items_count: number;
  time: string;
  status: 'success' | 'duplicate' | 'error';
  message: string;
}

const props = defineProps<{
  stats: StatsProps;
  availableCouriers: CourierOption[];
  recentHandovers: RecentHandover[];
}>();

// Barcode input & console state
const barcodeInput = ref('');
const scannerInputRef = ref<HTMLInputElement | null>(null);
const isProcessing = ref(false);
const autoLockFocus = ref(true);
const soundEnabled = ref(true);
const autoBook = ref(true);
const selectedCourier = ref<string>(props.availableCouriers[0]?.code || 'steadfast');
const pickupRiderNotes = ref('');

// Current scan result
const currentResult = ref<{
  status: 'idle' | 'success' | 'duplicate' | 'error';
  message: string;
  order?: ScannedOrderPayload;
  rawBarcode?: string;
}>({
  status: 'idle',
  message: '',
});

// Session metrics & log
const sessionLog = ref<SessionLogItem[]>([]);
const todayDispatchedCount = ref(props.stats.today_handed_over_count);
const pendingDispatchCount = ref(props.stats.pending_dispatch_count);

const sessionScannedCount = computed(() => {
  return sessionLog.value.filter((i) => i.status === 'success').length;
});

const sessionTotalCodBdt = computed(() => {
  return sessionLog.value
    .filter((i) => i.status === 'success')
    .reduce((sum, item) => sum + item.cod_amount, 0);
});

const successfulOrderIds = computed(() => {
  return sessionLog.value
    .filter((i) => i.status === 'success')
    .map((i) => i.order_id);
});

// Audio Synthesis using Web Audio API (Zero external assets required)
let audioCtx: AudioContext | null = null;

const initAudio = () => {
  if (!audioCtx && typeof window !== 'undefined') {
    const AudioContextClass = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
    if (AudioContextClass) {
      audioCtx = new AudioContextClass();
    }
  }
};

const playSuccessChime = () => {
  if (!soundEnabled.value) return;
  try {
    initAudio();
    if (!audioCtx) return;

    if (audioCtx.state === 'suspended') {
      audioCtx.resume();
    }

    const now = audioCtx.currentTime;
    const osc1 = audioCtx.createOscillator();
    const osc2 = audioCtx.createOscillator();
    const gain = audioCtx.createGain();

    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(880, now); // A5
    osc1.frequency.setValueAtTime(1174.66, now + 0.08); // D6

    osc2.type = 'triangle';
    osc2.frequency.setValueAtTime(440, now);
    osc2.frequency.setValueAtTime(587.33, now + 0.08);

    gain.gain.setValueAtTime(0.2, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

    osc1.connect(gain);
    osc2.connect(gain);
    gain.connect(audioCtx.destination);

    osc1.start(now);
    osc2.start(now);
    osc1.stop(now + 0.35);
    osc2.stop(now + 0.35);
  } catch (e) {
    console.warn('Audio chime playback failed:', e);
  }
};

const playWarningBuzz = () => {
  if (!soundEnabled.value) return;
  try {
    initAudio();
    if (!audioCtx) return;

    if (audioCtx.state === 'suspended') {
      audioCtx.resume();
    }

    const now = audioCtx.currentTime;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();

    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(160, now);
    osc.frequency.setValueAtTime(130, now + 0.12);

    gain.gain.setValueAtTime(0.25, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

    osc.connect(gain);
    gain.connect(audioCtx.destination);

    osc.start(now);
    osc.stop(now + 0.35);
  } catch (e) {
    console.warn('Audio warning buzz failed:', e);
  }
};

const ensureFocus = () => {
  if (autoLockFocus.value && scannerInputRef.value) {
    scannerInputRef.value.focus();
  }
};

const handleWindowClick = (e: MouseEvent) => {
  if (!autoLockFocus.value) return;
  const target = e.target as HTMLElement;
  const isInteractive = target.closest('button, a, select, textarea, input:not(.barcode-input)');
  if (!isInteractive) {
    ensureFocus();
  }
};

const getCsrfToken = (): string => {
  const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
  return meta?.content || '';
};

// Scan Processor
const processBarcode = async () => {
  const code = barcodeInput.value.trim();
  if (!code || isProcessing.value) return;

  isProcessing.value = true;
  barcodeInput.value = '';

  try {
    const res = await axios.post('/admin/orders/scan-handover/process', {
      barcode: code,
      courier_code: selectedCourier.value,
      auto_book: autoBook.value,
      notes: pickupRiderNotes.value.trim() || null,
    }, {
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
      },
    });

    const data = res.data;

    if (data.success && data.order) {
      playSuccessChime();
      currentResult.value = {
        status: 'success',
        message: data.message,
        order: data.order,
        rawBarcode: code,
      };

      // Check if already in session log
      const alreadyInSession = sessionLog.value.some((i) => i.order_id === data.order.id);
      if (!alreadyInSession) {
        sessionLog.value.unshift({
          id: `${Date.now()}-${data.order.id}`,
          order_id: data.order.id,
          order_number: data.order.order_number,
          courier_code: data.order.courier_code,
          tracking_code: data.order.courier_tracking_code || data.order.courier_consignment_id || 'N/A',
          customer_name: data.order.customer_name,
          customer_phone: data.order.customer_phone,
          destination: data.order.district_name || data.order.delivery_zone,
          cod_amount: Number(data.order.payable_cod_amount_bdt || 0),
          items_count: data.order.items_count,
          time: data.order.scanned_at || new Date().toLocaleTimeString(),
          status: 'success',
          message: data.message,
        });
        todayDispatchedCount.value += 1;
        if (pendingDispatchCount.value > 0) {
          pendingDispatchCount.value -= 1;
        }
      }
    } else if (data.already_handed_over) {
      playWarningBuzz();
      currentResult.value = {
        status: 'duplicate',
        message: data.message,
        order: data.order,
        rawBarcode: code,
      };
    } else {
      playWarningBuzz();
      currentResult.value = {
        status: 'error',
        message: data.message || 'Scanned barcode could not be processed.',
        order: data.order,
        rawBarcode: code,
      };
    }
  } catch (err: any) {
    playWarningBuzz();
    const errMsg = err?.response?.data?.message || err?.message || 'Server error while processing barcode scan.';
    currentResult.value = {
      status: 'error',
      message: errMsg,
      rawBarcode: code,
    };
  } finally {
    isProcessing.value = false;
    nextTick(() => {
      ensureFocus();
    });
  }
};

const handleBarcodeKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    processBarcode();
  }
};

const printCurrentSessionManifest = () => {
  const ids = successfulOrderIds.value;
  if (ids.length === 0) {
    alert('Please scan at least one order to generate a handover manifest.');
    return;
  }
  const url = `/admin/orders/scan-handover/manifest?order_ids=${ids.join(',')}&courier_code=${selectedCourier.value}`;
  window.open(url, '_blank');
};

const printSessionShippingLabels = () => {
  const ids = successfulOrderIds.value;
  if (ids.length === 0) return;
  const url = `/admin/orders/bulk-shipping-labels?order_ids=${ids.join(',')}`;
  window.open(url, '_blank');
};

const printSessionBagTickets = () => {
  const ids = successfulOrderIds.value;
  if (ids.length === 0) return;
  const url = `/admin/orders/bag-tickets?order_ids=${ids.join(',')}`;
  window.open(url, '_blank');
};

const resetSession = () => {
  if (sessionLog.value.length === 0) return;
  if (confirm('Start a new courier handover batch? This will clear the current session manifest table.')) {
    sessionLog.value = [];
    currentResult.value = { status: 'idle', message: '' };
    nextTick(() => ensureFocus());
  }
};

const formatCurrency = (val: number | string): string => {
  return Number(val).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(() => {
  window.addEventListener('click', handleWindowClick);
  nextTick(() => {
    ensureFocus();
  });
});

onUnmounted(() => {
  window.removeEventListener('click', handleWindowClick);
});
</script>

<template>
  <AdminLayout>
    <Head title="Scan & Handover to Courier" />

    <div class="scan-handover-page">
      <!-- Top Action Navigation Bar -->
      <div class="page-header">
        <div class="left-col">
          <Link href="/admin/orders" class="btn btn-outline btn-sm">
            ← Back to Orders
          </Link>
          <div>
            <h1 class="page-title">📦 Scan & Handover to Courier</h1>
            <p class="page-subtitle">
              High-speed warehouse console to dispatch packed parcels, book online consignments, and hand over to courier riders.
            </p>
          </div>
        </div>

        <div class="header-tools">
          <!-- Audio Toggle -->
          <button
            type="button"
            class="tool-btn"
            :class="{ 'is-active': soundEnabled }"
            :title="soundEnabled ? 'Mute sound effects' : 'Enable audio chimes'"
            @click="soundEnabled = !soundEnabled"
          >
            <span>{{ soundEnabled ? '🔊 Sound On' : '🔇 Muted' }}</span>
          </button>

          <!-- Auto-Focus Lock Toggle -->
          <button
            type="button"
            class="tool-btn"
            :class="{ 'is-active': autoLockFocus }"
            :title="autoLockFocus ? 'Scanner Auto-Focus Lock is Active' : 'Auto-Focus Disabled'"
            @click="autoLockFocus = !autoLockFocus; ensureFocus()"
          >
            <span>{{ autoLockFocus ? '🎯 Focus Locked' : 'Focus Free' }}</span>
          </button>

          <Link href="/admin/orders/scan-returns" class="btn btn-outline btn-sm">
            📷 Scan Returns Console →
          </Link>
        </div>
      </div>

      <!-- Dispatch Controls & Courier Configuration Bar -->
      <div class="dispatch-config-bar">
        <div class="config-item">
          <label class="config-label">🚚 Courier Partner Collecting:</label>
          <select v-model="selectedCourier" class="config-select">
            <option v-for="c in availableCouriers" :key="c.code" :value="c.code">
              {{ c.name }} {{ c.is_sandbox ? '🧪 (Sandbox)' : '⚡ (Live)' }}
            </option>
          </select>
        </div>

        <div class="config-item checkbox-item">
          <label class="checkbox-label" title="Automatically book consignment with courier API if not already booked">
            <input v-model="autoBook" type="checkbox" class="config-checkbox" />
            <span>Auto-book online consignment with courier API</span>
          </label>
        </div>

        <div class="config-item flex-1">
          <label class="config-label">Rider / Pickup Note (Optional):</label>
          <input
            v-model="pickupRiderNotes"
            type="text"
            placeholder="e.g. Rider Rahim (01700...), Bike DHAKA-HA-1234"
            class="config-input"
          />
        </div>
      </div>

      <!-- Live KPI Metrics Strip -->
      <div class="metrics-grid">
        <div class="metric-card card-session">
          <div class="metric-icon">📦</div>
          <div class="metric-content">
            <div class="metric-num">{{ sessionScannedCount }}</div>
            <div class="metric-label">Parcels Handed Over (Session)</div>
          </div>
        </div>

        <div class="metric-card card-cod">
          <div class="metric-icon">💰</div>
          <div class="metric-content">
            <div class="metric-num">৳{{ formatCurrency(sessionTotalCodBdt) }}</div>
            <div class="metric-label">Collectible COD Cash (Session)</div>
          </div>
        </div>

        <div class="metric-card card-today">
          <div class="metric-icon">🚚</div>
          <div class="metric-content">
            <div class="metric-num">{{ todayDispatchedCount }}</div>
            <div class="metric-label">Total Dispatched Today</div>
          </div>
        </div>

        <div class="metric-card card-pending">
          <div class="metric-icon">⏳</div>
          <div class="metric-content">
            <div class="metric-num">{{ pendingDispatchCount }}</div>
            <div class="metric-label">Pending In Picking/Packing</div>
          </div>
        </div>
      </div>

      <!-- Main Scanning Console Card -->
      <div class="scanner-card">
        <div class="input-header">
          <span class="pulse-indicator" :class="{ 'is-busy': isProcessing }"></span>
          <label class="input-title">Scan Shipping Label Barcode / Bag Ticket / Tracking Code:</label>
        </div>

        <div class="scanner-input-wrap">
          <input
            ref="scannerInputRef"
            v-model="barcodeInput"
            type="text"
            class="barcode-input"
            placeholder="Ready for barcode scanner (or type Order # / Tracking # and hit Enter)..."
            :disabled="isProcessing"
            @keydown="handleBarcodeKeydown"
          />
          <button
            type="button"
            class="btn btn-primary btn-submit-scan"
            :disabled="!barcodeInput.trim() || isProcessing"
            @click="processBarcode"
          >
            <span v-if="isProcessing">⏳ Processing...</span>
            <span v-else>↵ Submit Scan</span>
          </button>
        </div>

        <div class="input-tip">
          <span>💡 Supports USB & Bluetooth handheld barcode scanners, Order # (e.g. <code>ORD-2026-0001</code>), Courier Tracking Codes, Consignment IDs, or Bag Ticket barcodes.</span>
        </div>
      </div>

      <!-- Live Scan Result Feedback Banner -->
      <transition name="fade">
        <div
          v-if="currentResult.status !== 'idle'"
          class="result-banner"
          :class="`is-${currentResult.status}`"
        >
          <div class="result-icon-col">
            <span v-if="currentResult.status === 'success'">✅</span>
            <span v-else-if="currentResult.status === 'duplicate'">⚠️</span>
            <span v-else>❌</span>
          </div>

          <div class="result-body">
            <div class="result-headline">
              {{ currentResult.message }}
            </div>

            <!-- Last Scanned Order Summary Preview -->
            <div v-if="currentResult.order" class="order-preview-grid">
              <div class="preview-item">
                <span class="preview-lbl">Order #:</span>
                <strong class="font-mono">{{ currentResult.order.order_number }}</strong>
              </div>
              <div class="preview-item">
                <span class="preview-lbl">Customer:</span>
                <span>{{ currentResult.order.customer_name }} ({{ currentResult.order.customer_phone }})</span>
              </div>
              <div class="preview-item">
                <span class="preview-lbl">Destination:</span>
                <span>{{ currentResult.order.district_name }} ({{ currentResult.order.delivery_zone }})</span>
              </div>
              <div class="preview-item">
                <span class="preview-lbl">Courier & Tracking:</span>
                <span class="font-mono text-primary font-bold">
                  {{ currentResult.order.courier_code }} - {{ currentResult.order.courier_tracking_code || currentResult.order.courier_consignment_id || 'Assigned' }}
                </span>
              </div>
              <div class="preview-item">
                <span class="preview-lbl">COD Amount:</span>
                <strong class="text-success">৳{{ formatCurrency(currentResult.order.payable_cod_amount_bdt) }}</strong>
              </div>
              <div class="preview-item">
                <span class="preview-lbl">Items:</span>
                <span>{{ currentResult.order.items_count }} SKU unit(s)</span>
              </div>
            </div>
          </div>
        </div>
      </transition>

      <!-- Session Handover Manifest Table & Actions -->
      <div class="manifest-card">
        <div class="manifest-card-header">
          <div class="manifest-title-wrap">
            <h2 class="card-title">📋 Current Pickup Handover Manifest</h2>
            <span class="manifest-count-pill">{{ sessionScannedCount }} package(s) scanned in this batch</span>
          </div>

          <div class="manifest-actions">
            <!-- Print Manifest for Rider to Sign -->
            <button
              type="button"
              class="btn btn-manifest-print"
              :disabled="sessionScannedCount === 0"
              title="Print official Courier Pickup Manifest for rider to sign"
              @click="printCurrentSessionManifest"
            >
              🖨️ Print Courier Handover Manifest ({{ sessionScannedCount }})
            </button>

            <!-- Print Bulk 4x6 Labels -->
            <button
              type="button"
              class="btn btn-outline btn-sm"
              :disabled="sessionScannedCount === 0"
              title="Open 4x6 thermal barcode labels for all parcels in this batch"
              @click="printSessionShippingLabels"
            >
              🏷️ 4x6 Labels
            </button>

            <!-- Print Bulk Bag Tickets -->
            <button
              type="button"
              class="btn btn-outline btn-sm"
              :disabled="sessionScannedCount === 0"
              title="Open bag tickets for all parcels in this batch"
              @click="printSessionBagTickets"
            >
              🖨️ Bag Tickets
            </button>

            <!-- Reset Batch Button -->
            <button
              v-if="sessionScannedCount > 0"
              type="button"
              class="btn btn-danger-outline btn-sm"
              title="Start a new courier pickup batch"
              @click="resetSession"
            >
              ✕ New Batch
            </button>
          </div>
        </div>

        <!-- Manifest Table -->
        <div class="table-container">
          <table class="manifest-table">
            <thead>
              <tr>
                <th style="width: 40px;">SL</th>
                <th>Order #</th>
                <th>Courier & Tracking</th>
                <th>Customer</th>
                <th>Destination</th>
                <th style="text-align: right;">COD Amount (৳)</th>
                <th>Scanned Time</th>
                <th style="text-align: right; width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in sessionLog" :key="item.id" class="manifest-row">
                <td class="font-bold text-muted">{{ sessionLog.length - idx }}</td>
                <td>
                  <Link :href="`/admin/orders/${item.order_id}`" target="_blank" class="order-link font-mono">
                    <strong>{{ item.order_number }}</strong>
                  </Link>
                  <div class="sub-qty text-muted">{{ item.items_count }} item(s)</div>
                </td>
                <td>
                  <span class="courier-pill" :class="`courier-${item.courier_code?.toLowerCase()}`">
                    🚚 {{ item.courier_code }}
                  </span>
                  <div class="tracking-text font-mono">{{ item.tracking_code }}</div>
                </td>
                <td>
                  <div class="font-bold">{{ item.customer_name }}</div>
                  <div class="text-muted text-xs">📞 {{ item.customer_phone }}</div>
                </td>
                <td>
                  <span class="dest-text">{{ item.destination }}</span>
                </td>
                <td class="text-right font-mono font-bold text-success">
                  ৳{{ formatCurrency(item.cod_amount) }}
                </td>
                <td class="text-muted text-xs font-mono">
                  {{ item.time }}
                </td>
                <td class="text-right">
                  <div class="row-actions">
                    <a
                      :href="`/admin/orders/${item.order_id}/shipping-label`"
                      target="_blank"
                      class="btn-row-label"
                      title="Open 4x6 shipping label"
                    >
                      🏷️ Label
                    </a>
                    <a
                      :href="`/admin/orders/bag-tickets?order_ids=${item.order_id}`"
                      target="_blank"
                      class="btn-row-ticket"
                      title="Open bag ticket"
                    >
                      🖨️ Ticket
                    </a>
                  </div>
                </td>
              </tr>
              <tr v-if="sessionLog.length === 0">
                <td colspan="8" class="empty-state">
                  <div class="empty-box">
                    <span class="empty-icon">📦</span>
                    <p class="empty-title">No parcels scanned in this pickup batch yet.</p>
                    <p class="empty-sub">Aim your barcode scanner at shipping labels or bag tickets to register handovers in real time.</p>
                  </div>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="sessionScannedCount > 0">
              <tr class="tfoot-totals">
                <td colspan="5" class="text-right font-bold">TOTAL MANIFEST VALUE:</td>
                <td class="text-right font-mono font-bold text-success total-cod-cell">
                  ৳{{ formatCurrency(sessionTotalCodBdt) }}
                </td>
                <td colspan="2" class="text-center font-bold">
                  {{ sessionScannedCount }} package(s)
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Recent Handovers History Section -->
      <div v-if="recentHandovers.length > 0" class="recent-card">
        <h3 class="recent-title">🕒 Recently Handed Over Parcels Today</h3>
        <div class="recent-grid">
          <div v-for="rec in recentHandovers" :key="rec.id" class="recent-item">
            <div class="rec-top">
              <Link :href="`/admin/orders/${rec.id}`" class="rec-order font-mono">
                {{ rec.order_number }}
              </Link>
              <span class="rec-courier">{{ rec.courier_code }}</span>
            </div>
            <div class="rec-cust">{{ rec.customer_name }} ({{ rec.customer_phone }})</div>
            <div class="rec-meta">
              <span class="rec-cod">COD: ৳{{ formatCurrency(rec.payable_cod_amount_bdt) }}</span>
              <span class="rec-time">{{ rec.shipped_at }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.scan-handover-page {
  padding: $space-6;
  max-width: 1400px;
  margin: 0 auto;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: $space-5;

    .left-col {
      display: flex;
      align-items: center;
      gap: $space-4;

      .page-title {
        font-size: $fs-2xl;
        font-weight: 800;
        color: $color-text-main;
      }
      .page-subtitle {
        font-size: $fs-sm;
        color: $color-text-muted;
        margin-top: 2px;
      }
    }

    .header-tools {
      display: flex;
      align-items: center;
      gap: $space-2;

      .tool-btn {
        padding: 6px 12px;
        border-radius: $radius-md;
        font-size: $fs-xs;
        font-weight: 700;
        border: 1px solid $color-border;
        background: #ffffff;
        cursor: pointer;
        transition: all 0.15s ease-in-out;

        &.is-active {
          background: #fdf2f8;
          border-color: #fbcfe8;
          color: #db2777;
        }

        &:hover {
          border-color: #f472b6;
        }
      }
    }
  }

  .dispatch-config-bar {
    display: flex;
    align-items: center;
    gap: $space-4;
    background: #ffffff;
    border: 1.5px solid #fbcfe8;
    border-radius: $radius-lg;
    padding: $space-4;
    margin-bottom: $space-5;
    box-shadow: 0 2px 6px rgba(219, 39, 119, 0.05);

    .config-item {
      display: flex;
      flex-direction: column;
      gap: 4px;

      &.flex-1 {
        flex: 1;
      }

      &.checkbox-item {
        justify-content: center;
        padding-top: 14px;
      }

      .config-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #9d174d;
      }

      .config-select {
        padding: 8px 12px;
        border: 1.5px solid #fbcfe8;
        border-radius: $radius-md;
        font-size: $fs-sm;
        font-weight: 700;
        color: #831843;
        background: #ffffff;
        outline: none;

        &:focus {
          border-color: #db2777;
          box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.2);
        }
      }

      .config-input {
        padding: 8px 12px;
        border: 1.5px solid #fbcfe8;
        border-radius: $radius-md;
        font-size: $fs-sm;
        outline: none;

        &:focus {
          border-color: #db2777;
          box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.2);
        }
      }

      .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: $fs-xs;
        font-weight: 700;
        color: #831843;
        cursor: pointer;

        .config-checkbox {
          width: 16px;
          height: 16px;
          accent-color: #db2777;
        }
      }
    }
  }

  .metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: $space-4;
    margin-bottom: $space-5;

    .metric-card {
      background: #ffffff;
      border: 1.5px solid #fbcfe8;
      border-radius: $radius-lg;
      padding: $space-4;
      display: flex;
      align-items: center;
      gap: $space-3;
      box-shadow: 0 2px 6px rgba(219, 39, 119, 0.05);

      .metric-icon {
        font-size: 28px;
        line-height: 1;
      }

      .metric-num {
        font-size: 22px;
        font-weight: 800;
        line-height: 1.2;
      }

      .metric-label {
        font-size: 11px;
        color: $color-text-muted;
        font-weight: 600;
        text-transform: uppercase;
      }

      &.card-session .metric-num { color: #db2777; }
      &.card-cod .metric-num { color: #16a34a; }
      &.card-today .metric-num { color: #be185d; }
      &.card-pending .metric-num { color: #d97706; }
    }
  }

  .scanner-card {
    background: linear-gradient(135deg, #fff5f7 0%, #fdf2f8 50%, #fce7f3 100%);
    border: 2px solid #fbcfe8;
    border-radius: $radius-xl;
    padding: $space-6;
    margin-bottom: $space-5;
    color: #831843;
    box-shadow: 0 12px 30px -5px rgba(219, 39, 119, 0.12), 0 4px 10px -2px rgba(219, 39, 119, 0.05);
    position: relative;
    overflow: hidden;

    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #ec4899 0%, #db2777 50%, #f43f5e 100%);
    }

    .input-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: $space-3;

      .pulse-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.7);

        &.is-busy {
          background: #eab308;
          box-shadow: 0 0 10px rgba(234, 179, 8, 0.7);
        }
      }

      .input-title {
        font-size: $fs-base;
        font-weight: 800;
        color: #831843;
        letter-spacing: -0.2px;
      }
    }

    .scanner-input-wrap {
      display: flex;
      gap: $space-3;

      .barcode-input {
        flex: 1;
        padding: 14px 18px;
        background: #ffffff;
        border: 2px solid #f472b6;
        border-radius: $radius-lg;
        color: #831843;
        font-size: 18px;
        font-weight: 800;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        letter-spacing: 0.5px;
        outline: none;
        box-shadow: 0 2px 6px rgba(219, 39, 119, 0.06);
        transition: all 0.2s;

        &:focus {
          border-color: #db2777;
          background: #ffffff;
          box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.2), 0 4px 12px rgba(219, 39, 119, 0.12);
        }

        &::placeholder {
          color: #9ca3af;
          font-weight: 400;
          font-size: 15px;
        }
      }

      .btn-submit-scan {
        padding: 0 24px;
        font-size: $fs-sm;
        font-weight: 800;
        background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        color: #ffffff;
        border: none;
        border-radius: $radius-lg;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(219, 39, 119, 0.35);
        transition: all 0.2s ease;

        &:hover:not(:disabled) {
          background: linear-gradient(135deg, #db2777 0%, #be185d 100%);
          transform: translateY(-1px);
          box-shadow: 0 6px 16px rgba(219, 39, 119, 0.45);
        }

        &:disabled {
          opacity: 0.5;
          cursor: not-allowed;
          box-shadow: none;
        }
      }
    }

    .input-tip {
      font-size: 12px;
      color: #9d174d;
      font-weight: 600;
      margin-top: $space-2;

      code {
        color: #be185d;
        background: #fdf2f8;
        border: 1px solid #fbcfe8;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 700;
      }
    }
  }

  .result-banner {
    display: flex;
    align-items: flex-start;
    gap: $space-4;
    padding: $space-4;
    border-radius: $radius-lg;
    margin-bottom: $space-5;
    animation: slideDown 0.2s ease-out;

    &.is-success {
      background: #f0fdf4;
      border: 2px solid #86efac;
      color: #166534;
    }

    &.is-duplicate {
      background: #fefce8;
      border: 2px solid #fde047;
      color: #854d0e;
    }

    &.is-error {
      background: #fef2f2;
      border: 2px solid #fca5a5;
      color: #991b1b;
    }

    .result-icon-col {
      font-size: 28px;
      line-height: 1;
    }

    .result-body {
      flex: 1;

      .result-headline {
        font-size: $fs-base;
        font-weight: 800;
        margin-bottom: $space-2;
      }

      .order-preview-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px 16px;
        background: rgba(255, 255, 255, 0.7);
        padding: 10px 14px;
        border-radius: $radius-md;
        font-size: $fs-xs;

        .preview-item {
          display: flex;
          align-items: center;
          gap: 6px;

          .preview-lbl {
            font-weight: 700;
            opacity: 0.8;
          }
        }
      }
    }
  }

  .manifest-card {
    background: #ffffff;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    margin-bottom: $space-6;

    .manifest-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: $space-4 $space-6;
      border-bottom: 1px solid $color-border;

      .manifest-title-wrap {
        display: flex;
        align-items: center;
        gap: $space-3;

        .card-title {
          font-size: $fs-lg;
          font-weight: 800;
          color: $color-text-main;
        }

        .manifest-count-pill {
          background: #eff6ff;
          color: #1d4ed8;
          font-size: 11px;
          font-weight: 700;
          padding: 3px 8px;
          border-radius: $radius-full;
        }
      }

      .manifest-actions {
        display: flex;
        align-items: center;
        gap: $space-2;

        .btn-manifest-print {
          background: #0f172a;
          color: #ffffff;
          border: none;
          padding: 8px 16px;
          border-radius: $radius-md;
          font-size: $fs-xs;
          font-weight: 700;
          cursor: pointer;
          transition: background 0.2s;

          &:hover:not(:disabled) {
            background: #1e293b;
          }

          &:disabled {
            opacity: 0.5;
            cursor: not-allowed;
          }
        }

        .btn-danger-outline {
          background: #fff;
          border: 1px solid #fca5a5;
          color: #b91c1c;
          font-weight: 700;
          border-radius: $radius-md;
          padding: 6px 12px;
          cursor: pointer;

          &:hover {
            background: #fef2f2;
          }
        }
      }
    }

    .table-container {
      overflow-x: auto;
    }

    .manifest-table {
      width: 100%;
      border-collapse: collapse;
      font-size: $fs-sm;

      th {
        background: #f8fafc;
        padding: 10px 14px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: $color-text-muted;
        border-bottom: 1px solid $color-border;
      }

      td {
        padding: 12px 14px;
        border-bottom: 1px solid $color-border;
        vertical-align: middle;
      }

      .manifest-row:hover {
        background: #f8fafc;
      }

      .order-link {
        color: $color-primary;
        text-decoration: none;

        &:hover {
          text-decoration: underline;
        }
      }

      .sub-qty {
        font-size: 11px;
      }

      .courier-pill {
        display: inline-block;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: $radius-sm;

        &.courier-steadfast { background: #e0f2fe; color: #0369a1; }
        &.courier-pathao { background: #fee2e2; color: #b91c1c; }
        &.courier-redx { background: #ffedd5; color: #c2410c; }
      }

      .tracking-text {
        font-size: 11px;
        color: $color-text-muted;
        margin-top: 2px;
      }

      .dest-text {
        font-size: $fs-xs;
        font-weight: 600;
      }

      .row-actions {
        display: flex;
        justify-content: flex-end;
        gap: 6px;

        .btn-row-label,
        .btn-row-ticket {
          display: inline-block;
          font-size: 11px;
          font-weight: 700;
          padding: 4px 8px;
          border-radius: $radius-sm;
          text-decoration: none;
          border: 1px solid $color-border;
          color: $color-text-main;
          background: #ffffff;

          &:hover {
            border-color: $color-primary;
            color: $color-primary;
          }
        }
      }

      .empty-state {
        text-align: center;
        padding: $space-8;

        .empty-icon {
          font-size: 40px;
          display: block;
          margin-bottom: $space-2;
        }

        .empty-title {
          font-size: $fs-base;
          font-weight: 700;
          color: $color-text-main;
        }

        .empty-sub {
          font-size: $fs-xs;
          color: $color-text-muted;
          margin-top: 4px;
        }
      }

      .tfoot-totals {
        background: #f1f5f9;

        td {
          padding: 12px 14px;
          font-size: 13px;
        }

        .total-cod-cell {
          font-size: 15px;
        }
      }
    }
  }

  .recent-card {
    background: #ffffff;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    padding: $space-5;

    .recent-title {
      font-size: $fs-sm;
      font-weight: 700;
      text-transform: uppercase;
      color: $color-text-muted;
      margin-bottom: $space-4;
    }

    .recent-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: $space-3;

      .recent-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: $radius-md;
        padding: $space-3;
        font-size: $fs-xs;

        .rec-top {
          display: flex;
          justify-content: space-between;
          margin-bottom: 4px;

          .rec-order {
            font-weight: 700;
            color: $color-primary;
            text-decoration: none;

            &:hover {
              text-decoration: underline;
            }
          }

          .rec-courier {
            font-weight: 800;
            font-size: 10px;
            color: #64748b;
          }
        }

        .rec-cust {
          font-weight: 600;
          color: $color-text-main;
          margin-bottom: 4px;
        }

        .rec-meta {
          display: flex;
          justify-content: space-between;
          color: $color-text-muted;
          font-size: 11px;

          .rec-cod {
            font-weight: 700;
            color: #16a34a;
          }
        }
      }
    }
  }
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
