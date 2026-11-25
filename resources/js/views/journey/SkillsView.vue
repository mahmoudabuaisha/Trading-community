<script setup lang="ts">
import { BookOpen, Check, Lock, Swords } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import EmptyState from '../../components/EmptyState.vue'
import JourneyHead from '../../components/JourneyHead.vue'
import { ApiError } from '../../data/http'
import { repository } from '../../data/repository'
import { t } from '../../i18n'
import { percent } from '../../lib/dates'
import { useUiStore } from '../../stores/ui'
import type { SkillLevel, SkillPath } from '../../types/domain'

const ui = useUiStore()
const paths = ref<SkillPath[]>([])
const loaded = ref(false)
/** The level whose quiz or challenge is open, and the answers chosen so far. */
const openId = ref<number | null>(null)
const answers = reactive<Record<number, number>>({})
const busy = ref(false)

onMounted(async () => {
    try {
        paths.value = await repository.skills()
    } catch (error) {
        ui.failure(error)
    } finally {
        loaded.value = true
    }
})

const passedCount = (path: SkillPath) => path.levels.filter((level) => level.passed).length

function open(level: SkillLevel): void {
    openId.value = openId.value === level.id ? null : level.id
    for (const key of Object.keys(answers)) delete answers[Number(key)]
}

async function attempt(level: SkillLevel): Promise<void> {
    if (busy.value) return
    busy.value = true
    try {
        const result = await repository.attemptLevel(
            level.id,
            level.quiz.length ? level.quiz.map((_, index) => answers[index]) : undefined,
        )
        paths.value = result.paths
        if (result.passed) {
            openId.value = null
            ui.success(t('journey.skills.passed', { title: level.title }))
        } else {
            ui.failure(new ApiError(t('journey.skills.failed', { score: result.score, needed: result.needed }), 422))
        }
    } catch (error) {
        ui.failure(error)
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <section class="stack">
        <JourneyHead eyebrow="SKILL TREE" :title="t('journey.tiles.skills.title')" :text="t('journey.skills.sub')" />

        <article v-for="path in paths" :key="path.id" class="panel">
            <div class="section-head">
                <div>
                    <h3>{{ path.title }}</h3>
                    <p v-if="path.description">{{ path.description }}</p>
                </div>
                <span class="count-pill count-pill--gold">{{
                    t('journey.skills.level', { done: passedCount(path), total: path.levels.length })
                }}</span>
            </div>
            <div class="goal-card__bar">
                <i :style="{ inlineSize: `${percent(passedCount(path), path.levels.length || 1)}%` }" />
            </div>

            <ol class="skill-tree">
                <li
                    v-for="(level, index) in path.levels"
                    :key="level.id"
                    class="skill-node"
                    :class="{ 'skill-node--passed': level.passed, 'skill-node--locked': !level.unlocked }"
                >
                    <span class="skill-node__badge">
                        <Check v-if="level.passed" :size="18" :aria-label="t('journey.skills.done')" />
                        <Lock v-else-if="!level.unlocked" :size="16" :aria-label="t('journey.skills.locked')" />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <div class="skill-node__body">
                        <h4>{{ level.title }}</h4>
                        <p v-if="level.description && level.unlocked">{{ level.description }}</p>
                        <p v-if="!level.unlocked" class="hint">{{ t('journey.skills.lockedHint') }}</p>
                        <div v-if="level.unlocked" class="row-actions">
                            <RouterLink
                                v-if="level.lessonId"
                                :to="{ name: 'lesson', params: { id: level.lessonId } }"
                                class="pill"
                                ><BookOpen :size="13" aria-hidden="true" /> {{ t('journey.skills.lesson') }}</RouterLink
                            >
                            <button v-if="!level.passed" type="button" class="pill pill--chosen" @click="open(level)">
                                <Swords :size="13" aria-hidden="true" />
                                {{ t(level.quiz.length ? 'journey.skills.takeQuiz' : 'journey.skills.challenge') }}
                            </button>
                        </div>

                        <form v-if="openId === level.id" class="skill-quiz" @submit.prevent="attempt(level)">
                            <p v-if="level.challenge" class="skill-quiz__challenge">
                                <b>{{ t('journey.skills.challengeLabel') }}</b> {{ level.challenge }}
                            </p>
                            <fieldset
                                v-for="(question, questionIndex) in level.quiz"
                                :key="questionIndex"
                                class="perm-list condition-list"
                            >
                                <legend>{{ questionIndex + 1 }}. {{ question.question }}</legend>
                                <label
                                    v-for="(option, optionIndex) in question.options"
                                    :key="optionIndex"
                                    class="check"
                                >
                                    <input
                                        v-model="answers[questionIndex]"
                                        type="radio"
                                        :name="`q-${level.id}-${questionIndex}`"
                                        :value="optionIndex"
                                        required
                                    />
                                    {{ option }}
                                </label>
                            </fieldset>
                            <div class="actions">
                                <button type="submit" class="btn" :disabled="busy">
                                    {{ t(level.quiz.length ? 'journey.skills.submit' : 'journey.skills.confirm') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </li>
            </ol>
            <p v-if="!path.levels.length" class="hint">{{ t('journey.skills.noLevels') }}</p>
        </article>
        <EmptyState
            v-if="loaded && !paths.length"
            :title="t('journey.skills.emptyTitle')"
            :text="t('journey.skills.emptyText')"
        />
    </section>
</template>
