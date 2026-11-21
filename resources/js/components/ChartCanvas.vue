<script setup lang="ts">
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
    type ChartConfiguration,
} from 'chart.js'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Legend, LinearScale, Tooltip)

/** Thin Chart.js wrapper: re-renders whenever the configuration object changes. */
const props = defineProps<{ config: ChartConfiguration; label: string }>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function render(): void {
    chart?.destroy()
    if (!canvas.value) return
    const styles = getComputedStyle(document.documentElement)
    Chart.defaults.color = styles.getPropertyValue('--text-muted').trim()
    Chart.defaults.borderColor = styles.getPropertyValue('--line-soft').trim()
    Chart.defaults.font.family = styles.getPropertyValue('--font-sans').trim()
    chart = new Chart(canvas.value, props.config)
}

onMounted(render)
watch(() => props.config, render)
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
    <div class="chart"><canvas ref="canvas" role="img" :aria-label="label" /></div>
</template>
