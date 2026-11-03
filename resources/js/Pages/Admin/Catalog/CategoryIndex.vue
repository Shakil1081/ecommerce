<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface CategoryItem {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
  parent_name: string | null;
  description: string | null;
  is_active: boolean;
  is_featured: boolean;
  sort_order: number;
  products_count: number;
  children_count: number;
  created_at: string | null;
}

interface ParentOption {
  id: number;
  name: string;
}

interface Stats {
  total: number;
  root: number;
  active: number;
  featured: number;
}

interface Filters {
  search?: string | null;
  status?: string | null;
  action?: string | null;
}

const props = defineProps<{
  categories: CategoryItem[];
  parentOptions: ParentOption[];
  stats: Stats;
  filters: Filters;
}>();

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const showModal = ref(false);
const editingCategory = ref<CategoryItem | null>(null);

const form = useForm({
  id: null as number | null,
  name: '',
  slug: '',
  parent_id: '' as number | string,
  sort_order: 0,
  description: '',
  is_active: true,
  is_featured: false,
  seo_title: '',
  seo_description: '',
});

const autoGenerateSlug = ref(true);

const handleNameInput = () => {
  if (autoGenerateSlug.value && !editingCategory.value) {
    form.slug = form.name
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }
};

const openCreateModal = () => {
  editingCategory.value = null;
  autoGenerateSlug.value = true;
  form.reset();
  form.clearErrors();
  form.id = null;
  form.is_active = true;
  form.is_featured = false;
  form.sort_order = props.categories.length + 1;
  showModal.value = true;
};

const openEditModal = (category: CategoryItem) => {
  editingCategory.value = category;
  autoGenerateSlug.value = false;
  form.clearErrors();
  form.id = category.id;
  form.name = category.name;
  form.slug = category.slug;
  form.parent_id = category.parent_id || '';
  form.sort_order = category.sort_order;
  form.description = category.description || '';
  form.is_active = category.is_active;
  form.is_featured = category.is_featured;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingCategory.value = null;
  form.reset();
  form.clearErrors();
};

