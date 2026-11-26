<script setup lang="ts">
import { ImagePlus, Palette, Plus } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useUiStore } from '../../stores/ui'
import type { Design, DesignTarget } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import GoalMap from '../designs/GoalMap.vue'
import SkillShape from '../designs/SkillShape.vue'
import TranslateButton from './TranslateButton.vue'

/** Designs of the goal map and the skill tree: a built-in shape dressed in a style, named and priced. */
const ui = useUiStore()
const designs = ref<Design[]>([])
const layouts = ref<Record<DesignTarget, string[]>>({ goal_map: [], skill_tree: [] })
const shapes = ref<string[]>([])
const blank = () => ({
    id: null as number | null,
    target: 'goal_map' as DesignTarget,
    layout: 'mindmap',
    name: '',
    description: '',
    price: 100,
    accent: '#d6ad62',
    nodeShape: 'circle',
    isActive: true,
})
const draft = reactive(blank())
const background = ref<File | null>(null)
const preview = ref<string | null>(null)
const removeBackground = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const errors = ref<Record<string, string[]>>({})
const deleting = ref<Design | null>(null)

async function run(
    action: () => Promise<{ designs: Design[]; layouts: Record<DesignTarget, string[]>; nodeShapes: string[] } | void>,
    successKey?: string,
): Promise<boolean> {
    try {
        const result = (await action()) ?? (await repository.adminDesigns())
        designs.value = result.designs
        layouts.value = result.layouts
        shapes.value = result.nodeShapes
        if (successKey) ui.success(t(successKey))
        return true
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) errors.value = error.errors
        ui.failure(error)
        return false
    }
}
onMounted(() => run(() => repository.adminDesigns()))

const previewDesign = computed(() => ({
    layout: draft.layout,
    accent: draft.accent,
    nodeShape: draft.nodeShape,
    backgroundUrl: removeBackground.value ? null : preview.value,
}))
const sampleStages = computed(() => [
    {
        id: 1,
        title: t('designs.sampleA'),
        isDone: true,
        children: [
            { id: 11, title: '1', isDone: true },
            { id: 12, title: '2', isDone: false },
        ],
    },
    { id: 2, title: t('designs.sampleB'), isDone: false, children: [{ id: 21, title: '1', isDone: false }] },
    { id: 3, title: t('designs.sampleC'), isDone: false, children: [] },
])
const sampleLevels = [1, 2, 3, 4, 5].map((n) => ({ id: n, title: `${n}`, passed: n <= 2, unlocked: n <= 3 }))

