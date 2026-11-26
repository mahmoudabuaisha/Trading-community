import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { t } from '../i18n'
import { notifyBrowser } from '../lib/notifyPrefs'
import { playTone, stopTone } from '../lib/tones'
import { useUiStore } from './ui'
import { useWorkspaceStore } from './workspace'

/**
 * The focus timer, kept outside any page so it keeps running while the member
 * moves around the app. The end instant is saved on the device, so closing the
 * tab or the browser does not lose a session: on return it is finished and
 * logged.
 */
const KEY = 'sn-dcc:focus-timer:v1'
export const PRESETS = [10, 25, 30, 60]
export const MAX_MINUTES = 600

interface Saved {
    duration: number
    /** Running: when it ends (ms since epoch). */
    endsAt: number | null
    /** Paused: seconds left. */
    remaining: number
}

function read(): Saved {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) ?? 'null') as Saved | null
        if (saved && saved.duration > 0) return saved
    } catch {
        /* start fresh */
    }
    return { duration: 25, endsAt: null, remaining: 25 * 60 }
}

export const useFocusStore = defineStore('focus', () => {
    const initial = read()
    const duration = ref(initial.duration)
    const endsAt = ref<number | null>(initial.endsAt)
    const remaining = ref(initial.remaining)
    /** The session ended and the tone is ringing until the member stops it. */
    const ringing = ref(false)
    let ticker = 0

    const running = computed(() => endsAt.value !== null)
    const active = computed(() => running.value || remaining.value !== duration.value * 60)
    const pct = computed(() =>
        Math.max(0, Math.min(100, ((duration.value * 60 - remaining.value) / (duration.value * 60)) * 100)),
    )
    const clock = computed(() => {
        const hours = Math.floor(remaining.value / 3600)
        const mm = String(Math.floor((remaining.value % 3600) / 60)).padStart(2, '0')
        const ss = String(remaining.value % 60).padStart(2, '0')
        return hours ? `${hours}:${mm}:${ss}` : `${mm}:${ss}`
    })

    watch([duration, endsAt, remaining], () => {
        try {
            localStorage.setItem(
                KEY,
                JSON.stringify({ duration: duration.value, endsAt: endsAt.value, remaining: remaining.value }),
            )
        } catch {
            /* the timer still runs for this visit */
        }
    })

    function choose(minutes: number): void {
        if (running.value) return
        const value = Math.max(1, Math.min(MAX_MINUTES, Math.round(minutes)))
        duration.value = value
        remaining.value = value * 60
    }

    function start(): void {
        stopTone()
        ringing.value = false
        endsAt.value = Date.now() + remaining.value * 1000
        resume()
    }

    function pause(): void {
        tick()
        endsAt.value = null
        window.clearInterval(ticker)
    }

    function reset(): void {
        endsAt.value = null
        window.clearInterval(ticker)
        remaining.value = duration.value * 60
    }

    function silence(): void {
        stopTone()
        ringing.value = false
    }

    function tick(): void {
        if (endsAt.value === null) return
        remaining.value = Math.max(0, Math.ceil((endsAt.value - Date.now()) / 1000))
        if (remaining.value === 0) void finish()
    }

    /** Keep ticking (called on start and when the app opens with a session running). */
    function resume(): void {
        window.clearInterval(ticker)
        if (endsAt.value === null) return
        ticker = window.setInterval(tick, 500)
        tick()
    }

    async function finish(): Promise<void> {
        const minutes = duration.value
        reset()
        ringing.value = true
        playTone()
        if (notifyBrowser.value && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            try {
                new Notification(t('focus.doneTitle'), {
                    body: t('center.focusSaved', { n: minutes }),
                    tag: 'sn-focus',
                })
            } catch {
                /* some browsers only notify from a service worker */
            }
        }
        try {
            await useWorkspaceStore().logFocusSession(minutes)
            useUiStore().success(t('center.focusSaved', { n: minutes }))
        } catch (error) {
            useUiStore().failure(error)
        }
    }

    return { duration, remaining, running, active, ringing, pct, clock, choose, start, pause, reset, silence, resume }
})
