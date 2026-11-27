<script setup lang="ts">
import { Languages, RotateCcw, Save, Search, Type } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { request } from '../../data/http'
import { builtIn, messages, t } from '../../i18n'
import { loadSiteCopy } from '../../lib/content'
import { useUiStore } from '../../stores/ui'

/**
 * Every phrase of the login page, the home page and the command center can
 * be rewritten here. The administrator writes it once, in any language; it
 * is translated for members who use the platform in another language.
 */
const ui = useUiStore()
type Section = 'featured' | 'auth' | 'dash' | 'center'
const SECTIONS: Section[] = ['featured', 'auth', 'dash', 'center']
/** The phrases people see first, gathered at the top. */
const FEATURED = ['dash.tagline', 'dash.welcomeSub', 'auth.tagline', 'auth.signInTitle', 'center.title', 'center.sub']

const section = ref<Section>('featured')
const query = ref('')
const saved = ref<Record<string, string>>({})
const drafts = reactive<Record<string, string>>({})
const autoTranslate = ref(true)
const busy = ref<string | null>(null)
const loaded = ref(false)

/** Every phrase (string) under a part of the dictionary, as dotted keys. */
function keysOf(root: 'auth' | 'dash' | 'center'): string[] {
    const out: string[] = []
    const walk = (node: unknown, path: string) => {
        if (typeof node === 'string') out.push(path)
        else if (node && typeof node === 'object' && !Array.isArray(node))
            for (const [key, value] of Object.entries(node)) walk(value, `${path}.${key}`)
    }
    walk((messages.value as unknown as Record<string, unknown>)[root], root)
    return out
}

const rows = computed(() => {
    const keys = section.value === 'featured' ? FEATURED.filter((key) => builtIn(key) !== null) : keysOf(section.value)
    const q = query.value.trim().toLowerCase()
    return keys.filter(
        (key) =>
            !q || (builtIn(key) ?? '').toLowerCase().includes(q) || (saved.value[key] ?? '').toLowerCase().includes(q),
    )
})
const changedCount = computed(() => Object.keys(saved.value).length)

onMounted(async () => {
    try {
        const data = await request<{ copy: Record<string, string> | unknown[]; autoTranslate: boolean }>(
            'GET',
            '/admin/site-copy',
        )
        apply(data)
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})

function apply(data: { copy: Record<string, string> | unknown[]; autoTranslate: boolean }): void {
    saved.value = Array.isArray(data.copy) ? {} : data.copy
    autoTranslate.value = data.autoTranslate
    for (const key of Object.keys(drafts)) delete drafts[key]
}

const draftOf = (key: string): string => drafts[key] ?? saved.value[key] ?? ''
const dirty = (key: string): boolean => key in drafts && drafts[key].trim() !== (saved.value[key] ?? '')
/** `{n}`-style blanks the app fills in; a rewrite must keep them. */
function missingBlanks(key: string): string[] {
    const text = draftOf(key)
    if (!text.trim()) return []
    return (builtIn(key)?.match(/\{\w+\}/g) ?? []).filter((blank) => !text.includes(blank))
}

async function save(key: string, text: string): Promise<void> {
    busy.value = key
    try {
        apply(await request('PUT', '/admin/site-copy', { key, text: text.trim() }))
        await loadSiteCopy()
        ui.success(t(text.trim() ? 'admin.copySaved' : 'admin.copyReset'))
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = null
    }
}
</script>

<template>
    <div class="stack">
        <div class="section-head">
            <div>
                <h3>{{ t('admin.copyHead') }}</h3>
                <p>{{ t('admin.copySub') }}</p>
            </div>
            <Type :size="18" class="gold" aria-hidden="true" />
        </div>

        <p class="copy-status" :class="{ 'copy-status--off': !autoTranslate }">
            <Languages :size="16" aria-hidden="true" />
            {{ t(autoTranslate ? 'admin.copyAutoOn' : 'admin.copyAutoOff') }}
        </p>

        <div class="head-actions">
            <nav class="tabs" :aria-label="t('admin.copyHead')">
                <button
                    v-for="id in SECTIONS"
                    :key="id"
                    type="button"
                    :class="{ tabs__active: section === id }"
                    :aria-pressed="section === id"
                    @click="section = id"
                >
                    {{ t(`admin.copySections.${id}`) }}
                </button>
            </nav>
            <label class="copy-search">
                <Search :size="15" aria-hidden="true" />
                <input v-model="query" class="input" type="search" :placeholder="t('admin.copySearch')" />
            </label>
        </div>
        <p class="hint">{{ t('admin.copyCount', { n: changedCount }) }}</p>

        <ul v-if="loaded" class="copy-list">
            <li v-for="key in rows" :key="key" class="copy-row" :class="{ 'copy-row--changed': saved[key] }">
                <span class="copy-row__default">{{ builtIn(key) }}</span>
                <div class="copy-row__edit">
                    <input
                        :value="draftOf(key)"
                        class="input"
                        dir="auto"
                        maxlength="500"
                        :placeholder="t('admin.copyPh')"
                        :aria-label="builtIn(key) ?? key"
                        @input="drafts[key] = ($event.target as HTMLInputElement).value"
                        @keydown.enter.prevent="dirty(key) && save(key, drafts[key])"
                    />
                    <button
                        v-if="dirty(key)"
                        type="button"
                        class="btn btn--sm"
                        :disabled="busy === key || missingBlanks(key).length > 0"
                        @click="save(key, drafts[key])"
                    >
                        <Save :size="14" aria-hidden="true" /> {{ t('common.save') }}
                    </button>
                    <button
                        v-else-if="saved[key]"
                        type="button"
                        class="pill"
                        :disabled="busy === key"
                        @click="save(key, '')"
                    >
                        <RotateCcw :size="13" aria-hidden="true" /> {{ t('admin.copyRestore') }}
                    </button>
                </div>
                <small v-if="missingBlanks(key).length" class="field__error">{{
                    t('admin.copyBlanks', { blanks: missingBlanks(key).join(' ') })
                }}</small>
            </li>
            <li v-if="!rows.length" class="hint">{{ t('admin.copyNone') }}</li>
        </ul>
    </div>
</template>
