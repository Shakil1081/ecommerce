<script setup lang="ts">
defineProps<{
  title: string;
  subtitle?: string;
}>();
</script>

<template>
  <div class="auth-page container">
    <div class="auth-card card">
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="subtitle">{{ subtitle }}</p>
      <slot />
      <div v-if="$slots.footer" class="auth-footer">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.auth-page {
  @include flex-center;
  padding-top: $space-10;
  padding-bottom: $space-16;

  @media (max-width: 640px) {
    padding-top: $space-5;
  }
}

.auth-card {
  width: 100%;
  max-width: 440px;
  padding: $space-8;

  @media (max-width: 640px) {
    padding: $space-5;
  }

  h1 {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }

  .subtitle {
    font-size: $fs-sm;
    color: $color-neutral-500;
    margin-top: $space-1;
    margin-bottom: $space-6;
  }

  :deep(.btn-block) {
    width: 100%;
  }
}

.auth-footer {
  margin-top: $space-6;
  padding-top: $space-4;
  border-top: 1px solid $color-neutral-200;
  text-align: center;
  font-size: $fs-sm;
  color: $color-neutral-600;

  :deep(a) {
    color: $color-primary-600;
    font-weight: $fw-semibold;
    text-decoration: none;
  }
}
</style>
