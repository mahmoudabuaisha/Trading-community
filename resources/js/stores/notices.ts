import { defineStore } from 'pinia'
import { ref } from 'vue'

export type NoticeKind = 'morning' | 'absence' | 'sleep' | 'wake'

export interface Notice {
    id: number
    kind: NoticeKind
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

    function dismiss(id: number): void {
        items.value = items.value.filter((notice) => notice.id !== id)
    }

    return { items, add, dismiss }
})
