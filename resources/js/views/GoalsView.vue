<script setup lang="ts">
import { Minus, Plus, Target, Trash2 } from 'lucide-vue-next'
import { reactive, ref } from 'vue'
import EmptyState from '../components/EmptyState.vue'
import { t } from '../i18n'
import { periodRange } from '../lib/dates'
import { useUiStore } from '../stores/ui'
import { useWorkspaceStore } from '../stores/workspace'
import { PERIOD_TYPES, type Goal, type PeriodType } from '../types/domain'

const workspace = useWorkspaceStore()
const ui = useUiStore()

const form = reactive({ title: '', periodType: 'weekly' as PeriodType, targetValue: 5 })
const saving = ref(false)

async function add(): Promise<void> {
    if (!form.title.trim() || saving.value) return
    saving.value = true
    try {
        const range = periodRange(form.periodType, new Date())
        await workspace.createGoal({
            title: form.title.trim(),
            periodType: form.periodType,
            periodStart: range.start,
            periodEnd: range.end,
            targetValue: Math.max(1, Math.round(Number(form.targetValue)) || 1),
        })
        form.title = ''
        ui.success(t('goals.created'))
    } catch (error) {
        ui.failure(error)
    } finally {
        saving.value = false
    }
}

async function step(goal: Goal, delta: number): Promise<void> {
    try {
        await workspace.setGoalProgress(goal, goal.currentValue + delta)
    } catch (error) {
        ui.failure(error)
    }
}

async function remove(goal: Goal): Promise<void> {
    try {
        const undo = await workspace.deleteGoal(goal)
        ui.success(t('goals.deleted', { title: goal.title }), () => void undo().catch(ui.failure))
    } catch (error) {
        ui.failure(error)
    }
}
</script>

<template>
    <section class="stack">
        <div class="section-head">
            <div>
                <span class="eyebrow">DIRECTION / TARGETS</span>
                <h2>{{ t('goals.head') }}</h2>
                <p>{{ t('goals.sub') }}</p>
            </div>
        </div>

        <form class="inline-form" @submit.prevent="add">
            <input
                v-model="form.title"
                class="input"
                required
                maxlength="180"
                :placeholder="t('goals.ph')"
                :aria-label="t('goals.ph')"
            />
            <select v-model="form.periodType" class="input" :aria-label="t('tasks.fPeriod')">
                <option v-for="period in PERIOD_TYPES" :key="period" :value="period">
                    {{ t(`period.${period}`) }}
                </option>
            </select>
            <input
                v-model.number="form.targetValue"
                class="input input--narrow"
                type="number"
                min="1"
                max="100000"
                :aria-label="t('goals.target')"
            />
            <button type="submit" class="btn" :disabled="saving">
                <Plus :size="16" aria-hidden="true" /> {{ t('goals.add') }}
            </button>
        </form>

        <div v-if="workspace.goals.length" class="goal-grid">
            <div
                v-for="goal in workspace.goals"
                :key="goal.id"
                class="goal-card"
                :class="{ 'goal-card--done': goal.status === 'completed' }"
            >
                <div class="goal-card__top">
                    <span class="goal-card__icon"><Target :size="19" aria-hidden="true" /></span>
                    <span class="count-pill">{{ t(`period.${goal.periodType}`) }}</span>
                    <span v-if="goal.status === 'completed'" class="count-pill count-pill--ok">{{
                        t('goals.done')
                    }}</span>
                </div>
                <h3>{{ goal.title }}</h3>
                <p>{{ goal.description || t('goals.fallback') }}</p>
                <div class="track">
                    <i :style="{ inlineSize: `${Math.min(100, (goal.currentValue / goal.targetValue) * 100)}%` }" />
                </div>
                <div class="goal-card__foot">
                    <small>{{ t('goals.progress', { current: goal.currentValue, target: goal.targetValue }) }}</small>
                    <RouterLink :to="{ name: 'roadmap', params: { id: goal.id } }" class="pill">{{
                        t('journey.roadmap.open')
                    }}</RouterLink>
                    <div>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :disabled="goal.currentValue <= 0"
                            :aria-label="t('goals.decrease')"
                            @click="step(goal, -1)"
                        >
                            <Minus :size="13" />
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :disabled="goal.currentValue >= goal.targetValue"
                            :aria-label="t('goals.increase')"
                            @click="step(goal, 1)"
                        >
                            <Plus :size="13" />
                        </button>
                        <button
                            type="button"
                            class="icon-btn icon-btn--sm"
                            :aria-label="t('common.delete')"
                            @click="remove(goal)"
                        >
                            <Trash2 :size="13" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <EmptyState v-else :title="t('goals.emptyTitle')" :text="t('goals.emptyText')" />
    </section>
</template>
