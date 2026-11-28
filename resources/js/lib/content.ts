import { ref, watch } from 'vue'
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

export interface OfficialLink {
    kind: 'instagram' | 'telegram' | 'other'
    label: string
    url: string
}

/** The platform's official Instagram and Telegram accounts. */
export const officialLinks = ref<OfficialLink[]>([])

export async function loadOfficialLinks(): Promise<void> {
    try {
        officialLinks.value = (await request<{ links: OfficialLink[] }>('GET', '/official-links')).links
    } catch {
        /* the links simply do not show */
    }
}

export function installSiteCopy(): void {
    void loadSiteCopy()
    void loadOfficialLinks()
    watch(locale, () => void loadSiteCopy())
}