const saveCategory = () => {
  if (editingCategory.value) {
    form.put(`/admin/categories/${editingCategory.value.id}`, {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post('/admin/categories', {
      onSuccess: () => closeModal(),
    });
  }
};

const deleteCategory = (category: CategoryItem) => {
  if (category.products_count > 0) {
    alert(`Cannot delete "${category.name}" because ${category.products_count} product(s) are assigned to it. Please reassign or delete the products first.`);
    return;
  }
  if (category.children_count > 0) {
    alert(`Cannot delete "${category.name}" because it has ${category.children_count} subcategory/subcategories. Please remove or reassign them first.`);
    return;
  }
  if (confirm(`Are you sure you want to delete category "${category.name}"?`)) {
    router.delete(`/admin/categories/${category.id}`);
  }
};

const applyFilters = () => {
  router.get('/admin/categories', {
    search: searchQuery.value || undefined,
    status: statusFilter.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = '';
  router.get('/admin/categories');
};

// Available parent categories (excluding the one being edited to avoid cyclic loops)
const filteredParentOptions = computed(() => {
  if (!editingCategory.value) return props.parentOptions;
  return props.parentOptions.filter(p => p.id !== editingCategory.value?.id);
});

onMounted(() => {
  if (props.filters.action === 'create') {
    openCreateModal();
  }
});
</script>

<template>
  <AdminLayout title="Categories & Storefront Taxonomy">
    <Head title="Categories — Admin Catalog Hub" />

    <div class="categories-container">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <div class="header-badge">
            <span>📁 Catalog Operations</span>
          </div>
          <h1 class="header-title">Categories & Storefront Hierarchy</h1>
          <p class="header-subtitle">
            Organize products into hierarchical departments, categories, and subcategories for the public storefront and navigation menus.
          </p>
        </div>
        <div class="header-actions">
          <a
            href="/shop"
            target="_blank"
            class="btn btn-storefront-view"
            title="Open public shop page"
          >
            <span>🛍️</span> View Shop
            <span class="ext-arrow">↗</span>
          </a>
          <button type="button" class="btn btn-primary" @click="openCreateModal">
            <span>➕</span> Add New Category
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon-wrap icon-total">📁</div>
          <div class="stat-info">
            <span class="stat-label">Total Categories</span>
            <span class="stat-value">{{ stats.total }}</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrap icon-root">🌳</div>
          <div class="stat-info">
            <span class="stat-label">Root Categories</span>
            <span class="stat-value">{{ stats.root }}</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrap icon-active">🟢</div>
          <div class="stat-info">
            <span class="stat-label">Active on Storefront</span>
            <span class="stat-value">{{ stats.active }}</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrap icon-featured">⭐</div>
          <div class="stat-info">
            <span class="stat-label">Featured Collections</span>
            <span class="stat-value">{{ stats.featured }}</span>
          </div>
        </div>
      </div>

      <!-- Filters & Search Toolbar -->
      <div class="card toolbar-card">
        <div class="toolbar-wrap">
          <div class="search-box-wrap">
            <span class="search-icon">🔍</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search category by name, slug or description..."
              class="toolbar-search-input"
              @keyup.enter="applyFilters"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="clear-search-btn"
              @click="searchQuery = ''; applyFilters()"
            >
              ✕
            </button>
          </div>

          <div class="filter-controls">
            <select
              v-model="statusFilter"
              class="toolbar-select"
              @change="applyFilters"
            >
              <option value="">All Statuses</option>
              <option value="active">Active Only</option>
              <option value="inactive">Inactive Only</option>
            </select>

            <button
              v-if="searchQuery || statusFilter"
              type="button"
              class="btn-reset-filter"
              @click="resetFilters"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Categories Data Table -->
      <div class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Category Name & Slug</th>
                <th>Parent Hierarchy</th>
                <th>Assigned Products</th>
                <th>Sort Order</th>
                <th>Featured</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in categories" :key="cat.id">
                <td>
                  <div class="cat-identity">
                    <span class="cat-icon">{{ cat.parent_id ? '↳ 📂' : '📁' }}</span>
                    <div>
                      <div class="cat-name">
                        {{ cat.name }}
                        <span v-if="cat.children_count > 0" class="badge-sub-count">
                          {{ cat.children_count }} sub
                        </span>
                      </div>
                      <div class="cat-slug">/{{ cat.slug }}</div>
                    </div>
                  </div>
                </td>

                <td>
                  <span v-if="cat.parent_name" class="parent-badge">
                    {{ cat.parent_name }}
                  </span>
                  <span v-else class="text-muted">
                    — Root Level
                  </span>
                </td>

                <td>
                  <span class="products-count-badge" :class="{ 'has-products': cat.products_count > 0 }">
                    🛍️ {{ cat.products_count }} {{ cat.products_count === 1 ? 'Product' : 'Products' }}
                  </span>
                </td>

                <td>
                  <span class="sort-order-pill">#{{ cat.sort_order }}</span>
                </td>

                <td>
                  <span v-if="cat.is_featured" class="badge badge-featured">
                    ⭐ Featured
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>

                <td>
                  <span v-if="cat.is_active" class="badge badge-success">
                    Active
                  </span>
                  <span v-else class="badge badge-danger">
                    Inactive
                  </span>
                </td>

                <td class="text-right actions-cell">
                  <button
                    type="button"
                    class="btn btn-action-edit"
                    title="Edit category"
                    @click="openEditModal(cat)"
                  >
                    ✏️ Edit
                  </button>
                  <button
                    type="button"
                    class="btn btn-action-delete"
                    :disabled="cat.products_count > 0 || cat.children_count > 0"
                    :title="cat.products_count > 0 ? 'Cannot delete category with products' : (cat.children_count > 0 ? 'Cannot delete category with subcategories' : 'Delete category')"
                    @click="deleteCategory(cat)"
                  >
                    🗑️ Delete
                  </button>
                </td>
              </tr>

              <tr v-if="categories.length === 0">
                <td colspan="7" class="empty-row">
                  <div class="empty-state">
                    <span class="empty-icon">📁</span>
                    <h3 class="empty-title">No categories found</h3>
                    <p class="empty-desc">
                      Get started by creating your first storefront category to organize your products.
                    </p>
                    <button type="button" class="btn btn-primary" @click="openCreateModal">
                      ➕ Add Category
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create / Edit Category Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click="closeModal"></div>

      <div v-if="showModal" class="category-modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <div class="modal-title-wrap">
            <span class="modal-icon">{{ editingCategory ? '✏️' : '➕' }}</span>
            <h3 class="modal-title">{{ editingCategory ? 'Edit Category' : 'Create New Category' }}</h3>
          </div>
          <button type="button" class="modal-close-btn" @click="closeModal">✕</button>
        </div>

        <form @submit.prevent="saveCategory">
          <div class="modal-body">
            <!-- Name Input -->
            <div class="form-group">
              <label class="form-label" for="category_name">
                Category Name <span class="required">*</span>
              </label>
              <input
                id="category_name"
                v-model="form.name"
                type="text"
                class="form-control"
                placeholder="e.g., Smartphones & Accessories, Ladies Bags, Electronics..."
                required
                @input="handleNameInput"
              />
              <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
            </div>

            <!-- Slug Input -->
            <div class="form-group">
              <div class="label-with-hint">
                <label class="form-label" for="category_slug">Storefront URL Slug</label>
                <label class="auto-slug-toggle">
                  <input v-model="autoGenerateSlug" type="checkbox" /> Auto-generate
                </label>
              </div>
              <div class="slug-input-wrap">
                <span class="slug-prefix">/shop?category=</span>
                <input
                  id="category_slug"
                  v-model="form.slug"
                  type="text"
                  class="form-control slug-input"
                  placeholder="smartphones-mobile-accessories"
                  :readonly="autoGenerateSlug && !editingCategory"
                />
              </div>
              <span v-if="form.errors.slug" class="form-error">{{ form.errors.slug }}</span>
            </div>

            <!-- Parent Category Dropdown -->
            <div class="form-group">
              <label class="form-label" for="parent_category">
                Parent Category <span class="hint-label">(Optional Hierarchy)</span>
              </label>
              <select
                id="parent_category"
                v-model="form.parent_id"
                class="form-control"
              >
                <option value="">— None (Top-Level Root Category) —</option>
                <option
                  v-for="parent in filteredParentOptions"
                  :key="parent.id"
                  :value="parent.id"
                >
                  {{ parent.name }}
                </option>
              </select>
              <span v-if="form.errors.parent_id" class="form-error">{{ form.errors.parent_id }}</span>
            </div>

            <!-- Sort Order -->
            <div class="form-group">
              <label class="form-label" for="sort_order">Display Sort Order</label>
              <input
                id="sort_order"
                v-model.number="form.sort_order"
                type="number"
                min="0"
                class="form-control"
                placeholder="0"
              />
              <span class="field-hint">Lower numbers appear first on menus and public storefront pills.</span>
              <span v-if="form.errors.sort_order" class="form-error">{{ form.errors.sort_order }}</span>
            </div>

            <!-- Description -->
            <div class="form-group">
              <label class="form-label" for="category_desc">Description (Optional)</label>
              <textarea
                id="category_desc"
                v-model="form.description"
                rows="3"
                class="form-control textarea"
                placeholder="Brief description for SEO and catalog headers..."
              ></textarea>
              <span v-if="form.errors.description" class="form-error">{{ form.errors.description }}</span>
            </div>

            <!-- Toggles: Active & Featured -->
            <div class="toggles-grid">
              <label class="toggle-card">
                <input
                  v-model="form.is_active"
                  type="checkbox"
                  class="toggle-checkbox"
                />
                <div class="toggle-content">
                  <span class="toggle-title">Active on Storefront</span>
                  <span class="toggle-desc">Show category in navigation, filters and product pages.</span>
                </div>
              </label>

              <label class="toggle-card">
                <input
                  v-model="form.is_featured"
                  type="checkbox"
                  class="toggle-checkbox"
                />
                <div class="toggle-content">
                  <span class="toggle-title">⭐ Featured Collection</span>
                  <span class="toggle-desc">Highlight category with badges and homepage showcase.</span>
                </div>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline"
              :disabled="form.processing"
              @click="closeModal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing"
            >
              <span v-if="form.processing">Saving...</span>
              <span v-else>{{ editingCategory ? 'Save Changes' : 'Create Category' }}</span>
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<style lang="scss" scoped>
.categories-container {
  padding: $space-6;
  display: flex;
  flex-direction: column;
  gap: $space-6;
}

// Page Header
.page-header {
  @include flex-between;
  flex-wrap: wrap;
  gap: $space-4;

  .header-badge {
    display: inline-block;
    padding: 2px 10px;
    background: $color-primary-50;
    color: $color-primary-700;
    border-radius: $radius-full;
    font-size: 11px;
    font-weight: $fw-bold;
    margin-bottom: $space-1;
    border: 1px solid $color-primary-200;
  }

  .header-title {
    font-size: $fs-2xl;
    font-weight: $fw-extrabold;
    color: hsl(342, 85%, 22%);
    margin: 0;
    font-family: $font-heading;
  }

  .header-subtitle {
    font-size: $fs-sm;
    color: $color-neutral-500;
    margin-top: $space-1;
    max-width: 680px;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: $space-3;

    .btn-storefront-view {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 16px;
      border-radius: $radius-lg;
      background: #ffffff;
      border: 1.5px solid hsl(342, 35%, 86%);
      color: $color-primary-700;
      font-size: 13px;
      font-weight: $fw-bold;
      text-decoration: none;
      transition: all $transition-fast;

      &:hover {
        background: $color-primary-50;
        border-color: $color-primary-400;
        transform: translateY(-1px);
      }

      .ext-arrow {
        font-size: 11px;
        opacity: 0.7;
      }
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 18px;
      border-radius: $radius-lg;
      background: linear-gradient(135deg, $color-primary-500, $color-primary-600);
      color: #ffffff;
      border: none;
      font-size: 13px;
      font-weight: $fw-bold;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(235, 40, 108, 0.25);
      transition: all $transition-fast;

      &:hover {
        background: linear-gradient(135deg, $color-primary-600, $color-primary-700);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(235, 40, 108, 0.35);
      }
    }
  }
}

// Stats Cards
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: $space-4;

  .stat-card {
    background: #ffffff;
    border-radius: $radius-xl;
    padding: $space-4 $space-5;
    border: 1px solid hsl(342, 30%, 92%);
    display: flex;
    align-items: center;
    gap: $space-4;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);

    .stat-icon-wrap {
      width: 44px;
      height: 44px;
      border-radius: $radius-lg;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;

      &.icon-total { background: hsl(342, 85%, 96%); }
      &.icon-root { background: hsl(142, 60%, 95%); }
      &.icon-active { background: hsl(214, 85%, 95%); }
      &.icon-featured { background: hsl(43, 90%, 95%); }
    }

    .stat-info {
      display: flex;
      flex-direction: column;

      .stat-label {
        font-size: 12px;
        color: $color-neutral-500;
        font-weight: $fw-semibold;
      }

      .stat-value {
        font-size: 1.5rem;
        font-weight: $fw-extrabold;
        color: hsl(342, 85%, 20%);
        font-family: $font-heading;
      }
    }
  }
}

// Toolbar Card
.toolbar-card {
  background: #ffffff;
  border-radius: $radius-xl;
  padding: $space-3 $space-4;
  border: 1px solid hsl(342, 30%, 92%);

  .toolbar-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: $space-3;

    .search-box-wrap {
      position: relative;
      flex: 1;
      min-width: 280px;
      display: flex;
      align-items: center;

      .search-icon {
        position: absolute;
        left: 12px;
        font-size: 14px;
        color: $color-neutral-400;
      }

      .toolbar-search-input {
        width: 100%;
        padding: 8px 32px 8px 34px;
        border: 1.5px solid $color-neutral-200;
        border-radius: $radius-lg;
        font-size: 13px;
        outline: none;

        &:focus {
          border-color: $color-primary-500;
        }
      }

      .clear-search-btn {
        position: absolute;
        right: 10px;
        background: none;
        border: none;
        color: $color-neutral-400;
        cursor: pointer;
        font-size: 12px;

        &:hover {
          color: $color-neutral-700;
        }
      }
    }

    .filter-controls {
      display: flex;
      align-items: center;
      gap: $space-2;

      .toolbar-select {
        padding: 8px 12px;
        border-radius: $radius-lg;
        border: 1.5px solid $color-neutral-200;
        background: #ffffff;
        font-size: 13px;
        outline: none;
        color: $color-neutral-700;

        &:focus {
          border-color: $color-primary-500;
        }
      }

      .btn-reset-filter {
        padding: 8px 14px;
        border: 1px solid $color-neutral-300;
        border-radius: $radius-lg;
        background: #ffffff;
        color: $color-neutral-600;
        font-size: 12px;
        font-weight: $fw-bold;
        cursor: pointer;

        &:hover {
          background: $color-neutral-100;
        }
      }
    }
  }
}

// Table Card
.table-card {
  background: #ffffff;
  border-radius: $radius-xl;
  border: 1px solid hsl(342, 30%, 92%);
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;

  th {
    padding: $space-3 $space-4;
    background: hsl(342, 60%, 98%);
    font-size: 12px;
    font-weight: $fw-bold;
    color: hsl(342, 70%, 25%);
    border-bottom: 1px solid hsl(342, 30%, 90%);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  td {
    padding: $space-4;
    border-bottom: 1px solid $color-neutral-100;
    font-size: 13px;
    vertical-align: middle;
  }

  tbody tr:hover {
    background-color: hsl(342, 90%, 99%);
  }

  .cat-identity {
    display: flex;
    align-items: center;
    gap: $space-3;

    .cat-icon {
      font-size: 18px;
    }

    .cat-name {
      font-weight: $fw-bold;
      color: hsl(342, 85%, 20%);
      display: flex;
      align-items: center;
      gap: 6px;

      .badge-sub-count {
        font-size: 10px;
        padding: 1px 6px;
        border-radius: $radius-full;
        background: $color-primary-50;
        color: $color-primary-700;
        border: 1px solid $color-primary-200;
        font-weight: $fw-bold;
      }
    }

    .cat-slug {
      font-size: 11px;
      color: $color-neutral-400;
      font-family: monospace;
    }
  }

  .parent-badge {
    padding: 3px 8px;
    background: $color-neutral-100;
    border-radius: $radius-md;
    font-size: 11px;
    font-weight: $fw-semibold;
    color: $color-neutral-700;
  }

  .products-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: $radius-full;
    font-size: 12px;
    font-weight: $fw-semibold;
    background: $color-neutral-100;
    color: $color-neutral-600;

    &.has-products {
      background: hsl(342, 85%, 95%);
      color: $color-primary-700;
      font-weight: $fw-bold;
    }
  }

  .sort-order-pill {
    padding: 2px 8px;
    border-radius: $radius-md;
    background: $color-neutral-100;
    font-size: 11px;
    font-weight: $fw-bold;
    color: $color-neutral-600;
  }

  .badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: $radius-full;
    font-size: 11px;
    font-weight: $fw-bold;

    &.badge-success {
      background: hsl(142, 70%, 92%);
      color: hsl(142, 75%, 25%);
    }

    &.badge-danger {
      background: hsl(0, 75%, 94%);
      color: hsl(0, 75%, 35%);
    }

    &.badge-featured {
      background: hsl(43, 90%, 90%);
      color: hsl(38, 90%, 25%);
    }
  }

  .actions-cell {
    white-space: nowrap;

    .btn {
      padding: 5px 10px;
      border-radius: $radius-md;
      font-size: 12px;
      font-weight: $fw-bold;
      cursor: pointer;
      margin-left: 6px;
      transition: all $transition-fast;
    }

    .btn-action-edit {
      background: #ffffff;
      border: 1px solid hsl(342, 35%, 85%);
      color: $color-primary-700;

      &:hover {
        background: $color-primary-50;
        border-color: $color-primary-400;
      }
    }

    .btn-action-delete {
      background: #ffffff;
      border: 1px solid #fecdd3;
      color: #e11d48;

      &:hover:not(:disabled) {
        background: #ffe4e6;
        border-color: #f43f5e;
      }

      &:disabled {
        opacity: 0.4;
        cursor: not-allowed;
      }
    }
  }

  .empty-row {
    text-align: center;
    padding: $space-12;

    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: $space-2;

      .empty-icon {
        font-size: 40px;
        opacity: 0.6;
      }

      .empty-title {
        font-size: $fs-lg;
        font-weight: $fw-bold;
        color: $color-neutral-800;
        margin: 0;
      }

      .empty-desc {
        font-size: 13px;
        color: $color-neutral-500;
        margin-bottom: $space-3;
      }
    }
  }
}

