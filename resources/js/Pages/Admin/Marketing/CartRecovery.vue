<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface RecoveryStats {
  total_abandoned_carts: number;
  total_dispatches: number;
  total_recovered_carts: number;
  recovery_rate_percent: number;
  recovered_revenue_bdt: string;
}

interface RecoveryLogItem {
  id: number;
  ulid: string;
  cart_id: number;
  customer_name: string | null;
  customer_phone: string | null;
  customer_email: string | null;
  step: number;
  channel: string;
  status: string;
  incentive_coupon_code: string | null;
  recovered_order_id: number | null;
  dispatched_at: string;
  recovered_at: string | null;
}

interface PaginatedLogs {
  data: RecoveryLogItem[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
}

defineProps<{
  stats: RecoveryStats;
  logs: PaginatedLogs;
}>();

const triggerManualRecovery = () => {
  if (confirm('Run abandoned cart recovery sequence now? This will scan idle carts and dispatch pending step 1/2/3 reminders.')) {
    router.post('/admin/marketing/cart-recovery/trigger', {}, {
      preserveScroll: true,
    });
  }
};
</script>

<template>
  <AdminLayout title="Abandoned Cart Recovery & Sequencer">
    <Head title="Abandoned Cart Recovery & Sequencer" />

    <div class="recovery-page">
      <div class="page-header">
        <div>
          <h1 class="page-title">Abandoned Cart Recovery & Marketing Sequencer</h1>
          <p class="page-subtitle">
            Automated multi-step WhatsApp & SMS reminders re-engaging shoppers at 1h, 6h, and 24h intervals.
          </p>
        </div>
        <div class="header-actions">
          <button type="button" class="btn btn-primary" @click="triggerManualRecovery">
            ⚡ Run Manual Recovery Now
          </button>
        </div>
      </div>

      <!-- KPI Metrics Grid -->
      <div class="kpi-grid">
        <div class="kpi-card card">
          <div class="kpi-header">
            <span class="kpi-title">Abandoned Carts</span>
            <span class="kpi-icon">🛒</span>
          </div>
          <div class="kpi-value">{{ stats.total_abandoned_carts }}</div>
          <div class="kpi-subtext text-muted">Carts idle &gt; 1 hour</div>
        </div>

        <div class="kpi-card card">
          <div class="kpi-header">
            <span class="kpi-title">Reminders Sent</span>
            <span class="kpi-icon">📨</span>
          </div>
          <div class="kpi-value">{{ stats.total_dispatches }}</div>
          <div class="kpi-subtext text-muted">WhatsApp & SMS dispatches</div>
        </div>

        <div class="kpi-card card">
          <div class="kpi-header">
            <span class="kpi-title">Recovered Orders</span>
            <span class="kpi-icon">🎉</span>
          </div>
          <div class="kpi-value text-success">{{ stats.total_recovered_carts }}</div>
          <div class="kpi-subtext text-success font-medium">
            {{ stats.recovery_rate_percent }}% Conversion Rate
          </div>
        </div>

        <div class="kpi-card card">
          <div class="kpi-header">
            <span class="kpi-title">Recovered Revenue</span>
            <span class="kpi-icon">💰</span>
          </div>
          <div class="kpi-value revenue-val">৳{{ stats.recovered_revenue_bdt }}</div>
          <div class="kpi-subtext text-muted">Recaptured sales</div>
        </div>
      </div>

      <!-- Sequence Workflow Explainer -->
      <div class="sequence-pipeline card">
        <div class="card-header">
          <h3 class="card-title">Multi-Step Automated Recovery Funnel</h3>
        </div>
        <div class="card-body">
          <div class="pipeline-steps">
            <div class="funnel-step">
              <span class="step-badge step-1">Step 1</span>
              <h4>1 Hour Elapsed</h4>
              <p>Gentle reminder with direct slide-out cart recovery link.</p>
              <span class="channel-pill">WhatsApp / SMS</span>
            </div>

            <div class="pipeline-arrow">&rarr;</div>

            <div class="funnel-step">
              <span class="step-badge step-2">Step 2</span>
              <h4>6 Hours Elapsed</h4>
              <p>Urgency nudge: "Items reserved for a limited time".</p>
              <span class="channel-pill">WhatsApp / SMS</span>
            </div>

            <div class="pipeline-arrow">&rarr;</div>

