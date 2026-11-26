<script setup lang="ts">
import { BookOpen, Check, Lock, Swords } from 'lucide-vue-next'
import { t } from '../../i18n'
import type { SkillLevel } from '../../types/domain'

/** The level picked on the tree or pyramid: what it teaches, its lesson, and its quiz or challenge. */
defineProps<{ level: SkillLevel; index: number; open: boolean; busy: boolean; answers: Record<number, number> }>()
const emit = defineEmits<{ open: []; attempt: [] }>()
</script>

<template>
    <div
        class="skill-node skill-node--detail"
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
                <RouterLink v-if="level.lessonId" :to="{ name: 'lesson', params: { id: level.lessonId } }" class="pill"
                    ><BookOpen :size="13" aria-hidden="true" /> {{ t('journey.skills.lesson') }}</RouterLink
                >
                <button v-if="!level.passed" type="button" class="pill pill--chosen" @click="emit('open')">
                    <Swords :size="13" aria-hidden="true" />
                    {{ t(level.quiz.length ? 'journey.skills.takeQuiz' : 'journey.skills.challenge') }}
                </button>
            </div>

            <form v-if="open" class="skill-quiz" @submit.prevent="emit('attempt')">
                <p v-if="level.challenge" class="skill-quiz__challenge">
                    <b>{{ t('journey.skills.challengeLabel') }}</b> {{ level.challenge }}
                </p>
                <fieldset
                    v-for="(question, questionIndex) in level.quiz"
                    :key="questionIndex"
                    class="perm-list condition-list"
                >
                    <legend>{{ questionIndex + 1 }}. {{ question.question }}</legend>
                    <label v-for="(option, optionIndex) in question.options" :key="optionIndex" class="check">
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
    </div>
</template>