// Modal Styles
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
}

.category-modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 90%;
  max-width: 540px;
  max-height: 90vh;
  background: #ffffff;
  border-radius: $radius-2xl;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  z-index: 1001;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  .modal-header {
    @include flex-between;
    padding: $space-4 $space-6;
    border-bottom: 1px solid hsl(342, 30%, 92%);
    background: hsl(342, 60%, 98%);

    .modal-title-wrap {
      display: flex;
      align-items: center;
      gap: $space-2;

      .modal-icon {
        font-size: 18px;
      }

      .modal-title {
        font-size: $fs-base;
        font-weight: $fw-bold;
        color: hsl(342, 85%, 22%);
        margin: 0;
      }
    }

    .modal-close-btn {
      background: none;
      border: none;
      font-size: 16px;
      color: $color-neutral-400;
      cursor: pointer;

      &:hover {
        color: $color-neutral-800;
      }
    }
  }

  .modal-body {
    padding: $space-6;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: $space-4;

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;

      .form-label {
        font-size: 12px;
        font-weight: $fw-bold;
        color: $color-neutral-700;

        .required {
          color: $color-danger;
        }

        .hint-label {
          font-weight: normal;
          color: $color-neutral-400;
        }
      }

      .label-with-hint {
        @include flex-between;

        .auto-slug-toggle {
          font-size: 11px;
          color: $color-primary-600;
          font-weight: $fw-semibold;
          cursor: pointer;
          display: flex;
          align-items: center;
          gap: 4px;
        }
      }

      .form-control {
        padding: 8px 12px;
        border: 1.5px solid $color-neutral-300;
        border-radius: $radius-lg;
        font-size: 13px;
        outline: none;
        transition: border-color $transition-fast;

        &:focus {
          border-color: $color-primary-500;
        }

        &.textarea {
          resize: vertical;
        }
      }

      .slug-input-wrap {
        display: flex;
        align-items: center;
        border: 1.5px solid $color-neutral-300;
        border-radius: $radius-lg;
        overflow: hidden;

        &:focus-within {
          border-color: $color-primary-500;
        }

        .slug-prefix {
          padding: 8px 10px;
          background: $color-neutral-100;
          color: $color-neutral-500;
          font-size: 11px;
          font-family: monospace;
          border-right: 1px solid $color-neutral-300;
        }

        .slug-input {
          border: none;
          border-radius: 0;
          flex: 1;

          &:focus {
            box-shadow: none;
          }
        }
      }

      .field-hint {
        font-size: 11px;
        color: $color-neutral-400;
      }

      .form-error {
        font-size: 11px;
        color: $color-danger;
        font-weight: $fw-bold;
      }
    }

    .toggles-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: $space-3;
      margin-top: $space-2;

      @media (max-width: 500px) {
        grid-template-columns: 1fr;
      }

      .toggle-card {
        padding: $space-3;
        border: 1.5px solid $color-neutral-200;
        border-radius: $radius-lg;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        cursor: pointer;
        transition: all $transition-fast;

        &:has(.toggle-checkbox:checked) {
          border-color: $color-primary-400;
          background: hsl(342, 90%, 98%);
        }

        .toggle-checkbox {
          margin-top: 3px;
          accent-color: $color-primary-600;
        }

        .toggle-content {
          display: flex;
          flex-direction: column;

          .toggle-title {
            font-size: 12px;
            font-weight: $fw-bold;
            color: $color-neutral-800;
          }

          .toggle-desc {
            font-size: 10px;
            color: $color-neutral-400;
            line-height: 1.3;
          }
        }
      }
    }
  }

  .modal-footer {
    padding: $space-4 $space-6;
    border-top: 1px solid $color-neutral-200;
    display: flex;
    justify-content: flex-end;
    gap: $space-3;
    background: $color-neutral-50;

    .btn {
      padding: 8px 18px;
      border-radius: $radius-lg;
      font-size: 13px;
      font-weight: $fw-bold;
      cursor: pointer;
    }

    .btn-outline {
      border: 1px solid $color-neutral-300;
      background: #ffffff;
      color: $color-neutral-700;

      &:hover {
        background: $color-neutral-100;
      }
    }

    .btn-primary {
      background: linear-gradient(135deg, $color-primary-500, $color-primary-600);
      color: #ffffff;
      border: none;
      box-shadow: 0 4px 12px rgba(235, 40, 108, 0.25);

      &:hover {
        background: linear-gradient(135deg, $color-primary-600, $color-primary-700);
      }
    }
  }
}
</style>
