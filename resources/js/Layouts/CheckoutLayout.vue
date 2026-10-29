<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
  title?: string;
}>();
</script>

<template>
  <div class="checkout-shell">
    <Head :title="title ? `${title} - Secure Checkout` : 'Secure Checkout'" />

    <!-- Minimal Distraction-Free Header -->
    <header class="checkout-header">
      <div class="container checkout-header-container">
        <Link href="/" class="checkout-logo">
          <span class="logo-accent">E</span>COMMERCE
        </Link>

        <div class="checkout-security-badge">
          <span class="lock-icon">🔒</span>
          <span class="security-text">256-Bit SSL Encrypted Checkout</span>
        </div>

        <div class="checkout-support">
          <span class="phone-icon">📞</span>
          <span class="support-text">Helpline: +880 1700-000000</span>
        </div>
      </div>
    </header>

    <!-- Main Checkout Container -->
    <main class="checkout-main">
      <div class="container">
        <slot />
      </div>
    </main>

    <!-- Minimal Checkout Footer -->
    <footer class="checkout-footer">
      <div class="container footer-content">
        <p>&copy; 2026 Enterprise E-Commerce Platform. All transactions are secure and encrypted.</p>
        <div class="checkout-footer-links">
          <Link href="/privacy">Privacy Policy</Link>
          <Link href="/terms">Terms of Service</Link>
          <Link href="/returns">Return Policy</Link>
        </div>
      </div>
    </footer>
  </div>
</template>

<style lang="scss" scoped>
.checkout-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: $color-neutral-100;
}

.checkout-header {
  background-color: $color-neutral-0;
  border-bottom: 1px solid $color-neutral-200;
  padding: $space-4 0;

  .checkout-header-container {
    @include flex-between;
  }

  .checkout-logo {
    font-family: $font-heading;
    font-size: $fs-2xl;
    font-weight: $fw-extrabold;
    color: $color-neutral-900;
    letter-spacing: -0.03em;

    .logo-accent {
      color: $color-primary-600;
    }
  }

  .checkout-security-badge {
    @include flex-center;
    gap: $space-2;
    font-size: $fs-xs;
    font-weight: $fw-semibold;
    color: $color-primary-700;
    background-color: $color-primary-50;
    padding: $space-1 $space-3;
    border-radius: $radius-full;
    border: 1px solid $color-primary-200;

    @include media-down(sm) {
      display: none;
    }
  }

  .checkout-support {
    font-size: $fs-xs;
    font-weight: $fw-medium;
    color: $color-neutral-600;
  }
}

.checkout-main {
  flex: 1;
  padding: $space-8 0;
}

.checkout-footer {
  background-color: $color-neutral-0;
  border-top: 1px solid $color-neutral-200;
  padding: $space-6 0;

  .footer-content {
    @include flex-between;
    font-size: $fs-xs;
    color: $color-neutral-500;

    @include media-down(md) {
      flex-direction: column;
      gap: $space-3;
      text-align: center;
    }
  }

  .checkout-footer-links {
    display: flex;
    gap: $space-4;

    a:hover {
      color: $color-primary-600;
    }
  }
}
</style>

