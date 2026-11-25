<script setup lang="ts">
import { ArrowDown, ArrowUp, Check, CircleDot, Plus, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import EmptyState from '../../components/EmptyState.vue'
import JourneyHead from '../../components/JourneyHead.vue'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { percent } from '../../lib/dates'
import { useUiStore } from '../../stores/ui'
import { useWorkspaceStore } from '../../stores/workspace'
import type { GoalStep } from '../../types/domain'

const route = useRoute()
const workspace = useWorkspaceStore()
const ui = useUiStore()

const goalId = Number(route.params.id)
const goal = computed(() => workspace.goals.find((item) => item.id === goalId))
const steps = ref<GoalStep[]>([])
const loaded = ref(false)

const byPosition = (a: GoalStep, b: GoalStep) => a.position - b.position || a.id - b.id
/** Stages in order, each with its steps and how far along it is. */
const stages = computed(() =>
    steps.value
        .filter((step) => step.parentId === null)
        .sort(byPosition)
        .map((stage) => {
            const children = steps.value.filter((step) => step.parentId === stage.id).sort(byPosition)
            const done = children.length ? children.filter((step) => step.isDone).length : Number(stage.isDone)
            return {
                stage,
                children,
                pct: percent(done, children.length || 1),
                complete: children.length ? done === children.length : stage.isDone,
            }
        }),
)
const overall = computed(() =>
    percent(
        stages.value.reduce((sum, item) => sum + item.pct, 0),
        stages.value.length * 100 || 1,
    ),
)
/** The next thing to do: the first open step of the first unfinished stage (or that stage itself). */
const next = computed(() => {
    const open = stages.value.find((item) => !item.complete)
    return open ? (open.children.find((step) => !step.isDone) ?? open.stage) : null
})

async function run(action: () => Promise<unknown>): Promise<void> {
    try {
        const result = await action()
        if (Array.isArray(result)) steps.value = result as GoalStep[]
        else steps.value = await repository.roadmap(goalId)
    } catch (error) {
        ui.failure(error)
    }
}
onMounted(async () => {
    await run(() => repository.roadmap(goalId))
    loaded.value = true
})

const stageForm = reactive({ title: '', requirements: '' })
async function addStage(): Promise<void> {
    if (!stageForm.title.trim()) return
    await run(() =>
        repository.addStep(goalId, { title: stageForm.title.trim(), requirements: stageForm.requirements.trim() }),
    )
    Object.assign(stageForm, { title: '', requirements: '' })
}
const stepDrafts = reactive<Record<number, string>>({})
async function addStep(stageId: number): Promise<void> {
    const title = (stepDrafts[stageId] ?? '').trim()
    if (!title) return
    await run(() => repository.addStep(goalId, { title, parentId: stageId }))
    stepDrafts[stageId] = ''
}
const toggle = (step: GoalStep) => run(() => repository.updateStep(step.id, { isDone: !step.isDone }))
const move = (step: GoalStep, direction: 'up' | 'down') =>
    run(() => repository.updateStep(step.id, { move: direction }))
const remove = (step: GoalStep) => run(() => repository.deleteStep(step.id))
async function rename(step: GoalStep, event: Event): Promise<void> {
    const title = (event.target as HTMLInputElement).value.trim()
    if (title && title !== step.title) await run(() => repository.updateStep(step.id, { title }))
}
</script>

<template>
    <section class="stack">
        <JourneyHead
            eyebrow="GOAL ROADMAP"
            :title="goal?.title ?? t('journey.tiles.roadmap.title')"
            :text="t('journey.roadmap.sub')"
            back="goals"
        >
            <span class="points-chip">{{ overall }}%</span>
        </JourneyHead>

        <div v-if="next" class="panel roadmap-next">
            <CircleDot :size="22" class="gold" aria-hidden="true" />
            <div>
                <small>{{ t('journey.roadmap.next') }}</small>
                <b>{{ next.title }}</b>
            </div>
            <button type="button" class="btn" @click="toggle(next)">
                <Check :size="15" aria-hidden="true" /> {{ t('journey.roadmap.markDone') }}
            </button>
        </div>
        <div v-else-if="stages.length" class="panel roadmap-next">
            <Check :size="22" class="gold" aria-hidden="true" />
            <b>{{ t('journey.roadmap.allDone') }}</b>
        </div>

        <ol v-if="stages.length" class="roadmap">
            <li
                v-for="({ stage, children, pct, complete }, index) in stages"
                :key="stage.id"
                class="panel roadmap__stage"
                :class="{ 'roadmap__stage--done': complete }"
            >
                <div class="roadmap__head">
                    <span class="roadmap__index">{{ complete ? '✓' : index + 1 }}</span>
                    <div class="roadmap__title">
                        <input
                            class="input input--ghost"
                            :value="stage.title"
                            maxlength="180"
                            :aria-label="t('journey.roadmap.stageTitle')"
                            @change="rename(stage, $event)"
                        />
                        <small v-if="stage.requirements"
                            >{{ t('journey.roadmap.needs') }} {{ stage.requirements }}</small
                        >
                    </div>
                    <b>{{ pct }}%</b>
                    <div class="row-actions">
                        <button v-if="!children.length" type="button" class="pill" @click="toggle(stage)">
                            {{ t(stage.isDone ? 'journey.roadmap.reopen' : 'journey.roadmap.markDone') }}
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :disabled="index === 0"
                            :aria-label="t('journey.roadmap.up')"
                            @click="move(stage, 'up')"
                        >
                            <ArrowUp :size="14" />
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :disabled="index === stages.length - 1"
                            :aria-label="t('journey.roadmap.down')"
                            @click="move(stage, 'down')"
                        >
                            <ArrowDown :size="14" />
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :aria-label="t('common.delete')"
                            @click="remove(stage)"
                        >
                            <Trash2 :size="14" />
                        </button>
                    </div>
                </div>
                <div class="goal-card__bar"><i :style="{ inlineSize: `${pct}%` }" /></div>
                <ul class="roadmap__steps">
                    <li
                        v-for="(step, stepIndex) in children"
                        :key="step.id"
                        class="roadmap__step"
                        :class="{ 'roadmap__step--done': step.isDone }"
                    >
                        <label class="check"
                            ><input type="checkbox" :checked="step.isDone" @change="toggle(step)" />
                            <span>{{ step.title }}</span></label
                        >
                        <div class="row-actions">
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :disabled="stepIndex === 0"
                                :aria-label="t('journey.roadmap.up')"
                                @click="move(step, 'up')"
                            >
                                <ArrowUp :size="13" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :disabled="stepIndex === children.length - 1"
                                :aria-label="t('journey.roadmap.down')"
                                @click="move(step, 'down')"
                            >
                                <ArrowDown :size="13" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn icon-btn--sm"
                                :aria-label="t('common.delete')"
                                @click="remove(step)"
                            >
                                <Trash2 :size="13" />
                            </button>
                        </div>
                    </li>
                </ul>
                <form class="broadcast-now" @submit.prevent="addStep(stage.id)">
                    <input
                        v-model="stepDrafts[stage.id]"
                        class="input"
                        maxlength="180"
                        :placeholder="t('journey.roadmap.stepPh')"
                        :aria-label="t('journey.roadmap.stepPh')"
                    />
                    <button type="submit" class="btn btn--outline">
                        <Plus :size="15" aria-hidden="true" /> {{ t('journey.roadmap.addStep') }}
                    </button>
                </form>
            </li>
        </ol>
        <EmptyState
            v-else-if="loaded"
            :title="t('journey.roadmap.emptyTitle')"
            :text="t('journey.roadmap.emptyText')"
        />

        <form class="panel stack stack--tight" @submit.prevent="addStage">
            <h3>{{ t('journey.roadmap.addStageHead') }}</h3>
            <div class="form-grid form-grid--two">
                <input
                    v-model="stageForm.title"
                    class="input"
                    required
                    maxlength="180"
                    :placeholder="t('journey.roadmap.stagePh')"
                    :aria-label="t('journey.roadmap.stagePh')"
                />
                <input
                    v-model="stageForm.requirements"
                    class="input"
                    maxlength="500"
                    :placeholder="t('journey.roadmap.requirementsPh')"
                    :aria-label="t('journey.roadmap.requirementsPh')"
                />
            </div>
            <div class="actions">
                <button type="submit" class="btn">
                    <Plus :size="15" aria-hidden="true" /> {{ t('journey.roadmap.addStage') }}
                </button>
            </div>
        </form>
    </section>
</template>
