<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { computed } from 'vue'
import { isRtl, t } from '../i18n'

/** Heading shared by the growth-journey pages, with the way back to the hub. */
defineProps<{ eyebrow: string; title: string; text: string; back?: string }>()
const BackIcon = computed(() => (isRtl.value ? ChevronRight : ChevronLeft))
</script>

<template>
    <div class="section-head">
        <div>
            <RouterLink :to="{ name: back ?? 'journey' }" class="back-link">
                <component :is="BackIcon" :size="16" aria-hidden="true" />
                {{ t(back === 'goals' ? 'nav.goals' : 'nav.journey') }}
            </RouterLink>
            <span class="eyebrow">{{ eyebrow }}</span>
            <h2>{{ title }}</h2>
            <p>{{ text }}</p>
        </div>
        <div class="head-actions"><slot /></div>
    </div>
</template>
