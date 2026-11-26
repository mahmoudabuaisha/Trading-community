<script setup lang="ts">
import { Camera, Clapperboard, Mountain } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import { isRtl, t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { CARD_HEIGHT, CARD_WIDTH, download, drawCard, videoSupport, type CardText } from '../world/shareCard'
import type { SummitWorld, WorldData } from '../world/summitScene'

/**
 * "My summit": the member's 3D world. Its height, lanterns, peaks, flags,
 * trees, crystals and aurora come from what they actually did.
 */
const auth = useAuthStore()
const ui = useUiStore()
const host = ref<HTMLElement | null>(null)
const data = ref<Omit<WorldData, 'accent'> | null>(null)
const fallback = ref(false)
const recording = ref(0)
let world: SummitWorld | null = null

const video = videoSupport()
const accent = computed(() => auth.user?.theme?.accent ?? '#d6ad62')
const legend = computed(() =>
    data.value
        ? [
              { key: 'height', value: data.value.tasksCompleted },
              { key: 'peaks', value: data.value.areas.length },
              { key: 'flags', value: data.value.goalsCompleted.length },
              { key: 'trees', value: data.value.habitChecks },
              { key: 'aurora', value: data.value.streak },
              { key: 'crystals', value: data.value.pointsEarned },
          ]
        : [],
)

onMounted(async () => {
    try {
        data.value = await repository.world()
    } catch (error) {
        ui.failure(error)
        return
    }
    // The 3D code loads only on this page.
    const { createSummitWorld, webglAvailable } = await import('../world/summitScene')
    if (!host.value || !webglAvailable()) {
        fallback.value = true
        return
    }
    const lowPower =
        (navigator.hardwareConcurrency ?? 8) <= 4 ||
        ((navigator as { deviceMemory?: number }).deviceMemory ?? 8) <= 4 ||
        matchMedia('(pointer: coarse)').matches
    world = createSummitWorld(
        host.value,
        { ...data.value, accent: accent.value },
        { reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches, lowPower },
    )
})
onBeforeUnmount(() => world?.dispose())

function cardText(): CardText {
    const d = data.value!
    return {
        name: d.name,
        title: d.title,
        lines: [
            t('world.card.line1', { streak: d.streak, tasks: d.tasksCompleted }),
            t('world.card.line2', { points: d.pointsEarned, goals: d.goalsCompleted.length }),
        ],
        brand: 'SN TASKS',
        dir: isRtl.value ? 'rtl' : 'ltr',
    }
}

function cardCanvas(): CanvasRenderingContext2D {
    const canvas = document.createElement('canvas')
    canvas.width = CARD_WIDTH
    canvas.height = CARD_HEIGHT
    return canvas.getContext('2d')!
}

async function snapshot(): Promise<void> {
    if (!world) return
    await document.fonts.ready
    const ctx = cardCanvas()
    world.renderNow()
    drawCard(ctx, world.canvas, cardText(), accent.value)
    ctx.canvas.toBlob((blob) => blob && download(blob, 'my-summit.png'), 'image/png')
    ui.success(t('world.saved'))
}

/** Ten seconds, one full turn around the summit, drawn into the card and recorded. */
async function record(): Promise<void> {
    if (!world || !video || recording.value) return
    await document.fonts.ready
    const ctx = cardCanvas()
    const stream = ctx.canvas.captureStream(30)
    const recorder = new MediaRecorder(stream, { mimeType: video.mimeType, videoBitsPerSecond: 6_000_000 })
    const chunks: Blob[] = []
    recorder.ondataavailable = (event) => event.data.size && chunks.push(event.data)
    const text = cardText()
    const seconds = 10
    const started = performance.now()
    world.setAutoRotate(false)
    let turned = 0
    world.onFrame(() => {
        const progress = Math.min(1, (performance.now() - started) / (seconds * 1000))
        const target = progress * Math.PI * 2
        world!.turn(target - turned)
        turned = target
        recording.value = Math.max(1, Math.ceil(seconds * (1 - progress)))
        drawCard(ctx, world!.canvas, text, accent.value)
    })
    recorder.start(250)
    await new Promise((resolve) => setTimeout(resolve, seconds * 1000))
    recorder.stop()
    await new Promise((resolve) => (recorder.onstop = resolve))
    world.onFrame(null)
    world.setAutoRotate(true)
    recording.value = 0
    if (!chunks.length) {
        ui.failure(new ApiError(t('world.videoFailed'), 422))
        return
    }
    download(new Blob(chunks, { type: video.mimeType }), `my-summit.${video.extension}`)
    ui.success(t('world.saved'))
}
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">MY SUMMIT</span>
                <h2>{{ t('world.head') }}</h2>
                <p>{{ t('world.sub') }}</p>
            </div>
            <div class="head-actions">
                <button
                    type="button"
                    class="btn btn--outline"
                    :disabled="!data || fallback || recording > 0"
                    @click="snapshot"
                >
                    <Camera :size="16" aria-hidden="true" /> {{ t('world.photo') }}
                </button>
                <button
                    v-if="video"
                    type="button"
                    class="btn"
                    :disabled="!data || fallback || recording > 0"
                    @click="record"
                >
                    <Clapperboard :size="16" aria-hidden="true" />
                    {{ recording ? t('world.recording', { n: recording }) : t('world.video') }}
                </button>
            </div>
        </div>

        <div class="world-stage" :class="{ 'world-stage--recording': recording }">
            <div ref="host" class="world-stage__canvas" :aria-label="t('world.alt')" role="img" />
            <div v-if="fallback" class="world-stage__fallback">
                <Mountain :size="48" aria-hidden="true" />
                <p>{{ t('world.noWebgl') }}</p>
            </div>
            <span v-if="data && !fallback" class="world-stage__hint">{{ t('world.drag') }}</span>
        </div>

        <ul v-if="data" class="world-legend">
            <li v-for="item in legend" :key="item.key">
                <b>{{ item.value }}</b>
                <span>{{ t(`world.legend.${item.key}`) }}</span>
            </li>
        </ul>
        <p v-if="!video" class="hint">{{ t('world.noVideo') }}</p>
    </section>
</template>
