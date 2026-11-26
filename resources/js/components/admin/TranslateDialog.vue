<script setup lang="ts">
import { Languages, X } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { repository } from '../../data/repository'
import { LOCALE_NAMES, t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import type { Locale, TranslationSheet } from '../../types/domain'

/**
 * Other-language versions of one piece of admin-written content. Members see
 * the version for their language; a language left empty shows the original.
 */
const props = defineProps<{ type: string; id: number }>()
const emit = defineEmits<{ close: [] }>()
const ui = useUiStore()

const dialog = ref<HTMLDialogElement | null>(null)
const sheet = ref<TranslationSheet | null>(null)
const values = reactive<Record<string, Record<string, string>>>({})
const saving = ref(false)

function fill(next: TranslationSheet): void {
    sheet.value = next
    for (const field of next.fields) values[field.field] = { ...field.translations }
}

onMounted(async () => {
    dialog.value?.showModal()
    try {
        fill(await repository.adminTranslations(props.type, props.id))
    } catch (error) {
        ui.failure(error)
        emit('close')
    }
})

async function save(): Promise<void> {
    saving.value = true
    try {
        fill(await repository.adminSaveTranslations(props.type, props.id, values))
        ui.success(t('admin.trSaved'))
        emit('close')
    } catch (error) {
        ui.failure(error)
    } finally {
        saving.value = false
    }
}

const long = (text: string) => text.length > 80 || text.includes('\n')
const dir = (locale: Locale) => (locale === 'ar' ? 'rtl' : 'ltr')
</script>

<template>
    <dialog ref="dialog" class="dialog dialog--wide" @cancel.prevent="emit('close')" @click.self="emit('close')">
        <form class="dialog__body" @submit.prevent="save">
            <div class="dialog__head">
                <h2><Languages :size="20" aria-hidden="true" /> {{ t('admin.trHead') }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </div>
            <p class="hint">{{ t('admin.trSub') }}</p>

            <fieldset v-for="field in sheet?.fields ?? []" :key="field.field" class="perm-list condition-list">
                <legend>{{ t('admin.trOriginal') }}</legend>
                <p class="translate-original" dir="auto">{{ field.original }}</p>
                <label v-for="locale in sheet!.locales" :key="locale" class="field">
                    <span>
                        {{ LOCALE_NAMES[locale] }}
                        <small v-if="field.stale.includes(locale)" class="field__error">{{ t('admin.trStale') }}</small>
                        <small v-else-if="field.auto.includes(locale)" class="hint">{{ t('admin.trAuto') }}</small>
                    </span>
                    <textarea
                        v-if="long(field.original)"
                        v-model="values[field.field][locale]"
                        class="input"
                        rows="4"
                        :dir="dir(locale)"
                        maxlength="20000"
                        :placeholder="t('admin.trPh')"
                    />
                    <input
                        v-else
                        v-model="values[field.field][locale]"
                        class="input"
                        :dir="dir(locale)"
                        maxlength="20000"
                        :placeholder="t('admin.trPh')"
                    />
                </label>
            </fieldset>

            <div class="actions">
                <button type="button" class="btn btn--outline" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="btn" :disabled="saving || !sheet">
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </div>
        </form>
    </dialog>
</template>
