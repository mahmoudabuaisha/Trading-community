<script setup lang="ts">
import { Type } from 'lucide-vue-next'
import { reactive, ref, watch } from 'vue'
import { ApiError } from '../../data/http'
import { t } from '../../i18n'
import { ar } from '../../i18n/ar'
import { en } from '../../i18n/en'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import type { SiteContent } from '../../types/domain'

const admin = useAdminStore()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const form = reactive<Record<keyof SiteContent, string>>({
    taglineAr: '',
    taglineEn: '',
    welcomeSubAr: '',
    welcomeSubEn: '',
})
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

/** Each editable line, with the built-in text shown as the fallback. */
const fields: {
    key: keyof SiteContent
    group: 'cTagline' | 'cWelcome'
    lang: 'cArabic' | 'cEnglish'
    fallback: string
    dir: 'rtl' | 'ltr'
}[] = [
    { key: 'taglineAr', group: 'cTagline', lang: 'cArabic', fallback: ar.dash.tagline, dir: 'rtl' },
    { key: 'taglineEn', group: 'cTagline', lang: 'cEnglish', fallback: en.dash.tagline, dir: 'ltr' },
    { key: 'welcomeSubAr', group: 'cWelcome', lang: 'cArabic', fallback: ar.dash.welcomeSub, dir: 'rtl' },
    { key: 'welcomeSubEn', group: 'cWelcome', lang: 'cEnglish', fallback: en.dash.welcomeSub, dir: 'ltr' },
]

watch(
    () => admin.content,
    (content) => {
        for (const field of fields) form[field.key] = content[field.key] ?? ''
    },
    { immediate: true },
)

async function save(): Promise<void> {
    saving.value = true
    errors.value = {}
    try {
        await admin.saveContent({
            taglineAr: form.taglineAr.trim() || null,
            taglineEn: form.taglineEn.trim() || null,
            welcomeSubAr: form.welcomeSubAr.trim() || null,
            welcomeSubEn: form.welcomeSubEn.trim() || null,
        })
        // Show the change on this admin's own dashboard straight away.
        workspace.content = { ...admin.content }
        ui.success(t('admin.contentSaved'))
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form class="panel" @submit.prevent="save">
        <div class="section-head">
            <div>
                <span class="eyebrow">HOME PAGE / COPY</span>
                <h3>{{ t('admin.contentHead') }}</h3>
                <p>{{ t('admin.contentSub') }}</p>
            </div>
            <Type :size="19" class="gold" aria-hidden="true" />
        </div>
        <div class="form-grid form-grid--two">
            <label v-for="field in fields" :key="field.key" class="field">
                <span>{{ t(`admin.${field.group}`) }} — {{ t(`admin.${field.lang}`) }}</span>
                <input
                    v-model="form[field.key]"
                    class="input"
                    maxlength="160"
                    :dir="field.dir"
                    :placeholder="field.fallback"
                />
                <small v-if="errors[field.key]" class="field__error">{{ errors[field.key][0] }}</small>
                <small v-else class="hint">{{ t('admin.cDefault', { text: field.fallback }) }}</small>
            </label>
        </div>
        <div class="actions">
            <button type="submit" class="btn" :disabled="saving">
                {{ saving ? t('common.saving') : t('common.save') }}
            </button>
        </div>
    </form>
</template>
