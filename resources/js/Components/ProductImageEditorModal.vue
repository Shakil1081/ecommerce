<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const props = withDefaults(
  defineProps<{
    show: boolean;
    imageSrc: string;
    maxSizeKb?: number;
    initialFileName?: string;
  }>(),
  {
    maxSizeKb: 512, // 0.5 MB limit
    initialFileName: 'product-image.webp',
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'save', file: File, dataUrl: string, sizeKb: number): void;
}>();

const imageElement = ref<HTMLImageElement | null>(null);
const cropperInstance = ref<Cropper | null>(null);

// Editor Controls State
const selectedRatio = ref<number | 'free'>(1); // 1:1 square recommended for ecommerce
const outputFormat = ref<'image/webp' | 'image/jpeg'>('image/webp');
const quality = ref<number>(0.85);
const maxDimension = ref<number>(1200);

// Real-Time Calculations
const calculatedSizeKb = ref<number>(0);
const calculatedDimensions = ref<{ width: number; height: number }>({ width: 0, height: 0 });
const isEstimating = ref<boolean>(false);
const autoOptimizing = ref<boolean>(false);
const flipX = ref<number>(1);
const flipY = ref<number>(1);

const destroyCropper = () => {
  if (cropperInstance.value) {
    cropperInstance.value.destroy();
    cropperInstance.value = null;
  }
};

const initCropper = () => {
  destroyCropper();
  if (!imageElement.value) return;

  cropperInstance.value = new Cropper(imageElement.value, {
    aspectRatio: selectedRatio.value === 'free' ? NaN : selectedRatio.value,
    viewMode: 1, // Restrict the crop box not to exceed the size of the canvas
    dragMode: 'move',
    autoCropArea: 0.9,
    restore: false,
    guides: true,
    center: true,
    highlight: false,
    cropBoxMovable: true,
    cropBoxResizable: true,
    toggleDragModeOnDblclick: false,
    ready() {
      scheduleSizeEstimate();
    },
    cropend() {
      scheduleSizeEstimate();
    },
    zoom() {
      scheduleSizeEstimate();
    },
  });
};

// Debounced size estimator
let estimateTimer: ReturnType<typeof setTimeout> | null = null;
const scheduleSizeEstimate = () => {
  if (estimateTimer) clearTimeout(estimateTimer);
  estimateTimer = setTimeout(() => {
    estimateOutputSize();
  }, 180);
};

const getCroppedCanvasWithConstraints = (customQuality?: number, customDim?: number): HTMLCanvasElement | null => {
  if (!cropperInstance.value) return null;

  const targetDim = customDim || maxDimension.value;
  return cropperInstance.value.getCroppedCanvas({
    maxWidth: targetDim,
    maxHeight: targetDim,
    imageSmoothingEnabled: true,
    imageSmoothingQuality: 'high',
    fillColor: '#ffffff', // Clean white background for transparency in jpeg
  });
};

const estimateOutputSize = async () => {
  if (!cropperInstance.value) return;
  isEstimating.value = true;

  try {
    const canvas = getCroppedCanvasWithConstraints();
    if (!canvas) {
      isEstimating.value = false;
      return;
    }

    calculatedDimensions.value = { width: canvas.width, height: canvas.height };

    canvas.toBlob(
      (blob) => {
        if (blob) {
          calculatedSizeKb.value = Math.round((blob.size / 1024) * 10) / 10;
        }
        isEstimating.value = false;
      },
      outputFormat.value,
      quality.value
    );
  } catch (err) {
    console.error('Failed to estimate canvas size:', err);
    isEstimating.value = false;
  }
};

// Transform Controls
const setAspectRatio = (ratio: number | 'free') => {
  selectedRatio.value = ratio;
  if (!cropperInstance.value) return;
  cropperInstance.value.setAspectRatio(ratio === 'free' ? NaN : ratio);
  scheduleSizeEstimate();
};

const rotate = (degree: number) => {
  if (!cropperInstance.value) return;
  cropperInstance.value.rotate(degree);
  scheduleSizeEstimate();
};

const toggleFlip = (axis: 'x' | 'y') => {
  if (!cropperInstance.value) return;
  if (axis === 'x') {
    flipX.value = flipX.value * -1;
    cropperInstance.value.scaleX(flipX.value);
  } else {
    flipY.value = flipY.value * -1;
    cropperInstance.value.scaleY(flipY.value);
  }
  scheduleSizeEstimate();
};

