<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AccountShell from '@/Components/Account/AccountShell.vue';
import { formatBdt } from '@/Utils/format';

interface WishlistItem {
  product_id: string;
  name: string;
  slug: string;
  brand_name: string | null;
  min_price_bdt: string;
  image_url: string | null;
  added_at: string | null;
}

defineProps<{
  items: WishlistItem[];
}>();

const remove = (item: WishlistItem) => {
  router.post(`/account/wishlist/${item.product_id}`, {}, { preserveScroll: true });
};
</script>

<template>
  <StorefrontLayout title="Wishlist">
    <SeoHead title="My Wishlist" :noindex="true" />

    <AccountShell heading="Wishlist" :subheading="`${items.length} saved item${items.length === 1 ? '' : 's'}`">
      <div v-if="items.length === 0" class="card empty">
        <p class="heart" aria-hidden="true">♡</p>
        <p>Save products you love with the heart button on any product page.</p>
        <Link href="/shop" class="btn btn-primary">Browse products</Link>
      </div>

      <div v-else class="wish-grid">
        <div v-for="item in items" :key="item.product_id" class="card wish-card">
          <Link :href="`/products/${item.slug}`" class="thumb">
            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy">
            <span v-else class="no-img" aria-hidden="true">🛍️</span>
          </Link>
          <div class="info">
            <span v-if="item.brand_name" class="brand">{{ item.brand_name }}</span>
            <Link :href="`/products/${item.slug}`" class="name">{{ item.name }}</Link>
            <span class="price">From {{ formatBdt(item.min_price_bdt) }}</span>
          </div>
          <div class="actions">
            <Link :href="`/products/${item.slug}`" class="btn btn-primary btn-sm">View product</Link>
            <button type="button" class="btn btn-ghost btn-sm" @click="remove(item)">Remove</button>
          </div>
        </div>
      </div>
    </AccountShell>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.empty {
  padding: $space-10;
  text-align: center;
  color: $color-neutral-500;

  .heart {
    font-size: 2.5rem;
    color: $color-primary-400;
    margin-bottom: $space-2;
  }

  p {
    margin-bottom: $space-4;
  }
}

.wish-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: $space-4;

  @media (max-width: 480px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: $space-3;
  }
}

.wish-card {
  display: flex;
  flex-direction: column;
  overflow: hidden;

  .thumb {
    @include flex-center;
    aspect-ratio: 1 / 1;
    background: $color-neutral-50;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .no-img {
      font-size: 2rem;
    }
  }

  .info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: $space-3 $space-4 0;
  }

  .brand {
    font-size: $fs-2xs;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: $color-neutral-500;
  }

  .name {
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    color: $color-neutral-900;
    text-decoration: none;
    @include line-clamp(2);
  }

  .price {
    font-size: $fs-sm;
    color: $color-primary-700;
    font-weight: $fw-semibold;
  }

  .actions {
    display: flex;
    gap: $space-2;
    padding: $space-3 $space-4 $space-4;
    flex-wrap: wrap;
  }
}
</style>
