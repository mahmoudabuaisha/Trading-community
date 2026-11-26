<script setup lang="ts">
import { Music, Play, Upload } from 'lucide-vue-next'
import { ref } from 'vue'
import { ApiError } from '../data/http'
import { t } from '../i18n'
import { customToneName, focusTone, playTone, saveCustomTone, TONES } from '../lib/tones'
import { useUiStore } from '../stores/ui'

/** Which sound rings at the end of a focus session (saved on this device). */
const ui = useUiStore()
const fileInput = ref<HTMLInputElement | null>(null)

async function pick(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    const result = await saveCustomTone(file)
    if (result === 'ok') {
        ui.success(t('focus.toneSaved'))
        playTone('custom')
    } else ui.failure(new ApiError(t(result === 'type' ? 'focus.toneType' : 'focus.toneSize'), 422))
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <h3>{{ t('focus.toneHead') }}</h3>
                <p>{{ t('focus.toneSub') }}</p>
            </div>
            <Music :size="18" class="gold" aria-hidden="true" />
        </div>
        <div class="tone-list" role="radiogroup" :aria-label="t('focus.toneHead')">
            <label v-for="tone in TONES" v-show="tone !== 'custom' || customToneName" :key="tone" class="setting-row">
                <span>
                    <b>{{ tone === 'custom' ? customToneName : t(`focus.tones.${tone}`) }}</b>
                    <small v-if="tone === 'custom'">{{ t('focus.tones.custom') }}</small>
                </span>
                <button
                    type="button"
                    class="icon-btn icon-btn--sm"
                    :aria-label="t('focus.preview')"
                    @click.prevent="playTone(tone)"
                >
                    <Play :size="14" />
                </button>
                <input v-model="focusTone" type="radio" name="focus-tone" :value="tone" />
            </label>
        </div>
        <div class="actions">
            <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                <Upload :size="15" /> {{ t('focus.upload') }}
            </button>
        </div>
        <input ref="fileInput" class="sr-only" type="file" accept="audio/*" tabindex="-1" @change="pick" />
        <p class="hint">{{ t('focus.toneNote') }}</p>
    </div>
</template>
