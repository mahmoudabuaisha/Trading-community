import { watch } from 'vue'
import { request } from '../data/http'
import { locale, overrides } from '../i18n'

/**
 * Load the phrases the administrators rewrote, in the active language, and
 * load them again whenever the language changes. Failing quietly keeps the
 * built-in phrases.
 */
export async function loadSiteCopy(): Promise<void> {
    try {
        const { copy } = await request<{ copy: Record<string, string> | unknown[] }>('GET', '/site-copy')
        overrides.value = Array.isArray(copy) ? {} : copy
    } catch {
        /* the built-in phrases stay */
    }
}

export function installSiteCopy(): void {
    void loadSiteCopy()
    watch(locale, () => void loadSiteCopy())
}
