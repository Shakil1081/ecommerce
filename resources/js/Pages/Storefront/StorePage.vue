<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';

type Block =
  | { type: 'heading'; text: string }
  | { type: 'paragraph'; text: string }
  | { type: 'list'; items: string[] };

defineProps<{
  page: {
    slug: string;
    title: string;
    meta_description: string | null;
    blocks: Block[];
    updated_at: string | null;
  };
  seo: {
    canonical: string;
    json_ld: Record<string, unknown>[];
  };
}>();

const relatedLinks = [
  { slug: 'about', label: 'About Us' },
  { slug: 'contact', label: 'Contact' },
  { slug: 'shipping', label: 'Shipping & Delivery' },
  { slug: 'returns', label: 'Return & Refund Policy' },
  { slug: 'privacy', label: 'Privacy Policy' },
  { slug: 'terms', label: 'Terms & Conditions' },
];
</script>

<template>
  <StorefrontLayout :title="page.title">
    <SeoHead
      :title="page.title"
      :description="page.meta_description"
      :canonical="seo.canonical"
      :json-ld="seo.json_ld"
    />

    <div class="store-page container">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <Link href="/">Home</Link>
        <span aria-hidden="true">/</span>
        <span>{{ page.title }}</span>
      </nav>

      <div class="page-grid">
        <article class="page-article card">
          <h1>{{ page.title }}</h1>
          <p v-if="page.updated_at" class="updated">Last updated: {{ page.updated_at }}</p>

          <template v-for="(block, index) in page.blocks" :key="index">
            <h2 v-if="block.type === 'heading'">{{ block.text }}</h2>
            <p v-else-if="block.type === 'paragraph'">{{ block.text }}</p>
            <ul v-else>
              <li v-for="(item, i) in block.items" :key="i">{{ item }}</li>
            </ul>
          </template>
        </article>

        <aside class="page-aside">
          <div class="card aside-card">
            <h3>Customer help</h3>
            <ul>
              <li v-for="link in relatedLinks" :key="link.slug">
                <Link :href="`/${link.slug}`" :class="{ current: link.slug === page.slug }">{{ link.label }}</Link>
              </li>
              <li><Link href="/track-order">Track your order</Link></li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.store-page {
  padding-top: $space-6;
  padding-bottom: $space-12;
}

.breadcrumb {
  display: flex;
  gap: $space-2;
  font-size: $fs-sm;
  color: $color-neutral-500;
  margin-bottom: $space-4;

  a {
    color: $color-neutral-600;
    text-decoration: none;

    &:hover {
      color: $color-primary-600;
    }
  }
}

.page-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  gap: $space-6;
  align-items: start;

  @media (max-width: 860px) {
    grid-template-columns: 1fr;
  }
}

.page-article {
  padding: $space-8;
  max-width: 820px;

  @media (max-width: 640px) {
    padding: $space-5;
  }

  h1 {
    font-size: $fs-3xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    line-height: $lh-tight;

    @media (max-width: 640px) {
      font-size: $fs-2xl;
    }
  }

  .updated {
    font-size: $fs-xs;
    color: $color-neutral-500;
    margin-top: $space-2;
    margin-bottom: $space-6;
  }

  h2 {
    font-size: $fs-lg;
    font-weight: $fw-semibold;
    color: $color-neutral-900;
    margin-top: $space-6;
    margin-bottom: $space-2;
  }

  p,
  li {
    font-size: $fs-base;
    line-height: $lh-relaxed;
    color: $color-neutral-700;
  }

  p {
    margin-bottom: $space-3;
  }

  ul {
    padding-left: $space-5;
    margin-bottom: $space-3;
    list-style: disc;

    li {
      margin-bottom: $space-1;
    }
  }
}

.aside-card {
  padding: $space-5;
  position: sticky;
  top: 100px;

  h3 {
    font-size: $fs-sm;
    font-weight: $fw-bold;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: $color-neutral-500;
    margin-bottom: $space-3;
  }

  ul {
    display: flex;
    flex-direction: column;
    gap: $space-2;
  }

  a {
    font-size: $fs-sm;
    color: $color-neutral-700;
    text-decoration: none;

    &:hover,
    &.current {
      color: $color-primary-600;
    }

    &.current {
      font-weight: $fw-semibold;
    }
  }
}
</style>
