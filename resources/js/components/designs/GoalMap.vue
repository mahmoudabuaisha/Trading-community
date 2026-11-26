<script setup lang="ts">
import { computed } from 'vue'
import { isRtl } from '../../i18n'
import { twoLines } from '../../lib/shapes'
import DesignNode from './DesignNode.vue'

export interface MapStage {
    id: number
    title: string
    isDone: boolean
    children: { id: number; title: string; isDone: boolean }[]
}
export interface MapDesign {
    layout: string
    accent: string
    nodeShape: string
    backgroundUrl: string | null
}

/**
 * A goal drawn as a connected map: the goal at the root, its stages
 * branching from it and the steps of each stage beyond. Three layouts:
 * mind map (around the centre), branches (start to end) and orbit (rings).
 */
const props = defineProps<{ title: string; stages: MapStage[]; design: MapDesign; compact?: boolean }>()
const emit = defineEmits<{ toggle: [id: number] }>()

interface Point {
    id: number
    title: string
    isDone: boolean
    x: number
    y: number
}
interface Layout {
    width: number
    height: number
    root: { x: number; y: number }
    stages: (Point & { steps: Point[] })[]
    rings?: number[]
}

const layout = computed<Layout>(() => {
    const stages = props.stages
    const mirror = (x: number, width: number) => (isRtl.value ? width - x : x)

    if (props.design.layout === 'branches') {
        const leaves = Math.max(
            1,
            stages.reduce((sum, stage) => sum + Math.max(1, stage.children.length), 0),
        )
        const width = 1000
        const height = Math.max(360, leaves * 62 + 60)
        let cursor = 30
        const placed = stages.map((stage) => {
            const rows = Math.max(1, stage.children.length)
            const top = cursor
            cursor += rows * 62
            const y = top + (rows * 62) / 2
            return {
                ...stage,
                x: mirror(470, width),
                y,
                steps: stage.children.map((step, index) => ({
                    ...step,
                    x: mirror(800, width),
                    y: top + 31 + index * 62,
                })),
            }
        })
        return { width, height, root: { x: mirror(130, width), y: height / 2 }, stages: placed }
    }

    const width = 1000
    const height = props.design.layout === 'orbit' ? 720 : 760
    const cx = width / 2
    const cy = height / 2
    const count = Math.max(1, stages.length)
    const inner = props.design.layout === 'orbit' ? 175 : 200
    const outer = props.design.layout === 'orbit' ? 300 : 315
    const placed = stages.map((stage, index) => {
        // Start at the top and go round; in the mind map, stretch sideways so labels have room.
        const angle = -Math.PI / 2 + (2 * Math.PI * index) / count
        const stretch = props.design.layout === 'mindmap' ? 1.45 : 1
        const x = cx + Math.cos(angle) * inner * stretch
        const y = cy + Math.sin(angle) * inner
        const spread = Math.min(0.9, (2 * Math.PI) / count / 1.4)
        const steps = stage.children.map((step, stepIndex) => {
            const offset =
                stage.children.length > 1 ? -spread / 2 + (spread * stepIndex) / (stage.children.length - 1) : 0
            return {
                ...step,
                x: cx + Math.cos(angle + offset) * outer * stretch,
                y: cy + Math.sin(angle + offset) * outer,
            }
        })
        return { ...stage, x, y, steps }
    })
    return {
        width,
        height,
        root: { x: cx, y: cy },
        stages: placed,
        rings: props.design.layout === 'orbit' ? [inner, outer] : undefined,
    }
})

/** A soft curve between two points, bending away from the straight line. */
function curve(a: { x: number; y: number }, b: { x: number; y: number }): string {
    if (props.design.layout === 'branches') {
        const mx = (a.x + b.x) / 2
        return `M${a.x},${a.y} C${mx},${a.y} ${mx},${b.y} ${b.x},${b.y}`
    }
    const mx = (a.x + b.x) / 2 + (b.y - a.y) * 0.12
    const my = (a.y + b.y) / 2 - (b.x - a.x) * 0.12
    return `M${a.x},${a.y} Q${mx},${my} ${b.x},${b.y}`
}

const style = computed(() => ({ '--design-accent': props.design.accent }))
const done = computed(() => props.stages.length > 0 && props.stages.every((stage) => stage.isDone))
</script>

<template>
    <div class="design-canvas" :class="{ 'design-canvas--compact': compact }" :style="style">
        <img v-if="design.backgroundUrl" :src="design.backgroundUrl" alt="" class="design-canvas__bg" />
        <svg
            :viewBox="`0 0 ${layout.width} ${layout.height}`"
            role="img"
            :aria-label="title"
            class="design-canvas__svg"
        >
            <circle
                v-for="ring in layout.rings ?? []"
                :key="ring"
                :cx="layout.root.x"
                :cy="layout.root.y"
                :r="ring"
                class="design-ring"
            />
            <g class="design-links">
                <template v-for="stage in layout.stages" :key="`l${stage.id}`">
                    <path
                        :d="curve(layout.root, stage)"
                        class="design-link"
                        :class="{ 'design-link--done': stage.isDone }"
                    />
                    <path
                        v-for="step in stage.steps"
                        :key="`l${step.id}`"
                        :d="curve(stage, step)"
                        class="design-link design-link--thin"
                        :class="{ 'design-link--done': step.isDone }"
                    />
                </template>
            </g>
            <g v-for="stage in layout.stages" :key="stage.id">
                <g
                    v-for="step in stage.steps"
                    :key="step.id"
                    class="design-click"
                    role="button"
                    tabindex="0"
                    @click="emit('toggle', step.id)"
                    @keydown.enter.prevent="emit('toggle', step.id)"
                >
                    <DesignNode
                        :x="step.x"
                        :y="step.y"
                        :r="compact ? 10 : 13"
                        :shape="design.nodeShape"
                        :label="step.title"
                        :state="step.isDone ? 'done' : 'open'"
                        :max-label="18"
                    />
                </g>
                <g
                    class="design-click"
                    role="button"
                    tabindex="0"
                    @click="!stage.steps.length && emit('toggle', stage.id)"
                    @keydown.enter.prevent="!stage.steps.length && emit('toggle', stage.id)"
                >
                    <DesignNode
                        :x="stage.x"
                        :y="stage.y"
                        :r="compact ? 15 : 20"
                        :shape="design.nodeShape"
                        :label="stage.title"
                        :state="stage.isDone ? 'done' : 'open'"
                        :max-label="20"
                    />
                </g>
            </g>
            <g class="dnode dnode--root" :class="{ 'dnode--complete': done }">
                <title>{{ title }}</title>
                <circle :cx="layout.root.x" :cy="layout.root.y" :r="compact ? 44 : 62" class="dnode__body" />
                <text
                    :x="layout.root.x"
                    :y="layout.root.y + (twoLines(title)[1] ? -5 : 7)"
                    class="dnode__root-label"
                    text-anchor="middle"
                >
                    <tspan :x="layout.root.x">{{ twoLines(title)[0] }}</tspan>
                    <tspan v-if="twoLines(title)[1]" :x="layout.root.x" dy="24">{{ twoLines(title)[1] }}</tspan>
                </text>
            </g>
        </svg>
    </div>
</template>
