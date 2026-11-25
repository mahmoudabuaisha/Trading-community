import { onBeforeUnmount, onMounted, watch } from 'vue'
import { messages, t } from '../i18n'
import { today } from '../lib/dates'
import { browserNotificationsSupported, chime, notifyBrowser } from '../lib/notifyPrefs'
import { useAuthStore } from '../stores/auth'
import { useNoticesStore, type NoticeKind } from '../stores/notices'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'

/**
 * The gentle nudges a member can switch on in Settings: a morning message, a
 * welcome back after days away, and bedtime / wake-up reminders. They fire
 * while the app is open; each one fires once per day on this device.
 */
export function useNudges(): void {
    const auth = useAuthStore()
    const workspace = useWorkspaceStore()
    const notices = useNoticesStore()
    const ui = useUiStore()
    let timer = 0

    /** True the first time it is asked about this nudge today on this device. */
    function firstToday(kind: NoticeKind): boolean {
        const key = `sn-dcc:nudge:${auth.user?.id}:${kind}:v1`
        try {
            if (localStorage.getItem(key) === today()) return false
            localStorage.setItem(key, today())
        } catch {
            /* without storage the nudge may repeat within the day; better than never showing */
        }
        return true
    }

    function raise(kind: NoticeKind, title: string, text: string, alert = false): void {
        notices.add(kind, title, text)
        ui.success(`${title} — ${text}`)
        if (alert) {
            chime()
            if (notifyBrowser.value && browserNotificationsSupported() && Notification.permission === 'granted') {
                new Notification(title, { body: text, icon: '/images/icon-192.png', tag: `nudge-${kind}` })
            }
        }
    }

    function check(): void {
        const prefs = auth.user?.preferences
        if (!prefs || !workspace.loaded) return
        const now = new Date()
        const clock = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`

        // A message planned by the administrators for today takes the place of the random morning line.
        const planned = workspace.broadcasts.find((item) => item.kind === 'scheduled')
        if (!planned && prefs.notifyMorning && now.getHours() >= 5 && now.getHours() < 12 && firstToday('morning')) {
            const quotes = workspace.motivations.length
                ? workspace.motivations.map((item) => item.title)
                : messages.value.quotes
            raise('morning', t('nudge.morningTitle'), quotes[now.getDate() % quotes.length])
        }
        // Within the half hour after the chosen time, so a tab opened a little late still gets it.
        const due = (time: string | null) => {
            if (!time) return false
            const [hours, minutes] = time.split(':').map(Number)
            const elapsed = now.getHours() * 60 + now.getMinutes() - (hours * 60 + minutes)
            return elapsed >= 0 && elapsed < 30
        }
        if (prefs.notifySleep && due(prefs.sleepTime) && firstToday('sleep'))
            raise('sleep', t('nudge.sleepTitle'), t('nudge.sleepText', { time: clock }), true)
        if (prefs.notifySleep && due(prefs.wakeTime) && firstToday('wake'))
            raise('wake', t('nudge.wakeTitle'), t('nudge.wakeText', { time: clock }), true)
    }

    // Welcome back: reported by the server once, on the first load after days away.
    watch(
        () => workspace.absenceDays,
        (days) => {
            if (days > 0 && auth.user?.preferences.notifyInactivity)
                raise('absence', t('nudge.absenceTitle'), t('nudge.absenceText', { n: days }))
        },
        { immediate: true },
    )
    watch(() => workspace.loaded, check)

    // Messages from the administrators: each is announced once on this device, then stays in the bell for the day.
    watch(
        () => workspace.broadcasts,
        (list) => {
            for (const item of list) {
                const key = `sn-dcc:broadcast:${auth.user?.id}:${item.id}:v1`
                let fresh = true
                try {
                    fresh = localStorage.getItem(key) === null
                    localStorage.setItem(key, today())
                } catch {
                    /* without storage it may be announced again on reload */
                }
                notices.addBroadcast(item.id, t('nudge.broadcastTitle'), item.body, fresh)
                if (fresh) {
                    ui.success(`${t('nudge.broadcastTitle')} — ${item.body}`)
                    chime()
                }
            }
        },
        { immediate: true, deep: true },
    )

    onMounted(() => {
        check()
        timer = window.setInterval(check, 30_000)
    })
    onBeforeUnmount(() => window.clearInterval(timer))
}
