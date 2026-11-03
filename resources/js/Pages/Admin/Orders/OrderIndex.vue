<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

interface OrderItem {
  id: number;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price_bdt: string;
  subtotal_bdt: string;
}

interface CourierOption {
  code: string;
  name: string;
  is_sandbox: boolean;
  is_active: boolean;
}

interface CourierStatItem {
  code: string;
  name: string;
  sent_count: number;
  in_transit_count: number;
  delivered_count: number;
  returned_count: number;
}

interface ParcelSummary {
  total_sent: number;
  unbooked_pending: number;
  by_courier: Record<string, CourierStatItem>;
}

interface ProductOption {
  id: string;
  name: string;
}

interface OrderRecord {
  id: number;
  ulid: string;
  order_number: string;
  customer_name: string;
  customer_phone: string;
  delivery_zone: string;
  delivery_address: string;
  subtotal_bdt: string;
  discount_amount_bdt: string;
  shipping_fee_bdt: string;
  total_bdt: string;
  status: string;
  payment_status: string;
  payment_method: string;
  courier_code: string | null;
  courier_consignment_id: string | null;
  courier_tracking_code: string | null;
  payable_cod_amount_bdt?: string;
  ticket_print_count: number;
  ticket_printed_at: string | null;
  created_at: string;
  items: OrderItem[];
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const props = defineProps<{
  orders: {
    data: OrderRecord[];
    links: PaginationLink[];
    total: number;
    current_page: number;
  };
  filters: {
    status: string;
    delivery_zone: string;
    search: string | null;
    courier?: string;
    date_from?: string | null;
    date_to?: string | null;
    product_id?: string;
    product_search?: string | null;
  };
  counts: {
    all: number;
    pending: number;
    confirmed: number;
    processing: number;
    shipped: number;
    delivered: number;
    returned: number;
    cancelled: number;
  };
  couriers?: CourierOption[];
  parcel_summary?: ParcelSummary;
  products?: ProductOption[];
}>();

const search = ref(props.filters.search || '');
const selectedZone = ref(props.filters.delivery_zone || 'all');
const selectedCourierFilter = ref(props.filters.courier || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const selectedProductId = ref(props.filters.product_id || 'all');
const productSearch = ref(props.filters.product_search || '');

const hasActiveFilters = computed(() => {
  return (
    !!search.value ||
    selectedZone.value !== 'all' ||
    selectedCourierFilter.value !== 'all' ||
    !!dateFrom.value ||
    !!dateTo.value ||
    selectedProductId.value !== 'all' ||
    !!productSearch.value
  );
});

const handleSearch = () => {
  router.get(
    '/admin/orders',
    {
      status: props.filters.status,
      delivery_zone: selectedZone.value !== 'all' ? selectedZone.value : undefined,
      search: search.value || undefined,
      courier: selectedCourierFilter.value !== 'all' ? selectedCourierFilter.value : undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      product_id: selectedProductId.value !== 'all' ? selectedProductId.value : undefined,
      product_search: productSearch.value || undefined,
    },
    { preserveState: true }
  );
};

const handleStatusFilter = (targetStatus: string) => {
  router.get(
    '/admin/orders',
    {
      status: targetStatus,
      delivery_zone: selectedZone.value !== 'all' ? selectedZone.value : undefined,
      search: search.value || undefined,
      courier: selectedCourierFilter.value !== 'all' ? selectedCourierFilter.value : undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      product_id: selectedProductId.value !== 'all' ? selectedProductId.value : undefined,
      product_search: productSearch.value || undefined,
    },
    { preserveState: true }
  );
};

const handleCourierFilter = (courierCode: string) => {
  if (selectedCourierFilter.value === courierCode) {
    selectedCourierFilter.value = 'all';
  } else {
    selectedCourierFilter.value = courierCode;
  }
  handleSearch();
};

const applyDatePreset = (preset: 'today' | 'yesterday' | 'last_7_days' | 'this_month' | 'all') => {
  const today = new Date();
  const format = (d: Date) => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  if (preset === 'today') {
    dateFrom.value = format(today);
    dateTo.value = format(today);
  } else if (preset === 'yesterday') {
    const y = new Date();
    y.setDate(today.getDate() - 1);
    dateFrom.value = format(y);
    dateTo.value = format(y);
  } else if (preset === 'last_7_days') {
    const d7 = new Date();
    d7.setDate(today.getDate() - 6);
    dateFrom.value = format(d7);
    dateTo.value = format(today);
  } else if (preset === 'this_month') {
    const mStart = new Date(today.getFullYear(), today.getMonth(), 1);
    dateFrom.value = format(mStart);
    dateTo.value = format(today);
  } else {
    dateFrom.value = '';
    dateTo.value = '';
  }
  handleSearch();
};

const resetAllFilters = () => {
  search.value = '';
  selectedZone.value = 'all';
  selectedCourierFilter.value = 'all';
  dateFrom.value = '';
  dateTo.value = '';
  selectedProductId.value = 'all';
  productSearch.value = '';
  router.get('/admin/orders', { status: props.filters.status }, { preserveState: true });
};

const formatCurrency = (val: string | number) => {
  return Number(val).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getStatusBadgeClass = (status: string) => {
  return `badge-status-${status.replace(/_/g, '-')}`;
};

const selectedOrderIds = ref<number[]>([]);
const isConfirming = ref<Record<number, boolean>>({});
const isTransitioning = ref<Record<number, boolean>>({});
const isBulkConfirming = ref(false);
const isBulkTransitioning = ref(false);

const selectedOrders = computed(() => {
  return props.orders.data.filter((o) => selectedOrderIds.value.includes(o.id));
});

const confirmedSelectedCount = computed(() => {
  return selectedOrders.value.filter((o) => o.status === 'confirmed').length;
});

const processingSelectedCount = computed(() => {
  return selectedOrders.value.filter((o) => o.status === 'processing' || o.status === 'picked_and_packed').length;
});

const pendingSelectedCount = computed(() => {
  return selectedOrders.value.filter((o) => o.status === 'pending').length;
});

const isAllSelected = computed(() => {
  return props.orders.data.length > 0 && selectedOrderIds.value.length === props.orders.data.length;
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedOrderIds.value = [];
  } else {
    selectedOrderIds.value = props.orders.data.map((o) => o.id);
  }
};

const confirmOrder = (order: OrderRecord) => {
  isConfirming.value[order.id] = true;
  router.post(
    `/admin/orders/${order.id}/transition`,
    {
      target_status: 'confirmed',
      reason: 'Confirmed directly from orders table action button.',
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isConfirming.value[order.id] = false;
      },
    }
  );
};

const transitionOrder = (order: OrderRecord, targetStatus: string, reason?: string) => {
  isTransitioning.value[order.id] = true;
  router.post(
    `/admin/orders/${order.id}/transition`,
    {
      target_status: targetStatus,
      reason: reason || `Transitioned to ${targetStatus} from orders table action button.`,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isTransitioning.value[order.id] = false;
      },
    }
  );
};

const handleBulkConfirm = async () => {
  if (selectedOrderIds.value.length === 0) return;

  const count = selectedOrderIds.value.length;
  const result = await Swal.fire({
    title: 'Confirm Orders?',
    html: `Are you sure you want to confirm all <b>${count}</b> selected order(s)? Stock will be reserved for dispatch.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#64748b',
    confirmButtonText: `✓ Yes, Confirm ${count} Order(s)`,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusConfirm: true,
  });

  if (!result.isConfirmed) return;

  isBulkConfirming.value = true;
  router.post(
    '/admin/orders/bulk-confirm',
    { order_ids: selectedOrderIds.value },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        selectedOrderIds.value = [];
        Swal.fire({
          icon: 'success',
          title: 'Orders Confirmed!',
          text: `${count} order(s) successfully confirmed and stock reserved.`,
          timer: 2000,
          showConfirmButton: false,
        });
      },
      onFinish: () => {
        isBulkConfirming.value = false;
      },
    }
  );
};

const handleBulkTransition = async (targetStatus: string, actionLabel: string) => {
  if (selectedOrderIds.value.length === 0) return;

  const count = selectedOrderIds.value.length;
  const result = await Swal.fire({
    title: `Move to ${actionLabel}?`,
    html: `Are you sure you want to transition <b>${count}</b> selected order(s) to <b>'${actionLabel}'</b>?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#64748b',
    confirmButtonText: `Yes, Move to ${actionLabel}`,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusConfirm: true,
  });

  if (!result.isConfirmed) return;

  isBulkTransitioning.value = true;
  router.post(
    '/admin/orders/bulk-transition',
    {
      order_ids: selectedOrderIds.value,
      target_status: targetStatus,
      reason: `Bulk transitioned to ${targetStatus} from admin orders dashboard.`,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        selectedOrderIds.value = [];
        Swal.fire({
          icon: 'success',
          title: 'Status Updated!',
          text: `${count} order(s) moved to ${actionLabel}.`,
          timer: 2000,
          showConfirmButton: false,
        });
      },
      onFinish: () => {
        isBulkTransitioning.value = false;
      },
    }
  );
};

