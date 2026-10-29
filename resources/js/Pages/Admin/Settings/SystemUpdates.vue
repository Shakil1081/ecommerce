<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface SystemInfo {
  appName: string;
  appVersion: string;
  environment: string;
  debug: boolean;
  phpVersion: string;
  laravelVersion: string;
  dbConnection: string;
  cacheDriver: string;
  queueDriver: string;
  storageDisk: string;
  activeStorageDriver: string;
}

interface ReleaseItem {
  version: string;
  date: string | null;
  is_unreleased: boolean;
  sections: Record<string, string[]>;
}

defineProps<{
  systemInfo: SystemInfo;
  releases: ReleaseItem[];
}>();

const getSectionBadgeClass = (section: string) => {
  const s = section.toLowerCase();
  if (s.includes('add')) return 'badge-success';
  if (s.includes('fix')) return 'badge-warning';
  if (s.includes('change')) return 'badge-info';
  if (s.includes('security')) return 'badge-danger';
  return 'badge-neutral';
};
</script>

<template>
  <AdminLayout title="System Updates & Release History">
    <Head title="System Info & Release Notes" />

    <div class="system-updates-container">
      <div class="page-header">
        <div>
          <h1 class="header-title">System Info & Release Timeline</h1>
          <p class="header-subtitle">
            Inspect active runtime diagnostics and git-tracked version release history.
          </p>
        </div>
        <div class="header-actions">
          <Link href="/admin/settings/media-storage" class="btn btn-secondary">
            <span>☁️</span> Media Storage
          </Link>
          <a
            href="https://github.com/Shakil1081/ecommerce/releases"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-primary"
          >
            <span>🚀</span> GitHub Releases
          </a>
        </div>
      </div>

      <!-- System Diagnostics Grid -->
      <div class="card diagnostics-card">
        <h2 class="card-title">Runtime Diagnostics</h2>
        <div class="diagnostics-grid">
          <div class="diag-item">
            <span class="diag-label">App Version</span>
            <span class="diag-value">
              <span class="badge badge-primary">{{ systemInfo.appVersion }}</span>
            </span>
          </div>

          <div class="diag-item">
            <span class="diag-label">Environment</span>
            <span class="diag-value">
              <span class="badge" :class="systemInfo.environment === 'production' ? 'badge-danger' : 'badge-warning'">
                {{ systemInfo.environment }}
              </span>
            </span>
          </div>

          <div class="diag-item">
            <span class="diag-label">PHP Runtime</span>
            <span class="diag-value code-font">v{{ systemInfo.phpVersion }}</span>
          </div>

          <div class="diag-item">
            <span class="diag-label">Laravel Framework</span>
            <span class="diag-value code-font">v{{ systemInfo.laravelVersion }}</span>
          </div>

          <div class="diag-item">
            <span class="diag-label">Database Connection</span>
            <span class="diag-value code-font">{{ systemInfo.dbConnection }}</span>
          </div>

          <div class="diag-item">
            <span class="diag-label">Active Media Driver</span>
            <span class="diag-value">{{ systemInfo.activeStorageDriver }}</span>
          </div>
        </div>
      </div>

      <!-- Release Timeline -->
      <div class="card timeline-card">
        <div class="timeline-header">
          <h2 class="card-title">Version Release History</h2>
          <span class="timeline-badge">{{ releases.length }} Releases Tracked</span>
        </div>

        <div class="releases-timeline">
          <div
            v-for="(release, index) in releases"
            :key="release.version"
            class="timeline-node"
            :class="{ 'latest-node': index === 0 }"
          >
            <div class="timeline-marker">
              <span class="marker-dot"></span>
              <span class="marker-line" v-if="index !== releases.length - 1"></span>
            </div>

            <div class="release-content">
              <div class="release-top">
                <div class="release-identity">
                  <h3 class="release-version">
                    {{ release.is_unreleased ? 'Unreleased (Next Sprint)' : `v${release.version}` }}
                  </h3>
                  <span v-if="release.is_unreleased" class="badge badge-warning">
                    In Development
                  </span>
                  <span v-else class="badge badge-success">
                    Release
                  </span>
                </div>
                <div class="release-date" v-if="release.date">
                  📅 {{ release.date }}
                </div>
              </div>

              <!-- Sections (Added, Changed, Fixed, etc.) -->
              <div class="sections-stack">
                <div
                  v-for="(items, sectionTitle) in release.sections"
                  :key="sectionTitle"
                  class="release-section"
                >
                  <div class="section-title-wrap">
                    <span class="badge" :class="getSectionBadgeClass(sectionTitle)">
                      {{ sectionTitle }}
                    </span>
                  </div>
                  <ul class="release-items-list">
                    <li v-for="(item, i) in items" :key="i" class="release-item">
                      {{ item }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.system-updates-container {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: $spacing-4;

  .header-title {
    font-size: $font-size-2xl;
    font-weight: 700;
    color: $color-text-main;
    margin-bottom: $spacing-1;
  }

  .header-subtitle {
    font-size: $font-size-sm;
    color: $color-text-muted;
    max-width: 650px;
  }

  .header-actions {
    display: flex;
    gap: $spacing-3;
  }
}

.diagnostics-card {
  display: flex;
  flex-direction: column;
  gap: $spacing-4;
}

.card-title {
  font-size: $font-size-lg;
  font-weight: 600;
  color: $color-text-main;
}

.diagnostics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: $spacing-4;
}

