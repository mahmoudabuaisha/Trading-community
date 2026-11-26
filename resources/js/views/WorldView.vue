<script setup lang="ts">
import { Download, Mountain } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { PLACES, POINTS_PER_BEAD } from '../world/places'
import type { SummitWorld, WorldData } from '../world/summitScene'

/**
 * "My summit": the member's record as a 3D diorama. Nothing is written over
 * the scene, so a screenshot or screen recording is ready for a story or a
 * reel; "Save picture" downloads a clean, story-sized image of it.
 */
const auth = useAuthStore()
const ui = useUiStore()
const host = ref<HTMLElement | null>(null)
const data = ref<Omit<WorldData, 'accent'> | null>(null)
const fallback = ref(false)
const saving = ref(false)
let world: SummitWorld | null = null

const accent = computed(() => auth.user?.theme?.accent ?? '#d6ad62')
/** What each part of the scene stands for, with the member's numbers. */
const legend = computed(() => {
    const d = data.value
    if (!d) return []
    return [
        { key: 'lanterns', value: d.tasksCompleted, places: PLACES.lanterns },
        { key: 'flags', value: d.goalsCompleted.length, places: PLACES.flags },
        { key: 'trees', value: d.habitChecks, places: PLACES.plots },
        { key: 'obelisks', value: d.areas.length, places: PLACES.areas },
        { key: 'beads', value: d.pointsEarned, places: PLACES.beads * POINTS_PER_BEAD },
        { key: 'aurora', value: d.streak, places: 0 },
    ]
})

/** Reload when the member comes back to the tab, so new tasks and goals show without a refresh. */
async function build(): Promise<void> {
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
    world?.dispose()
    world = createSummitWorld(
        host.value,
        { ...data.value, accent: accent.value },
        { reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches, lowPower },
    )
}

let hiddenAt = 0
function onVisibility(): void {
    if (document.visibilityState === 'hidden') hiddenAt = Date.now()
    else if (hiddenAt && Date.now() - hiddenAt > 30_000) void build()
}

onMounted(() => {
    void build()
    document.addEventListener('visibilitychange', onVisibility)
})
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibility)
    world?.dispose()
})

async function savePicture(): Promise<void> {
    if (!world || saving.value) return
    saving.value = true
    try {
        const blob = await world.capture(1080, 1920)
        if (!blob) throw new ApiError(t('world.saveFailed'), 422)
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = 'my-summit.png'
        link.click()
        setTimeout(() => URL.revokeObjectURL(url), 2000)
        ui.success(t('world.saved'))
    } catch (error) {
        ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <h2>{{ t('world.head') }}</h2>
                <p>{{ t('world.sub') }}</p>
            </div>
        </div>

        <div class="world-stage">
            <div ref="host" class="world-stage__canvas" :aria-label="t('world.alt')" role="img" />
            <div v-if="fallback" class="world-stage__fallback">
                <Mountain :size="48" aria-hidden="true" />
                <p>{{ t('world.noWebgl') }}</p>
            </div>
            <button
                v-if="data && !fallback"
                type="button"
                class="world-stage__save"
                :disabled="saving"
                :aria-label="t('world.save')"
                :title="t('world.save')"
                @click="savePicture"
            >
                <Download :size="15" aria-hidden="true" />
                <span>{{ t('world.save') }}</span>
            </button>
        </div>

        <ul v-if="data" class="world-legend">
            <li v-for="item in legend" :key="item.key" :class="`world-legend__item--${item.key}`">
                <i aria-hidden="true" />
                <b>{{ item.value }}</b>
                <span>{{ t(`world.legend.${item.key}`) }}</span>
                <small v-if="item.places && item.value > item.places">{{ t('world.counterNote') }}</small>
            </li>
        </ul>
    </section>
</template>
