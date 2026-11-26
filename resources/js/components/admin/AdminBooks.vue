<script setup lang="ts">
import { BookOpen, FileUp, ImagePlus, Plus } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import type { Book } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'
import TranslateButton from './TranslateButton.vue'

/** Paid books: listed for every member, opened only for the subscribers chosen in their subscription. */
const ui = useUiStore()
const books = ref<Book[]>([])
const blank = () => ({ id: null as number | null, title: '', author: '', description: '', isPublished: true })
const draft = reactive(blank())
const cover = ref<File | null>(null)
const file = ref<File | null>(null)
const coverInput = ref<HTMLInputElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const deleting = ref<Book | null>(null)

async function run(action: () => Promise<Book[] | void>, successKey?: string): Promise<boolean> {
    try {
        books.value = (await action()) ?? (await repository.adminBooks())
        if (successKey) ui.success(t(successKey))
        return true
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        ui.failure(error)
        return false
    }
}
onMounted(() => run(() => repository.adminBooks()))

function reset(): void {
    Object.assign(draft, blank())
    cover.value = null
    file.value = null
    errors.value = {}
}
function edit(book: Book): void {
    Object.assign(draft, {
        id: book.id,
        title: book.title,
        author: book.author,
        description: book.description,
        isPublished: book.isPublished,
    })
    cover.value = null
    file.value = null
    errors.value = {}
}
async function save(): Promise<void> {
    if (!draft.title.trim() || saving.value) return
    saving.value = true
    errors.value = {}
    const { id, ...input } = draft
    if (
        await run(
            () => repository.adminSaveBook(id, { ...input, cover: cover.value, file: file.value }),
            'admin.bkSaved',
        )
    )
        reset()
    saving.value = false
}
const togglePublished = (book: Book) => run(() => repository.adminSaveBook(book.id, { isPublished: !book.isPublished }))
async function confirmDelete(): Promise<void> {
    const book = deleting.value
    deleting.value = null
    if (book) await run(() => repository.adminDeleteBook(book.id))
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">PAID LIBRARY</span>
                <h3>{{ t('admin.bkHead') }}</h3>
                <p>{{ t('admin.bkSub') }}</p>
            </div>
            <BookOpen :size="19" class="gold" aria-hidden="true" />
        </div>

        <form class="stack stack--tight" @submit.prevent="save">
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('admin.bkTitle') }}</span>
                    <input v-model="draft.title" class="input" required maxlength="160" />
                    <small v-if="errors.title" class="field__error">{{ errors.title[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.bkAuthor') }}</span>
                    <input v-model="draft.author" class="input" maxlength="120" />
                </label>
                <label class="field field--wide">
                    <span>{{ t('admin.bDescription') }}</span>
                    <textarea v-model="draft.description" class="input" rows="3" maxlength="5000" />
                </label>
            </div>
            <div class="head-actions">
                <button type="button" class="btn btn--outline" @click="coverInput?.click()">
                    <ImagePlus :size="15" aria-hidden="true" /> {{ cover?.name ?? t('admin.bkCover') }}
                </button>
                <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                    <FileUp :size="15" aria-hidden="true" />
                    {{ file?.name ?? t(draft.id === null ? 'admin.bkFile' : 'admin.bkReplaceFile') }}
                </button>
                <input
                    ref="coverInput"
                    class="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    tabindex="-1"
                    @change="cover = ($event.target as HTMLInputElement).files?.[0] ?? null"
                />
                <input
                    ref="fileInput"
                    class="sr-only"
                    type="file"
                    accept="application/pdf,.epub"
                    tabindex="-1"
                    @change="file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                />
                <small v-if="errors.file" class="field__error">{{ errors.file[0] }}</small>
                <small v-else class="hint">{{ t('admin.bkFileHint') }}</small>
            </div>
            <div class="head-actions">
                <label class="check"
                    ><input v-model="draft.isPublished" type="checkbox" /> {{ t('admin.visible') }}</label
                >
                <button type="submit" class="btn" :disabled="saving">
                    <Plus :size="15" aria-hidden="true" />
                    {{ saving ? t('common.saving') : t(draft.id === null ? 'admin.bkAdd' : 'admin.saveEdit') }}
                </button>
                <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="reset">
                    {{ t('admin.cancelEdit') }}
                </button>
            </div>
        </form>

        <div class="motivation-list">
            <article v-for="book in books" :key="book.id" class="motivation-item store-admin-item">
                <span class="book-cover book-cover--sm">
                    <img v-if="book.coverUrl" :src="book.coverUrl" alt="" loading="lazy" />
                    <BookOpen v-else :size="26" aria-hidden="true" />
                </span>
                <div>
                    <span class="count-pill" :class="{ 'count-pill--ok': book.isPublished }">{{
                        t(book.isPublished ? 'admin.published' : 'admin.draft')
                    }}</span>
                    <span v-if="!book.hasFile" class="count-pill count-pill--alert">{{ t('admin.bkNoFile') }}</span>
                    <h4>{{ book.title }}</h4>
                    <p v-if="book.author" class="muted">{{ book.author }}</p>
                </div>
                <div class="row-actions">
                    <a
                        v-if="book.hasFile"
                        class="pill"
                        :href="`/api/books/${book.id}/read`"
                        target="_blank"
                        rel="noopener"
                        >{{ t('books.read') }}</a
                    >
                    <button type="button" class="pill" @click="edit(book)">{{ t('common.edit') }}</button>
                    <TranslateButton type="book" :id="book.id" />
                    <button type="button" class="pill" @click="togglePublished(book)">
                        {{ t(book.isPublished ? 'admin.hide' : 'admin.publish') }}
                    </button>
                    <button type="button" class="pill" @click="deleting = book">{{ t('common.delete') }}</button>
                </div>
            </article>
            <EmptyState v-if="!books.length" :title="t('admin.bkEmptyTitle')" :text="t('admin.bkEmptyText')" />
        </div>
        <ConfirmDialog
            v-if="deleting"
            :message="t('admin.bkConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
