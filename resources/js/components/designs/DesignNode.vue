<script setup lang="ts">
import { computed } from 'vue'
import { appleStem, clip, shapePath } from '../../lib/shapes'

/** One node of a map or tree: its shape, its state, and its label. */
const props = defineProps<{
    x: number
    y: number
    r: number
    shape: string
    label: string
    state: 'done' | 'open' | 'locked' | 'root'
    selected?: boolean
    hideLabel?: boolean
    maxLabel?: number
}>()

const d = computed(() => shapePath(props.shape, props.x, props.y, props.r))
const apple = computed(() => (props.shape === 'apple' ? appleStem(props.x, props.y, props.r) : null))
</script>

<template>
    <g class="dnode" :class="[`dnode--${state}`, { 'dnode--selected': selected }]">
        <title>{{ label }}</title>
        <path v-if="selected" :d="shapePath(shape, x, y, r + 7)" class="dnode__halo" />
        <path :d="d" class="dnode__body" />
        <template v-if="apple">
            <path :d="apple.stem" class="dnode__stem" />
            <path :d="apple.leaf" class="dnode__leaf" />
        </template>
        <text v-if="!hideLabel" :x="x" :y="y + r + 18" class="dnode__label" text-anchor="middle">{{
            clip(label, maxLabel)
        }}</text>
    </g>
</template>
