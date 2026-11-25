<script setup lang="ts">
/** `frame` is a reward key (bronze, silver, gold, royal); anything else draws the plain avatar. */
defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg'; frame?: string | null }>()
</script>

<template>
    <span
        class="avatar"
        :class="[`avatar--${size ?? 'md'}`, frame && frame !== 'none' ? `avatar--frame avatar--frame-${frame}` : '']"
    >
        <img v-if="src" :src="src" alt="" />
        <template v-else>{{ name.trim().slice(0, 1).toUpperCase() || '?' }}</template>
    </span>
</template>
