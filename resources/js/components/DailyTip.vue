<script setup lang="ts">
import { Gift, Sparkles } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { repository } from '../data/repository'
import { t } from '../i18n'
import { today } from '../lib/dates'
import { useUiStore } from '../stores/ui'

/**
 * "Your tip of the day": one draw per day, free for everyone. The lines run
 * past like a slot reel, slowing down until they stop on the drawn one.
 */
const ui = useUiStore()
const tip = ref<string | null>(null)
const available = ref(false)
const loaded = ref(false)
const spinning = ref(false)
const shown = ref('')
let timer = 0

async function load(): Promise<void> {
    try {
        const state = await repository.dailyTip(today())
        tip.value = state.tip
        available.value = state.available
    } catch {
        /* the card simply stays hidden if the server cannot answer */
    } finally {
        loaded.value = true
    }
}
onMounted(load)
onBeforeUnmount(() => window.clearTimeout(timer))

/** Show each line for a little longer than the last, then land on the drawn one. */
function roll(reel: string[], index = 0, delay = 60): Promise<void> {
    return new Promise((resolve) => {
        const step = (i: number, wait: number) => {
            shown.value = reel[i]
            if (i === reel.length - 1) return resolve()
            timer = window.setTimeout(() => step(i + 1, wait * 1.22), wait)
        }
        step(index, delay)
    })
}

async function draw(): Promise<void> {
    if (spinning.value || !available.value) return
    spinning.value = true
    try {
        const result = await repository.drawDailyTip(today())
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
        if (!reduced && result.reel.length > 1) await roll(result.reel)
        tip.value = result.tip
        available.value = false
    } catch (error) {
        ui.failure(error)
    } finally {
        spinning.value = false
    }
}
</script>

<template>
    <section
        v-if="loaded && (tip || available)"
        class="dash-card daily-tip"
        :class="{ 'daily-tip--done': tip && !spinning }"
    >
        <header class="daily-tip__head">
            <span class="daily-tip__icon"><Gift :size="22" aria-hidden="true" /></span>
            <div>
                <h3>{{ t('tip.title') }}</h3>
                <p>{{ t(tip ? 'tip.subDone' : 'tip.sub') }}</p>
            </div>
        </header>

        <div class="daily-tip__window" aria-live="polite">
            <p v-if="spinning" :key="shown" class="daily-tip__line daily-tip__line--rolling">{{ shown }}</p>
            <p v-else-if="tip" class="daily-tip__line daily-tip__line--final">
                <Sparkles :size="18" aria-hidden="true" /> {{ tip }}
            </p>
            <button v-else type="button" class="btn btn--gold daily-tip__button" @click="draw">
                <Gift :size="18" aria-hidden="true" /> {{ t('tip.button') }}
            </button>
        </div>
        <small v-if="tip && !spinning" class="hint">{{ t('tip.comeBack') }}</small>
    </section>
</template>
