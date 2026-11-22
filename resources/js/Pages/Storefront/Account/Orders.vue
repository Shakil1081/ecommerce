<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AccountShell from '@/Components/Account/AccountShell.vue';
import OrderStatusBadge from '@/Components/Account/OrderStatusBadge.vue';
import { formatBdt, paymentMethodLabel } from '@/Utils/format';

interface OrderRow {
  order_number: string;
  status: string;
  payment_method: string;
  total_bdt: string;
  items_count: number | null;
  placed_at: string | null;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

// Laravel pagination labels contain &laquo; / &raquo; entities; decode as plain text (no v-html)
const pageLabel = (label: string): string => label.replace('&laquo;', '«').replace('&raquo;', '»');

defineProps<{
  orders: {
    data: OrderRow[];
    links: PaginationLink[];
    total: number;
  };
}>();
</script>

<template>
  <StorefrontLayout title="My Orders">
    <SeoHead title="My Orders" :noindex="true" />

    <AccountShell heading="My Orders" :subheading="`${orders.total} order${orders.total === 1 ? '' : 's'}`">
      <div v-if="orders.data.length === 0" class="card empty">
        <p>You haven't placed any orders yet.</p>
        <Link href="/shop" class="btn btn-primary">Start shopping</Link>
      </div>

      <div v-else class="order-cards">
        <Link
          v-for="order in orders.data"
          :key="order.order_number"
          :href="`/account/orders/${order.order_number}`"
          class="order-card card"
        >
          <div class="top">
            <div>
              <strong class="order-no">{{ order.order_number }}</strong>
              <span class="meta">
                Placed {{ order.placed_at }}
                <template v-if="order.items_count"> · {{ order.items_count }} item{{ order.items_count === 1 ? '' : 's' }}</template>
              </span>
            </div>
            <OrderStatusBadge :status="order.status" />
          </div>
          <div class="bottom">
            <span class="pay">{{ paymentMethodLabel(order.payment_method) }}</span>
            <span class="total">{{ formatBdt(order.total_bdt) }}</span>
          </div>
        </Link>
      </div>

      <nav v-if="orders.links.length > 3" class="pagination" aria-label="Pagination">
        <template v-for="(link, index) in orders.links" :key="index">
          <Link
            v-if="link.url"
            :href="link.url"
            class="page-link"
            :class="{ active: link.active }"
            preserve-scroll
          >
            {{ pageLabel(link.label) }}
          </Link>
          <span v-else class="page-link disabled">{{ pageLabel(link.label) }}</span>
        </template>
      </nav>
    </AccountShell>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.empty {
  padding: $space-10;
  text-align: center;
  color: $color-neutral-500;

  p {
    margin-bottom: $space-4;
  }
}

.order-cards {
  display: flex;
  flex-direction: column;
  gap: $space-3;
}

.order-card {
  padding: $space-4 $space-5;
  text-decoration: none;
  color: inherit;
  transition: box-shadow $transition-fast;

  &:hover {
    box-shadow: $shadow-md;
  }

  .top {
    @include flex-between;
    align-items: flex-start;
    gap: $space-3;
  }

  .order-no {
    display: block;
    font-family: $font-mono;
    font-size: $fs-sm;
    color: $color-neutral-900;
  }

  .meta {
    font-size: $fs-xs;
    color: $color-neutral-500;
  }

  .bottom {
    @include flex-between;
    margin-top: $space-3;
    padding-top: $space-3;
    border-top: 1px solid $color-neutral-100;
    font-size: $fs-sm;
  }

  .pay {
    color: $color-neutral-600;
  }

  .total {
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }
}

.pagination {
  display: flex;
  flex-wrap: wrap;
  gap: $space-1;
  margin-top: $space-6;
  justify-content: center;

  .page-link {
    min-width: 36px;
    padding: 6px 10px;
    border-radius: $radius-md;
    font-size: $fs-sm;
    text-align: center;
    color: $color-neutral-700;
    text-decoration: none;
    border: 1px solid $color-neutral-200;
    background: $color-neutral-0;

    &.active {
      background: $color-primary-600;
      border-color: $color-primary-600;
      color: $color-neutral-0;
    }

    &.disabled {
      color: $color-neutral-400;
    }
  }
}
</style>
