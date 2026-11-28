<script setup lang="ts">
import { computed } from 'vue'
import { isRtl } from '../../i18n'
import { twoLines } from '../../lib/shapes'
import DesignNode from './DesignNode.vue'
import type { MapDesign } from './GoalMap.vue'

export interface ShapeLevel {
    id: number
    title: string
    passed: boolean
    unlocked: boolean
}

/**
 * A skill path drawn in one of three shapes: a tree whose fruit are the
 * levels (first level lowest), a pyramid with the skill at the top and its
 * levels branching beneath, or a staircase climbing to the top.
 */
const props = defineProps<{
    title: string
    levels: ShapeLevel[]
    design: MapDesign
    selectedId?: number | null
    compact?: boolean
}>()
const emit = defineEmits<{ select: [id: number] }>()

const state = (level: ShapeLevel) => (level.passed ? 'done' : level.unlocked ? 'open' : 'locked')

const shape = computed(() => {
    const levels = props.levels
    const n = Math.max(1, levels.length)
    const mirror = (x: number, width: number) => (isRtl.value ? width - x : x)

    // Hierarchy: the first level at the top, each level branching into two beneath it, in order
    // (level 1 → 2 and 3, level 2 → 4 and 5…), like an organisation chart. Goal-map designs
    // dressing a skill tree are drawn this way too.
    if (!['tree', 'pyramid', 'stairs'].includes(props.design.layout)) {
        const depth = Math.max(1, Math.ceil(Math.log2(n + 1)))
        const width = Math.max(700, Math.min(1200, 2 ** (depth - 1) * 150))
        const rowGap = 120
        const height = 90 + (depth - 1) * rowGap + 90
        const nodes = levels.map((level, index) => {
            const row = Math.floor(Math.log2(index + 1))
            const inRow = index + 1 - 2 ** row
            const slots = 2 ** row
            const x = mirror(((inRow + 0.5) / slots) * width, width)
            const y = 70 + row * rowGap
            return { ...level, x, y, parent: { x, y }, row }
        })
        nodes.forEach((node, index) => {
            if (index > 0) {
                const parent = nodes[Math.floor((index - 1) / 2)]
                node.parent = { x: parent.x, y: parent.y }
            }
        })
        return { width, height, top: { x: width / 2, y: 30 }, nodes, kind: 'hierarchy' as const, depth, rowGap }
    }

    if (props.design.layout === 'pyramid') {
        // Rows grow by one: 2, 3, 4… under the skill at the top.
        const rows: ShapeLevel[][] = []
        let size = 2
        for (let i = 0; i < levels.length; size++) {
            rows.push(levels.slice(i, i + size))
            i += size
        }
        const width = 900
        const rowGap = 120
        const height = 150 + rows.length * rowGap + 40
        const top = { x: width / 2, y: 70 }
        const nodes: (ShapeLevel & { x: number; y: number; parent: { x: number; y: number } })[] = []
        let previous: { x: number; y: number }[] = [top]
        rows.forEach((row, rowIndex) => {
            const span = Math.min(width - 120, 220 * row.length)
            const y = 70 + (rowIndex + 1) * rowGap
            const placed = row.map((level, index) => {
                const x = mirror(
                    width / 2 - span / 2 + (row.length === 1 ? span / 2 : (span * index) / (row.length - 1)),
                    width,
                )
                const parent =
                    previous[Math.min(previous.length - 1, Math.floor((index * previous.length) / row.length))]
                return { ...level, x, y, parent }
            })
            nodes.push(...placed)
            previous = placed
        })
        return { width, height, top, nodes, kind: 'pyramid' as const }
    }

    if (props.design.layout === 'stairs') {
        const width = 900
        const stepW = Math.min(150, (width - 160) / n)
        const stepH = 60
        const height = 120 + n * stepH + 60
        const base = height - 40
        const nodes = levels.map((level, index) => {
            const x = mirror(80 + stepW * index + stepW / 2, width)
            const y = base - stepH * (index + 1) - 22
            return { ...level, x, y, parent: { x, y } }
        })
        const steps = levels.map((_, index) => {
            const x0 = mirror(80 + stepW * index, width)
            const x1 = mirror(80 + stepW * (index + 1), width)
            const y = base - stepH * (index + 1)
            return { x: Math.min(x0, x1), y, w: Math.abs(x1 - x0), h: stepH * (index + 1) }
        })
        const top = { x: mirror(80 + stepW * n + 30, width), y: base - stepH * n - 30 }
        return { width, height, top, nodes, steps, kind: 'stairs' as const }
    }

    // Tree: a trunk up the middle, a branch for each level alternating sides, the fruit at its tip.
    const width = 700
    const gap = 78
    const height = 140 + n * gap + 80
    const trunkX = width / 2
    const ground = height - 30
    const nodes = levels.map((level, index) => {
        const side = index % 2 === 0 ? 1 : -1
        const y = ground - 90 - index * gap
        // One reach for every branch keeps the tree tidy and symmetrical.
        const reach = 170
        return { ...level, x: trunkX + side * (isRtl.value ? -reach : reach), y, parent: { x: trunkX, y: y + 26 } }
    })
    const crownTop = ground - 90 - (n - 1) * gap - 60
    return { width, height, top: { x: trunkX, y: crownTop }, nodes, kind: 'tree' as const, ground, trunkX }
})

