<script setup lang="ts">
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cartStore';

const cartStore = useCartStore();

onMounted(() => {
  cartStore.fetchCart();
});
</script>

<template>
  <div>
    <!-- Backdrop Overlay -->
    <transition name="fade">
      <div
        v-if="cartStore.isOpen"
        class="drawer-backdrop"
        @click="cartStore.closeDrawer"
      />
    </transition>

    <!-- Slide-Out Drawer Panel -->
    <aside class="cart-drawer-panel" :class="{ 'is-open': cartStore.isOpen }">
      <!-- Header -->
      <div class="drawer-header">
        <div class="header-title-wrap">
          <span class="cart-icon">🛒</span>
          <h2 class="drawer-title">Shopping Cart</h2>
          <span class="items-badge">{{ cartStore.totalItemsCount }}</span>
        </div>
        <button class="drawer-close-btn" aria-label="Close cart drawer" @click="cartStore.closeDrawer">
          ✕
        </button>
      </div>

      <!-- Free Shipping Progress Bar -->
      <div class="free-shipping-section">
        <div class="shipping-message">
          <span v-if="cartStore.freeShipping.is_unlocked" class="unlocked-msg">
            🎉 Congratulations! You unlocked <strong>FREE Delivery</strong> across Bangladesh!
          </span>
          <span v-else class="progress-msg">
            Add <strong>৳{{ Number(cartStore.freeShipping.amount_remaining_bdt).toLocaleString() }}</strong> more for <strong>FREE Delivery</strong>! 🚚
          </span>
        </div>
        <div class="progress-track">
          <div
            class="progress-bar"
            :class="{ 'is-complete': cartStore.freeShipping.is_unlocked }"
            :style="{ width: `${cartStore.freeShipping.progress_percent}%` }"
          />
        </div>
      </div>

      <!-- Body: Item List or Empty State -->
      <div class="drawer-body">
        <div v-if="cartStore.items.length === 0" class="empty-cart-state">
          <span class="empty-icon">🛍️</span>
          <h3 class="empty-title">Your cart is empty</h3>
          <p class="empty-subtitle">Discover our genuine imports and locally sourced products!</p>
          <button class="btn btn-primary" @click="cartStore.closeDrawer">
            Start Shopping
          </button>
        </div>

        <ul v-else class="cart-item-list">
          <li v-for="item in cartStore.items" :key="item.id" class="cart-item">
            <div class="item-thumb-wrapper">
              <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="item.product_name"
                class="item-thumb"
              />
              <div v-else class="item-thumb-placeholder">📦</div>
            </div>

            <div class="item-details">
              <div class="item-header-row">
                <h4 class="item-name">{{ item.product_name }}</h4>
                <button
                  class="item-remove-btn"
                  title="Remove item"
                  :disabled="cartStore.isLoading"
                  @click="cartStore.removeItem(item.product_variant_id)"
                >
                  ✕
                </button>
              </div>

              <span class="item-sku">SKU: {{ item.sku }}</span>

              <div v-if="Object.keys(item.attributes).length > 0" class="item-attributes">
                <span v-for="(val, key) in item.attributes" :key="key" class="attr-pill">
                  {{ key }}: {{ val }}
                </span>
              </div>

              <div class="item-pricing-row">
                <span class="unit-price">৳{{ Number(item.unit_price_bdt).toLocaleString() }}</span>
                <span v-if="Number(item.tier_savings_bdt) > 0" class="tier-savings-badge">
                  Save ৳{{ Number(item.tier_savings_bdt).toLocaleString() }}/unit
                </span>
              </div>

              <div class="item-action-row">
                <div class="quantity-stepper">
                  <button
                    class="stepper-btn"
                    :disabled="cartStore.isLoading"
                    @click="cartStore.updateQuantity(item.product_variant_id, item.quantity - 1)"
                  >
                    -
                  </button>
                  <span class="stepper-val">{{ item.quantity }}</span>
                  <button
                    class="stepper-btn"
                    :disabled="cartStore.isLoading"
                    @click="cartStore.updateQuantity(item.product_variant_id, item.quantity + 1)"
                  >
                    +
                  </button>
                </div>
                <strong class="item-subtotal">৳{{ Number(item.subtotal_bdt).toLocaleString() }}</strong>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Footer: Totals & Checkout Actions -->
      <div v-if="cartStore.items.length > 0" class="drawer-footer">
        <div class="totals-breakdown">
          <div class="totals-row">
            <span class="totals-label">Subtotal</span>
            <span class="totals-value">৳{{ Number(cartStore.subtotal).toLocaleString() }}</span>
          </div>
          <div class="totals-row">
            <span class="totals-label">Estimated Delivery</span>
            <span class="totals-value" :class="{ 'free-tag': cartStore.freeShipping.is_unlocked }">
              {{ cartStore.freeShipping.is_unlocked ? 'FREE' : 'Calculated at checkout' }}
            </span>
          </div>
          <div class="totals-row grand-total-row">
            <span class="total-label">Estimated Total</span>
            <strong class="grand-total-value">৳{{ Number(cartStore.total).toLocaleString() }}</strong>
          </div>
        </div>

        <div class="checkout-actions">
          <Link href="/checkout" class="btn btn-primary btn-checkout" @click="cartStore.closeDrawer">
            Proceed to 1-Page Checkout →
          </Link>
          <button class="btn btn-outline" @click="cartStore.closeDrawer">
            Continue Shopping
          </button>
        </div>
      </div>
    </aside>
  </div>