const printBagTickets = (orderIds?: number[]) => {
  const ids = orderIds && orderIds.length > 0 ? orderIds : selectedOrderIds.value;
  if (ids.length === 0) return;
  const url = `/admin/orders/bag-tickets?order_ids=${ids.join(',')}`;
  window.open(url, '_blank');
};

const defaultCouriers: CourierOption[] = [
  { code: 'steadfast', name: 'Steadfast Courier', is_sandbox: false, is_active: true },
  { code: 'pathao', name: 'Pathao Courier', is_sandbox: true, is_active: true },
];

const availableCouriers = computed(() => {
  return props.couriers && props.couriers.length > 0 ? props.couriers : defaultCouriers;
});

const selectedCourier = ref(
  props.couriers && props.couriers.length > 0 ? props.couriers[0].code : 'steadfast'
);

const selectedCourierName = computed(() => {
  const c = availableCouriers.value.find((item) => item.code === selectedCourier.value);
  return c ? c.name : selectedCourier.value;
});

const isBookingCourier = ref<Record<number, boolean>>({});
const isBulkBookingCourier = ref(false);
const courierNotification = ref<{
  type: 'success' | 'error';
  message: string;
  trackingCode?: string;
  labelUrl?: string;
} | null>(null);

const getCsrfToken = () => {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
};

const bookCourierSingle = async (order: OrderRecord) => {
  const result = await Swal.fire({
    title: '<span style="color: #9d174d; font-weight: 800; font-size: 22px;">Send & Book with Courier?</span>',
    html: `
      <div class="swal-pink-card" style="background: #fdf2f8; border: 2px solid #fbcfe8; border-radius: 12px; padding: 18px 20px; color: #831843; font-size: 15px; font-weight: 600; line-height: 1.5; margin: 12px 0; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.12); text-align: center;">
        🚚 Are you sure you want to send and book order <b style="color: #be185d; font-size: 16px;">${order.order_number}</b> with <b style="color: #be185d; font-size: 16px;">${selectedCourierName.value}</b>?
      </div>
    `,
    icon: 'question',
    iconColor: '#db2777',
    background: '#fff5f7',
    showCancelButton: true,
    confirmButtonColor: '#db2777',
    cancelButtonColor: '#64748b',
    confirmButtonText: `🚀 Yes, Send to ${selectedCourierName.value}`,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusConfirm: true,
    customClass: {
      popup: 'swal-pink-popup',
    },
  });

  if (!result.isConfirmed) return;

  isBookingCourier.value[order.id] = true;
  courierNotification.value = null;

  try {
    const res = await fetch(`/admin/orders/${order.id}/book-courier`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        courier_code: selectedCourier.value,
      }),
    });

    const data = await res.json();
    if (res.ok && data.status === 'success') {
      const tracking = data.tracking_code || data.consignment_id;
      const labelUrl = `/admin/orders/${order.id}/shipping-label`;

      courierNotification.value = {
        type: 'success',
        message: `Consignment booked successfully with ${selectedCourierName.value}! Tracking Code: ${tracking}`,
        trackingCode: tracking,
        labelUrl,
      };

      Swal.fire({
        icon: 'success',
        title: 'Consignment Booked!',
        html: `Order <b>${order.order_number}</b> successfully booked with <b>${selectedCourierName.value}</b>!<br><br>Tracking Code: <code style="font-family: monospace; font-weight: bold; background: #eff6ff; padding: 4px 8px; border-radius: 4px; color: #1d4ed8; font-size: 14px;">${tracking}</code>`,
        confirmButtonColor: '#0f172a',
        confirmButtonText: '🏷️ Print 4x6 Label',
        showCancelButton: true,
        cancelButtonText: 'Close',
      }).then((modalRes) => {
        if (modalRes.isConfirmed) {
          window.open(labelUrl, '_blank');
        }
      });

      router.reload({ preserveScroll: true });
    } else {
      const errorMsg = data.message || `Failed to book order #${order.order_number} with courier.`;
      courierNotification.value = {
        type: 'error',
        message: errorMsg,
      };
      Swal.fire({
        icon: 'error',
        title: 'Booking Failed',
        text: errorMsg,
        confirmButtonColor: '#dc2626',
      });
    }
  } catch (err: any) {
    const errorMsg = err?.message || 'Network error while booking courier.';
    courierNotification.value = {
      type: 'error',
      message: errorMsg,
    };
    Swal.fire({
      icon: 'error',
      title: 'Network Error',
      text: errorMsg,
      confirmButtonColor: '#dc2626',
    });
  } finally {
    isBookingCourier.value[order.id] = false;
  }
};

const handleBulkBookCourier = async () => {
  if (selectedOrderIds.value.length === 0) return;

  const count = selectedOrderIds.value.length;
  const result = await Swal.fire({
    title: '<span style="color: #9d174d; font-weight: 800; font-size: 22px;">Send & Book with Courier?</span>',
    html: `
      <div class="swal-pink-card" style="background: #fdf2f8; border: 2px solid #fbcfe8; border-radius: 12px; padding: 18px 20px; color: #831843; font-size: 15px; font-weight: 600; line-height: 1.5; margin: 12px 0; box-shadow: 0 4px 12px rgba(236, 72, 153, 0.12); text-align: center;">
        🚚 Are you sure you want to send and book <b style="color: #be185d; font-size: 16px;">${count}</b> selected order(s) with <b style="color: #be185d; font-size: 16px;">${selectedCourierName.value}</b>?
      </div>
    `,
    icon: 'question',
    iconColor: '#db2777',
    background: '#fff5f7',
    showCancelButton: true,
    confirmButtonColor: '#db2777',
    cancelButtonColor: '#64748b',
    confirmButtonText: `🚀 Yes, Send to ${selectedCourierName.value}`,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusConfirm: true,
    customClass: {
      popup: 'swal-pink-popup',
    },
  });

  if (!result.isConfirmed) return;

  isBulkBookingCourier.value = true;
  courierNotification.value = null;

  try {
    const res = await fetch('/admin/orders/bulk-book-courier', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        order_ids: selectedOrderIds.value,
        courier_code: selectedCourier.value,
      }),
    });

    const data = await res.json();
    if (res.ok && data.status === 'success') {
      const summary = data.summary;
      const labelUrl = `/admin/orders/bulk-shipping-labels?order_ids=${selectedOrderIds.value.join(',')}`;

      courierNotification.value = {
        type: 'success',
        message: `Bulk Courier Booking Complete with ${selectedCourierName.value}! Successfully Booked: ${summary.successful}, Failed: ${summary.failed}.`,
        labelUrl,
      };

      Swal.fire({
        icon: 'success',
        title: 'Bulk Booking Complete!',
        html: `Successfully sent to <b>${selectedCourierName.value}</b>!<br><br>Booked: <b style="color: #16a34a; font-size: 15px;">${summary.successful}</b> | Failed: <b style="color: #dc2626; font-size: 15px;">${summary.failed}</b>`,
        confirmButtonColor: '#0f172a',
        confirmButtonText: '🏷️ Print 4x6 Labels',
        showCancelButton: true,
        cancelButtonText: 'Done',
      }).then((modalRes) => {
        if (modalRes.isConfirmed) {
          window.open(labelUrl, '_blank');
        }
      });

      selectedOrderIds.value = [];
      router.reload({ preserveScroll: true });
    } else {
      const errorMsg = data.message || 'Failed to bulk book courier consignments.';
      courierNotification.value = {
        type: 'error',
        message: errorMsg,
      };
      Swal.fire({
        icon: 'error',
        title: 'Booking Failed',
        text: errorMsg,
        confirmButtonColor: '#dc2626',
      });
    }
  } catch (err: any) {
    const errorMsg = err?.message || 'Network error while booking courier.';
    courierNotification.value = {
      type: 'error',
      message: errorMsg,
    };
    Swal.fire({
      icon: 'error',
      title: 'Network Error',
      text: errorMsg,
      confirmButtonColor: '#dc2626',
    });
  } finally {
    isBulkBookingCourier.value = false;
  }
};