const zoom = (delta: number) => {
  if (!cropperInstance.value) return;
  cropperInstance.value.zoom(delta);
  scheduleSizeEstimate();
};

const resetCrop = () => {
  if (!cropperInstance.value) return;
  flipX.value = 1;
  flipY.value = 1;
  cropperInstance.value.reset();
  setAspectRatio(1);
  quality.value = 0.85;
  maxDimension.value = 1200;
  scheduleSizeEstimate();
};

// Automated 0.5MB (512 KB) Optimizer
const autoOptimizeForHalfMb = async () => {
  if (!cropperInstance.value) return;
  autoOptimizing.value = true;

  const testQualities = [0.85, 0.78, 0.70, 0.62, 0.55, 0.48];
  const testDimensions = [1200, 1000, 800, 600];

  let bestBlob: Blob | null = null;
  let bestQuality = quality.value;
  let bestDim = maxDimension.value;

  for (const dim of testDimensions) {
    for (const q of testQualities) {
      const canvas = getCroppedCanvasWithConstraints(q, dim);
      if (!canvas) continue;

      const blob = await new Promise<Blob | null>((resolve) => {
        canvas.toBlob((b) => resolve(b), outputFormat.value, q);
      });

      if (blob) {
        const sizeKb = blob.size / 1024;
        if (sizeKb <= props.maxSizeKb) {
          bestBlob = blob;
          bestQuality = q;
          bestDim = dim;
          break;
        }
      }
    }
    if (bestBlob) break;
  }

  if (bestBlob) {
    quality.value = bestQuality;
    maxDimension.value = bestDim;
    calculatedSizeKb.value = Math.round((bestBlob.size / 1024) * 10) / 10;
  } else {
    // Aggressive fallback
    quality.value = 0.5;
    maxDimension.value = 600;
    await estimateOutputSize();
  }

  autoOptimizing.value = false;
};

// Save & Apply
const applyAndSave = () => {
  if (!cropperInstance.value || calculatedSizeKb.value > props.maxSizeKb) return;

  const canvas = getCroppedCanvasWithConstraints();
  if (!canvas) return;

  canvas.toBlob(
    (blob) => {
      if (!blob) return;

      const ext = outputFormat.value === 'image/webp' ? 'webp' : 'jpg';
      const baseName = props.initialFileName.replace(/\.[^/.]+$/, '');
      const finalFileName = `${baseName}.${ext}`;

      const finalFile = new File([blob], finalFileName, { type: outputFormat.value });
      const finalDataUrl = canvas.toDataURL(outputFormat.value, quality.value);

      emit('save', finalFile, finalDataUrl, calculatedSizeKb.value);
      emit('close');
    },
    outputFormat.value,
    quality.value
  );
};

// Watch for modal visibility changes
watch(
  () => props.show,
  async (newVal) => {
    if (newVal) {
      await nextTick();
      initCropper();
    } else {
      destroyCropper();
    }
  }
);

watch([outputFormat, quality, maxDimension], () => {
  scheduleSizeEstimate();
});

onBeforeUnmount(() => {
  destroyCropper();
  if (estimateTimer) clearTimeout(estimateTimer);
});
</script>