</template>

<style lang="scss" scoped>
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1040;
}

.cart-drawer-panel {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  max-width: 440px;
  background: $color-surface;
  box-shadow: -10px 0 25px -5px rgba(0, 0, 0, 0.15);
  z-index: 1045;
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 300ms cubic-bezier(0.16, 1, 0.3, 1);

  &.is-open {
    transform: translateX(0);
  }
}

.drawer-header {
  padding: $space-4 $space-6;
  border-bottom: 1px solid $color-border;
  display: flex;
  justify-content: space-between;
  align-items: center;

  .header-title-wrap {
    display: flex;
    align-items: center;
    gap: $space-2;

    .cart-icon {
      font-size: $fs-xl;
    }

    .drawer-title {
      font-size: $fs-lg;
      font-weight: 700;
      color: $color-text-main;
    }

    .items-badge {
      background: $color-primary;
      color: #ffffff;
      font-size: $fs-xs;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: $radius-full;
    }
  }

  .drawer-close-btn {
    background: transparent;
    border: none;
    font-size: $fs-lg;
    color: $color-text-muted;
    cursor: pointer;
    padding: $space-1;
    border-radius: $radius-sm;

    &:hover {
      color: $color-text-main;
      background: $color-surface-hover;
    }
  }
}

.free-shipping-section {
  padding: $space-3 $space-6;
  background: $color-bg-subtle;
  border-bottom: 1px solid $color-border;
  display: flex;
  flex-direction: column;
  gap: $space-2;

  .shipping-message {
    font-size: $fs-xs;
    color: $color-text-main;

    .unlocked-msg {
      color: #16a34a;
      font-weight: 600;
    }
  }

  .progress-track {
    height: 6px;
    background: $color-neutral-200;
    border-radius: $radius-full;
    overflow: hidden;

    .progress-bar {
      height: 100%;
      background: $color-primary;
      transition: width 300ms ease;

      &.is-complete {
        background: #16a34a;
      }
    }
  }
}

.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: $space-4 $space-6;
}

.empty-cart-state {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: $space-8 0;

  .empty-icon {
    font-size: 3.5rem;
    margin-bottom: $space-3;
  }

  .empty-title {
    font-size: $fs-lg;
    font-weight: 700;
    color: $color-text-main;
  }

  .empty-subtitle {
    font-size: $fs-sm;
    color: $color-text-muted;
    max-width: 260px;
    margin: $space-2 0 $space-6 0;
  }
}

