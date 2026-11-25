import { defineStore } from 'pinia'
import { ref } from 'vue'

export type NoticeKind = 'morning' | 'absence' | 'sleep' | 'wake' | 'broadcast'

export interface Notice {
    id: number
    kind: NoticeKind
    /** For a message from the administrators: its id, so it is listed once. */
    broadcastId?: number
    title: string
    text: string
}

/** Notifications raised during this visit (morning message, welcome back, sleep and wake reminders). */
export const useNoticesStore = defineStore('notices', () => {
    const items = ref<Notice[]>([])
    let nextId = 1

    function add(kind: NoticeKind, title: string, text: string): void {
        // One of each kind at a time: a newer one replaces the older.
        items.value = [{ id: nextId++, kind, title, text }, ...items.value.filter((notice) => notice.kind !== kind)]
    }

    const dismissed = new Set<number>()

    /** List a message from the administrators (once per id); a dismissed one stays dismissed for this visit. */
    function addBroadcast(broadcastId: number, title: string, text: string, toFront: boolean): void {
        if (dismissed.has(broadcastId) || items.value.some((notice) => notice.broadcastId === broadcastId)) return
        const notice: Notice = { id: nextId++, kind: 'broadcast', broadcastId, title, text }
        items.value = toFront ? [notice, ...items.value] : [...items.value, notice]
    }

    function dismiss(id: number): void {
        const notice = items.value.find((item) => item.id === id)
        if (notice?.broadcastId) dismissed.add(notice.broadcastId)
        items.value = items.value.filter((item) => item.id !== id)
    }

    return { items, add, addBroadcast, dismiss }
})
