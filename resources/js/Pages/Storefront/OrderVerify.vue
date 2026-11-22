<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import { formatBdt } from '@/Utils/format';

const props = defineProps<{
  orderNumber: string;
  maskedPhone: string;
  totalBdt: string;
  resendAvailableIn: number;
}>();

const form = useForm({ code: '' });
const secondsLeft = ref(props.resendAvailableIn);
const isResending = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const startCountdown = (seconds: number) => {
  secondsLeft.value = seconds;
  if (timer) clearInterval(timer);
  timer = setInterval(() => {
    if (secondsLeft.value > 0) {
      secondsLeft.value--;
    } else if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }, 1000);
};

onMounted(() => startCountdown(props.resendAvailableIn));
onUnmounted(() => {
  if (timer) clearInterval(timer);
});

const canResend = computed(() => secondsLeft.value <= 0 && !isResending.value);

const submit = () => {
  form.code = form.code.replace(/\D/g, '');
  form.post(`/orders/${props.orderNumber}/verify`, { preserveScroll: true });
};

const resend = () => {
  if (!canResend.value) return;
  isResending.value = true;
  router.post(`/orders/${props.orderNumber}/verify/resend`, {}, {
    preserveScroll: true,
    onSuccess: () => startCountdown(60),
    onFinish: () => {
      isResending.value = false;
    },
  });
};
</script>

<template>
  <StorefrontLayout title="Confirm your order">
    <SeoHead title="Confirm your order" :noindex="true" />

    <div class="verify-page container">
      <div class="verify-card card">
        <div class="icon-wrap" aria-hidden="true">📱</div>
        <h1>Confirm your order</h1>
        <p class="lead">
          We sent a verification code by SMS to <strong>{{ maskedPhone }}</strong>.
          Enter it below to confirm order <strong>{{ orderNumber }}</strong> ({{ formatBdt(totalBdt) }}).
        </p>
        <p class="lead-bn">আপনার মোবাইলে পাঠানো কোডটি লিখে অর্ডারটি নিশ্চিত করুন।</p>

        <form class="verify-form" @submit.prevent="submit">
          <label class="sf-field-label" for="otp-code">Verification code</label>
          <input
            id="otp-code"
            v-model="form.code"
            class="sf-input code-input"
            :class="{ 'has-error': form.errors.code }"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            placeholder="• • • •"
            required
            autofocus
          >
          <p v-if="form.errors.code" class="sf-field-error" role="alert">{{ form.errors.code }}</p>

          <button type="submit" class="btn btn-primary btn-lg submit-btn" :disabled="form.processing || form.code.length < 4">
            {{ form.processing ? 'Checking…' : 'Confirm order' }}
          </button>
        </form>

        <div class="resend-row">
          <span>Didn't get the code?</span>
          <button type="button" class="link-btn" :disabled="!canResend" @click="resend">
            {{ secondsLeft > 0 ? `Resend in ${secondsLeft}s` : (isResending ? 'Sending…' : 'Send a new code') }}
          </button>
        </div>

        <p class="help-note">
          If you can't receive SMS, don't worry: our team will call you on this number to confirm the order.
        </p>
      </div>
    </div>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.verify-page {
  @include flex-center;
  padding-top: $space-10;
  padding-bottom: $space-16;

  @media (max-width: 640px) {
    padding-top: $space-6;
  }
}

.verify-card {
  width: 100%;
  max-width: 460px;
  padding: $space-8;
  text-align: center;

  @media (max-width: 640px) {
    padding: $space-5;
  }

  .icon-wrap {
    font-size: 2.5rem;
    margin-bottom: $space-3;
  }

  h1 {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
    margin-bottom: $space-3;
  }

  .lead {
    font-size: $fs-sm;
    color: $color-neutral-600;
    line-height: $lh-relaxed;
  }

  .lead-bn {
    font-size: $fs-sm;
    color: $color-neutral-500;
    margin: $space-2 0 $space-6;
  }
}

.verify-form {
  text-align: left;

  .code-input {
    margin-top: $space-1;
    text-align: center;
    font-size: 26px;
    letter-spacing: 0.5em;
    font-family: $font-mono;
  }

  .sf-field-error {
    margin-top: $space-2;
  }

  .submit-btn {
    width: 100%;
    margin-top: $space-4;
  }
}

.resend-row {
  display: flex;
  justify-content: center;
  gap: $space-2;
  margin-top: $space-5;
  font-size: $fs-sm;
  color: $color-neutral-500;

  .link-btn {
    background: none;
    border: none;
    padding: 0;
    color: $color-primary-600;
    font-weight: $fw-semibold;
    cursor: pointer;

    &:disabled {
      color: $color-neutral-400;
      cursor: default;
    }
  }
}

.help-note {
  margin-top: $space-5;
  font-size: $fs-xs;
  color: $color-neutral-500;
  line-height: $lh-relaxed;
}
</style>
