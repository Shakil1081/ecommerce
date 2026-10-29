<script setup lang="ts">
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps<{
  laravelVersion: string;
  phpVersion: string;
}>();
</script>

<template>
  <StorefrontLayout title="Enterprise Hybrid Trading Storefront">
    <div class="container hero-section">
      <div class="hero-card card">
        <div class="card-body">
          <span class="badge badge-primary">Turnkey Commercial E-Commerce</span>
          <h1 class="hero-title">Dual Sourcing (Global Import + Local Procurement)</h1>
          <p class="hero-desc">
            Direct B2C & B2B trading with unified Landed Cost, NBR statutory VAT (Mushak 6.3), 
            and 7-Layer anti-fraud protection.
          </p>
          <div class="hero-badges">
            <div class="metric-card">
              <span class="metric-label">Framework</span>
              <span class="metric-value">Laravel {{ laravelVersion }}</span>
            </div>
            <div class="metric-card">
              <span class="metric-label">Runtime</span>
              <span class="metric-value">PHP {{ phpVersion }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.hero-section {
  padding: $space-12 0;
}

.hero-card {
  max-width: 800px;
  margin: 0 auto;
  text-align: center;
}

.hero-title {
  margin-top: $space-4;
  margin-bottom: $space-4;
}

.hero-desc {
  font-size: $fs-lg;
  color: $color-neutral-600;
  margin-bottom: $space-6;
}

.hero-badges {
  display: flex;
  justify-content: center;
  gap: $space-6;
}
</style>

