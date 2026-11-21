<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'

defineProps<{ title: string; text: string }>()
</script>

<template>
    <div class="empty">
        <span class="empty__icon"><Sparkles :size="20" aria-hidden="true" /></span>
        <b>{{ title }}</b>
        <p>{{ text }}</p>
        <slot />
    </div>
</template>
