<script setup lang="ts">
import { FileUp, FolderPlus, ImagePlus, Plus, ShoppingBag, Star, Trash2 } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { applyTheme, themeTokens, themeWarnings } from '../../lib/theme'
import { useAuthStore } from '../../stores/auth'
import { t } from '../../i18n'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import { FRAME_STYLES, STORE_KINDS, type StoreCategory, type StoreItem, type StoreKind } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'
import StoreArt from '../StoreArt.vue'
import TranslateButton from './TranslateButton.vue'

const admin = useAdminStore()
const ui = useUiStore()
const auth = useAuthStore()

const blank = () => ({
    id: null as number | null,
    name: '',
    description: '',
    kind: 'title' as StoreKind,
    value: '',
    price: 100,
    isActive: true,
    categoryId: null as number | null,
    link: '',
    isExclusive: false,
    themeAccent: '#3fc48a',
    themeBackground: '#07080a',
    useBackground: false,
})

// ── Sections added by the administrators ("Books", "Courses", …) ─────────
const categories = ref<StoreCategory[]>([])
const newCategory = ref('')
const deletingCategory = ref<StoreCategory | null>(null)

async function runCategories(action: () => Promise<StoreCategory[] | void>): Promise<boolean> {
    try {
        categories.value = (await action()) ?? (await repository.adminStoreCategories())
        return true
    } catch (error) {
        ui.failure(error)
        return false
    }
}
onMounted(() => runCategories(() => repository.adminStoreCategories()))

async function addCategory(): Promise<void> {
    const name = newCategory.value.trim()
    if (name && (await runCategories(() => repository.adminSaveStoreCategory(null, name)))) newCategory.value = ''
}
async function renameCategory(category: StoreCategory, event: Event): Promise<void> {
    const name = (event.target as HTMLInputElement).value.trim()
    if (name && name !== category.name) await runCategories(() => repository.adminSaveStoreCategory(category.id, name))
}
async function confirmDeleteCategory(): Promise<void> {
    const category = deletingCategory.value
    deletingCategory.value = null
    if (category && (await runCategories(() => repository.adminDeleteStoreCategory(category.id))))
        await admin.loadStoreItems()
}
const categoryName = (id: number | null) => categories.value.find((category) => category.id === id)?.name
const draft = reactive(blank())
const image = ref<File | null>(null)
const preview = ref<string | null>(null)
const removeImage = ref(false)
/** A product's file, handed to buyers after purchase. */
const file = ref<File | null>(null)
const removeFile = ref(false)
const existingFile = ref<string | null>(null)
const productFile = ref<HTMLInputElement | null>(null)

// ── A theme: colours the app derives its shades from, and an optional picture ──
const themeImage = ref<File | null>(null)
const themePreview = ref<string | null>(null)
const removeThemeImage = ref(false)
const themeInput = ref<HTMLInputElement | null>(null)
const trying = ref(false)
const themeBase = computed(() => (draft.useBackground ? draft.themeBackground : null))
const warnings = computed(() => (draft.kind === 'theme' ? themeWarnings(draft.themeAccent, themeBase.value) : []))
const previewStyle = computed(() => ({
    ...themeTokens(draft.themeAccent, themeBase.value),
    ...(themePreview.value && !removeThemeImage.value ? { backgroundImage: `url(${themePreview.value})` } : {}),
}))
function pickThemeImage(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null
    input.value = ''
    if (!file) return
    if (themePreview.value?.startsWith('blob:')) URL.revokeObjectURL(themePreview.value)
    themeImage.value = file
    themePreview.value = URL.createObjectURL(file)
    removeThemeImage.value = false
}
/** Try the theme on the whole page; the member's own look comes back when trying stops. */
function stopTrying(): void {
    trying.value = false
    applyTheme(auth.user?.theme ?? null)
}
watch([trying, () => draft.themeAccent, themeBase], () => {
    if (trying.value) applyTheme({ accent: draft.themeAccent, background: themeBase.value })
})
onBeforeUnmount(() => trying.value && stopTrying())
const fileInput = ref<HTMLInputElement | null>(null)
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const deleting = ref<StoreItem | null>(null)