.cart-item-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: $space-4;
}

.cart-item {
  display: flex;
  gap: $space-3;
  padding-bottom: $space-4;
  border-bottom: 1px solid $color-border;

  .item-thumb-wrapper {
    width: 72px;
    height: 72px;
    flex-shrink: 0;
    border-radius: $radius-md;
    border: 1px solid $color-border;
    overflow: hidden;
    background: $color-bg-subtle;
    display: flex;
    align-items: center;
    justify-content: center;

    .item-thumb {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .item-thumb-placeholder {
      font-size: 1.5rem;
    }
  }

  .item-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: $space-1;

    .item-header-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;

      .item-name {
        font-size: $fs-sm;
        font-weight: 600;
        color: $color-text-main;
        line-height: 1.3;
      }

      .item-remove-btn {
        background: transparent;
        border: none;
        color: $color-text-muted;
        font-size: $fs-xs;
        cursor: pointer;
        padding: 0 $space-1;

        &:hover {
          color: #ef4444;
        }
      }
    }

    .item-sku {
      font-size: $fs-2xs;
      color: $color-text-muted;
    }

    .item-attributes {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      margin-top: 2px;

      .attr-pill {
        font-size: $fs-2xs;
        background: $color-bg-subtle;
        padding: 1px 6px;
        border-radius: $radius-xs;
        border: 1px solid $color-border;
      }
    }

    .item-pricing-row {
      display: flex;
      align-items: center;
      gap: $space-2;
      margin-top: 2px;

      .unit-price {
        font-size: $fs-xs;
        color: $color-text-muted;
      }

      .tier-savings-badge {
        font-size: $fs-2xs;
        font-weight: 600;
        color: #16a34a;
        background: rgba(22, 163, 74, 0.1);
        padding: 1px 6px;
        border-radius: $radius-xs;
      }
    }

    .item-action-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: $space-2;

      .quantity-stepper {
        display: flex;
        align-items: center;
        border: 1px solid $color-border;
        border-radius: $radius-sm;
        overflow: hidden;

        .stepper-btn {
          background: $color-bg-subtle;
          border: none;
          width: 26px;
          height: 26px;
          font-weight: 700;
          cursor: pointer;

          &:hover:not(:disabled) {
            background: $color-border;
          }
        }

        .stepper-val {
          width: 32px;
          text-align: center;
          font-size: $fs-xs;
          font-weight: 600;
        }
      }

      .item-subtotal {
        font-size: $fs-sm;
        font-weight: 700;
        color: $color-text-main;
      }
    }
  }
}

.drawer-footer {
  padding: $space-4 $space-6;
  border-top: 1px solid $color-border;
  background: $color-surface;

  .totals-breakdown {
    display: flex;
    flex-direction: column;
    gap: $space-2;
    margin-bottom: $space-4;

    .totals-row {
      display: flex;
      justify-content: space-between;
      font-size: $fs-sm;
      color: $color-text-muted;

      .totals-value.free-tag {
        color: #16a34a;
        font-weight: 700;
      }

      &.grand-total-row {
        font-size: $fs-base;
        color: $color-text-main;
        padding-top: $space-2;
        border-top: 1px dashed $color-border;

        .grand-total-value {
          font-size: $fs-lg;
          color: $color-primary;
        }
      }
    }
  }

  .checkout-actions {
    display: flex;
    flex-direction: column;
    gap: $space-2;

    .btn-checkout {
      width: 100%;
      text-align: center;
      padding: $space-3;
      font-weight: 700;
    }

    .btn-outline {
      width: 100%;
      text-align: center;
      padding: $space-2;
      background: transparent;
      border: 1px solid $color-border;
      border-radius: $radius-md;
      font-size: $fs-sm;
      cursor: pointer;

      &:hover {
        background: $color-surface-hover;
      }
    }
  }
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 250ms ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
