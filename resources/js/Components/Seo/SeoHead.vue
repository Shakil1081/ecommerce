<script setup lang="ts">
import { computed, h } from 'vue';
import { Head } from '@inertiajs/vue3';

/**
 * Full SEO metadata for public pages (AGENTS.md §10): title, description, canonical,
 * Open Graph, Twitter card and JSON-LD. Rendered by Inertia's <Head>, so it is in the SSR HTML.
 */
const props = withDefaults(defineProps<{
  title: string;
  description?: string | null;
  canonical?: string | null;
  image?: string | null;
  type?: string;
  jsonLd?: Record<string, unknown>[];
  noindex?: boolean;
}>(), {
  description: null,
  canonical: null,
  image: null,
  type: 'website',
  jsonLd: () => [],
  noindex: false,
});

// <title> gets "| Pick & Pearl" from the global title template in app.ts / ssr.ts;
// social cards have no template, so they carry the full form explicitly.
const fullTitle = computed(() => `${props.title} | Pick & Pearl`);

// <Head> prints string children verbatim, so JSON-LD goes through a function node.
// Every "<" is escaped as < so a value containing a closing script tag cannot break out of it.
const jsonLdNodes = computed(() => props.jsonLd.map((schema) => {
  const json = JSON.stringify(schema).replace(/</g, '\\u003c');
  return () => h('script', { type: 'application/ld+json' }, json);
}));
</script>

<template>
  <Head :title="title">
    <meta v-if="description" head-key="description" name="description" :content="description">
    <meta v-if="noindex" head-key="robots" name="robots" content="noindex, nofollow">
    <link v-if="canonical" head-key="canonical" rel="canonical" :href="canonical">
    <meta head-key="og:title" property="og:title" :content="fullTitle">
    <meta v-if="description" head-key="og:description" property="og:description" :content="description">
    <meta head-key="og:type" property="og:type" :content="type">
    <meta v-if="canonical" head-key="og:url" property="og:url" :content="canonical">
    <meta v-if="image" head-key="og:image" property="og:image" :content="image">
    <meta head-key="og:site_name" property="og:site_name" content="Pick & Pearl">
    <meta head-key="twitter:card" name="twitter:card" :content="image ? 'summary_large_image' : 'summary'">
    <meta head-key="twitter:title" name="twitter:title" :content="fullTitle">
    <meta v-if="description" head-key="twitter:description" name="twitter:description" :content="description">
    <meta v-if="image" head-key="twitter:image" name="twitter:image" :content="image">
    <component :is="node" v-for="(node, index) in jsonLdNodes" :key="index" />
  </Head>
</template>
