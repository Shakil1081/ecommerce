<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AccountShell from '@/Components/Account/AccountShell.vue';
import OrderStatusBadge from '@/Components/Account/OrderStatusBadge.vue';
import { formatBdt } from '@/Utils/format';

interface OrderRow {
  order_number: string;
  status: string;
  payment_method: string;
  total_bdt: string;
  placed_at: string | null;
}

defineProps<{
  customer: { name: string; email: string; phone: string | null; member_since: string | null };
  stats: { orders: number; active_orders: number; wishlist: number; addresses: number };
  recentOrders: OrderRow[];
}>();
</script>

<template>
  <StorefrontLayout title="My Account">
    <SeoHead title="My Account" :noindex="true" />

    <AccountShell heading="My Account" :subheading="customer.member_since ? `Member since ${customer.member_since}` : undefined">
      <div class="stats-grid">
        <Link href="/account/orders" class="stat card">
          <span class="stat-value">{{ stats.orders }}</span>
          <span class="stat-label">Total orders</span>
        </Link>
        <Link href="/account/orders" class="stat card">
          <span class="stat-value">{{ stats.active_orders }}</span>
          <span class="stat-label">In progress</span>
        </Link>
        <Link href="/account/wishlist" class="stat card">
          <span class="stat-value">{{ stats.wishlist }}</span>
          <span class="stat-label">Wishlist</span>
        </Link>
        <Link href="/account/addresses" class="stat card">
          <span class="stat-value">{{ stats.addresses }}</span>
          <span class="stat-label">Saved addresses</span>
        </Link>
      </div>

      <div class="card panel">
        <div class="panel-header">
          <h2>Recent orders</h2>
          <Link v-if="recentOrders.length" href="/account/orders" class="panel-link">View all</Link>
        </div>

        <div v-if="recentOrders.length === 0" class="empty">
          <p>You haven't placed any orders yet.</p>
          <Link href="/shop" class="btn btn-primary">Start shopping</Link>
        </div>

        <ul v-else class="order-list">
          <li v-for="order in recentOrders" :key="order.order_number">
            <Link :href="`/account/orders/${order.order_number}`" class="order-row">
              <div>
                <strong class="order-no">{{ order.order_number }}</strong>
                <span class="order-date">{{ order.placed_at }}</span>
              </div>
              <OrderStatusBadge :status="order.status" />
              <span class="order-total">{{ formatBdt(order.total_bdt) }}</span>
            </Link>
          </li>
        </ul>
      </div>

      <div class="card panel profile-panel">
        <div class="panel-header">
          <h2>Account details</h2>
          <Link href="/account/profile" class="panel-link">Edit</Link>
        </div>
        <dl class="details">
          <dt>Name</dt><dd>{{ customer.name }}</dd>
          <dt>Mobile</dt><dd>{{ customer.phone || '—' }}</dd>
          <dt>Email</dt><dd>{{ customer.email }}</dd>
        </dl>
      </div>
    </AccountShell>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: $space-4;
  margin-bottom: $space-6;

  @media (max-width: 640px) {
    grid-template-columns: repeat(2, 1fr);
    gap: $space-3;
  }
}

.stat {
  display: flex;
  flex-direction: column;
  padding: $space-4;
  text-decoration: none;
  transition: box-shadow $transition-fast;

  &:hover {
    box-shadow: $shadow-md;
  }

  .stat-value {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }

  .stat-label {
    font-size: $fs-xs;
    color: $color-neutral-500;
  }
}

.panel {
  padding: $space-5;
  margin-bottom: $space-6;

  .panel-header {
    @include flex-between;
    margin-bottom: $space-4;

    h2 {
      font-size: $fs-lg;
      font-weight: $fw-semibold;
      color: $color-neutral-900;
    }
  }

  .panel-link {
    font-size: $fs-sm;
    color: $color-primary-600;
    text-decoration: none;
    font-weight: $fw-semibold;
  }
}

.empty {
  text-align: center;
  padding: $space-6 0;
  color: $color-neutral-500;

  p {
    margin-bottom: $space-4;
  }
}

.order-list li + li {
  border-top: 1px solid $color-neutral-100;
}

.order-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: $space-4;
  padding: $space-3 0;
  text-decoration: none;
  color: inherit;

  @media (max-width: 480px) {
    grid-template-columns: 1fr auto;

    .order-total {
      grid-column: 1 / -1;
    }
  }

  .order-no {
    display: block;
    font-family: $font-mono;
    font-size: $fs-sm;
    color: $color-neutral-900;
  }

  .order-date {
    font-size: $fs-xs;
    color: $color-neutral-500;
  }

  .order-total {
    font-weight: $fw-semibold;
    color: $color-neutral-900;
  }
}

.details {
  display: grid;
  grid-template-columns: 90px 1fr;
  gap: $space-2 $space-4;
  font-size: $fs-sm;

  dt {
    color: $color-neutral-500;
  }

  dd {
    color: $color-neutral-900;
    word-break: break-word;
  }
}
</style>
