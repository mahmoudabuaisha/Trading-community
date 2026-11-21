import { defineStore } from 'pinia'
import { ref } from 'vue'
import { ApiError } from '../data/http'
import { t } from '../i18n'

export interface Toast {
    id: number
    kind: 'success' | 'error'
    text: string
    /** Optional undo action shown on the toast. */
    undo?: () => void
}

/** Transient feedback (toasts) shared by every screen. */
export const useUiStore = defineStore('ui', () => {
    const toasts = ref<Toast[]>([])
    let nextId = 1

    function push(kind: Toast['kind'], text: string, undo?: () => void, ttl = 5000): number {
        const id = nextId++
        toasts.value.push({ id, kind, text, undo })
        window.setTimeout(() => dismiss(id), ttl)
        return id
    }

    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((toast) => toast.id !== id)
    }

    const success = (text: string, undo?: () => void) => push('success', text, undo, undo ? 6000 : 4000)

    /** Show the most specific message available for a failed request. */
    function failure(error: unknown): void {
        push('error', describeError(error), undefined, 7000)
    }

    return { toasts, success, failure, dismiss }
})

export function describeError(error: unknown): string {
    if (error instanceof ApiError) {
        const first = Object.values(error.errors)[0]?.[0]
        return first || error.message || t('common.unexpected')
    }
    if (error instanceof TypeError) return t('common.offline')
    return t('common.unexpected')
}
