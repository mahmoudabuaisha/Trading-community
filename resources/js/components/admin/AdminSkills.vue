<script setup lang="ts">
import { ArrowDown, ArrowUp, GitBranch, Plus, Trash2 } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { useAdminStore } from '../../stores/admin'
import { useUiStore } from '../../stores/ui'
import type { QuizQuestion, SkillLevel, SkillPath } from '../../types/domain'
import ConfirmDialog from '../ConfirmDialog.vue'
import EmptyState from '../EmptyState.vue'
import TranslateButton from './TranslateButton.vue'

/** Skill paths: each is a ladder of levels; a level can link a lesson, set a challenge and carry a quiz. */
const admin = useAdminStore()
const ui = useUiStore()
const paths = ref<SkillPath[]>([])
const confirm = ref<{ message: string; action: () => Promise<unknown> } | null>(null)

async function run(action: () => Promise<SkillPath[] | void>, successKey?: string): Promise<boolean> {
    try {
        paths.value = (await action()) ?? (await repository.adminSkillPaths())
        if (successKey) ui.success(t(successKey))
        return true
    } catch (error) {
        ui.failure(error)
        return false
    }
}
onMounted(() => run(() => repository.adminSkillPaths()))

const pathForm = reactive({ title: '', description: '' })
async function addPath(): Promise<void> {
    if (!pathForm.title.trim()) return
    if (
        await run(
            () =>
                repository.adminSavePath(null, {
                    title: pathForm.title.trim(),
                    description: pathForm.description.trim(),
                }),
            'admin.skSaved',
        )
    )
        Object.assign(pathForm, { title: '', description: '' })
}
const togglePath = (path: SkillPath) =>
    run(() => repository.adminSavePath(path.id, { isActive: !path.isActive }), 'admin.skSaved')

// One level editor at a time, inside its path.
const blank = () => ({
    pathId: null as number | null,
    id: null as number | null,
    title: '',
    description: '',
    challenge: '',
    lessonId: null as number | null,
    quiz: [] as Required<QuizQuestion>[],
})
const draft = reactive(blank())
function newLevel(path: SkillPath): void {
    Object.assign(draft, blank(), { pathId: path.id })
}
function editLevel(path: SkillPath, level: SkillLevel): void {
    Object.assign(draft, {
        pathId: path.id,
        id: level.id,
        title: level.title,
        description: level.description,
        challenge: level.challenge,
        lessonId: level.lessonId,
        quiz: level.quiz.map((question) => ({
            question: question.question,
            options: [...question.options],
            answer: question.answer ?? 0,
        })),
    })
}
async function saveLevel(): Promise<void> {
    if (draft.pathId === null || !draft.title.trim()) return
    const quiz = draft.quiz
        .map((question) => ({
            ...question,
            question: question.question.trim(),
            options: question.options.map((option) => option.trim()),
        }))
        .filter((question) => question.question)
    if (quiz.some((question) => question.options.some((option) => !option))) {
        ui.failure(new ApiError(t('admin.skEmptyOption'), 422))
        return
    }
    const { pathId, id } = draft
    if (
        await run(
            () =>
                repository.adminSaveLevel(pathId, id, {
                    title: draft.title.trim(),
                    description: draft.description.trim(),
                    challenge: draft.challenge.trim(),
                    lessonId: draft.lessonId,
                    quiz,
                }),
            'admin.skSaved',
        )
    )
        Object.assign(draft, blank())
}
const move = (path: SkillPath, level: SkillLevel, direction: 'up' | 'down') =>
    run(() => repository.adminSaveLevel(path.id, level.id, { move: direction }))

async function runConfirm(): Promise<void> {
    const pending = confirm.value
    confirm.value = null
    await pending?.action()
}
</script>