<template>
  <div v-if="show" class="image-editor-modal-backdrop" @click.self="$emit('close')">
    <div class="image-editor-card">
      <!-- Modal Header -->
      <div class="editor-header">
        <div class="header-left">
          <div class="header-icon-wrap">
            <span class="header-icon">🎨</span>
          </div>
          <div>
            <h3 class="editor-title">Product Image Editor & Compressor</h3>
            <p class="editor-subtitle">
              Crop, adjust, and optimize product images to strictly meet the <strong>0.5 MB (512 KB)</strong> store limit.
            </p>
          </div>
        </div>
        <button type="button" class="btn-close" @click="$emit('close')" aria-label="Close modal">✕</button>
      </div>

      <!-- Modal Body (Two-Column Layout) -->
      <div class="editor-body">
        <!-- Left: Cropper Workspace -->
        <div class="workspace-panel">
          <div class="cropper-container">
            <img ref="imageElement" :src="imageSrc" alt="Source image for cropping" class="source-crop-img" />
          </div>

          <!-- Bottom Workspace Toolbar -->
          <div class="workspace-toolbar">
            <div class="ratio-group">
              <span class="toolbar-label">Aspect Ratio:</span>
              <button
                type="button"
                class="btn-ratio"
                :class="{ active: selectedRatio === 1 }"
                @click="setAspectRatio(1)"
                title="1:1 Square (Ideal for eCommerce PDP & Grid)"
              >
                1:1 (Square ⭐)
              </button>
              <button
                type="button"
                class="btn-ratio"
                :class="{ active: selectedRatio === 4 / 3 }"
                @click="setAspectRatio(4 / 3)"
              >
                4:3
              </button>
              <button
                type="button"
                class="btn-ratio"
                :class="{ active: selectedRatio === 16 / 9 }"
                @click="setAspectRatio(16 / 9)"
              >
                16:9
              </button>
              <button
                type="button"
                class="btn-ratio"
                :class="{ active: selectedRatio === 'free' }"
                @click="setAspectRatio('free')"
              >
                Freeform
              </button>
            </div>

            <div class="tool-actions">
              <button type="button" class="btn-tool" @click="rotate(-90)" title="Rotate Counter-Clockwise 90°">
                ↺ -90°
              </button>
              <button type="button" class="btn-tool" @click="rotate(90)" title="Rotate Clockwise 90°">
                ↻ +90°
              </button>
              <button type="button" class="btn-tool" @click="toggleFlip('x')" title="Flip Horizontal">
                ⇄ Flip H
              </button>
              <button type="button" class="btn-tool" @click="toggleFlip('y')" title="Flip Vertical">
                ⇅ Flip V
              </button>
              <button type="button" class="btn-tool" @click="zoom(0.1)" title="Zoom In">
                🔍 +
              </button>
              <button type="button" class="btn-tool" @click="zoom(-0.1)" title="Zoom Out">
                🔍 -
              </button>
              <button type="button" class="btn-tool btn-reset" @click="resetCrop" title="Reset all transforms">
                🔄 Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Right: Real-Time 0.5MB Compression & Output Settings -->
        <div class="settings-panel">
          <div class="panel-section">
            <div class="section-title">
              <span>📊 Real-Time Size & Optimization</span>
            </div>

            <!-- Size Status Card -->
            <div
              class="size-meter-card"
              :class="{
                'is-valid': calculatedSizeKb <= maxSizeKb && calculatedSizeKb > 0,
                'is-danger': calculatedSizeKb > maxSizeKb,
              }"
            >
              <div class="meter-top">
                <span class="meter-lbl">Estimated Output Size:</span>
                <span class="meter-val">
                  <span v-if="isEstimating" class="estimating-pulse">Calculating...</span>
                  <span v-else>{{ calculatedSizeKb }} KB</span>
                </span>
              </div>

              <!-- Progress bar against 512 KB -->
              <div class="meter-bar-track">
                <div
                  class="meter-bar-fill"
                  :style="{
                    width: `${Math.min(100, (calculatedSizeKb / maxSizeKb) * 100)}%`,
                    backgroundColor: calculatedSizeKb > maxSizeKb ? '#e11d48' : '#059669',
                  }"
                ></div>
              </div>

              <div class="meter-meta">
                <span>Max Allowed: <strong>{{ maxSizeKb }} KB (0.5 MB)</strong></span>
                <span>Dim: <strong>{{ calculatedDimensions.width }} × {{ calculatedDimensions.height }}px</strong></span>
              </div>

              <!-- Status Badge -->
              <div class="status-badge-wrap">
                <div v-if="calculatedSizeKb <= maxSizeKb" class="badge-success">
                  ✓ Compliant (Ready to Upload)
                </div>
                <div v-else class="badge-danger">
                  ⚠️ Exceeds 0.5MB Limit (+{{ Math.round(calculatedSizeKb - maxSizeKb) }} KB)
                </div>
              </div>
            </div>

            <!-- 1-Click Auto-Optimizer Button -->
            <button
              type="button"
              class="btn-auto-optimize"
              :disabled="autoOptimizing || calculatedSizeKb <= maxSizeKb"
              @click="autoOptimizeForHalfMb"
            >
              <span v-if="autoOptimizing">⏳ Auto-Compressing to &lt; 0.5MB...</span>
              <span v-else>⚡ Auto-Compress to &le; 0.5 MB</span>
            </button>
          </div>

          <!-- Compression Controls -->
          <div class="panel-section">
            <div class="section-title">
              <span>⚙️ Compression Settings</span>
            </div>

            <!-- Format Selector -->
            <div class="form-group-sm">
              <label>Output Format:</label>
              <div class="format-toggle">
                <button
                  type="button"
                  :class="{ active: outputFormat === 'image/webp' }"
                  @click="outputFormat = 'image/webp'"
                >
                  WebP (Recommended • Smallest)
                </button>
                <button
                  type="button"
                  :class="{ active: outputFormat === 'image/jpeg' }"
                  @click="outputFormat = 'image/jpeg'"
                >
                  JPEG / JPG
                </button>
              </div>
            </div>

            <!-- Quality Slider -->
            <div class="form-group-sm">
              <div class="slider-header">
                <label>Quality Compression:</label>
                <span class="slider-val">{{ Math.round(quality * 100) }}%</span>
              </div>
              <input
                type="range"
                min="0.35"
                max="0.95"
                step="0.05"
                v-model.number="quality"
                class="quality-slider"
              />
              <div class="slider-marks">
                <span>Lower Size (Faster)</span>
                <span>Higher Quality</span>
              </div>
            </div>

            <!-- Max Dimension Constraint -->
            <div class="form-group-sm">
              <label>Max Resolution (Width/Height):</label>
              <select v-model.number="maxDimension" class="select-dimension">
                <option :value="1600">1600 × 1600 px (Ultra HD)</option>
                <option :value="1200">1200 × 1200 px (Standard eCommerce ⭐)</option>
                <option :value="1000">1000 × 1000 px (Balanced)</option>
                <option :value="800">800 × 800 px (Compact / Fast Load)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="editor-footer">
        <button type="button" class="btn-cancel" @click="$emit('close')">
          Cancel
        </button>
        <button
          type="button"
          class="btn-save-cropped"
          :disabled="calculatedSizeKb > maxSizeKb || isEstimating || autoOptimizing"
          @click="applyAndSave"
        >
          <span v-if="calculatedSizeKb > maxSizeKb">⚠️ Cannot Save (Exceeds 0.5MB Limit)</span>
          <span v-else>✓ Save & Apply Optimized Image ({{ calculatedSizeKb }} KB)</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.image-editor-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: $space-4;
}

