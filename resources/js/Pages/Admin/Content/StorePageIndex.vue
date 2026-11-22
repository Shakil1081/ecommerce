<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface StorePageRow {
  id: number;
  slug: string;
  title: string;
  url: string;
  is_published: boolean;
  updated_at: string | null;
  updated_by: string | null;
}

defineProps<{
  pages: StorePageRow[];
}>();
</script>

<template>
  <AdminLayout title="Store Pages">
    <Head title="Store Pages" />

    <div class="pages-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">Store Pages</h1>
          <p class="header-subtitle">
            About, Contact and policy pages linked from the storefront footer. Payment gateways (bKash, Nagad,
            SSLCommerz) check these pages during merchant approval, so replace every [placeholder] before going live.
          </p>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Page</th>
                <th>Public URL</th>
                <th>Status</th>
                <th>Last updated</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="page in pages" :key="page.id">
                <td><strong>{{ page.title }}</strong></td>
                <td><a :href="page.url" target="_blank" rel="noopener" class="url-link">/{{ page.slug }}</a></td>
                <td>
                  <span class="badge" :class="page.is_published ? 'badge-success' : 'badge-secondary'">
                    {{ page.is_published ? 'Published' : 'Hidden' }}
                  </span>
                </td>
                <td class="muted">
                  {{ page.updated_at }}<template v-if="page.updated_by"> · {{ page.updated_by }}</template>
                </td>
                <td class="text-right">
                  <Link :href="`/admin/pages/${page.id}/edit`" class="btn btn-sm btn-secondary">Edit</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.pages-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
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

.table-card {
  padding: 0;
  overflow: hidden;
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;

  th {
    background: $color-surface-hover;
    padding: $spacing-3 $spacing-4;
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-text-muted;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid $color-border;
    white-space: nowrap;
  }

  td {
    padding: $spacing-4;
    border-bottom: 1px solid $color-border;
    font-size: $font-size-sm;
  }

  tr:last-child td {
    border-bottom: none;
  }

  .text-right {
    text-align: right;
  }

  .muted {
    color: $color-text-muted;
    font-size: $font-size-xs;
  }
}

.url-link {
  font-family: $font-mono;
  color: $color-primary;
  text-decoration: none;
}
</style>
