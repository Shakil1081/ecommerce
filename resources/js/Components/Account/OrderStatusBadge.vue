<script setup lang="ts">
import { computed } from 'vue';
import { orderStatusLabel } from '@/Utils/format';

const props = defineProps<{
  status: string;
}>();

const tone = computed(() => {
  if (['delivered'].includes(props.status)) return 'success';
  if (['cancelled', 'returned'].includes(props.status)) return 'muted';
  if (['handed_to_courier', 'out_for_delivery'].includes(props.status)) return 'info';
  return 'warning';
});
</script>

<template>
  <span class="status-badge" :class="`tone-${tone}`">{{ orderStatusLabel(status) }}</span>
</template>

<style lang="scss" scoped>
.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: $radius-full;
  font-size: $fs-xs;
  font-weight: $fw-semibold;
  white-space: nowrap;

  &.tone-success { background: rgba(22, 163, 74, 0.12); color: #15803d; }
  &.tone-info { background: rgba(37, 99, 235, 0.1); color: #1d4ed8; }
  &.tone-warning { background: rgba(217, 119, 6, 0.12); color: #b45309; }
  &.tone-muted { background: $color-neutral-100; color: $color-neutral-600; }
}
</style>
