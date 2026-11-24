import { computed, type ComputedRef } from 'vue'
import { locale, t } from '../i18n'
import { useWorkspaceStore } from '../stores/workspace'

/**
 * Interface copy an administrator can override. Falls back to the built-in
 * translation when no override is set for the active language.
 */
export function useSiteCopy(): { tagline: ComputedRef<string>; welcomeSub: ComputedRef<string> } {
    const workspace = useWorkspaceStore()
    const pick = (ar: string | null, en: string | null, fallbackKey: string) =>
        (locale.value === 'ar' ? ar : en)?.trim() || t(fallbackKey)
    return {
        tagline: computed(() => pick(workspace.content.taglineAr, workspace.content.taglineEn, 'dash.tagline')),
        welcomeSub: computed(() =>
            pick(workspace.content.welcomeSubAr, workspace.content.welcomeSubEn, 'dash.welcomeSub'),
        ),
    }
}