/** What `value` holds depends on the kind; switching kind starts it from a sensible choice. */
function onKind(): void {
    draft.value = draft.kind === 'frame' ? FRAME_STYLES[2] : ''
    if (draft.kind === 'product') draft.categoryId ??= categories.value[0]?.id ?? null
}

function setImage(file: File | null): void {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value)
    image.value = file
    preview.value = file ? URL.createObjectURL(file) : null
    removeImage.value = false
}

function pick(event: Event): void {
    const input = event.target as HTMLInputElement
    setImage(input.files?.[0] ?? null)
    input.value = ''
}

function reset(): void {
    Object.assign(draft, blank())
    setImage(null)
    file.value = null
    removeFile.value = false
    existingFile.value = null
    themeImage.value = null
    themePreview.value = null
    removeThemeImage.value = false
    if (trying.value) stopTrying()
    errors.value = {}
}

function edit(item: StoreItem): void {
    Object.assign(draft, {
        id: item.id,
        name: item.name,
        description: item.description,
        kind: item.kind,
        value: item.value ?? '',
        price: item.price,
        isActive: item.isActive,
        categoryId: item.categoryId,
        link: item.link ?? '',
        isExclusive: item.isExclusive,
        themeAccent: item.themeAccent ?? '#3fc48a',
        themeBackground: item.themeBackground ?? '#07080a',
        useBackground: item.themeBackground !== null,
    })
    themeImage.value = null
    themePreview.value = item.themeImageUrl
    removeThemeImage.value = false
    file.value = null
    removeFile.value = false
    existingFile.value = item.fileName
    setImage(null)
    preview.value = item.imageUrl
    errors.value = {}
}

async function save(): Promise<void> {
    if (!draft.name.trim() || saving.value) return
    saving.value = true
    errors.value = {}
    const { id, ...input } = draft
    try {
        await admin.saveStoreItem(id, {
            ...input,
            name: input.name.trim(),
            description: input.description.trim(),
            value: input.value.trim(),
            price: Math.max(0, Math.round(Number(input.price))),
            image: image.value,
            removeImage: removeImage.value,
            ...(input.kind === 'theme'
                ? {
                      themeAccent: input.themeAccent,
                      themeBackground: input.useBackground ? input.themeBackground : null,
                      themeImage: themeImage.value,
                      removeThemeImage: removeThemeImage.value,
                  }
                : {}),
            ...(input.kind === 'product'
                ? {
                      categoryId: input.categoryId,
                      link: input.link.trim(),
                      file: file.value,
                      removeFile: removeFile.value,
                  }
                : {}),
        })
        ui.success(t('admin.stSaved'))
        reset()
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        else ui.failure(error)
    } finally {
        saving.value = false
    }
}

async function toggle(item: StoreItem): Promise<void> {
    try {
        await admin.saveStoreItem(item.id, { isActive: !item.isActive })
        ui.success(t('admin.stSaved'))
    } catch (error) {
        ui.failure(error)
    }
}