const style = computed(() => ({ '--design-accent': props.design.accent }))
/** A unique id for this drawing's gradient. */
const uid = Math.random().toString(36).slice(2, 8)
</script>

<template>
    <div class="design-canvas" :class="{ 'design-canvas--compact': compact }" :style="style">
        <img v-if="design.backgroundUrl" :src="design.backgroundUrl" alt="" class="design-canvas__bg" />
        <svg :viewBox="`0 0 ${shape.width} ${shape.height}`" role="img" :aria-label="title" class="design-canvas__svg">
            <template v-if="shape.kind === 'hierarchy'">
                <defs>
                    <linearGradient :id="`hier-${uid}`" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="var(--design-accent)" stop-opacity="0.9" />
                        <stop offset="1" stop-color="var(--design-accent)" stop-opacity="0.35" />
                    </linearGradient>
                </defs>
                <!-- Faint guide lines mark each tier. -->
                <line
                    v-for="row in shape.depth"
                    :key="`tier${row}`"
                    x1="30"
                    :x2="shape.width - 30"
                    :y1="70 + (row - 1) * shape.rowGap"
                    :y2="70 + (row - 1) * shape.rowGap"
                    class="hier-tier"
                />
                <path
                    v-for="node in shape.nodes.slice(1)"
                    :key="`h${node.id}`"
                    :d="`M${node.parent.x},${node.parent.y + 44} C${node.parent.x},${(node.parent.y + 44 + node.y) / 2} ${node.x},${(node.parent.y + 44 + node.y) / 2} ${node.x},${node.y - 24}`"
                    class="hier-link"
                    :class="{ 'hier-link--done': node.passed }"
                    :stroke="`url(#hier-${uid})`"
                />
            </template>
            <template v-else-if="shape.kind === 'tree'">
                <ellipse
                    :cx="shape.trunkX"
                    :cy="(shape.top.y + shape.ground - 60) / 2"
                    :rx="290"
                    :ry="(shape.ground - shape.top.y) / 2"
                    class="tree-crown"
                />
                <ellipse
                    :cx="shape.trunkX"
                    :cy="(shape.top.y + shape.ground - 60) / 2"
                    :rx="230"
                    :ry="(shape.ground - shape.top.y) / 2 - 30"
                    class="tree-crown tree-crown--inner"
                />
                <path
                    :d="`M${shape.trunkX - 26},${shape.ground} Q${shape.trunkX - 10},${(shape.ground + shape.top.y) / 2} ${shape.trunkX - 6},${shape.top.y + 40} L${shape.trunkX + 6},${shape.top.y + 40} Q${shape.trunkX + 10},${(shape.ground + shape.top.y) / 2} ${shape.trunkX + 26},${shape.ground} Z`"
                    class="tree-trunk"
                />
                <path
                    v-for="node in shape.nodes"
                    :key="`b${node.id}`"
                    :d="`M${node.parent.x},${node.parent.y} Q${(node.parent.x + node.x) / 2},${node.parent.y - 6} ${node.x},${node.y - 14}`"
                    class="tree-branch"
                />
                <line
                    :x1="shape.trunkX - 230"
                    :y1="shape.ground"
                    :x2="shape.trunkX + 230"
                    :y2="shape.ground"
                    class="tree-ground"
                />
            </template>
            <template v-else-if="shape.kind === 'pyramid'">
                <path
                    v-for="node in shape.nodes"
                    :key="`l${node.id}`"
                    :d="`M${node.parent.x},${node.parent.y} L${node.x},${node.y}`"
                    class="design-link"
                    :class="{ 'design-link--done': node.passed }"
                />
                <g class="dnode dnode--root">
                    <title>{{ title }}</title>
                    <circle :cx="shape.top.x" :cy="shape.top.y" :r="compact ? 36 : 48" class="dnode__body" />
                    <text
                        :x="shape.top.x"
                        :y="shape.top.y + (twoLines(title)[1] ? -5 : 7)"
                        class="dnode__root-label"
                        text-anchor="middle"
                    >
                        <tspan :x="shape.top.x">{{ twoLines(title)[0] }}</tspan>
                        <tspan v-if="twoLines(title)[1]" :x="shape.top.x" dy="24">{{ twoLines(title)[1] }}</tspan>
                    </text>
                </g>
            </template>
            <template v-else>
                <rect
                    v-for="(step, index) in shape.steps"
                    :key="`s${index}`"
                    :x="step.x"
                    :y="step.y"
                    :width="step.w"
                    :height="step.h"
                    class="stairs-step"
                    :class="{ 'stairs-step--done': levels[index]?.passed }"
                />
                <text :x="shape.top.x" :y="shape.top.y" class="dnode__root-label" text-anchor="middle">🏆</text>
            </template>

            <g
                v-for="node in shape.nodes"
                :key="node.id"
                class="design-click"
                role="button"
                tabindex="0"
                @click="emit('select', node.id)"
                @keydown.enter.prevent="emit('select', node.id)"
            >
                <DesignNode
                    :x="node.x"
                    :y="node.y"
                    :r="compact ? 13 : 18"
                    :shape="design.nodeShape"
                    :label="node.title"
                    :state="state(node)"
                    :selected="node.id === selectedId"
                    :max-label="16"
                />
            </g>
        </svg>
    </div>
</template>
