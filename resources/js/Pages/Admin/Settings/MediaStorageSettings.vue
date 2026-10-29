<script setup lang="ts">
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

interface StorageSetting {
  id: string;
  driver: 'local' | 's3' | 'r2' | 'gcs' | 'minio';
  name: string;
  is_active: boolean;
  credentials: Record<string, string>;
  latency_ms: number | null;
  last_tested_at: string | null;
  created_at: string;
}

const props = defineProps<{
  settings: StorageSetting[];
  activeDriver: string;
}>();

const testing = ref(false);
const testResult = ref<{ success: boolean; message: string; latency_ms: number | null } | null>(null);

const form = useForm({
  id: '',
  driver: 'r2',
  name: 'Cloudflare R2 (Zero Egress)',
  is_active: false,
  credentials: {
    account_id: '',
    key: '',
    secret: '',
    bucket: '',
    region: 'auto',
    cdn_url: '',
    endpoint: '',
  },
});

const selectDriver = (driver: 'local' | 's3' | 'r2' | 'gcs' | 'minio') => {
  form.driver = driver;
  if (driver === 'r2') {
    form.name = 'Cloudflare R2 (Zero Egress)';
    form.credentials.region = 'auto';
  } else if (driver === 's3') {
    form.name = 'Amazon AWS S3';
    form.credentials.region = 'ap-southeast-1';
  } else if (driver === 'minio') {
    form.name = 'MinIO Self-Hosted';
    form.credentials.endpoint = 'http://127.0.0.1:9000';
  } else if (driver === 'local') {
    form.name = 'Local Server Storage';
  }
};

const runPreflightTest = async () => {
  testing.value = true;
  testResult.value = null;

  try {
    const response = await axios.post('/admin/settings/media-storage/test', {
      driver: form.driver,
      credentials: form.credentials,
      id: form.id || null,
    });
    testResult.value = response.data;
  } catch (error: any) {
    testResult.value = {
      success: false,
      message: error.response?.data?.message || 'Handshake failed',
      latency_ms: null,
    };
  } finally {
    testing.value = false;
  }
};

const saveConfig = () => {
  form.post('/admin/settings/media-storage', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      testResult.value = null;
    },
  });
};

const testExistingSetting = async (id: string, driver: string) => {
  testing.value = true;
  testResult.value = null;

  try {
    const response = await axios.post('/admin/settings/media-storage/test', {
      driver,
      id,
    });
    testResult.value = response.data;
    router.reload({ only: ['settings'] });
  } catch (error: any) {
    testResult.value = {
      success: false,
      message: error.response?.data?.message || 'Handshake failed',
      latency_ms: null,
    };
  } finally {
    testing.value = false;
  }
};
</script>

