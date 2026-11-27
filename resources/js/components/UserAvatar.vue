<script setup lang="ts">
import { computed } from 'vue'
import { frameSrc, isFrameStyle } from '../lib/frames'

/**
 * A member's photo (or initial). With a frame, the photo sits in the frame's
 * rounded-square window and the frame picture is laid over it; a light sweeps
 * across the metal now and then.
 */
const props = defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg'; frame?: string | null }>()
const picture = computed(() => frameSrc(props.frame))
/** The six illustrated frames share one cut; uploaded frames use their own proportions. */
const illustrated = computed(() => isFrameStyle(props.frame))
</script>

<template>
    <span
        class="avatar"
        :class="[
            `avatar--${size ?? 'md'}`,
            picture ? (illustrated ? `avatar--framed avatar--framed-${frame}` : 'avatar--frame-picture') : '',
        ]"
        :style="picture && illustrated ? { '--frame-src': `url(${picture})` } : undefined"
    >
        <span v-if="picture && illustrated" class="avatar__window">
            <img v-if="src" :src="src" alt="" class="avatar__photo" />
            <template v-else>{{ name.trim().slice(0, 1).toUpperCase() || '?' }}</template>
        </span>
        <template v-else>
            <img v-if="src" :src="src" alt="" class="avatar__photo" />
            <template v-else>{{ name.trim().slice(0, 1).toUpperCase() || '?' }}</template>
        </template>
        <img v-if="picture" :src="picture" alt="" class="avatar__frame-picture" />
        <span v-if="picture && illustrated" class="avatar__shine" aria-hidden="true" />
    </span>
</template>
