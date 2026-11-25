import { computed, onBeforeUnmount, onMounted, ref, watch, type ComputedRef } from 'vue'
import { t } from '../i18n'
import { browserNotificationsSupported, chime, notifyBrowser } from '../lib/notifyPrefs'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import type { Task } from '../types/domain'

/**
 * Task reminders that have come due. A reminder is due once its time has
 * passed, until the task is finished or the member dismisses it. When one
 * becomes due while the app is open, the member gets a toast, an optional
 * chime and, where allowed, a browser notification.
 */
export function useReminders(): { due: ComputedRef<Task[]> } {
    const workspace = useWorkspaceStore()
    const ui = useUiStore()
    const now = ref(Date.now())
    let timer = 0
    /** Reminders already announced in this visit, so each one alerts once. */
    const announced = new Set<number>()
    let primed = false

    const due = computed(() =>
        workspace.tasks
            .filter(
                (task) =>
                    task.remindAt !== null &&
                    task.reminderDismissedAt === null &&
                    task.status !== 'completed' &&
                    task.status !== 'cancelled' &&
                    new Date(task.remindAt).getTime() <= now.value,
            )
            .sort((a, b) => (a.remindAt! < b.remindAt! ? 1 : -1)),
    )

    watch(
        [due, () => workspace.loaded],
        ([tasks, loaded]) => {
            if (!loaded) return
            const fresh = tasks.filter((task) => !announced.has(task.id))
            for (const task of fresh) announced.add(task.id)
            // Reminders that were already due when the page opened are listed in the bell, not announced again.
            if (!primed) {
                primed = true
                return
            }
            for (const task of fresh) {
                ui.success(t('notify.reminderToast', { title: task.title }))
                if (notifyBrowser.value && browserNotificationsSupported() && Notification.permission === 'granted') {
                    new Notification(t('notify.reminderTitle'), {
                        body: task.title,
                        icon: '/images/icon-192.png',
                        tag: `task-${task.id}`,
                    })
                }
            }
            if (fresh.length) chime()
        },
        { immediate: true },
    )

    onMounted(() => (timer = window.setInterval(() => (now.value = Date.now()), 15_000)))
    onBeforeUnmount(() => window.clearInterval(timer))

    return { due }
}
