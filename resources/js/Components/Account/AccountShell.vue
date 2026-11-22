<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

defineProps<{
  heading: string;
  subheading?: string;
}>();

const page = usePage<SharedProps>();
const userName = computed(() => page.props.auth.user?.name ?? '');

const links = [
  { href: '/account', label: 'Overview', icon: '🏠' },
  { href: '/account/orders', label: 'My Orders', icon: '📦' },
  { href: '/account/wishlist', label: 'Wishlist', icon: '♡' },
  { href: '/account/addresses', label: 'Addresses', icon: '📍' },
  { href: '/account/profile', label: 'Profile & Password', icon: '👤' },
];

const isActive = (href: string): boolean => {
  const path = page.url.split('?')[0];
  return href === '/account' ? path === '/account' : path.startsWith(href);
};

const logout = () => router.post('/account/logout');
</script>

<template>
  <div class="account-shell container">
    <aside class="account-nav card">
      <div class="account-greeting">
        <span class="avatar">{{ userName.charAt(0).toUpperCase() }}</span>
        <div>
          <span class="hello">Hello,</span>
          <strong class="name">{{ userName }}</strong>
        </div>
      </div>
      <nav class="nav-links" aria-label="My account">
        <Link
          v-for="link in links"
          :key="link.href"
          :href="link.href"
          class="nav-link"
          :class="{ active: isActive(link.href) }"
        >
          <span class="icon" aria-hidden="true">{{ link.icon }}</span>
          {{ link.label }}
        </Link>
        <button type="button" class="nav-link logout" @click="logout">
          <span class="icon" aria-hidden="true">↩</span>
          Log out
        </button>
      </nav>
    </aside>

    <section class="account-content">
      <header class="content-header">
        <h1>{{ heading }}</h1>
        <p v-if="subheading">{{ subheading }}</p>
      </header>
      <slot />
    </section>
  </div>
</template>

<style lang="scss" scoped>
.account-shell {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: $space-6;
  padding-top: $space-8;
  padding-bottom: $space-12;
  align-items: start;

  @media (max-width: 860px) {
    grid-template-columns: 1fr;
    padding-top: $space-4;
  }
}

.account-nav {
  padding: $space-4;
  position: sticky;
  top: 100px;

  @media (max-width: 860px) {
    position: static;
    padding: $space-3;
  }
}

.account-greeting {
  display: flex;
  align-items: center;
  gap: $space-3;
  padding: $space-2 $space-2 $space-4;
  border-bottom: 1px solid $color-neutral-200;
  margin-bottom: $space-3;

  @media (max-width: 860px) {
    display: none;
  }

  .avatar {
    @include flex-center;
    width: 42px;
    height: 42px;
    border-radius: $radius-full;
    background: $color-primary-100;
    color: $color-primary-700;
    font-weight: $fw-bold;
  }

  .hello {
    display: block;
    font-size: $fs-xs;
    color: $color-neutral-500;
  }

  .name {
    font-size: $fs-sm;
    color: $color-neutral-900;
  }
}

.nav-links {
  display: flex;
  flex-direction: column;
  gap: 2px;

  @media (max-width: 860px) {
    flex-direction: row;
    overflow-x: auto;
    gap: $space-2;
    -webkit-overflow-scrolling: touch;
  }
}

.nav-link {
  display: flex;
  align-items: center;
  gap: $space-3;
  padding: $space-3;
  border-radius: $radius-md;
  font-size: $fs-sm;
  font-weight: $fw-medium;
  color: $color-neutral-700;
  text-decoration: none;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  white-space: nowrap;
  transition: background $transition-fast, color $transition-fast;

  &:hover {
    background: $color-neutral-100;
  }

  &.active {
    background: $color-primary-50;
    color: $color-primary-700;
    font-weight: $fw-semibold;
  }

  &.logout {
    color: $color-neutral-500;
    margin-top: $space-2;
    border-top: 1px solid $color-neutral-100;
    border-radius: 0;

    @media (max-width: 860px) {
      margin-top: 0;
      border-top: none;
      border-radius: $radius-md;
    }
  }

  .icon {
    width: 20px;
    text-align: center;
  }
}

.content-header {
  margin-bottom: $space-6;

  h1 {
    font-size: $fs-2xl;
    font-weight: $fw-bold;
    color: $color-neutral-900;
  }

  p {
    margin-top: $space-1;
    color: $color-neutral-500;
    font-size: $fs-sm;
  }
}
</style>
