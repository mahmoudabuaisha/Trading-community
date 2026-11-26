<script setup lang="ts">
/**
 * `frame` is a built-in reward style (bronze, silver, gold, royal, diamond) or
 * the address of a frame picture uploaded by the administrators, drawn over
 * the photo. Anything else draws the plain avatar.
 */
const props = defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg'; frame?: string | null }>()
const isPicture = (frame?: string | null): frame is string =>
    !!frame && (frame.startsWith('/') || frame.startsWith('http'))
</script>

<template>
    <span
        class="avatar"
        :class="[
            `avatar--${size ?? 'md'}`,
            isPicture(props.frame)
                ? 'avatar--frame-picture'
                : frame && frame !== 'none'
                  ? `avatar--frame avatar--frame-${frame}`
                  : '',
        ]"
    >
        <img v-if="src" :src="src" alt="" class="avatar__photo" />
        <template v-else>{{ name.trim().slice(0, 1).toUpperCase() || '?' }}</template>
        <img v-if="isPicture(props.frame)" :src="props.frame" alt="" class="avatar__frame-picture" />
    </span>
</template>