const printShippingLabels = (orderIds?: number[]) => {
  const ids = orderIds && orderIds.length > 0 ? orderIds : selectedOrderIds.value;
  if (ids.length === 0) return;
  const url = `/admin/orders/bulk-shipping-labels?order_ids=${ids.join(',')}`;
  window.open(url, '_blank');
};

// Row Action Dropdown Menu state
const activeDropdownOrderId = ref<number | null>(null);

const toggleOrderDropdown = (id: number) => {
  activeDropdownOrderId.value = activeDropdownOrderId.value === id ? null : id;
};

const closeOrderDropdown = () => {
  activeDropdownOrderId.value = null;
};

onMounted(() => {
  window.addEventListener('click', closeOrderDropdown);
});

onUnmounted(() => {
  window.removeEventListener('click', closeOrderDropdown);
});
</script>

<template>
  <AdminLayout>
    <Head title="Orders & Anti-Fraud Management" />

    <div class="orders-management-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">🛍️ Orders & Dispatch Management</h1>
          <p class="page-subtitle">Track real-time order lifecycle transitions, customer verification, and courier handoffs.</p>
        </div>
        <div class="header-actions">
          <Link
            href="/admin/orders/scan-handover"
            class="btn btn-scan-handover"
            title="Open high-speed handheld barcode scanner console to handover parcels to courier"
          >
            📦 Scan & Handover to Courier
          </Link>
          <Link
            href="/admin/orders/scan-returns"
            class="btn btn-scan-returns"
            title="Open high-speed handheld barcode scanner console to receive returned parcels"
          >
            📷 Scan Courier Returns
          </Link>
          <button
            v-if="orders.data.length > 0"
            type="button"
            class="btn btn-outline btn-sm btn-header-print"
            title="Open printable bag tickets for all orders currently listed"
            @click="printBagTickets(orders.data.map((o) => o.id))"
          >
            🖨️ Print All Page Bag Tickets ({{ orders.data.length }})
          </button>
        </div>
      </div>

      <!-- Courier Parcel Dispatch Breakdown Statistics -->
      <div v-if="parcel_summary" class="dispatch-overview-bar">
        <div class="overview-title-row">
          <div class="overview-heading">
            <span class="heading-icon">📊</span>
            <span class="heading-text">Courier Parcel Dispatch Intelligence</span>
            <span class="subtitle-text">(Real-time breakdown of dispatched parcels by courier partner)</span>
          </div>
          <div v-if="hasActiveFilters" class="active-filter-indicator">
            <span class="indicator-tag">⚡ Filters Applied</span>
            <button type="button" class="btn-clear-mini" @click="resetAllFilters">Reset</button>
          </div>
        </div>

        <div class="overview-cards-grid">
          <!-- Total Sent Card -->
          <div
            class="overview-card card-total"
            :class="{ 'is-active': selectedCourierFilter === 'all' }"
            title="Click to view all sent parcels"
            @click="handleCourierFilter('all')"
          >
            <div class="card-icon-wrap">📦</div>
            <div class="card-body">
              <div class="card-num">{{ parcel_summary.total_sent }}</div>
              <div class="card-title">Total Parcels Sent</div>
              <div class="card-sub">All Active Couriers</div>
            </div>
            <div class="card-badge">{{ selectedCourierFilter === 'all' ? 'Active' : 'All' }}</div>
          </div>

          <!-- Courier Breakdown Cards -->
          <div
            v-for="(courierStat, cCode) in parcel_summary.by_courier"
            :key="cCode"
            class="overview-card"
            :class="[`card-courier-${cCode}`, { 'is-active': selectedCourierFilter === cCode }]"
            :title="`Click to filter orders sent with ${courierStat.name}`"
            @click="handleCourierFilter(String(cCode))"
          >
            <div class="card-icon-wrap">🚚</div>
            <div class="card-body">
              <div class="card-num">{{ courierStat.sent_count }}</div>
              <div class="card-title">{{ courierStat.name }}</div>
              <div class="card-metrics-row">
                <span class="metric-pill in-transit" title="In Transit / With Courier">🚚 {{ courierStat.in_transit_count }} Transit</span>
                <span class="metric-pill delivered" title="Delivered Successfully">✓ {{ courierStat.delivered_count }} Delivered</span>
                <span v-if="courierStat.returned_count > 0" class="metric-pill returned" title="Returned Parcels">↩ {{ courierStat.returned_count }} Returned</span>
              </div>
            </div>
            <div class="card-badge">{{ selectedCourierFilter === cCode ? '✓ Active' : 'Filter' }}</div>
          </div>

          <!-- Pending Dispatch Card -->
          <div
            class="overview-card card-pending"
            :class="{ 'is-active': selectedCourierFilter === 'unbooked' }"
            title="Click to view orders not yet booked with any courier"
            @click="handleCourierFilter('unbooked')"
          >
            <div class="card-icon-wrap">⏳</div>
            <div class="card-body">
              <div class="card-num">{{ parcel_summary.unbooked_pending }}</div>
              <div class="card-title">Pending Booking</div>
              <div class="card-sub">Not yet booked with courier</div>
            </div>
            <div class="card-badge">{{ selectedCourierFilter === 'unbooked' ? '✓ Active' : 'Filter' }}</div>
          </div>
        </div>
      </div>

      <!-- Status Filter Tabs -->
      <div class="status-tabs">
        <button
          class="status-tab"
          :class="{ 'is-active': filters.status === 'all' }"
          @click="handleStatusFilter('all')"
        >
          All Orders <span class="tab-badge">{{ counts.all }}</span>
        </button>
        <button
          class="status-tab tab-pending"
          :class="{ 'is-active': filters.status === 'pending' }"
          @click="handleStatusFilter('pending')"
        >
          Pending Verification <span class="tab-badge">{{ counts.pending }}</span>
        </button>
        <button
          class="status-tab tab-confirmed"
          :class="{ 'is-active': filters.status === 'confirmed' }"
          @click="handleStatusFilter('confirmed')"
        >
          Confirmed <span class="tab-badge">{{ counts.confirmed }}</span>
        </button>
        <button
          class="status-tab tab-processing"
          :class="{ 'is-active': filters.status === 'processing' }"
          @click="handleStatusFilter('processing')"
        >
          Processing & Pick <span class="tab-badge">{{ counts.processing }}</span>
        </button>
        <button
          class="status-tab tab-shipped"
          :class="{ 'is-active': filters.status === 'handed_to_courier' }"
          @click="handleStatusFilter('handed_to_courier')"
        >
          With Courier <span class="tab-badge">{{ counts.shipped }}</span>
        </button>
        <button
          class="status-tab tab-delivered"
          :class="{ 'is-active': filters.status === 'delivered' }"
          @click="handleStatusFilter('delivered')"
        >
          Delivered <span class="tab-badge">{{ counts.delivered }}</span>
        </button>
        <button
          class="status-tab tab-returned"
          :class="{ 'is-active': filters.status === 'returned' }"
          @click="handleStatusFilter('returned')"
        >
          Returned Parcels <span class="tab-badge">{{ counts.returned || 0 }}</span>
        </button>
        <button
          class="status-tab tab-cancelled"
          :class="{ 'is-active': filters.status === 'cancelled' }"
          @click="handleStatusFilter('cancelled')"
        >
          Cancelled <span class="tab-badge">{{ counts.cancelled }}</span>
        </button>
      </div>

      <!-- Comprehensive Filters Panel -->
      <div class="filters-panel">
        <!-- Row 1: Search, Delivery Zone, Filter Courier, Top Send Courier -->
        <div class="filter-row primary-row">
          <div class="search-wrap">
            <input
              v-model="search"
              type="text"
              placeholder="Search by Order #, Customer Name, Phone..."
              class="input-search"
              @keyup.enter="handleSearch"
            />
            <button class="btn btn-primary btn-search" @click="handleSearch">Search</button>
          </div>

          <div class="filter-group">
            <label class="filter-label">Zone:</label>
            <select v-model="selectedZone" class="filter-select" @change="handleSearch">
              <option value="all">All Delivery Zones</option>
              <option value="inside_dhaka">Inside Dhaka (৳70)</option>
              <option value="dhaka_suburbs">Dhaka Suburbs (৳100)</option>
              <option value="outside_dhaka">Outside Dhaka (৳130)</option>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Filter Courier:</label>
            <select v-model="selectedCourierFilter" class="filter-select" @change="handleSearch">
              <option value="all">All Couriers</option>
              <option v-for="c in availableCouriers" :key="c.code" :value="c.code">
                {{ c.name }}
              </option>
              <option value="unbooked">Pending Dispatch (Unbooked)</option>
            </select>
          </div>

          <!-- Top Dispatch Courier Selector (which courier to send to) -->
          <div class="courier-selector-wrap">
            <label class="filter-label">🚚 Send To:</label>
            <select v-model="selectedCourier" class="select-courier">
              <option v-for="c in availableCouriers" :key="c.code" :value="c.code">
                {{ c.name }} {{ c.is_sandbox ? '🧪 (Sandbox)' : '⚡ (Live)' }}
              </option>
            </select>
          </div>
        </div>

        <!-- Row 2: Date Range Filter & Product Filter -->
        <div class="filter-row secondary-row">
          <!-- Date Range Filter -->
          <div class="date-filter-group">
            <label class="filter-label">📅 Date Range:</label>
            <div class="date-inputs">
              <input
                v-model="dateFrom"
                type="date"
                class="input-date"
                title="Filter orders from this date"
                @change="handleSearch"
              />
              <span class="date-separator">to</span>
              <input
                v-model="dateTo"
                type="date"
                class="input-date"
                title="Filter orders up to this date"
                @change="handleSearch"
              />
            </div>
            <div class="date-presets">
              <button
                type="button"
                class="btn-preset"
                @click="applyDatePreset('today')"
              >
                Today
              </button>
              <button
                type="button"
                class="btn-preset"
                @click="applyDatePreset('yesterday')"
              >
                Yesterday
              </button>
              <button
                type="button"
                class="btn-preset"
                @click="applyDatePreset('last_7_days')"
              >
                7 Days
              </button>
              <button
                type="button"
                class="btn-preset"
                @click="applyDatePreset('this_month')"
              >
                This Month
              </button>
              <button
                v-if="dateFrom || dateTo"
                type="button"
                class="btn-preset is-clear"
                @click="applyDatePreset('all')"
              >
                Clear Dates
              </button>
            </div>
          </div>

          <!-- Filter by Product -->
          <div class="product-filter-group">
            <label class="filter-label">📦 Filter by Product:</label>
            <div class="product-inputs">
              <select
                v-model="selectedProductId"
                class="select-product"
                @change="handleSearch"
              >
                <option value="all">All Products</option>
                <option v-for="prod in products" :key="prod.id" :value="prod.id">
                  {{ prod.name }}
                </option>
              </select>
              <input
                v-model="productSearch"
                type="text"
                placeholder="Or SKU / Product name..."
                class="input-product-search"
                @keyup.enter="handleSearch"
              />
              <button
                type="button"
                class="btn btn-filter-apply"
                @click="handleSearch"
              >
                Apply
              </button>
              <button
                v-if="hasActiveFilters"
                type="button"
                class="btn btn-filter-reset"
                title="Reset all filters"
                @click="resetAllFilters"
              >
                ✕ Reset All
              </button>
            </div>
          </div>
        </div>

        <!-- Active Filter Pills (if any) -->
        <div v-if="hasActiveFilters" class="active-pills-bar">
          <span class="pills-label">Active Filters:</span>
          <span v-if="search" class="filter-pill">
            Search: "{{ search }}" <button type="button" @click="search = ''; handleSearch()">✕</button>
          </span>
          <span v-if="selectedZone !== 'all'" class="filter-pill">
            Zone: {{ selectedZone }} <button type="button" @click="selectedZone = 'all'; handleSearch()">✕</button>
          </span>
          <span v-if="selectedCourierFilter !== 'all'" class="filter-pill">
            Courier: {{ selectedCourierFilter }} <button type="button" @click="selectedCourierFilter = 'all'; handleSearch()">✕</button>
          </span>
          <span v-if="dateFrom || dateTo" class="filter-pill">
            Date: {{ dateFrom || 'Start' }} → {{ dateTo || 'Now' }} <button type="button" @click="dateFrom = ''; dateTo = ''; handleSearch()">✕</button>
          </span>
          <span v-if="selectedProductId !== 'all'" class="filter-pill">
            Product: {{ products?.find(p => p.id === selectedProductId)?.name || selectedProductId }} <button type="button" @click="selectedProductId = 'all'; handleSearch()">✕</button>
          </span>
          <span v-if="productSearch" class="filter-pill">
            Keyword: "{{ productSearch }}" <button type="button" @click="productSearch = ''; handleSearch()">✕</button>
          </span>
          <button type="button" class="btn-clear-all" @click="resetAllFilters">Clear All</button>
        </div>
      </div>

      <!-- Courier Booking Notification Banner -->
      <div
        v-if="courierNotification"
        class="courier-notification-banner"
        :class="`is-${courierNotification.type}`"
      >
        <div class="banner-content">
          <span class="banner-icon">{{ courierNotification.type === 'success' ? '✅' : '⚠️' }}</span>
          <span>{{ courierNotification.message }}</span>
          <a
            v-if="courierNotification.labelUrl"
            :href="courierNotification.labelUrl"
            target="_blank"
            class="banner-link"
          >
            🖨️ Open 4x6 Printable Shipping Label(s) →
          </a>
        </div>
        <button
          type="button"
          class="banner-close"
          @click="courierNotification = null"
        >
          ✕
        </button>
      </div>

      <!-- Bulk Actions Bar -->
      <div v-if="selectedOrderIds.length > 0" class="bulk-actions-bar">
        <div class="bulk-info">
          <span class="bulk-count-badge">{{ selectedOrderIds.length }}</span>
          <span>order(s) selected</span>
        </div>
        <div class="bulk-btn-group">
          <!-- Send Selected to Courier -->
          <button
            type="button"
            class="btn btn-courier-bulk-send"
            :disabled="isBulkBookingCourier"
            :title="`Send and book ${selectedOrderIds.length} orders with ${selectedCourierName}`"
            @click="handleBulkBookCourier"
          >
            <span v-if="isBulkBookingCourier">⏳ Booking with {{ selectedCourierName }}...</span>
            <span v-else>🚀 Send to {{ selectedCourierName }} ({{ selectedOrderIds.length }})</span>
          </button>

          <!-- Print 4x6 Shipping Labels for Selected Orders -->
          <button
            type="button"
            class="btn btn-labels-bulk"
            title="Print 4x6 thermal barcode shipping labels for selected orders"
            @click="printShippingLabels()"
          >
            🏷️ Print 4x6 Labels ({{ selectedOrderIds.length }})
          </button>

          <button
            type="button"
            class="btn btn-print-bulk"
            title="Print in-bag tickets with Code128 barcodes for selected orders"
            @click="printBagTickets()"
          >
            🖨️ Print {{ selectedOrderIds.length }} Bag Ticket(s)
          </button>

          <!-- Bulk Confirm for Pending Orders -->
          <button
            v-if="pendingSelectedCount > 0 || filters.status === 'pending'"
            type="button"
            class="btn btn-confirm-bulk"
            :disabled="isBulkConfirming"
            title="Confirm selected pending orders and reserve stock"
            @click="handleBulkConfirm"
          >
            <span v-if="isBulkConfirming">⏳ Confirming...</span>
            <span v-else>✓ Confirm Selected ({{ pendingSelectedCount || selectedOrderIds.length }})</span>
          </button>

          <!-- Bulk Move Confirmed Orders to Processing & Pick -->
          <button
            v-if="confirmedSelectedCount > 0 || filters.status === 'confirmed'"
            type="button"
            class="btn btn-process-bulk"
            :disabled="isBulkTransitioning"
            title="Move confirmed orders to Processing & Pick"
            @click="handleBulkTransition('processing', 'Processing & Pick')"
          >
            <span v-if="isBulkTransitioning">⏳ Updating...</span>
            <span v-else>📦 Move to Processing & Pick ({{ confirmedSelectedCount || selectedOrderIds.length }})</span>
          </button>

          <!-- Bulk Assign Processing Orders to With Courier -->
          <button
            v-if="processingSelectedCount > 0 || filters.status === 'processing'"
            type="button"
            class="btn btn-courier-bulk"
            :disabled="isBulkTransitioning"
            title="Bulk assign orders to With Courier"
            @click="handleBulkTransition('handed_to_courier', 'With Courier')"
          >
            <span v-if="isBulkTransitioning">⏳ Updating...</span>
            <span v-else>🚚 Assign With Courier ({{ processingSelectedCount || selectedOrderIds.length }})</span>
          </button>

          <button
            type="button"
            class="btn btn-deselect"
            @click="selectedOrderIds = []"
          >
            ✕ Deselect All
          </button>
        </div>
      </div>

      <!-- Orders Data Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="checkbox-cell">
                <input
                  type="checkbox"
                  class="checkbox-custom"
                  :checked="isAllSelected"
                  title="Select All on Page"
                  @change="toggleSelectAll"
                />
              </th>
              <th>Order #</th>
              <th>Customer</th>
              <th>Zone</th>
              <th>Courier Consignment</th>
              <th>Items</th>
              <th>Total BDT</th>
              <th>Status</th>
              <th>Date</th>
              <th style="min-width: 100px; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="order in orders.data"
              :key="order.id"
              class="order-row"
              :class="{ 'is-selected': selectedOrderIds.includes(order.id) }"
            >
              <td class="checkbox-cell">
                <input
                  type="checkbox"
                  class="checkbox-custom"
                  :value="order.id"
                  v-model="selectedOrderIds"
                />
              </td>
              <td class="order-num-cell">
                <Link :href="`/admin/orders/${order.id}`" class="order-link">
                  <strong class="font-mono">{{ order.order_number }}</strong>
                </Link>
                <div class="order-ticket-sub">
                  <a
                    :href="`/admin/orders/bag-tickets?order_ids=${order.id}`"
                    target="_blank"
                    class="ticket-badge-link"
                    :class="order.ticket_print_count > 0 ? 'is-printed' : 'is-unprinted'"
                    :title="order.ticket_print_count > 0 ? `Bag ticket printed ${order.ticket_print_count} time(s). Click to reprint.` : 'Bag ticket not printed yet. Click to print.'"
                  >
                    <span v-if="order.ticket_print_count > 0">🖨️ {{ order.ticket_print_count }}x Bag Ticket</span>
                    <span v-else>⚠️ Bag Ticket (0x)</span>
                  </a>
                </div>
              </td>
              <td>
                <div class="customer-info">
                  <span class="cust-name">{{ order.customer_name }}</span>
                  <span class="cust-phone">{{ order.customer_phone }}</span>
                </div>
              </td>
              <td>
                <span class="zone-pill" :class="`zone-${order.delivery_zone}`">
                  {{ order.delivery_zone === 'inside_dhaka' ? 'Inside Dhaka' : order.delivery_zone === 'dhaka_suburbs' ? 'Dhaka Suburbs' : 'Outside Dhaka' }}
                </span>
              </td>
              <td>
                <div v-if="order.courier_consignment_id || order.courier_tracking_code" class="courier-badge-wrap">
                  <span class="courier-tag" :class="`courier-${order.courier_code || 'default'}`">
                    🚚 {{ (order.courier_code || 'courier').toUpperCase() }}
                  </span>
                  <div class="courier-details">
                    <span class="tracking-code font-mono" :title="`Tracking: ${order.courier_tracking_code || order.courier_consignment_id}`">
                      {{ order.courier_tracking_code || order.courier_consignment_id }}
                    </span>
                    <a
                      :href="`/admin/orders/${order.id}/shipping-label`"
                      target="_blank"
                      class="label-link"
                      title="Open 4x6 thermal printable barcode label"
                    >
                      🖨️ Label
                    </a>
                  </div>
                </div>
                <div v-else class="courier-unbooked">
                  <span class="unbooked-label">Unbooked</span>
                  <button
                    type="button"
                    class="btn btn-courier-mini-send"
                    :disabled="isBookingCourier[order.id]"
                    :title="`Book consignment with ${selectedCourierName}`"
                    @click="bookCourierSingle(order)"
                  >
                    <span v-if="isBookingCourier[order.id]">⏳</span>
                    <span v-else>🚀 Send</span>
                  </button>
                </div>
              </td>
              <td>
                <span class="items-count-badge">{{ order.items?.length || 0 }} SKU(s)</span>
              </td>
              <td class="order-total-cell">
                <strong>৳{{ formatCurrency(order.total_bdt) }}</strong>
              </td>
              <td>
                <span class="status-badge" :class="getStatusBadgeClass(order.status)">
                  {{ order.status.replace(/_/g, ' ') }}
                </span>
              </td>
              <td class="order-date-cell">
                {{ new Date(order.created_at).toLocaleDateString('en-GB') }}
              </td>
              <td class="actions-cell text-right">
                <div class="action-dropdown-wrap">
                  <button
                    type="button"
                    class="btn-dropdown-trigger"
                    :class="{ 'is-active': activeDropdownOrderId === order.id }"
                    title="Order Actions"
                    @click.stop="toggleOrderDropdown(order.id)"
                  >
                    <span>Actions</span>
                    <span class="chevron" :class="{ 'is-open': activeDropdownOrderId === order.id }">▾</span>
                  </button>

                  <!-- Action Dropdown Menu -->
                  <div
                    v-if="activeDropdownOrderId === order.id"
                    class="action-dropdown-menu"
                    @click.stop
                  >
                    <!-- View Details -->
                    <Link
                      :href="`/admin/orders/${order.id}`"
                      class="dropdown-item"
                      @click="closeOrderDropdown"
                    >
                      <span class="dropdown-item-icon">👁️</span>
                      <span>View Details</span>
                    </Link>

                    <!-- Print In-Bag Ticket with Barcode -->
                    <button
                      type="button"
                      class="dropdown-item"
                      @click="closeOrderDropdown(); printBagTickets([order.id])"
                    >
                      <span class="dropdown-item-icon">🖨️</span>
                      <span>Print Bag Ticket</span>
                    </button>

                    <!-- 4x6 Thermal Shipping Label (if booked) -->
                    <a
                      v-if="order.courier_consignment_id || order.courier_tracking_code"
                      :href="`/admin/orders/${order.id}/shipping-label`"
                      target="_blank"
                      class="dropdown-item"
                      @click="closeOrderDropdown"
                    >
                      <span class="dropdown-item-icon">🏷️</span>
                      <span>4x6 Shipping Label ↗</span>
                    </a>

                    <!-- Send to Courier (if not yet booked) -->
                    <button
                      v-if="!order.courier_consignment_id && !order.courier_tracking_code && order.status !== 'cancelled' && order.status !== 'delivered' && order.status !== 'returned'"
                      type="button"
                      class="dropdown-item dropdown-item-primary"
                      :disabled="isBookingCourier[order.id]"
                      @click="closeOrderDropdown(); bookCourierSingle(order)"
                    >
                      <span class="dropdown-item-icon">🚀</span>
                      <span>{{ isBookingCourier[order.id] ? 'Booking...' : `Send to ${selectedCourierName}` }}</span>
                    </button>

                    <div class="dropdown-divider"></div>

                    <!-- Quick Confirm Order (if pending) -->
                    <button
                      v-if="order.status === 'pending'"
                      type="button"
                      class="dropdown-item dropdown-item-success"
                      :disabled="isConfirming[order.id]"
                      @click="closeOrderDropdown(); confirmOrder(order)"
                    >
                      <span class="dropdown-item-icon">✓</span>
                      <span>{{ isConfirming[order.id] ? 'Confirming...' : 'Confirm Order' }}</span>
                    </button>

                    <!-- Quick Move to Processing & Pick (if confirmed) -->
                    <button
                      v-if="order.status === 'confirmed'"
                      type="button"
                      class="dropdown-item"
                      :disabled="isTransitioning[order.id]"
                      @click="closeOrderDropdown(); transitionOrder(order, 'processing', 'Moved to Processing & Pick from orders table')"
                    >
                      <span class="dropdown-item-icon">📦</span>
                      <span>Move to Processing & Pick</span>
                    </button>

                    <!-- Quick Assign With Courier (if processing) -->
                    <button
                      v-if="order.status === 'processing' || order.status === 'picked_and_packed'"
                      type="button"
                      class="dropdown-item"
                      :disabled="isTransitioning[order.id]"
                      @click="closeOrderDropdown(); transitionOrder(order, 'handed_to_courier', 'Handed over to courier from orders table')"
                    >
                      <span class="dropdown-item-icon">🚚</span>
                      <span>Assign With Courier</span>
                    </button>

                    <!-- Quick Mark Delivered (if with courier / out for delivery) -->
                    <button
                      v-if="order.status === 'handed_to_courier' || order.status === 'out_for_delivery'"
                      type="button"
                      class="dropdown-item dropdown-item-success"
                      :disabled="isTransitioning[order.id]"
                      @click="closeOrderDropdown(); transitionOrder(order, 'delivered', 'Marked delivered from orders table')"
                    >
                      <span class="dropdown-item-icon">🎉</span>
                      <span>Mark Delivered</span>
                    </button>

                    <!-- Quick Receive Returned Parcel & Restock (if handed_to_courier / out_for_delivery) -->
                    <button
                      v-if="order.status === 'handed_to_courier' || order.status === 'out_for_delivery'"
                      type="button"
                      class="dropdown-item dropdown-item-danger"
                      :disabled="isTransitioning[order.id]"
                      @click="closeOrderDropdown(); transitionOrder(order, 'returned', 'Courier returned parcel received back at warehouse')"
                    >
                      <span class="dropdown-item-icon">🔄</span>
                      <span>Receive Return & Restock</span>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-if="orders.data.length === 0">
              <td colspan="10" class="empty-state">
                <div class="empty-wrap">
                  <span class="empty-icon">📭</span>
                  <p>No orders found matching the selected filters.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Links -->
      <div v-if="orders.links.length > 3" class="pagination-wrap">
        <template v-for="(link, i) in orders.links" :key="i">
          <Link
            v-if="link.url"
            :href="link.url"
            class="pagination-link"
            :class="{ 'is-active': link.active }"
            v-html="link.label"
          />
          <span v-else class="pagination-link is-disabled" v-html="link.label" />
        </template>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.orders-management-page {
  padding: $space-6;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: $space-4;
    margin-bottom: $space-6;
    flex-wrap: wrap;

    .page-title {
      font-size: $fs-2xl;
      font-weight: 800;
      color: $color-text-main;
    }
    .page-subtitle {
      font-size: $fs-sm;
      color: $color-text-muted;
      margin-top: $space-1;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: $space-3;
      flex-wrap: wrap;
    }

    .btn-scan-handover {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      color: #ffffff;
      font-weight: 700;
      font-size: $fs-sm;
      padding: 8px 16px;
      border-radius: $radius-md;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
      transition: all 0.2s ease;

      &:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        transform: translateY(-1px);
        color: #ffffff;
      }
    }

    .btn-scan-returns {
      background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
      color: #ffffff;
      font-weight: 700;
      font-size: $fs-sm;
      padding: 8px 16px;
      border-radius: $radius-md;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(225, 29, 72, 0.25);
      transition: all 0.2s ease;

      &:hover {
        background: linear-gradient(135deg, #be123c 0%, #9f1239 100%);
        box-shadow: 0 4px 12px rgba(225, 29, 72, 0.35);
        transform: translateY(-1px);
        color: #ffffff;
      }
    }

    .btn-header-print {
      border-color: #cbd5e1;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;

      &:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
      }
    }
  }

  .bulk-actions-bar {
    position: sticky;
    top: 70px;
    z-index: 50;
    background: #0f172a;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: $radius-lg;
    margin-bottom: $space-4;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: $space-3;
    flex-wrap: wrap;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);

    .bulk-info {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 600;

      .bulk-count-badge {
        background: #eb286c;
        color: #ffffff;
        padding: 2px 8px;
        border-radius: $radius-full;
        font-weight: 800;
        font-size: 12px;
      }
    }

    .bulk-btn-group {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;

      .btn-print-bulk {
        background: #eb286c;
        color: #ffffff;
        border: none;
        padding: 7px 16px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease;

        &:hover {
          background: #d41f5e;
        }
      }

      .btn-confirm-bulk {
        background: #16a34a;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;

        &:hover:not(:disabled) {
          background: #15803d;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-process-bulk {
        background: #7c3aed;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;

        &:hover:not(:disabled) {
          background: #6d28d9;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-courier-bulk {
        background: #2563eb;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;

        &:hover:not(:disabled) {
          background: #1d4ed8;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-courier-bulk-send {
        background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        color: #ffffff;
        border: none;
        padding: 7px 16px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 6px rgba(219, 39, 119, 0.3);
        transition: all 0.15s ease;

        &:hover:not(:disabled) {
          background: linear-gradient(135deg, #db2777 0%, #be185d 100%);
          transform: translateY(-1px);
          box-shadow: 0 4px 10px rgba(219, 39, 119, 0.4);
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }

      .btn-labels-bulk {
        background: #334155;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        border-radius: $radius-md;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease;

        &:hover {
          background: #1e293b;
        }
      }

      .btn-deselect {
        background: transparent;
        color: #94a3b8;
        border: 1px solid #475569;
        padding: 6px 12px;
        border-radius: $radius-md;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;

        &:hover {
          color: #ffffff;
          border-color: #cbd5e1;
        }
      }
    }
  }

  .dispatch-overview-bar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: $radius-lg;
    padding: $space-4 $space-5;
    margin-bottom: $space-6;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);

    .overview-title-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: $space-4;
      flex-wrap: wrap;
      gap: $space-2;

      .overview-heading {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #1e293b;

        .heading-icon {
          font-size: 18px;
        }

        .heading-text {
          font-weight: 800;
        }

        .subtitle-text {
          font-size: 12px;
          color: #64748b;
          font-weight: 400;
        }
      }

      .active-filter-indicator {
        display: flex;
        align-items: center;
        gap: 8px;

        .indicator-tag {
          background: #fef3c7;
          color: #b45309;
          font-size: 11px;
          font-weight: 700;
          padding: 2px 8px;
          border-radius: $radius-full;
          border: 1px solid #fde68a;
        }

        .btn-clear-mini {
          background: transparent;
          border: 1px solid #cbd5e1;
          color: #64748b;
          font-size: 11px;
          font-weight: 600;
          padding: 2px 8px;
          border-radius: $radius-sm;
          cursor: pointer;
          &:hover {
            color: #b91c1c;
            border-color: #fca5a5;
          }
        }
      }
    }

    .overview-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: $space-3;

      .overview-card {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: $radius-md;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        cursor: pointer;
        transition: all 0.2s ease;

        &:hover {
          border-color: #94a3b8;
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        &.is-active {
          background: #eff6ff;
          border-color: #2563eb;
          box-shadow: 0 0 0 1px #2563eb;
        }

        .card-icon-wrap {
          font-size: 24px;
          display: flex;
          align-items: center;
          justify-content: center;
          width: 44px;
          height: 44px;
          border-radius: $radius-md;
          background: #ffffff;
          border: 1px solid #e2e8f0;
          flex-shrink: 0;
        }

        .card-body {
          flex: 1;
          min-width: 0;

          .card-num {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.1;
          }

          .card-title {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
          }

          .card-sub {
            font-size: 10px;
            color: #64748b;
          }

          .card-metrics-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            flex-wrap: wrap;

            .metric-pill {
              font-size: 10px;
              font-weight: 700;
              padding: 1px 5px;
              border-radius: $radius-sm;

              &.in-transit {
                background: #e0f2fe;
                color: #0369a1;
              }
              &.delivered {
                background: #dcfce7;
                color: #15803d;
              }
              &.returned {
                background: #fee2e2;
                color: #b91c1c;
              }
            }
          }
        }

        .card-badge {
          position: absolute;
          top: 8px;
          right: 8px;
          font-size: 9px;
          font-weight: 800;
          text-transform: uppercase;
          background: #ffffff;
          border: 1px solid #cbd5e1;
          color: #475569;
          padding: 2px 6px;
          border-radius: $radius-sm;
        }

        &.card-total {
          &.is-active {
            border-color: #0f172a;
            background: #f1f5f9;
          }
        }

        &.card-pending {
          &.is-active {
            border-color: #f59e0b;
            background: #fffbeb;
          }
        }
      }
    }
  }

  .status-tabs {
    display: flex;
    gap: $space-2;
    overflow-x: auto;
    padding-bottom: $space-2;
    margin-bottom: $space-6;
    border-bottom: 1px solid $color-border;

    .status-tab {
      background: $color-surface;
      border: 1px solid $color-border;
      border-radius: $radius-md;
      padding: $space-2 $space-4;
      font-size: $fs-xs;
      font-weight: 600;
      color: $color-text-muted;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: $space-2;
      white-space: nowrap;
      transition: all 0.15s ease;

      .tab-badge {
        background: $color-bg-subtle;
        padding: 2px 6px;
        border-radius: $radius-full;
        font-size: 11px;
      }

      &:hover {
        border-color: $color-primary;
        color: $color-primary;
      }

      &.is-active {
        background: $color-primary;
        border-color: $color-primary;
        color: white;

        .tab-badge {
          background: rgba(255, 255, 255, 0.25);
          color: white;
        }
      }
    }
  }

  .filters-panel {
    background: $color-surface;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    padding: $space-4 $space-5;
    margin-bottom: $space-6;
    display: flex;
    flex-direction: column;
    gap: $space-3;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);

    .filter-row {
      display: flex;
      align-items: center;
      gap: $space-3;
      flex-wrap: wrap;

      &.primary-row {
        justify-content: space-between;

        .search-wrap {
          display: flex;
          gap: $space-2;
          flex: 1;
          min-width: 260px;
          max-width: 420px;

          .input-search {
            flex: 1;
            padding: $space-2 $space-3;
            border: 1px solid $color-border;
            border-radius: $radius-md;
            font-size: $fs-sm;
            outline: none;

            &:focus {
              border-color: $color-primary;
            }
          }
        }

        .filter-group {
          display: flex;
          align-items: center;
          gap: $space-2;

          .filter-label {
            font-size: $fs-xs;
            font-weight: 700;
            color: $color-text-muted;
            white-space: nowrap;
          }

          .filter-select {
            padding: $space-2 $space-3;
            border: 1px solid $color-border;
            border-radius: $radius-md;
            font-size: $fs-sm;
            background: $color-surface;
            cursor: pointer;
          }
        }

        .courier-selector-wrap {
          display: flex;
          align-items: center;
          gap: $space-2;
          background: #fdf2f8;
          border: 1px solid #fbcfe8;
          padding: 6px 14px;
          border-radius: $radius-md;
          box-shadow: 0 1px 3px rgba(219, 39, 119, 0.08);

          .filter-label {
            font-size: $fs-xs;
            font-weight: 800;
            color: #be185d;
            white-space: nowrap;
          }

          .select-courier {
            padding: 5px 12px;
            border: 1px solid #f472b6;
            border-radius: $radius-sm;
            font-size: $fs-sm;
            font-weight: 700;
            background: #ffffff;
            color: #9d174d;
            outline: none;
            cursor: pointer;

            &:focus {
              border-color: #db2777;
              box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.2);
            }
          }
        }
      }

      &.secondary-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: $radius-md;
        padding: $space-3 $space-4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: $space-4;

        .date-filter-group {
          display: flex;
          align-items: center;
          gap: $space-2;
          flex-wrap: wrap;

          .filter-label {
            font-size: $fs-xs;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
          }

          .date-inputs {
            display: flex;
            align-items: center;
            gap: 6px;

            .input-date {
              padding: 5px 8px;
              border: 1px solid #cbd5e1;
              border-radius: $radius-sm;
              font-size: 12px;
              color: #1e293b;
              background: #ffffff;
            }

            .date-separator {
              font-size: 11px;
              color: #64748b;
              font-weight: 600;
            }
          }

          .date-presets {
            display: flex;
            align-items: center;
            gap: 4px;

            .btn-preset {
              background: #ffffff;
              border: 1px solid #cbd5e1;
              color: #475569;
              padding: 4px 8px;
              border-radius: $radius-sm;
              font-size: 11px;
              font-weight: 600;
              cursor: pointer;
              transition: all 0.15s ease;

              &:hover {
                border-color: #2563eb;
                color: #2563eb;
                background: #eff6ff;
              }

              &.is-clear {
                color: #b91c1c;
                border-color: #fca5a5;
                &:hover {
                  background: #fee2e2;
                }
              }
            }
          }
        }

        .product-filter-group {
          display: flex;
          align-items: center;
          gap: $space-2;
          flex-wrap: wrap;

          .filter-label {
            font-size: $fs-xs;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
          }

          .product-inputs {
            display: flex;
            align-items: center;
            gap: 6px;

            .select-product {
              max-width: 220px;
              padding: 5px 8px;
              border: 1px solid #cbd5e1;
              border-radius: $radius-sm;
              font-size: 12px;
              background: #ffffff;
            }

            .input-product-search {
              max-width: 160px;
              padding: 5px 8px;
              border: 1px solid #cbd5e1;
              border-radius: $radius-sm;
              font-size: 12px;
              background: #ffffff;
            }

            .btn-filter-apply {
              background: #0f172a;
              color: #ffffff;
              border: none;
              padding: 5px 12px;
              border-radius: $radius-sm;
              font-size: 12px;
              font-weight: 700;
              cursor: pointer;

              &:hover {
                background: #1e293b;
              }
            }

            .btn-filter-reset {
              background: transparent;
              color: #b91c1c;
              border: 1px solid #fca5a5;
              padding: 5px 10px;
              border-radius: $radius-sm;
              font-size: 12px;
              font-weight: 600;
              cursor: pointer;

              &:hover {
                background: #fee2e2;
              }
            }
          }
        }
      }

      .active-pills-bar {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        padding-top: $space-2;
        border-top: 1px dashed #e2e8f0;

        .pills-label {
          font-size: 11px;
          font-weight: 700;
          color: #64748b;
        }

        .filter-pill {
          background: #f1f5f9;
          border: 1px solid #cbd5e1;
          color: #1e293b;
          padding: 2px 8px;
          border-radius: $radius-full;
          font-size: 11px;
          font-weight: 600;
          display: inline-flex;
          align-items: center;
          gap: 5px;

          button {
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 11px;
            cursor: pointer;
            padding: 0;
            line-height: 1;

            &:hover {
              color: #dc2626;
            }
          }
        }

        .btn-clear-all {
          background: transparent;
          border: none;
          color: #b91c1c;
          font-size: 11px;
          font-weight: 700;
          cursor: pointer;
          text-decoration: underline;
          padding: 0 4px;
        }
      }
    }
  }

  .courier-notification-banner {
    margin-bottom: $space-5;
    padding: 12px 18px;
    border-radius: $radius-md;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
    font-weight: 600;

    &.is-success {
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      color: #065f46;
    }

    &.is-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }

    .banner-content {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .banner-link {
      color: #047857;
      text-decoration: underline;
      font-weight: 700;
      margin-left: 6px;
    }

    .banner-close {
      background: transparent;
      border: none;
      font-size: 16px;
      cursor: pointer;
      color: inherit;
      opacity: 0.7;
      &:hover {
        opacity: 1;
      }
    }
  }

  .table-container {
    background: $color-surface;
    border: 1px solid $color-border;
    border-radius: $radius-lg;
    overflow-x: auto;
    min-height: 400px;
    padding-bottom: 40px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: $fs-sm;

      th {
        background: $color-bg-subtle;
        padding: $space-3 $space-4;
        text-align: left;
        font-weight: 700;
        color: $color-text-muted;
        font-size: $fs-xs;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid $color-border;
      }

      td {
        padding: $space-4;
        border-bottom: 1px solid $color-border;
        vertical-align: middle;
      }

      .checkbox-cell {
        width: 40px;
        text-align: center;
        padding-left: $space-3;
        padding-right: $space-2;

        .checkbox-custom {
          width: 17px;
          height: 17px;
          cursor: pointer;
          accent-color: $color-primary;
          border-radius: $radius-sm;
        }
      }

      .order-row {
        transition: background 0.15s ease;

        &:hover {
          background: $color-bg-subtle;
        }

        &.is-selected {
          background: rgba($color-primary, 0.05);
        }
      }

      .actions-cell {
        position: relative;
        text-align: right;

        .action-dropdown-wrap {
          position: relative;
          display: inline-block;
          text-align: right;

          .btn-dropdown-trigger {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 6px 14px;
            border-radius: $radius-md;
            font-size: $fs-xs;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            white-space: nowrap;

            .chevron {
              font-size: 10px;
              transition: transform 0.15s ease;

              &.is-open {
                transform: rotate(180deg);
              }
            }

            &:hover,
            &.is-active {
              background: #eff6ff;
              border-color: #2563eb;
              color: #1d4ed8;
              box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15);
            }
          }

          .action-dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: $radius-lg;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
            min-width: 205px;
            z-index: 100;
            padding: 6px 0;
            text-align: left;
            animation: fadeIn 0.15s ease;

            .dropdown-item {
              display: flex;
              align-items: center;
              gap: 10px;
              padding: 8px 16px;
              font-size: $fs-xs;
              font-weight: 600;
              color: #334155;
              text-decoration: none;
              width: 100%;
              border: none;
              background: transparent;
              cursor: pointer;
              transition: background-color 0.15s ease, color 0.15s ease;
              white-space: nowrap;
              text-align: left;

              .dropdown-item-icon {
                font-size: 14px;
                width: 16px;
                display: inline-flex;
                justify-content: center;
              }

              &:hover {
                background-color: #f1f5f9;
                color: #0f172a;
              }

              &.dropdown-item-primary {
                color: #0284c7;
                font-weight: 700;
                &:hover {
                  background-color: #f0f9ff;
                  color: #0369a1;
                }
              }

              &.dropdown-item-success {
                color: #16a34a;
                font-weight: 700;
                &:hover {
                  background-color: #f0fdf4;
                  color: #15803d;
                }
              }

              &.dropdown-item-danger {
                color: #e11d48;
                font-weight: 700;
                &:hover {
                  background-color: #fff1f2;
                  color: #be123c;
                }
              }

              &:disabled {
                opacity: 0.5;
                cursor: not-allowed;
              }
            }

            .dropdown-divider {
              height: 1px;
              background-color: #f1f5f9;
              margin: 4px 0;
            }
          }
        }
      }

      .courier-badge-wrap {
        display: flex;
        flex-direction: column;
        gap: 4px;

        .courier-tag {
          display: inline-flex;
          align-items: center;
          gap: 4px;
          padding: 2px 8px;
          border-radius: $radius-sm;
          font-size: 10px;
          font-weight: 800;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          width: fit-content;

          &.courier-steadfast {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
          }

          &.courier-pathao {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
          }

          &.courier-redx {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
          }

          &.courier-default {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
          }
        }

        .courier-details {
          display: flex;
          align-items: center;
          gap: 6px;

          .tracking-code {
            font-size: 11px;
            color: #1e293b;
            font-weight: 600;
          }

          .label-link {
            font-size: 11px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            padding: 1px 6px;
            background: #eff6ff;
            border-radius: $radius-sm;
            border: 1px solid #bfdbfe;

            &:hover {
              background: #dbeafe;
              text-decoration: underline;
            }
          }
        }
      }

      .courier-unbooked {
        display: flex;
        align-items: center;
        gap: 6px;

        .unbooked-label {
          font-size: 11px;
          color: #94a3b8;
          font-style: italic;
        }

        .btn-courier-mini-send {
          background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
          color: #ffffff;
          border: none;
          padding: 3px 8px;
          border-radius: $radius-sm;
          font-size: 11px;
          font-weight: 700;
          cursor: pointer;
          box-shadow: 0 1px 3px rgba(219, 39, 119, 0.25);
          transition: all 0.15s ease;

          &:hover:not(:disabled) {
            background: linear-gradient(135deg, #db2777 0%, #be185d 100%);
          }

          &:disabled {
            opacity: 0.6;
            cursor: not-allowed;
          }
        }
      }

      .order-num-cell {
        vertical-align: middle;

        .order-link {
          color: $color-primary;
          text-decoration: none;
          display: inline-block;
          font-size: $fs-sm;

          &:hover {
            text-decoration: underline;
          }
        }

        .order-ticket-sub {
          margin-top: 4px;

          .ticket-badge-link {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 7px;
            border-radius: $radius-full;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            line-height: 1.3;
            transition: all 0.15s ease-in-out;
            white-space: nowrap;

            &.is-printed {
              background: #ecfdf5;
              color: #047857;
              border: 1px solid #a7f3d0;

              &:hover {
                background: #d1fae5;
                color: #065f46;
                border-color: #6ee7b7;
                box-shadow: 0 1px 3px rgba(4, 120, 87, 0.2);
              }
            }

            &.is-unprinted {
              background: #fffbeb;
              color: #b45309;
              border: 1px solid #fde68a;

              &:hover {
                background: #fef3c7;
                color: #92400e;
                border-color: #fcd34d;
                box-shadow: 0 1px 3px rgba(180, 83, 9, 0.2);
              }
            }
          }
        }
      }

      .customer-info {
        display: flex;
        flex-direction: column;
        .cust-name {
          font-weight: 600;
          color: $color-text-main;
        }
        .cust-phone {
          font-size: $fs-xs;
          color: $color-text-muted;
        }
      }

      .zone-pill {
        padding: 3px 8px;
        border-radius: $radius-sm;
        font-size: 11px;
        font-weight: 600;

        &.zone-inside_dhaka {
          background: rgba($color-success, 0.1);
          color: $color-success;
        }
        &.zone-dhaka_suburbs {
          background: rgba($color-warning, 0.15);
          color: #b45309;
        }
        &.zone-outside_dhaka {
          background: rgba(147, 51, 234, 0.1);
          color: #7e22ce;
        }
      }

      .ticket-status-wrap {
        display: flex;
        align-items: center;

        .print-count-badge {
          display: inline-flex;
          align-items: center;
          gap: 4px;
          padding: 3px 8px;
          border-radius: $radius-full;
          font-size: 11px;
          font-weight: 700;
          white-space: nowrap;

          &.is-printed {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
          }

          &.is-unprinted {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
          }
        }
      }

      .items-count-badge {
        font-size: $fs-xs;
        color: $color-text-muted;
      }

      .payment-method-badge {
        background: $color-bg-subtle;
        padding: 2px 6px;
        border-radius: $radius-sm;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.05em;
      }

      .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: $radius-full;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;

        &.badge-status-pending {
          background: #fef3c7;
          color: #b45309;
        }
        &.badge-status-confirmed {
          background: #e0f2fe;
          color: #0369a1;
        }
        &.badge-status-processing {
          background: #ede9fe;
          color: #6d28d9;
        }
        &.badge-status-picked-and-packed {
          background: #fae8ff;
          color: #86198f;
        }
        &.badge-status-handed-to-courier {
          background: #dbeafe;
          color: #1d4ed8;
        }
        &.badge-status-out-for-delivery {
          background: #ccfbf1;
          color: #0f766e;
        }
        &.badge-status-delivered {
          background: #dcfce7;
          color: #15803d;
        }
        &.badge-status-cancelled {
          background: #fee2e2;
          color: #b91c1c;
        }
        &.badge-status-returned {
          background: #ffe4e6;
          color: #be123c;
        }
      }

      .empty-state {
        text-align: center;
        padding: $space-12;

        .empty-wrap {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: $space-2;
          color: $color-text-muted;

          .empty-icon {
            font-size: 32px;
          }
        }
      }
    }
  }

  .pagination-wrap {
    display: flex;
    justify-content: center;
    gap: $space-1;
    margin-top: $space-6;

    .pagination-link {
      padding: $space-2 $space-3;
      border: 1px solid $color-border;
      border-radius: $radius-md;
      font-size: $fs-xs;
      color: $color-text-main;
      text-decoration: none;

      &.is-active {
        background: $color-primary;
        border-color: $color-primary;
        color: white;
      }

      &.is-disabled {
        color: $color-text-muted;
        cursor: not-allowed;
      }
    }
  }
}

:global(.swal-pink-popup) {
  background-color: #fff5f7 !important;
  border: 2px solid #fbcfe8 !important;
  border-radius: 16px !important;
  box-shadow: 0 20px 40px rgba(219, 39, 119, 0.18) !important;
}

:global(.swal-pink-popup .swal2-title) {
  color: #9d174d !important;
}
</style>
