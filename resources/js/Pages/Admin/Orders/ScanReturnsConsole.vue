<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

interface StatsProps {
  today_returned_count: number;
  total_returned_count: number;
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
  total_bdt: string;
  payable_cod_amount_bdt: string;
  items_count: number;
  items: OrderItemPayload[];
  scanned_at: string;
}

interface RecentReturn {
  id: number;
  order_number: string;
  customer_name: string;
  customer_phone: string;
  courier_code: string;
  courier_tracking_code: string | null;
  courier_consignment_id: string | null;
  total_bdt: string;
  items_count: number;
  returned_at: string;
}

interface SessionLogItem {
  id: string;
  time: string;
  order_number: string;
  courier_code: string;
  tracking_code: string;
  customer_name: string;
  customer_phone: string;
  items_count: number;
  status: 'success' | 'duplicate' | 'error';
  message: string;
  order_id?: number;
}

const props = defineProps<{
  stats: StatsProps;
  recentReturns: RecentReturn[];
}>();

// Barcode input state
const barcodeInput = ref('');
const scannerInputRef = ref<HTMLInputElement | null>(null);
const isProcessing = ref(false);
const autoLockFocus = ref(true);
const soundEnabled = ref(true);
const selectedReason = ref('Customer refused delivery / unreachable');

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
const sessionScannedCount = ref(0);
const sessionRestockedUnits = ref(0);
const sessionLog = ref<SessionLogItem[]>([]);

// Blacklist modal / action state
const isBlacklisting = ref(false);
const blacklistSuccess = ref<string | null>(null);

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
    osc.frequency.setValueAtTime(260, now);
    osc.frequency.setValueAtTime(180, now + 0.12);

    gain.gain.setValueAtTime(0.25, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);

    osc.connect(gain);
    gain.connect(audioCtx.destination);

    osc.start(now);
    osc.stop(now + 0.4);
  } catch (e) {
    console.warn('Audio warning buzz failed:', e);
  }
};

// Auto Focus management
const refocusScanner = () => {
  if (autoLockFocus.value) {
    nextTick(() => {
      scannerInputRef.value?.focus();
    });
  }
};

const handleWindowClick = (e: MouseEvent) => {
  // If user clicked inside an input, button, or link, don't steal focus
  const target = e.target as HTMLElement | null;
  if (target && (target.tagName === 'INPUT' || target.tagName === 'BUTTON' || target.tagName === 'A' || target.tagName === 'SELECT')) {
    return;
  }
  refocusScanner();
};

onMounted(() => {
  refocusScanner();
  window.addEventListener('click', handleWindowClick);
});

onUnmounted(() => {
  window.removeEventListener('click', handleWindowClick);
});

// Process Barcode Scan
const processBarcode = async () => {
  const code = barcodeInput.value.trim();
  if (!code || isProcessing.value) return;

  isProcessing.value = true;
  blacklistSuccess.value = null;

  try {
    const res = await axios.post('/admin/orders/scan-returns/process', {
      barcode: code,
      notes: selectedReason.value,
    });

    const data = res.data;

    if (data.success && data.order) {
      // 1. Success
      playSuccessChime();
      currentResult.value = {
        status: 'success',
        message: data.message,
        order: data.order,
        rawBarcode: code,
      };

      sessionScannedCount.value++;
      sessionRestockedUnits.value += Number(data.order.items_count || 0);

      sessionLog.value.unshift({
        id: `${Date.now()}-${code}`,
        time: data.order.scanned_at || new Date().toLocaleTimeString(),
        order_number: data.order.order_number,
        courier_code: data.order.courier_code,
        tracking_code: data.order.courier_tracking_code || code,
        customer_name: data.order.customer_name,
        customer_phone: data.order.customer_phone,
        items_count: data.order.items_count,
        status: 'success',
        message: data.message,
        order_id: data.order.id,
      });
    } else if (data.already_returned && data.order) {
      // 2. Duplicate Scan
      playWarningBuzz();
      currentResult.value = {
        status: 'duplicate',
        message: data.message,
        order: data.order,
        rawBarcode: code,
      };

      sessionLog.value.unshift({
        id: `${Date.now()}-${code}`,
        time: new Date().toLocaleTimeString(),
        order_number: data.order.order_number,
        courier_code: data.order.courier_code,
        tracking_code: data.order.courier_tracking_code || code,
        customer_name: data.order.customer_name,
        customer_phone: data.order.customer_phone,
        items_count: data.order.items_count,
        status: 'duplicate',
        message: data.message,
        order_id: data.order.id,
      });
    } else {
      // 3. Not Found or unallowed transition
      playWarningBuzz();
      currentResult.value = {
        status: 'error',
        message: data.message || `No order found matching barcode [${code}].`,
        rawBarcode: code,
      };

      sessionLog.value.unshift({
        id: `${Date.now()}-${code}`,
        time: new Date().toLocaleTimeString(),
        order_number: 'N/A',
        courier_code: 'N/A',
        tracking_code: code,
        customer_name: 'Unknown',
        customer_phone: 'N/A',
        items_count: 0,
        status: 'error',
        message: data.message || `Barcode [${code}] not found.`,
      });
    }
  } catch (err: any) {
    playWarningBuzz();
    const errMsg = err.response?.data?.message || `Failed to process barcode [${code}]. Check network connection.`;
    currentResult.value = {
      status: 'error',
      message: errMsg,
      rawBarcode: code,
    };

    sessionLog.value.unshift({
      id: `${Date.now()}-${code}`,
      time: new Date().toLocaleTimeString(),
      order_number: 'N/A',
      courier_code: 'N/A',
      tracking_code: code,
      customer_name: 'Unknown',
      customer_phone: 'N/A',
      items_count: 0,
      status: 'error',
      message: errMsg,
    });
  } finally {
    isProcessing.value = false;
    barcodeInput.value = '';
    refocusScanner();
  }
};