<template>
    <div class="panel">
        <div class="section-head">
            <div>
                <span class="eyebrow">SKILL TREE</span>
                <h3>{{ t('admin.skHead') }}</h3>
                <p>{{ t('admin.skSub') }}</p>
            </div>
            <GitBranch :size="19" class="gold" aria-hidden="true" />
        </div>

        <form class="broadcast-now" @submit.prevent="addPath">
            <input
                v-model="pathForm.title"
                class="input"
                required
                maxlength="120"
                :placeholder="t('admin.skPathPh')"
                :aria-label="t('admin.skPathPh')"
            />
            <input
                v-model="pathForm.description"
                class="input"
                maxlength="500"
                :placeholder="t('admin.bDescription')"
                :aria-label="t('admin.bDescription')"
            />
            <button type="submit" class="btn"><Plus :size="15" aria-hidden="true" /> {{ t('admin.skAddPath') }}</button>
        </form>

        <div class="motivation-list">
            <article v-for="path in paths" :key="path.id" class="skill-admin">
                <div class="motivation-item">
                    <div>
                        <span class="count-pill" :class="{ 'count-pill--ok': path.isActive }"
                            >{{ t(path.isActive ? 'admin.published' : 'admin.draft') }} ·
                            {{ t('admin.skLevels', { n: path.levels.length }) }}</span
                        >
                        <h4>{{ path.title }}</h4>
                        <p v-if="path.description">{{ path.description }}</p>
                    </div>
                    <div class="row-actions">
                        <button type="button" class="pill" @click="newLevel(path)">
                            <Plus :size="13" aria-hidden="true" /> {{ t('admin.skAddLevel') }}
                        </button>
                        <TranslateButton type="skill-path" :id="path.id" />
                        <button type="button" class="pill" @click="togglePath(path)">
                            {{ t(path.isActive ? 'admin.hide' : 'admin.publish') }}
                        </button>
                        <button
                            type="button"
                            class="pill"
                            @click="
                                confirm = {
                                    message: t('admin.skConfirmPath'),
                                    action: () => run(() => repository.adminDeletePath(path.id)),
                                }
                            "
                        >
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </div>
                <ol class="device-list">
                    <li v-for="(level, index) in path.levels" :key="level.id" class="device-row">
                        <b>{{ index + 1 }}</b>
                        <span>
                            <b>{{ level.title }}</b>
                            <small
                                >{{ t('admin.skLevelMeta', { n: level.quiz.length })
                                }}{{ level.lessonId ? ` · ${t('admin.skHasLesson')}` : ''
                                }}{{ level.challenge ? ` · ${t('admin.skHasChallenge')}` : '' }}</small
                            >
                        </span>
                        <div class="row-actions">
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :disabled="index === 0"
                                :aria-label="t('journey.roadmap.up')"
                                @click="move(path, level, 'up')"
                            >
                                <ArrowUp :size="13" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :disabled="index === path.levels.length - 1"
                                :aria-label="t('journey.roadmap.down')"
                                @click="move(path, level, 'down')"
                            >
                                <ArrowDown :size="13" />
                            </button>
                            <button type="button" class="pill" @click="editLevel(path, level)">
                                <TranslateButton type="skill-level" :id="level.id" />
                                {{ t('common.edit') }}
                            </button>
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :aria-label="t('common.delete')"
                                @click="
                                    confirm = {
                                        message: t('admin.skConfirmLevel'),
                                        action: () => run(() => repository.adminDeleteLevel(path.id, level.id)),
                                    }
                                "
                            >
                                <Trash2 :size="13" />
                            </button>
                        </div>
                    </li>
                </ol>

                <form
                    v-if="draft.pathId === path.id"
                    class="stack stack--tight skill-admin__form"
                    @submit.prevent="saveLevel"
                >
                    <h4>{{ t(draft.id === null ? 'admin.skNewLevel' : 'admin.skEditLevel') }}</h4>
                    <div class="form-grid form-grid--two">
                        <label class="field">
                            <span>{{ t('admin.skLevelTitle') }}</span>
                            <input v-model="draft.title" class="input" required maxlength="120" />
                        </label>
                        <label class="field">
                            <span>{{ t('admin.skLesson') }}</span>
                            <select v-model="draft.lessonId" class="input">
                                <option :value="null">{{ t('admin.skNoLesson') }}</option>
                                <option v-for="lesson in admin.lessons" :key="lesson.id" :value="lesson.id">
                                    {{ lesson.title }}
                                </option>
                            </select>
                        </label>
                        <label class="field field--wide">
                            <span>{{ t('admin.skLevelDescription') }}</span>
                            <textarea v-model="draft.description" class="input" rows="2" maxlength="5000" />
                        </label>
                        <label class="field field--wide">
                            <span>{{ t('admin.skChallenge') }}</span>
                            <input
                                v-model="draft.challenge"
                                class="input"
                                maxlength="500"
                                :placeholder="t('admin.skChallengePh')"
                            />
                        </label>
                    </div>
                    <fieldset
                        v-for="(question, questionIndex) in draft.quiz"
                        :key="questionIndex"
                        class="perm-list condition-list"
                    >
                        <legend>{{ t('admin.skQuestion', { n: questionIndex + 1 }) }}</legend>
                        <input
                            v-model="question.question"
                            class="input"
                            maxlength="300"
                            :placeholder="t('admin.skQuestionPh')"
                            :aria-label="t('admin.skQuestionPh')"
                        />
                        <label v-for="(_, optionIndex) in question.options" :key="optionIndex" class="condition-row">
                            <input
                                v-model="question.options[optionIndex]"
                                class="input"
                                maxlength="200"
                                :placeholder="t('admin.skOption', { n: optionIndex + 1 })"
                                :aria-label="t('admin.skOption', { n: optionIndex + 1 })"
                            />
                            <span class="check"
                                ><input
                                    v-model="question.answer"
                                    type="radio"
                                    :name="`answer-${questionIndex}`"
                                    :value="optionIndex"
                                />
                                {{ t('admin.skCorrect') }}</span
                            >
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :disabled="question.options.length <= 2"
                                :aria-label="t('common.delete')"
                                @click="
                                    (question.options.splice(optionIndex, 1),
                                    (question.answer = Math.min(question.answer, question.options.length - 1)))
                                "
                            >
                                <Trash2 :size="13" />
                            </button>
                        </label>
                        <div class="row-actions">
                            <button
                                v-if="question.options.length < 6"
                                type="button"
                                class="pill"
                                @click="question.options.push('')"
                            >
                                {{ t('admin.skAddOption') }}
                            </button>
                            <button type="button" class="pill" @click="draft.quiz.splice(questionIndex, 1)">
                                {{ t('admin.skRemoveQuestion') }}
                            </button>
                        </div>
                    </fieldset>
                    <div class="head-actions">
                        <button
                            type="button"
                            class="btn btn--outline"
                            @click="draft.quiz.push({ question: '', options: ['', ''], answer: 0 })"
                        >
                            <Plus :size="15" aria-hidden="true" /> {{ t('admin.skAddQuestion') }}
                        </button>
                        <button type="submit" class="btn">{{ t('common.save') }}</button>
                        <button type="button" class="btn btn--outline" @click="draft.pathId = null">
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                    <small class="hint">{{ t('admin.skPassHint') }}</small>
                </form>
            </article>
            <EmptyState v-if="!paths.length" :title="t('admin.skEmptyTitle')" :text="t('admin.skEmptyText')" />
        </div>

        <ConfirmDialog
            v-if="confirm"
            :message="confirm.message"
            :confirm-label="t('common.delete')"
            @confirm="runConfirm"
            @cancel="confirm = null"
        />
    </div>
</template>
