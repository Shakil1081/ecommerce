<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CheckoutLayout from '@/Layouts/CheckoutLayout.vue';
import { useCartStore } from '@/Stores/cartStore';

interface District {
  id: number;
  division_id: number;
  name_en: string;
  name_bn: string;
  zone_type: 'inside_dhaka' | 'dhaka_suburbs' | 'outside_dhaka';
  is_active: boolean;
}

interface Division {
  id: number;
  name_en: string;
  name_bn: string;
  districts?: District[];
}

interface CartItem {
  id: number;
  product_variant_id: string;
  product_name: string;
  sku: string;
  attributes: Record<string, string>;
  quantity: number;
  unit_price_bdt: string;
  subtotal_bdt: string;
  image_url: string | null;
}

interface CartProp {
  id: number;
  ulid: string;
  total_items_count: number;
  subtotal_bdt: string;
  discount_amount_bdt: string;
  total_bdt: string;
  free_shipping?: {
    threshold_bdt: string;
    amount_remaining_bdt: string;
    is_unlocked: boolean;
    progress_percent: number;
  };
  items: CartItem[];
}

const props = defineProps<{
  cart: { data: CartProp } | CartProp;
  divisions?: Division[];
  timingToken?: string;
  freeShippingThreshold?: number;
  shippingRates?: {
    inside_dhaka: number;
    dhaka_suburbs: number;
    outside_dhaka: number;
  };
}>();

const cartStore = useCartStore();

// Normalize cart data (whether wrapped in Resource data or direct)
const cartData = computed<CartProp>(() => {
  if ('data' in props.cart && props.cart.data) {
    return props.cart.data;
  }
  return props.cart as CartProp;
});

const form = ref({
  customer_name: '',
  customer_phone: '',
  customer_email: '',
  delivery_zone: 'inside_dhaka' as 'inside_dhaka' | 'dhaka_suburbs' | 'outside_dhaka',
  delivery_address: '',
  customer_notes: '',
  payment_method: 'cash_on_delivery',
  user_company_fax: '', // Invisible honeypot trap
  _form_timing_token: props.timingToken || '',
});

const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const couponCode = ref('');
const isApplyingCoupon = ref(false);
const couponFeedback = ref<{ success: boolean; message: string } | null>(null);

const currentZone = computed<'inside_dhaka' | 'dhaka_suburbs' | 'outside_dhaka'>(() => {
  return form.value.delivery_zone;
});

// Calculations
const subtotal = computed<number>(() => {
  return Number(cartData.value.subtotal_bdt || 0);
});

const discount = computed<number>(() => {
  return Number(cartData.value.discount_amount_bdt || 0);
});

const isFreeShipping = computed<boolean>(() => {
  const threshold = props.freeShippingThreshold || 2000;
  return subtotal.value >= threshold || (cartData.value.free_shipping?.is_unlocked ?? false);
});

const shippingFee = computed<number>(() => {
  if (isFreeShipping.value) return 0;
  const rates = props.shippingRates || {
    inside_dhaka: 60,
    dhaka_suburbs: 100,
    outside_dhaka: 130,
  };
  return rates[currentZone.value] ?? 60;
});

const grandTotal = computed<number>(() => {
  const total = subtotal.value - discount.value + shippingFee.value;
  return Math.max(0, total);
});

// Amount needed for free shipping
const amountNeededForFreeShipping = computed<number>(() => {
  const threshold = props.freeShippingThreshold || 2000;
  return Math.max(0, threshold - subtotal.value);
});

const freeShippingProgress = computed<number>(() => {
  const threshold = props.freeShippingThreshold || 2000;
  if (subtotal.value >= threshold) return 100;
  return Math.min(100, Math.round((subtotal.value / threshold) * 100));
});

// Coupon handler
const handleApplyCoupon = async () => {
  if (!couponCode.value.trim()) return;
  isApplyingCoupon.value = true;
  couponFeedback.value = null;

  const res = await cartStore.applyCoupon(couponCode.value.trim());
  isApplyingCoupon.value = false;

  if (res.success) {
    couponFeedback.value = { success: true, message: 'কুপন সফলভাবে যুক্ত হয়েছে!' };
    couponCode.value = '';
    router.reload({ only: ['cart'] });
  } else {
    couponFeedback.value = { success: false, message: res.message || 'অবৈধ বা মেয়াদোত্তীর্ণ কুপন কোড।' };
  }
};

const handleRemoveCoupon = async () => {
  couponFeedback.value = null;
  await cartStore.removeCoupon();
  router.reload({ only: ['cart'] });
};