// 1-Click Blacklist Fake / Serial Return Customer Phone
const blacklistCustomer = (phone: string, name: string, orderNumber: string) => {
  if (!phone || isBlacklisting.value) return;

  if (!confirm(`Are you sure you want to BLACKLIST customer phone [${phone}] from placing future Cash-on-Delivery orders?`)) {
    return;
  }

  isBlacklisting.value = true;
  router.post(
    '/admin/fraud-directory',
    {
      type: 'blacklist',
      phone_number: phone,
      customer_name: name,
      reason: `Courier Return on order [${orderNumber}]: Parcel refused / returned to sender.`,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        blacklistSuccess.value = `Phone [${phone}] successfully added to Merchant Blacklist.`;
        isBlacklisting.value = false;
      },
      onError: () => {
        isBlacklisting.value = false;
      },
    }
  );
};

const formatCurrency = (val: string | number) => {
  return Number(val || 0).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
  <AdminLayout>
    <Head title="Scan Courier Returns & Inward Console - Admin" />

    <div class="scan-returns-page">
      <!-- Breadcrumb & Top Header -->
      <div class="top-nav-bar">
        <div class="breadcrumb">
          <Link href="/admin/orders" class="breadcrumb-link">🛍️ Orders & Anti-Fraud</Link>
          <span class="breadcrumb-separator">/</span>
          <span class="breadcrumb-current">📷 Scan Courier Returns</span>
        </div>

        <div class="header-right-actions">
          <button
            type="button"
            class="sound-toggle-btn"
            :class="{ 'is-muted': !soundEnabled }"
            :title="soundEnabled ? 'Audio chimes enabled' : 'Audio chimes muted'"
            @click="soundEnabled = !soundEnabled"
          >
            <span v-if="soundEnabled">🔊 Sound On</span>
            <span v-else>🔇 Sound Muted</span>
          </button>

          <Link href="/admin/orders?status=returned" class="btn btn-outline btn-sm">
            🔄 View All Returned Orders ({{ stats.total_returned_count }})
          </Link>
        </div>
      </div>

      <!-- Main Headline & Shift KPI Cards -->
      <div class="page-header-row">
        <div>
          <h1 class="page-title">📷 Courier Return Parcel Inward Console</h1>
          <p class="page-subtitle">
            Scan courier tracking barcodes, airwaybills, or bag tickets to immediately receive returns, verify items, and automatically restock physical inventory into the warehouse.
          </p>
        </div>

        <!-- Metrics Counters -->
        <div class="stats-pills-row">
          <div class="stat-pill">
            <span class="stat-label">Restocked This Shift</span>
            <span class="stat-value text-accent">{{ sessionScannedCount }} <small class="text-xs font-normal">parcels</small></span>
          </div>
          <div class="stat-pill">
            <span class="stat-label">Items Restocked</span>
            <span class="stat-value text-emerald">+{{ sessionRestockedUnits }} <small class="text-xs font-normal">units</small></span>
          </div>
          <div class="stat-pill">
            <span class="stat-label">Total Returned Today</span>
            <span class="stat-value">{{ stats.today_returned_count + sessionScannedCount }}</span>
          </div>
        </div>
      </div>

      <!-- SCANNER DEDICATED CONSOLE -->
      <div class="scanner-console-card">
        <div class="scanner-header">
          <div class="scanner-status-indicator">
            <span class="pulse-dot"></span>
            <span class="status-text">Scanner Ready (Auto-Detect USB/Bluetooth/Laser)</span>
          </div>

          <div class="scanner-options">
            <label class="focus-lock-toggle">
              <input type="checkbox" v-model="autoLockFocus" />
              <span>Lock Focus to Scanner</span>
            </label>
          </div>
        </div>

        <div class="scanner-input-container">
          <div class="scanner-icon-wrap">
            <span class="barcode-laser-icon">⚡</span>
          </div>
          <input
            ref="scannerInputRef"
            v-model="barcodeInput"
            type="text"
            class="scanner-barcode-input"
            placeholder="Scan barcode with handheld laser or type tracking code & press Enter..."
            autofocus
            autocomplete="off"
            :disabled="isProcessing"
            @keydown.enter.prevent="processBarcode"
          />
          <button
            type="button"
            class="btn-scanner-submit"
            :disabled="isProcessing || !barcodeInput.trim()"
            @click="processBarcode"
          >
            <span v-if="isProcessing" class="spinner">⏳</span>
            <span v-else>Receive & Restock ↵</span>
          </button>
        </div>

        <div class="scanner-meta-bar">
          <div class="return-reason-selector">
            <label class="reason-label">Return Reason Note:</label>
            <select v-model="selectedReason" class="reason-select">
              <option value="Customer refused delivery / unreachable">Customer refused delivery / unreachable</option>
              <option value="Customer cancelled at door">Customer cancelled at door</option>
              <option value="Fake order / prank number">Fake order / prank number</option>
              <option value="Address unreachable / phone switched off">Address unreachable / phone switched off</option>
              <option value="Damaged in transit by courier">Damaged in transit by courier</option>
              <option value="Wrong item delivered">Wrong item delivered</option>
              <option value="Customer requested return & refund">Customer requested return & refund</option>
            </select>
          </div>

          <div class="scanner-hints">
            <span>💡 Supports: <strong>Steadfast / Pathao Tracking Barcode</strong>, <strong>Order Number (ORD-...)</strong>, or <strong>Bag Ticket Code128</strong></span>
          </div>
        </div>
      </div>

      <!-- SCAN RESULT BANNER -->
      <div v-if="currentResult.status !== 'idle'" class="result-banner-wrap">
        <!-- SUCCESS STATE -->
        <div v-if="currentResult.status === 'success' && currentResult.order" class="result-card result-success">
          <div class="result-badge-col">
            <div class="success-icon-badge">✓</div>
            <span class="success-tag">PARCEL RESTOCKED</span>
          </div>

          <div class="result-body-col">
            <div class="result-top-info">
              <div class="order-ident">
                <span class="order-number-title">{{ currentResult.order.order_number }}</span>
                <span class="courier-badge">{{ currentResult.order.courier_code }}</span>
                <span v-if="currentResult.order.courier_tracking_code" class="tracking-pill font-mono">
                  Tracking: {{ currentResult.order.courier_tracking_code }}
                </span>
              </div>
              <div class="scan-time-pill">
                Scanned at {{ currentResult.order.scanned_at }}
              </div>
            </div>

            <p class="result-message-text">{{ currentResult.message }}</p>

            <!-- Restocked Items List -->
            <div class="restocked-items-box">
              <div class="items-header">
                <span>Restocked Physical Inventory ({{ currentResult.order.items_count }} Units):</span>
                <span class="cogs-badge">Warehouse Inward GRN Updated</span>
              </div>
              <div class="items-grid">
                <div v-for="item in currentResult.order.items" :key="item.id" class="restock-item-pill">
                  <span class="item-name">{{ item.product_name }}</span>
                  <span class="item-sku font-mono">SKU: {{ item.sku }}</span>
                  <span class="item-qty-badge">+{{ item.quantity }} Restocked</span>
                </div>
              </div>
            </div>

            <!-- Customer & Quick Anti-Fraud Action -->
            <div class="result-actions-row">
              <div class="customer-info-snippet">
                <span>Customer: <strong>{{ currentResult.order.customer_name }}</strong></span>
                <span class="phone-link">📞 {{ currentResult.order.customer_phone }}</span>
                <span>Amount: <strong>৳{{ formatCurrency(currentResult.order.payable_cod_amount_bdt) }}</strong></span>
              </div>

              <div class="action-buttons-group">
                <button
                  type="button"
                  class="btn btn-blacklist-action"
                  :disabled="isBlacklisting"
                  title="Add this customer phone number to merchant blacklist"
                  @click="blacklistCustomer(currentResult.order.customer_phone, currentResult.order.customer_name, currentResult.order.order_number)"
                >
                  <span v-if="isBlacklisting">⏳ Saving...</span>
                  <span v-else>🛡️ Blacklist Customer Phone</span>
                </button>

                <Link
                  :href="`/admin/orders/${currentResult.order.id}`"
                  class="btn btn-outline btn-sm"
                  target="_blank"
                >
                  View Order Details ↗
                </Link>
              </div>
            </div>

            <div v-if="blacklistSuccess" class="blacklist-success-alert">
              ✓ {{ blacklistSuccess }}
            </div>
          </div>
        </div>

        <!-- DUPLICATE SCAN STATE -->
        <div v-else-if="currentResult.status === 'duplicate'" class="result-card result-warning">
          <div class="result-badge-col warning-col">
            <div class="warning-icon-badge">⚠️</div>
            <span class="warning-tag">ALREADY RETURNED</span>
          </div>

          <div class="result-body-col">
            <div class="result-top-info">
              <span class="order-number-title">{{ currentResult.order?.order_number || currentResult.rawBarcode }}</span>
              <span class="duplicate-alert-badge">Duplicate Scan Prevented</span>
            </div>
            <p class="result-message-text text-amber">{{ currentResult.message }}</p>
            <p class="text-xs text-muted">
              Inventory was already replenished for this order previously. No duplicate stock was added.
            </p>
          </div>
        </div>

        <!-- ERROR / NOT FOUND STATE -->
        <div v-else-if="currentResult.status === 'error'" class="result-card result-error">
          <div class="result-badge-col error-col">
            <div class="error-icon-badge">✕</div>
            <span class="error-tag">NOT FOUND</span>
          </div>

          <div class="result-body-col">
            <div class="result-top-info">
              <span class="order-number-title font-mono">Barcode: {{ currentResult.rawBarcode }}</span>
              <span class="error-alert-badge">Lookup Failed</span>
            </div>
            <p class="result-message-text text-rose">{{ currentResult.message }}</p>
            <p class="text-xs text-muted">
              Please check if the courier barcode was scanned properly or search for the order manually in Orders dashboard.
            </p>
          </div>
        </div>
      </div>

      <!-- WORKSPACE TWO COLUMNS: CURRENT SESSION LOG & RECENT RETURNS ARCHIVE -->
      <div class="scan-history-grid">
        <!-- Shift Activity Log -->
        <div class="history-card">
          <div class="card-header">
            <div>
              <h2 class="card-title">📋 This Shift's Scanned Parcels ({{ sessionLog.length }})</h2>
              <p class="card-subtitle">Real-time log of returns received and restocked during this scanner session.</p>
            </div>
            <button
              v-if="sessionLog.length > 0"
              type="button"
              class="btn-text-clear"
              @click="sessionLog = []"
            >
              Clear Log
            </button>
          </div>

          <div v-if="sessionLog.length === 0" class="empty-log-state">
            <span class="empty-icon">📦</span>
            <p>No parcels scanned in this session yet.</p>
            <small>Point your barcode scanner at a courier tracking label to begin.</small>
          </div>

          <div v-else class="log-table-wrap">
            <table class="session-table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Order #</th>
                  <th>Courier / Tracking</th>
                  <th>Customer</th>
                  <th>Restocked</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sessionLog" :key="item.id" :class="`row-status-${item.status}`">
                  <td class="font-mono text-xs">{{ item.time }}</td>
                  <td class="font-mono font-bold">{{ item.order_number }}</td>
                  <td>
                    <div class="courier-tracking-cell">
                      <span class="courier-tag">{{ item.courier_code }}</span>
                      <span class="tracking-code font-mono text-xs">{{ item.tracking_code }}</span>
                    </div>
                  </td>
                  <td>
                    <div class="cust-cell">
                      <span>{{ item.customer_name }}</span>
                      <small class="text-muted">{{ item.customer_phone }}</small>
                    </div>
                  </td>
                  <td>
                    <span v-if="item.status === 'success'" class="units-badge text-emerald">+{{ item.items_count }} items</span>
                    <span v-else class="units-badge text-muted">0 items</span>
                  </td>
                  <td>
                    <span v-if="item.status === 'success'" class="badge-status-tag tag-success">Restocked ✓</span>
                    <span v-else-if="item.status === 'duplicate'" class="badge-status-tag tag-warning">Duplicate ⚠️</span>
                    <span v-else class="badge-status-tag tag-error">Failed ✕</span>
                  </td>
                  <td>
                    <Link
                      v-if="item.order_id"
                      :href="`/admin/orders/${item.order_id}`"
                      class="btn-table-view"
                      target="_blank"
                    >
                      View ↗
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Warehouse Returns Guide & Recent Archive -->
        <div class="guide-sidebar">
          <!-- Hardware Scanner Guide Card -->
          <div class="guide-card">
            <h3 class="guide-title">🔌 Handheld Scanner Integration</h3>
            <ul class="guide-list">
              <li>
                <strong>Plug & Play Compatibility</strong>: Works instantly with all USB, 2.4G wireless, and Bluetooth laser / 2D barcode scanners (Honeywell, Zebra, Netum, Datalogic, Eyoyo).
              </li>
              <li>
                <strong>Supported Barcode Formats</strong>:
                <ul class="sub-list">
                  <li>Steadfast Courier Airwaybill / Tracking barcode</li>
                  <li>Pathao Consignment barcode</li>
                  <li>RedX / Paperfly tracking label</li>
                  <li>In-Bag Packing Ticket Code128 barcode</li>
                </ul>
              </li>
              <li>
                <strong>Scanner Configuration</strong>: Ensure your scanner is set to append a <code>CR / Enter</code> suffix (the default factory setting on 99% of scanners).
              </li>
              <li>
                <strong>Automated Restocking</strong>: When a return is received, physical stock is atomically restored in MySQL warehouse inventory to keep stock counts 100% accurate.
              </li>
            </ul>
          </div>

          <!-- Previously Returned Archive List -->
          <div class="recent-archive-card">
            <div class="archive-header">
              <h3 class="archive-title">Recent Returns Received</h3>
              <Link href="/admin/orders?status=returned" class="archive-link">View All ({{ stats.total_returned_count }}) →</Link>
            </div>

            <div v-if="recentReturns.length === 0" class="empty-archive">
              No returned orders recorded yet.
            </div>

            <div v-else class="archive-list">
              <div v-for="order in recentReturns" :key="order.id" class="archive-item">
                <div class="archive-item-top">
                  <Link :href="`/admin/orders/${order.id}`" class="order-link font-mono">
                    {{ order.order_number }}
                  </Link>
                  <span class="archive-time">{{ order.returned_at }}</span>
                </div>
                <div class="archive-item-bottom">
                  <span>{{ order.customer_name }} ({{ order.courier_code }})</span>
                  <span class="archive-amount">৳{{ formatCurrency(order.total_bdt) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.scan-returns-page {
  padding: $space-6;
  max-width: 1400px;
  margin: 0 auto;

  .top-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $space-4;
    flex-wrap: wrap;
    gap: $space-3;

    .breadcrumb {
      display: flex;
      align-items: center;
      gap: $space-2;
      font-size: $fs-sm;
      color: $color-text-muted;

      .breadcrumb-link {
        color: $color-primary;
        text-decoration: none;
        font-weight: 600;

        &:hover {
          text-decoration: underline;
        }
      }

      .breadcrumb-current {
        color: $color-text-main;
        font-weight: 700;
      }
    }

    .header-right-actions {
      display: flex;
      align-items: center;
      gap: $space-3;

      .sound-toggle-btn {
        background: #fdf2f8;
        border: 1px solid #fbcfe8;
        color: #9d174d;
        padding: 6px 14px;
        border-radius: $radius-md;
        font-size: $fs-xs;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;

        &:hover {
          background: #fce7f3;
          border-color: #f472b6;
        }

        &.is-muted {
          background: #fef2f2;
          border-color: #fecaca;
          color: #dc2626;
        }
      }
    }
  }

  .page-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: $space-6;
    flex-wrap: wrap;
    gap: $space-4;

    .page-title {
      font-size: $fs-2xl;
      font-weight: 800;
      color: $color-text-main;
      margin: 0;
    }

    .page-subtitle {
      font-size: $fs-sm;
      color: $color-text-muted;
      margin-top: $space-1;
      max-width: 700px;
    }

    .stats-pills-row {
      display: flex;
      gap: $space-3;
      flex-wrap: wrap;

      .stat-pill {
        background: #ffffff;
        border: 1.5px solid #fbcfe8;
        border-radius: $radius-lg;
        padding: 10px 18px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 6px rgba(219, 39, 119, 0.05);

        .stat-label {
          font-size: $fs-xs;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          color: #9d174d;
          font-weight: 700;
        }

        .stat-value {
          font-size: $fs-xl;
          font-weight: 800;
          color: $color-text-main;
          margin-top: 2px;

          &.text-accent {
            color: #db2777;
          }

          &.text-emerald {
            color: #059669;
          }
        }
      }
    }
  }

  // SCANNER CONSOLE CARD (Refined Premium Pink Theme)
  .scanner-console-card {
    background: linear-gradient(135deg, #fff5f7 0%, #fdf2f8 50%, #fce7f3 100%);
    border: 2px solid #fbcfe8;
    border-radius: $radius-xl;
    padding: $space-6;
    color: #831843;
    box-shadow: 0 12px 30px -5px rgba(219, 39, 119, 0.12), 0 4px 10px -2px rgba(219, 39, 119, 0.05);
    margin-bottom: $space-6;
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

    .scanner-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: $space-4;
      flex-wrap: wrap;
      gap: $space-2;

      .scanner-status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 4px 12px;
        border-radius: 9999px;
        box-shadow: 0 1px 2px rgba(16, 185, 129, 0.08);

        .pulse-dot {
          width: 8px;
          height: 8px;
          background: #10b981;
          border-radius: 50%;
          box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
          animation: pulse 1.5s infinite;
        }

        .status-text {
          font-size: $fs-xs;
          font-weight: 800;
          color: #065f46;
          letter-spacing: 0.02em;
        }
      }

      .focus-lock-toggle {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: $fs-xs;
        color: #9d174d;
        font-weight: 700;
        cursor: pointer;

        input {
          cursor: pointer;
          accent-color: #db2777;
          width: 15px;
          height: 15px;
        }
      }
    }

    .scanner-input-container {
      display: flex;
      gap: $space-3;
      align-items: center;
      background: #ffffff;
      border: 2px solid #f472b6;
      border-radius: $radius-lg;
      padding: 6px 10px 6px 14px;
      box-shadow: 0 2px 8px rgba(219, 39, 119, 0.08);
      transition: all 0.2s ease;

      &:focus-within {
        border-color: #db2777;
        box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.2), 0 4px 12px rgba(219, 39, 119, 0.12);
        background: #ffffff;
      }

      .scanner-icon-wrap {
        font-size: 24px;
        padding-left: 2px;
      }

      .scanner-barcode-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: #831843;
        font-size: 20px;
        font-family: monospace;
        font-weight: 800;
        letter-spacing: 0.05em;

        &::placeholder {
          color: #9ca3af;
          font-family: sans-serif;
          font-weight: 500;
          font-size: $fs-sm;
          letter-spacing: normal;
        }
      }

      .btn-scanner-submit {
        background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        color: #ffffff;
        border: none;
        border-radius: $radius-md;
        padding: 12px 24px;
        font-size: $fs-sm;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(219, 39, 119, 0.35);
        transition: all 0.2s ease;
        white-space: nowrap;

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

    .scanner-meta-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: $space-4;
      flex-wrap: wrap;
      gap: $space-3;

      .return-reason-selector {
        display: flex;
        align-items: center;
        gap: 8px;

        .reason-label {
          font-size: $fs-xs;
          color: #831843;
          font-weight: 700;
        }

        .reason-select {
          background: #ffffff;
          border: 1.5px solid #fbcfe8;
          color: #831843;
          border-radius: $radius-md;
          padding: 6px 12px;
          font-size: $fs-xs;
          font-weight: 700;
          outline: none;
          cursor: pointer;
          box-shadow: 0 1px 2px rgba(219, 39, 119, 0.05);

          option {
            background: #ffffff;
            color: #831843;
          }

          &:focus {
            border-color: #db2777;
            box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.2);
          }
        }
      }

      .scanner-hints {
        font-size: $fs-xs;
        color: #9d174d;
        font-weight: 600;

        strong {
          color: #831843;
          font-weight: 800;
        }
      }
    }
  }

  // RESULT BANNER
  .result-banner-wrap {
    margin-bottom: $space-6;
    animation: fadeIn 0.3s ease;

    .result-card {
      border-radius: $radius-xl;
      padding: $space-5;
      display: flex;
      gap: $space-5;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);

      &.result-success {
        background: #f0fdf4;
        border: 2px solid #86efac;
      }

      &.result-warning {
        background: #fffbeb;
        border: 2px solid #fde68a;
      }

      &.result-error {
        background: #fef2f2;
        border: 2px solid #fecaca;
      }

      .result-badge-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 110px;

        .success-icon-badge {
          width: 52px;
          height: 52px;
          background: #10b981;
          color: #ffffff;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 28px;
          font-weight: 800;
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .success-tag {
          font-size: 11px;
          font-weight: 800;
          color: #047857;
          margin-top: 8px;
          letter-spacing: 0.05em;
        }

        .warning-icon-badge {
          width: 52px;
          height: 52px;
          background: #f59e0b;
          color: #ffffff;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 26px;
          font-weight: 800;
        }

        .warning-tag {
          font-size: 11px;
          font-weight: 800;
          color: #b45309;
          margin-top: 8px;
        }

        .error-icon-badge {
          width: 52px;
          height: 52px;
          background: #ef4444;
          color: #ffffff;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 26px;
          font-weight: 800;
        }

        .error-tag {
          font-size: 11px;
          font-weight: 800;
          color: #b91c1c;
          margin-top: 8px;
        }
      }

      .result-body-col {
        flex: 1;

        .result-top-info {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: $space-2;
          flex-wrap: wrap;
          gap: $space-2;

          .order-ident {
            display: flex;
            align-items: center;
            gap: $space-2;

            .order-number-title {
              font-size: $fs-lg;
              font-weight: 800;
              color: $color-text-main;
            }

            .courier-badge {
              background: #0f172a;
              color: #ffffff;
              font-size: 11px;
              font-weight: 700;
              padding: 2px 8px;
              border-radius: $radius-sm;
            }

            .tracking-pill {
              background: #e2e8f0;
              color: #334155;
              font-size: $fs-xs;
              padding: 2px 8px;
              border-radius: $radius-sm;
              font-weight: 600;
            }
          }

          .scan-time-pill {
            font-size: $fs-xs;
            color: $color-text-muted;
            font-weight: 600;
          }
        }

        .result-message-text {
          font-size: $fs-sm;
          color: #15803d;
          font-weight: 600;
          margin-bottom: $space-3;

          &.text-amber {
            color: #b45309;
          }

          &.text-rose {
            color: #b91c1c;
          }
        }

        .restocked-items-box {
          background: #ffffff;
          border: 1px solid #bbf7d0;
          border-radius: $radius-lg;
          padding: 10px 14px;
          margin-bottom: $space-3;

          .items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: $fs-xs;
            font-weight: 700;
            color: #166534;
            margin-bottom: 8px;

            .cogs-badge {
              background: #dcfce7;
              padding: 2px 6px;
              border-radius: $radius-sm;
              font-size: 10px;
            }
          }

          .items-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;

            .restock-item-pill {
              background: #f8fafc;
              border: 1px solid #e2e8f0;
              border-radius: $radius-md;
              padding: 6px 10px;
              display: flex;
              align-items: center;
              gap: 8px;
              font-size: $fs-xs;

              .item-name {
                font-weight: 700;
                color: $color-text-main;
              }

              .item-sku {
                color: $color-text-muted;
              }

              .item-qty-badge {
                background: #dcfce7;
                color: #15803d;
                font-weight: 800;
                padding: 2px 6px;
                border-radius: $radius-sm;
              }
            }
          }
        }

        .result-actions-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          flex-wrap: wrap;
          gap: $space-3;

          .customer-info-snippet {
            display: flex;
            gap: $space-3;
            font-size: $fs-xs;
            color: $color-text-main;

            .phone-link {
              font-weight: 600;
              color: $color-primary;
            }
          }

          .action-buttons-group {
            display: flex;
            gap: $space-2;

            .btn-blacklist-action {
              background: #ef4444;
              color: #ffffff;
              border: none;
              padding: 6px 12px;
              border-radius: $radius-md;
              font-size: $fs-xs;
              font-weight: 700;
              cursor: pointer;
              transition: all 0.2s ease;

              &:hover:not(:disabled) {
                background: #dc2626;
              }
            }
          }
        }

        .blacklist-success-alert {
          margin-top: $space-2;
          background: #dbeafe;
          color: #1e40af;
          padding: 6px 12px;
          border-radius: $radius-md;
          font-size: $fs-xs;
          font-weight: 600;
        }
      }
    }
  }

  // TWO COLUMN WORKSPACE
  .scan-history-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: $space-6;

    @media (max-width: 1024px) {
      grid-template-columns: 1fr;
    }

    .history-card {
      background: #ffffff;
      border: 1px solid $color-border;
      border-radius: $radius-xl;
      padding: $space-5;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

      .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: $space-4;

        .card-title {
          font-size: $fs-base;
          font-weight: 800;
          color: $color-text-main;
          margin: 0;
        }

        .card-subtitle {
          font-size: $fs-xs;
          color: $color-text-muted;
          margin-top: 2px;
        }

        .btn-text-clear {
          background: none;
          border: none;
          color: #94a3b8;
          font-size: $fs-xs;
          cursor: pointer;
          font-weight: 600;

          &:hover {
            color: #ef4444;
          }
        }
      }

      .empty-log-state {
        text-align: center;
        padding: $space-10 $space-4;
        color: $color-text-muted;

        .empty-icon {
          font-size: 36px;
          display: block;
          margin-bottom: $space-2;
          opacity: 0.7;
        }

        p {
          font-size: $fs-sm;
          font-weight: 600;
          margin: 0;
        }

        small {
          font-size: $fs-xs;
        }
      }

      .log-table-wrap {
        overflow-x: auto;

        .session-table {
          width: 100%;
          border-collapse: collapse;
          font-size: $fs-xs;

          th {
            text-align: left;
            padding: 8px 10px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
          }

          td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
          }

          .courier-tracking-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;

            .courier-tag {
              font-weight: 700;
              color: $color-text-main;
            }

            .tracking-code {
              color: $color-text-muted;
            }
          }

          .cust-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
          }

          .units-badge {
            font-weight: 700;
          }

          .badge-status-tag {
            padding: 3px 8px;
            border-radius: $radius-sm;
            font-weight: 700;
            font-size: 10px;
            display: inline-block;

            &.tag-success {
              background: #dcfce7;
              color: #15803d;
            }

            &.tag-warning {
              background: #fef3c7;
              color: #b45309;
            }

            &.tag-error {
              background: #fee2e2;
              color: #b91c1c;
            }
          }

          .btn-table-view {
            color: $color-primary;
            text-decoration: none;
            font-weight: 700;

            &:hover {
              text-decoration: underline;
            }
          }
        }
      }
    }

    .guide-sidebar {
      display: flex;
      flex-direction: column;
      gap: $space-5;

      .guide-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: $radius-xl;
        padding: $space-5;

        .guide-title {
          font-size: $fs-sm;
          font-weight: 800;
          color: $color-text-main;
          margin-top: 0;
          margin-bottom: $space-3;
        }

        .guide-list {
          padding-left: $space-4;
          font-size: $fs-xs;
          color: #475569;
          line-height: 1.6;

          li {
            margin-bottom: 8px;
          }

          .sub-list {
            margin-top: 4px;
            padding-left: $space-4;
            color: #64748b;
          }
        }
      }

      .recent-archive-card {
        background: #ffffff;
        border: 1px solid $color-border;
        border-radius: $radius-xl;
        padding: $space-5;

        .archive-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: $space-3;

          .archive-title {
            font-size: $fs-sm;
            font-weight: 800;
            color: $color-text-main;
            margin: 0;
          }

          .archive-link {
            font-size: $fs-xs;
            color: $color-primary;
            font-weight: 700;
            text-decoration: none;

            &:hover {
              text-decoration: underline;
            }
          }
        }

        .empty-archive {
          font-size: $fs-xs;
          color: $color-text-muted;
          text-align: center;
          padding: $space-4 0;
        }

        .archive-list {
          display: flex;
          flex-direction: column;
          gap: 8px;

          .archive-item {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: $radius-md;
            padding: 8px 12px;
            font-size: $fs-xs;

            .archive-item-top {
              display: flex;
              justify-content: space-between;
              align-items: center;

              .order-link {
                color: $color-primary;
                font-weight: 700;
                text-decoration: none;

                &:hover {
                  text-decoration: underline;
                }
              }

              .archive-time {
                color: $color-text-muted;
                font-size: 10px;
              }
            }

            .archive-item-bottom {
              display: flex;
              justify-content: space-between;
              align-items: center;
              margin-top: 4px;
              color: #64748b;

              .archive-amount {
                font-weight: 700;
                color: $color-text-main;
              }
            }
          }
        }
      }
    }
  }
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