            <div class="funnel-step">
              <span class="step-badge step-3">Step 3</span>
              <h4>24 Hours Elapsed</h4>
              <p>Final incentive offer: 5% discount with coupon <code>COMEBACK5</code>.</p>
              <span class="channel-pill">Coupon Included</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Recovery Logs Table -->
      <div class="card logs-card">
        <div class="card-header">
          <h3 class="card-title">Dispatched Recovery Notifications ({{ logs.total }})</h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Dispatched At</th>
                  <th>Customer Name</th>
                  <th>Mobile Phone</th>
                  <th class="text-center">Sequence Step</th>
                  <th class="text-center">Channel</th>
                  <th>Coupon Code</th>
                  <th>Status</th>
                  <th>Recovered Order</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="logs.data.length === 0">
                  <td colspan="8" class="text-center text-muted p-6">
                    No abandoned cart recovery dispatches logged yet.
                  </td>
                </tr>
                <tr v-for="log in logs.data" :key="log.id">
                  <td class="text-muted text-sm">{{ log.dispatched_at }}</td>
                  <td class="font-medium">{{ log.customer_name || 'Guest Shopper' }}</td>
                  <td class="font-mono text-sm">{{ log.customer_phone || '-' }}</td>
                  <td class="text-center">
                    <span :class="['step-pill', `step-${log.step}`]">
                      Step {{ log.step }}
                    </span>
                  </td>
                  <td class="text-center">
                    <span :class="['channel-badge', `channel-${log.channel}`]">
                      {{ log.channel.toUpperCase() }}
                    </span>
                  </td>
                  <td>
                    <code v-if="log.incentive_coupon_code" class="coupon-code">
                      {{ log.incentive_coupon_code }}
                    </code>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>
                    <span :class="['status-pill', log.status === 'recovered' ? 'status-recovered' : 'status-sent']">
                      {{ log.status === 'recovered' ? '🎉 Recovered' : 'Sent' }}
                    </span>
                  </td>
                  <td>
                    <Link
                      v-if="log.recovered_order_id"
                      :href="`/admin/orders/${log.recovered_order_id}`"
                      class="order-link font-mono"
                    >
                      #{{ log.recovered_order_id }}
                    </Link>
                    <span v-else class="text-muted">-</span>
                  </td>
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
.recovery-page {
  padding: $space-6;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
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

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: $space-4;
  margin-bottom: $space-6;

  @media (max-width: $bp-lg) {
    grid-template-columns: repeat(2, 1fr);
  }

  @media (max-width: $bp-sm) {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  padding: $space-5;

  .kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $space-2;

    .kpi-title {
      font-size: $fs-xs;
      text-transform: uppercase;
      font-weight: $fw-bold;
      color: $color-neutral-500;
    }

    .kpi-icon {
      font-size: 1.25rem;
    }
  }

  .kpi-value {
    font-size: $fs-3xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    line-height: $lh-tight;
  }

  .revenue-val {
    color: $color-primary-600;
  }

  .kpi-subtext {
    font-size: $fs-xs;
    margin-top: $space-1;
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

.sequence-pipeline {
  margin-bottom: $space-6;

  .pipeline-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: $space-4;

    @media (max-width: $bp-md) {
      flex-direction: column;
      align-items: stretch;
    }
  }

  .funnel-step {
    flex: 1;
    background: $color-neutral-50;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-md;
    padding: $space-4;
    text-align: center;

    .step-badge {
      display: inline-block;
      font-size: $fs-2xs;
      font-weight: $fw-bold;
      text-transform: uppercase;
      padding: 2px $space-2;
      border-radius: $radius-sm;
      margin-bottom: $space-2;

      &.step-1 { background: $color-secondary-100; color: $color-secondary-800; }
      &.step-2 { background: hsl(38, 92%, 90%); color: hsl(38, 92%, 25%); }
      &.step-3 { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
    }

    h4 {
      font-size: $fs-sm;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 0 0 $space-1 0;
    }

    p {
      font-size: $fs-xs;
      color: $color-neutral-500;
      margin: 0 0 $space-2 0;
      line-height: $lh-normal;
    }

    .channel-pill {
      font-size: $fs-2xs;
      background: $color-neutral-200;
      color: $color-neutral-700;
      padding: 1px $space-2;
      border-radius: $radius-full;
    }
  }

  .pipeline-arrow {
    font-size: 1.5rem;
    color: $color-neutral-400;

    @media (max-width: $bp-md) {
      transform: rotate(90deg);
      text-align: center;
    }
  }
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

  .step-pill {
    font-size: $fs-2xs;
    padding: 2px $space-2;
    border-radius: $radius-full;
    font-weight: $fw-bold;

    &.step-1 { background: $color-secondary-100; color: $color-secondary-800; }
    &.step-2 { background: hsl(38, 92%, 90%); color: hsl(38, 92%, 25%); }
    &.step-3 { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); }
  }

  .channel-badge {
    font-size: $fs-2xs;
    padding: 2px $space-2;
    border-radius: $radius-sm;
    font-weight: $fw-bold;

    &.channel-whatsapp { background: #dcfce7; color: #15803d; }
    &.channel-sms { background: #e0f2fe; color: #0369a1; }
    &.channel-email { background: #f3e8ff; color: #7e22ce; }
  }

  .coupon-code {
    background: $color-neutral-100;
    border: 1px dashed $color-neutral-300;
    padding: 1px $space-2;
    border-radius: $radius-sm;
    font-family: $font-mono;
    font-weight: $fw-bold;
    color: $color-primary-700;
  }

  .status-pill {
    font-size: $fs-2xs;
    padding: 2px $space-2;
    border-radius: $radius-full;
    font-weight: $fw-semibold;

    &.status-sent { background: $color-neutral-100; color: $color-neutral-600; }
    &.status-recovered { background: hsl(142, 71%, 90%); color: hsl(142, 71%, 25%); font-weight: $fw-bold; }
  }

  .order-link {
    color: $color-primary-600;
    font-weight: $fw-semibold;
    text-decoration: none;

    &:hover { text-decoration: underline; }
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
