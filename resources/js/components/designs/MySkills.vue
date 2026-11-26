<script setup lang="ts">
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'
import SkillShape from './SkillShape.vue'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { percent } from '../../lib/dates'
import { useDesignsStore } from '../../stores/designs'
import { useUiStore } from '../../stores/ui'
import type { SkillLevel, SkillPath } from '../../types/domain'

/**
 * The member's own skill paths: skills of their choosing, each with notes,
 * drawn with the design they picked and ticked off in any order.
 */
const ui = useUiStore()
const designs = useDesignsStore()
const treeDesign = computed(() => designs.current('skill_tree'))
const paths = ref<SkillPath[]>([])
const loaded = ref(false)
const busy = ref(false)

const pathForm = reactive({ id: null as number | null, title: '', description: '' })
const editingPath = ref(false)
/** The skill being added or edited, per path. */
const skillForm = reactive({ pathId: null as number | null, id: null as number | null, title: '', description: '' })
const chosen = reactive<Record<number, number>>({})
const deleting = ref<{ path: SkillPath; level?: SkillLevel } | null>(null)

onMounted(async () => {
    try {
        paths.value = await repository.mySkills()
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})

async function run(action: () => Promise<SkillPath[]>, done?: string): Promise<boolean> {
    if (busy.value) return false
    busy.value = true
    try {
        paths.value = await action()
        if (done) ui.success(t(done))
        return true
    } catch (error) {
        ui.failure(error)
        return false
    } finally {
        busy.value = false
    }
}

function newPath(): void {
    Object.assign(pathForm, { id: null, title: '', description: '' })
    editingPath.value = true
}
function editPath(path: SkillPath): void {
    Object.assign(pathForm, { id: path.id, title: path.title, description: path.description })
    editingPath.value = true
}
async function savePath(): Promise<void> {
    const input = { title: pathForm.title.trim(), description: pathForm.description.trim() }
    if (input.title && (await run(() => repository.saveMySkillPath(pathForm.id, input), 'mySkills.saved')))
        editingPath.value = false
}

function newSkill(path: SkillPath): void {
    Object.assign(skillForm, { pathId: path.id, id: null, title: '', description: '' })
}
function editSkill(path: SkillPath, level: SkillLevel): void {
    Object.assign(skillForm, { pathId: path.id, id: level.id, title: level.title, description: level.description })
}
async function saveSkill(): Promise<void> {
    const input = { title: skillForm.title.trim(), description: skillForm.description.trim() }
    if (!input.title || skillForm.pathId === null) return
    if (await run(() => repository.saveMySkill(skillForm.pathId!, skillForm.id, input), 'mySkills.saved')) {
        skillForm.pathId = null
    }
}

function selected(path: SkillPath): SkillLevel | null {
    return (
        path.levels.find((level) => level.id === chosen[path.id]) ??
        path.levels.find((level) => !level.passed) ??
        path.levels.at(-1) ??
        null
    )
}

async function confirmDelete(): Promise<void> {
    const target = deleting.value
    deleting.value = null
    if (!target) return
    await run(
        () =>
            target.level
                ? repository.deleteMySkill(target.path.id, target.level.id)
                : repository.deleteMySkillPath(target.path.id),
        'mySkills.deleted',
    )
}

const doneCount = (path: SkillPath) => path.levels.filter((level) => level.passed).length
</script>

<template>
    <div class="stack">
        <div class="head-actions">
            <button type="button" class="btn" @click="newPath">
                <Plus :size="15" aria-hidden="true" /> {{ t('mySkills.addPath') }}
            </button>
        </div>

        <form v-if="editingPath" class="panel form-grid" @submit.prevent="savePath">
            <label class="field">
                <span>{{ t('mySkills.pathTitle') }}</span>
                <input
                    v-model="pathForm.title"
                    class="input"
                    required
                    maxlength="120"
                    :placeholder="t('mySkills.pathTitlePh')"
                />
            </label>
            <label class="field">
                <span>{{ t('mySkills.pathDescription') }}</span>
                <textarea
                    v-model="pathForm.description"
                    class="input"
                    rows="2"
                    maxlength="1000"
                    :placeholder="t('mySkills.pathDescriptionPh')"
                />
            </label>
            <div class="actions">
                <button type="button" class="btn btn--outline" @click="editingPath = false">
                    {{ t('common.cancel') }}
                </button>
                <button type="submit" class="btn" :disabled="busy">{{ t('common.save') }}</button>
            </div>
        </form>

        <article v-for="path in paths" :key="path.id" class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ path.title }}</h3>
                    <p v-if="path.description" dir="auto" class="pre-line">{{ path.description }}</p>
                </div>
                <div class="row-actions">
                    <span class="count-pill count-pill--gold">{{
                        t('mySkills.count', { done: doneCount(path), total: path.levels.length })
                    }}</span>
                    <button
                        type="button"
                        class="icon-btn icon-btn--sm"
                        :aria-label="t('common.edit')"
                        @click="editPath(path)"
                    >
                        <Pencil :size="14" />
                    </button>
                    <button
                        type="button"
                        class="icon-btn icon-btn--sm"
                        :aria-label="t('common.delete')"
                        @click="deleting = { path }"
                    >
                        <Trash2 :size="14" />
                    </button>
                </div>
            </div>
            <div class="goal-card__bar">
                <i :style="{ inlineSize: `${percent(doneCount(path), path.levels.length || 1)}%` }" />
            </div>

            <SkillShape
                v-if="treeDesign && path.levels.length"
                :title="path.title"
                :levels="path.levels"
                :design="treeDesign"
                :selected-id="selected(path)?.id"
                @select="(id) => (chosen[path.id] = id)"
            />

            <div v-if="selected(path)" class="my-skill" :class="{ 'my-skill--done': selected(path)!.passed }">
                <div class="my-skill__head">
                    <b>{{ selected(path)!.title }}</b>
                    <div class="row-actions">
                        <button
                            type="button"
                            class="btn btn--sm"
                            :class="{ 'btn--outline': selected(path)!.passed }"
                            :disabled="busy"
                            @click="run(() => repository.toggleMySkill(path.id, selected(path)!.id))"
                        >
                            <Check :size="14" aria-hidden="true" />
                            {{ t(selected(path)!.passed ? 'mySkills.undo' : 'mySkills.markDone') }}
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :aria-label="t('common.edit')"
                            @click="editSkill(path, selected(path)!)"
                        >
                            <Pencil :size="14" />
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :aria-label="t('common.delete')"
                            @click="deleting = { path, level: selected(path)! }"
                        >
                            <Trash2 :size="14" />
                        </button>
                    </div>
                </div>
                <p v-if="selected(path)!.description" dir="auto" class="pre-line">{{ selected(path)!.description }}</p>
                <p v-else class="hint">{{ t('mySkills.noNotes') }}</p>
            </div>

            <form v-if="skillForm.pathId === path.id" class="form-grid my-skill__form" @submit.prevent="saveSkill">
                <label class="field">
                    <span>{{ t('mySkills.skillTitle') }}</span>
                    <input
                        v-model="skillForm.title"
                        class="input"
                        required
                        maxlength="120"
                        :placeholder="t('mySkills.skillTitlePh')"
                    />
                </label>
                <label class="field">
                    <span>{{ t('mySkills.notes') }}</span>
                    <textarea
                        v-model="skillForm.description"
                        class="input"
                        rows="4"
                        maxlength="5000"
                        :placeholder="t('mySkills.notesPh')"
                    />
                </label>
                <div class="actions">
                    <button type="button" class="btn btn--outline" @click="skillForm.pathId = null">
                        <X :size="14" /> {{ t('common.cancel') }}
                    </button>
                    <button type="submit" class="btn" :disabled="busy">{{ t('common.save') }}</button>
                </div>
            </form>
            <button v-else type="button" class="pill" @click="newSkill(path)">
                <Plus :size="14" aria-hidden="true" /> {{ t('mySkills.addSkill') }}
            </button>
        </article>

        <EmptyState
            v-if="loaded && !paths.length && !editingPath"
            :title="t('mySkills.emptyTitle')"
            :text="t('mySkills.emptyText')"
        />

        <ConfirmDialog
            v-if="deleting"
            :message="
                t(deleting.level ? 'mySkills.deleteSkill' : 'mySkills.deletePath', {
                    name: deleting.level ? deleting.level.title : deleting.path.title,
                })
            "
            :confirm-label="t('common.delete')"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </div>
</template>
