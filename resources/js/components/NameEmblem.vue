<script setup lang="ts">
/** The small badge a member wears next to their name: an emoji or a picture. */
defineProps<{ emblem?: string | null }>()
</script>

<template>
    <span v-if="emblem" class="emblem" aria-hidden="true">
        <img v-if="emblem.startsWith('/') || emblem.startsWith('http')" :src="emblem" alt="" />
        <template v-else>{{ emblem }}</template>
    </span>
</template>
