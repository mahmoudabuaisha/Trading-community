<script setup lang="ts">
import { BellOff, Pause, Play, ScanLine } from 'lucide-vue-next'
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { t } from '../i18n'
import { useFocusStore } from '../stores/focus'

/** A small timer that follows the member around the app while a focus session runs. */
const focus = useFocusStore()
const route = useRoute()
onMounted(() => focus.resume())
</script>

<template>
    <div v-if="focus.ringing" class="focus-dock focus-dock--ringing" role="alert">
        <ScanLine :size="18" aria-hidden="true" />
        <b>{{ t('focus.doneTitle') }}</b>
        <button type="button" class="btn btn--sm" @click="focus.silence()">
            <BellOff :size="15" /> {{ t('focus.stop') }}
        </button>
    </div>
    <div v-else-if="focus.active && route.name !== 'overview'" class="focus-dock">
        <RouterLink
            :to="{ name: 'overview', query: { tab: 'focus' } }"
            class="focus-dock__time"
            :aria-label="t('focus.open')"
        >
            <ScanLine :size="16" aria-hidden="true" />
            <span role="timer">{{ focus.clock }}</span>
        </RouterLink>
        <button
            v-if="focus.running"
            type="button"
            class="icon-btn icon-btn--sm"
            :aria-label="t('center.pause')"
            @click="focus.pause()"
        >
            <Pause :size="15" />
        </button>
        <button
            v-else
            type="button"
            class="icon-btn icon-btn--sm"
            :aria-label="t('center.resume')"
            @click="focus.start()"
        >
            <Play :size="15" />
        </button>
    </div>
</template>