.image-editor-card {
  background: #ffffff;
  border-radius: $radius-xl;
  width: 100%;
  max-width: 1100px;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(219, 39, 119, 0.25), 0 10px 25px -5px rgba(0, 0, 0, 0.2);
  border: 1.5px solid #fbcfe8;
  position: relative;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ec4899 0%, #db2777 50%, #f43f5e 100%);
    z-index: 10;
  }
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: linear-gradient(135deg, #fff5f7 0%, #fdf2f8 50%, #fce7f3 100%);
  border-bottom: 1px solid #fbcfe8;

  .header-left {
    display: flex;
    align-items: center;
    gap: 14px;

    .header-icon-wrap {
      width: 44px;
      height: 44px;
      border-radius: $radius-md;
      background: #ffffff;
      border: 1px solid #fbcfe8;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 8px rgba(219, 39, 119, 0.1);
    }

    .editor-title {
      font-size: $fs-lg;
      font-weight: 800;
      color: #831843;
      margin: 0;
    }

    .editor-subtitle {
      font-size: $fs-xs;
      color: #9d174d;
      margin: 2px 0 0 0;
    }
  }

  .btn-close {
    background: #ffffff;
    border: 1px solid #fbcfe8;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: #9d174d;
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
      background: #fdf2f8;
      border-color: #f472b6;
      transform: scale(1.05);
    }
  }
}

.editor-body {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 0;
  flex: 1;
  overflow: hidden;
  min-height: 480px;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }
}

// Left workspace
.workspace-panel {
  display: flex;
  flex-direction: column;
  background: #0f172a;
  position: relative;
  overflow: hidden;
}

.cropper-container {
  flex: 1;
  min-height: 400px;
  max-height: 520px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0b0f19;
  position: relative;
  overflow: hidden;

  .source-crop-img {
    max-width: 100%;
    max-height: 100%;
    display: block;
  }
}