async function confirmDelete(): Promise<void> {
    const item = deleting.value
    deleting.value = null
    if (!item) return
    try {
        await admin.deleteStoreItem(item.id)
        if (draft.id === item.id) reset()
        ui.success(t('admin.stDeleted'))
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">SN REWARDS / STORE</span>
                <h3>{{ t('admin.storeHead') }}</h3>
                <p>{{ t('admin.storeSub') }}</p>
            </div>
            <ShoppingBag :size="19" class="gold" aria-hidden="true" />
        </div>

        <div class="store-sections">
            <h4><FolderPlus :size="16" aria-hidden="true" /> {{ t('admin.scHead') }}</h4>
            <p class="hint">{{ t('admin.scSub') }}</p>
            <div v-for="category in categories" :key="category.id" class="condition-row">
                <input
                    class="input"
                    :value="category.name"
                    maxlength="60"
                    :aria-label="t('admin.scName')"
                    @change="renameCategory(category, $event)"
                />
                <TranslateButton type="store-category" :id="category.id" />
                <button
                    type="button"
                    class="icon-btn"
                    :aria-label="t('common.delete')"
                    @click="deletingCategory = category"
                >
                    <Trash2 :size="15" />
                </button>
            </div>
            <form class="broadcast-now" @submit.prevent="addCategory">
                <input
                    v-model="newCategory"
                    class="input"
                    maxlength="60"
                    :placeholder="t('admin.scPh')"
                    :aria-label="t('admin.scPh')"
                />
                <button type="submit" class="btn btn--outline">
                    <Plus :size="15" aria-hidden="true" /> {{ t('admin.scAdd') }}
                </button>
            </form>
        </div>

        <form class="stack stack--tight" @submit.prevent="save">
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('admin.stName') }}</span>
                    <input
                        v-model="draft.name"
                        class="input"
                        required
                        maxlength="80"
                        :placeholder="t('admin.stNamePh')"
                    />
                    <small v-if="errors.name" class="field__error">{{ errors.name[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.stPrice') }}</span>
                    <input v-model.number="draft.price" class="input" type="number" min="0" max="1000000" required />
                    <small v-if="errors.price" class="field__error">{{ errors.price[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.stKind') }}</span>
                    <select v-model="draft.kind" class="input" @change="onKind">
                        <option v-for="kind in STORE_KINDS" :key="kind" :value="kind">
                            {{ t(`store.kind.${kind}`) }}
                        </option>
                    </select>
                    <small class="hint">{{ t(`admin.stKindHint.${draft.kind}`) }}</small>
                </label>
                <label v-if="draft.kind === 'title'" class="field">
                    <span>{{ t('admin.stTitleText') }}</span>
                    <input
                        v-model="draft.value"
                        class="input"
                        required
                        maxlength="40"
                        :placeholder="t('admin.stTitlePh')"
                    />
                    <small v-if="errors.value" class="field__error">{{ errors.value[0] }}</small>
                </label>
                <label v-else-if="draft.kind === 'frame'" class="field">
                    <span>{{ t('admin.stFrameStyle') }}</span>
                    <select v-model="draft.value" class="input">
                        <option v-for="style in FRAME_STYLES" :key="style" :value="style">
                            {{ t(`store.frames.${style}`) }}
                        </option>
                        <option value="image">{{ t('store.frames.image') }}</option>
                    </select>
                    <small class="hint">{{
                        t(draft.value === 'image' ? 'admin.stFrameImageHint' : 'admin.stFrameStyleHint')
                    }}</small>
                    <small v-if="errors.image" class="field__error">{{ errors.image[0] }}</small>
                </label>
                <label v-else-if="draft.kind === 'emblem'" class="field">
                    <span>{{ t('admin.stEmblemValue') }}</span>
                    <input v-model="draft.value" class="input" maxlength="16" :placeholder="t('admin.stEmblemPh')" />
                    <small class="hint">{{ t('admin.stEmblemHint') }}</small>
                    <small v-if="errors.image" class="field__error">{{ errors.image[0] }}</small>
                </label>
                <template v-else-if="draft.kind === 'theme'">
                    <label class="field">
                        <span>{{ t('themes.accent') }}</span>
                        <input v-model="draft.themeAccent" class="input design-editor__color" type="color" />
                        <small class="hint">{{ t('themes.accentHint') }}</small>
                        <small v-if="errors.themeAccent" class="field__error">{{ errors.themeAccent[0] }}</small>
                    </label>
                    <div class="field">
                        <label class="check"
                            ><input v-model="draft.useBackground" type="checkbox" /> {{ t('themes.background') }}</label
                        >
                        <input
                            v-if="draft.useBackground"
                            v-model="draft.themeBackground"
                            class="input design-editor__color"
                            type="color"
                            :aria-label="t('themes.background')"
                        />
                        <small class="hint">{{ t('themes.backgroundHint') }}</small>
                    </div>
                    <div class="field field--wide">
                        <span>{{ t('themes.image') }}</span>
                        <div class="head-actions">
                            <button type="button" class="btn btn--outline" @click="themeInput?.click()">
                                {{ t(themePreview && !removeThemeImage ? 'admin.stChangeImage' : 'themes.imagePick') }}
                            </button>
                            <button
                                v-if="themePreview && !removeThemeImage"
                                type="button"
                                class="pill"
                                @click="((removeThemeImage = true), (themeImage = null))"
                            >
                                {{ t('admin.stRemoveImage') }}
                            </button>
                            <input
                                ref="themeInput"
                                class="sr-only"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                tabindex="-1"
                                @change="pickThemeImage"
                            />
                        </div>
                        <small v-if="errors.themeImage" class="field__error">{{ errors.themeImage[0] }}</small>
                        <small v-else class="hint">{{ t('themes.imageHint') }}</small>
                    </div>
                    <div class="field field--wide">
                        <span>{{ t('admin.dsPreview') }}</span>
                        <div class="theme-preview" :style="previewStyle">
                            <div class="theme-preview__side">
                                <b>SN</b>
                                <i class="theme-preview__nav theme-preview__nav--on" />
                                <i class="theme-preview__nav" />
                                <i class="theme-preview__nav" />
                            </div>
                            <div class="theme-preview__main">
                                <h4>{{ t('themes.previewTitle') }}</h4>
                                <div class="theme-preview__card">
                                    <span>{{ t('themes.previewCard') }}</span>
                                    <div class="goal-card__bar"><i style="inline-size: 64%" /></div>
                                    <div class="row-actions">
                                        <span class="btn">{{ t('themes.previewButton') }}</span>
                                        <span class="pill pill--chosen">25</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-for="warning in warnings" :key="warning" class="field__error">
                            ⚠ {{ t(`themes.warn.${warning}`) }}
                        </p>
                        <label class="check"
                            ><input
                                :checked="trying"
                                type="checkbox"
                                @change="($event.target as HTMLInputElement).checked ? (trying = true) : stopTrying()"
                            />
                            {{ t('themes.tryIt') }}</label
                        >
                    </div>
                </template>
                <template v-else-if="draft.kind === 'product'">
                    <label class="field">
                        <span>{{ t('admin.stCategory') }}</span>
                        <select v-model="draft.categoryId" class="input" required>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                        <small v-if="!categories.length" class="field__error">{{ t('admin.stNoCategory') }}</small>
                        <small v-else-if="errors.categoryId" class="field__error">{{ errors.categoryId[0] }}</small>
                    </label>
                    <label class="field">
                        <span>{{ t('admin.stLink') }}</span>
                        <input
                            v-model="draft.link"
                            class="input"
                            type="url"
                            dir="ltr"
                            maxlength="500"
                            placeholder="https://"
                        />
                        <small v-if="errors.link" class="field__error">{{ errors.link[0] }}</small>
                    </label>
                    <div class="field field--wide">
                        <span>{{ t('admin.stFile') }}</span>
                        <div class="head-actions">
                            <button type="button" class="btn btn--outline" @click="productFile?.click()">
                                <FileUp :size="15" aria-hidden="true" />
                                {{ file?.name ?? (existingFile && !removeFile ? existingFile : t('admin.stFilePick')) }}
                            </button>
                            <button
                                v-if="(existingFile && !removeFile) || file"
                                type="button"
                                class="pill"
                                @click="((file = null), (removeFile = true))"
                            >
                                {{ t('admin.stFileRemove') }}
                            </button>
                            <input
                                ref="productFile"
                                class="sr-only"
                                type="file"
                                tabindex="-1"
                                @change="
                                    ((file = ($event.target as HTMLInputElement).files?.[0] ?? null),
                                    (removeFile = false))
                                "
                            />
                        </div>
                        <small v-if="errors.file" class="field__error">{{ errors.file[0] }}</small>
                        <small v-else class="hint">{{ t('admin.stFileHint') }}</small>
                    </div>
                </template>
                <label class="field field--wide">
                    <span>{{ t('admin.bDescription') }}</span>
                    <input
                        v-model="draft.description"
                        class="input"
                        maxlength="240"
                        :placeholder="t('admin.stDescriptionPh')"
                    />
                </label>
            </div>

            <div class="head-actions">
                <StoreArt
                    :item="{
                        kind: draft.kind,
                        value: draft.value,
                        imageUrl: removeImage ? null : preview,
                        themeAccent: draft.themeAccent,
                        themeBackground: themeBase,
                        themeImageUrl: removeThemeImage ? null : themePreview,
                        name: draft.name,
                    }"
                />
                <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                    <ImagePlus :size="15" aria-hidden="true" />
                    {{ t(preview ? 'admin.stChangeImage' : 'admin.stAddImage') }}
                </button>
                <button
                    v-if="preview && !removeImage && draft.kind !== 'avatar'"
                    type="button"
                    class="pill"
                    @click="((removeImage = true), (image = null))"
                >
                    {{ t('admin.stRemoveImage') }}
                </button>
                <input
                    ref="fileInput"
                    class="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    tabindex="-1"
                    @change="pick"
                />
                <small v-if="errors.image" class="field__error">{{ errors.image[0] }}</small>
                <small v-else class="hint">{{
                    t(draft.kind === 'avatar' ? 'admin.stImageRequired' : 'admin.stImageHint')
                }}</small>
            </div>

            <div class="head-actions">
                <label class="check"
                    ><input v-model="draft.isActive" type="checkbox" /> {{ t('admin.stActive') }}</label
                >
                <label class="check" :title="t('admin.stExclusiveHint')"
                    ><input v-model="draft.isExclusive" type="checkbox" /> {{ t('admin.stExclusive') }}</label
                >
                <button type="submit" class="btn" :disabled="saving">
                    <Plus :size="15" aria-hidden="true" />
                    {{ saving ? t('common.saving') : t(draft.id === null ? 'admin.stAdd' : 'admin.saveEdit') }}
                </button>
                <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="reset">
                    {{ t('admin.cancelEdit') }}
                </button>
            </div>
        </form>

        <div class="motivation-list">
            <article v-for="item in admin.storeItems" :key="item.id" class="motivation-item store-admin-item">
                <StoreArt :item="item" />
                <div>
                    <span class="count-pill" :class="{ 'count-pill--ok': item.isActive }">
                        {{
                            item.kind === 'product'
                                ? (categoryName(item.categoryId) ?? t('store.kind.product'))
                                : t(`store.kind.${item.kind}`)
                        }}
                        ·
                        {{ t(item.isActive ? 'admin.stOnSale' : 'admin.stOffSale')
                        }}{{ item.isExclusive ? ` · ${t('admin.stExclusive')}` : '' }}
                    </span>
                    <span class="count-pill count-pill--gold"
                        ><Star :size="12" aria-hidden="true" /> {{ item.price }}</span
                    >
                    <h4>{{ item.name }}</h4>
                    <p v-if="item.description">{{ item.description }}</p>
                </div>
                <div class="row-actions">
                    <button type="button" class="pill" @click="edit(item)">{{ t('common.edit') }}</button>
                    <TranslateButton type="store-item" :id="item.id" />
                    <button type="button" class="pill" @click="toggle(item)">
                        {{ t(item.isActive ? 'admin.hide' : 'admin.publish') }}
                    </button>
                    <button type="button" class="pill" @click="deleting = item">{{ t('common.delete') }}</button>
                </div>
            </article>
            <EmptyState
                v-if="!admin.storeItems.length"
                :title="t('admin.stEmptyTitle')"
                :text="t('admin.stEmptyText')"
            />
        </div>

        <ConfirmDialog
            v-if="deletingCategory"
            :message="t('admin.scConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDeleteCategory"
            @cancel="deletingCategory = null"
        />

        <ConfirmDialog
            v-if="deleting"
            :message="t('admin.stConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
