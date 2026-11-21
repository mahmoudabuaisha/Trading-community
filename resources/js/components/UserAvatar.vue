<script setup lang="ts">
defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg' }>()
</script>

<template>
    <span class="avatar" :class="`avatar--${size ?? 'md'}`">
        <img v-if="src" :src="src" alt="" />
        <template v-else>{{ name.trim().slice(0, 1).toUpperCase() || '?' }}</template>
    </span>
</template>