.workspace-toolbar {
  background: #1e293b;
  padding: 12px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  border-top: 1px solid #334155;

  .toolbar-label {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    margin-right: 4px;
  }

  .ratio-group {
    display: flex;
    align-items: center;
    gap: 6px;

    .btn-ratio {
      background: #334155;
      color: #cbd5e1;
      border: 1px solid #475569;
      padding: 5px 10px;
      border-radius: $radius-sm;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;

      &:hover {
        background: #475569;
        color: #ffffff;
      }

      &.active {
        background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        color: #ffffff;
        border-color: #f472b6;
      }
    }
  }

  .tool-actions {
    display: flex;
    align-items: center;
    gap: 6px;

    .btn-tool {
      background: #334155;
      color: #cbd5e1;
      border: 1px solid #475569;
      padding: 5px 9px;
      border-radius: $radius-sm;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;

      &:hover {
        background: #475569;
        color: #ffffff;
      }

      &.btn-reset {
        color: #fca5a5;
        border-color: #7f1d1d;

        &:hover {
          background: #7f1d1d;
          color: #ffffff;
        }
      }
    }
  }
}

// Right settings panel
.settings-panel {
  background: #ffffff;
  border-left: 1px solid #f1f5f9;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  overflow-y: auto;
}

.panel-section {
  display: flex;
  flex-direction: column;
  gap: 12px;

  .section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #831843;
    letter-spacing: 0.04em;
    padding-bottom: 6px;
    border-bottom: 1.5px solid #fce7f3;
  }
}

.size-meter-card {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: $radius-md;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: all 0.2s ease;

  &.is-valid {
    border-color: #a7f3d0;
    background: #f0fdf4;
  }

  &.is-danger {
    border-color: #fca5a5;
    background: #fff1f2;
  }

  .meter-top {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .meter-lbl {
      font-size: 12px;
      font-weight: 600;
      color: #475569;
    }

    .meter-val {
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
    }
  }

  .meter-bar-track {
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;

    .meter-bar-fill {
      height: 100%;
      border-radius: 4px;
      transition: width 0.3s ease, background-color 0.3s ease;
    }
  }

  .meter-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #64748b;
  }

  .status-badge-wrap {
    margin-top: 4px;

    .badge-success {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #a7f3d0;
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 11px;
      font-weight: 700;
      text-align: center;
    }

    .badge-danger {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 11px;
      font-weight: 700;
      text-align: center;
    }
  }
}

.btn-auto-optimize {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  color: #ffffff;
  border: none;
  padding: 10px 14px;
  border-radius: $radius-md;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
  transition: all 0.15s ease;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    opacity: 0.95;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.form-group-sm {
  display: flex;
  flex-direction: column;
  gap: 6px;

  label {
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
  }

  .format-toggle {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;

    button {
      background: #f1f5f9;
      border: 1.5px solid #e2e8f0;
      border-radius: $radius-sm;
      padding: 7px 6px;
      font-size: 11px;
      font-weight: 600;
      color: #475569;
      cursor: pointer;
      transition: all 0.15s ease;

      &:hover {
        background: #e2e8f0;
      }

      &.active {
        background: #fff5f7;
        border-color: #db2777;
        color: #be185d;
        font-weight: 700;
      }
    }
  }

  .slider-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .slider-val {
      font-size: 12px;
      font-weight: 800;
      color: #be185d;
    }
  }

  .quality-slider {
    width: 100%;
    accent-color: #db2777;
    cursor: pointer;
  }

  .slider-marks {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #94a3b8;
  }

  .select-dimension {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid #cbd5e1;
    border-radius: $radius-sm;
    font-size: 12px;
    font-weight: 500;
    color: #1e293b;
    background: #ffffff;
    cursor: pointer;

    &:focus {
      outline: none;
      border-color: #db2777;
      box-shadow: 0 0 0 2px rgba(219, 39, 119, 0.15);
    }
  }
}

// Modal Footer
.editor-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
  padding: 14px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;

  .btn-cancel {
    background: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 9px 18px;
    border-radius: $radius-md;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
      background: #f1f5f9;
      color: #0f172a;
    }
  }

  .btn-save-cropped {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 50%, #f43f5e 100%);
    color: #ffffff;
    border: none;
    padding: 10px 22px;
    border-radius: $radius-md;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(219, 39, 119, 0.25);
    transition: all 0.15s ease;

    &:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(219, 39, 119, 0.35);
    }

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
      background: #94a3b8;
      box-shadow: none;
    }
  }
}
</style>

