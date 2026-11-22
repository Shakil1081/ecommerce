<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  page: {
    id: number;
    slug: string;
    title: string;
    meta_description: string | null;
    body: string;
    is_published: boolean;
    url: string;
  };
}>();

const form = useForm({
  title: props.page.title,
  meta_description: props.page.meta_description ?? '',
  body: props.page.body,
  is_published: props.page.is_published,
});

const placeholderCount = computed(() => (form.body.match(/\[[^\]]+\]/g) ?? []).length);

const submit = () => form.put(`/admin/pages/${props.page.id}`);
</script>

<template>
  <AdminLayout :title="`Edit: ${page.title}`">
    <Head :title="`Edit ${page.title}`" />

    <form class="page-form" @submit.prevent="submit">
      <div class="form-header">
        <div>
          <Link href="/admin/pages" class="back-link">← Store Pages</Link>
          <h1 class="header-title">{{ page.title }}</h1>
          <a :href="page.url" target="_blank" rel="noopener" class="url-link">{{ page.url }}</a>
        </div>
        <div class="header-actions">
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Save page' }}
          </button>
        </div>
      </div>

      <div v-if="placeholderCount > 0" class="warning-box">
        ⚠️ This page still has {{ placeholderCount }} [placeholder] value{{ placeholderCount === 1 ? '' : 's' }}
        (for example your phone number, address or BIN). Replace them before going live.
      </div>

      <div class="card form-card">
        <div class="form-group">
          <label class="form-label" for="title">Page title</label>
          <input id="title" v-model="form.title" class="form-control" type="text" maxlength="150" required>
          <span v-if="form.errors.title" class="form-error">{{ form.errors.title }}</span>
        </div>

        <div class="form-group">
          <label class="form-label" for="meta_description">Search description (shown on Google)</label>
          <input id="meta_description" v-model="form.meta_description" class="form-control" type="text" maxlength="300">
          <span class="form-hint">{{ form.meta_description.length }}/300 characters. Aim for 120–160.</span>
          <span v-if="form.errors.meta_description" class="form-error">{{ form.errors.meta_description }}</span>
        </div>

        <div class="form-group">
          <label class="form-label" for="body">Content</label>
          <textarea id="body" v-model="form.body" class="form-control body-input" rows="22" required />
          <span class="form-hint">
            Formatting: start a line with <code>## </code> for a heading, <code>- </code> for a bullet point,
            and leave a blank line between paragraphs.
          </span>
          <span v-if="form.errors.body" class="form-error">{{ form.errors.body }}</span>
        </div>

        <label class="check-row">
          <input v-model="form.is_published" type="checkbox">
          Published (visible on the storefront)
        </label>
      </div>
    </form>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.page-form {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
  max-width: 960px;
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: $spacing-4;
  flex-wrap: wrap;

  .back-link {
    font-size: $font-size-sm;
    color: $color-text-muted;
    text-decoration: none;
  }

  .header-title {
    font-size: $font-size-2xl;
    font-weight: 700;
    color: $color-text-main;
    margin: $spacing-1 0;
  }
}

.url-link {
  font-family: $font-mono;
  font-size: $font-size-xs;
  color: $color-primary;
}

.warning-box {
  padding: $spacing-3 $spacing-4;
  border-radius: $radius-md;
  background: rgba(217, 119, 6, 0.1);
  color: #92400e;
  font-size: $font-size-sm;
}

.form-card {
  padding: $spacing-6;
  display: flex;
  flex-direction: column;
  gap: $spacing-5;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: $spacing-2;
}

.form-label {
  font-size: $font-size-sm;
  font-weight: 600;
  color: $color-text-main;
}

.form-control {
  padding: $spacing-3 $spacing-4;
  border-radius: $radius-md;
  border: 1px solid $color-border;
  background: $color-surface;
  color: $color-text-main;
  font-size: $font-size-sm;
  font-family: inherit;
  outline: none;
  transition: border-color $transition-fast;

  &:focus {
    border-color: $color-primary;
  }
}

.body-input {
  font-family: $font-mono;
  line-height: 1.6;
  resize: vertical;
}

.form-hint {
  font-size: $font-size-xs;
  color: $color-text-muted;

  code {
    background: $color-surface-hover;
    padding: 1px 5px;
    border-radius: $radius-sm;
  }
}

.form-error {
  font-size: $font-size-xs;
  color: $color-danger;
}

.check-row {
  display: flex;
  align-items: center;
  gap: $spacing-2;
  font-size: $font-size-sm;
  color: $color-text-main;
}
</style>