<template>
  <AdminLayout title="Multi-Cloud Media Storage Engine">
    <div class="storage-settings-view">
      <!-- Active Storage Status Banner -->
      <div class="active-banner card">
        <div class="card-body banner-body">
          <div class="banner-info">
            <span class="badge badge-primary">ACTIVE MEDIA DISK</span>
            <h3 class="banner-title">Current Engine: <span class="highlight">{{ activeDriver.toUpperCase() }}</span></h3>
            <p class="banner-desc">
              All high-res catalog images, WebP/AVIF conversions, and document uploads route dynamically through this driver.
            </p>
          </div>
          <div class="banner-badge">
            <span class="pulse-dot"></span>
            <span>Live Filesystem Bound</span>
          </div>
        </div>
      </div>

      <div class="settings-grid">
        <!-- Configured Storage Disks List -->
        <div class="configurations-card card">
          <div class="card-header">
            <h4>Configured Storage Providers</h4>
          </div>
          <div class="card-body">
            <div v-if="settings.length === 0" class="empty-state">
              <p>No external storage providers configured yet. Currently running on default local filesystem.</p>
            </div>

            <div v-else class="provider-list">
              <div
                v-for="setting in settings"
                :key="setting.id"
                class="provider-item"
                :class="{ 'is-active': setting.is_active }"
              >
                <div class="provider-info">
                  <div class="provider-title-row">
                    <span class="provider-name">{{ setting.name }}</span>
                    <span v-if="setting.is_active" class="badge badge-success">ACTIVE</span>
                    <span v-if="setting.latency_ms" class="badge badge-secondary">{{ setting.latency_ms }}ms</span>
                  </div>
                  <div class="provider-meta">
                    <span>Driver: <strong>{{ setting.driver }}</strong></span>
                    <span v-if="setting.credentials?.bucket">Bucket: <strong>{{ setting.credentials.bucket }}</strong></span>
                    <span v-if="setting.last_tested_at">Tested: {{ setting.last_tested_at }}</span>
                  </div>
                </div>

                <div class="provider-actions">
                  <button
                    class="btn btn-outline btn-sm"
                    :disabled="testing"
                    @click="testExistingSetting(setting.id, setting.driver)"
                  >
                    Test Handshake
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add / Configure Storage Provider Form -->
        <div class="configure-card card">
          <div class="card-header">
            <h4>Add or Configure Provider</h4>
          </div>
          <div class="card-body">
            <form @submit.prevent="saveConfig" class="storage-form">
              <!-- Driver Tabs -->
              <div class="form-group">
                <label class="form-label">Select Storage Driver</label>
                <div class="driver-tabs">
                  <button
                    type="button"
                    class="driver-tab-btn"
                    :class="{ active: form.driver === 'r2' }"
                    @click="selectDriver('r2')"
                  >
                    ⚡ Cloudflare R2
                  </button>
                  <button
                    type="button"
                    class="driver-tab-btn"
                    :class="{ active: form.driver === 's3' }"
                    @click="selectDriver('s3')"
                  >
                    ☁️ AWS S3
                  </button>
                  <button
                    type="button"
                    class="driver-tab-btn"
                    :class="{ active: form.driver === 'minio' }"
                    @click="selectDriver('minio')"
                  >
                    🗄️ MinIO
                  </button>
                  <button
                    type="button"
                    class="driver-tab-btn"
                    :class="{ active: form.driver === 'local' }"
                    @click="selectDriver('local')"
                  >
                    💻 Local Disk
                  </button>
                </div>
              </div>

              <!-- Name & Active -->
              <div class="form-row">
                <div class="form-group flex-1">
                  <label class="form-label">Configuration Display Name</label>
                  <input v-model="form.name" type="text" class="form-input" required>
                </div>
                <div class="form-group checkbox-group">
                  <label class="checkbox-label">
                    <input v-model="form.is_active" type="checkbox">
                    <span>Activate as Primary Disk</span>
                  </label>
                </div>
              </div>

              <!-- R2 Specific Fields -->
              <div v-if="form.driver === 'r2'" class="credentials-block">
                <div class="form-group">
                  <label class="form-label">Cloudflare Account ID</label>
                  <input v-model="form.credentials.account_id" type="text" class="form-input" placeholder="e.g. 9a8b7c6d5e4f3a2b">
                </div>
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label class="form-label">R2 Access Key ID</label>
                    <input v-model="form.credentials.key" type="text" class="form-input">
                  </div>
                  <div class="form-group flex-1">
                    <label class="form-label">R2 Secret Access Key</label>
                    <input v-model="form.credentials.secret" type="password" class="form-input">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label class="form-label">R2 Bucket Name</label>
                    <input v-model="form.credentials.bucket" type="text" class="form-input" placeholder="ecommerce-media">
                  </div>
                  <div class="form-group flex-1">
                    <label class="form-label">Public Custom Domain / CDN URL</label>
                    <input v-model="form.credentials.cdn_url" type="url" class="form-input" placeholder="https://media.yourstore.com">
                  </div>
                </div>
              </div>

              <!-- S3 Specific Fields -->
              <div v-if="form.driver === 's3'" class="credentials-block">
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label class="form-label">AWS Access Key ID</label>
                    <input v-model="form.credentials.key" type="text" class="form-input">
                  </div>
                  <div class="form-group flex-1">
                    <label class="form-label">AWS Secret Access Key</label>
                    <input v-model="form.credentials.secret" type="password" class="form-input">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label class="form-label">S3 Bucket Name</label>
                    <input v-model="form.credentials.bucket" type="text" class="form-input">
                  </div>
                  <div class="form-group flex-1">
                    <label class="form-label">AWS Region</label>
                    <input v-model="form.credentials.region" type="text" class="form-input" placeholder="ap-southeast-1">
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">CloudFront CDN URL (Optional)</label>
                  <input v-model="form.credentials.cdn_url" type="url" class="form-input" placeholder="https://d1234.cloudfront.net">
                </div>
              </div>

              <!-- MinIO Specific Fields -->
              <div v-if="form.driver === 'minio'" class="credentials-block">
                <div class="form-group">
                  <label class="form-label">MinIO Endpoint URL</label>
                  <input v-model="form.credentials.endpoint" type="url" class="form-input" placeholder="http://127.0.0.1:9000">
                </div>
                <div class="form-row">
                  <div class="form-group flex-1">
                    <label class="form-label">Access Key</label>
                    <input v-model="form.credentials.key" type="text" class="form-input">
                  </div>
                  <div class="form-group flex-1">
                    <label class="form-label">Secret Key</label>
                    <input v-model="form.credentials.secret" type="password" class="form-input">
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Bucket Name</label>
                  <input v-model="form.credentials.bucket" type="text" class="form-input">
                </div>
              </div>

              <!-- Test Result Box -->
              <div v-if="testResult" class="test-result-box" :class="{ success: testResult.success, error: !testResult.success }">
                <span>{{ testResult.success ? '✅' : '❌' }}</span>
                <span>{{ testResult.message }}</span>
              </div>

              <!-- Action Buttons -->
              <div class="form-actions">
                <button
                  type="button"
                  class="btn btn-outline"
                  :disabled="testing"
                  @click="runPreflightTest"
                >
                  <span v-if="testing">⏳ Testing Handshake...</span>
                  <span v-else>🔍 Pre-Flight Test Connection</span>
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="form.processing"
                >
                  Save Configuration
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.storage-settings-view {
  @include flex-column;
  gap: $space-6;
}

