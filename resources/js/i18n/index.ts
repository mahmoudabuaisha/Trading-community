import { computed, ref, watchEffect } from 'vue'
import type { Locale } from '../types/domain'
import { ar, type Messages } from './ar'
import { en } from './en'

const STORAGE_KEY = 'sn-dcc:locale:v1'
const dictionaries: Record<Locale, Messages> = { ar, en }

function initialLocale(): Locale {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'en' ? 'en' : 'ar'
    } catch {
        return 'ar'
    }
}

export const locale = ref<Locale>(initialLocale())
export const isRtl = computed(() => locale.value === 'ar')
/** The active dictionary; use for arrays and nested lookups by machine key. */
export const messages = computed(() => dictionaries[locale.value])

watchEffect(() => {
    document.documentElement.lang = locale.value
    document.documentElement.dir = isRtl.value ? 'rtl' : 'ltr'
    try {
        localStorage.setItem(STORAGE_KEY, locale.value)
    } catch {
        /* storage unavailable (private window) — the choice lasts for this visit only */
    }
})

export function setLocale(next: Locale): void {
    locale.value = next
}

/** Translate a dotted key, replacing `{name}` placeholders. Returns the key itself when missing. */
export function t(key: string, params: Record<string, string | number> = {}): string {
    let node: unknown = messages.value
    for (const part of key.split('.')) node = (node as Record<string, unknown> | undefined)?.[part]
    if (typeof node !== 'string') return key
    return node.replace(/\{(\w+)\}/g, (_, name) => String(params[name] ?? `{${name}}`))
}

/** Locale-aware date/time formatting. Arabic keeps Latin digits for consistency with inputs. */
export function formatDate(value: Date | string, options: Intl.DateTimeFormatOptions): string {
    const date = typeof value === 'string' ? new Date(value) : value
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-u-nu-latn' : 'en-GB', options).format(date)
}
