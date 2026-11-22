<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import AccountShell from '@/Components/Account/AccountShell.vue';

interface Address {
  id: number;
  label: string;
  recipient_name: string;
  phone: string;
  district_id: number | null;
  district_name: string | null;
  address_line: string;
  is_default: boolean;
}

interface District {
  id: number;
  name_en: string;
  name_bn: string;
}

const props = defineProps<{
  addresses: Address[];
  districts: District[];
}>();

const editingId = ref<number | null>(null);
const showForm = ref(props.addresses.length === 0);

const form = useForm({
  label: 'Home',
  recipient_name: '',
  phone: '',
  district_id: null as number | null,
  address_line: '',
  is_default: false,
});

const openNew = () => {
  editingId.value = null;
  form.reset();
  form.clearErrors();
  showForm.value = true;
};

const openEdit = (address: Address) => {
  editingId.value = address.id;
  form.label = address.label;
  form.recipient_name = address.recipient_name;
  form.phone = address.phone;
  form.district_id = address.district_id;
  form.address_line = address.address_line;
  form.is_default = address.is_default;
  form.clearErrors();
  showForm.value = true;
};

const closeForm = () => {
  showForm.value = false;
  editingId.value = null;
};

const submit = () => {
  const options = { preserveScroll: true, onSuccess: closeForm };
  if (editingId.value) {
    form.put(`/account/addresses/${editingId.value}`, options);
  } else {
    form.post('/account/addresses', options);
  }
};

const makeDefault = (address: Address) => {
  router.put(`/account/addresses/${address.id}`, { ...address, is_default: true }, { preserveScroll: true });
};

const remove = (address: Address) => {
  if (confirm(`Remove the "${address.label}" address?`)) {
    router.delete(`/account/addresses/${address.id}`, { preserveScroll: true });
  }
};
</script>

<template>
  <StorefrontLayout title="Addresses">
    <SeoHead title="Saved addresses" :noindex="true" />

    <AccountShell heading="Saved addresses" subheading="Your default address is filled in automatically at checkout.">
      <div class="address-grid">
        <div v-for="address in addresses" :key="address.id" class="card address-card" :class="{ default: address.is_default }">
          <div class="card-top">
            <strong>{{ address.label }}</strong>
            <span v-if="address.is_default" class="default-pill">Default</span>
          </div>
          <p class="lines">
            {{ address.recipient_name }}<br>
            {{ address.phone }}<br>
            {{ address.address_line }}<template v-if="address.district_name">, {{ address.district_name }}</template>
          </p>
          <div class="actions">
            <button type="button" class="link-btn" @click="openEdit(address)">Edit</button>
            <button v-if="!address.is_default" type="button" class="link-btn" @click="makeDefault(address)">Make default</button>
            <button type="button" class="link-btn danger" @click="remove(address)">Remove</button>
          </div>
        </div>

        <button v-if="!showForm" type="button" class="card add-card" @click="openNew">
          <span aria-hidden="true">＋</span> Add new address
        </button>
      </div>

      <form v-if="showForm" class="card panel" @submit.prevent="submit" novalidate>
        <h2>{{ editingId ? 'Edit address' : 'New address' }}</h2>

        <div class="form-grid">
          <div class="sf-field">
            <label class="sf-field-label" for="label">Label</label>
            <input id="label" v-model="form.label" class="sf-input" :class="{ 'has-error': form.errors.label }" type="text" placeholder="Home, Office…" required>
            <span v-if="form.errors.label" class="sf-field-error">{{ form.errors.label }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="recipient_name">Recipient name</label>
            <input id="recipient_name" v-model="form.recipient_name" class="sf-input" :class="{ 'has-error': form.errors.recipient_name }" type="text" autocomplete="name" required>
            <span v-if="form.errors.recipient_name" class="sf-field-error">{{ form.errors.recipient_name }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="phone">Mobile number</label>
            <input id="phone" v-model="form.phone" class="sf-input" :class="{ 'has-error': form.errors.phone }" type="tel" inputmode="tel" autocomplete="tel" placeholder="017XXXXXXXX" required>
            <span v-if="form.errors.phone" class="sf-field-error">{{ form.errors.phone }}</span>
          </div>

          <div class="sf-field">
            <label class="sf-field-label" for="district_id">District</label>
            <select id="district_id" v-model.number="form.district_id" class="sf-input" :class="{ 'has-error': form.errors.district_id }" required>
              <option :value="null" disabled>Select district</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name_en }} ({{ district.name_bn }})</option>
            </select>
            <span v-if="form.errors.district_id" class="sf-field-error">{{ form.errors.district_id }}</span>
          </div>
        </div>

        <div class="sf-field">
          <label class="sf-field-label" for="address_line">Full address</label>
          <textarea id="address_line" v-model="form.address_line" class="sf-input" :class="{ 'has-error': form.errors.address_line }" placeholder="House, road, area, thana" required />
          <span v-if="form.errors.address_line" class="sf-field-error">{{ form.errors.address_line }}</span>
        </div>

        <label class="sf-check default-check">
          <input v-model="form.is_default" type="checkbox"> Use as my default address
        </label>

        <div class="form-actions">
          <button v-if="addresses.length" type="button" class="btn btn-ghost" @click="closeForm">Cancel</button>
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Save address' }}
          </button>
        </div>
      </form>
    </AccountShell>
  </StorefrontLayout>
</template>

<style lang="scss" scoped>
.address-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: $space-4;
  margin-bottom: $space-6;
}

.address-card {
  padding: $space-4 $space-5;
  display: flex;
  flex-direction: column;

  &.default {
    border-color: $color-primary-300;
  }

  .card-top {
    @include flex-between;
    font-size: $fs-sm;
    color: $color-neutral-900;
  }

  .default-pill {
    font-size: $fs-2xs;
    font-weight: $fw-bold;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: $radius-full;
    background: $color-primary-50;
    color: $color-primary-700;
  }

  .lines {
    flex: 1;
    margin: $space-3 0;
    font-size: $fs-sm;
    color: $color-neutral-600;
    line-height: $lh-relaxed;
  }

  .actions {
    display: flex;
    flex-wrap: wrap;
    gap: $space-4;
  }
}

.link-btn {
  background: none;
  border: none;
  padding: 0;
  font-size: $fs-sm;
  font-weight: $fw-semibold;
  color: $color-primary-600;
  cursor: pointer;

  &.danger {
    color: $color-danger;
  }
}

.add-card {
  @include flex-center;
  gap: $space-2;
  min-height: 150px;
  border: 2px dashed $color-neutral-300;
  background: transparent;
  color: $color-neutral-600;
  font-size: $fs-sm;
  font-weight: $fw-semibold;
  cursor: pointer;
  box-shadow: none;

  &:hover {
    border-color: $color-primary-400;
    color: $color-primary-600;
  }
}

.panel {
  padding: $space-5;

  h2 {
    font-size: $fs-base;
    font-weight: $fw-semibold;
    color: $color-neutral-900;
    margin-bottom: $space-4;
  }
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 $space-4;

  @media (max-width: 640px) {
    grid-template-columns: 1fr;
  }
}

.default-check {
  margin-bottom: $space-5;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: $space-3;
}
</style>
