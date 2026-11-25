<script setup lang="ts">
import { GraduationCap, ImagePlus, Plus } from 'lucide-vue-next'
import { onBeforeUnmount, reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { t } from '../../i18n'
import { fileToLessonImage } from '../../lib/image'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import type { Lesson } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'

const admin = useAdminStore()
const ui = useUiStore()

const draft = reactive({
    id: null as number | null,
    title: '',
    body: '',
    isPublished: true,
    isPremium: false,
    removeImage: false,
})
const image = ref<Blob | null>(null)
/** What the form shows: a freshly chosen picture, or the lesson's stored one. */
const preview = ref<string | null>(null)
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const deleting = ref<Lesson | null>(null)

function revokePreview(): void {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value)
}
onBeforeUnmount(revokePreview)

function reset(): void {
    revokePreview()
    Object.assign(draft, { id: null, title: '', body: '', isPublished: true, isPremium: false, removeImage: false })
    image.value = null
    preview.value = null
    errors.value = {}
}

function edit(lesson: Lesson): void {
    reset()
    Object.assign(draft, {
        id: lesson.id,
        title: lesson.title,
        body: lesson.body,
        isPublished: lesson.isPublished,
        isPremium: lesson.isPremium,
    })
    preview.value = lesson.imageUrl
}

async function pickImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    const result = await fileToLessonImage(file)
    if (!result.ok) {
        ui.failure(new ApiError(t(`profile.avatar${result.reason[0].toUpperCase()}${result.reason.slice(1)}`), 422))
        return
    }
    revokePreview()
    image.value = result.blob
    preview.value = URL.createObjectURL(result.blob)
    draft.removeImage = false
}

function removeImage(): void {
    revokePreview()
    image.value = null
    preview.value = null
    draft.removeImage = true
}

async function save(): Promise<void> {
    if (!draft.title.trim() || !draft.body.trim() || saving.value) return
    saving.value = true
    errors.value = {}
    try {
        await admin.saveLesson(draft.id, {
            title: draft.title.trim(),
            body: draft.body.trim(),
            isPublished: draft.isPublished,
            isPremium: draft.isPremium,
            image: image.value,
            removeImage: draft.removeImage,
        })
        ui.success(t('admin.lSaved'))
        reset()
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}

async function toggle(lesson: Lesson): Promise<void> {
    try {
        await admin.saveLesson(lesson.id, { isPublished: !lesson.isPublished })
        ui.success(t('admin.lSaved'))
    } catch (error) {
        ui.failure(error)
    }
}

async function confirmDelete(): Promise<void> {
    const lesson = deleting.value
    deleting.value = null
    if (!lesson) return
    try {
        await admin.deleteLesson(lesson.id)
        if (draft.id === lesson.id) reset()
        ui.success(t('admin.lDeleted'))
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">ACADEMY / LESSONS</span>
                <h3>{{ t('admin.lessonsHead') }}</h3>
                <p>{{ t('admin.lessonsSub') }}</p>
            </div>
            <GraduationCap :size="19" class="gold" aria-hidden="true" />
        </div>

        <form class="stack stack--tight" @submit.prevent="save">
            <label class="field">
                <span>{{ t('admin.lTitle') }}</span>
                <input v-model="draft.title" class="input" required maxlength="180" />
                <small v-if="errors.title" class="field__error">{{ errors.title[0] }}</small>
            </label>
            <label class="field">
                <span>{{ t('admin.lBody') }}</span>
                <textarea v-model="draft.body" class="input" rows="6" required maxlength="20000" />
                <small v-if="errors.body" class="field__error">{{ errors.body[0] }}</small>
            </label>
            <div class="field">
                <span>{{ t('admin.lImage') }}</span>
                <img v-if="preview" :src="preview" alt="" class="lesson-form__preview" />
                <div class="head-actions">
                    <label class="btn btn--outline">
                        <ImagePlus :size="16" aria-hidden="true" /> {{ t('admin.lChooseImage') }}
                        <input
                            class="sr-only"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            @change="pickImage"
                        />
                    </label>
                    <button v-if="preview" type="button" class="btn btn--outline" @click="removeImage">
                        {{ t('admin.lRemoveImage') }}
                    </button>
                </div>
                <small v-if="errors.image" class="field__error">{{ errors.image[0] }}</small>
            </div>
            <div class="head-actions">
                <label class="check"
                    ><input v-model="draft.isPublished" type="checkbox" /> {{ t('admin.visible') }}</label
                >
                <label class="check"
                    ><input v-model="draft.isPremium" type="checkbox" /> {{ t('admin.lPremium') }}</label
                >
                <button type="submit" class="btn" :disabled="saving">
                    <Plus :size="15" aria-hidden="true" />
                    {{ saving ? t('common.saving') : t(draft.id === null ? 'admin.lAdd' : 'admin.saveEdit') }}
                </button>
                <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="reset">
                    {{ t('admin.cancelEdit') }}
                </button>
            </div>
        </form>

        <div class="motivation-list">
            <article v-for="lesson in admin.lessons" :key="lesson.id" class="motivation-item">
                <img v-if="lesson.imageUrl" :src="lesson.imageUrl" alt="" class="lesson-form__thumb" loading="lazy" />
                <div>
                    <span class="count-pill" :class="{ 'count-pill--ok': lesson.isPublished }">
                        {{ t(lesson.isPublished ? 'admin.published' : 'admin.draft')
                        }}{{ lesson.isPremium ? ` · ${t('admin.lPremiumTag')}` : '' }}
                    </span>
                    <h4>{{ lesson.title }}</h4>
                    <p class="clamp">{{ lesson.body }}</p>
                </div>
                <div class="row-actions">
                    <button type="button" class="pill" @click="edit(lesson)">{{ t('common.edit') }}</button>
                    <button type="button" class="pill" @click="toggle(lesson)">
                        {{ t(lesson.isPublished ? 'admin.hide' : 'admin.publish') }}
                    </button>
                    <button type="button" class="pill" @click="deleting = lesson">{{ t('common.delete') }}</button>
                </div>
            </article>
            <EmptyState v-if="!admin.lessons.length" :title="t('admin.lEmptyTitle')" :text="t('admin.lEmptyText')" />
        </div>

        <ConfirmDialog
            v-if="deleting"
            :message="t('admin.lConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
