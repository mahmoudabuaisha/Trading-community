import { ref, watch } from 'vue'

/**
 * Per-device notification preferences. Browser permission is granted per
 * device, so these live in local storage rather than on the account.
 */
const KEY = 'sn-dcc:notify:v1'

function read(): { sound: boolean; browser: boolean } {
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) ?? '{}')
        return { sound: stored.sound !== false, browser: stored.browser === true }
    } catch {
        return { sound: true, browser: false }
    }
}

const initial = read()
export const notifySound = ref(initial.sound)
export const notifyBrowser = ref(initial.browser)

watch([notifySound, notifyBrowser], ([sound, browser]) => {
    try {
        localStorage.setItem(KEY, JSON.stringify({ sound, browser }))
    } catch {
        /* storage unavailable — the choice lasts for this visit only */
    }
})

/** Browser notifications only exist on secure (HTTPS) pages. */
export const browserNotificationsSupported = (): boolean =>
    typeof Notification !== 'undefined' && window.isSecureContext

/** Ask for permission and remember the result. Returns whether notifications are now on. */
export async function enableBrowserNotifications(): Promise<boolean> {
    if (!browserNotificationsSupported()) return false
    const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission()
    notifyBrowser.value = permission === 'granted'
    return notifyBrowser.value
}

/** A short two-note chime, when the member has sound on. */
export function chime(): void {
    if (!notifySound.value) return
    try {
        const context = new AudioContext()
        const gain = context.createGain()
        gain.connect(context.destination)
        gain.gain.setValueAtTime(0.0001, context.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.06, context.currentTime + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.6)
        for (const [index, frequency] of [784, 1047].entries()) {
            const oscillator = context.createOscillator()
            oscillator.type = 'sine'
            oscillator.frequency.value = frequency
            oscillator.connect(gain)
            oscillator.start(context.currentTime + index * 0.16)
            oscillator.stop(context.currentTime + 0.6)
        }
        window.setTimeout(() => void context.close(), 900)
    } catch {
        /* sound is a nicety; ignore browsers that block it */
    }
}
