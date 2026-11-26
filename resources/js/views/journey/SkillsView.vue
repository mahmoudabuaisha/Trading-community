<script setup lang="ts">
import { Palette } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import EmptyState from '../../components/EmptyState.vue'
import DesignPicker from '../../components/designs/DesignPicker.vue'
import SkillLevelCard from '../../components/designs/SkillLevelCard.vue'
import SkillShape from '../../components/designs/SkillShape.vue'
import { useDesignsStore } from '../../stores/designs'
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
const designs = useDesignsStore()
const treeDesign = computed(() => designs.current('skill_tree'))
const picking = ref(false)
/** The level shown under each path's drawing, by path. */
const chosen = reactive<Record<number, number>>({})

/** The picked level, or else the one to work on next (the first open one), or the last. */
function selectedLevel(path: SkillPath): SkillLevel | null {
    return (
        path.levels.find((level) => level.id === chosen[path.id]) ??
        path.levels.find((level) => level.unlocked && !level.passed) ??
        path.levels.at(-1) ??
        null
    )
}
function select(path: SkillPath, id: number): void {
    chosen[path.id] = id
    openId.value = null
}

onMounted(async () => {
    void designs.load().catch(ui.failure)
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
        <JourneyHead eyebrow="SKILL TREE" :title="t('journey.tiles.skills.title')" :text="t('journey.skills.sub')">
            <button type="button" class="btn btn--outline" @click="picking = true">
                <Palette :size="15" aria-hidden="true" /> {{ t('designs.choose') }}
            </button>
        </JourneyHead>
        <DesignPicker v-if="picking" target="skill_tree" @close="picking = false" />

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

            <SkillShape
                v-if="treeDesign && path.levels.length"
                :title="path.title"
                :levels="path.levels"
                :design="treeDesign"
                :selected-id="selectedLevel(path)?.id"
                @select="(id) => select(path, id)"
            />
            <SkillLevelCard
                v-if="selectedLevel(path)"
                :level="selectedLevel(path)!"
                :index="path.levels.indexOf(selectedLevel(path)!)"
                :open="openId === selectedLevel(path)!.id"
                :busy="busy"
                :answers="answers"
                @open="open(selectedLevel(path)!)"
                @attempt="attempt(selectedLevel(path)!)"
            />
            <p v-if="!path.levels.length" class="hint">{{ t('journey.skills.noLevels') }}</p>
        </article>
        <EmptyState
            v-if="loaded && !paths.length"
            :title="t('journey.skills.emptyTitle')"
            :text="t('journey.skills.emptyText')"
        />
    </section>
</template>
