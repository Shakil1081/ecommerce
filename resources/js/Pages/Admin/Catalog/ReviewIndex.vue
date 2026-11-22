<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface ReviewRow {
  id: string;
  product_name: string | null;
  product_slug: string | null;
  customer_name: string;
  customer_email: string | null;
  rating: number;
  title: string | null;
  review_text: string;
  is_verified_purchase: boolean;
  status: 'pending' | 'approved' | 'rejected';
  created_at: string | null;
  moderated_by: string | null;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const props = defineProps<{
  reviews: { data: ReviewRow[]; links: PaginationLink[]; total: number };
  status: 'pending' | 'approved' | 'rejected';
  counts: { pending: number; approved: number; rejected: number };
}>();

const tabs = computed(() => [
  { key: 'pending', label: 'Waiting for review', count: props.counts.pending },
  { key: 'approved', label: 'Approved', count: props.counts.approved },
  { key: 'rejected', label: 'Rejected', count: props.counts.rejected },
]);

const stars = (rating: number): string => '★'.repeat(rating) + '☆'.repeat(5 - rating);

const moderate = (review: ReviewRow, status: 'approved' | 'rejected') => {
  router.post(`/admin/reviews/${review.id}/moderate`, { status }, { preserveScroll: true });
};

const pageLabel = (label: string): string => label.replace('&laquo;', '«').replace('&raquo;', '»');
</script>

<template>
  <AdminLayout title="Product Reviews">
    <Head title="Product Reviews" />

    <div class="reviews-container">
      <div class="page-header">
        <h1 class="header-title">Product Reviews</h1>
        <p class="header-subtitle">
          Customer reviews appear on the product page only after approval. "Verified purchase" means the customer
          has a delivered order containing the product.
        </p>
      </div>

      <div class="tabs">
        <Link
          v-for="tab in tabs"
          :key="tab.key"
          :href="`/admin/reviews?status=${tab.key}`"
          class="tab"
          :class="{ active: status === tab.key }"
          preserve-state
        >
          {{ tab.label }} <span class="count">{{ tab.count }}</span>
        </Link>
      </div>

      <div v-if="reviews.data.length === 0" class="card empty">No reviews here.</div>

      <div v-else class="review-list">
        <article v-for="review in reviews.data" :key="review.id" class="card review">
          <div class="review-top">
            <div>
              <span class="stars" :aria-label="`${review.rating} out of 5`">{{ stars(review.rating) }}</span>
              <strong v-if="review.title" class="title">{{ review.title }}</strong>
            </div>
            <span class="date">{{ review.created_at }}</span>
          </div>

          <p class="text">{{ review.review_text }}</p>

          <div class="meta">
            <span>
              <strong>{{ review.customer_name }}</strong>
              <template v-if="review.customer_email"> · {{ review.customer_email }}</template>
            </span>
            <span v-if="review.is_verified_purchase" class="verified">✓ Verified purchase</span>
            <span>
              on
              <a v-if="review.product_slug" :href="`/products/${review.product_slug}`" target="_blank" rel="noopener">{{ review.product_name }}</a>
              <template v-else>{{ review.product_name ?? 'Deleted product' }}</template>
            </span>
            <span v-if="review.moderated_by" class="muted">Moderated by {{ review.moderated_by }}</span>
          </div>

          <!-- Moderation is enforced server-side by permission:catalog.edit -->
          <div class="actions">
            <button v-if="review.status !== 'approved'" type="button" class="btn btn-sm btn-primary" @click="moderate(review, 'approved')">
              Approve
            </button>
            <button v-if="review.status !== 'rejected'" type="button" class="btn btn-sm btn-danger" @click="moderate(review, 'rejected')">
              Reject
            </button>
          </div>
        </article>
      </div>

      <nav v-if="reviews.links.length > 3" class="pagination" aria-label="Pagination">
        <template v-for="(link, index) in reviews.links" :key="index">
          <Link v-if="link.url" :href="link.url" class="page-link" :class="{ active: link.active }" preserve-scroll>
            {{ pageLabel(link.label) }}
          </Link>
          <span v-else class="page-link disabled">{{ pageLabel(link.label) }}</span>
        </template>
      </nav>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.reviews-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-5;
}

.page-header {
  .header-title {
    font-size: $font-size-2xl;
    font-weight: 700;
    color: $color-text-main;
    margin-bottom: $spacing-1;
  }

  .header-subtitle {
    font-size: $font-size-sm;
    color: $color-text-muted;
    max-width: 720px;
  }
}

.tabs {
  display: flex;
  gap: $spacing-2;
  flex-wrap: wrap;

  .tab {
    padding: $spacing-2 $spacing-4;
    border-radius: $radius-full;
    border: 1px solid $color-border;
    background: $color-surface;
    font-size: $font-size-sm;
    color: $color-text-muted;
    text-decoration: none;

    &.active {
      border-color: $color-primary;
      color: $color-primary;
      font-weight: 600;
    }

    .count {
      margin-left: $spacing-1;
      font-size: $font-size-xs;
      opacity: 0.8;
    }
  }
}

.empty {
  padding: $spacing-8;
  text-align: center;
  color: $color-text-muted;
}

.review-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-3;
}

.review {
  padding: $spacing-5;

  .review-top {
    display: flex;
    justify-content: space-between;
    gap: $spacing-3;
    align-items: flex-start;
  }

  .stars {
    color: #d97706;
    letter-spacing: 1px;
    margin-right: $spacing-2;
  }

  .title {
    color: $color-text-main;
    font-size: $font-size-sm;
  }

  .date {
    font-size: $font-size-xs;
    color: $color-text-muted;
    white-space: nowrap;
  }

  .text {
    margin: $spacing-3 0;
    font-size: $font-size-sm;
    color: $color-text-main;
    line-height: 1.6;
    white-space: pre-line;
  }

  .meta {
    display: flex;
    flex-wrap: wrap;
    gap: $spacing-2 $spacing-4;
    font-size: $font-size-xs;
    color: $color-text-muted;

    a {
      color: $color-primary;
    }
  }

  .verified {
    color: #15803d;
    font-weight: 600;
  }

  .actions {
    display: flex;
    gap: $spacing-2;
    margin-top: $spacing-4;
  }
}

.pagination {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-1;
  justify-content: center;

  .page-link {
    min-width: 34px;
    padding: 6px 10px;
    border-radius: $radius-md;
    border: 1px solid $color-border;
    font-size: $font-size-sm;
    text-align: center;
    color: $color-text-main;
    text-decoration: none;

    &.active {
      background: $color-primary;
      border-color: $color-primary;
      color: #fff;
    }

    &.disabled {
      color: $color-text-muted;
    }
  }
}
</style>