.diag-item {
  display: flex;
  flex-direction: column;
  gap: $spacing-1;
  padding: $spacing-3 $spacing-4;
  border-radius: $radius-md;
  background: $color-surface-hover;
  border: 1px solid $color-border;

  .diag-label {
    font-size: $font-size-xs;
    font-weight: 600;
    color: $color-text-muted;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .diag-value {
    font-size: $font-size-sm;
    font-weight: 600;
    color: $color-text-main;
  }

  .code-font {
    font-family: monospace;
  }
}

.timeline-card {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: $spacing-4;
  border-bottom: 1px solid $color-border;
}

.timeline-badge {
  font-size: $font-size-xs;
  font-weight: 600;
  color: $color-text-muted;
  background: $color-surface-hover;
  padding: 2px $spacing-2;
  border-radius: $radius-sm;
}

.releases-timeline {
  display: flex;
  flex-direction: column;
  gap: $spacing-6;
}

.timeline-node {
  display: flex;
  gap: $spacing-4;
  position: relative;
}

.timeline-marker {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;

  .marker-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: $color-primary;
    border: 3px solid hsla(217, 91%, 60%, 0.2);
    z-index: 2;
  }

  .marker-line {
    width: 2px;
    flex-grow: 1;
    background: $color-border;
    margin-top: $spacing-1;
  }
}

.release-content {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  gap: $spacing-4;
  padding-bottom: $spacing-6;
}

.release-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: $spacing-2;
}

.release-identity {
  display: flex;
  align-items: center;
  gap: $spacing-3;

  .release-version {
    font-size: $font-size-base;
    font-weight: 700;
    color: $color-text-main;
  }
}

.release-date {
  font-size: $font-size-xs;
  color: $color-text-muted;
}

.sections-stack {
  display: flex;
  flex-direction: column;
  gap: $spacing-4;
}

.release-section {
  display: flex;
  flex-direction: column;
  gap: $spacing-2;

  .section-title-wrap {
    display: flex;
  }
}

.release-items-list {
  list-style-type: disc;
  padding-left: $spacing-5;
  display: flex;
  flex-direction: column;
  gap: $spacing-2;

  .release-item {
    font-size: $font-size-sm;
    color: $color-text-main;
    line-height: 1.5;
  }
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px $spacing-2;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  font-weight: 600;

  &.badge-primary {
    background: hsla(217, 91%, 60%, 0.12);
    color: $color-primary;
  }

  &.badge-neutral {
    background: $color-surface-hover;
    color: $color-text-muted;
  }

  &.badge-warning {
    background: hsla(38, 92%, 50%, 0.12);
    color: $color-warning;
  }

  &.badge-success {
    background: hsla(142, 71%, 45%, 0.12);
    color: $color-success;
  }

  &.badge-danger {
    background: hsla(0, 84%, 60%, 0.12);
    color: $color-danger;
  }

  &.badge-info {
    background: hsla(199, 89%, 48%, 0.12);
    color: $color-info;
  }
}
</style>

