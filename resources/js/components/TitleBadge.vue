<script setup lang="ts">
import { computed } from 'vue'
import { TITLE_STYLES } from '../types/domain'

/**
 * A member's title as an illustrated badge: its icon above, the title in a
 * glowing frame in its own colour, and a light that sweeps across now and
 * then. The text is live, so it reads in the member's language.
 */
const props = defineProps<{ text: string; look?: string | null; size?: 'sm' | 'md' | 'lg' }>()
const styled = computed(() => !!props.look && (TITLE_STYLES as readonly string[]).includes(props.look))
</script>

<template>
    <span
        class="title-badge"
        :class="[`title-badge--${size ?? 'md'}`, styled ? `title-badge--${look}` : 'title-badge--plain']"
    >
        <img v-if="styled && size !== 'sm'" :src="`/images/titles/${look}.webp`" alt="" class="title-badge__icon" />
        <span class="title-badge__plate">
            <span class="title-badge__text">{{ text }}</span>
        </span>
    </span>
</template>