.active-banner {
  background: linear-gradient(135deg, $color-neutral-900 0%, $color-secondary-950 100%);
  color: $color-neutral-0;
  border-color: $color-neutral-800;

  .banner-body {
    @include flex-between;
  }

  .banner-title {
    color: $color-neutral-0;
    margin: $space-2 0;

    .highlight {
      color: $color-primary-400;
    }
  }

  .banner-desc {
    color: $color-neutral-400;
    font-size: $fs-sm;
    max-width: 650px;
  }

  .banner-badge {
    @include flex-center;
    gap: $space-2;
    background-color: rgba($color-primary-500, 0.15);
    border: 1px solid rgba($color-primary-500, 0.3);
    color: $color-primary-300;
    padding: $space-2 $space-4;
    border-radius: $radius-full;
    font-size: $fs-xs;
    font-weight: $fw-semibold;

    .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: $radius-full;
      background-color: $color-primary-400;
      box-shadow: 0 0 8px $color-primary-400;
    }
  }
}

.settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: $space-6;

  @include media-down(lg) {
    grid-template-columns: 1fr;
  }
}

.provider-list {
  @include flex-column;
  gap: $space-3;

  .provider-item {
    padding: $space-4;
    border: 1px solid $color-neutral-200;
    border-radius: $radius-lg;
    background-color: $color-neutral-50;
    @include flex-between;

    &.is-active {
      border-color: $color-primary-500;
      background-color: rgba($color-primary-500, 0.04);
    }

    .provider-title-row {
      @include flex-start;
      gap: $space-2;
      margin-bottom: $space-1;

      .provider-name {
        font-weight: $fw-semibold;
        color: $color-neutral-900;
      }
    }

    .provider-meta {
      display: flex;
      gap: $space-4;
      font-size: $fs-xs;
      color: $color-neutral-500;
    }
  }
}

.storage-form {
  @include flex-column;
  gap: $space-4;

  .driver-tabs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: $space-2;
    margin-top: $space-2;

    .driver-tab-btn {
      padding: $space-2;
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      border: 1px solid $color-neutral-300;
      border-radius: $radius-md;
      background-color: $color-neutral-0;
      color: $color-neutral-700;
      transition: all $transition-fast;

      &.active {
        background-color: $color-primary-600;
        border-color: $color-primary-600;
        color: $color-neutral-0;
      }
    }
  }

  .form-row {
    display: flex;
    gap: $space-4;
    align-items: center;

    .flex-1 { flex: 1; }
  }

  .form-group {
    @include flex-column;
    gap: $space-1;

    .form-label {
      font-size: $fs-xs;
      font-weight: $fw-semibold;
      color: $color-neutral-700;
    }

    .form-input {
      padding: $space-2 $space-3;
      border: 1px solid $color-neutral-300;
      border-radius: $radius-md;
      font-size: $fs-sm;
      background-color: $color-neutral-0;
      @include focus-ring($color-primary-500);
    }
  }

  .checkbox-group {
    margin-top: $space-5;

    .checkbox-label {
      display: flex;
      align-items: center;
      gap: $space-2;
      font-size: $fs-sm;
      font-weight: $fw-medium;
      cursor: pointer;
    }
  }

  .credentials-block {
    @include flex-column;
    gap: $space-4;
    padding: $space-4;
    background-color: $color-neutral-50;
    border-radius: $radius-lg;
    border: 1px solid $color-neutral-200;
  }

  .test-result-box {
    padding: $space-3 $space-4;
    border-radius: $radius-md;
    font-size: $fs-xs;
    font-weight: $fw-medium;
    display: flex;
    align-items: center;
    gap: $space-2;

    &.success {
      background-color: hsl(142, 76%, 94%);
      color: hsl(142, 76%, 24%);
      border: 1px solid hsl(142, 76%, 80%);
    }

    &.error {
      background-color: hsl(0, 86%, 95%);
      color: hsl(0, 72%, 35%);
      border: 1px solid hsl(0, 86%, 85%);
    }
  }

  .form-actions {
    @include flex-between;
    margin-top: $space-2;
  }
}
</style>