function onTarget(): void {
    draft.layout = layouts.value[draft.target][0]
    if (draft.target === 'skill_tree' && draft.nodeShape === 'circle') draft.nodeShape = 'apple'
}
function pick(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null
    input.value = ''
    if (!file) return
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value)
    background.value = file
    preview.value = URL.createObjectURL(file)
    removeBackground.value = false
}
function reset(): void {
    Object.assign(draft, blank())
    background.value = null
    preview.value = null
    removeBackground.value = false
    errors.value = {}
}
function edit(design: Design): void {
    Object.assign(draft, {
        id: design.id,
        target: design.target,
        layout: design.layout,
        name: design.name,
        description: design.description,
        price: design.price,
        accent: design.accent,
        nodeShape: design.nodeShape,
        isActive: design.isActive,
    })
    background.value = null
    preview.value = design.backgroundUrl
    removeBackground.value = false
    errors.value = {}
}
async function save(): Promise<void> {
    if (!draft.name.trim()) return
    errors.value = {}
    const { id, ...input } = draft
    if (
        await run(
            () =>
                repository.adminSaveDesign(id, {
                    ...input,
                    name: input.name.trim(),
                    description: input.description.trim(),
                    background: background.value,
                    removeBackground: removeBackground.value,
                }),
            'admin.dsSaved',
        )
    )
        reset()
}
const toggle = (design: Design) => run(() => repository.adminSaveDesign(design.id, { isActive: !design.isActive }))
async function confirmDelete(): Promise<void> {
    const design = deleting.value
    deleting.value = null
    if (design) await run(() => repository.adminDeleteDesign(design.id))
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">MAP &amp; TREE DESIGNS</span>
                <h3>{{ t('admin.dsHead') }}</h3>
                <p>{{ t('admin.dsSub') }}</p>
            </div>
            <Palette :size="19" class="gold" aria-hidden="true" />
        </div>

        <form class="design-editor" @submit.prevent="save">
            <div class="form-grid form-grid--two">
                <label class="field">
                    <span>{{ t('admin.dsTarget') }}</span>
                    <select v-model="draft.target" class="input" @change="onTarget">
                        <option value="goal_map">{{ t('designs.target.goal_map') }}</option>
                        <option value="skill_tree">{{ t('designs.target.skill_tree') }}</option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('admin.dsLayout') }}</span>
                    <select v-model="draft.layout" class="input">
                        <option v-for="layout in layouts[draft.target]" :key="layout" :value="layout">
                            {{ t(`designs.layout.${layout}`) }}
                        </option>
                    </select>
                    <small v-if="errors.layout" class="field__error">{{ errors.layout[0] }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.dsName') }}</span>
                    <input v-model="draft.name" class="input" required maxlength="80" />
                </label>
                <label class="field">
                    <span>{{ t('admin.stPrice') }}</span>
                    <input v-model.number="draft.price" class="input" type="number" min="0" max="1000000" required />
                    <small class="hint">{{ t('admin.dsFreeHint') }}</small>
                </label>
                <label class="field">
                    <span>{{ t('admin.dsShape') }}</span>
                    <select v-model="draft.nodeShape" class="input">
                        <option v-for="shape in shapes" :key="shape" :value="shape">
                            {{ t(`designs.shape.${shape}`) }}
                        </option>
                    </select>
                </label>
                <label class="field">
                    <span>{{ t('admin.dsAccent') }}</span>
                    <input v-model="draft.accent" class="input design-editor__color" type="color" />
                </label>
                <label class="field field--wide">
                    <span>{{ t('admin.bDescription') }}</span>
                    <input v-model="draft.description" class="input" maxlength="240" />
                </label>
            </div>
            <div class="head-actions">
                <button type="button" class="btn btn--outline" @click="fileInput?.click()">
                    <ImagePlus :size="15" aria-hidden="true" />
                    {{ t(preview && !removeBackground ? 'admin.dsChangeBg' : 'admin.dsAddBg') }}
                </button>
                <button
                    v-if="preview && !removeBackground"
                    type="button"
                    class="pill"
                    @click="((removeBackground = true), (background = null))"
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
                <label class="check"
                    ><input v-model="draft.isActive" type="checkbox" /> {{ t('admin.stActive') }}</label
                >
            </div>
            <div class="design-editor__preview">
                <small class="hint">{{ t('admin.dsPreview') }}</small>
                <GoalMap
                    v-if="draft.target === 'goal_map'"
                    :title="t('designs.sampleGoal')"
                    :stages="sampleStages"
                    :design="previewDesign"
                    compact
                />
                <SkillShape
                    v-else
                    :title="t('designs.sampleSkill')"
                    :levels="sampleLevels"
                    :design="previewDesign"
                    compact
                />
            </div>
            <div class="head-actions">
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" /> {{ t(draft.id === null ? 'admin.dsAdd' : 'admin.saveEdit') }}
                </button>
                <button v-if="draft.id !== null" type="button" class="btn btn--outline" @click="reset">
                    {{ t('admin.cancelEdit') }}
                </button>
            </div>
        </form>

        <div class="motivation-list">
            <article v-for="design in designs" :key="design.id" class="motivation-item">
                <div>
                    <span class="count-pill" :class="{ 'count-pill--ok': design.isActive }"
                        >{{ t(`designs.target.${design.target}`) }} · {{ t(`designs.layout.${design.layout}`) }}</span
                    >
                    <span class="count-pill count-pill--gold">{{
                        design.price === 0 ? t('designs.free') : t('rewards.count', { n: design.price })
                    }}</span>
                    <h4><span class="design-swatch" :style="{ background: design.accent }" /> {{ design.name }}</h4>
                    <p v-if="design.description" class="muted">{{ design.description }}</p>
                </div>
                <div class="row-actions">
                    <button type="button" class="pill" @click="edit(design)">{{ t('common.edit') }}</button>
                    <TranslateButton type="design" :id="design.id" />
                    <button type="button" class="pill" @click="toggle(design)">
                        {{ t(design.isActive ? 'admin.hide' : 'admin.publish') }}
                    </button>
                    <button type="button" class="pill" @click="deleting = design">{{ t('common.delete') }}</button>
                </div>
            </article>
        </div>
        <ConfirmDialog
            v-if="deleting"
            :message="t('admin.dsConfirmDelete')"
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