// Form submit
const handleOrderSubmit = () => {
  errors.value = {};

  if (!form.value.customer_name.trim()) {
    errors.value.customer_name = 'অনুগ্রহ করে আপনার নাম লিখুন।';
    return;
  }

  const cleanPhone = form.value.customer_phone.replace(/\D/g, '');
  if (!cleanPhone || cleanPhone.length < 11) {
    errors.value.customer_phone = 'সঠিক ১১ ডিজিটের মোবাইল নম্বর দিন (যেমন: 017xxxxxxxx)';
    return;
  }

  if (!form.value.delivery_address.trim() || form.value.delivery_address.trim().length < 5) {
    errors.value.delivery_address = 'আপনার সম্পূর্ণ ডেলিভারি ঠিকানা দিন (বাড়ি/রোড, এলাকা/থানা)';
    return;
  }

  isSubmitting.value = true;

  router.post('/checkout', form.value, {
    onError: (errs) => {
      errors.value = errs as Record<string, string>;
      isSubmitting.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};
</script>

<template>
  <CheckoutLayout title="1-Page Fast Checkout">
    <Head title="Checkout — Order Confirmation" />

    <div class="checkout-wrapper">
      <!-- Breadcrumb / Return to Shop -->
      <div class="checkout-nav-bar">
        <Link href="/shop" class="return-link">
          ← শপে ফিরে যান (Continue Shopping)
        </Link>
        <span class="step-indicator">
          <span class="step-badge active">১</span> অর্ডার তথ্য ও চেকআউট
        </span>
      </div>

      <!-- Empty Cart Notice -->
      <div v-if="!cartData.items || cartData.items.length === 0" class="empty-cart-card card">
        <div class="empty-icon">🛍️</div>
        <h2>আপনার শপিং কার্ট খালি!</h2>
        <p>চেকআউট সম্পন্ন করার জন্য অনুগ্রহ করে আগে শপ থেকে পণ্য যোগ করুন।</p>
        <Link href="/shop" class="btn btn-primary">
          পণ্য ব্রাউজ করুন (Go to Shop)
        </Link>
      </div>

      <!-- Main Checkout Grid -->
      <div v-else class="checkout-grid">
        <!-- Left Column: Checkout Details Form -->
        <div class="checkout-form-column">
          <form @submit.prevent="handleOrderSubmit" class="checkout-main-form">
            <!-- Invisible Honeypot & Timing Token -->
            <input
              type="text"
              v-model="form.user_company_fax"
              name="user_company_fax"
              class="visually-hidden"
              tabindex="-1"
              autocomplete="new-password"
              aria-hidden="true"
              style="position: absolute !important; left: -9999px !important; top: -9999px !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;"
            />
            <input
              type="hidden"
              v-model="form._form_timing_token"
              name="_form_timing_token"
            />

            <!-- General Error Alert -->
            <div v-if="errors.cart || errors.fraud || errors.payment_method || errors.general" class="alert-error-box">
              <span class="alert-icon">⚠️</span>
              <div>
                <strong class="alert-title">অর্ডার সম্পন্ন করা সম্ভব হয়নি</strong>
                <p class="alert-desc">{{ errors.cart || errors.fraud || errors.payment_method || errors.general }}</p>
              </div>
            </div>

            <!-- Section 1: Customer Contact Information -->
            <div class="form-section card">
              <div class="section-header">
                <span class="section-num">১</span>
                <div>
                  <h3 class="section-title">গ্রাহকের তথ্য (Customer Details)</h3>
                  <p class="section-sub">অর্ডার কনফার্মেশনের জন্য সঠিক নাম ও মোবাইল নম্বর লিখুন</p>
                </div>
              </div>

              <div class="form-group-grid">
                <!-- Customer Name -->
                <div class="form-field full-width">
                  <label class="field-label" for="checkout-name">
                    <span>আপনার নাম</span>
                    <span class="required">*</span>
                  </label>
                  <input
                    id="checkout-name"
                    v-model="form.customer_name"
                    type="text"
                    class="form-input"
                    :class="{ 'has-error': errors.customer_name }"
                    placeholder="আপনার সম্পূর্ণ নাম লিখুন"
                    required
                  />
                  <span v-if="errors.customer_name" class="field-error">{{ errors.customer_name }}</span>
                </div>

                <!-- Customer Phone -->
                <div class="form-field">
                  <label class="field-label" for="checkout-phone">
                    <span>মোবাইল নম্বর</span>
                    <span class="required">*</span>
                  </label>
                  <div class="phone-input-wrap">
                    <span class="phone-prefix">🇧🇩 +88</span>
                    <input
                      id="checkout-phone"
                      v-model="form.customer_phone"
                      type="tel"
                      class="form-input phone-input"
                      :class="{ 'has-error': errors.customer_phone }"
                      placeholder="017XXXXXXXX"
                      required
                    />
                  </div>
                  <span v-if="errors.customer_phone" class="field-error">{{ errors.customer_phone }}</span>
                </div>

                <!-- Customer Email (Optional) -->
                <div class="form-field">
                  <label class="field-label" for="checkout-email">
                    <span>ইমেইল (ঐচ্ছিক)</span>
                  </label>
                  <input
                    id="checkout-email"
                    v-model="form.customer_email"
                    type="email"
                    class="form-input"
                    :class="{ 'has-error': errors.customer_email }"
                    placeholder="example@mail.com"
                  />
                  <span v-if="errors.customer_email" class="field-error">{{ errors.customer_email }}</span>
                </div>
              </div>
            </div>

            <!-- Section 2: Delivery Area & Address -->
            <div class="form-section card">
              <div class="section-header">
                <span class="section-num">২</span>
                <div>
                  <h3 class="section-title">ডেলিভারি এরিয়া ও ঠিকানা (Delivery Area & Address)</h3>
                  <p class="section-sub">আপনার ডেলিভারি এরিয়া বেছে নিন এবং সম্পূর্ণ ঠিকানা লিখুন</p>
                </div>
              </div>

              <div class="form-group-grid">
                <!-- Delivery Zone / Area Radio Cards -->
                <div class="form-field full-width">
                  <label class="field-label">
                    <span>ডেলিভারি এরিয়া বেছে নিন</span>
                    <span class="required">*</span>
                  </label>
                  <div class="zone-options-grid">
                    <label
                      class="zone-radio-card"
                      :class="{ selected: form.delivery_zone === 'inside_dhaka' }"
                    >
                      <input
                        type="radio"
                        v-model="form.delivery_zone"
                        value="inside_dhaka"
                        class="zone-native-radio"
                      />
                      <span class="zone-name">ঢাকা সিটি</span>
                      <span class="zone-rate">
                        {{ isFreeShipping ? 'ফ্রি (Free)' : `৳${shippingRates?.inside_dhaka ?? 60}` }}
                      </span>
                    </label>

                    <label
                      class="zone-radio-card"
                      :class="{ selected: form.delivery_zone === 'dhaka_suburbs' }"
                    >
                      <input
                        type="radio"
                        v-model="form.delivery_zone"
                        value="dhaka_suburbs"
                        class="zone-native-radio"
                      />
                      <span class="zone-name">ঢাকা উপশহর</span>
                      <span class="zone-rate">
                        {{ isFreeShipping ? 'ফ্রি (Free)' : `৳${shippingRates?.dhaka_suburbs ?? 100}` }}
                      </span>
                    </label>

                    <label
                      class="zone-radio-card"
                      :class="{ selected: form.delivery_zone === 'outside_dhaka' }"
                    >
                      <input
                        type="radio"
                        v-model="form.delivery_zone"
                        value="outside_dhaka"
                        class="zone-native-radio"
                      />
                      <span class="zone-name">ঢাকার বাইরে</span>
                      <span class="zone-rate">
                        {{ isFreeShipping ? 'ফ্রি (Free)' : `৳${shippingRates?.outside_dhaka ?? 130}` }}
                      </span>
                    </label>
                  </div>
                </div>

                <!-- Detailed Street Address -->
                <div class="form-field full-width">
                  <label class="field-label" for="checkout-address">
                    <span>সম্পূর্ণ ঠিকানা (বাসা/রোড, এলাকা, থানা ও জেলা)</span>
                    <span class="required">*</span>
                  </label>
                  <textarea
                    id="checkout-address"
                    v-model="form.delivery_address"
                    class="form-textarea"
                    :class="{ 'has-error': errors.delivery_address }"
                    rows="3"
                    placeholder="বাসা নং, রোড নং/নাম, এলাকা, থানা ও জেলার নাম লিখুন..."
                    required
                  ></textarea>
                  <span v-if="errors.delivery_address" class="field-error">{{ errors.delivery_address }}</span>
                </div>

                <!-- Customer Notes -->
                <div class="form-field full-width">
                  <label class="field-label" for="checkout-notes">
                    <span>অর্ডার নোট (ঐচ্ছিক)</span>
                  </label>
                  <input
                    id="checkout-notes"
                    v-model="form.customer_notes"
                    type="text"
                    class="form-input"
                    placeholder="ডেলিভারির বিশেষ নির্দেশনা থাকলে এখানে লিখুন (যেমন: কল করে ডেলিভারি দিবেন)"
                  />
                </div>
              </div>
            </div>

            <!-- Section 3: Payment Method Selection -->
            <div class="form-section card">
              <div class="section-header">
                <span class="section-num">৩</span>
                <div>
                  <h3 class="section-title">পেমেন্ট মেথড (Payment Method)</h3>
                  <p class="section-sub">আপনার সুবিধাজনক পেমেন্ট মাধ্যম বেছে নিন</p>
                </div>
              </div>

              <div class="payment-methods-grid">
                <!-- Cash on Delivery (COD) -->
                <label
                  class="payment-option-card"
                  :class="{ selected: form.payment_method === 'cash_on_delivery' }"
                >
                  <div class="payment-option-head">
                    <input
                      type="radio"
                      v-model="form.payment_method"
                      value="cash_on_delivery"
                      class="native-radio"
                    />
                    <div class="option-icon">💵</div>
                    <div>
                      <div class="option-title">ক্যাশ অন ডেলিভারি (Cash on Delivery)</div>
                      <div class="option-sub">পণ্য হাতে পেয়ে দেখে টাকা দিন • কোনো অগ্রিম টাকা নেই</div>
                    </div>
                  </div>
                  <span class="badge-recommended">সবচেয়ে জনপ্রিয়</span>
                </label>

                <!-- bKash -->
                <label
                  class="payment-option-card"
                  :class="{ selected: form.payment_method === 'bkash' }"
                >
                  <div class="payment-option-head">
                    <input
                      type="radio"
                      v-model="form.payment_method"
                      value="bkash"
                      class="native-radio"
                    />
                    <div class="option-icon bkash-icon">📱</div>
                    <div>
                      <div class="option-title">বিকাশ পেমেন্ট (bKash Online)</div>
                      <div class="option-sub">বিকাশ একাউন্ট থেকে নিরাপদ ও দ্রুত পেমেন্ট</div>
                    </div>
                  </div>
                  <span class="badge-instant">ইনস্ট্যান্ট</span>
                </label>

                <!-- Nagad -->
                <label
                  class="payment-option-card"
                  :class="{ selected: form.payment_method === 'nagad' }"
                >
                  <div class="payment-option-head">
                    <input
                      type="radio"
                      v-model="form.payment_method"
                      value="nagad"
                      class="native-radio"
                    />
                    <div class="option-icon nagad-icon">📱</div>
                    <div>
                      <div class="option-title">নগদ পেমেন্ট (Nagad Online)</div>
                      <div class="option-sub">ডাক বিভাগের ডিজিটাল লেনদেন নগদ পেমেন্ট</div>
                    </div>
                  </div>
                  <span class="badge-instant">ইনস্ট্যান্ট</span>
                </label>
              </div>
            </div>

            <!-- Mobile View Order Submit Button -->
            <div class="mobile-submit-wrap">
              <button
                type="submit"
                class="btn-order-submit"
                :disabled="isSubmitting"
              >
                <span v-if="isSubmitting">⏳ অর্ডার প্রসেস হচ্ছে...</span>
                <span v-else>👉 অর্ডার নিশ্চিত করুন — ৳{{ grandTotal.toLocaleString() }}</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Right Column: Order Summary & Cart Items -->
        <div class="checkout-summary-column">
          <div class="summary-card card sticky-summary">
            <h3 class="summary-title">অর্ডার সারাংশ (Order Summary)</h3>

            <!-- Free Shipping Bar -->
            <div class="free-shipping-widget">
              <div class="shipping-info-text">
                <span v-if="isFreeShipping" class="free-unlocked-text">
                  🎉 অভিনন্দন! আপনি <strong>ফ্রি হোম ডেলিভারি</strong> পাচ্ছেন!
                </span>
                <span v-else class="free-progress-text">
                  আর <strong>৳{{ amountNeededForFreeShipping.toLocaleString() }}</strong> টাকার কেনাকাটায় <strong>ফ্রি ডেলিভারি</strong>! 🚚
                </span>
              </div>
              <div class="shipping-progress-track">
                <div
                  class="shipping-progress-fill"
                  :style="{ width: `${freeShippingProgress}%` }"
                ></div>
              </div>
            </div>

            <!-- Items List -->
            <div class="cart-items-preview">
              <div class="items-header">
                <span>পণ্য তালিকা ({{ cartData.total_items_count }} টি)</span>
                <Link href="/shop" class="edit-link">আরও পণ্য যোগ করুন</Link>
              </div>

              <div class="items-scroll-list">
                <div v-for="item in cartData.items" :key="item.id" class="item-row">
                  <div class="item-thumb-box">
                    <img
                      v-if="item.image_url"
                      :src="item.image_url"
                      :alt="item.product_name"
                      class="item-img"
                    />
                    <div v-else class="item-img-placeholder">👜</div>
                    <span class="item-qty-badge">{{ item.quantity }}</span>
                  </div>

                  <div class="item-details">
                    <h4 class="item-name">{{ item.product_name }}</h4>
                    <div v-if="item.attributes && Object.keys(item.attributes).length" class="item-variants">
                      <span v-for="(val, key) in item.attributes" :key="key" class="variant-tag">
                        {{ key }}: {{ val }}
                      </span>
                    </div>
                    <div class="item-price-wrap">
                      <span class="item-unit">৳{{ Number(item.unit_price_bdt).toLocaleString() }}</span>
                      <strong class="item-line-total">৳{{ Number(item.subtotal_bdt).toLocaleString() }}</strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Coupon Code Section -->
            <div class="coupon-section">
              <div class="coupon-input-group">
                <input
                  v-model="couponCode"
                  type="text"
                  placeholder="কুপন কোড লিখুন"
                  class="coupon-input"
                  :disabled="isApplyingCoupon"
                />
                <button
                  type="button"
                  class="btn-apply-coupon"
                  :disabled="!couponCode.trim() || isApplyingCoupon"
                  @click="handleApplyCoupon"
                >
                  <span v-if="isApplyingCoupon">...</span>
                  <span v-else>প্রয়োগ</span>
                </button>
              </div>
              <div v-if="couponFeedback" class="coupon-feedback" :class="{ 'is-success': couponFeedback.success }">
                {{ couponFeedback.message }}
              </div>
            </div>

            <!-- Price Breakdown -->
            <div class="price-breakdown">
              <div class="price-row">
                <span class="label">পণ্যের মূল্য (Subtotal):</span>
                <span class="value">৳{{ subtotal.toLocaleString() }}</span>
              </div>

              <div class="price-row">
                <span class="label">ডেলিভারি চার্জ (Delivery):</span>
                <span v-if="isFreeShipping" class="value free-charge">৳০ (ফ্রি)</span>
                <span v-else class="value">৳{{ shippingFee.toLocaleString() }}</span>
              </div>

              <div v-if="discount > 0" class="price-row discount-row">
                <span class="label">কুপন ডিসকাউন্ট:</span>
                <span class="value">-৳{{ discount.toLocaleString() }}</span>
              </div>

              <div class="price-row total-row">
                <span class="total-label">সর্বমোট প্রদেয় (Grand Total):</span>
                <strong class="total-value">৳{{ grandTotal.toLocaleString() }}</strong>
              </div>

              <div class="cod-tag-badge">
                <span class="cod-icon">💵</span>
                <span>ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে মূল্য দিন</span>
              </div>
            </div>

            <!-- Desktop Big Submit Button -->
            <button
              type="button"
              class="btn-order-submit desktop-submit"
              :disabled="isSubmitting"
              @click="handleOrderSubmit"
            >
              <span v-if="isSubmitting">⏳ অর্ডার প্রসেস হচ্ছে...</span>
              <span v-else>👉 অর্ডার কনফার্ম করুন — ৳{{ grandTotal.toLocaleString() }}</span>
            </button>

            <!-- Trust Badges -->
            <div class="trust-perks-list">
              <div class="trust-perk">
                <span class="perk-icon">🚚</span>
                <span>সারা বাংলাদেশে দ্রুততম হোম ডেলিভারি</span>
              </div>
              <div class="trust-perk">
                <span class="perk-icon">🛡️</span>
                <span>১০০% অথেনটিক এবং অরিজিনাল প্রডাক্ট</span>
              </div>
              <div class="trust-perk">
                <span class="perk-icon">🔄</span>
                <span>সহজ ৭ দিনের রিটার্ন ও রিপ্লেসমেন্ট গ্যারান্টি</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </CheckoutLayout>
</template>

<style lang="scss" scoped>
.checkout-wrapper {
  max-width: 1200px;
  margin: 0 auto;
  padding: $space-6 $space-4 $space-16;

  @include media-down(md) {
    padding: $space-4 $space-2 $space-20;
  }
}

.checkout-nav-bar {
  @include flex-between;
  margin-bottom: $space-6;

  .return-link {
    color: $color-neutral-600;
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    text-decoration: none;
    transition: color 0.2s ease;

    &:hover {
      color: $color-primary-600;
    }
  }

  .step-indicator {
    font-size: $fs-sm;
    font-weight: $fw-semibold;
    color: $color-neutral-800;
    display: flex;
    align-items: center;
    gap: $space-2;

    .step-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      border-radius: $radius-full;
      background-color: $color-primary-600;
      color: #fff;
      font-size: $fs-xs;
      font-weight: $fw-bold;
    }
  }
}

.empty-cart-card {
  text-align: center;
  padding: $space-12 $space-6;
  background-color: #fff;
  border-radius: $radius-lg;
  border: 1px solid $color-neutral-200;

  .empty-icon {
    font-size: 3.5rem;
    margin-bottom: $space-4;
  }

  h2 {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    margin-bottom: $space-2;
  }

  p {
    color: $color-neutral-500;
    margin-bottom: $space-6;
  }

  .btn {
    padding: $space-3 $space-6;
    border-radius: $radius-md;
    text-decoration: none;
  }
}

.checkout-grid {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: $space-8;
  align-items: start;

  @include media-down(lg) {
    grid-template-columns: 1fr;
    gap: $space-6;
  }
}

// Left Form Column
.checkout-form-column {
  .checkout-main-form {
    display: flex;
    flex-direction: column;
    gap: $space-6;
  }

  .form-section {
    background-color: #fff;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-lg;
    padding: $space-6;

    @include media-down(sm) {
      padding: $space-4;
    }
  }

  .section-header {
    display: flex;
    align-items: flex-start;
    gap: $space-3;
    margin-bottom: $space-5;
    padding-bottom: $space-4;
    border-bottom: 1px solid $color-neutral-100;

    .section-num {
      width: 32px;
      height: 32px;
      border-radius: $radius-full;
      background-color: $color-primary-100;
      color: $color-primary-700;
      font-weight: $fw-extrabold;
      font-size: $fs-base;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .section-title {
      font-size: $fs-lg;
      font-weight: $fw-bold;
      color: $color-neutral-900;
      margin: 0 0 $space-1;
    }

    .section-sub {
      font-size: $fs-xs;
      color: $color-neutral-500;
      margin: 0;
    }
  }

  .form-group-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: $space-4;

    @include media-down(sm) {
      grid-template-columns: 1fr;
    }

    .full-width {
      grid-column: 1 / -1;
    }
  }

  .form-field {
    display: flex;
    flex-direction: column;
    gap: $space-1;

    .field-label {
      font-size: $fs-sm;
      font-weight: $fw-semibold;
      color: $color-neutral-800;

      .required {
        color: $color-danger;
        margin-left: 2px;
      }
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: $space-3;
      border: 1px solid $color-neutral-300;
      border-radius: $radius-md;
      font-size: $fs-base;
      color: $color-neutral-900;
      background-color: #fff;
      transition: all 0.2s ease;

      &:focus {
        outline: none;
        border-color: $color-primary-500;
        box-shadow: 0 0 0 3px rgba($color-primary-500, 0.15);
      }

      &.has-error {
        border-color: $color-danger;
        background-color: #fff5f5;
      }
    }

    .phone-input-wrap {
      display: flex;
      align-items: center;
      position: relative;

      .phone-prefix {
        position: absolute;
        left: $space-3;
        font-size: $fs-sm;
        font-weight: $fw-semibold;
        color: $color-neutral-500;
        pointer-events: none;
      }

      .phone-input {
        padding-left: 78px;
      }
    }

    .field-error {
      color: $color-danger;
      font-size: $fs-xs;
      font-weight: $fw-medium;
      margin-top: 2px;
    }
  }

  .zone-options-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: $space-3;
    margin-top: $space-1;

    @media (max-width: 500px) {
      grid-template-columns: 1fr;
    }

    .zone-radio-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 12px 14px;
      border-radius: $radius-lg;
      background: #ffffff;
      border: 2px solid $color-neutral-200;
      cursor: pointer;
      transition: all 0.2s ease;
      position: relative;
      text-align: center;
      user-select: none;

      .zone-native-radio {
        position: absolute;
        opacity: 0;
        pointer-events: none;
      }

      .zone-name {
        font-size: $fs-sm;
        font-weight: $fw-bold;
        color: $color-neutral-800;
      }

      .zone-rate {
        font-size: $fs-base;
        font-weight: $fw-extrabold;
        color: $color-primary-600;
        margin-top: 3px;
      }

      &:hover {
        border-color: $color-primary-400;
        background: $color-primary-50;
      }

      &.selected {
        border-color: $color-primary-500;
        background: $color-primary-50;
        box-shadow: 0 2px 8px rgba(235, 40, 108, 0.18);

        .zone-name {
          color: $color-primary-900;
        }
      }
    }
  }

  // Payment Selection
  .payment-methods-grid {
    display: flex;
    flex-direction: column;
    gap: $space-3;

    .payment-option-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 2px solid $color-neutral-200;
      border-radius: $radius-lg;
      padding: $space-4;
      cursor: pointer;
      transition: all 0.2s ease;

      &:hover {
        border-color: $color-neutral-300;
        background-color: $color-neutral-50;
      }

      &.selected {
        border-color: $color-primary-500;
        background-color: $color-primary-50;
      }

      .payment-option-head {
        display: flex;
        align-items: center;
        gap: $space-3;

        .native-radio {
          accent-color: $color-primary-600;
          width: 18px;
          height: 18px;
        }

        .option-icon {
          font-size: 1.6rem;
        }

        .option-title {
          font-size: $fs-base;
          font-weight: $fw-bold;
          color: $color-neutral-900;
        }

        .option-sub {
          font-size: $fs-xs;
          color: $color-neutral-500;
        }
      }

      .badge-recommended {
        background-color: $color-primary-600;
        color: #fff;
        font-size: $fs-xs;
        font-weight: $fw-bold;
        padding: 3px 10px;
        border-radius: $radius-full;
      }

      .badge-instant {
        background-color: $color-neutral-200;
        color: $color-neutral-700;
        font-size: $fs-xs;
        font-weight: $fw-semibold;
        padding: 2px 8px;
        border-radius: $radius-full;
      }
    }
  }

  .alert-error-box {
    display: flex;
    align-items: flex-start;
    gap: $space-3;
    background-color: #fee2e2;
    border: 1px solid #ef4444;
    padding: $space-4;
    border-radius: $radius-md;
    color: #991b1b;

    .alert-icon {
      font-size: 1.5rem;
    }

    .alert-title {
      font-size: $fs-sm;
      font-weight: $fw-bold;
      margin-bottom: 2px;
    }

    .alert-desc {
      font-size: $fs-xs;
      margin: 0;
    }
  }

  .mobile-submit-wrap {
    display: none;

    @include media-down(lg) {
      display: block;
      margin-top: $space-4;
    }
  }
}

