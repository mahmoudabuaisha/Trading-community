<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{ icon: Component; label: string; value: string | number; note: string }>()
</script>

<template>
    <div class="stat-card">
        <span class="stat-card__icon"><component :is="icon" :size="18" aria-hidden="true" /></span>
        <small>{{ label }}</small>
        <strong>{{ value }}</strong>
        <span class="stat-card__note">{{ note }}</span>
    </div>
</template>
