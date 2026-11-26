<script setup lang="ts">
import { ImagePlus, Trash2, X } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError } from '../data/http'
import { repository } from '../data/repository'
import { formatDate, t } from '../i18n'
import { parseDay } from '../lib/dates'
import { useUiStore } from '../stores/ui'
import type { DayEntry } from '../types/domain'

/**
 * A picture and a note pinned to one calendar day. Shared by the habit
 * tracker and the command center's calendar, which read the same entries.
 */
const props = defineProps<{ day: string; entry: DayEntry | null }>()
const emit = defineEmits<{ close: []; saved: [entry: DayEntry | null] }>()
const ui = useUiStore()

const dialog = ref<HTMLDialogElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const note = ref(props.entry?.note ?? '')
const image = ref<File | null>(null)
const preview = ref<string | null>(props.entry?.imageUrl ?? null)
const removeImage = ref(false)
const saving = ref(false)
const label = formatDate(parseDay(props.day), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

onMounted(() => dialog.value?.showModal())
onBeforeUnmount(() => {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value)
})

function pick(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null
    input.value = ''
    if (!file) return
    if (file.size > 4 * 1024 * 1024) {
        ui.failure(new ApiError(t('habits.dayTooBig'), 422))
        return
    }
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value)
    image.value = file
    preview.value = URL.createObjectURL(file)
    removeImage.value = false
}

function dropImage(): void {
    image.value = null
    removeImage.value = true
}

async function save(): Promise<void> {
    saving.value = true
    try {
        const entry = await repository.saveDayEntry(props.day, {
            note: note.value.trim(),
            image: image.value,
            removeImage: removeImage.value,
        })
        emit('saved', entry)
        ui.success(t('habits.daySaved'))
        emit('close')
    } catch (error) {
        ui.failure(error)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <dialog ref="dialog" class="dialog" @cancel.prevent="emit('close')" @click.self="emit('close')">
        <form class="dialog__body" @submit.prevent="save">
            <div class="dialog__head">
                <h2>{{ label }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('common.close')" @click="emit('close')">
                    <X :size="18" />
                </button>
            </div>
            <img v-if="preview && !removeImage" :src="preview" alt="" class="day-dialog__image" />
            <div class="head-actions">
                <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                    <ImagePlus :size="15" aria-hidden="true" />
                    {{ t(preview && !removeImage ? 'habits.dayChangeImage' : 'habits.dayAddImage') }}
                </button>
                <button v-if="preview && !removeImage" type="button" class="pill" @click="dropImage">
                    <Trash2 :size="13" aria-hidden="true" /> {{ t('habits.dayRemoveImage') }}
                </button>
                <input
                    ref="fileInput"
                    class="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    tabindex="-1"
                    @change="pick"
                />
            </div>
            <label class="field">
                <span>{{ t('habits.dayNote') }}</span>
                <textarea v-model="note" class="input" rows="4" maxlength="1000" :placeholder="t('habits.dayNotePh')" />
            </label>
            <div class="actions">
                <button type="button" class="btn btn--outline" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="btn" :disabled="saving">
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </div>
        </form>
    </dialog>
</template>