// Right Summary Column
.checkout-summary-column {
  .sticky-summary {
    position: sticky;
    top: 24px;
    background-color: #fff;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-lg;
    padding: $space-6;
  }

  .summary-title {
    font-size: $fs-lg;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    margin: 0 0 $space-4;
    padding-bottom: $space-3;
    border-bottom: 1px solid $color-neutral-100;
  }

  .free-shipping-widget {
    background: linear-gradient(135deg, $color-primary-50 0%, #fff 100%);
    border: 1px solid $color-primary-200;
    border-radius: $radius-md;
    padding: $space-3;
    margin-bottom: $space-4;

    .shipping-info-text {
      font-size: $fs-xs;
      margin-bottom: $space-2;

      .free-unlocked-text {
        color: $color-success;
        font-weight: $fw-bold;
      }

      .free-progress-text {
        color: $color-neutral-700;
      }
    }

    .shipping-progress-track {
      width: 100%;
      height: 6px;
      background-color: $color-neutral-200;
      border-radius: $radius-full;
      overflow: hidden;

      .shipping-progress-fill {
        height: 100%;
        background-color: $color-primary-500;
        border-radius: $radius-full;
        transition: width 0.3s ease;
      }
    }
  }

  // Items scroll
  .cart-items-preview {
    margin-bottom: $space-4;

    .items-header {
      @include flex-between;
      font-size: $fs-xs;
      font-weight: $fw-bold;
      color: $color-neutral-600;
      margin-bottom: $space-3;

      .edit-link {
        color: $color-primary-600;
        text-decoration: none;

        &:hover {
          text-decoration: underline;
        }
      }
    }

    .items-scroll-list {
      max-height: 220px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: $space-3;
      padding-right: $space-1;
    }

    .item-row {
      display: flex;
      align-items: center;
      gap: $space-3;

      .item-thumb-box {
        position: relative;
        width: 52px;
        height: 52px;
        border-radius: $radius-md;
        border: 1px solid $color-neutral-200;
        background-color: $color-neutral-50;
        overflow: hidden;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;

        .item-img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .item-img-placeholder {
          font-size: 1.4rem;
        }

        .item-qty-badge {
          position: absolute;
          top: -4px;
          right: -4px;
          background-color: $color-neutral-700;
          color: #fff;
          font-size: 10px;
          font-weight: $fw-bold;
          width: 18px;
          height: 18px;
          border-radius: $radius-full;
          display: flex;
          align-items: center;
          justify-content: center;
        }
      }

      .item-details {
        flex: 1;
        min-width: 0;

        .item-name {
          font-size: $fs-xs;
          font-weight: $fw-semibold;
          color: $color-neutral-900;
          margin: 0;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .item-variants {
          font-size: 11px;
          color: $color-neutral-500;
          margin-top: 2px;
        }

        .item-price-wrap {
          @include flex-between;
          margin-top: 2px;

          .item-unit {
            font-size: 11px;
            color: $color-neutral-400;
          }

          .item-line-total {
            font-size: $fs-xs;
            color: $color-neutral-800;
          }
        }
      }
    }
  }

  // Coupon
  .coupon-section {
    margin-bottom: $space-4;

    .coupon-input-group {
      display: flex;
      gap: $space-2;

      .coupon-input {
        flex: 1;
        padding: $space-2 $space-3;
        border: 1px solid $color-neutral-300;
        border-radius: $radius-md;
        font-size: $fs-xs;

        &:focus {
          outline: none;
          border-color: $color-primary-500;
        }
      }

      .btn-apply-coupon {
        padding: $space-2 $space-4;
        background-color: $color-neutral-800;
        color: #fff;
        border: none;
        border-radius: $radius-md;
        font-size: $fs-xs;
        font-weight: $fw-bold;
        cursor: pointer;
        transition: background-color 0.2s ease;

        &:hover:not(:disabled) {
          background-color: $color-neutral-900;
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
      }
    }

    .coupon-feedback {
      font-size: 11px;
      color: $color-danger;
      margin-top: 4px;

      &.is-success {
        color: $color-success;
      }
    }
  }

  // Price Breakdown
  .price-breakdown {
    border-top: 1px solid $color-neutral-200;
    padding-top: $space-3;
    display: flex;
    flex-direction: column;
    gap: $space-2;

    .price-row {
      @include flex-between;
      font-size: $fs-sm;
      color: $color-neutral-600;

      .free-charge {
        color: $color-success;
        font-weight: $fw-bold;
      }

      &.discount-row {
        color: $color-primary-600;
      }

      &.total-row {
        border-top: 1px dashed $color-neutral-200;
        padding-top: $space-3;
        margin-top: $space-1;
        color: $color-neutral-900;

        .total-label {
          font-size: $fs-base;
          font-weight: $fw-bold;
        }

        .total-value {
          font-size: $fs-xl;
          font-weight: $fw-extrabold;
          color: $color-primary-600;
        }
      }
    }

    .cod-tag-badge {
      display: flex;
      align-items: center;
      gap: $space-2;
      background-color: $color-primary-50;
      border: 1px solid $color-primary-200;
      color: $color-primary-800;
      font-size: 11px;
      font-weight: $fw-semibold;
      padding: 6px 10px;
      border-radius: $radius-md;
      margin-top: $space-2;
    }
  }

  .desktop-submit {
    display: block;
    margin-top: $space-4;

    @include media-down(lg) {
      display: none;
    }
  }

  .trust-perks-list {
    margin-top: $space-6;
    padding-top: $space-4;
    border-top: 1px solid $color-neutral-100;
    display: flex;
    flex-direction: column;
    gap: $space-2;

    .trust-perk {
      display: flex;
      align-items: center;
      gap: $space-2;
      font-size: 11px;
      color: $color-neutral-500;

      .perk-icon {
        font-size: $fs-sm;
      }
    }
  }
}

// Global Order Submit Button
.btn-order-submit {
  width: 100%;
  background: linear-gradient(135deg, $color-primary-500 0%, $color-primary-600 100%);
  color: #fff;
  border: none;
  border-radius: $radius-lg;
  padding: $space-4;
  font-size: $fs-base;
  font-weight: $fw-bold;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba($color-primary-600, 0.35);
  transition: all 0.2s ease;

  &:hover:not(:disabled) {
    background: linear-gradient(135deg, $color-primary-600 0%, $color-primary-700 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba($color-primary-600, 0.45);
  }

  &:active:not(:disabled) {
    transform: translateY(0);
  }

  &:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>

